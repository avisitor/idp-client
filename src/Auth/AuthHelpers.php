<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * AuthHelpers - Factories for token enhancement and admin checks.
 */
class AuthHelpers
{
    public static function createTokenEnhancer(callable $roleMapCallback, string $appId, string $idpUrl): callable
    {
        if (empty($appId)) {
            throw new \InvalidArgumentException('App ID is required for token enhancement');
        }
        if (empty($idpUrl)) {
            throw new \InvalidArgumentException('IDP URL is required for token enhancement');
        }

        return function ($originalToken, string $userEmail, $adminLevel) use ($roleMapCallback, $appId, $idpUrl) {
            $mapper = $roleMapCallback;
            $roles = call_user_func($mapper, $adminLevel);
            if (!is_array($roles)) {
                $roles = [];
            }

            $tokenManager = new TokenManager();
            return $tokenManager->enhanceToken($originalToken, $userEmail, $roles ?? [], $appId, $idpUrl);
        };
    }

    public static function createTokenGetter(callable $userInfoCallback, callable $roleMapCallback, string $appId, string $idpUrl): callable
    {
        if (empty($appId)) {
            throw new \InvalidArgumentException('App ID is required for token getter');
        }
        if (empty($idpUrl)) {
            throw new \InvalidArgumentException('IDP URL is required for token getter');
        }

        return function ($userEmail = null) use ($userInfoCallback, $roleMapCallback, $appId, $idpUrl) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $email = $userEmail ?: ($_SESSION['email'] ?? $_SESSION['username'] ?? null);
            if (!$email) {
                error_log("[AuthHelpers] No user email available for JWT token retrieval");
                return null;
            }

            $currentToken = $_SESSION['jwt_token'] ?? null;
            $tokenManager = new TokenManager();

            try {
                $token = $tokenManager->getValidToken([
                    'userEmail' => $email,
                    'currentToken' => $currentToken,
                    'userInfoCallback' => $userInfoCallback,
                    'roleMapCallback' => $roleMapCallback,
                    'appId' => $appId,
                    'idpUrl' => $idpUrl,
                    'session' => &$_SESSION
                ]);
            } catch (\InvalidArgumentException $e) {
                error_log("[AuthHelpers] Token getter misconfigured: " . $e->getMessage());
                throw $e;
            }

            if ($token && $token !== $currentToken) {
                session_write_close();
                session_start();
            }

            return $token;
        };
    }

    public static function createUserProfileLoader(callable $userInfoCallback, callable $roleMapCallback, callable $tokenEnhancer): callable
    {
        return function (string $userEmail) use ($userInfoCallback, $roleMapCallback, $tokenEnhancer) {
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $userInfo = call_user_func($userInfoCallback, $userEmail);
                if (empty($userInfo)) {
                    return null;
                }

                $admin = $userInfo['admin'] ?? 0;
                $roles = call_user_func($roleMapCallback, $admin);
                if (!is_array($roles)) {
                    $roles = [];
                }

                $_SESSION['admin'] = (string)$admin;
                $_SESSION['email'] = $userEmail;
                $_SESSION['username'] = $userEmail;
                $_SESSION['loggedin'] = true;
                $_SESSION['roles'] = $roles;

                $userInfo['roles'] = $roles;

                $jwtToken = $_GET['token'] ?? $_GET['jwt'] ?? null;
                if ($jwtToken) {
                    $enhancedJwt = call_user_func($tokenEnhancer, $jwtToken, $userEmail, $admin);
                    $_SESSION['jwt_token'] = $enhancedJwt ?: $jwtToken;
                    error_log("[AuthHelpers] Stored " . ($enhancedJwt ? 'enhanced' : 'original') . " JWT token for {$userEmail}");
                }

                setcookie('email', $userEmail, time() + (86400 * 365), '/');

                error_log("[AuthHelpers] Loaded profile for {$userEmail} (admin={$admin}) with roles: " . implode(', ', $roles));
                return $userInfo;
            } catch (\Throwable $e) {
                error_log("[AuthHelpers] Error loading profile for {$userEmail}: " . $e->getMessage());
                return null;
            }
        };
    }

    public static function createAdminStatusChecker(callable $userInfoCallback, callable $roleMapCallback): callable
    {
        return function (string $userEmail, array $userRoles) use ($userInfoCallback, $roleMapCallback) {
            try {
                $userInfo = call_user_func($userInfoCallback, $userEmail);
                if (empty($userInfo)) {
                    return [];
                }

                $admin = $userInfo['admin'] ?? 0;
                $roles = call_user_func($roleMapCallback, $admin);
                if (!is_array($roles)) {
                    $roles = [];
                }

                error_log("[AuthHelpers] check_admin_status for {$userEmail} (admin={$admin}) returning roles: " . implode(', ', $roles));
                return $roles;
            } catch (\Throwable $e) {
                error_log("[AuthHelpers] Error checking admin status for {$userEmail}: " . $e->getMessage());
                return [];
            }
        };
    }
}
