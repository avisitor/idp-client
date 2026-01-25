<?php

namespace WorldSpot\IDPClient;

/**
 * IDP Manager - Identity Provider Client for Applications
 * Handles authentication delegation and user management with Identity Provider
 */
class IDPManager
{
    private $config;
    private $baseUrl;
    private $appId;
    private $tokenRefreshAttempts = 0; // Track refresh attempts to prevent infinite loops
    private $maxTokenRefreshAttempts = 2; // Maximum number of refresh attempts

    public function __construct($config = null, $environment = null)
    {
        // If environment is provided, set it before loading config
        if ($environment !== null) {
            foreach ($environment as $key => $value) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        if ($config === null) {
            $config = $this->getIDPConfig();
        }
        
        $this->config = $config;
        $this->baseUrl = $config['idp_url'];
        $this->appId = $config['app_id'];
    }

    /**
     * Get IDP configuration from environment
     */
    private function getIDPConfig()
    {
        $idpUrl = $_ENV['IDP_URL'] ?? getenv('IDP_URL');
        $appId = $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID');
        
        if (empty($idpUrl)) {
            throw new \Exception('IDP_URL not configured in environment');
        }
        if (empty($appId)) {
            throw new \Exception('IDP_APP_ID not configured in environment');
        }
        
        return [
            'idp_url' => $idpUrl,
            'app_id' => $appId
        ];
    }

    /**
     * Get application configuration from environment
     */
    public function getAppConfig()
    {
        return [
            'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Application',
            'domain' => $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?: 'localhost',
            'base_path' => $_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: '',
            'auth_path' => $_ENV['APP_AUTH_PATH'] ?? getenv('APP_AUTH_PATH') ?: '/auth',
            'email' => $_ENV['APP_EMAIL'] ?? getenv('APP_EMAIL') ?: 'noreply@localhost',
            'protocol' => $_ENV['APP_PROTOCOL'] ?? getenv('APP_PROTOCOL') ?: 'https'
        ];
    }

    /**
     * Build callback URL for IDP returns
     */
    public function buildCallbackUrl($redirect = null)
    {
        $appConfig = $this->getAppConfig();
        $protocol = $appConfig['protocol'];
        $host = $_SERVER['HTTP_HOST'] ?? $appConfig['domain'];
        $callbackPath = $appConfig['base_path'] . $appConfig['auth_path'] . '/idp-callback.php';
        
        $callbackUrl = $protocol . '://' . $host . $callbackPath;
        
        if ($redirect) {
            $callbackUrl .= '?redirect=' . urlencode($redirect);
        }
        
        return $callbackUrl;
    }

    /**
     * Build application URL
     */
    public function buildAppUrl($path = '')
    {
        $appConfig = $this->getAppConfig();
        $protocol = $appConfig['protocol'];
        $host = $_SERVER['HTTP_HOST'] ?? $appConfig['domain'];
        $basePath = $appConfig['base_path'];
        
        // Ensure proper slash handling
        $url = $protocol . '://' . $host . rtrim($basePath, '/');
        
        if ($path && $path !== '') {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Get IDP login URL for redirect-based authentication
     */
    public function getLoginUrl($returnUrl = null)
    {
        $params = ['app' => $this->appId];
        if ($returnUrl) {
            $params['return'] = $returnUrl;
        }
        return $this->baseUrl . '/?' . http_build_query($params);
    }

    /**
     * Authenticate user through IDP and set up session
     */
    public function authenticateUser($email, $password, $redirect = '/')
    {
        try {
            $returnUrl = $this->buildCallbackUrl($redirect);
            $loginUrl = $this->getLoginUrl($returnUrl);
            
            return [
                'success' => false, 
                'error' => 'Please use the IDP redirect flow. <a href="' . htmlspecialchars($loginUrl) . '">Click here to login via IDP</a>',
                'login_url' => $loginUrl
            ];
            
        } catch (\Exception $e) {
            error_log("IDP authentication error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication service unavailable'];
        }
    }

    /**
     * Register new user through IDP
     */
    public function registerUser($email, $password, $name = '', $redirect = '/')
    {
        try {
            $idpResponse = $this->register($email, $password, ['name' => $name]);
            
            if (!$idpResponse['success']) {
                return ['success' => false, 'error' => $idpResponse['error']];
            }
            
            $idpUser = $idpResponse['user'];
            
            // Local user profile management is handled by the application,
            // not by the IDP client
            
            error_log("User registration successful: $email (IDP ID: {$idpUser['id']})");
            
            return [
                'success' => true,
                'user' => $idpUser,
                'verification_required' => $idpResponse['verification_required'] ?? true,
                'message' => 'Registration successful! Please check your email for a verification link.'
            ];
            
        } catch (\Exception $e) {
            error_log("IDP registration error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Registration service temporarily unavailable'];
        }
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset($email)
    {
        $url = $this->baseUrl . '/api/password-reset';
        $data = [
            'app' => $this->appId,
            'email' => $email
        ];

        $response = $this->makeRequest('POST', $url, $data);
        
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Password reset request failed'];
        }

        return [
            'success' => true,
            'message' => 'Password reset email sent'
        ];
    }

    /**
     * Verify email with token
     */
    public function verifyEmail($token)
    {
        try {
            $response = $this->verifyEmailToken($token);
            
            if (!$response['success']) {
                return ['success' => false, 'error' => $response['error']];
            }
            
            $idpUser = $response['user'];
            
            // Local user profile management is handled by the application,
            // not by the IDP client
            
            error_log("Email verification successful for: " . $idpUser['email']);
            
            return [
                'success' => true,
                'user' => $idpUser,
                'message' => 'Email verified successfully! You can now log in.'
            ];
            
        } catch (\Exception $e) {
            error_log("IDP email verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Email verification service temporarily unavailable'];
        }
    }

    /**
     * Logout user
     */
    public function logoutUser($redirect = '/')
    {
        session_start();
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        error_log("User logged out");
        
        return [
            'success' => true,
            'redirect' => $redirect
        ];
    }

    /**
     * Register new user with IDP
     */
    public function register($email, $password, $userData = [])
    {
        $url = $this->baseUrl . '/api/register';
        $data = [
            'app' => $this->appId,
            'email' => $email,
            'password' => $password,
            'user_data' => $userData
        ];

        $response = $this->makeRequest('POST', $url, $data);
        
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Registration failed'];
        }

        return [
            'success' => true,
            'user' => $response['user'],
            'verification_required' => $response['verification_required'] ?? true
        ];
    }

    /**
     * Verify email token from IDP
     */
    public function verifyEmailToken($token)
    {
        $url = $this->baseUrl . '/api/verify';
        $data = [
            'app' => $this->appId,
            'token' => $token
        ];

        $response = $this->makeRequest('POST', $url, $data);
        
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Email verification failed'];
        }

        return [
            'success' => true,
            'user' => $response['user']
        ];
    }

    /**
     * Map application roles to resource-specific roles
     */
    public function mapRolesToResource($appRoles, $resource)
    {
        $mappings = [
            'mail-service' => [
                'superadmin' => 'superadmin',
                'admin' => 'superadmin',
                'tenant_admin' => 'tenant_admin',
                'tenantadmin' => 'tenant_admin',
                'editor' => 'editor',
                'user' => 'editor'
            ],
            'other-service' => [
                'superadmin' => 'manager',
                'admin' => 'manager', 
                'editor' => 'contributor'
            ]
        ];
        
        $resourceMappings = $mappings[$resource] ?? [];
        
        $resourceRoles = [];
        foreach ($appRoles as $appRole) {
            $mappedRole = $resourceMappings[$appRole] ?? 'user';
            if (!in_array($mappedRole, $resourceRoles)) {
                $resourceRoles[] = $mappedRole;
            }
        }
        
        return empty($resourceRoles) ? ['user'] : $resourceRoles;
    }

    /**
     * Get User Profile Manager instance
     */
    /**
     * Make HTTP request to IDP
     */
    private function makeRequest($method, $url, $data = null, $contentType = 'json')
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($data) {
            if ($contentType === 'form') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "Network error: $error"];
        }

        if ($contentType === 'form') {
            return [
                'success' => true,
                'body' => $response,
                'http_code' => $httpCode
            ];
        }

        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $responseData ?: ['success' => true];
        } else {
            $errorMsg = $responseData['error'] ?? $responseData['message'] ?? "HTTP $httpCode";
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Get a valid JWT token with automatic refresh and role enhancement
     * This is the centralized method that all resources should use
     * 
     * @param bool $forceRefresh Force refresh even if current token seems valid
     * @return string|null Valid JWT token or null on failure
     */
    public function getValidToken($forceRefresh = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $currentToken = $_SESSION['jwt_token'] ?? null;
        
        // If force refresh or token validation fails, attempt refresh
        if ($forceRefresh || !$this->isValidTokenWithRoles($currentToken)) {
            if ($this->tokenRefreshAttempts >= $this->maxTokenRefreshAttempts) {
                error_log("IDPManager: Maximum token refresh attempts exceeded ({$this->maxTokenRefreshAttempts})");
                $this->tokenRefreshAttempts = 0; // Reset for next session
                return null;
            }

            $this->tokenRefreshAttempts++;
            error_log("IDPManager: Attempting token refresh (attempt {$this->tokenRefreshAttempts}/{$this->maxTokenRefreshAttempts})");
            
            $refreshedToken = $this->refreshEnhancedToken($currentToken);
            if ($refreshedToken) {
                $_SESSION['jwt_token'] = $refreshedToken;
                $this->tokenRefreshAttempts = 0; // Reset on success
                error_log("IDPManager: Successfully refreshed token");
                return $refreshedToken;
            } else {
                error_log("IDPManager: Failed to refresh token");
                return null;
            }
        }

        // Reset attempts counter if we have a valid token
        $this->tokenRefreshAttempts = 0;
        return $currentToken;
    }

    /**
     * Validate JWT token and check if it has the required roles
     * @param string $token JWT token to validate
     * @return bool True if token is valid and has roles
     */
    private function isValidTokenWithRoles($token) {
        if (!$token) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        try {
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!$payload) {
                return false;
            }

            // Check expiration
            $now = time();
            $exp = $payload['exp'] ?? 0;
            if ($exp > 0 && $exp < $now) {
                error_log("IDPManager: Token expired (exp: $exp, now: $now)");
                return false;
            }

            // Check for roles (required for enhanced access)
            $hasRoles = isset($payload['roles']) && is_array($payload['roles']) && count($payload['roles']) > 0;
            if (!$hasRoles) {
                error_log("IDPManager: Token lacks required roles");
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("IDPManager: Error validating JWT token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh JWT token with enhanced roles from IDP
     * @param string $currentToken Current JWT token (may be expired)
     * @return string|null Enhanced JWT token or null on failure
     */
    private function refreshEnhancedToken($currentToken) {
        // Get user info from session
        $userEmail = $_SESSION['email'] ?? null;
        $admin = $_SESSION['admin'] ?? 0;

        if (!$userEmail) {
            error_log("IDPManager: Cannot refresh token - no user email in session");
            return null;
        }

        if (!$currentToken) {
            error_log("IDPManager: Cannot refresh token - no current token available");
            return null;
        }

        try {
            // Use the getEnhancedJwtFromIDP function (should be available from app config)
            if (function_exists('getEnhancedJwtFromIDP')) {
                $enhancedToken = getEnhancedJwtFromIDP($currentToken, $userEmail, $admin);
                if ($enhancedToken) {
                    error_log("IDPManager: Successfully got enhanced token from IDP for $userEmail");
                    return $enhancedToken;
                } else {
                    error_log("IDPManager: getEnhancedJwtFromIDP returned null for $userEmail");
                }
            } else {
                error_log("IDPManager: getEnhancedJwtFromIDP function not available - ensure app configuration is loaded");
            }

            error_log("IDPManager: Token enhancement failed for $userEmail");
            return null;

        } catch (\Exception $e) {
            error_log("IDPManager: Exception during token refresh: " . $e->getMessage());
            return null;
        }
    }

    /**
     * DEPRECATED: Call IDP enhance-token endpoint directly
     * This method is disabled to prevent fallback confusion.
     * Use getEnhancedJwtFromIDP function from app configuration instead.
     */
    private function callIDPEnhanceEndpoint($originalToken, $userEmail, $adminLevel) {
        error_log("IDPManager: callIDPEnhanceEndpoint is deprecated and disabled");
        return null;
    }

    /**
     * Check if user needs to login (for use by applications)
     * @return bool True if user needs to login
     */
    public function needsLogin() {
        return $this->getValidToken() === null;
    }

    /**
     * Redirect to login if no valid token available
     * @param string $returnUrl URL to return to after login
     */
    public function requireLogin($returnUrl = null) {
        if ($this->needsLogin()) {
            $loginUrl = $this->getLoginUrl($returnUrl);
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /**
     * Get logout URL for redirecting user to IDP logout
     * @param string $redirectUrl Optional URL to redirect to after logout
     * @return string Full logout URL
     */
    public function getLogoutUrl($redirectUrl = null): string {
        $params = [
            'app_id' => $this->appId
        ];
        
        if ($redirectUrl) {
            $params['redirect_uri'] = $redirectUrl;
        }
        
        return $this->baseUrl . '/logout?' . http_build_query($params);
    }

    /**
     * Validate an access token with the IDP
     * @param string $token The access token to validate
     * @return bool True if token is valid and not expired
     */
    public function validateToken($token): bool {
        if (empty($token)) {
            return false;
        }

        try {
            $url = $this->baseUrl . '/api/validate-token';
            $response = $this->makeRequest('POST', $url, [
                'token' => $token,
                'app_id' => $this->appId
            ]);

            return $response['valid'] ?? false;
        } catch (\Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user profile information from IDP using access token
     * @param string $token Valid access token
     * @return array User profile data (email, name, roles, etc.)
     */
    public function getUserInfo($token): array {
        if (empty($token)) {
            throw new \Exception("Access token is required");
        }

        try {
            $url = $this->baseUrl . '/api/user/profile';
            
            // Use cURL directly for Bearer token authentication
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("HTTP request failed: " . $error);
            }
            
            if ($httpCode >= 400) {
                throw new \Exception("HTTP error: " . $httpCode . " - " . $response);
            }
            
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON decode error: " . json_last_error_msg());
            }

            return [
                'email' => $responseData['email'] ?? '',
                'name' => $responseData['name'] ?? '',
                'sub' => $responseData['sub'] ?? $responseData['id'] ?? '',
                'id' => $responseData['id'] ?? $responseData['sub'] ?? '',
                'roles' => $responseData['roles'] ?? ['participant']
            ];
        } catch (\Exception $e) {
            error_log("Get user info error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate registration URL with callback and parameters
     * @param string $callbackUrl Where to redirect after registration
     * @param array $params Additional parameters (email, name, etc.)
     * @return string Full registration URL
     */
    public function getRegisterUrl($callbackUrl, $params = []): string {
        $state = bin2hex(random_bytes(16));
        
        // Store state for validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_state'] = $state;

        $queryParams = array_merge([
            'app_id' => $this->appId,
            'callback_url' => $callbackUrl,
            'state' => $state
        ], $params);

        return $this->baseUrl . '/register?' . http_build_query($queryParams);
    }

    /**
     * Generate password reset URL
     * @param array $params Parameters including email
     * @return string Password reset URL
     */
    public function getResetPasswordUrl($params = []): string {
        $queryParams = array_merge([
            'app_id' => $this->appId
        ], $params);

        return $this->baseUrl . '/reset-password?' . http_build_query($queryParams);
    }

    /**
     * Generate password change URL for authenticated users
     * @return string Password change URL
     */
    public function getChangePasswordUrl(): string {
        $params = [
            'app_id' => $this->appId
        ];

        return $this->baseUrl . '/change-password?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback from IDP
     * @param string $code Authorization code from IDP
     * @param string $state State parameter for CSRF protection
     * @return array Result with success status and tokens
     */
    public function handleCallback($code, $state): array {
        try {
            // Validate state parameter for CSRF protection
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
                return [
                    'success' => false,
                    'error' => 'Invalid state parameter - possible CSRF attack'
                ];
            }
            
            // Clear the state
            unset($_SESSION['oauth_state']);

            // Exchange authorization code for access token
            $url = $this->baseUrl . '/oauth/token';
            $response = $this->makeRequest('POST', $url, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'app_id' => $this->appId,
                'redirect_uri' => $this->buildCallbackUrl()
            ]);

            if ($response['success'] && isset($response['access_token'])) {
                return [
                    'success' => true,
                    'access_token' => $response['access_token'],
                    'refresh_token' => $response['refresh_token'] ?? '',
                    'expires_in' => $response['expires_in'] ?? 3600,
                    'error' => ''
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to obtain access token'
                ];
            }
        } catch (\Exception $e) {
            error_log("OAuth callback error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage()
            ];
        }
    }
}