# IDP JavaScript Authentication

A lightweight JavaScript library for forcing authentication with the IDP in HTML/JavaScript applications without requiring a backend.

## Quick Start

### 1. Add to Your HTML Page

```html
<!-- Include the script -->
<script src="https://worldspot.org/idp-client/idp-auth.js"></script>

<!-- Initialize authentication -->
<script>
  IDPAuth.init({
    idpUrl: 'https://idp.worldspot.org',
    appId: 'your-app-id',
    callbackUrl: window.location.href
  });
</script>
```

### 2. What Happens Automatically

When the page loads with the script initialized:
- Checks if a valid JWT token exists in browser storage
- If **no token** or **token is expired**: redirects to IDP login
- If **valid token exists**: allows page to load and be used

## Configuration Options

```javascript
IDPAuth.init({
  // Required
  idpUrl: 'https://idp.worldspot.org',        // IDP server URL
  appId: 'your-app-id',                  // Application ID from IDP
  
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
</head>
<body>
  <h1>Welcome to My App</h1>
  <p id="message">Loading...</p>

  <script src="/idp-auth.js"></script>
  <script>
    // Initialize authentication - redirects to IDP if not authenticated
    IDPAuth.init({
      idpUrl: 'https://idp.mycompany.com',
      appId: 'my-web-app'
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
  src="/idp-auth.js"
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

- **idp-auth.js** - Main library (include this in your HTML)
- **examples/idp-auth-example.html** - Full working example
- **JS-AUTHENTICATION.md** - This documentation
