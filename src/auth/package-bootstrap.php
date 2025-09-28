<?php
/**
 * Package Bootstrap for Standalone IDP-Client Auth Module
 * 
 * This bootstrap is used when running the auth module as a standalone
 * package without app-specific configuration. It provides minimal
 * defaults and expects configuration via environment variables.
 */

// Prevent multiple loading
if (defined('PACKAGE_BOOTSTRAP_LOADED')) {
    return;
}
define('PACKAGE_BOOTSTRAP_LOADED', true);

// ============================================================================
// PACKAGE DEPENDENCIES
// ============================================================================

// Load composer autoloader first
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
}

// Load the main IDPClient package
require_once __DIR__ . '/../helpers.php';

// Load environment variables (if dotenv is available)
$dotenvPath = findPackageDotenvFile();
if ($dotenvPath && class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->safeLoad();
}

/**
 * Find .env file for package usage
 */
function findPackageDotenvFile() {
    // Check if path is explicitly configured
    $configuredPath = $_ENV['DOTENV_PATH'] ?? getenv('DOTENV_PATH');
    if ($configuredPath && file_exists($configuredPath)) {
        return $configuredPath;
    }
    
    // Search upward from package directory
    $currentDir = __DIR__;
    $maxLevels = 10;
    
    for ($i = 0; $i < $maxLevels; $i++) {
        $dotenvPath = $currentDir . '/.env';
        if (file_exists($dotenvPath)) {
            return $dotenvPath;
        }
        
        $parentDir = dirname($currentDir);
        if ($parentDir === $currentDir) {
            break; // Reached filesystem root
        }
        $currentDir = $parentDir;
    }
    
    return null;
}

// ============================================================================
// MINIMAL APP CONFIGURATION STUBS
// ============================================================================

/**
 * Get minimal app configuration
 */
if (!function_exists('getAppSpecificConfig')) {
    function getAppSpecificConfig() {
        return [
            'app_name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?? 'Application',
            'app_url' => $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost'
        ];
    }
}

/**
 * Get IDP configuration from environment
 */
if (!function_exists('getAppIDPConfig')) {
    function getAppIDPConfig() {
        return [
            'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?? 'https://idp.worldspot.org',
            'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID') ?? 'default-app-id'
        ];
    }
}

/**
 * Minimal admin check
 */
if (!function_exists('isAppAdmin')) {
    function isAppAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }
}

/**
 * Minimal successful login hook
 */
if (!function_exists('onSuccessfulLogin')) {
    function onSuccessfulLogin($userInfo, $redirectUrl = null) {
        // Basic session setup
        $_SESSION["loggedin"] = true;
        $_SESSION["username"] = $userInfo['email'];
        $_SESSION["auth_email"] = $userInfo['email'];
        $_SESSION["auth_user_id"] = $userInfo['user_id'] ?? null;
        $_SESSION["auth_name"] = $userInfo['name'] ?? null;
        $_SESSION["roles"] = $userInfo['roles'] ?? ['user'];
        
        // Log successful authentication
        if (function_exists('authLog')) {
            authLog("User successfully logged in: " . ($userInfo['email'] ?? 'unknown'));
        }
        
        // Use provided redirect or default
        return $redirectUrl ?: buildAppUrl('index.php');
    }
}

/**
 * Minimal logout hook
 */
if (!function_exists('onLogout')) {
    function onLogout() {
        if (function_exists('authLog')) {
            $username = $_SESSION['username'] ?? 'unknown';
            authLog("User logging out: $username");
        }
    }
}

/**
 * Minimal logout redirect hook
 */
if (!function_exists('onLogoutRedirect')) {
    function onLogoutRedirect($defaultRedirectUrl) {
        // Simple redirect to home page
        return buildAppUrl('index.php');
    }
}

/**
 * Get minimal page configuration
 */
if (!function_exists('getAuthPageConfig')) {
    function getAuthPageConfig() {
        $appConfig = getAppSpecificConfig();
        
        return [
            'app_name' => $appConfig['app_name'],
            'banner_file' => '', // No banner in package mode
            'css_file' => 'style.css',
            'support_email' => $_ENV['SUPPORT_EMAIL'] ?? getenv('SUPPORT_EMAIL') ?? 'support@' . parse_url($appConfig['app_url'], PHP_URL_HOST),
            'logo_url' => $_ENV['APP_LOGO_URL'] ?? getenv('APP_LOGO_URL') ?? '',
            'theme_color' => $_ENV['APP_THEME_COLOR'] ?? getenv('APP_THEME_COLOR') ?? '#007cba'
        ];
    }
}

/**
 * Render minimal auth page header
 */
if (!function_exists('renderAuthPageHeader')) {
    function renderAuthPageHeader($title = 'Authentication') {
        $config = getAuthPageConfig();
        
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta name="viewport" content="initial-scale=1.0, user-scalable=no">';
        echo '<meta charset="utf-8">';
        echo '<title>' . htmlspecialchars($config['app_name']) . ' - ' . htmlspecialchars($title) . '</title>';
        
        if (file_exists(__DIR__ . '/' . $config['css_file'])) {
            echo '<link href="' . htmlspecialchars($config['css_file']) . '" type="text/css" rel="stylesheet" />';
        }
        
        echo '</head>';
        echo '<body>';
        
        echo '<div id="header">';
        echo '<h3>' . htmlspecialchars($title) . '</h3>';
        echo '</div>';
        echo '<div id="wrap">';
    }
}

/**
 * Render minimal auth page footer
 */
if (!function_exists('renderAuthPageFooter')) {
    function renderAuthPageFooter() {
        echo '</div>'; // Close wrap
        echo '</body>';
        echo '</html>';
    }
}

/**
 * Build sites URL compatibility function
 */
if (!function_exists('buildSitesUrl')) {
    function buildSitesUrl($page = 'index.php') {
        return buildAppUrl($page);
    }
}

// ============================================================================
// LOAD THE GENERIC BOOTSTRAP
// ============================================================================

// Now load the main auth bootstrap (which will use our stubs above)
require_once __DIR__ . '/auth-bootstrap.php';

?>