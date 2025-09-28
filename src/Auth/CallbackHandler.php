<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * IDP Callback Handler
 * 
 * Handles authentication callbacks from IDP
 */
class CallbackHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        // Get redirect URL from query parameter
        $redirect = $_GET['redirect'] ?? $this->buildAppUrl('index.php');
        
        try {
            // Check for authentication token from IDP
            $token = $_GET['token'] ?? $_GET['jwt'] ?? null;
            
            if (!$token) {
                $this->authLog("IDP Callback - No token found. GET: " . json_encode($_GET));
                throw new \Exception("No authentication token received from IDP");
            }
            
            // Validate and decode JWT token
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                throw new \Exception("Invalid JWT token format");
            }
            
            $payload = json_decode(base64_decode($tokenParts[1]), true);
            if (!$payload) {
                throw new \Exception("Invalid JWT payload");
            }
            
            // Extract standardized user information
            $userInfo = [
                'email' => $payload['sub'] ?? null,
                'user_id' => $payload['sub'] ?? $payload['user_id'] ?? null,
                'name' => $payload['name'] ?? null,
                'roles' => $payload['roles'] ?? ['user']
            ];
            
            if (!$userInfo['email']) {
                throw new \Exception("Incomplete user information in JWT - missing email");
            }
            
            $this->authLog("IDP Callback - User info: " . json_encode($userInfo));
            
            // Set standardized authentication session
            $this->setAuthenticatedUser($userInfo);
            
            // Call app-specific success callback
            $finalRedirect = $this->callAppCallback('onSuccessfulLogin', $userInfo, $redirect);
            if ($finalRedirect) {
                $redirect = $finalRedirect;
            }
            
            $this->authLog("IDP authentication successful for user: " . $userInfo['email']);
            
            // Redirect to final destination
            header("Location: $redirect");
            exit;
            
        } catch (\Exception $e) {
            $this->authLog("IDP callback error: " . $e->getMessage());
            
            // Redirect back to login with error
            $errorMsg = urlencode("Authentication failed: " . $e->getMessage());
            $loginUrl = $this->buildAuthUrl('login.php') . "?error=$errorMsg&from=" . urlencode($redirect);
            header("Location: $loginUrl");
            exit;
        }
    }
    
    /**
     * Set authentication session data
     */
    protected function setAuthenticatedUser($userInfo)
    {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $userInfo['email'];
        $_SESSION['email'] = $userInfo['email'];
        $_SESSION['is_admin'] = in_array('admin', $userInfo['roles']);
        $_SESSION['authenticated_at'] = time();
        
        // Store roles and other user data
        $_SESSION['roles'] = $userInfo['roles'];
        foreach ($userInfo as $key => $value) {
            if (!in_array($key, ['email', 'roles'])) {
                $_SESSION['user_' . $key] = $value;
            }
        }
    }
}