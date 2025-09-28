<?php
// Password Change - Redirect to IDP
require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

ensureSession();

// Get redirect parameter for after password change
$redirectAfter = $_GET['redirect'] ?? '../';

// Get IDP configuration and build change URL
$config = getIDPConfig();
$idpChangeUrl = $config['idp_url'] . '/change.php';

// Build return URL for after IDP password change
$callbackUrl = buildCallbackUrl($redirectAfter);

// Build full IDP change URL with parameters
$idpUrl = $idpChangeUrl . '?' . http_build_query([
    'app' => $config['app_id'],
    'return' => $callbackUrl
]);

// Log the redirect
authLog("Password change redirecting to IDP: $idpUrl");

// Redirect immediately to IDP password change
header("Location: $idpUrl");
exit;
?>