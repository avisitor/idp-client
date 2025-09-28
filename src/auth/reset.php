<?php
// Password Reset - Redirect to IDP
require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

ensureSession();

// Get redirect parameter for after reset
$redirectAfter = $_GET['redirect'] ?? '../';

// Get IDP configuration and build reset URL
$config = getIDPConfig();
$idpResetUrl = $config['idp_url'] . '/reset.php';

// Build return URL for after IDP reset
$callbackUrl = buildCallbackUrl($redirectAfter);

// Build full IDP reset URL with parameters
$idpUrl = $idpResetUrl . '?' . http_build_query([
    'app' => $config['app_id'],
    'return' => $callbackUrl
]);

// Log the redirect
authLog("Password reset redirecting to IDP: $idpUrl");

// Redirect immediately to IDP reset
header("Location: $idpUrl");
exit;
?>
