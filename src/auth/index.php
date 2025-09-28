<?php
require_once file_exists('package-bootstrap.php') ? 'package-bootstrap.php' : 'auth-bootstrap.php';

// Ensure session is started
ensureSession();
 
// Do we send the user someplace particular or to the home page?
if(isset($_REQUEST['from']) && !empty($_REQUEST['from'])) {
    $redirect = $_REQUEST['from'];
    if(isset($_REQUEST['siteid']) && !empty($_REQUEST['siteid'])) {
        $redirect .= "?siteid=" . $_REQUEST['siteid'];
    }
} else {
    // Default to the application home page with absolute URL
    $redirect = buildAppUrl('/sites/index.php');
}

// Check if the user is already logged in, if yes then redirect him to welcome page

//if(isset($_SESSION["username"]) && $_SESSION["username"] === true){
if(isset($_SESSION["username"]) && $_SESSION["username"]) {
    $_SESSION["loggedin"] = true;
    // If there is more for this contact, get and save it
    if( !isset($_SESSION["contact"]) || sizeof($_SESSION["contact"]) < 1 ) {
        $p = new Participant();
        $_SESSION["contact"] = $p->get( $_SESSION["username"] );
        error_log( "login.php: Validated login" );
    }
    if( sizeof($_SESSION["contact"]) < 1 ) {
        $redirect = "..";
    }
    error_log( "login.php: _SESSION[username]: " . var_export( $_SESSION["username"], true ) );

    error_log( "login.php REQUEST " . var_export( $_REQUEST, true ) );
    error_log( "login.php GET " . var_export( $_GET, true ) );
    error_log( "login.php: redirect = $redirect" );
    header("location: $redirect");
    return;
}

// Immediately redirect to IDP for authentication
// No need to show local login form since we're using IDP delegation

$idp = getIDPClient();

// Build return URL
$returnUrl = buildCallbackUrl($redirect);

// Get IDP login URL and redirect immediately
$loginUrl = $idp->getLoginUrl($returnUrl);

// Debug logging
error_log("login.php DEBUG: redirect = $redirect");
error_log("login.php DEBUG: returnUrl = $returnUrl");
error_log("login.php DEBUG: loginUrl = $loginUrl");

// Redirect to IDP
header("Location: $loginUrl");
?>
