<?php
/**
 * Logout Shell - Copy this file to your auth directory
 * 
 * Minimal 4-line shell that delegates to package LogoutHandler
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/my-auth-config.php'; // Your app customizations

$handler = new \WorldSpot\IDPClient\Auth\LogoutHandler(getAuthConfig(), getAuthCallbacks());
$handler->handle();
?>