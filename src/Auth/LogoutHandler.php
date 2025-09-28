<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Logout Handler
 * 
 * Handles user logout requests
 */
class LogoutHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        try {
            // Call app-specific pre-logout hook
            $this->callAppCallback('onLogout');
            
            // Clear authentication session
            $this->clearAuthentication();
            
            // Call app-specific post-logout hook for redirect
            $redirectUrl = $this->callAppCallback('onLogoutRedirect', $this->buildAppUrl('index.php'));
            if (!$redirectUrl) {
                $redirectUrl = $this->buildAppUrl('index.php');
            }
            
            $this->authLog("User logged out successfully, redirecting to: $redirectUrl");
            
            header("Location: $redirectUrl");
            exit;
            
        } catch (\Exception $e) {
            $this->authLog("Logout error: " . $e->getMessage());
            
            // Fallback: basic logout and redirect to home
            $this->clearAuthentication();
            header("Location: " . $this->buildAppUrl('index.php'));
            exit;
        }
    }
    
    /**
     * Clear authentication session
     */
    protected function clearAuthentication()
    {
        // Clear authentication-specific session data
        $keysToKeep = ['_token']; // Keep CSRF tokens and other non-auth data
        $sessionData = [];
        
        foreach ($keysToKeep as $key) {
            if (isset($_SESSION[$key])) {
                $sessionData[$key] = $_SESSION[$key];
            }
        }
        
        session_destroy();
        session_start();
        
        // Restore non-auth session data
        foreach ($sessionData as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }
}