<?php
/**
 * Generic IDP Callback Handler
 * 
 * This file processes authentication returns from IDP and delegates
 * app-specific logic to the onSuccessfulLogin hook for portability.
 */

require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

ensureSession();

// Get redirect URL from query parameter
$redirect = $_GET['redirect'] ?? null;

// If no redirect specified, let the app determine the default
if (!$redirect) {
    $redirect = buildAppUrl('index.php'); // Generic default, app can override
}

try {
    // Check for authentication token from IDP
    $token = $_GET['token'] ?? $_GET['jwt'] ?? null;
    
    if (!$token) {
        // Log debugging information
        authLog("IDP Callback - No token found. GET: " . json_encode($_GET));
        authLog("IDP Callback - SESSION: " . json_encode($_SESSION));
        
        throw new Exception("No authentication token received from IDP");
    }
    
    // Validate and decode JWT token
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        throw new Exception("Invalid JWT token format");
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    if (!$payload) {
        throw new Exception("Invalid JWT payload");
    }
    
    // Extract standardized user information
    $userInfo = [
        'email' => $payload['sub'] ?? null,
        'user_id' => $payload['sub'] ?? $payload['user_id'] ?? null,
        'name' => $payload['name'] ?? null,
        'roles' => $payload['roles'] ?? ['editor']
    ];
    
    if (!$userInfo['email']) {
        throw new Exception("Incomplete user information in JWT - missing email");
    }
    
    authLog("IDP Callback - JWT payload: " . json_encode($payload));
    authLog("IDP Callback - User info: " . json_encode($userInfo));
    
    // Set standardized authentication session
    setAuthenticatedUser($userInfo);
    
    // Delegate to app-specific login handler
    if (function_exists('onSuccessfulLogin')) {
        $redirect = onSuccessfulLogin($userInfo, $redirect);
    }
    
    authLog("IDP authentication successful for user: " . $userInfo['email']);
    
    // Redirect to final destination
    header("Location: $redirect");
    exit;
    
} catch (Exception $e) {
    authLog("IDP callback error: " . $e->getMessage());
    
    // Redirect back to login with error
    $errorMsg = urlencode("Authentication failed: " . $e->getMessage());
    header("Location: " . buildAuthUrl('login.php') . "?error=$errorMsg&from=" . urlencode($redirect));
    exit;
}
?>