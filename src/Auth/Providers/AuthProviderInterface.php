<?php

namespace WorldSpot\IDPClient\Auth\Providers;

/**
 * Authentication Provider Interface
 * 
 * This interface defines the contract for all authentication providers,
 * enabling seamless switching between local and external authentication systems.
 * 
 * @package WorldSpot\IDPClient
 * @subpackage Auth\Providers
 */
interface AuthProviderInterface 
{
    /**
     * Authenticate a user with credentials
     * 
     * @param array $credentials Array containing username, password, and optional redirect info
     * @return array Result array with keys: success (bool), user (array), redirect (string), error (string)
     */
    public function login(array $credentials): array;
    
    /**
     * Log out the current user
     * 
     * @param string|null $redirectUrl Optional URL to redirect to after logout
     * @return array Result array with keys: success (bool), redirect (string), error (string)
     */
    public function logout(?string $redirectUrl = null): array;
    
    /**
     * Register a new user account
     * 
     * @param array $userData User registration data (email, password, name, etc.)
     * @return array Result array with keys: success (bool), user (array), error (string), verification_required (bool)
     */
    public function register(array $userData): array;
    
    /**
     * Initiate password reset process
     * 
     * @param string $email User's email address
     * @return array Result array with keys: success (bool), message (string), error (string)
     */
    public function resetPassword(string $email): array;
    
    /**
     * Change user's password
     * 
     * @param string $currentPassword Current password for verification
     * @param string $newPassword New password
     * @return array Result array with keys: success (bool), message (string), error (string)
     */
    public function changePassword(string $currentPassword, string $newPassword): array;
    
    /**
     * Check if user is currently authenticated
     * 
     * @return bool True if user is authenticated, false otherwise
     */
    public function isAuthenticated(): bool;
    
    /**
     * Get current authenticated user information
     * 
     * @return array|null User information array or null if not authenticated
     */
    public function getCurrentUser(): ?array;
    
    /**
     * Get URL for login page/redirect
     * 
     * @param string|null $redirectAfter URL to redirect to after successful login
     * @param array $params Additional parameters to include in login URL
     * @return string Login URL
     */
    public function getLoginUrl(?string $redirectAfter = null, array $params = []): string;
    
    /**
     * Get URL for registration page/redirect
     * 
     * @param string|null $redirectAfter URL to redirect to after successful registration
     * @param array $params Additional parameters to include in registration URL
     * @return string Registration URL
     */
    public function getRegistrationUrl(?string $redirectAfter = null, array $params = []): string;
    
    /**
     * Get URL for password reset page/redirect
     * 
     * @param string|null $email Pre-populate email if provided
     * @return string Password reset URL
     */
    public function getResetPasswordUrl(?string $email = null): string;
    
    /**
     * Get URL for change password page/redirect
     * 
     * @return string Change password URL
     */
    public function getChangePasswordUrl(): string;
    
    /**
     * Initialize session and load user data
     * Called from common.php getSession() function or similar
     * 
     * @return void
     */
    public function initializeSession(): void;
    
    /**
     * Verify email/user account
     * 
     * @param string $token Verification token
     * @return array Result array with keys: success (bool), message (string), error (string)
     */
    public function verifyAccount(string $token): array;
    
    /**
     * Check if external authentication is enabled
     * 
     * @return bool True if using external auth, false if local
     */
    public function isExternalAuth(): bool;
}
