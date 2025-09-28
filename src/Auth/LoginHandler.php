<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Login Handler
 * 
 * Handles login requests by redirecting to IDP
 */
class LoginHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        // Get redirect parameter
        $redirect = $_GET['from'] ?? $this->buildAppUrl('index.php');
        
        $this->authLog("Login request - redirect: $redirect");
        
        // Build callback URL
        $returnUrl = $this->buildAuthUrl('idp-callback.php', ['redirect' => $redirect]);
        
        // Get IDP login URL
        if ($this->idpManager) {
            $loginUrl = $this->idpManager->getLoginUrl($returnUrl);
        } else {
            // Fallback to manual URL building
            $loginUrl = $this->config['idp_url'] . '/?' . http_build_query([
                'app' => $this->config['app_id'],
                'return' => $returnUrl
            ]);
        }
        
        $this->authLog("Redirecting to IDP: $loginUrl");
        
        // Call app pre-login hook
        $this->callAppCallback('onPreLogin', $redirect, $loginUrl);
        
        // Redirect to IDP
        header("Location: $loginUrl");
        exit;
    }
}