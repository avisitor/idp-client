# IDP-Client Authentication Package

## Fully Automated Setup! 

This package provides a **true composer package experience** with automatic file setup.

## Quick Start

### 1. Install Package (Auto-creates auth files!)
```bash
composer require worldspot/idp-client
# Auth files automatically copied to /auth/ directory!
```

### 2. Manual Setup (if auto-setup failed)
```bash
# If auto-setup didn't work, run manually:
php vendor/worldspot/idp-client/install-auth.php

# Or copy manually:
cp vendor/worldspot/idp-client/templates/* your-app/auth/
```

### 3. Configure Your App
Edit `your-app/auth/auth-config.php`:
```php
function getAuthConfig() {
    return [
        'app_name' => 'Your App Name',
        'app_url' => 'https://your-app.com', 
        'app_id' => 'your-idp-app-id',
        'support_email' => 'support@your-app.com'
    ];
}
```

### 4. Done! 
Your auth system is ready. All URLs work:
- `/auth/login.php` - Login
- `/auth/register.php` - Registration  
- `/auth/reset.php` - Password reset
- `/auth/logout.php` - Logout
- `/auth/idp-callback.php` - IDP callback
- `/auth/verify.php` - Email verification

## Architecture

### Package Structure
```
vendor/worldspot/idp-client/
├── src/
│   ├── Auth/                    # Core handlers (NO COPYING NEEDED)
│   │   ├── AuthHandler.php      # Base handler class
│   │   ├── LoginHandler.php     # Login logic
│   │   ├── CallbackHandler.php  # IDP callback logic  
│   │   ├── LogoutHandler.php    # Logout logic
│   │   ├── RegisterHandler.php  # Registration logic
│   │   ├── ResetHandler.php     # Password reset logic
│   │   └── VerifyHandler.php    # Email verification logic
│   ├── IDPManager.php           # IDP integration
│   └── helpers.php              # Helper functions
└── templates/                   # Shell templates (COPY THESE)
    ├── auth-config.php          # App configuration (CUSTOMIZE)
    ├── login.php                # 4-line shell
    ├── register.php             # 4-line shell  
    ├── reset.php                # 4-line shell
    ├── logout.php               # 4-line shell
    ├── idp-callback.php         # 4-line shell
    ├── verify.php               # 4-line shell
    └── index.php                # 4-line shell
```

### Shell Template Example
Each shell file is just **4 lines**:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth-config.php'; 
$handler = new \WorldSpot\IDPClient\Auth\LoginHandler(getAuthConfig(), getAuthCallbacks());
$handler->handle();
?>
```

## Benefits

✅ **True Composer Package** - No large file copying  
✅ **Minimal Integration** - Only 8 tiny shell files + 1 config file  
✅ **All Logic in Package** - Updates via composer update  
✅ **Fully Configurable** - Customize via auth-config.php callbacks  
✅ **Complete Auth Flow** - Login, register, reset, verify, logout  
✅ **IDP Integrated** - WorldSpot IDP ready  
✅ **Framework Agnostic** - Works with any PHP app

## Configuration Options

### Environment Variables
```bash
APP_NAME="Your Application"
APP_URL="https://your-app.com"
IDP_URL="https://idp.worldspot.org"  
IDP_APP_ID="your-app-id"
SUPPORT_EMAIL="support@your-app.com"
AUTH_ENABLE_LOGGING=true
AUTH_LOG_FILE="/tmp/auth.log"
```

### App Callbacks
Customize behavior in `auth-config.php`:
```php
function getAuthCallbacks() {
    return [
        'onSuccessfulLogin' => function($userInfo, $redirectUrl) {
            // Your post-login logic
            $_SESSION['user_data'] = $userInfo;
            return $redirectUrl;
        },
        'onLogout' => function() {
            // Your pre-logout logic  
        },
        'renderBanner' => function() {
            // Your app banner/navigation
            include __DIR__ . '/../banner.html';
        }
    ];
}
```

## Testing

Test the package without copying files:
```bash
# Test package handlers directly
cd vendor/worldspot/idp-client/src/Auth
APP_NAME="Test App" IDP_APP_ID="test-123" php -r "
require '../../../../autoload.php';
\$h = new \WorldSpot\IDPClient\Auth\LoginHandler(['app_name' => 'Test', 'app_id' => 'test']);
echo 'Package handlers working!';
"
```

This is now a **proper composer package** that works immediately after installation! 🚀