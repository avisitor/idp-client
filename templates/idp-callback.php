<?php
/**
 * IDP Callback Shell - Copy this file to your auth directory
 * 
 * Minimal 4-line shell that delegates to package CallbackHandler
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/my-auth-config.php'; // Your app customizations

$handler = new \WorldSpot\IDPClient\Auth\CallbackHandler(getAuthConfig(), getAuthCallbacks());
$handler->handle();
?>