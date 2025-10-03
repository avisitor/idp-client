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
        
        // Get redirect parameter - use configured default_redirect instead of hardcoded index.php
        $defaultRedirect = $this->config['default_redirect'] ?? $this->buildAppUrl();
        $redirect = $_GET['from'] ?? $_GET['return'] ?? $defaultRedirect;
        
        // Convert relative URLs to full URLs for IDP compatibility
        if (!preg_match('/^https?:\/\//', $redirect)) {
            $redirect = $this->buildAppUrl($redirect);
        }
        
        $this->authLog("Login request - redirect: $redirect");
        
        // Use the final destination directly as return URL to avoid callback loop
        $returnUrl = $this->buildAuthUrl('idp-callback.php', ['redirect' => $redirect]);
        
        // Get IDP login URL
        if ($this->idpManager) {
            $loginUrl = $this->idpManager->getLoginUrl($returnUrl);
        } else {
            // Fallback to manual URL building - use callback URL for proper token handling
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