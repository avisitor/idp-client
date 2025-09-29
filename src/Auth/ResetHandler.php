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
        
        // Build return URL for after IDP password change
        // Since user is already authenticated, return to main app instead of login page
        $returnUrl = $this->config['app_url'] ?: $this->buildAuthUrl('../');
        
        // Build IDP change password URL (for authenticated users)
        $idpChangeUrl = $this->config['idp_url'] . '/change.php';
        $idpUrl = $idpChangeUrl . '?' . http_build_query([
            'app' => $this->config['app_id'],
            'return' => $returnUrl
        ]);
        
        $this->authLog("Password change redirecting to IDP: $idpUrl");
        
        // Call app pre-change hook
        $this->callAppCallback('onPrePasswordChange', $idpUrl);
        
        // Redirect to IDP password change
        header("Location: $idpUrl");
        exit;
    }
}