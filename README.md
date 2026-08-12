# WorldSpot IDP Client Package

A PHP library for integrating with the WorldSpot Identity Provider (IDP) system.

## Features

- Secure authentication delegation
- User registration and profile management
- Password reset functionality
- Email verification
- Session management
- Configurable via environment variables

## Installation

```bash
composer require worldspot/idp-client
```

## Configuration

Copy the `.env.example` to your project and configure the required environment variables:

```bash
cp vendor/worldspot/idp-client/.env.example .env
```

Required environment variables:
- `IDP_URL`: URL of the IDP server
- `IDP_APP_ID`: Your application ID registered with the IDP
- `APP_NAME`: Your application name
- `APP_DOMAIN`: Your application domain
- `APP_BASE_PATH`: Base path for your application
- `APP_AUTH_PATH`: Authentication path for callbacks
- `APP_EMAIL`: Default email for your application
- `APP_PROTOCOL`: Protocol (http/https)
- Database configuration variables

## Usage

### Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use WorldSpot\IDPClient\IDPManager;

// Initialize IDP Manager
$idp = new IDPManager();

// Redirect to IDP for authentication
$returnUrl = $idp->buildCallbackUrl('/dashboard');
$loginUrl = $idp->getLoginUrl($returnUrl);
header("Location: $loginUrl");
```

### User Registration

```php
$result = $idp->registerUser($email, $password, $name);
if ($result['success']) {
    echo "Registration successful! Check your email for verification.";
}
```

### Password Reset

```php
$result = $idp->requestPasswordReset($email);
if ($result['success']) {
    echo "Password reset instructions sent to your email.";
}
```

### Token Management Helpers

The package now exposes reusable factories under `WorldSpot\IDPClient\Auth` that any IDP-enabled application can use to enhance and refresh JWT tokens with application-specific roles.

#### `TokenStore` — durable token persistence (v1.4.0)

`WorldSpot\IDPClient\Auth\TokenStore` is the single source of truth for persisting and
refreshing the IDP JWT. The IDP issues a JWT whose `exp` is governed by
`IDP_TOKEN_EXPIRY_SECONDS` (default 24h), but storing it only in `$_SESSION` previously
let PHP's default `session.gc_maxlifetime` (1440s) garbage-collect it and log users out
after ~24 minutes.

`TokenStore` persists the token in **both** `$_SESSION['jwt_token']` and a durable,
HttpOnly `idp_jwt` cookie, so the session GC no longer caps the login lifetime. All of the
library's token read/write paths (`IDPManager`, `TokenManager`, `AuthHelpers`,
`token-refresh.php`) now route through it.

```php
use WorldSpot\IDPClient\Auth\TokenStore;

TokenStore::store($jwt);              // session + durable cookie
$token = TokenStore::get();           // session first, then cookie
if (TokenStore::needsRefresh($token)) {
    $token = TokenStore::refresh($token, $_ENV['IDP_APP_ID'], $_ENV['IDP_URL']) ?? $token;
    TokenStore::store($token);
}
TokenStore::clear();                  // on logout
```

`refresh()` is server-to-server (the IDP refresh endpoint only allows specific CORS
origins) and re-signs even an expired token, so the effective lifetime is governed by the
IDP token, not the PHP session.


```php
use WorldSpot\IDPClient\Auth\AuthHelpers;

$getEnhancedJwtFromIDP = AuthHelpers::createTokenEnhancer(
    fn($adminLevel) => determineAppRoles($adminLevel),
    $_ENV['IDP_APP_ID'],
    $_ENV['IDP_URL']
);

$getValidAppJwtToken = AuthHelpers::createTokenGetter(
    fn($email) => loadUserProfile($email),
    fn($admin) => determineAppRoles($admin),
    $_ENV['IDP_APP_ID'],
    $_ENV['IDP_URL']
);
```

Use `AuthHelpers::createUserProfileLoader` and `createAdminStatusChecker` to wire these helpers into your `getAuthConfig()` overrides, and rely on `WorldSpot\IDPClient\Auth\TokenManager` directly whenever you need to validate or refresh tokens in other services.

## Integration Requirements

Your application needs:
1. A callback handler at `APP_AUTH_PATH/idp-callback.php`
2. Database table for user profiles (see migration example)
3. Environment configuration

## License

This is proprietary software. Contact WorldSpot Organization for licensing information.