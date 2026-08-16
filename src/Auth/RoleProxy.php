<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Auth;

/**
 * RoleProxy — Self-contained authentication proxy + role-lookup endpoint handler.
 *
 * All generic logic lives here. An app wires it up with two injectable closures
 * and three env values, then calls handle().
 *
 * ─────────────────────────────────────────────────────────────────────────
 * THREE MODES  (dispatched automatically from $_GET)
 * ─────────────────────────────────────────────────────────────────────────
 *
 * MODE 1 – INITIATE   GET ?return=<url>&rsig=<hmac>
 *   Called by an external app's login page.
 *   rsig = HMAC-SHA256('return:' + url, secret) — prevents open-redirect abuse.
 *   • User already has a valid session here → redirects to <url> with signed
 *     result immediately (SSO-like skip of IDP login).
 *   • Otherwise → redirects to IDP, with this endpoint as the IDP return URL.
 *
 * MODE 2 – IDP CALLBACK   GET ?return=<url>&rsig=<hmac>&token=<jwt>
 *   IDP appends &token=<jwt> to whatever return URL it was given, which was
 *   this endpoint carrying ?return=...&rsig=... from MODE 1.
 *   • Validates JWT issuer and expiry.
 *   • Looks up role via $lookupRole closure.
 *   • Appends signed auth result to <url> and redirects.
 *
 * MODE 3 – DIRECT LOOKUP   GET ?token=<jwt>   (no ?return)
 *   Returns JSON {"email":"...","admin":N}.
 *   Kept for backward compatibility with direct API callers.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * SIGNED RESULT appended to callback URL (modes 1 & 2)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *   ?auth_email=<email>&auth_admin=<N>&auth_exp=<unix>&auth_sig=<hmac>
 *
 *   auth_sig = HMAC-SHA256(email . '|' . admin . '|' . exp, secret)
 *   Valid for 5 minutes — enough for one page load.
 *   The calling app verifies auth_sig with the same shared secret.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * MINIMUM REQUIRED IN EACH APP
 * ─────────────────────────────────────────────────────────────────────────
 *
 *   (new RoleProxy(
 *       secret:          getEnvVar('ROLE_API_SECRET'),
 *       idpUrl:          getEnvVar('IDP_URL'),
 *       appId:           getEnvVar('IDP_APP_ID'),
 *       getSessionEmail: fn() => /* email from this app's session, or null *\/ ,
 *       getSessionAdmin: fn() => /* admin/role int from this app's session *\/ ,
 *       lookupRole:      fn(string $email) => /* admin/role int from DB *\/ ,
 *   ))->handle();
 */
class RoleProxy
{
    private string   $secret;
    private string   $idpUrl;
    private string   $appId;
    private string   $expectedIssuer;
    private \Closure $getSessionEmail;
    private \Closure $getSessionAdmin;
    private \Closure $lookupRole;

    /**
     * @param string   $secret           Shared secret (ROLE_API_SECRET env var)
     * @param string   $idpUrl           IDP base URL (trailing slash stripped automatically)
     * @param string   $appId            IDP app ID registered for this application
     * @param \Closure $getSessionEmail  (): ?string  — currently logged-in email from this
     *                                   app's session, or null/''.
     * @param \Closure $getSessionAdmin  (): ?int     — admin/role integer from this app's
     *                                   session, or NULL if the admin level is not stored in
     *                                   the session (e.g. email-cookie auto-login without a
     *                                   full auth).  When NULL, lookupRole is called instead.
     * @param \Closure $lookupRole       (string $email): int  — admin/role integer from the
     *                                   app's DB for the given email.
     * @param string   $expectedIssuer   JWT 'iss' claim to accept
     *                                   (default: 'https://idp.worldspot.org')
     */
    public function __construct(
        string   $secret,
        string   $idpUrl,
        string   $appId,
        \Closure $getSessionEmail,
        \Closure $getSessionAdmin,
        \Closure $lookupRole,
        string   $expectedIssuer = 'https://idp.worldspot.org'
    ) {
        $this->secret          = $secret;
        $this->idpUrl          = rtrim($idpUrl, '/');
        $this->appId           = $appId;
        $this->getSessionEmail = $getSessionEmail;
        $this->getSessionAdmin = $getSessionAdmin;
        $this->lookupRole      = $lookupRole;
        $this->expectedIssuer  = $expectedIssuer;
    }

    /**
     * Dispatch the incoming request.  Always terminates via redirect or JSON output + exit.
     */
    public function handle(): void
    {
        header('Cache-Control: no-store, no-cache');

        $token  = trim($_GET['token']  ?? '');
        $return = trim($_GET['return'] ?? '');
        $rsig   = trim($_GET['rsig']   ?? '');

        if ($return === '') {
            $this->handleMode3($token);
        }

        $this->validateRsig($return, $rsig);

        if ($token === '') {
            $this->handleMode1($return);
        } else {
            $this->handleMode2($return, $token);
        }
    }

    // ── MODE 3: direct JSON lookup ────────────────────────────────────────

    private function handleMode3(string $token): void
    {
        header('Content-Type: application/json');

        if ($token === '') {
            http_response_code(400);
            echo json_encode(['error' => 'token parameter required']);
            exit;
        }

        $payload = $this->decodeJwt($token);
        if ($payload === null || empty($payload['sub'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Malformed token']);
            exit;
        }
        if (!$this->validateJwtClaims($payload)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        $email = (string) $payload['sub'];
        $admin = (int) ($this->lookupRole)($email);
        echo json_encode(['email' => $email, 'admin' => $admin]);
        exit;
    }

    // ── MODE 1: initiate ──────────────────────────────────────────────────

    private function handleMode1(string $return): void
    {
        $sessionEmail = (string) (($this->getSessionEmail)() ?? '');

        if ($sessionEmail !== '') {
            // User already logged in — skip IDP entirely (SSO-like)
            $admin = ($this->getSessionAdmin)();
            if ($admin === null) {
                // Email is known (e.g. cookie auto-login) but role was never stored in
                // this session — look it up from the DB instead of defaulting to 0.
                $admin = (int) ($this->lookupRole)($sessionEmail);
            }
            $this->redirectWithResult($return, $sessionEmail, $admin);
        }

        if ($this->idpUrl === '' || $this->appId === '') {
            http_response_code(503);
            echo 'IDP_URL or IDP_APP_ID is not configured on this server.';
            exit;
        }

        // Forward to IDP; IDP will append &token=JWT to $selfUrl (→ MODE 2)
        $params = [
            'app'    => $this->appId,
            'return' => $this->currentUrl(),   // carries ?return=...&rsig=...
        ];
        $audience = $_GET['audience'] ?? null;
        if ($audience !== null) {
            $params['audience'] = $audience;
        }
        header('Location: ' . $this->idpUrl . '/?' . http_build_query($params));
        exit;
    }

    // ── MODE 2: IDP callback ──────────────────────────────────────────────

    private function handleMode2(string $return, string $token): void
    {
        $payload = $this->decodeJwt($token);
        if ($payload === null || empty($payload['sub'])) {
            http_response_code(400);
            echo 'Malformed authentication token.';
            exit;
        }
        if (!$this->validateJwtClaims($payload)) {
            http_response_code(401);
            echo 'Invalid or expired authentication token.';
            exit;
        }

        $email = (string) $payload['sub'];
        $admin = (int) ($this->lookupRole)($email);
        $this->redirectWithResult($return, $email, $admin);
    }

    // ── Shared helpers ────────────────────────────────────────────────────

    private function validateRsig(string $return, string $rsig): void
    {
        if ($this->secret === '') {
            http_response_code(503);
            echo 'ROLE_API_SECRET is not configured on this server.';
            exit;
        }
        $expected = hash_hmac('sha256', 'return:' . $return, $this->secret);
        if ($rsig === '' || !hash_equals($expected, $rsig)) {
            http_response_code(403);
            echo 'Invalid return URL signature.';
            exit;
        }
    }

    private function redirectWithResult(string $return, string $email, int $admin): void
    {
        $exp = time() + 300; // 5-minute window
        $sig = hash_hmac('sha256', $email . '|' . $admin . '|' . $exp, $this->secret);
        $sep = str_contains($return, '?') ? '&' : '?';
        header('Location: ' . $return . $sep . http_build_query([
            'auth_email' => $email,
            'auth_admin' => $admin,
            'auth_exp'   => $exp,
            'auth_sig'   => $sig,
        ]));
        exit;
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $pad     = strlen($parts[1]) % 4;
        $b64     = $parts[1] . ($pad ? str_repeat('=', 4 - $pad) : '');
        $payload = json_decode(base64_decode(strtr($b64, '-_', '+/')), true);
        return is_array($payload) ? $payload : null;
    }

    private function validateJwtClaims(array $payload): bool
    {
        if (($payload['iss'] ?? '') !== $this->expectedIssuer) {
            return false;
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return false;
        }
        return true;
    }

    private function currentUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    }
}
