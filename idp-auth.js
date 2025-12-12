/**
 * IDP Client Authentication for HTML/JavaScript
 * 
 * Small embeddable script for forcing authentication with the IDP
 * when no valid JWT token is present.
 * 
 * Usage:
 * <script src="path/to/idp-auth.js"></script>
 * <script>
 *   IDPAuth.init({
 *     idpUrl: 'https://idp.example.com',
 *     appId: 'your-app-id',
 *     tokenStorageKey: 'jwt_token', // optional, defaults to 'jwt_token'
 *     callbackUrl: window.location.href, // optional, where to redirect after login
 *     bufferMinutes: 5 // optional, refresh token before expiring
 *   });
 * </script>
 */

const IDPAuth = (function() {
    const DEFAULT_TOKEN_KEY = 'jwt_token';
    const DEFAULT_BUFFER_MINUTES = 5;

    let config = {
        idpUrl: null,
        appId: null,
        tokenStorageKey: DEFAULT_TOKEN_KEY,
        callbackUrl: window.location.href,
        bufferMinutes: DEFAULT_BUFFER_MINUTES
    };

    /**
     * Initialize IDP authentication
     */
    function init(options = {}) {
        console.log('IDPAuth: init() called with options:', options);
        Object.assign(config, options);
        console.log('IDPAuth: config after assignment:', config);

        if (!config.idpUrl || !config.appId) {
            const missingParams = [];
            if (!config.idpUrl) missingParams.push('idpUrl');
            if (!config.appId) missingParams.push('appId');
            console.error('IDPAuth: Missing required parameters: ' + missingParams.join(', '));
            console.error('IDPAuth: Current config:', config);
            return false;
        }

        console.log('IDPAuth: Initialized with appId=' + config.appId + ', idpUrl=' + config.idpUrl);

        // Check token validity on page load
        checkAndEnforceAuth();

        return true;
    }

    /**
     * Check if JWT token is expired
     */
    function isTokenExpired(token, bufferMinutes = DEFAULT_BUFFER_MINUTES) {
        if (!token) {
            return true;
        }

        try {
            const parts = token.split('.');
            if (parts.length !== 3) {
                console.warn('IDPAuth: Invalid JWT format');
                return true;
            }

            // Decode payload (add padding if needed)
            let payload = parts[1];
            payload += '=='.slice(0, (4 - payload.length % 4) % 4);
            
            const decoded = JSON.parse(atob(payload));
            
            if (!decoded.exp) {
                console.warn('IDPAuth: No expiration found in token');
                return true;
            }

            const now = Math.floor(Date.now() / 1000);
            const bufferSeconds = bufferMinutes * 60;
            const isExpired = decoded.exp < (now + bufferSeconds);

            if (isExpired) {
                const timeUntilExp = decoded.exp - now;
                console.log(`IDPAuth: Token expires in ${timeUntilExp} seconds (buffer: ${bufferSeconds}s)`);
            }

            return isExpired;

        } catch (error) {
            console.error('IDPAuth: Error checking token expiration:', error);
            return true;
        }
    }

    /**
     * Get token from storage
     */
    function getStoredToken() {
        // Try localStorage first
        let token = localStorage.getItem(config.tokenStorageKey);
        if (token) return token;

        // Try sessionStorage
        token = sessionStorage.getItem(config.tokenStorageKey);
        if (token) return token;

        return null;
    }

    /**
     * Store token in localStorage and sessionStorage
     */
    function storeToken(token) {
        if (token) {
            localStorage.setItem(config.tokenStorageKey, token);
            sessionStorage.setItem(config.tokenStorageKey, token);
        }
    }

    /**
     * Clear stored token
     */
    function clearToken() {
        localStorage.removeItem(config.tokenStorageKey);
        sessionStorage.removeItem(config.tokenStorageKey);
    }

    /**
     * Build IDP login URL
     */
    function buildLoginUrl(returnUrl) {
        const params = new URLSearchParams({
            app: config.appId,
            appId: config.appId,
            return: returnUrl
        });
        const url = `${config.idpUrl}/?${params.toString()}`;
        console.log('IDPAuth: Building login URL with appId=' + config.appId);
        console.log('IDPAuth: Full login URL: ' + url);
        return url;
    }

    /**
     * Check authentication and redirect to IDP if needed
     */
    function checkAndEnforceAuth() {
        // First, try to extract token from URL (IDP callback)
        const tokenFromUrl = extractTokenFromUrl();
        if (tokenFromUrl) {
            console.log('IDPAuth: Token found in URL, storing it');
            storeToken(tokenFromUrl);
            // Remove token from URL to clean it up
            if (window.history.replaceState) {
                const cleanUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, cleanUrl);
            }
            return true;
        }

        // Check stored token
        const token = getStoredToken();

        if (isTokenExpired(token, config.bufferMinutes)) {
            redirectToLogin();
            return false;
        }

        return true;
    }

    /**
     * Redirect to IDP login
     */
    function redirectToLogin() {
        clearToken();
        const loginUrl = buildLoginUrl(config.callbackUrl);
        console.log('IDPAuth: Redirecting to IDP login:', loginUrl);
        window.location.href = loginUrl;
    }

    /**
     * Extract token from URL parameters (for callback page)
     */
    function extractTokenFromUrl() {
        const params = new URLSearchParams(window.location.search);
        // Try different parameter names the IDP might use
        return params.get('token') || params.get('jwt') || params.get('access_token');
    }

    /**
     * Process IDP callback - extract and store token
     */
    function processCallback() {
        const token = extractTokenFromUrl();

        if (!token) {
            console.warn('IDPAuth: No token found in callback URL');
            redirectToLogin();
            return false;
        }

        if (isTokenExpired(token)) {
            console.warn('IDPAuth: Token from IDP is already expired');
            redirectToLogin();
            return false;
        }

        storeToken(token);
        console.log('IDPAuth: Token stored successfully');
        return true;
    }

    /**
     * Get current stored token (if valid)
     */
    function getToken() {
        const token = getStoredToken();
        
        if (isTokenExpired(token, config.bufferMinutes)) {
            clearToken();
            return null;
        }

        return token;
    }

    /**
     * Logout - clear token and optionally redirect
     */
    function logout(redirectUrl = null) {
        clearToken();
        console.log('IDPAuth: Logged out');

        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    }

    /**
     * Get token expiration time in seconds from now
     */
    function getTokenExpiration() {
        const token = getStoredToken();
        
        if (!token) {
            return null;
        }

        try {
            const parts = token.split('.');
            if (parts.length !== 3) {
                return null;
            }

            let payload = parts[1];
            payload += '=='.slice(0, (4 - payload.length % 4) % 4);
            
            const decoded = JSON.parse(atob(payload));
            const now = Math.floor(Date.now() / 1000);
            
            return decoded.exp - now;

        } catch (error) {
            console.error('IDPAuth: Error getting token expiration:', error);
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    function isAuthenticated() {
        return getToken() !== null;
    }

    return {
        init,
        getToken,
        isAuthenticated,
        logout,
        processCallback,
        checkAndEnforceAuth,
        redirectToLogin,
        getTokenExpiration,
        getStoredToken,
        storeToken,
        clearToken
    };
})();
