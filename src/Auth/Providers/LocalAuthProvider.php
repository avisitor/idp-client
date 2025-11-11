<?php

namespace WorldSpot\IDPClient\Auth\Providers;

/**
 * Abstract Local Authentication Provider
 * 
 * Base class for local authentication implementations.
 * Applications should extend this class and implement the abstract methods
 * to integrate with their specific database and user management systems.
 * 
 * @package WorldSpot\IDPClient
 * @subpackage Auth\Providers
 */
abstract class LocalAuthProvider implements AuthProviderInterface 
{
    protected $config;
    
    public function __construct(array $config = []) {
        $this->config = $config;
    }
    
    /**
     * Get database connection - must be implemented by application
     * 
     * @return \PDO Database connection
     */
    abstract protected function getConnection(): \PDO;
    
    /**
     * Load user-specific data after login - must be implemented by application
     * 
     * @param string $username Username/email
     * @return array User data to store in session
     */
    abstract protected function loadUserData(string $username): array;
    
    /**
     * Get the base URL for auth pages
     * 
     * @return string Base URL
     */
    protected function getAuthBaseUrl(): string {
        $baseUrl = $this->config['app_base_url'] ?? '';
        $authPath = $this->config['app_auth_path'] ?? '/auth';
        return rtrim($baseUrl, '/') . '/' . ltrim($authPath, '/');
    }
    
    /**
     * Authenticate a user with credentials
     */
    public function login(array $credentials): array {
        $username = trim($credentials['username'] ?? '');
        $password = trim($credentials['password'] ?? '');
        $redirectAfter = $credentials['redirect'] ?? '';
        
        if (empty($username)) {
            return [
                'success' => false,
                'error' => 'Please enter email.',
                'redirect' => '',
                'user' => null
            ];
        }
        
        if (empty($password)) {
            return [
                'success' => false, 
                'error' => 'Please enter your password.',
                'redirect' => '',
                'user' => null
            ];
        }
        
        try {
            $conn = $this->getConnection();
            $sql = "SELECT id, username, password, active FROM users WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    if (!$user['active']) {
                        return [
                            'success' => false,
                            'error' => 'Please activate your account. Check your email for the activation link.',
                            'redirect' => '',
                            'user' => null
                        ];
                    }
                    
                    // Start session and store user data
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    
                    $_SESSION["loggedin"] = true;
                    $_SESSION["username"] = $username;
                    $_SESSION["authenticated"] = true;
                    $_SESSION["email"] = $username;
                    
                    // Load application-specific user data
                    $userData = $this->loadUserData($username);
                    foreach ($userData as $key => $value) {
                        $_SESSION[$key] = $value;
                    }
                    
                    error_log("LocalAuthProvider: Login successful for $username");
                    
                    return [
                        'success' => true,
                        'user' => array_merge([
                            'id' => $user['id'],
                            'username' => $username,
                            'email' => $username,
                            'active' => $user['active']
                        ], $userData),
                        'redirect' => $redirectAfter,
                        'error' => ''
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Invalid username or password.',
                        'redirect' => '',
                        'user' => null
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid username or password.',
                    'redirect' => '',
                    'user' => null
                ];
            }
        } catch (\Exception $e) {
            error_log("LocalAuthProvider login error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Login system error. Please try again.',
                'redirect' => '',
                'user' => null
            ];
        }
    }
    
    /**
     * Log out the current user
     */
    public function logout(?string $redirectUrl = null): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        $redirect = $redirectUrl ?? $this->getLoginUrl();
        
        return [
            'success' => true,
            'redirect' => $redirect,
            'error' => ''
        ];
    }
    
    /**
     * Register a new user account
     * Override in application if needed
     */
    public function register(array $userData): array {
        return [
            'success' => false,
            'error' => 'Local registration not implemented.',
            'user' => null,
            'verification_required' => true
        ];
    }
    
    /**
     * Initiate password reset process
     * Override in application if needed
     */
    public function resetPassword(string $email): array {
        return [
            'success' => false,
            'error' => 'Local password reset not implemented.',
            'message' => ''
        ];
    }
    
    /**
     * Change user's password
     * Override in application if needed
     */
    public function changePassword(string $currentPassword, string $newPassword): array {
        return [
            'success' => false,
            'error' => 'Local password change not implemented.',
            'message' => ''
        ];
    }
    
    /**
     * Check if user is currently authenticated
     */
    public function isAuthenticated(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true &&
               isset($_SESSION["username"]) && !empty($_SESSION["username"]);
    }
    
    /**
     * Get current authenticated user information
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'username' => $_SESSION["username"] ?? '',
            'email' => $_SESSION["email"] ?? $_SESSION["username"] ?? '',
            'authenticated' => $_SESSION["authenticated"] ?? true,
            'session_data' => $_SESSION
        ];
    }
    
    /**
     * Get URL for login page/redirect
     */
    public function getLoginUrl(?string $redirectAfter = null, array $params = []): string {
        $baseUrl = $this->getAuthBaseUrl() . '/login.php';
        $queryParams = [];
        
        if ($redirectAfter) {
            $queryParams['from'] = $redirectAfter;
        }
        
        if (!empty($params)) {
            $queryParams = array_merge($queryParams, $params);
        }
        
        return empty($queryParams) ? $baseUrl : $baseUrl . '?' . http_build_query($queryParams);
    }
    
    /**
     * Get URL for registration page/redirect
     */
    public function getRegistrationUrl(?string $redirectAfter = null, array $params = []): string {
        $baseUrl = $this->getAuthBaseUrl() . '/register.php';
        $queryParams = [];
        
        if ($redirectAfter) {
            $queryParams['from'] = $redirectAfter;
        }
        
        if (!empty($params)) {
            $queryParams = array_merge($queryParams, $params);
        }
        
        return empty($queryParams) ? $baseUrl : $baseUrl . '?' . http_build_query($queryParams);
    }
    
    /**
     * Get URL for password reset page/redirect
     */
    public function getResetPasswordUrl(?string $email = null): string {
        $baseUrl = $this->getAuthBaseUrl() . '/reset.php';
        
        if ($email) {
            return $baseUrl . '?email=' . urlencode($email);
        }
        
        return $baseUrl;
    }
    
    /**
     * Get URL for change password page/redirect
     */
    public function getChangePasswordUrl(): string {
        return $this->getAuthBaseUrl() . '/change.php';
    }
    
    /**
     * Initialize session and load user data
     */
    public function initializeSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($this->isAuthenticated()) {
            $username = $_SESSION["username"] ?? '';
            if ($username) {
                // Reload user data if needed
                $userData = $this->loadUserData($username);
                foreach ($userData as $key => $value) {
                    if (!isset($_SESSION[$key])) {
                        $_SESSION[$key] = $value;
                    }
                }
            }
        }
    }
    
    /**
     * Verify email/user account
     * Override in application if needed
     */
    public function verifyAccount(string $token): array {
        return [
            'success' => false,
            'error' => 'Local account verification not implemented.',
            'message' => ''
        ];
    }
    
    /**
     * Check if external authentication is enabled
     */
    public function isExternalAuth(): bool {
        return false;
    }
}
