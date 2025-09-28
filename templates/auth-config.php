<?php
/**
 * App-Specific Authentication Configuration Template
 * 
 * Copy this file to your auth directory and customize for your application.
 * This is the ONLY file you need to customize when using the IDP-Client package.
 */

/**
 * Get authentication configuration for your app
 * Customize these values for your application
 */
function getAuthConfig() {
    return [
        // Required - customize these
        'app_name' => $_ENV['APP_NAME'] ?? 'Your Application Name',
        'app_url' => $_ENV['APP_URL'] ?? 'https://your-app.com',
        'idp_url' => $_ENV['IDP_URL'] ?? 'https://idp.worldspot.org',
        'app_id' => $_ENV['IDP_APP_ID'] ?? 'your-app-id',
        'support_email' => $_ENV['SUPPORT_EMAIL'] ?? 'support@your-app.com',
        
        // Optional - customize if needed  
        'enable_logging' => $_ENV['AUTH_ENABLE_LOGGING'] ?? 'true',
        'log_file' => $_ENV['AUTH_LOG_FILE'] ?? '/tmp/auth.log',
        'css_file' => 'style.css',
        'theme_color' => $_ENV['APP_THEME_COLOR'] ?? '#007cba'
    ];
}

/**
 * Get app-specific authentication callbacks/hooks
 * Customize these functions for your application behavior
 */
function getAuthCallbacks() {
    return [
        // Called after successful login - customize for your app
        'onSuccessfulLogin' => function($userInfo, $redirectUrl) {
            // Add any app-specific login logic here
            // For example: update user profile, log analytics, etc.
            
            // Set up any additional session variables your app needs
            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $userInfo['email'];
            $_SESSION["auth_email"] = $userInfo['email'];
            $_SESSION["user_id"] = $userInfo['user_id'] ?? null;
            $_SESSION["user_name"] = $userInfo['name'] ?? null;
            $_SESSION["roles"] = $userInfo['roles'] ?? ['user'];
            
            // Example: Get additional user data from your app's database
            // if (class_exists('YourUserClass')) {
            //     $user = new YourUserClass();
            //     $_SESSION["profile"] = $user->getProfile($userInfo['email']);
            // }
            
            return $redirectUrl ?: 'index.php'; // Return redirect URL
        },
        
        // Called before logout - customize for your app
        'onLogout' => function() {
            // Add any app-specific logout logic here
            // For example: log analytics, cleanup, etc.
        },
        
        // Called to determine logout redirect - customize for your app  
        'onLogoutRedirect' => function($defaultUrl) {
            // You can customize the logout redirect behavior
            // For example: redirect to IDP logout, or app home page
            return $defaultUrl; // or return custom URL
        },
        
        // Called to render app banner/header - customize for your app
        'renderBanner' => function() {
            // Render your app's banner/navigation
            // For example: include banner HTML file
            // if (file_exists(__DIR__ . '/../banner.html')) {
            //     include __DIR__ . '/../banner.html';
            // }
        }
    ];
}

/**
 * Check if current user is an admin in your app
 * Customize this function for your admin system
 */
function isAppAdmin() {
    // Customize this for your app's admin check
    // Example using session:
    return isset($_SESSION['roles']) && in_array('admin', $_SESSION['roles']);
    
    // Example using your app's admin class:
    // if (class_exists('YourAdminClass')) {
    //     $admin = new YourAdminClass();
    //     return $admin->isAdmin($_SESSION['email'] ?? null);
    // }
}

// Load environment variables if needed
if (file_exists(__DIR__ . '/../.env') && class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}
?>