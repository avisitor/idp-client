<?php

namespace WorldSpot\IDPClient\Auth\Providers;

/**
 * Authentication Provider Factory
 * 
 * Factory class that creates the appropriate authentication provider
 * based on the USE_EXTERNAL_AUTH configuration setting.
 * 
 * Applications should provide their own LocalAuthProvider implementation
 * by extending the abstract LocalAuthProvider class.
 * 
 * @package WorldSpot\IDPClient
 * @subpackage Auth\Providers
 */
class AuthFactory 
{
    private static $instance = null;
    private static $config = [];
    private static $localProviderClass = null;
    
    /**
     * Set configuration for the factory
     * 
     * @param array $config Configuration array with keys:
     *                      - use_external_auth: boolean
     *                      - app_base_url: string
     *                      - app_auth_path: string
     *                      - idp_url: string (if using external auth)
     *                      - idp_app_id: string (if using external auth)
     */
    public static function setConfig(array $config): void {
        self::$config = $config;
        // Clear instance when config changes
        self::$instance = null;
    }
    
    /**
     * Set the local provider class name
     * Applications must provide their own LocalAuthProvider implementation
     * 
     * @param string $className Fully qualified class name that extends LocalAuthProvider
     */
    public static function setLocalProviderClass(string $className): void {
        self::$localProviderClass = $className;
        // Clear instance when provider class changes
        self::$instance = null;
    }
    
    /**
     * Create authentication provider based on configuration
     * 
     * @param array|null $config Optional config to override default
     * @return AuthProviderInterface The appropriate auth provider instance
     * @throws \Exception If configuration is invalid or provider cannot be created
     */
    public static function create(?array $config = null): AuthProviderInterface {
        if ($config !== null) {
            self::setConfig($config);
        }
        
        $useExternal = filter_var(self::$config['use_external_auth'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        try {
            if ($useExternal) {
                error_log("AuthFactory: Creating ExternalAuthProvider");
                return new ExternalAuthProvider(self::$config);
            } else {
                error_log("AuthFactory: Creating LocalAuthProvider");
                
                // Use the configured local provider class
                if (self::$localProviderClass && class_exists(self::$localProviderClass)) {
                    $className = self::$localProviderClass;
                    return new $className(self::$config);
                }
                
                throw new \Exception("Local auth provider class not configured. Call AuthFactory::setLocalProviderClass() first.");
            }
        } catch (\Exception $e) {
            error_log("AuthFactory: Error creating auth provider: " . $e->getMessage());
            
            // Fallback to external auth on error if configured
            if ($useExternal) {
                throw $e;
            }
            
            // If local auth failed and external is not configured, re-throw
            throw $e;
        }
    }
    
    /**
     * Get singleton instance of authentication provider
     * Use this when you need to maintain state across multiple calls
     * 
     * @param array|null $config Optional config to override default
     * @return AuthProviderInterface The auth provider instance
     */
    public static function getInstance(?array $config = null): AuthProviderInterface {
        if (self::$instance === null || $config !== null) {
            self::$instance = self::create($config);
        }
        
        return self::$instance;
    }
    
    /**
     * Clear the singleton instance (useful for testing or config changes)
     */
    public static function clearInstance(): void {
        self::$instance = null;
    }
    
    /**
     * Check if external authentication is configured and enabled
     * 
     * @return bool True if external auth is enabled and configured
     */
    public static function isExternalAuthEnabled(): bool {
        $useExternal = filter_var(self::$config['use_external_auth'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (!$useExternal) {
            return false;
        }
        
        // Check if required configuration is present
        $idpUrl = self::$config['idp_url'] ?? '';
        $idpAppId = self::$config['idp_app_id'] ?? '';
        
        return !empty($idpUrl) && !empty($idpAppId);
    }
    
    /**
     * Get authentication provider type without creating instance
     * 
     * @return string 'local' or 'external'
     */
    public static function getProviderType(): string {
        return self::isExternalAuthEnabled() ? 'external' : 'local';
    }
    
    /**
     * Test authentication provider configuration
     * 
     * @return array Test results with success status and details
     */
    public static function testConfiguration(): array {
        $results = [
            'provider_type' => self::getProviderType(),
            'tests' => []
        ];
        
        try {
            $provider = self::create();
            
            // Test basic provider creation
            $results['tests']['provider_creation'] = [
                'success' => true,
                'message' => 'Provider created successfully',
                'provider_class' => get_class($provider)
            ];
            
            // Test configuration
            if ($provider->isExternalAuth()) {
                $results['tests']['external_config'] = [
                    'success' => self::isExternalAuthEnabled(),
                    'message' => self::isExternalAuthEnabled() ? 'External auth properly configured' : 'External auth configuration incomplete'
                ];
            } else {
                $results['tests']['local_config'] = [
                    'success' => true,
                    'message' => 'Local auth configuration available'
                ];
            }
            
            // Test URL generation
            try {
                $loginUrl = $provider->getLoginUrl();
                $results['tests']['url_generation'] = [
                    'success' => !empty($loginUrl),
                    'message' => !empty($loginUrl) ? 'URL generation working' : 'URL generation failed',
                    'login_url' => $loginUrl
                ];
            } catch (\Exception $e) {
                $results['tests']['url_generation'] = [
                    'success' => false,
                    'message' => 'URL generation failed: ' . $e->getMessage()
                ];
            }
            
        } catch (\Exception $e) {
            $results['tests']['provider_creation'] = [
                'success' => false,
                'message' => 'Provider creation failed: ' . $e->getMessage()
            ];
        }
        
        return $results;
    }
}
