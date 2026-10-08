<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Auth;

/**
 * TokenManager - Handles JWT enhancement and validation for IDP-aware apps.
 */

class TokenManager
{
    /**
     * Enhance a JWT token on the IDP side so it contains the requested roles.
     *
     * @param string|null $originalToken
     * @param string $userEmail
     * @param array $roles
     * @param string $appId
     * @param string $idpUrl
     * @return string|null
     */
    public function enhanceToken(?string $originalToken, string $userEmail, array $roles, string $appId, string $idpUrl, ?string $audience = null): ?string
    {
        if (empty($idpUrl)) {
            throw new \InvalidArgumentException('IDP URL is required for token enhancement');
        }

        if (empty($appId)) {
            throw new \InvalidArgumentException('App ID is required for token enhancement');
        }

        $enhanceUrl = rtrim($idpUrl, '/') . '/enhance-token.php';
        $payload = [
            'token' => $originalToken ?: '',
            'appId' => $appId,
            'claims' => [
                'roles' => array_values(array_unique($roles))
            ]
        ];
        if ($audience !== null) {
            $payload['audience'] = $audience;
        }

        try {
            $postData = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    'content' => $postData,
                    'timeout' => 10,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $response = @file_get_contents($enhanceUrl, false, $context);
            if ($response === false) {
                $error = error_get_last();
                error_log("[TokenManager] Failed to enhance token for {$userEmail}: " . ($error['message'] ?? 'unknown error'));
                return null;
            }

            $httpCode = 0;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                        $httpCode = (int)$matches[1];
                        break;
                    }
                }
            }

            if ($httpCode >= 400) {
                error_log("[TokenManager] HTTP {$httpCode} response enhancing token for {$userEmail}");
                error_log("[TokenManager] Response: {$response}");
                return null;
            }

            $data = json_decode($response, true);
            if (!is_array($data) || empty($data['token'])) {
                error_log("[TokenManager] Invalid enhancement response for {$userEmail}: {$response}");
                return null;
            }

            error_log("[TokenManager] Token enhanced for {$userEmail} with roles: " . implode(', ', $roles));
            return $data['token'];
        } catch (\Throwable $e) {
            error_log("[TokenManager] Exception enhancing token for {$userEmail}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check that the token has a roles claim and is not expired.
     *
     * @param string|null $token
     * @param string|null $idpUrl When provided, the token's signature is
     *   cryptographically verified against the IDP's published JWKS before
     *   any claim is trusted. Omitting it falls back to the legacy
     *   base64-only decode (unsafe — callers should always pass idpUrl).
     */
    public function tokenHasRoles(?string $token, ?string $idpUrl = null): bool
    {
        if (empty($token)) {
            return false;
        }

        $payload = $idpUrl !== null
            ? JwtVerifier::verify($token, $idpUrl)
            : $this->legacyDecodeUnverified($token);

        if (!is_array($payload)) {
            return false;
        }

        $exp = $payload['exp'] ?? 0;
        if ($exp > 0 && $exp <= time()) {
            return false;
        }

        return isset($payload['roles']) && is_array($payload['roles']);
    }

    /**
     * Attempt to return a valid token with the requested roles.
     *
     * @param array $options
     * @return string|null
     */
    public function getValidToken(array $options): ?string
    {
        $userEmail = $options['userEmail'] ?? null;
        $appId = $options['appId'] ?? null;
        $idpUrl = $options['idpUrl'] ?? null;
        $currentToken = $options['currentToken'] ?? null;
        $userInfoCallback = $options['userInfoCallback'] ?? null;
        $roleMapCallback = $options['roleMapCallback'] ?? null;

        if (empty($userEmail)) {
            throw new \InvalidArgumentException('userEmail is required to refresh the token');
        }
        if (empty($appId)) {
            throw new \InvalidArgumentException('App ID is required to refresh the token');
        }
        if (empty($idpUrl)) {
            throw new \InvalidArgumentException('IDP URL is required to refresh the token');
        }
        if (!is_callable($userInfoCallback)) {
            throw new \InvalidArgumentException('userInfoCallback must be callable');
        }
        if (!is_callable($roleMapCallback)) {
            throw new \InvalidArgumentException('roleMapCallback must be callable');
        }

        // Prefer the token persisted by TokenStore (session + durable cookie)
        // so a GC'd PHP session does not force a re-enhance on every request.
        $currentToken = $currentToken ?? TokenStore::get();

        if ($currentToken && $this->tokenHasRoles($currentToken, $idpUrl)) {
            return $currentToken;
        }

        $userInfo = call_user_func($userInfoCallback, $userEmail);
        if (empty($userInfo)) {
            error_log("[TokenManager] Unable to fetch user info for {$userEmail}");
            return $currentToken;
        }

        $adminLevel = $userInfo['admin'] ?? 0;
        $roles = call_user_func($roleMapCallback, $adminLevel);
        if (!is_array($roles)) {
            $roles = [];
        }

        error_log("[TokenManager] Enhancing token for {$userEmail} (admin={$adminLevel}) with roles: " . json_encode($roles));
        $enhancedToken = $this->enhanceToken($currentToken, $userEmail, $roles, $appId, $idpUrl);

        if ($enhancedToken) {
            // Persist through TokenStore so the token survives session GC.
            TokenStore::store($enhancedToken);
            if (isset($options['session']) && is_array($options['session'])) {
                $options['session']['admin'] = (string)$adminLevel;
                $options['session']['roles'] = $roles;
            }
        }

        return $enhancedToken ?: $currentToken;
    }

    /**
     * Decode a JWT payload, verifying its signature against the IDP's JWKS.
     *
     * SECURITY: prior to R11 this only base64-decoded the payload without
     * checking the signature, so any caller-supplied token was trusted
     * blindly. Always pass $idpUrl so the signature is actually checked.
     *
     * @param string $token
     * @param string|null $idpUrl IDP base URL used to fetch/verify against
     *   its JWKS. If omitted, falls back to the legacy unverified decode
     *   (unsafe — kept only for callers not yet updated to pass $idpUrl).
     * @return array|null
     */
    public function decodeToken(string $token, ?string $idpUrl = null): ?array
    {
        if (empty($token)) {
            return null;
        }

        if ($idpUrl !== null) {
            return JwtVerifier::verify($token, $idpUrl);
        }

        return $this->legacyDecodeUnverified($token);
    }

    /**
     * Legacy unverified decode — DOES NOT check the signature. Retained only
     * as a fallback for callers that have not yet been updated to supply an
     * IDP URL; do not use for anything security-sensitive.
     */
    private function legacyDecodeUnverified(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        return is_array($payload) ? $payload : null;
    }
}
