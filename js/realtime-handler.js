/**
 * Real-Time Handler - Coordinator for all real-time features
 */
const RealtimeHandler = (() => {
    let emailDebounceTimer = null;
    let analyticsData = { startTime: Date.now(), fieldFocus: {}, completedFields: new Set() };

    // ── Form Progress ─────────────────────────────────────────────────────────
    function updateProgress() {
        const form = document.getElementById('registration-form');
        if (!form) return;
        const required = form.querySelectorAll('[required]');
        let filled = 0;
        required.forEach(el => {
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (form.querySelector(`[name="${el.name}"]:checked`)) filled++;
            } else if (el.value && el.value.trim() !== '') {
                filled++;
            }
        });
        const pct = required.length > 0 ? Math.round((filled / required.length) * 100) : 0;
        const bar = document.getElementById('progress-fill');
        const label = document.getElementById('progress-label');
        if (bar)   bar.style.width = `${pct}%`;
        if (label) label.textContent = `Form completion: ${pct}%`;
    }

    // ── Email Real-Time Check ─────────────────────────────────────────────────
    function initEmailCheck() {
        const emailInput = document.getElementById('email');
        if (!emailInput) return;
        emailInput.addEventListener('input', () => {
            clearTimeout(emailDebounceTimer);
            const val = emailInput.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return;
            emailInput.parentNode.classList.add('email-checking');
            emailDebounceTimer = setTimeout(() => checkEmailRealtime(emailInput, val), 500);
        });
    }

    async function checkEmailRealtime(input, email) {
        try {
            const resp = await fetch('api/check-email-realtime.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}`
            });
            const data = await resp.json();
            input.parentNode.classList.remove('email-checking');
            const feedbackId = 'email-feedback';
            let fb = document.getElementById(feedbackId);
            if (!fb) {
                fb = document.createElement('div');
                fb.id = feedbackId;
                fb.className = 'field-feedback';
                input.parentNode.insertBefore(fb, input.nextSibling);
            }
            if (data.exists) {
                input.classList.add('field-invalid');
                fb.classList.add('invalid');
                fb.textContent = 'This email is already registered.';
                if (typeof NotificationSystem !== 'undefined')
                    NotificationSystem.warning('This email address is already registered.');
            } else {
                input.classList.add('field-valid');
                fb.classList.add('valid');
                fb.textContent = 'Email is available.';
            }
        } catch {
            const inp = input;
            if (inp) inp.parentNode.classList.remove('email-checking');
        }
    }

    // ── Password Strength Meter ───────────────────────────────────────────────
    function initPasswordStrength() {
        const input = document.getElementById('password');
        const container = document.getElementById('password-strength');
        if (!input || !container) return;

        container.innerHTML = `
            <div class="strength-bar-container">
                <div class="strength-bar-segment" id="sb1"></div>
                <div class="strength-bar-segment" id="sb2"></div>
                <div class="strength-bar-segment" id="sb3"></div>
                <div class="strength-bar-segment" id="sb4"></div>
            </div>
            <div id="strength-label" class="password-strength"></div>
            <ul class="password-requirements" id="pw-reqs">
                <li id="req-length">At least 8 characters</li>
                <li id="req-upper">Uppercase letter</li>
                <li id="req-lower">Lowercase letter</li>
                <li id="req-number">Number</li>
                <li id="req-symbol">Symbol (!@#$...)</li>
            </ul>
        `;

        input.addEventListener('input', () => updatePasswordStrength(input.value));
    }

    function updatePasswordStrength(pwd) {
        const checks = {
            'req-length': pwd.length >= 8,
            'req-upper':  /[A-Z]/.test(pwd),
            'req-lower':  /[a-z]/.test(pwd),
            'req-number': /[0-9]/.test(pwd),
            'req-symbol': /[^A-Za-z0-9]/.test(pwd)
        };
        let score = Object.values(checks).filter(Boolean).length;

        Object.entries(checks).forEach(([id, met]) => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('met', met);
        });

        const segs = ['sb1','sb2','sb3','sb4'];
        const label = document.getElementById('strength-label');
        segs.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'strength-bar-segment';
        });

        if (pwd.length === 0 || score === 0) {
            if (label) label.textContent = '';
            return;
        }
        const activeClass = score <= 2 ? 'active-weak' : score <= 3 ? 'active-medium' : 'active-strong';
        const activeCount = score <= 2 ? 1 : score <= 3 ? 2 : score === 4 ? 3 : 4;
        for (let i = 0; i < activeCount; i++) {
            const el = document.getElementById(segs[i]);
            if (el) el.classList.add(activeClass);
        }
        if (label) {
            label.textContent = score <= 2 ? 'Weak' : score <= 3 ? 'Medium' : 'Strong';
            label.className = `password-strength strength-${score <= 2 ? 'weak' : score <= 3 ? 'medium' : 'strong'}`;
        }
    }

    // ── Real-Time Age Calculations ────────────────────────────────────────────
    function initDateValidation() {
        // Main DOB
        const dob = document.getElementById('dob');
        if (dob) {
            dob.addEventListener('input', () => {
                if (dob.value) {
                    const age = calcAge(dob.value);
                    let fb = document.getElementById('dob-age-feedback');
                    if (!fb) {
                        fb = document.createElement('small');
                        fb.id = 'dob-age-feedback';
                        fb.className = 'text-muted';
                        dob.parentNode.appendChild(fb);
                    }
                    fb.textContent = age >= 0 ? `Age: ${age} years` : '';
                }
            });
        }
        // Spouse DOB
        const spouseDob = document.getElementById('spouse_dob');
        if (spouseDob) {
            spouseDob.addEventListener('input', () => {
                const age = calcAge(spouseDob.value);
                const spouseAge = document.getElementById('spouse_age');
                if (spouseAge) spouseAge.value = age >= 0 ? age : '';
            });
        }
    }

    function calcAge(dateStr) {
        if (!dateStr) return -1;
        const dob = new Date(dateStr);
        const today = new Date();
        if (isNaN(dob.getTime())) return -1;
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age;
    }

    // ── Form Analytics ────────────────────────────────────────────────────────
    function initAnalytics() {
        const form = document.getElementById('registration-form');
        if (!form) return;
        form.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('focus', () => { analyticsData.fieldFocus[el.name || el.id] = Date.now(); });
            el.addEventListener('blur', () => {
                if (el.value) analyticsData.completedFields.add(el.name || el.id);
            });
        });
    }

    // ── Init All ─────────────────────────────────────────────────────────────
    function init() {
        if (typeof ValidationEngine !== 'undefined')  ValidationEngine.init();
        if (typeof FormStateManager !== 'undefined')  FormStateManager.init();
        if (typeof WebSocketClient !== 'undefined')   WebSocketClient.init();
        if (typeof NotificationSystem !== 'undefined') NotificationSystem.init();

        initEmailCheck();
        initPasswordStrength();
        initDateValidation();
        initAnalytics();

        // Progress updates on any input
        const form = document.getElementById('registration-form');
        if (form) {
            form.addEventListener('input',  updateProgress);
            form.addEventListener('change', updateProgress);
            updateProgress();
        }
    }

    return { init, updateProgress, calcAge };
})();

if (typeof module !== 'undefined') module.exports = RealtimeHandler;
