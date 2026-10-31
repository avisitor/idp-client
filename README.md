# WorldSpot IDP Client Package

A PHP library for integrating with the WorldSpot Identity Provider (IDP) system.

## Features

- Secure authentication delegation
- User registration and profile management
- Password reset functionality
- Email verification
- Session management
- Automatic session recovery while the IDP token is valid (v2.2.0)
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

#### Session recovery — no re-login after session GC (v2.2.0)

Before 2.2.0 the durable `idp_jwt` cookie was written by hand-built integrations but the
library's own login callback never stored it, and nothing read it back. PHP's session GC
(commonly 24–55 minutes) therefore still logged users out while their IDP token was valid
for up to `IDP_TOKEN_EXPIRY_SECONDS` (default 24h).

From 2.2.0 this is automatic; applications do **not** need to opt in:

- `CallbackHandler` persists the JWT through `TokenStore::store()` immediately after
  `JwtVerifier::verify()` succeeds — never before, and never from unverified claims.
- `ExternalAuthProvider::isAuthenticated()` calls `SessionRecovery::attempt()` when
  the native session has no authenticated user. Recovery reads the durable token,
  verifies its signature and `exp`, refreshes it when it is expired or within 10 minutes
  of expiry, re-verifies the refreshed token, checks that its `appId` matches this
  application and that it carries a `sub` claim, and only then repopulates `$_SESSION`.
- Recovery is skipped entirely while the session is already authenticated, so the normal
  request path performs no token read and no network call.
- Every logout path (`ExternalAuthProvider::logout()`, `LogoutHandler`,
  `IDPManager::logoutUser()` / `IDPClient::logoutUser()`, and
  `LocalAuthProvider::logout()`) clears the durable token, so a logged-out browser is
  never silently signed back in.

Recovery is bounded by the IDP token lifetime: once `exp` passes and the IDP refuses the
refresh, the user is sent to the IDP login page as before. No environment flag, database
migration or application code change is required.

```php
use WorldSpot\IDPClient\Auth\SessionRecovery;

// Usually called for you by ExternalAuthProvider::isAuthenticated().
$recovery = new SessionRecovery();
$recovered = $recovery->attempt($_ENV['IDP_APP_ID'], $_ENV['IDP_URL']);
```

`attempt()` returns `true` only after a fully verified token has hydrated `$_SESSION`;
any failure (missing token, bad signature, expired with failed refresh, foreign-app token,
missing subject) returns `false` and leaves the session untouched.


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