<?php
/**
 * IDP-Client Installation Helper - Enhanced Version
 * 
 * Script to copy auth shell files and mail service integration files after composer install
 */

// Detect if we're in vendor directory
$vendorDir = null;
$projectRoot = null;

// Try multiple ways to detect the structure
if (file_exists(__DIR__ . '/../../autoload.php')) {
    // We're in vendor/avisitor/idp-client/
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

echo "IDP-Client: Setting up auth and mail service integration files...\n";
echo "Project root: $projectRoot\n";

// Find or create directories
$authDir = findOrCreateAuthDir($projectRoot);
$sitesDir = findOrCreateSitesDir($projectRoot);

if (!$authDir) {
    echo "Could not create auth directory. Please create manually and copy files from vendor/avisitor/idp-client/templates/\n";
    exit(1);
}

// Copy auth files
$templateDir = $vendorDir . '/avisitor/idp-client/templates';
$authCopied = copyAuthFiles($templateDir, $authDir);

// Copy mail service integration files
$mailCopied = copyMailServiceFiles($templateDir, $sitesDir);

echo "IDP-Client: Copied $authCopied auth files to $authDir\n";
echo "IDP-Client: Copied $mailCopied mail service files to $sitesDir\n";

echo "Next steps:\n";
echo "1. Edit auth/my-auth-config.php with your app settings\n";
echo "2. Set environment variables: IDP_APP_ID, MAIL_SERVICE_URL, MAIL_SERVICE_APP_ID\n";
echo "3. Create .env file with all required configuration (no dangerous defaults provided)\n";
echo "4. Your auth system is ready! Visit /auth/login.php\n";
echo "5. Mail service integration available at get-valid-token.php and auth/mail-sms-functions.php\n";
echo "6. Use validateAppConfig() to check for missing environment variables\n";

function findOrCreateAuthDir($projectRoot)
{
    // Try common locations
    $candidates = [
        $projectRoot . '/auth',
        $projectRoot . '/src/auth',
        $projectRoot . '/public/auth'
    ];
    
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            echo "Found existing auth directory: $dir\n";
            return $dir;
        }
    }
    
    // Create auth directory in project root
    $authDir = $projectRoot . '/auth';
    if (mkdir($authDir, 0755, true)) {
        echo "Created auth directory: $authDir\n";
        return $authDir;
    }
    
    return null;
}

function findOrCreateSitesDir($projectRoot)
{
    // Try common locations for sites/public directory
    $candidates = [
        $projectRoot . '/sites',
        $projectRoot . '/planting/sites',
        $projectRoot . '/src/sites', 
        $projectRoot . '/public',
        $projectRoot . '/web',
        $projectRoot . '/' // project root as fallback
    ];
    
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            echo "Found existing sites/public directory: $dir\n";
            return $dir;
        }
    }
    
    // Create sites directory in project root
    $sitesDir = $projectRoot . '/sites';
    if (mkdir($sitesDir, 0755, true)) {
        echo "Created sites directory: $sitesDir\n";
        return $sitesDir;
    }
    
    return $projectRoot; // Fallback to project root
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
    
    return copyFiles($templateDir, $authDir, $files);
}

function copyMailServiceFiles($templateDir, $sitesDir)
{
    // Create js subdirectory if needed
    $jsDir = $sitesDir . '/js';
    if (!is_dir($jsDir)) {
        mkdir($jsDir, 0755, true);
    }
    
    $files = [
        'get-valid-token.php' => 'Generic JWT token endpoint for mail service integration',
        'app-config.php' => 'Generic environment configuration loader (no dangerous defaults)'
    ];
    
    $jsFiles = [
        'auth/mail-sms-functions.php' => 'Generic JavaScript functions for mail/SMS log viewing'
    ];
    
    $copied = copyFiles($templateDir, $sitesDir, $files);
    $copied += copyFiles($templateDir, $sitesDir, $jsFiles);
    
    return $copied;
}

function copyFiles($templateDir, $destDir, $files)
{
    $copied = 0;
    
    foreach ($files as $file => $note) {
        $source = $templateDir . '/' . $file;
        $dest = $destDir . '/' . $file;
        
        if (!file_exists($source)) {
            continue;
        }
        
        if (file_exists($dest)) {
            echo "Skipping $file (already exists)\n";
            continue;
        }
        
        // Create directory if needed
        $destDirPath = dirname($dest);
        if (!is_dir($destDirPath)) {
            mkdir($destDirPath, 0755, true);
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