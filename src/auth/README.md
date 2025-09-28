# Testing IDP-Client Package Auth Module

## Package Files Location
The generic auth files are now available in `/var/www/html/idp-client/src/auth/` and can be used as a standalone package.

## Generic Auth Files (Package Ready):
- `package-bootstrap.php` - Package-specific bootstrap with minimal dependencies
- `auth-bootstrap.php` - Core portable authentication framework  
- `login.php` - Generic login redirect to IDP
- `register.php` - Generic registration redirect to IDP
- `reset.php` - Generic password reset redirect to IDP
- `change.php` - Generic password change redirect to IDP
- `logout.php` - Generic logout with hooks
- `idp-callback.php` - Generic IDP callback handler
- `verify.php` - Generic email verification with branding hooks
- `index.php` - Auth landing page (copy of login.php)
- `style.css` - Default styling
- `password-fields.css` - Password field styling
- `password-fields.js` - Password field JavaScript

## Testing the Package

### 1. Basic Test (using environment variables):
```bash
cd /var/www/html/idp-client/src/auth
IDP_APP_ID="your-app-id" APP_NAME="Your App" php -f login.php
```

### 2. With .env file:
```bash
# Create .env file with your config
cd /var/www/html/idp-client/src/auth  
DOTENV_PATH="/path/to/.env" php -f login.php
```

### 3. Required Environment Variables:
- `APP_NAME` - Your application name
- `APP_URL` - Your application base URL  
- `IDP_URL` - IDP server URL (default: https://idp.worldspot.org)
- `IDP_APP_ID` - Your IDP application ID
- `SUPPORT_EMAIL` - Support contact email
- `AUTH_ENABLE_LOGGING` - Enable auth logging (true/false)
- `AUTH_LOG_FILE` - Auth log file path

## Integration with New App

### Method 1: Copy Files
1. Copy all files from `/var/www/html/idp-client/src/auth/` to your app's auth directory
2. Create your own `auth-app-config.php` with app-specific hooks
3. Set environment variables or create `.env` file

### Method 2: Package Installation (Future)
```bash
composer require worldspot/idp-client
# Copy auth files from vendor/worldspot/idp-client/src/auth/
# Create app-specific configuration
```

## Key Benefits

1. **Zero App Dependencies** - Works standalone with just environment variables
2. **Portable** - Same files work across different applications  
3. **Configurable** - All branding and behavior via environment/hooks
4. **Complete** - Full auth flow including login, register, reset, verify, logout
5. **IDP Integrated** - Built-in WorldSpot IDP integration

The package is now **fully functional and testable**! 🚀