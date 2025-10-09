<?php
/**
 * 🔧 My App Authentication Customizations
 * 
 * This tiny file customizes the IDP-Client package for your specific app.
 * Edit the functions below with your app's details.
 * 
 * IMPORTANT: Set these environment variables in your .env file:
 * - APP_NAME (required)
 * - APP_URL (required) 
 * - IDP_APP_ID (required)
 * - SUPPORT_EMAIL (required)
 * - IDP_URL (required)
 */

// ============================================================================
// 🔴 REQUIRED: Edit these functions with your app details
// ============================================================================

/**
 * 📝 Set your application details here
 * Use environment variables for production deployments
 */
function getAppDetails() {
    return [
        'app_name' => $_ENV['APP_NAME'] ?? 'My Awesome App',                    // 🔴 Set APP_NAME in .env
        'app_url' => $_ENV['APP_URL'] ?? 'https://my-app.com',                 // 🔴 Set APP_URL in .env  
        'app_id' => $_ENV['IDP_APP_ID'] ?? 'your-idp-app-id-here',               // 🔴 Set IDP_APP_ID in .env (get from WorldSpot IDP)
        'support_email' => $_ENV['SUPPORT_EMAIL'] ?? 'support@my-app.com',          // 🔴 Set SUPPORT_EMAIL in .env
    ];
}

// ============================================================================
// 🔶 OPTIONAL: Customize behavior (good defaults provided)
// ============================================================================

/**
 * 🎨 Customize auth behavior and styling (optional)
 */
function getAppCustomizations() {
    return [
        'default_redirect' => 'dashboard.php',             // Where to go after login
        'logout_redirect' => 'index.php',                 // Where to go after logout
        'auth_css' => 'my-auth-styles.css',               // Your CSS file name
        'theme_color' => '#ff6b35',                       // Your brand color
    ];
}

/**
 * 🏢 Integrate with your existing user system (optional)
 */
function getAppUserIntegration() {
    return [
        'load_user_profile' => function($userEmail) {
            // Load additional user data from your database
            // Example:
            // return MyUser::findByEmail($userEmail);
            return null;
        },
        
        'check_admin_status' => function($userEmail, $userRoles) {
            // Check if user is admin in your system
            // Default: use IDP roles
            return in_array('admin', $userRoles);
        },
        
        'render_app_banner' => function() {
            // Render your app's navigation/header on auth pages
            // Example:
            // include __DIR__ . '/../header.php';
        }
    ];
}

// Load the main package configuration (this loads all the generic logic)
$packageConfigPath = __DIR__ . '/../vendor/avisitor/idp-client/src/auth-config.php';
if (!file_exists($packageConfigPath)) {
    die('Error: IDP-Client package not found. Run: composer require avisitor/idp-client' . PHP_EOL);
}
require_once $packageConfigPath;
?>