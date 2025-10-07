<?php
/**
 * Silent JWT Token Refresh Manager
 * 
 * Provides automatic token refresh functionality for IDP-Client applications.
 * This handles expired tokens transparently without forcing users back to IDP.
 */

class TokenRefreshManager {
    private $config;
    private $idpUrl;
    private $appId;
    
    public function __construct($config = null) {
        if ($config) {
            $this->config = $config;
        } elseif (function_exists('getAuthConfig')) {
            $this->config = getAuthConfig();
        } else {
            // Fallback: try to load config from environment or sensible defaults
            $this->config = [
                'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?: 'https://idp.worldspot.org',
                'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID') ?: ($_SESSION['app_id'] ?? 'unknown-app')
            ];
        }
        
        $this->idpUrl = $this->config['idp_url'];
        $this->appId = $this->config['app_id'];
    }
    
    /**
     * Check if a JWT token is expired or will expire soon
     * 
     * @param string $token JWT token to check
     * @param int $bufferMinutes Minutes before expiration to consider "expired"
     * @return bool True if token is expired or expiring soon
     */
    public function isTokenExpired($token, $bufferMinutes = 5) {
        if (empty($token)) {
            return true;
        }
        
        try {
            // Parse JWT payload
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                error_log("[TokenRefresh] Invalid JWT format");
                return true;
            }
            
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!$payload || !isset($payload['exp'])) {
                error_log("[TokenRefresh] No expiration found in token");
                return true;
            }
            
            $exp = $payload['exp'];
            $now = time();
            $bufferSeconds = $bufferMinutes * 60;
            
            $isExpired = $exp < ($now + $bufferSeconds);
            
            if ($isExpired) {
                $timeUntilExp = $exp - $now;
                error_log("[TokenRefresh] Token expires in {$timeUntilExp} seconds (buffer: {$bufferSeconds}s)");
            }
            
            return $isExpired;
            
        } catch (Exception $e) {
            error_log("[TokenRefresh] Error checking token expiration: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Get a fresh enhanced JWT token from IDP using the combined endpoint
     * 
     * @param string $userEmail User email for token enhancement
     * @param array $roles Array of roles to include in token
     * @return string|null Fresh JWT token or null on failure
     */
    public function getRefreshedToken($userEmail, $roles = []) {
        $refreshUrl = $this->idpUrl . '/refresh-or-enhance-token.php';
        
        // Use existing token if available, even if expired (for user context)
        $existingToken = $_SESSION['jwt_token'] ?? null;
        
        $requestData = [
            'appId' => $this->appId,
            'email' => $userEmail,
            'claims' => [
                'roles' => $roles
            ]
        ];
        
        // Include existing token if available for context
        if ($existingToken) {
            $requestData['token'] = $existingToken;
        }
        
        try {
            $postData = json_encode($requestData);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    'content' => $postData,
                    'timeout' => 10
                ]
            ]);
            
            $response = file_get_contents($refreshUrl, false, $context);
            
            if ($response === false) {
                error_log("[TokenRefresh] Failed to refresh token from IDP for $userEmail");
                return null;
            }
            
            $data = json_decode($response, true);
            if (!$data || !isset($data['token'])) {
                error_log("[TokenRefresh] Invalid response from IDP refresh for $userEmail: $response");
                return null;
            }
            
            $action = $data['action'] ?? 'refreshed';
            error_log("[TokenRefresh] Successfully $action token for $userEmail with roles: " . implode(', ', $roles));
            return $data['token'];
            
        } catch (Exception $e) {
            error_log("[TokenRefresh] Error refreshing token for $userEmail: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Automatically refresh session JWT token if expired
     * 
     * @param string $userEmail User email
     * @param array $roles Array of roles for token enhancement
     * @return bool True if token was refreshed or is still valid
     */
    public function ensureValidSessionToken($userEmail, $roles = []) {
        $currentToken = $_SESSION['jwt_token'] ?? null;
        
        if ($this->isTokenExpired($currentToken)) {
            error_log("[TokenRefresh] Session token expired, refreshing for $userEmail");
            
            $newToken = $this->getRefreshedToken($userEmail, $roles);
            if ($newToken) {
                $_SESSION['jwt_token'] = $newToken;
                error_log("[TokenRefresh] Successfully updated session token for $userEmail");
                return true;
            } else {
                error_log("[TokenRefresh] Failed to refresh session token for $userEmail");
                return false;
            }
        }
        
        return true; // Token is still valid
    }
    
    /**
     * Get a valid JWT token, refreshing if necessary
     * 
     * @param string $userEmail User email
     * @param array $roles Array of roles for token enhancement
     * @return string|null Valid JWT token or null if refresh failed
     */
    public function getValidToken($userEmail, $roles = []) {
        $currentToken = $_SESSION['jwt_token'] ?? null;
        
        if (!$this->isTokenExpired($currentToken)) {
            return $currentToken; // Current token is still valid
        }
        
        // Token is expired, get a fresh one
        $newToken = $this->getRefreshedToken($userEmail, $roles);
        if ($newToken) {
            $_SESSION['jwt_token'] = $newToken;
            return $newToken;
        }
        
        return null;
    }
}

/**
 * Global convenience function to get a valid JWT token
 * 
 * @param string $userEmail User email (defaults to session email)
 * @param array $roles Array of roles for token enhancement
 * @return string|null Valid JWT token or null if unavailable
 */
function getValidJwtToken($userEmail = null, $roles = []) {
    static $tokenManager;
    
    if (!$tokenManager) {
        $tokenManager = new TokenRefreshManager();
    }
    
    $email = $userEmail ?: ($_SESSION['email'] ?? $_SESSION['auth_email'] ?? null);
    if (!$email) {
        error_log("[TokenRefresh] No user email available for token refresh");
        return null;
    }
    
    return $tokenManager->getValidToken($email, $roles);
}

/**
 * Ensure the session has a valid JWT token, refreshing if necessary
 * 
 * @param string $userEmail User email (defaults to session email)
 * @param array $roles Array of roles for token enhancement
 * @return bool True if valid token is available
 */
function ensureValidJwtToken($userEmail = null, $roles = []) {
    static $tokenManager;
    
    if (!$tokenManager) {
        $tokenManager = new TokenRefreshManager();
    }
    
    $email = $userEmail ?: ($_SESSION['email'] ?? $_SESSION['auth_email'] ?? null);
    if (!$email) {
        error_log("[TokenRefresh] No user email available for token refresh");
        return false;
    }
    
    return $tokenManager->ensureValidSessionToken($email, $roles);
}