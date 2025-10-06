<?php
/**
 * Get Valid JWT Token Endpoint
 * 
 * Returns a valid JWT token, automatically refreshing if necessary.
 * This endpoint uses the idp-client token refresh functionality.
 * Generic implementation that can be used across different applications.
 * 
 * Part of avisitor/idp-client package
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-detect vendor path (works from various locations)
$vendorPath = null;
$checkPaths = [
    __DIR__ . '/../../vendor/autoload.php',  // sites/get-valid-token.php
    __DIR__ . '/../vendor/autoload.php',     // get-valid-token.php in root
    __DIR__ . '/vendor/autoload.php'        // get-valid-token.php in project
];

foreach ($checkPaths as $path) {
    if (file_exists($path)) {
        $vendorPath = $path;
        break;
    }
}

if (!$vendorPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Vendor autoload not found']);
    exit;
}

require_once $vendorPath;

// Load generic app configuration
require_once __DIR__ . '/app-config.php';

use WorldSpot\IDPClient\IDPManager;

// Set JSON response headers
header('Content-Type: application/json');

try {
    error_log('[get-valid-token] Starting token request');
    
    // Get user email from session
    $userEmail = $_SESSION['email'] ?? $_SESSION['username'] ?? null;
    error_log('[get-valid-token] User email from session: ' . ($userEmail ?: 'NONE'));
    
    if (!$userEmail) {
        error_log('[get-valid-token] No user email found');
        echo json_encode([
            'success' => false,
            'error' => 'No authenticated user found'
        ]);
        exit;
    }
    
    // Initialize IDP Manager
    $idpManager = new IDPManager();
    
    // Check if user is authenticated
    if ($idpManager->needsLogin()) {
        error_log('[get-valid-token] User not authenticated');
        echo json_encode([
            'success' => false,
            'error' => 'User not authenticated'
        ]);
        exit;
    }
    
    // DEBUG: Check current admin status using existing app logic
    require_once __DIR__ . '/../planting/sites/plantclasses.php';
    $adminChecker = new Admin();
    $existingIsAdmin = $adminChecker->isAdmin();
    error_log("[get-valid-token] DEBUG: User $userEmail - existing isAdmin(): " . ($existingIsAdmin ? 'true' : 'false'));
    
    // Load app configuration to ensure roles are properly set
    require_once __DIR__ . '/my-auth-config.php';
    $appConfig = getAppUserIntegration();
    if (isset($appConfig['load_user_profile'])) {
        $userProfile = $appConfig['load_user_profile']($userEmail);
        error_log("[get-valid-token] DEBUG: User profile loaded, triggering role mapping");
    }
    
    // Use the idp-client token system to get a valid token
    $token = $idpManager->getValidToken();
    
    if ($token) {
        error_log('[get-valid-token] Successfully obtained token for ' . $userEmail);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user_email' => $userEmail,
            'app_id' => getEnvVar('MAIL_SERVICE_APP_ID')
        ]);
    } else {
        error_log('[get-valid-token] Failed to obtain token for ' . $userEmail);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to obtain valid token'
        ]);
    }
    
} catch (Exception $e) {
    error_log('[get-valid-token] Exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>