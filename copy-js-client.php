<?php

declare(strict_types=1);

/**
 * Copy the JS client (idp-auth.js + app-config.php) into the consuming
 * application's js/ directory, creating it if necessary.
 *
 * Wire it up in the application's composer.json so copies refresh on every
 * install/update (keeps the vendor tree out of the web-served docroot):
 *
 *   "scripts": {
 *       "copy-js-client": "@php vendor/avisitor/idp-client/copy-js-client.php",
 *       "post-install-cmd": ["@copy-js-client"],
 *       "post-update-cmd": ["@copy-js-client"]
 *   }
 *
 * Can also be run manually: php vendor/avisitor/idp-client/copy-js-client.php [app-root]
 *
 * Files are always overwritten so package updates propagate.
 */

// Detect the consuming application root
$projectRoot = null;

if (file_exists(__DIR__ . '/../../autoload.php')) {
    // We're in <app>/vendor/avisitor/idp-client/
    $projectRoot = dirname(dirname(__DIR__));
} elseif (isset($argv[1])) {
    $projectRoot = realpath($argv[1]);
    if (!$projectRoot) {
        fwrite(STDERR, "copy-js-client: path '{$argv[1]}' does not exist" . PHP_EOL);
        exit(1);
    }
} else {
    fwrite(STDERR, 'copy-js-client: could not detect app root; pass it as argument, e.g.' . PHP_EOL);
    fwrite(STDERR, '  php copy-js-client.php /path/to/app' . PHP_EOL);
    exit(1);
}

$destDir = $projectRoot . '/js';
if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
    fwrite(STDERR, "copy-js-client: could not create directory: {$destDir}" . PHP_EOL);
    exit(1);
}

$copied = 0;
foreach (['idp-auth.js', 'app-config.php'] as $file) {
    $source = __DIR__ . '/' . $file;
    $dest = $destDir . '/' . $file;

    if (!is_file($source)) {
        fwrite(STDERR, "copy-js-client: source missing, package broken?: {$source}" . PHP_EOL);
        exit(1);
    }

    if (!copy($source, $dest)) {
        fwrite(STDERR, "copy-js-client: failed to copy to {$dest}" . PHP_EOL);
        exit(1);
    }

    chmod($dest, 0644);
    $copied++;
}

echo "copy-js-client: copied {$copied} files -> {$destDir}/" . PHP_EOL;
