<?php
/**
 * Example IDP Callback Handler
 * 
 * This file should be placed at APP_AUTH_PATH/idp-callback.php in your application
 * It handles returns from the IDP after authentication
 */

require_once 'vendor/autoload.php';

use WorldSpot\IDPClient\IDPManager;

session_start();

// Get redirect URL from query parameter
$redirect = $_GET['redirect'] ?? null;

// If no redirect specified, use the application home page
if (!$redirect) {
    $idp = new IDPManager();
    $redirect = $idp->buildAppUrl('/');
}

try {
    // Check for token in query parameters (common IDP pattern)
    $token = $_GET['token'] ?? $_GET['jwt'] ?? null;
    
    if (!$token) {
        // Check if IDP set a session or cookie
        error_log("IDP Callback - GET parameters: " . json_encode($_GET));
        error_log("IDP Callback - SESSION data: " . json_encode($_SESSION));
        
        throw new Exception("No authentication token received from IDP");
    }
    
    // Decode JWT (this is a simplified version - should use proper JWT library)
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        throw new Exception("Invalid JWT token format");
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    if (!$payload) {
        throw new Exception("Invalid JWT payload");
    }
    
    // Extract user information from JWT
    $email = $payload['sub'] ?? null;
    $userId = $payload['sub'] ?? $payload['user_id'] ?? null;
    $name = $payload['name'] ?? null;
    $roles = $payload['roles'] ?? ['user'];
    
    if (!$email) {
        throw new Exception("Incomplete user information in JWT - missing email in 'sub' field");
    }
    
    error_log("IDP Callback - JWT payload: " . json_encode($payload));
    error_log("IDP Callback - Extracted: email=$email, roles=" . json_encode($roles));
    
    // Set up session
    $_SESSION["loggedin"] = true;
    $_SESSION["username"] = $email;
    $_SESSION["auth_email"] = $email;
    $_SESSION["auth_user_id"] = $userId;
    $_SESSION["auth_name"] = $name;
    $_SESSION["roles"] = $roles;
    
    // Get or create user profile
    $idp = new IDPManager();
    $profileManager = $idp->getUserProfileManager();
    
    $profile = $profileManager->getByEmail($email);
    if (!$profile && $userId) {
        // Create new profile
        $profileManager->create($userId, $email, [
            'name' => $name,
            'rate' => 0
        ]);
    }
    
    error_log("IDP authentication successful for user: $email");
    
    // Redirect to original destination
    header("Location: $redirect");
    exit;
    
} catch (Exception $e) {
    error_log("IDP callback error: " . $e->getMessage());
    
    // Redirect back to login with error
    $errorMsg = urlencode("Authentication failed: " . $e->getMessage());
    $idp = new IDPManager();
    $loginPath = $idp->getAppConfig()['auth_path'] . '/login.php';
    header("Location: {$loginPath}?error=$errorMsg&from=" . urlencode($redirect));
    exit;
}
?>