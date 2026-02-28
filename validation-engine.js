/**
 * validation-engine.js
 * Real-time field-level validation with instant visual feedback.
 */

(function (global) {
    'use strict';

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------
    function getFeedbackEl(input) {
        var id = input.id + '-feedback';
        var el = document.getElementById(id);
        if (!el) {
            el = document.createElement('span');
            el.id = id;
            el.className = 'field-feedback';
            input.parentNode.appendChild(el);
        }
        return el;
    }

    function setValid(input, msg) {
        input.classList.remove('is-invalid-rt');
        input.classList.add('is-valid-rt');
        var fb = getFeedbackEl(input);
        fb.textContent = msg || '';
        fb.className = 'field-feedback valid';
    }

    function setInvalid(input, msg) {
        input.classList.remove('is-valid-rt');
        input.classList.add('is-invalid-rt');
        var fb = getFeedbackEl(input);
        fb.textContent = msg || '';
        fb.className = 'field-feedback invalid';
    }

    function clearState(input) {
        input.classList.remove('is-valid-rt', 'is-invalid-rt');
        var fb = getFeedbackEl(input);
        fb.textContent = '';
        fb.className = 'field-feedback';
    }

    // -------------------------------------------------------------------
    // Individual field validators
    // -------------------------------------------------------------------
    var rules = {
        required: function (value) {
            return value.trim().length > 0;
        },
        email: function (value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
        },
        minLength: function (value, len) {
            return value.length >= parseInt(len, 10);
        },
        tel: function (value) {
            return /^[0-9]{7,15}$/.test(value.replace(/[\s\-()]/g, ''));
        },
        date: function (value) {
            return value !== '' && !isNaN(Date.parse(value));
        },
        pastDate: function (value) {
            return value !== '' && new Date(value) < new Date();
        },
        minAge: function (value, minAge) {
            if (!value) { return false; }
            var dob = new Date(value);
            var today = new Date();
            var age = today.getFullYear() - dob.getFullYear();
            var m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }
            return age >= parseInt(minAge, 10);
        }
    };

    /**
     * Validate a single input element using data-* attributes.
     * Supported attributes:
     *   data-required="true"
     *   data-email="true"
     *   data-min-length="8"
     *   data-tel="true"
     *   data-date="true"
     *   data-past-date="true"
     *   data-min-age="18"
     *   data-label="Field Name"   (used in error messages)
     *
     * @param {HTMLInputElement} input
     * @returns {boolean}
     */
    function validateField(input) {
        var value = input.value;
        var label = input.dataset.label || input.id || 'This field';

        if (value === '' && !input.dataset.required) {
            clearState(input);
            return true;
        }

        if (input.dataset.required) {
            if (!rules.required(value)) {
                setInvalid(input, label + ' is required.');
                return false;
            }
        }

        if (input.dataset.email) {
            if (!rules.email(value)) {
                setInvalid(input, 'Please enter a valid email address.');
                return false;
            }
        }

        if (input.dataset.tel) {
            if (!rules.tel(value)) {
                setInvalid(input, 'Enter a valid phone number (digits only).');
                return false;
            }
        }

        if (input.dataset.date) {
            if (!rules.date(value)) {
                setInvalid(input, 'Please enter a valid date.');
                return false;
            }
        }

        if (input.dataset.pastDate) {
            if (!rules.pastDate(value)) {
                setInvalid(input, 'Date must be in the past.');
                return false;
            }
        }

        if (input.dataset.minAge) {
            if (!rules.minAge(value, input.dataset.minAge)) {
                setInvalid(input, 'Must be at least ' + input.dataset.minAge + ' years old.');
                return false;
            }
        }

        if (input.dataset.minLength) {
            if (!rules.minLength(value, input.dataset.minLength)) {
                setInvalid(input, label + ' must be at least ' + input.dataset.minLength + ' characters.');
                return false;
            }
        }

        setValid(input, '');
        return true;
    }

    /**
     * Calculate age in years from a date string, or return null.
     * @param {string} dateStr
     * @returns {number|null}
     */
    function calculateAge(dateStr) {
        if (!dateStr) { return null; }
        var dob = new Date(dateStr);
        if (isNaN(dob.getTime())) { return null; }
        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        var m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }
        return age;
    }

    /**
     * Attach real-time validation listeners to all inputs with data-validate attribute.
     * @param {HTMLElement} [scope] - Root element to search within (defaults to document).
     */
    function attachListeners(scope) {
        scope = scope || document;
        var inputs = scope.querySelectorAll('input[data-required], input[data-email], input[data-tel], input[data-date], input[data-past-date], input[data-min-age], input[data-min-length]');
        inputs.forEach(function (input) {
            input.addEventListener('blur', function () {
                if (input.value !== '') { validateField(input); }
            });
            input.addEventListener('input', function () {
                if (input.classList.contains('is-invalid-rt') || input.classList.contains('is-valid-rt')) {
                    validateField(input);
                }
            });
        });
    }

    // Expose password strength evaluator
    function evaluatePassword(password) {
        var strength = 0;
        var met = {
            length:    password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number:    /[0-9]/.test(password),
            symbol:    /[^A-Za-z0-9]/.test(password)
        };
        Object.keys(met).forEach(function (k) { if (met[k]) { strength++; } });
        return { strength: strength, met: met };
    }

    global.ValidationEngine = {
        validateField: validateField,
        setValid:      setValid,
        setInvalid:    setInvalid,
        clearState:    clearState,
        calculateAge:  calculateAge,
        attachListeners: attachListeners,
        evaluatePassword: evaluatePassword
    };

}(window));
