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
        return [
            'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?: 'https://idp.worldspot.org',
            'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID') ?: 'default-app-id'
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
        $callbackPath = $appConfig['auth_path'] . '/idp-callback.php';
        
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
        
        return $protocol . '://' . $host . $basePath . $path;
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
}