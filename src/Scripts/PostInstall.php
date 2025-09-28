<?php
/**
 * IDP-Client Post-Install Script
 * 
 * This script automatically copies auth shell files to the project's auth directory
 * after composer install/require.
 */

namespace WorldSpot\IDPClient\Scripts;

use Composer\Script\Event;
use Composer\IO\IOInterface;

class PostInstall
{
    public static function copyAuthFiles(Event $event)
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        
        // Determine auth directory location
        $authDir = self::findOrCreateAuthDir($projectRoot, $io);
        
        if (!$authDir) {
            $io->write('<info>IDP-Client: Skipping auth file copy - no auth directory found/created</info>');
            return;
        }
        
        // Copy shell files
        $templateDir = $vendorDir . '/worldspot/idp-client/templates';
        $copiedFiles = self::copyShellFiles($templateDir, $authDir, $io);
        
        if ($copiedFiles > 0) {
            $io->write("<info>IDP-Client: Copied {$copiedFiles} auth files to {$authDir}</info>");
            $io->write('<info>Next steps:</info>');
            $io->write('<info>1. Edit auth/auth-config.php with your app settings</info>');
            $io->write('<info>2. Set environment variables: APP_NAME, IDP_APP_ID, etc.</info>');
            $io->write('<info>3. Your auth system is ready! Visit /auth/login.php</info>');
        } else {
            $io->write('<info>IDP-Client: No files copied (already exist or permission issues)</info>');
        }
    }
    
    /**
     * Find or create auth directory
     */
    private static function findOrCreateAuthDir($projectRoot, IOInterface $io)
    {
        // Common auth directory patterns
        $candidates = [
            $projectRoot . '/auth',
            $projectRoot . '/public/auth', 
            $projectRoot . '/web/auth',
            $projectRoot . '/htdocs/auth',
            $projectRoot . '/www/auth',
            $projectRoot . '/src/auth'
        ];
        
        // Check if any exist
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                $io->write("<info>IDP-Client: Found existing auth directory: {$dir}</info>");
                return $dir;
            }
        }
        
        // Ask user where to create auth directory
        $io->write('<info>IDP-Client: No auth directory found.</info>');
        
        // Try to create in common locations
        $defaultDir = $projectRoot . '/auth';
        if (!is_dir($projectRoot) || !is_writable($projectRoot)) {
            return null;
        }
        
        if (!is_dir($defaultDir)) {
            if (mkdir($defaultDir, 0755, true)) {
                $io->write("<info>IDP-Client: Created auth directory: {$defaultDir}</info>");
                return $defaultDir;
            }
        }
        
        return null;
    }
    
    /**
     * Copy shell files to auth directory
     */
    private static function copyShellFiles($templateDir, $authDir, IOInterface $io)
    {
        if (!is_dir($templateDir)) {
            return 0;
        }
        
        $files = [
            'auth-config.php',
            'login.php',
            'register.php', 
            'reset.php',
            'change.php',
            'logout.php',
            'idp-callback.php',
            'verify.php',
            'index.php'
        ];
        
        $copiedCount = 0;
        
        foreach ($files as $file) {
            $source = $templateDir . '/' . $file;
            $dest = $authDir . '/' . $file;
            
            if (!file_exists($source)) {
                continue;
            }
            
            // Don't overwrite existing files (except README)
            if (file_exists($dest) && $file !== 'README.md') {
                $io->write("<comment>IDP-Client: Skipping {$file} (already exists)</comment>");
                continue;
            }
            
            if (copy($source, $dest)) {
                $copiedCount++;
                
                // Set proper permissions
                chmod($dest, 0644);
                
                if ($file === 'auth-config.php') {
                    $io->write("<comment>IDP-Client: Copied {$file} - CUSTOMIZE THIS FILE</comment>");
                } else {
                    $io->write("<info>IDP-Client: Copied {$file}</info>");
                }
            }
        }
        
        return $copiedCount;
    }
}
?>