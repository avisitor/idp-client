<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Auth;

/**
 * SessionRecovery — rebuilds $_SESSION from the durable IDP token.
 *
 * PHP's session GC (session.gc_maxlifetime) routinely discards a session long
 * before the IDP token expires, logging users out while TokenStore still holds
 * a usable token in its HttpOnly cookie. This class rehydrates the session
 * from that token, but only from claims that JwtVerifier has verified
 * (signature + exp) — never from a raw base64 decode. An expired-but-signed
 * token is refreshed through the IDP and the refreshed token is verified
 * before its claims are trusted.
 */
final class SessionRecovery
{
    /** @var callable(string): ?array */
    private $verify;

    /** @var callable(string): ?string */
    private $refresh;

    /**
     * @param callable(string): ?array $verify  Returns the verified payload.
     * @param callable(string): ?string $refresh Returns a new token, or null.
     */
    public function __construct(?callable $verify = null, ?callable $refresh = null)
    {
        $this->verify = $verify;
        $this->refresh = $refresh;
    }

    /**
     * Attempt to re-establish the session for $appId from the stored token.
     *
     * @return bool True when the session was hydrated from a verified token.
     */
    public function attempt(string $appId, string $idpUrl): bool
    {
        $token = TokenStore::get();
        if ($token === null || $token === '') {
            return false;
        }

        $verify = $this->verify ?? static fn (string $t): ?array => JwtVerifier::verify($t, $idpUrl);
        $refresh = $this->refresh ?? static fn (string $t): ?string => TokenStore::refresh($t, $appId, $idpUrl);

        $payload = $verify($token);

        if ($payload === null || TokenStore::needsRefresh($token, 600)) {
            $new = $refresh($token);
            if ($new === null || $new === '') {
                return false;
            }
            TokenStore::store($new);
            $token = $new;
            // A refreshed token carries appId but not necessarily roles; it
            // must still pass verification before any claim is trusted.
            $payload = $verify($token);
            if ($payload === null) {
                return false;
            }
        }

        if (($payload['appId'] ?? '') !== $appId) {
            return false;
        }
        if (empty($payload['sub'])) {
            return false;
        }

        $roles = $payload['roles'] ?? ['user'];
        if (!is_array($roles)) {
            $roles = ['user'];
        }

        $subject = (string) $payload['sub'];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $subject;
        $_SESSION['email'] = $subject;
        $_SESSION['roles'] = $roles;
        $_SESSION['is_admin'] = in_array('admin', $roles, true);
        $_SESSION['authenticated_at'] = time();

        // Refresh the session copy and the durable cookie with the token that
        // was actually verified.
        TokenStore::store($token);

        return true;
    }
}
