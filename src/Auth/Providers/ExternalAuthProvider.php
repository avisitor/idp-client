<?php

namespace WorldSpot\IDPClient\Auth\Providers;

use WorldSpot\IDPClient\IDPManager;

/**
 * External Authentication Provider
 * 
 * Uses the IDPClient package to delegate authentication to an external
 * Identity Provider, enabling centralized user management across multiple apps.
 * 
 * @package WorldSpot\IDPClient
 * @subpackage Auth\Providers
 */
class ExternalAuthProvider implements AuthProviderInterface 
{
    protected $config;
    protected $idpManager;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array with keys:
     *                      - app_base_url: Base URL of the application
     *                      - app_auth_path: Path to auth directory (default: /auth)
     *                      - app_home: Home page URL (default: /)
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->idpManager = new IDPManager();
    }
    
    protected function get_caller_info() {
        $c = '';
        $file = '';
        $func = '';
        $class = '';
        $trace = debug_backtrace();
        if (isset($trace[2])) {
            $file = $trace[1]['file'];
            $func = $trace[2]['function'];
            if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
                $func = '';
            }
        } else if (isset($trace[1])) {
            $file = $trace[1]['file'];
            $func = '';
        }
        if (isset($trace[3]['class'])) {
            $class = $trace[3]['class'];
            $func = $trace[3]['function'];
            $file = $trace[2]['file'];
        } else if (isset($trace[2]['class'])) {
            $class = $trace[2]['class'];
            $func = $trace[2]['function'];
            $file = $trace[1]['file'];
        }
        if ($file != '') $file = basename($file);
        $c = $file . ": ";
        $c .= ($class != '') ? ":" . $class . "->" : "";
        $c .= ($func != '') ? $func . "(): " : "";
        return($c);
    }

    public function debuglog( $msg, $prefix="" ) {
        if( is_object( $msg ) || is_array( $msg ) ) {
            $msg = var_export( $msg, true );
        }
        if( $prefix ) {
            $msg = "$prefix: $msg";
        }
        error_log( $_SERVER['SCRIPT_NAME'] . ": " . $this->get_caller_info() . ": $msg" );
    }

    /**
     * Authenticate a user with credentials
     * For external auth, this redirects to IDP rather than processing locally
     */
    public function login(array $credentials): array {
        $redirectAfter = $credentials['redirect'] ?? '';
        
        // For external auth, we redirect to the IDP login page
        $loginUrl = $this->getLoginUrl($redirectAfter);
        
        return [
            'success' => true,
            'redirect' => $loginUrl,
            'user' => null,
            'error' => '',
            'external_redirect' => true
        ];
    }
    
    /**
     * Log out the current user
     */
    public function logout(?string $redirectUrl = null): array {
        try {
            // Build callback URL from configuration
            $baseUrl = $this->config['app_base_url'] ?? throw new \Exception('APP_BASE_URL not configured');
            $authPath = $this->config['app_auth_path'] ?? '/auth';
            $callbackUrl = rtrim($baseUrl, '/') . '/' . ltrim($authPath, '/') . '/callback.php';
            
            if ($redirectUrl) {
                $callbackUrl .= '?redirect=' . urlencode($redirectUrl);
            }
            
            $this->debuglog("callbackUrl = $callbackUrl");
            
            // Use IDP logout which will handle token cleanup and redirect to callback
            $logoutUrl = $this->idpManager->getLogoutUrl($callbackUrl);
            
            $this->debuglog("logoutUrl = $logoutUrl");
            
            // Clear local session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION = array();
            session_destroy();
            
            return [
                'success' => true,
                'redirect' => $logoutUrl,
                'error' => '',
                'external_redirect' => true
            ];
        } catch (\Exception $e) {
            $this->debuglog("logout error: " . $e->getMessage());
            
            // Fallback to local logout
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION = array();
            session_destroy();
            
            // Default to login page if no redirect specified
            $fallbackRedirect = $redirectUrl ?? $this->getLoginUrl();
            
            return [
                'success' => true,
                'redirect' => $fallbackRedirect,
                'error' => 'Logout completed locally due to IDP connection issue.',
                'external_redirect' => false
            ];
        }
    }
    
    /**
     * Register a new user account
     */
    public function register(array $userData): array {
        $redirectAfter = $userData['redirect'] ?? '';
        
        // External registration redirects to IDP
        $registerUrl = $this->getRegistrationUrl($redirectAfter);
        
        return [
            'success' => true,
            'redirect' => $registerUrl,
            'user' => null,
            'error' => '',
            'verification_required' => false, // Handled by IDP
            'external_redirect' => true
        ];
    }
    
    /**
     * Initiate password reset process
     */
    public function resetPassword(string $email): array {
        // External password reset redirects to IDP
        $resetUrl = $this->getResetPasswordUrl($email);
        
        return [
            'success' => true,
            'message' => 'Redirecting to password reset page...',
            'redirect' => $resetUrl,
            'error' => '',
            'external_redirect' => true
        ];
    }
    
    /**
     * Change user's password
     */
    public function changePassword(string $currentPassword, string $newPassword): array {
        // External password change redirects to IDP
        $changeUrl = $this->getChangePasswordUrl();
        
        return [
            'success' => true,
            'message' => 'Redirecting to password change page...',
            'redirect' => $changeUrl,
            'error' => '',
            'external_redirect' => true
        ];
    }
    
    /**
     * Check if user is currently authenticated
     */
    public function isAuthenticated(): bool {
        $this->debuglog( session_status(), "session_status" );
        if (session_status() === PHP_SESSION_NONE) {
            $this->debuglog( "session_status === PHP_SESSION_NONE" );
            return false;
        }
        
        // For external auth, check if we have a valid email/username in session
        $userEmail = $_SESSION['email'] ?? $_SESSION['username'] ?? null;
        if (!$userEmail) {
            $this->debuglog( "neither email nor username set in SESSION" );
            return false;
        }
        
        // Check if we have authenticated flag
        $state = empty($_SESSION['authenticated']) ? "false" : "true";
        $this->debuglog( "SESSION[authenticated] = $state" );
        return !empty($_SESSION['authenticated']);
    }
    
    /**
     * Get current authenticated user information
     */
    public function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            $this->debuglog( "Starting session" );
            session_start();
        }
        
        // Check if we have basic authentication (email/username) from external auth
        $userEmail = $_SESSION['email'] ?? $_SESSION['username'] ?? null;
        if (!$userEmail) {
            return null;
        }
        
        try {
            // Use IDPManager's built-in token validation
            $validToken = $this->idpManager->getValidToken();
            if (!$validToken && !isset($_SESSION['jwt_token'])) {
                $this->debuglog("No valid token for $userEmail");
            }
            
            // Return user info from session
            $adminLevel = (int)($_SESSION['admin'] ?? 0);
            $roles = $_SESSION['roles'] ?? [];
            
            return [
                'username' => $userEmail,
                'email' => $userEmail,
                'name' => $_SESSION['name'] ?? '',
                'roles' => $roles,
                'admin' => $adminLevel,
                'idp_user_id' => $_SESSION['idp_user_id'] ?? '',
                'authenticated' => $_SESSION['authenticated'] ?? true
            ];
        } catch (\Exception $e) {
            $this->debuglog("error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get URL for login page/redirect
     */
    public function getLoginUrl(?string $redirectAfter = null, array $params = []): string {
        $appBaseUrl = $this->config['app_base_url'] ?? '';
        $authPath = $this->config['app_auth_path'] ?? '/auth';
        $callbackUrl = rtrim($appBaseUrl, '/') . '/' . ltrim($authPath, '/') . '/callback.php';
        
        if ($redirectAfter) {
            $callbackUrl .= '?redirect=' . urlencode($redirectAfter);
        }
        
        return $this->idpManager->getLoginUrl($callbackUrl);
    }
    
    /**
     * Get URL for login page/redirect
     */
    public function getLogoutUrl(?string $redirectAfter = null, array $params = []): string {
        return $this->getLoginUrl($redirectAfter, $params);
    }
    
    /**
     * Get URL for registration page/redirect
     */
    public function getRegistrationUrl(?string $redirectAfter = null, array $params = []): string {
        $appBaseUrl = $this->config['app_base_url'] ?? '';
        $authPath = $this->config['app_auth_path'] ?? '/auth';
        $callbackUrl = rtrim($appBaseUrl, '/') . '/' . ltrim($authPath, '/') . '/callback.php';
        
        if ($redirectAfter) {
            $callbackUrl .= '?redirect=' . urlencode($redirectAfter);
        }
        
        return $this->idpManager->getRegisterUrl($callbackUrl, $params);
    }
    
    /**
     * Get URL for password reset page/redirect
     */
    public function getResetPasswordUrl(?string $email = null): string {
        $params = [];
        if ($email) {
            $params['email'] = $email;
        }
        
        return $this->idpManager->getResetPasswordUrl($params);
    }
    
    /**
     * Get URL for change password page/redirect
     */
    public function getChangePasswordUrl(): string {
        return $this->idpManager->getChangePasswordUrl();
    }
    
    /**
     * Initialize session and load user data from IDP token
     * This is a minimal implementation - applications can override for custom behavior
     */
    public function initializeSession(): void {
        $this->debuglog( session_status(), "session_status" );
        if (session_status() === PHP_SESSION_NONE) {
            $this->debuglog( "Starting session" );
            session_start();
        } else {
            $this->debuglog( $_SESSION, "Session already started" );
        }
        
        // If we have a session with email, ensure basic auth flags are set
        if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
            if (!isset($_SESSION['authenticated'])) {
                $_SESSION['authenticated'] = true;
            }
            if (!isset($_SESSION['username'])) {
                $_SESSION['username'] = $_SESSION['email'];
            }
        }
    }
    
    /**
     * Verify email/user account
     */
    public function verifyAccount(string $token): array {
        // Account verification is handled by IDP
        return [
            'success' => true,
            'message' => 'Account verification is handled by the Identity Provider.',
            'error' => ''
        ];
    }
    
    /**
     * Check if external authentication is enabled
     */
    public function isExternalAuth(): bool {
        return true;
    }
}
