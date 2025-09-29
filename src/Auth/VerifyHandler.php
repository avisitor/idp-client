<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Email Verification Handler
 * 
 * Handles email verification requests
 */
class VerifyHandler extends AuthHandler
{
    public function handle()
    {
        $this->ensureSession();
        
        if (isset($_GET['token']) && !empty($_GET['token'])) {
            // Verify via IDP
            $token = $_GET['token'];
            
            // Check for return URL parameter
            $returnUrl = $_GET['return'] ?? $_GET['redirect'] ?? null;
            
            try {
                $verificationResult = $this->verifyEmailToken($token);
                
                if ($verificationResult['success']) {
                    // Build login URL with return parameter if provided
                    $loginUrl = $this->buildAuthUrl('login.php');
                    if ($returnUrl) {
                        $loginUrl .= '?return=' . urlencode($returnUrl);
                    }
                    
                    $content = '<div class="statusmsg">Your email has been verified! You can now <a href="' . 
                              htmlspecialchars($loginUrl) . '">login</a> to your account.</div>';
                    
                    $this->renderResponse('Email Verification', $content, $loginUrl);
                } else {
                    $content = '<div class="statusmsg">Verification failed: ' . htmlspecialchars($verificationResult['error']) . '</div>';
                    $content .= '<div class="statusmsg">Please try requesting a new verification email or contact support at ' . 
                               htmlspecialchars($this->config['support_email']) . '.</div>';
                    
                    $this->renderResponse('Email Verification', $content);
                }
            } catch (\Exception $e) {
                $this->authLog("Email verification error: " . $e->getMessage());
                
                $content = '<div class="statusmsg">Verification failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $content .= '<div class="statusmsg">Please contact support at ' . htmlspecialchars($this->config['support_email']) . '.</div>';
                
                $this->renderResponse('Email Verification', $content);
            }
        } else {
            // Invalid approach
            $content = '<div class="statusmsg">Invalid verification link. Please use the link that has been sent to your email. ' .
                      'If you have not received the verification email, request help at ' . htmlspecialchars($this->config['support_email']) . '.</div>';
            
            $this->renderResponse('Email Verification', $content);
        }
    }
    
    /**
     * Verify email token via IDP
     */
    protected function verifyEmailToken($token)
    {
        try {
            if ($this->idpManager && method_exists($this->idpManager, 'verifyEmailToken')) {
                return $this->idpManager->verifyEmailToken($token);
            }
            
            // Fallback: direct API call
            $verifyUrl = $this->config['idp_url'] . '/api/verify-email';
            
            $postData = json_encode([
                'token' => $token,
                'app_id' => $this->config['app_id']
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $postData
                ]
            ]);
            
            $result = @file_get_contents($verifyUrl, false, $context);
            
            if ($result === false) {
                return ['success' => false, 'error' => 'Network error'];
            }
            
            $response = json_decode($result, true);
            return $response ?: ['success' => false, 'error' => 'Invalid response'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}