<?php
/**
 * Generic Application Configuration Loader
 * Centralizes environment loading and configuration management
 * 
 * Features:
 * - CLI-compatible environment loading (falls back to getenv())
 * - Conflict-safe function declarations (checks function_exists)
 * - Centralized app and IDP configuration
 * - No dangerous default values - requires proper environment setup
 * 
 * Part of avisitor/idp-client package
 */

// Auto-detect .env file location
$envPaths = [
    __DIR__ . '/.env',           // Same directory as this file
    __DIR__ . '/../.env',        // Parent directory  
    __DIR__ . '/../../.env',     // Two levels up
    getcwd() . '/.env'           // Current working directory
];

$envLoaded = false;

// Load environment variables once, globally
if (!defined('APP_CONFIG_LOADED')) {
    // Auto-detect vendor path
    $vendorPaths = [
        __DIR__ . '/../../vendor/autoload.php',  // sites/app-config.php
        __DIR__ . '/../vendor/autoload.php',     // app-config.php in root
        __DIR__ . '/vendor/autoload.php'        // app-config.php in project
    ];
    
    foreach ($vendorPaths as $vendorPath) {
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            break;
        }
    }
    
    // Try to load .env file from various locations
    if (class_exists('Dotenv\Dotenv')) {
        foreach ($envPaths as $envPath) {
            $envDir = dirname($envPath);
            if (file_exists($envPath)) {
                try {
                    $dotenv = Dotenv\Dotenv::createImmutable($envDir);
                    $dotenv->load();
                    $envLoaded = true;
                    break;
                } catch (Exception $e) {
                    // Continue to next path
                }
            }
        }
    }
    
    define('APP_CONFIG_LOADED', true);
}

/**
 * Get environment variable with fallback to getenv() for CLI compatibility
 * No default values - applications must provide proper configuration
 */
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        // Return the value if found, otherwise the default
        return $value !== false ? $value : $default;
    }
}

/**
 * Get application configuration from environment
 * Returns null for missing values - applications must handle properly
 */
if (!function_exists('getAppConfig')) {
    function getAppConfig()
    {
        static $config = null;
        
        if ($config === null) {
            $config = [
                'name' => getEnvVar('APP_NAME'),
                'domain' => getEnvVar('APP_DOMAIN'),
                'base_path' => getEnvVar('APP_BASE_PATH'),
                'auth_path' => getEnvVar('APP_AUTH_PATH'),
                'sites_path' => getEnvVar('APP_SITES_PATH'),
                'email' => getEnvVar('APP_EMAIL'),
                'protocol' => getEnvVar('APP_PROTOCOL')
            ];
        }
        
        return $config;
    }
}

/**
 * Get IDP configuration from environment
 * Returns null for missing values - applications must handle properly
 */
if (!function_exists('getIDPConfig')) {
    function getIDPConfig()
    {
        static $config = null;
        
        if ($config === null) {
            $config = [
                'idp_url' => getEnvVar('IDP_URL'),
                'app_id' => getEnvVar('IDP_APP_ID')
            ];
        }
        
        return $config;
    }
}

/**
 * Validate that required environment variables are set
 * Throws exception if critical variables are missing
 */
if (!function_exists('validateAppConfig')) {
    function validateAppConfig($requiredVars = []) {
        $missing = [];
        
        // Default required variables for idp-client
        $defaultRequired = ['IDP_URL', 'IDP_APP_ID'];
        $checkVars = array_merge($defaultRequired, $requiredVars);
        
        foreach ($checkVars as $var) {
            if (empty(getEnvVar($var))) {
                $missing[] = $var;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception('Required environment variables not set: ' . implode(', ', $missing));
        }
        
        return true;
    }
}

/**
 * Get mail service configuration from environment
 */
if (!function_exists('getMailServiceConfig')) {
    function getMailServiceConfig()
    {
        static $config = null;
        
        if ($config === null) {
            $config = [
                'url' => getEnvVar('MAIL_SERVICE_URL'),
                'app_id' => getEnvVar('MAIL_SERVICE_APP_ID')
            ];
        }
        
        return $config;
    }
}
?>