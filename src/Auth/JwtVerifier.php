<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/**
 * JwtVerifier - Fetches and caches the IDP's published JWKS, then verifies
 * a JWT's RS256 signature and standard claims before its payload is trusted.
 *
 * Fixes a security gap where consumers of idp-client only base64-decoded the
 * JWT payload without ever checking the cryptographic signature, meaning any
 * client could forge arbitrary claims (roles, email, etc).
 */
class JwtVerifier
{
    /** Default JWKS cache TTL in seconds (1 hour). */
    public const DEFAULT_CACHE_TTL = 3600;

    /**
     * Verify a JWT's signature against the IDP's JWKS and return the decoded
     * payload, or null if the token is malformed, unsigned, or fails
     * signature/claim verification.
     *
     * @param string $token
     * @param string $idpUrl Base URL of the IDP (JWKS is fetched from
     *                        {$idpUrl}/keys/jwks.json)
     * @param array{cacheTtl?: int, cacheFile?: string} $options
     * @return array|null
     */
    public static function verify(string $token, string $idpUrl, array $options = []): ?array
    {
        if ($token === '' || $idpUrl === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $jwks = self::fetchJwks($idpUrl, $options);
        if ($jwks === null) {
            return null;
        }

        try {
            $keySet = JWK::parseKeySet($jwks, 'RS256');
            $decoded = JWT::decode($token, $keySet);
            return json_decode((string)json_encode($decoded), true);
        } catch (Throwable $e) {
            error_log('[JwtVerifier] Signature/claim verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch the IDP's JWKS, using a local file cache to avoid a network
     * round-trip on every request.
     *
     * @return array|null Decoded JWKS ['keys' => [...]] or null on failure.
     */
    private static function fetchJwks(string $idpUrl, array $options): ?array
    {
        $ttl = $options['cacheTtl'] ?? self::DEFAULT_CACHE_TTL;
        $cacheFile = $options['cacheFile'] ?? (sys_get_temp_dir() . '/idp-client-jwks-' . md5($idpUrl) . '.json');

        if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['keys'])) {
                return $cached;
            }
        }

        $jwksUrl = rtrim($idpUrl, '/') . '/keys/jwks.json';

        try {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $response = @file_get_contents($jwksUrl, false, $context);
        } catch (Throwable $e) {
            $response = false;
        }

        if ($response === false) {
            // Network failure: fall back to a stale cache if we have one,
            // rather than failing every request.
            if (is_file($cacheFile)) {
                $stale = json_decode((string)file_get_contents($cacheFile), true);
                if (is_array($stale) && isset($stale['keys'])) {
                    return $stale;
                }
            }
            error_log("[JwtVerifier] Failed to fetch JWKS from {$jwksUrl}");
            return null;
        }

        $jwks = json_decode($response, true);
        if (!is_array($jwks) || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
            error_log("[JwtVerifier] Invalid JWKS response from {$jwksUrl}");
            return null;
        }

        @file_put_contents($cacheFile, $response);

        return $jwks;
    }
}
