<?php
/**
 * Generic Mail and SMS JavaScript Functions
 * 
 * This file generates JavaScript functions for mail and SMS integration
 * that can be used across different applications with idp-client authentication.
 * Include this as PHP to access session variables and environment configuration.
 * 
 * Part of avisitor/idp-client package
 */

// Start session to check authentication status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load generic app configuration
require_once __DIR__ . '/../app-config.php';

header('Content-Type: application/javascript');
?>
/**
 * Generic Mail and SMS Log Viewing Functions
 * 
 * Generic implementation for mail service integration
 */

function viewMailLog() {
    <?php if (isset($_SESSION['jwt_token']) && !empty($_SESSION['jwt_token'])): ?>
        <?php
        // Determine user roles for mail service
        $sessionRoles = $_SESSION['roles'] ?? [];
        $isAdmin = in_array('tenant_admin', $sessionRoles) ? '1' : '0';
        $isLeader = '1'; // Default leader access for retree-hawaii
        ?>
        // Use the automatic token refresh system
        const baseUrl = '<?php echo getEnvVar('MAIL_SERVICE_URL'); ?>/ui?view=email-logs&appId=<?php echo getEnvVar('MAIL_SERVICE_APP_ID'); ?>';
        const roleParams = '&isAdmin=<?php echo $isAdmin; ?>&isLeader=<?php echo $isLeader; ?>';
        const mailServiceUrl = baseUrl + roleParams;
        console.log('Base mail service URL:', mailServiceUrl);
        
        // Call server-side function to get a valid token (automatically refreshed if needed)
        fetch('get-valid-token.php')
            .then(response => {
                console.log('Token response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Token response data:', data);
                if (data.success && data.token) {
                    const urlWithToken = mailServiceUrl + '&token=' + encodeURIComponent(data.token);
                    console.log('Final URL:', urlWithToken);
                    console.log('Opening mail-service with auto-refreshed token');
                    window.open(urlWithToken, '_blank');
                } else {
                    console.error('Failed to get valid token:', data.error);
                    // Redirect to re-authenticate
                    window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
                }
            })
            .catch(error => {
                console.error('Error getting valid token:', error);
                // Redirect to re-authenticate
                window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
            });
    <?php else: ?>
        // No token in session, redirect to authenticate
        console.log('No authentication token, redirecting to authenticate');
        window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
    <?php endif; ?>
}

function viewSmsLog() {
    <?php if (isset($_SESSION['jwt_token']) && !empty($_SESSION['jwt_token'])): ?>
        <?php
        // Determine user roles for mail service
        $sessionRoles = $_SESSION['roles'] ?? [];
        $isAdmin = in_array('tenant_admin', $sessionRoles) ? '1' : '0';
        $isLeader = '1'; // Default leader access for retree-hawaii
        ?>
        // Use the automatic token refresh system
        const baseUrl = '<?php echo getEnvVar('MAIL_SERVICE_URL'); ?>/ui?view=sms-logs&appId=<?php echo getEnvVar('MAIL_SERVICE_APP_ID'); ?>';
        const roleParams = '&isAdmin=<?php echo $isAdmin; ?>&isLeader=<?php echo $isLeader; ?>';
        const mailServiceUrl = baseUrl + roleParams;
        console.log('Base SMS service URL:', mailServiceUrl);
        
        // Call server-side function to get a valid token (automatically refreshed if needed)
        fetch('get-valid-token.php')
            .then(response => {
                console.log('Token response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Token response data:', data);
                if (data.success && data.token) {
                    const urlWithToken = mailServiceUrl + '&token=' + encodeURIComponent(data.token);
                    console.log('Final URL:', urlWithToken);
                    console.log('Opening mail-service SMS logs with auto-refreshed token');
                    window.open(urlWithToken, '_blank');
                } else {
                    console.error('Failed to get valid token:', data.error);
                    // Redirect to re-authenticate
                    window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
                }
            })
            .catch(error => {
                console.error('Error getting valid token:', error);
                // Redirect to re-authenticate
                window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
            });
    <?php else: ?>
        // No token in session, redirect to authenticate
        console.log('No authentication token, redirecting to authenticate');
        window.location.href = '../auth/login.php?return=' + encodeURIComponent(window.location.href);
    <?php endif; ?>
}

// Function to compose mail to specific recipients (for integration with existing forms)
function composeMailTo(recipients, subject = '', options = {}) {
    if (!recipients || recipients.length === 0) {
        alert('No recipients specified');
        return;
    }
    
    // Create a form to post the data to mail.php (assumes mail.php exists in same directory)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'mail.php';
    form.style.display = 'none';
    
    // Add recipients
    if (typeof recipients === 'string') {
        // Simple email string
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'addressees';
        input.value = recipients;
        form.appendChild(input);
    } else if (Array.isArray(recipients)) {
        // Array of emails
        recipients.forEach((email, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `addressees[${index}]`;
            input.value = email;
            form.appendChild(input);
        });
    }
    
    // Add subject
    if (subject) {
        const subjectInput = document.createElement('input');
        subjectInput.type = 'hidden';
        subjectInput.name = 'subject';
        subjectInput.value = subject;
        form.appendChild(subjectInput);
    }
    
    // Add any additional options
    for (const [key, value] of Object.entries(options)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    // Submit form
    document.body.appendChild(form);
    form.submit();
}

// Function to compose SMS to specific phone numbers
function composeSmsTo(phoneNumbers, message = '', options = {}) {
    if (!phoneNumbers || phoneNumbers.length === 0) {
        alert('No phone numbers specified');
        return;
    }
    
    // Create a form to post the data to sendsms.php (assumes sendsms.php exists in same directory)
    const form = document.createElement('form');
    form.method = 'GET'; // Use GET to match standard sendsms.php pattern
    form.action = 'sendsms.php';
    form.style.display = 'none';
    
    // Add phone number (use first one for simple case)
    const phoneInput = document.createElement('input');
    phoneInput.type = 'hidden';
    phoneInput.name = 'to';
    phoneInput.value = Array.isArray(phoneNumbers) ? phoneNumbers[0] : phoneNumbers;
    form.appendChild(phoneInput);
    
    // Add message
    if (message) {
        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'body';
        messageInput.value = message;
        form.appendChild(messageInput);
    }
    
    // Submit form
    document.body.appendChild(form);
    form.submit();
}