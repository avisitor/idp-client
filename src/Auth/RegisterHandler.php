<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Register Handler
 * 
 * Handles registration requests by redirecting to IDP
 */
class RegisterHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        // Build return URL for after IDP registration
        $returnUrl = $this->buildAuthUrl('idp-callback.php', [
            'redirect' => $this->buildAppUrl('index.php')
        ]);
        
        // Build IDP registration URL
        $idpRegistrationUrl = $this->config['idp_url'] . '/register.php';
        $idpUrl = $idpRegistrationUrl . '?' . http_build_query([
            'app' => $this->config['app_id'],
            'return' => $returnUrl
        ]);
        
        $this->authLog("Registration redirecting to IDP: $idpUrl");
        
        // Call app pre-registration hook
        $this->callAppCallback('onPreRegister', $idpUrl);
        
        // Redirect to IDP registration
        header("Location: $idpUrl");
        exit;
    }
}