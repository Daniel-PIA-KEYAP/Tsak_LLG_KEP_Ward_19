/**
 * Validation Engine - Real-time field validation
 */
const ValidationEngine = (() => {
    const rules = {
        first_name:  { minLength: 2, pattern: /^[A-Za-z\s'-]+$/, label: 'First name' },
        surname:     { minLength: 2, pattern: /^[A-Za-z\s'-]+$/, label: 'Surname' },
        village:     { minLength: 2, label: 'Village' },
        tribe:       { minLength: 2, label: 'Tribe' },
        council_ward:{ minLength: 2, label: 'Council Ward' },
        district:    { minLength: 2, label: 'District' },
        province:    { minLength: 2, label: 'Province' },
        nationality: { minLength: 2, label: 'Nationality' },
        email:       { type: 'email', label: 'Email' },
        mobile:      { pattern: /^[0-9]{10}$/, label: 'Mobile number (10 digits)' },
        dob:         { type: 'date', minAge: 18, label: 'Date of birth' },
        password:    { minLength: 8, label: 'Password' }
    };

    function validateField(id, value) {
        const rule = rules[id];
        if (!rule) return null;
        if (!value || value.trim() === '') return { valid: false, message: `${rule.label} is required.` };
        if (rule.minLength && value.trim().length < rule.minLength)
            return { valid: false, message: `${rule.label} must be at least ${rule.minLength} characters.` };
        if (rule.pattern && !rule.pattern.test(value.trim()))
            return { valid: false, message: `${rule.label} contains invalid characters.` };
        if (rule.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()))
            return { valid: false, message: 'Please enter a valid email address.' };
        if (rule.type === 'date' && rule.minAge) {
            const dob = new Date(value);
            const today = new Date();
            if (isNaN(dob.getTime())) return { valid: false, message: 'Please enter a valid date.' };
            if (dob > today) return { valid: false, message: 'Date cannot be in the future.' };
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            if (age < rule.minAge) return { valid: false, message: `You must be at least ${rule.minAge} years old.` };
        }
        return { valid: true, message: `${rule.label} looks good!` };
    }

    function applyFeedback(input, result) {
        if (!result) return;
        const feedbackId = `${input.id}-feedback`;
        let feedback = document.getElementById(feedbackId);
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.id = feedbackId;
            feedback.className = 'field-feedback';
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }
        input.classList.remove('field-valid', 'field-invalid');
        feedback.classList.remove('valid', 'invalid');
        if (result.valid) {
            input.classList.add('field-valid');
            feedback.classList.add('valid');
        } else {
            input.classList.add('field-invalid');
            feedback.classList.add('invalid');
        }
        feedback.textContent = result.message;
    }

    function attachToField(input) {
        const validate = () => {
            const result = validateField(input.id, input.value);
            if (result) applyFeedback(input, result);
        };
        input.addEventListener('input', validate);
        input.addEventListener('blur', validate);
    }

    function init() {
        Object.keys(rules).forEach(id => {
            const el = document.getElementById(id);
            if (el) attachToField(el);
        });
    }

    function validateDate(inputEl, minAge) {
        const val = inputEl.value;
        if (!val) return false;
        const dob = new Date(val);
        const today = new Date();
        if (isNaN(dob.getTime()) || dob > today) return false;
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age >= minAge;
    }

    return { init, validateField, applyFeedback, attachToField, validateDate };
})();

if (typeof module !== 'undefined') module.exports = ValidationEngine;
