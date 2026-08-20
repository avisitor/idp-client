<?php

declare(strict_types=1);

/**
 * IDP client-side configuration endpoint.
 *
 * Emits window.IDP_CONFIG (idpUrl + appId) read solely from the consuming
 * application's .env file, so no IDP settings are hardcoded in - or accepted
 * via query params by - the application's HTML pages.
 *
 * Installation (see copy-js-client.php): this file must be copied to
 * <app-root>/js/app-config.php and loaded in HTML before idp-auth.js:
 *
 *   <script src="js/app-config.php"></script>
 *   <script src="js/idp-auth.js"></script>
 *   <script>
 *     IDPAuth.init({
 *       idpUrl: window.IDP_CONFIG.idpUrl,
 *       appId: window.IDP_CONFIG.appId,
 *       callbackUrl: window.location.href
 *     });
 *   </script>
 *
 * Requires in the application .env:
 *   IDP_URL     - e.g. https://idp.example.com
 *   IDP_APP_ID  - application ID registered with the IDP
 *
 * Requires the avisitor/env-loader package (for \Avisitor\Env\Loader).
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/vendor/autoload.php';
\Avisitor\Env\Loader::load(dirname(__DIR__) . '/.env');

$idpUrl = \Avisitor\Env\Loader::get('IDP_URL') ?: '';
$appId = \Avisitor\Env\Loader::get('IDP_APP_ID') ?: '';

if ($idpUrl === '' || $appId === '') {
    error_log('app-config.php: IDP_URL and/or IDP_APP_ID missing from .env');
}

// json_encode() safely escapes the values into JS string literals
echo 'window.IDP_CONFIG = {' . PHP_EOL;
echo '    idpUrl: ' . json_encode($idpUrl, JSON_UNESCAPED_SLASHES) . ',' . PHP_EOL;
echo '    appId: ' . json_encode($appId) . PHP_EOL;
echo '};' . PHP_EOL;
