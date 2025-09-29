<?php
/**
 * 🔧 My App Authentication Customizations
 * 
 * This tiny file customizes the IDP-Client package for your specific app.
 * Edit the functions below with your app's details.
 */

// ============================================================================
// 🔴 REQUIRED: Edit these functions with your app details
// ============================================================================

/**
 * 📝 Set your application details here
 */
function getAppDetails() {
    return [
        'app_name' => 'My Awesome App',                    // 🔴 Change this
        'app_url' => 'https://my-app.com',                 // 🔴 Change this  
        'app_id' => 'your-idp-app-id-here',               // 🔴 Change this (get from WorldSpot IDP)
        'support_email' => 'support@my-app.com',          // 🔴 Change this
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