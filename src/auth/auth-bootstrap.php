<?php
/**
 * Generic Authentication Bootstrap
 * 
 * This file provides a portable authentication framework that can be
 * integrated with any application. All app-specific dependencies are
 * isolated in auth-app-config.php.
 * 
 * Features:
 * - Environment-based configuration
 * - Conditional session management
 * - Portable URL builders
 * - Generic authentication helpers
 * - IDP integration framework
 */

// Prevent multiple loading
if (defined('AUTH_BOOTSTRAP_LOADED')) {
    return;
}
define('AUTH_BOOTSTRAP_LOADED', true);

// ============================================================================
// LOAD APP-SPECIFIC CONFIGURATION FIRST
// ============================================================================

// Load app-specific configuration (skip in package mode)
if (file_exists(__DIR__ . '/auth-app-config.php')) {
    require_once __DIR__ . '/auth-app-config.php';
} else {
    // We're running in package mode - app config should be provided externally
    // This auth-bootstrap.php should be loaded by package-bootstrap.php
}

// ============================================================================
// ENVIRONMENT & DEPENDENCY SETUP
// ============================================================================

/**
 * Simple .env file discovery using app configuration
 */
if (!function_exists('findDotenvFile')) {
    function findDotenvFile() {
        // Use app-defined path (set by app-specific bootstrap)
        if (defined('APP_DOTENV_PATH') && file_exists(APP_DOTENV_PATH)) {
            return APP_DOTENV_PATH;
        }
        
        // Fallback to environment variable
        $configuredPath = $_ENV['DOTENV_PATH'] ?? getenv('DOTENV_PATH');
        if ($configuredPath && file_exists($configuredPath)) {
            return $configuredPath;
        }
        
        return null;
    }
}

// Load environment variables (if available)
$dotenvPath = findDotenvFile();
if ($dotenvPath && class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->safeLoad();
}

// ============================================================================
// SESSION MANAGEMENT
// ============================================================================

/**
 * Ensure session is started if needed
 * Conditional session management - only starts if not in CLI mode
 */
if (!function_exists('ensureSession')) {
    function ensureSession() {
        // Don't start sessions in CLI mode or if already started
        if (php_sapi_name() === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Start session with secure settings
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Lax'
            ]);
        }
    }
}

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Generic authentication logging function
 * Uses environment configuration to determine log behavior
 */
if (!function_exists('authLog')) {
    function authLog($message) {
        $enableLogging = $_ENV['AUTH_ENABLE_LOGGING'] ?? getenv('AUTH_ENABLE_LOGGING');
        
        if ($enableLogging && $enableLogging !== 'false') {
            $logFile = $_ENV['AUTH_LOG_FILE'] ?? getenv('AUTH_LOG_FILE') ?? '/tmp/auth.log';
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] $message" . PHP_EOL;
            
            // Ensure log directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
}

// ============================================================================
// URL BUILDERS
// ============================================================================

/**
 * Build authentication URLs based on environment configuration
 */
if (!function_exists('buildAuthUrl')) {
    function buildAuthUrl($page, $params = []) {
        // Get base URL from environment or auto-detect
        $baseUrl = $_ENV['AUTH_BASE_URL'] ?? getenv('AUTH_BASE_URL');
        
        if (!$baseUrl) {
            // Auto-detect base URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $baseUrl = $protocol . '://' . $host . $scriptDir;
        }
        
        // Ensure base URL ends with auth directory
        if (!str_ends_with($baseUrl, '/auth')) {
            $baseUrl = rtrim($baseUrl, '/') . '/auth';
        }
        
        $url = $baseUrl . '/' . ltrim($page, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
}

/**
 * Build app URLs (typically for redirects after auth)
 */
if (!function_exists('buildAppUrl')) {
    function buildAppUrl($page = '', $params = []) {
        // Get app base URL from environment
        $appBaseUrl = $_ENV['APP_BASE_URL'] ?? getenv('APP_BASE_URL');
        
        if (!$appBaseUrl) {
            // Auto-detect and assume app is one level up from auth
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $appBaseUrl = $protocol . '://' . $host . $scriptDir;
        }
        
        $url = rtrim($appBaseUrl, '/');
        
        if ($page) {
            $url .= '/' . ltrim($page, '/');
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
}

// ============================================================================
// AUTHENTICATION STATE HELPERS
// ============================================================================

/**
 * Check if user is currently authenticated
 */
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        ensureSession();
        return !empty($_SESSION['authenticated']) && !empty($_SESSION['username']);
    }
}

/**
 * Get current authenticated user info
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        ensureSession();
        
        if (!isAuthenticated()) {
            return null;
        }
        
        return [
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'is_admin' => $_SESSION['is_admin'] ?? false,
            'authenticated_at' => $_SESSION['authenticated_at'] ?? null
        ];
    }
}

/**
 * Set authentication session data
 */
if (!function_exists('setAuthenticatedUser')) {
    function setAuthenticatedUser($userInfo) {
        ensureSession();
        
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $userInfo['username'] ?? $userInfo['email'] ?? 'unknown';
        $_SESSION['email'] = $userInfo['email'] ?? '';
        $_SESSION['is_admin'] = $userInfo['is_admin'] ?? false;
        $_SESSION['authenticated_at'] = time();
        
        // Store any additional user data
        foreach ($userInfo as $key => $value) {
            if (!in_array($key, ['username', 'email', 'is_admin', 'authenticated_at'])) {
                $_SESSION['user_' . $key] = $value;
            }
        }
    }
}

/**
 * Clear authentication session
 */
if (!function_exists('clearAuthentication')) {
    function clearAuthentication() {
        ensureSession();
        
        // Clear authentication-specific session data
        $keysToKeep = ['_token']; // Keep CSRF tokens and other non-auth data
        $sessionData = [];
        
        foreach ($keysToKeep as $key) {
            if (isset($_SESSION[$key])) {
                $sessionData[$key] = $_SESSION[$key];
            }
        }
        
        session_destroy();
        session_start();
        
        // Restore non-auth session data
        foreach ($sessionData as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }
}

// ============================================================================
// REDIRECT HELPERS
// ============================================================================

/**
 * Redirect to a URL with optional message
 */
if (!function_exists('redirectWithMessage')) {
    function redirectWithMessage($url, $message = null, $type = 'info') {
        ensureSession();
        
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        
        header("Location: $url");
        exit;
    }
}

/**
 * Get and clear flash message
 */
if (!function_exists('getFlashMessage')) {
    function getFlashMessage() {
        ensureSession();
        
        $message = $_SESSION['flash_message'] ?? null;
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        return $message ? ['message' => $message, 'type' => $type] : null;
    }
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================

/**
 * Validate email format
 */
if (!function_exists('isValidEmail')) {
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validate password strength (basic)
 */
if (!function_exists('isValidPassword')) {
    function isValidPassword($password) {
        $minLength = $_ENV['AUTH_MIN_PASSWORD_LENGTH'] ?? getenv('AUTH_MIN_PASSWORD_LENGTH') ?? 8;
        return strlen($password) >= $minLength;
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Initialize session if not in CLI mode
if (php_sapi_name() !== 'cli') {
    ensureSession();
}

// Log bootstrap completion
authLog("Auth bootstrap loaded successfully");

?>