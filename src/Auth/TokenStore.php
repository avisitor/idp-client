<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Auth;

/**
 * TokenStore — single source of truth for persisting and refreshing the IDP JWT.
 *
 * Problem this solves: the IDP issues a JWT whose `exp` is governed by
 * IDP_TOKEN_EXPIRY_SECONDS (default 24h), but every consuming app previously
 * stored it only in $_SESSION. PHP's default session.gc_maxlifetime (1440s =
 * 24 minutes) garbage-collects that session, logging the user out long before
 * the token's own expiry.
 *
 * TokenStore persists the JWT in BOTH:
 *   - $_SESSION['jwt_token']   (fast, same request), and
 *   - a durable, HttpOnly `idp_jwt` cookie (survives session GC).
 *
 * get() reads session first, then the cookie. refresh() re-issues a fresh
 * token via the IDP's refresh endpoint (which re-signs even an expired token),
 * so the effective lifetime is governed by the IDP token, not the PHP session.
 */
class TokenStore
{
    public const COOKIE_NAME = 'idp_jwt';
    private const COOKIE_LIFETIME = 86400 * 30;

    /**
     * Persist the JWT to session + durable cookie. Pass null/'' to clear.
     */
    public static function store(?string $token): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($token === null || $token === '') {
            self::clear();
            return;
        }
        $_SESSION['jwt_token'] = $token;
        self::setCookie($token);
    }

    /**
     * Read the JWT: session first, then the durable cookie.
     */
    public static function get(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_SESSION['jwt_token'] ?? null;
        if (!empty($token)) {
            return $token;
        }
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }

    /**
     * Clear the JWT from both session and cookie.
     */
    public static function clear(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['jwt_token']);
        self::setCookie('');
    }

    /**
     * Decode a JWT payload WITHOUT verifying the signature. Used only to read
     * exp / sub / aud so we can decide when to refresh and recover identity.
     */
    public static function decode(?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * True when the token is missing, undecodable, has no exp, or is within
     * $lead seconds of expiry.
     */
    public static function needsRefresh(?string $token, int $lead = 600): bool
    {
        $payload = self::decode($token);
        if ($payload === null) {
            return true;
        }
        $exp = (int)($payload['exp'] ?? 0);
        if (!$exp) {
            return true;
        }
        return (time() + $lead) >= $exp;
    }

    /**
     * Re-issue a fresh JWT from the IDP refresh endpoint.
     *
     * NOTE: this is server-to-server (curl). The endpoint only allows CORS for
     * specific origins, so it must NOT be called from browser JS in most apps.
     * The endpoint re-signs even an EXPIRED token (it decodes the payload
     * without checking exp), so refresh works right up to the edge.
     *
     * @return string|null New token, or null on any failure (caller keeps old).
     */
    public static function refresh(string $token, string $appId, string $idpUrl): ?string
    {
        $payload = self::decode($token);
        $body = ['token' => $token, 'appId' => $appId];
        if (!empty($payload['aud'])) {
            $body['audience'] = $payload['aud'];
        }

        $ch = curl_init(rtrim($idpUrl, '/') . '/refresh-or-enhance-token.php');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $http !== 200 || $resp === false) {
            return null;
        }
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['token'])) {
            return null;
        }
        return $data['token'];
    }

    private static function setCookie(?string $token): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($token === null || $token === '') {
            setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', $secure, true);
            return;
        }
        setcookie(self::COOKIE_NAME, $token, time() + self::COOKIE_LIFETIME, '/', '', $secure, true);
    }
}
