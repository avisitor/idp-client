<?php

namespace WorldSpot\IDPClient\Auth;

/**
 * Core Authentication Handler
 * 
 * Base class for all auth handlers that provides common functionality
 * and configuration injection.
 */
class AuthHandler
{
    protected $config;
    protected $idpManager;
    protected $appCallbacks;
    
    public function __construct($config = [], $appCallbacks = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->appCallbacks = $appCallbacks;
        
        // Validate configuration before proceeding
        $this->validateConfig();
        
        // Initialize IDP Manager
        $this->idpManager = $this->createIDPManager();
    }
    
    /**
     * Get default configuration
     */
    protected function getDefaultConfig()
    {
        return [
            'app_name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME'),
            'app_url' => $_ENV['APP_URL'] ?? getenv('APP_URL'),
            'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL'),
            'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID'),
            'support_email' => $_ENV['SUPPORT_EMAIL'] ?? getenv('SUPPORT_EMAIL'),
            'enable_logging' => $_ENV['AUTH_ENABLE_LOGGING'] ?? getenv('AUTH_ENABLE_LOGGING') ?? 'false',
            'log_file' => $_ENV['AUTH_LOG_FILE'] ?? getenv('AUTH_LOG_FILE'),
            'css_file' => 'style.css',
            'theme_color' => $_ENV['APP_THEME_COLOR'] ?? getenv('APP_THEME_COLOR') ?? '#007cba'
        ];
    }
    
    /**
     * Validate required configuration
     */
    protected function validateConfig()
    {
        $required = [
            'app_name' => 'APP_NAME',
            'app_url' => 'APP_URL', 
            'idp_url' => 'IDP_URL',
            'app_id' => 'IDP_APP_ID',
            'support_email' => 'SUPPORT_EMAIL'
        ];
        
        $missing = [];
        $invalid = [];
        
        foreach ($required as $configKey => $envVar) {
            $value = $this->config[$configKey] ?? null;
            
            if (empty($value)) {
                $missing[] = $envVar;
                $this->configLog("MISSING: Environment variable '$envVar' is required but not set");
            } else {
                // Validate specific formats
                if ($configKey === 'app_url' || $configKey === 'idp_url') {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $invalid[] = "$envVar (invalid URL format: '$value')";
                        $this->configLog("INVALID: $envVar contains invalid URL: '$value'");
                    }
                } elseif ($configKey === 'support_email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $invalid[] = "$envVar (invalid email format: '$value')";
                        $this->configLog("INVALID: $envVar contains invalid email: '$value'");
                    }
                }
            }
        }
        
        // Check optional but recommended settings
        if (empty($this->config['log_file']) && ($this->config['enable_logging'] === 'true' || $this->config['enable_logging'] === true)) {
            $this->configLog("WARNING: AUTH_ENABLE_LOGGING is enabled but AUTH_LOG_FILE is not set");
        }
        
        // Throw detailed error if anything is missing or invalid
        if (!empty($missing) || !empty($invalid)) {
            $errors = [];
            
            if (!empty($missing)) {
                $errors[] = "Missing required environment variables: " . implode(', ', $missing);
            }
            
            if (!empty($invalid)) {
                $errors[] = "Invalid configuration values: " . implode(', ', $invalid);
            }
            
            $message = "IDP-Client Configuration Error:\n\n" . implode("\n", $errors) . 
                       "\n\nPlease check your .env file and ensure all required environment variables are properly set.";
            
            $this->configLog("FATAL: Configuration validation failed - " . implode("; ", $errors));
            
            throw new \InvalidArgumentException($message);
        }
        
        $this->configLog("SUCCESS: All required configuration variables are present and valid");
    }
    
    /**
     * Log configuration validation messages
     */
    protected function configLog($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] CONFIG: $message" . PHP_EOL;
        
        // Always log config issues to error_log for immediate visibility
        error_log("IDP-Client Config: $message");
        
        // Also write to file if possible (but don't fail if we can't)
        if (!empty($this->config['log_file'])) {
            $logDir = dirname($this->config['log_file']);
            if (is_dir($logDir) || mkdir($logDir, 0755, true)) {
                @file_put_contents($this->config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
            }
        }
    }
    
    /**
     * Create IDP Manager instance
     */
    protected function createIDPManager()
    {
        if (class_exists('\\WorldSpot\\IDPClient\\IDPManager')) {
            return new \WorldSpot\IDPClient\IDPManager(null, [
                'IDP_URL' => $this->config['idp_url'],
                'IDP_APP_ID' => $this->config['app_id']
            ]);
        }
        
        return null;
    }
    
    /**
     * Start session if needed
     */
    protected function ensureSession()
    {
        if (php_sapi_name() === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Lax'
            ]);
        }
    }
    
    /**
     * Log authentication events
     */
    protected function authLog($message)
    {
        if ($this->config['enable_logging'] === 'true' || $this->config['enable_logging'] === true) {
            error_log( "AuthHandler::authLog: $message" );
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] $message" . PHP_EOL;
            
            $logDir = dirname($this->config['log_file']);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($this->config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Build authentication URL
     */
    protected function buildAuthUrl($page, $params = [])
    {
        $baseUrl = $this->config['app_url'];
        if (!str_ends_with($baseUrl, '/auth')) {
            $baseUrl = rtrim($baseUrl, '/') . '/auth';
        }
        
        $url = $baseUrl . '/' . ltrim($page, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Build app URL
     */
    protected function buildAppUrl($page = '', $params = [])
    {
        $url = rtrim($this->config['app_url'], '/');
        
        if ($page) {
            $url .= '/' . ltrim($page, '/');
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Call app callback if available
     */
    protected function callAppCallback($name, ...$args)
    {
        if (isset($this->appCallbacks[$name]) && is_callable($this->appCallbacks[$name])) {
            return call_user_func_array($this->appCallbacks[$name], $args);
        }
        
        return null;
    }
    
    /**
     * Render HTML response
     */
    protected function renderResponse($title, $content, $autoRedirect = null)
    {
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta name="viewport" content="initial-scale=1.0, user-scalable=no">';
        echo '<meta charset="utf-8">';
        echo '<title>' . htmlspecialchars($this->config['app_name']) . ' - ' . htmlspecialchars($title) . '</title>';
        
        // Try to load CSS from same directory as the calling script
        $cssPath = dirname($_SERVER['SCRIPT_NAME']) . '/' . $this->config['css_file'];
        echo '<link href="' . htmlspecialchars($cssPath) . '" type="text/css" rel="stylesheet" />';
        
        if ($autoRedirect) {
            echo '<script>setTimeout(function(){ window.location.href = "' . htmlspecialchars($autoRedirect) . '"; }, 3000);</script>';
        }
        
        echo '</head>';
        echo '<body>';
        
        // Call app banner hook if available
        $this->callAppCallback('renderBanner');
        
        echo '<div id="header">';
        echo '<h3>' . htmlspecialchars($title) . '</h3>';
        echo '</div>';
        echo '<div id="wrap">';
        echo $content;
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
    
    /**
     * Redirect to URL
     */
    protected function redirect($url, $message = null, $type = 'info')
    {
        $this->ensureSession();
        
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        
        header("Location: $url");
        exit;
    }
}
