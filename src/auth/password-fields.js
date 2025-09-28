/* Toggle visibility in password fields */
document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.addEventListener('click', togglePasswordVisibility);
    });
    function togglePasswordVisibility() {
        const passwordFields = document.querySelectorAll('.password-field, .confirm-password-field');
        const icons = document.querySelectorAll('.toggle-icon');
        let showingText = false;
        
        passwordFields.forEach(field => {
            if (field.type === 'password') {
                field.type = 'text';
                showingText = true;
            } else {
                field.type = 'password';
            }
        });

        icons.forEach(icon => {
            icon.textContent = showingText ? '🙈' : '👁️';
        });
    }
