document.addEventListener('DOMContentLoaded', function () {
    const basePath = window.location.pathname.toLowerCase().includes('/crud-php/') ? '/Crud-php' : '';

    function setInlineError(message) {
        const errorElement = document.getElementById('error-message');
        if (!errorElement) return;
        errorElement.textContent = message || '';
    }

    function getErrorMessage(error, fallbackMessage) {
        if (error && error.response && error.response.data) {
            if (typeof error.response.data === 'string') {
                return error.response.data;
            }
            if (error.response.data.message) {
                return error.response.data.message;
            }
        }
        return fallbackMessage;
    }

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            setInlineError('');

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            axios.post(basePath + '/api/login.php', data)
                .then(function () {
                    window.location.href = 'public/index.php';
                })
                .catch(function (error) {
                    setInlineError(getErrorMessage(error, 'Invalid username or password.'));
                    console.error(error);
                });
        });
    }

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            setInlineError('');

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            axios.post(basePath + '/api/register.php', data)
                .then(function () {
                    window.location.href = 'login.php?registered=1';
                })
                .catch(function (error) {
                    setInlineError(getErrorMessage(error, 'Registration failed.'));
                    console.error(error);
                });
        });
    }
});
