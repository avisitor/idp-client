<?php
// Registration - Redirect to IDP
require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

ensureSession();

// Get redirect parameter for after registration
$redirectAfter = $_GET['redirect'] ?? '../';

// Get IDP configuration and build registration URL
$config = getIDPConfig();
$idpRegistrationUrl = $config['idp_url'] . '/register.php';

// Build return URL for after IDP registration
$callbackUrl = buildCallbackUrl($redirectAfter);

// Build full IDP registration URL with parameters
$idpUrl = $idpRegistrationUrl . '?' . http_build_query([
    'app' => $config['app_id'],
    'return' => $callbackUrl
]);

// Log the redirect
authLog("Registration redirecting to IDP: $idpUrl");

// Redirect immediately to IDP registration
header("Location: $idpUrl");
exit;
?>