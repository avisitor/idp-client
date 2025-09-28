<?php
/**
 * Generic Email Verification Handler
 * 
 * Uses app configuration hooks for branding and styling
 */

require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

// Render generic page header with app-specific branding
renderAuthPageHeader('Email Verification');

if(isset($_GET['token']) && !empty($_GET['token'])){
    // Verify via IDP
    $token = $_GET['token'];
    
    $verificationResult = verifyEmailToken($token);
    
    if($verificationResult['success']) {
        echo '<div class="statusmsg">Your email has been verified! You can now <a href="' . buildAuthUrl('login.php') . '">login</a> to your account.</div>';
        // Redirect to login page after a delay
        echo '<script>setTimeout(function(){ window.location.href = "' . buildAuthUrl('login.php') . '"; }, 3000);</script>';
    } else {
        echo '<div class="statusmsg">Verification failed: ' . htmlspecialchars($verificationResult['error']) . '</div>';
        $config = getAuthPageConfig();
        echo '<div class="statusmsg">Please try requesting a new verification email or contact support at ' . htmlspecialchars($config['support_email']) . '.</div>';
    }
} else {
    // Invalid approach
    $config = getAuthPageConfig();
    echo '<div class="statusmsg">Invalid verification link. Please use the link that has been sent to your email. If you have not received the verification email, request help at ' . htmlspecialchars($config['support_email']) . '.</div>';
}

// Render generic page footer
renderAuthPageFooter();
?>
