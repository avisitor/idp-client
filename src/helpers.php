<?php

use WorldSpot\IDPClient\IDPManager;

/**
 * Global helper functions for backward compatibility and convenience
 */

if (!function_exists('getIDPManager')) {
    function getIDPManager()
    {
        static $manager = null;
        
        if ($manager === null) {
            $manager = new IDPManager();
        }
        
        return $manager;
    }
}

if (!function_exists('getIDPClient')) {
    function getIDPClient() {
        return getIDPManager();
    }
}

if (!function_exists('getIDPConfig')) {
    function getIDPConfig() {
        return [
            'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?: 'https://idp.worldspot.org',
            'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID') ?: 'default-app-id'
        ];
    }
}

if (!function_exists('buildCallbackUrl')) {
    function buildCallbackUrl($redirect = null) {
        return getIDPManager()->buildCallbackUrl($redirect);
    }
}

if (!function_exists('buildAppUrl')) {
    function buildAppUrl($path = '') {
        return getIDPManager()->buildAppUrl($path);
    }
}

if (!function_exists('authenticateUser')) {
    function authenticateUser($email, $password, $redirect = '/') {
        return getIDPManager()->authenticateUser($email, $password, $redirect);
    }
}

if (!function_exists('registerUser')) {
    function registerUser($email, $password, $name = '', $redirect = '/') {
        return getIDPManager()->registerUser($email, $password, $name, $redirect);
    }
}

if (!function_exists('requestPasswordReset')) {
    function requestPasswordReset($email) {
        return getIDPManager()->requestPasswordReset($email);
    }
}

if (!function_exists('verifyEmailToken')) {
    function verifyEmailToken($token) {
        return getIDPManager()->verifyEmailToken($token);
    }
}

if (!function_exists('verifyEmail')) {
    function verifyEmail($token) {
        return getIDPManager()->verifyEmail($token);
    }
}

if (!function_exists('logoutUser')) {
    function logoutUser($redirect = '/') {
        return getIDPManager()->logoutUser($redirect);
    }
}

if (!function_exists('getAppConfig')) {
    function getAppConfig() {
        return getIDPManager()->getAppConfig();
    }
}