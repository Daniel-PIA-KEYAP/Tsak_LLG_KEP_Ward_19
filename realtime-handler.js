/**
 * realtime-handler.js
 * Main coordinator for all real-time registration form features.
 * Depends on: validation-engine.js, notification-system.js,
 *             form-state-manager.js, websocket-client.js
 */

(function (global) {
    'use strict';

    // -------------------------------------------------------------------
    // Utility: debounce
    // -------------------------------------------------------------------
    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var ctx  = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    // -------------------------------------------------------------------
    // Form Progress Tracker
    // -------------------------------------------------------------------
    function initProgressTracker(form) {
        var fill  = document.getElementById('form-progress-fill');
        var label = document.getElementById('form-progress-percent');
        if (!fill) { return; }

        function update() {
            var required = form.querySelectorAll('[required]');
            if (required.length === 0) { return; }
            var filled = 0;
            required.forEach(function (el) {
                if (el.type === 'radio' || el.type === 'checkbox') {
                    var name = el.name;
                    if (form.querySelector('input[name="' + name + '"]:checked')) { filled++; }
                } else if (el.value.trim() !== '') {
                    filled++;
                }
            });

            // Deduplicate radio groups
            var radioGroups = {};
            required.forEach(function (el) {
                if (el.type === 'radio') { radioGroups[el.name] = true; }
            });
            var radioGroupCount   = Object.keys(radioGroups).length;
            var nonRadioRequired  = Array.from(required).filter(function (el) { return el.type !== 'radio'; }).length;
            var checkedRadios     = Object.keys(radioGroups).filter(function (name) {
                return form.querySelector('input[name="' + name + '"]:checked');
            }).length;

            var total  = nonRadioRequired + radioGroupCount;
            var filledCount = Array.from(required).filter(function (el) {
                return el.type !== 'radio' && el.value.trim() !== '';
            }).length + checkedRadios;

            var pct = total > 0 ? Math.round((filledCount / total) * 100) : 0;
            fill.style.width = pct + '%';
            if (label) { label.textContent = pct + '%'; }
        }

        form.addEventListener('input',  update);
        form.addEventListener('change', update);
        update();
    }

    // -------------------------------------------------------------------
    // Real-Time Email Check
    // -------------------------------------------------------------------
    function initEmailCheck(emailInput) {
        if (!emailInput) { return; }

        var feedbackEl = document.getElementById('email-realtime-feedback');
        if (!feedbackEl) {
            feedbackEl = document.createElement('span');
            feedbackEl.id = 'email-realtime-feedback';
            feedbackEl.className = 'field-feedback';
            emailInput.parentNode.appendChild(feedbackEl);
        }

        var debouncedCheck = debounce(function () {
            var email = emailInput.value.trim();
            // Skip if not a valid email format
            if (!ValidationEngine.isValidEmail(email)) { return; }

            feedbackEl.textContent = 'Checking availability…';
            feedbackEl.className = 'field-feedback email-checking';
            emailInput.dataset.emailChecked = 'pending';

            if (!global.fetch) { return; }
            fetch('api/check-email-realtime.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.exists) {
                    ValidationEngine.setInvalid(emailInput, 'This email is already registered.');
                    feedbackEl.className = 'field-feedback invalid';
                    feedbackEl.textContent = 'This email is already registered.';
                    emailInput.dataset.emailChecked = 'taken';
                    NotificationSystem.warning('That email address is already in use.', 3000);
                } else {
                    ValidationEngine.setValid(emailInput, 'Email is available.');
                    feedbackEl.className = 'field-feedback valid';
                    feedbackEl.textContent = 'Email is available.';
                    emailInput.dataset.emailChecked = 'ok';
                }
            })
            .catch(function () {
                feedbackEl.textContent = '';
                emailInput.dataset.emailChecked = '';
            });
        }, 500);

        emailInput.addEventListener('input', function () {
            emailInput.dataset.emailChecked = '';
            feedbackEl.textContent = '';
            feedbackEl.className = 'field-feedback';
            debouncedCheck();
        });
    }

    // -------------------------------------------------------------------
    // Real-Time Age Calculations
    // -------------------------------------------------------------------
    function setupAgeDisplay(dobInput, ageContainer, minAge, label) {
        if (!dobInput) { return; }

        var display = document.createElement('span');
        display.className = 'age-display';
        dobInput.parentNode.appendChild(display);

        function update() {
            var age = ValidationEngine.calculateAge(dobInput.value);
            if (age === null) {
                display.textContent = '';
                return;
            }
            if (new Date(dobInput.value) > new Date()) {
                display.textContent = 'Date cannot be in the future.';
                display.className = 'age-display invalid';
                return;
            }
            display.textContent = 'Age: ' + age + ' years';
            display.className = 'age-display';

            if (minAge && age < minAge) {
                display.textContent += ' (must be at least ' + minAge + ')';
                display.className = 'age-display invalid';
            }

            // Update linked age input if present
            if (ageContainer) { ageContainer.value = age; }
        }

        dobInput.addEventListener('input',  update);
        dobInput.addEventListener('change', update);
        update();
    }

    // -------------------------------------------------------------------
    // Password Strength Meter with Requirements
    // -------------------------------------------------------------------
    function initPasswordStrength(passwordInput) {
        if (!passwordInput) { return; }

        var strengthBar  = document.getElementById('password-strength-fill');
        var strengthText = document.getElementById('password-strength');
        var reqPanel     = document.getElementById('password-requirements');

        var reqItems = {
            length:    document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number:    document.getElementById('req-number'),
            symbol:    document.getElementById('req-symbol')
        };

        function update() {
            var val = passwordInput.value;
            if (!val) {
                if (reqPanel) { reqPanel.classList.remove('visible'); }
                if (strengthBar) { strengthBar.className = 'strength-0'; strengthBar.style.width = '0%'; }
                if (strengthText) { strengthText.textContent = ''; strengthText.className = 'password-strength'; }
                return;
            }

            if (reqPanel) { reqPanel.classList.add('visible'); }

            var result = ValidationEngine.evaluatePassword(val);
            var met    = result.met;

            // Update requirement items
            Object.keys(reqItems).forEach(function (key) {
                var item = reqItems[key];
                if (!item) { return; }
                var icon = item.querySelector('.req-icon');
                if (met[key]) {
                    item.classList.add('met');
                    if (icon) { icon.innerHTML = '<i class="fa fa-check"></i>'; }
                } else {
                    item.classList.remove('met');
                    if (icon) { icon.innerHTML = '<i class="fa fa-times"></i>'; }
                }
            });

            // Strength bar
            if (strengthBar) {
                strengthBar.className = 'strength-' + result.strength;
                strengthBar.style.width = (result.strength * 25) + '%';
            }

            // Strength text
            if (strengthText) {
                var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
                var classes = ['', 'strength-weak', 'strength-medium', 'strength-medium', 'strength-strong'];
                strengthText.textContent = labels[result.strength] || '';
                strengthText.className = 'password-strength ' + (classes[result.strength] || '');
            }
        }

        passwordInput.addEventListener('input',  update);
        passwordInput.addEventListener('focus',  function () { if (reqPanel && passwordInput.value) { reqPanel.classList.add('visible'); } });
        passwordInput.addEventListener('blur',   function () { if (reqPanel) { reqPanel.classList.remove('visible'); } });
        update();
    }

    // -------------------------------------------------------------------
    // Confirm Password Match
    // -------------------------------------------------------------------
    function initPasswordConfirm(passwordInput, confirmInput) {
        if (!passwordInput || !confirmInput) { return; }

        function check() {
            if (confirmInput.value === '') { ValidationEngine.clearState(confirmInput); return; }
            if (passwordInput.value === confirmInput.value) {
                ValidationEngine.setValid(confirmInput, 'Passwords match.');
            } else {
                ValidationEngine.setInvalid(confirmInput, 'Passwords do not match.');
            }
        }

        passwordInput.addEventListener('input',  check);
        confirmInput.addEventListener('input',  check);
        confirmInput.addEventListener('blur',   check);
    }

    // -------------------------------------------------------------------
    // Form Analytics (field focus/blur tracking & completion %)
    // -------------------------------------------------------------------
    function initAnalytics(form) {
        var _fieldTimes = {};

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('focus', function () {
                _fieldTimes[el.name || el.id] = Date.now();
            });
            el.addEventListener('blur', function () {
                var start = _fieldTimes[el.name || el.id];
                if (start) {
                    // Time spent on field available for future analytics extensions
                    delete _fieldTimes[el.name || el.id];
                }
            });
        });
    }

    // -------------------------------------------------------------------
    // Initialise everything
    // -------------------------------------------------------------------
    function init() {
        var form = document.getElementById('registration-form');
        if (!form) { return; }

        // Progress tracker
        initProgressTracker(form);

        // Form state persistence
        if (typeof global.FormStateManager !== 'undefined') {
            FormStateManager.init(form, document.getElementById('save-status'));
        }

        // Real-time statistics via SSE/polling
        if (typeof global.WebSocketClient !== 'undefined') {
            WebSocketClient.connect();
        }

        // Field-level validation
        if (typeof global.ValidationEngine !== 'undefined') {
            ValidationEngine.attachListeners(form);
        }

        // Email check
        initEmailCheck(document.getElementById('email'));

        // Age displays
        setupAgeDisplay(
            document.getElementById('dob'),
            null,
            18,
            'You'
        );
        setupAgeDisplay(
            document.getElementById('spouse_dob'),
            document.getElementById('spouse_age'),
            18,
            'Spouse'
        );

        // Password strength + confirm
        var pwInput = document.getElementById('password');
        var cpInput = document.getElementById('cpassword');
        initPasswordStrength(pwInput);
        initPasswordConfirm(pwInput, cpInput);

        // Analytics
        initAnalytics(form);

        // Prevent submit if email is taken
        form.addEventListener('submit', function (e) {
            var emailInput = document.getElementById('email');
            if (emailInput && emailInput.dataset.emailChecked === 'taken') {
                e.preventDefault();
                e.stopImmediatePropagation();
                NotificationSystem.error('Please use a different email address — this one is already registered.');
            }
        }, true /* capture: runs before existing submit handler */);
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}(window));
