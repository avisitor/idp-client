<?php

declare(strict_types=1);

/**
 * WorldSpot\IDPClient\IDPClient — static facade (PSR-4).
 *
 * PSR-4 replacement for the legacy global helper functions in helpers.php
 * (getIDPManager, getIDPClient, getIDPConfig, buildCallbackUrl, buildAppUrl,
 * authenticateUser, registerUser, requestPasswordReset, verifyEmailToken,
 * verifyEmail, logoutUser, getAppConfig) (R7: SHARED_CODE_PLATFORM_ANALYSIS).
 * Behavior is identical; only the invocation surface changed so multiple
 * applications can load in one process without global-symbol collisions.
 */

namespace WorldSpot\IDPClient;

class IDPClient
{
    public static function getManager()
    {
        static $manager = null;

        if ($manager === null) {
            $manager = new IDPManager();
        }

        return $manager;
    }

    public static function getClient()
    {
        return self::getManager();
    }

    public static function getConfig()
    {
        return [
            'idp_url' => $_ENV['IDP_URL'] ?? getenv('IDP_URL') ?: 'https://idp.worldspot.org',
            'app_id' => $_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID') ?: 'default-app-id'
        ];
    }

    public static function buildCallbackUrl($redirect = null)
    {
        return self::getManager()->buildCallbackUrl($redirect);
    }

    public static function buildAppUrl($path = '')
    {
        return self::getManager()->buildAppUrl($path);
    }

    public static function authenticateUser($email, $password, $redirect = '/')
    {
        return self::getManager()->authenticateUser($email, $password, $redirect);
    }

    public static function registerUser($email, $password, $name = '', $redirect = '/')
    {
        return self::getManager()->registerUser($email, $password, $name, $redirect);
    }

    public static function requestPasswordReset($email)
    {
        return self::getManager()->requestPasswordReset($email);
    }

    public static function verifyEmailToken($token)
    {
        return self::getManager()->verifyEmailToken($token);
    }

    public static function verifyEmail($token)
    {
        return self::getManager()->verifyEmail($token);
    }

    public static function logoutUser($redirect = '/')
    {
        return self::getManager()->logoutUser($redirect);
    }

    public static function getAppConfig()
    {
        return self::getManager()->getAppConfig();
    }
}
