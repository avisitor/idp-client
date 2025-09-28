<?php
/**
 * IDP-Client Authentication Configuration
 * 
 * This file is part of the package and provides sensible defaults.
 * ONLY customize the marked sections below for your application.
 */

// ============================================================================
// 🔧 CUSTOMIZE THIS SECTION FOR YOUR APP
// ============================================================================

/**
 * � REQUIRED: Application identification
 * These MUST be customized for your app to work properly
 */
if (!function_exists('getAppDetails')) {
function getAppDetails() {
    return [
        // 🔴 REQUIRED: Your application name (shown in browser titles, emails, etc.)
        'app_name' => $_ENV['APP_NAME'] ?? 'My Application',
        
        // 🔴 REQUIRED: Your application's base URL (where your app is hosted)
        'app_url' => $_ENV['APP_URL'] ?? 'https://my-app.com',
        
        // 🔴 REQUIRED: Your IDP App ID (get this from WorldSpot IDP admin)
        'app_id' => $_ENV['IDP_APP_ID'] ?? 'your-idp-app-id-here',
        
        // 🔶 RECOMMENDED: Support email for users having auth issues
        'support_email' => $_ENV['SUPPORT_EMAIL'] ?? 'support@my-app.com',
    ];
}
}

/**
 * 🎨 OPTIONAL: Customize authentication behavior and branding
 * These have good defaults but can be customized
 */
if (!function_exists('getAppCustomizations')) {
function getAppCustomizations() {
    return [
        // After login, redirect users here (relative to app_url)
        'default_redirect' => 'index.php',
        
        // After logout, redirect users here  
        'logout_redirect' => 'index.php',
        
        // CSS file for auth pages (must exist in your auth directory)
        'auth_css' => 'style.css',
        
        // App theme color for auth pages
        'theme_color' => $_ENV['APP_THEME_COLOR'] ?? '#007cba',
        
        // Enable detailed auth logging (useful for debugging)
        'enable_logging' => $_ENV['AUTH_ENABLE_LOGGING'] ?? 'true',
        
        // Where to write auth logs
        'log_file' => $_ENV['AUTH_LOG_FILE'] ?? '/tmp/auth.log',
    ];
}
}

/**
 * 🏢 OPTIONAL: App-specific user data integration
 * Customize these if you have existing user systems to integrate
 */
if (!function_exists('getAppUserIntegration')) {
function getAppUserIntegration() {
    return [
        // Function to load additional user data after IDP login
        'load_user_profile' => function($userEmail) {
            // Example: Load from your existing user table
            // if (class_exists('MyUserClass')) {
            //     $user = new MyUserClass();
            //     return $user->getProfileByEmail($userEmail);
            // }
            return null;
        },
        
        // Function to check if user is admin in your system  
        'check_admin_status' => function($userEmail, $userRoles) {
            // Default: Check if 'admin' role from IDP
            return in_array('admin', $userRoles);
            
            // Example: Check your admin table
            // if (class_exists('MyAdminClass')) {
            //     $admin = new MyAdminClass();
            //     return $admin->isAdmin($userEmail);
            // }
        },
        
        // Function to render your app's banner/navigation on auth pages
        'render_app_banner' => function() {
            // Example: Include your app's navigation
            // $bannerFile = __DIR__ . '/../includes/banner.html';
            // if (file_exists($bannerFile)) {
            //     include $bannerFile;
            // }
        }
    ];
}
}

// ============================================================================
// 📦 PACKAGE CODE - DO NOT MODIFY BELOW THIS LINE
// ============================================================================

/**
 * Get complete authentication configuration (combines all sections)
 */
function getAuthConfig() {
    $details = getAppDetails();
    $customizations = getAppCustomizations();
    
    return array_merge([
        // Core required config
        'app_name' => $details['app_name'],
        'app_url' => $details['app_url'], 
        'app_id' => $details['app_id'],
        'support_email' => $details['support_email'],
        
        // IDP settings
        'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?? 'https://idp.worldspot.org',
        
        // Customizations  
        'default_redirect' => $customizations['default_redirect'],
        'logout_redirect' => $customizations['logout_redirect'],
        'css_file' => $customizations['auth_css'],
        'theme_color' => $customizations['theme_color'],
        'enable_logging' => $customizations['enable_logging'],
        'log_file' => $customizations['log_file'],
    ]);
}

/**
 * Get authentication callbacks/hooks for package handlers
 */
function getAuthCallbacks() {
    $integration = getAppUserIntegration();
    $config = getAuthConfig();
    
    return [
        // Called after successful IDP login
        'onSuccessfulLogin' => function($userInfo, $redirectUrl) use ($integration, $config) {
            // Set standard session data
            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $userInfo['email'];
            $_SESSION["auth_email"] = $userInfo['email'];
            $_SESSION["user_id"] = $userInfo['user_id'] ?? null;
            $_SESSION["user_name"] = $userInfo['name'] ?? null;
            $_SESSION["roles"] = $userInfo['roles'] ?? ['user'];
            
            // Check admin status
            $checkAdmin = $integration['check_admin_status'];
            $_SESSION["is_admin"] = $checkAdmin($userInfo['email'], $userInfo['roles']);
            
            // Load additional user profile data
            $loadProfile = $integration['load_user_profile'];
            if ($profile = $loadProfile($userInfo['email'])) {
                $_SESSION["user_profile"] = $profile;
            }
            
            // Return redirect URL (use default if none provided)
            return $redirectUrl ?: $config['default_redirect'];
        },
        
        // Called before logout
        'onLogout' => function() {
            // Log the logout event
            $username = $_SESSION['username'] ?? 'unknown';
            error_log("User logout: $username");
        },
        
        // Called to determine logout redirect
        'onLogoutRedirect' => function($defaultUrl) use ($config) {
            // You can customize logout behavior here
            // For now, just use app's logout redirect setting
            return $config['logout_redirect'];
        },
        
        // Called to render app banner on auth pages
        'renderBanner' => function() use ($integration) {
            $renderBanner = $integration['render_app_banner'];
            $renderBanner();
        }
    ];
}

/**
 * Check if current user is an admin
 */
function isAppAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

// ============================================================================
// 🔧 ENVIRONMENT SETUP
// ============================================================================

// Auto-load environment variables if .env file exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($envFile));
    $dotenv->safeLoad();
}

// Validate required configuration
$config = getAuthConfig();
if ($config['app_id'] === 'your-idp-app-id-here') {
    error_log('WARNING: Please customize app_id in auth-config.php');
}
?>