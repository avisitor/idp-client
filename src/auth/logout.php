<?php
/**
 * Generic Logout Handler
 * 
 * Delegates app-specific logout logic to hooks for portability.
 * 
 * Extensibility hooks available:
 * - onPreLogout(): Called before any logout logic
 * - onLogout(): Called before session clearing 
 * - onPostLogout(): Called after session clearing
 * - onLogoutRedirect($defaultUrl): Called to customize redirect URL
 * - onLogoutError($error): Called when logout encounters an error
 */

require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

// Allow for additional customization file inclusion
if (file_exists(__DIR__ . '/logout-customizations.php')) {
    require_once __DIR__ . '/logout-customizations.php';
}

ensureSession();

try {
    // Call app-specific pre-logout hook (for early customization)
    if (function_exists('onPreLogout')) {
        onPreLogout();
    }
    
    // Call app-specific pre-session-clear logout hook
    if (function_exists('onLogout')) {
        onLogout();
    }
    
    // Standard session clearing
    clearAuthentication();
    
    // Call app-specific post-logout hook (after session clearing)
    if (function_exists('onPostLogout')) {
        onPostLogout();
    }
    
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
