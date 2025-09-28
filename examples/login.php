<?php
/**
 * Example Login Page
 * 
 * Simple login page that redirects to IDP for authentication
 */

require_once 'vendor/autoload.php';

use WorldSpot\IDPClient\IDPManager;

session_start();

// Check if user is already logged in
if (isset($_SESSION["username"]) && $_SESSION["username"]) {
    $_SESSION["loggedin"] = true;
    $idp = new IDPManager();
    $redirect = $_GET['from'] ?? $idp->buildAppUrl('/dashboard');
    header("location: $redirect");
    exit;
}

// Initialize IDP Manager
$idp = new IDPManager();

// Get redirect destination
$redirect = $_GET['from'] ?? $idp->buildAppUrl('/dashboard');

// Build return URL for IDP callback
$returnUrl = $idp->buildCallbackUrl($redirect);

// Get IDP login URL and redirect immediately
$loginUrl = $idp->getLoginUrl($returnUrl);

// Debug logging
error_log("Login redirect: $redirect");
error_log("Return URL: $returnUrl");  
error_log("IDP Login URL: $loginUrl");

// Redirect to IDP
header("Location: $loginUrl");
exit;
?>