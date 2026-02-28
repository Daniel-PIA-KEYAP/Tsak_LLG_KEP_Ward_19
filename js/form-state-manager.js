/**
 * Form State Manager - Auto-save to localStorage and server every 30s
 */
const FormStateManager = (() => {
    const STORAGE_KEY = 'kep_form_state';
    const SAVE_INTERVAL = 30000;
    const MAX_STATE_AGE_MS = 24 * 60 * 60 * 1000; // 24 hours
    let saveTimer = null;
    let hasUnsavedChanges = false;
    let saveStatusEl = null;

    function init() {
        saveStatusEl = document.getElementById('save-status');
        const form = document.getElementById('registration-form');
        if (!form) return;

        // Restore saved state
        restoreFromLocalStorage();

        // Listen for changes
        form.addEventListener('input', onFormChange);
        form.addEventListener('change', onFormChange);

        // Auto-save every 30 seconds
        saveTimer = setInterval(autoSave, SAVE_INTERVAL);

        // Warn before leaving if unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    function onFormChange() {
        hasUnsavedChanges = true;
        updateSaveStatus('unsaved', 'Unsaved changes');
        showUnsavedWarning(true);
    }

    function collectFormData() {
        const form = document.getElementById('registration-form');
        if (!form) return {};
        const data = {};
        const inputs = form.querySelectorAll('input:not([type=file]):not([type=password]), select, textarea');
        inputs.forEach(input => {
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) data[input.name] = input.value;
            } else {
                data[input.name] = input.value;
            }
        });
        return data;
    }

    function saveToLocalStorage(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ data, ts: Date.now() }));
        } catch (e) { /* storage full or unavailable */ }
    }

    function restoreFromLocalStorage() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) return;
            const { data, ts } = JSON.parse(saved);
            const age = Date.now() - ts;
            if (age > MAX_STATE_AGE_MS) return; // ignore if older than 24h
            const form = document.getElementById('registration-form');
            if (!form) return;
            Object.entries(data).forEach(([name, value]) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (!el) return;
                if (el.type === 'radio') {
                    const radio = form.querySelector(`[name="${name}"][value="${value}"]`);
                    if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
                } else if (el.type === 'checkbox') {
                    el.checked = (value === el.value);
                } else {
                    el.value = value;
                    el.dispatchEvent(new Event('input'));
                }
            });
            if (typeof NotificationSystem !== 'undefined') {
                NotificationSystem.info('Unsaved progress has been restored.');
            }
        } catch (e) { /* parse error */ }
    }

    async function saveToServer(data) {
        try {
            const resp = await fetch('api/save-form-state.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return resp.ok;
        } catch { return false; }
    }

    async function autoSave() {
        if (!hasUnsavedChanges) return;
        updateSaveStatus('saving', 'Saving...');
        const data = collectFormData();
        saveToLocalStorage(data);
        const ok = await saveToServer(data);
        hasUnsavedChanges = false;
        showUnsavedWarning(false);
        if (ok) {
            updateSaveStatus('saved', 'Saved');
        } else {
            updateSaveStatus('saved', 'Saved locally');
        }
    }

    function updateSaveStatus(state, text) {
        if (!saveStatusEl) return;
        saveStatusEl.className = state;
        const icon = state === 'saving' ? '⟳' : state === 'saved' ? '✓' : '●';
        saveStatusEl.innerHTML = `<span>${icon}</span><span>${text}</span>`;
    }

    function showUnsavedWarning(show) {
        const el = document.getElementById('unsaved-warning');
        if (el) el.style.display = show ? 'block' : 'none';
    }

    function clearSavedState() {
        localStorage.removeItem(STORAGE_KEY);
        hasUnsavedChanges = false;
        showUnsavedWarning(false);
    }

    return { init, autoSave, clearSavedState, collectFormData };
})();

if (typeof module !== 'undefined') module.exports = FormStateManager;
