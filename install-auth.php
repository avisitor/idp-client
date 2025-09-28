<?php
/**
 * IDP-Client Installation Helper
 * 
 * Simple script to copy auth shell files after composer install
 */

// Detect if we're in vendor directory
$vendorDir = null;
$projectRoot = null;

// Try multiple ways to detect the structure
if (file_exists(__DIR__ . '/../../autoload.php')) {
    // We're in vendor/worldspot/idp-client/
    $vendorDir = dirname(dirname(__DIR__));
    $projectRoot = dirname($vendorDir);
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // We're in project root
    $projectRoot = __DIR__;
    $vendorDir = __DIR__ . '/vendor';
} else {
    echo "Could not detect project structure.\n";
    exit(1);
}

echo "IDP-Client: Setting up auth files...\n";
echo "Project root: $projectRoot\n";

// Find or create auth directory
$authDir = findOrCreateAuthDir($projectRoot);

if (!$authDir) {
    echo "Could not create auth directory. Please create manually and copy files from vendor/worldspot/idp-client/templates/\n";
    exit(1);
}

// Copy files
$templateDir = $vendorDir . '/worldspot/idp-client/templates';
$copied = copyAuthFiles($templateDir, $authDir);

echo "IDP-Client: Copied $copied files to $authDir\n";
echo "Next steps:\n";
echo "1. Edit auth/auth-config.php with your app settings\n";
echo "2. Set environment variables: APP_NAME, IDP_APP_ID, etc.\n";
echo "3. Your auth system is ready! Visit /auth/login.php\n";

function findOrCreateAuthDir($projectRoot)
{
    // Try common locations
    $candidates = [
        $projectRoot . '/auth',
        $projectRoot . '/public/auth',
        $projectRoot . '/web/auth'
    ];
    
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            echo "Found existing auth directory: $dir\n";
            return $dir;
        }
    }
    
    // Create default
    $defaultDir = $projectRoot . '/auth';
    if (mkdir($defaultDir, 0755, true)) {
        echo "Created auth directory: $defaultDir\n";
        return $defaultDir;
    }
    
    return null;
}

function copyAuthFiles($templateDir, $authDir)
{
    $files = [
        'my-auth-config.php' => 'CUSTOMIZE THIS FILE - Set your app details',
        'login.php' => null,
        'register.php' => null,
        'reset.php' => null,
        'change.php' => null, 
        'logout.php' => null,
        'idp-callback.php' => null,
        'verify.php' => null,
        'index.php' => null
    ];
    
    $copied = 0;
    
    foreach ($files as $file => $note) {
        $source = $templateDir . '/' . $file;
        $dest = $authDir . '/' . $file;
        
        if (!file_exists($source)) {
            continue;
        }
        
        if (file_exists($dest)) {
            echo "Skipping $file (already exists)\n";
            continue;
        }
        
        if (copy($source, $dest)) {
            chmod($dest, 0644);
            $copied++;
            
            if ($note) {
                echo "Copied $file - $note\n";
            } else {
                echo "Copied $file\n";
            }
        }
    }
    
    return $copied;
}
?>