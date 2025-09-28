<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Password Reset Handler
 * 
 * Handles password reset requests by redirecting to IDP
 */
class ResetHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        // Build return URL for after IDP reset
        $returnUrl = $this->buildAuthUrl('login.php');
        
        // Build IDP reset URL
        $idpResetUrl = $this->config['idp_url'] . '/reset.php';
        $idpUrl = $idpResetUrl . '?' . http_build_query([
            'app' => $this->config['app_id'],
            'return' => $returnUrl
        ]);
        
        $this->authLog("Password reset redirecting to IDP: $idpUrl");
        
        // Call app pre-reset hook
        $this->callAppCallback('onPreReset', $idpUrl);
        
        // Redirect to IDP reset
        header("Location: $idpUrl");
        exit;
    }
}