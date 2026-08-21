# IDP JavaScript Authentication

A lightweight JavaScript library for forcing authentication with the IDP in HTML/JavaScript applications without requiring a backend.

## Quick Start

### 1. Install the Package

The package is distributed via GitHub (not Packagist), so first add the VCS
repository to your application's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/avisitor/idp-client.git"
    }
]
```

Then require it (v2.0.3 or newer):

```bash
composer require avisitor/idp-client:^2.0.3
```

### 2. Copy the JS Client Out of vendor

Wire the copier into your application's `composer.json` so `js/` stays in sync
on every install/update (keeps the vendor tree out of your web-served docroot):

```json
"scripts": {
    "copy-js-client": "@php vendor/avisitor/idp-client/copy-js-client.php",
    "post-install-cmd": ["@copy-js-client"],
    "post-update-cmd": ["@copy-js-client"]
}
```

Run it (also runs automatically on install/update):

```bash
composer run copy-js-client
```

This copies two files into `<app-root>/js/`, creating the directory if needed:
- `idp-auth.js` - the authentication library
- `app-config.php` - server-side config endpoint (reads your `.env`)

Add `js/idp-auth.js` to your application's `.gitignore` - both are generated
artifacts refreshed by composer.

### 3. Configure via .env

The IDP URL and application ID are read solely from your application's `.env`
(no hardcoded values, no query-string overrides):

```env
IDP_URL=https://idp.worldspot.org
IDP_APP_ID=your-app-id
```

`js/app-config.php` serves these to the browser as `window.IDP_CONFIG`.
If either key is missing, it logs to the PHP error log and `IDPAuth.init()`
refuses to initialize.

### 4. Add to Your HTML Page

```html
<!-- Config endpoint (reads .env server-side) -->
<script src="js/app-config.php"></script>

<!-- Include the library -->
<script src="js/idp-auth.js"></script>

<!-- Initialize authentication -->
<script>
  IDPAuth.init({
    idpUrl: window.IDP_CONFIG.idpUrl,
    appId: window.IDP_CONFIG.appId,
    tokenStorageKey: 'jwt_token',
    callbackUrl: window.location.href,
    bufferMinutes: 5
  });
</script>
```

### 5. What Happens Automatically

When the page loads with the script initialized:
- Checks if a valid JWT token exists in browser storage
- If **no token** or **token is expired**: redirects to IDP login
- If **valid token exists**: allows page to load and be used

## Configuration Options

When using `js/app-config.php`, `idpUrl` and `appId` come from
`window.IDP_CONFIG` (populated from your `.env`) - do not hardcode them:

```javascript
IDPAuth.init({
  // Required - normally taken from window.IDP_CONFIG
  idpUrl: window.IDP_CONFIG.idpUrl,      // IDP server URL (from .env IDP_URL)
  appId: window.IDP_CONFIG.appId,        // Application ID (from .env IDP_APP_ID)
  
  // Optional
  tokenStorageKey: 'jwt_token',          // Where to store token (default: 'jwt_token')
  callbackUrl: window.location.href,     // Where to redirect after login (default: current page)
  bufferMinutes: 5                       // Refresh token N minutes before expiry (default: 5)
});
```

## Usage Examples

### Check If User Is Authenticated

```javascript
if (IDPAuth.isAuthenticated()) {
  console.log('User has valid token');
} else {
  console.log('User needs to login');
}
```

### Get the JWT Token

```javascript
const token = IDPAuth.getToken();
if (token) {
  // Use token in API calls
  fetch('/api/data', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
}
```

### Logout User

```javascript
IDPAuth.logout();
// or logout and redirect
IDPAuth.logout('https://example.com/goodbye');
```

### Get Token Expiration Time

```javascript
const secondsUntilExpiry = IDPAuth.getTokenExpiration();
console.log(`Token expires in ${secondsUntilExpiry} seconds`);
```

### Handle IDP Callback (if using custom callback page)

If you configure a custom callback page that receives the token from the IDP:

```javascript
IDPAuth.init({ /* config */ });

// Process the token from IDP callback
if (IDPAuth.processCallback()) {
  // Token saved successfully, can proceed
  window.location.href = '/dashboard';
} else {
  // Token is invalid or missing
  IDPAuth.redirectToLogin();
}
```

## Complete HTML Page Example

```html
<!DOCTYPE html>
<html>
<head>
  <title>My Protected App</title>
  <script src="js/app-config.php"></script>
  <script src="js/idp-auth.js"></script>
</head>
<body>
  <h1>Welcome to My App</h1>
  <p id="message">Loading...</p>

  <script>
    // Initialize authentication - redirects to IDP if not authenticated
    IDPAuth.init({
      idpUrl: window.IDP_CONFIG.idpUrl,
      appId: window.IDP_CONFIG.appId,
      tokenStorageKey: 'jwt_token',
      callbackUrl: window.location.href,
      bufferMinutes: 5
    });

    // Once loaded, we know user is authenticated
    document.getElementById('message').textContent = 
      'You are authenticated! Token expires in: ' + 
      IDPAuth.getTokenExpiration() + ' seconds';

    // Use token for API calls
    const token = IDPAuth.getToken();
    fetch('/api/user/profile', {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
    .then(r => r.json())
    .then(data => {
      console.log('User profile:', data);
    });

    // Add logout button
    document.body.innerHTML += `
      <button onclick="IDPAuth.logout('/')">Logout</button>
    `;
  </script>
</body>
</html>
```

## Token Storage

The library automatically stores tokens in:
- **localStorage** - persists across browser sessions
- **sessionStorage** - backup storage

This allows users to remain authenticated across browser refreshes and tab switches.

## Security Notes

- Tokens are stored in the browser (vulnerable to XSS attacks)
- For sensitive applications, consider additional security measures:
  - Use HTTP-only cookies (requires backend support)
  - Implement Content Security Policy (CSP)
  - Use SubResource Integrity (SRI) for the script tag
  - Regularly validate token expiration

Example with SRI:
```html
<script 
  src="js/idp-auth.js"
  integrity="sha384-[hash-here]"
  crossorigin="anonymous">
</script>
```

## API Reference

### `IDPAuth.init(options)`
Initialize authentication and check for valid token. Redirects to login if needed.

**Parameters:**
- `options` (Object) - Configuration object with `idpUrl`, `appId`, and optional settings

**Returns:** `true` if successful, `false` if config is invalid

---

### `IDPAuth.getToken()`
Get the stored JWT token if it's valid and not expired.

**Returns:** JWT token string or `null` if no valid token

---

### `IDPAuth.isAuthenticated()`
Check if user has a valid, non-expired token.

**Returns:** `true` if authenticated, `false` otherwise

---

### `IDPAuth.logout(redirectUrl)`
Clear all stored tokens and optionally redirect.

**Parameters:**
- `redirectUrl` (String, optional) - URL to redirect to after logout

---

### `IDPAuth.processCallback()`
Process IDP callback and store token from URL parameters.

**Returns:** `true` if token was valid and stored, `false` otherwise

---

### `IDPAuth.redirectToLogin()`
Clear tokens and redirect user to IDP login page.

---

### `IDPAuth.getTokenExpiration()`
Get the number of seconds until the token expires.

**Returns:** Number of seconds, or `null` if no valid token

---

### `IDPAuth.getStoredToken()`
Get the raw stored token without expiration check.

**Returns:** Token string or `null`

---

### `IDPAuth.storeToken(token)`
Manually store a token.

**Parameters:**
- `token` (String) - JWT token to store

---

### `IDPAuth.clearToken()`
Clear all stored tokens.

## Troubleshooting

### Page loads without redirecting, nothing in console about IDPAuth
- `window.IDP_CONFIG` may be empty: check that `IDP_URL` and `IDP_APP_ID`
  are set in your application's `.env` (missing keys are logged to the PHP
  error log by `js/app-config.php`)
- Verify `js/app-config.php` loads directly in the browser and emits
  `window.IDP_CONFIG = {...}`
- Confirm `js/app-config.php` is included BEFORE `js/idp-auth.js` init call

### js/ files missing or stale after composer update
- Ensure the `copy-js-client` scripts are present in your `composer.json`
  (see Quick Start step 2)
- Run manually: `composer run copy-js-client`

### Token not being stored
- Check browser storage is enabled
- Verify token is valid JWT format
- Check browser console for errors

### Being redirected to login unexpectedly
- Token may be expired (check `getTokenExpiration()`)
- `bufferMinutes` setting may be too high
- Token format may be invalid

### CORS errors
- Ensure IDP URL allows cross-origin requests
- Configure IDP CORS settings appropriately

## File Locations

- **idp-auth.js** - Main library (copied to your app's `js/` by copy-js-client.php)
- **app-config.php** - Config endpoint emitting window.IDP_CONFIG from your .env (same)
- **copy-js-client.php** - Installer that copies the JS client into `<app>/js/`
- **examples/idp-auth-example.html** - Full working example
- **JS-AUTHENTICATION.md** - This documentation
