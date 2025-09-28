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

## Integration Requirements

Your application needs:
1. A callback handler at `APP_AUTH_PATH/idp-callback.php`
2. Database table for user profiles (see migration example)
3. Environment configuration

## License

This is proprietary software. Contact WorldSpot Organization for licensing information.