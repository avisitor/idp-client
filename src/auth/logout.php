<?php
/**
 * Generic Logout Handler
 * 
 * Delegates app-specific logout logic to hooks for portability
 */

require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

ensureSession();

try {
    // Call app-specific pre-logout hook
    if (function_exists('onLogout')) {
        onLogout();
    }
    
    // Standard session clearing
    clearAuthentication();
    
    // Call app-specific post-logout hook for redirects
    $redirectUrl = buildAppUrl('index.php'); // Default fallback
    
    if (function_exists('onLogoutRedirect')) {
        $redirectUrl = onLogoutRedirect($redirectUrl);
    }
    
    authLog("User logged out successfully, redirecting to: $redirectUrl");
    
    header("Location: $redirectUrl");
    exit;
    
} catch (Exception $e) {
    authLog("Logout error: " . $e->getMessage());
    
    // Fallback: basic logout and redirect to home
    clearAuthentication();
    header("Location: " . buildAppUrl('index.php'));
    exit;
}
?>
