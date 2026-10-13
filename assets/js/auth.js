// Account pages (log in, sign up, forgot/reset password, verification).
// Show/hide password, live password rules, digits-only phone, and friendly checks before submit.
// These checks only help the user; the AuthController still validates everything on the server.
(function () {
    const rules = {
        length: function (v) { return v.length >= 8; },
        letter: function (v) { return /[A-Za-z]/.test(v); },   // same rules as includes/passwords.php
        number: function (v) { return /\d/.test(v); },
    };

    const messages = {
        email: 'Enter a valid email, like juan@gmail.com.',
        phone: 'Use 11 digits starting with 09, like 09171234567.',
        password: 'Your password needs all three.',
        match: 'The passwords do not match.',
    };

    function passwordOk(value) {
        return rules.length(value) && rules.letter(value) && rules.number(value);
    }

    // SHOW / HIDE inside the password field
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        button.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    // Password rules turn into filled dots as they are met
    document.querySelectorAll('[data-password-rules]').forEach(function (list) {
        const input = document.getElementById(list.dataset.passwordRules);
        const update = function () {
            list.querySelectorAll('[data-rule]').forEach(function (item) {
                item.classList.toggle('is-met', rules[item.dataset.rule](input.value));
            });
        };

        input.addEventListener('input', update);
        update();
    });

    // Phone: digits only
    document.querySelectorAll('[data-check="phone"]').forEach(function (input) {
        input.addEventListener('input', function () {
            const digits = input.value.replace(/\D/g, '').slice(0, 11);

            if (digits !== input.value) {
                input.value = digits;
            }
        });
    });

    function problemFor(input) {
        const value = input.value.trim();

        if (input.required && value === '') {
            return input.dataset.requiredMessage || messages[input.dataset.check] || 'This field is required.';
        }

        switch (input.dataset.check) {
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : messages.email;
            case 'phone':
                return /^09\d{9}$/.test(value) ? '' : messages.phone;
            case 'password':
                return passwordOk(input.value) ? '' : messages.password;
            case 'match':
                return input.value === document.getElementById(input.dataset.match).value ? '' : messages.match;
            default:
                return '';
        }
    }

    function setError(input, message) {
        const field = input.closest('.auth-field');
        const errorId = input.id + 'Error';
        let error = document.getElementById(errorId);
        const describedBy = (input.getAttribute('aria-describedby') || '').split(' ').filter(function (id) {
            return id && id !== errorId;
        });

        if (message) {
            if (!error) {
                error = document.createElement('p');
                error.className = 'auth-error-text';
                error.id = errorId;
                field.appendChild(error);
            }

            error.textContent = message;
            input.setAttribute('aria-invalid', 'true');
            describedBy.push(errorId);
        } else {
            if (error) {
                error.remove();
            }

            input.removeAttribute('aria-invalid');
        }

        if (describedBy.length) {
            input.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            input.removeAttribute('aria-describedby');
        }
    }

    document.querySelectorAll('[data-auth-form]').forEach(function (form) {
        const inputs = Array.from(form.querySelectorAll('.auth-input'));

        // Clear a field's message as soon as it is fixed
        inputs.forEach(function (input) {
            input.addEventListener('input', function () {
                if (input.getAttribute('aria-invalid') === 'true' && !problemFor(input)) {
                    setError(input, '');
                }
            });
        });

        form.addEventListener('submit', function (event) {
            let firstBad = null;

            inputs.forEach(function (input) {
                const message = problemFor(input);
                setError(input, message);

                if (message && !firstBad) {
                    firstBad = input;
                }
            });

            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
                return;
            }

            // Prevent double submits while the server responds
            const button = form.querySelector('.auth-submit');

            if (button && button.dataset.loadingText) {
                button.dataset.idleText = button.textContent;
                button.disabled = true;
                button.textContent = button.dataset.loadingText;
            }
        });
    });

    // Coming back with the browser's Back button should not leave a button stuck on "Logging in…"
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.querySelectorAll('.auth-submit[disabled]').forEach(function (button) {
                button.disabled = false;
                button.textContent = button.dataset.idleText || button.textContent;
            });
        }
    });

    // Log in <-> Sign up on one page: slide the photo over the form not in use, without reloading.
    // Without JavaScript the links still work: "Create one" goes to register.php, which opens Sign up mode.
    const slide = document.querySelector('[data-auth-slide]');

    if (slide) {
        const halves = {
            login: slide.querySelector('.auth-half-login'),
            signup: slide.querySelector('.auth-half-signup'),
        };
        const titles = { login: 'Log In', signup: 'Create Account' };

        const setMode = function (mode) {
            const signup = mode === 'signup';

            slide.classList.toggle('is-signup', signup);

            Object.keys(halves).forEach(function (key) {
                const half = halves[key];

                if (key === mode) {
                    half.removeAttribute('inert');
                    half.removeAttribute('aria-hidden');
                } else {
                    half.setAttribute('inert', '');
                    half.setAttribute('aria-hidden', 'true');
                }
            });

            history.replaceState(null, '', signup ? 'login.php?panel=register' : 'login.php');
            document.title = titles[mode] + " | Norily's Vehicle Repair Shop";

            const first = halves[mode].querySelector('.auth-input');

            if (first) {
                first.focus({ preventScroll: true });
            }
        };

        slide.querySelectorAll('[data-auth-mode]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                setMode(link.dataset.authMode);
            });
        });
    }
})();
