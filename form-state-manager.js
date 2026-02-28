/**
 * form-state-manager.js
 * Auto-saves form progress to localStorage and optionally to the server.
 * Allows users to recover unsaved progress after page refresh.
 */

(function (global) {
    'use strict';

    var STORAGE_KEY = 'kep_form_state';
    var AUTO_SAVE_INTERVAL = 30000; // 30 seconds
    var _form = null;
    var _saveTimer = null;
    var _dirty = false;
    var _saveStatusEl = null;

    // Fields to exclude from persistence (sensitive data)
    var EXCLUDED_FIELDS = ['password', 'cpassword', 'id_photo', 'token'];

    // -------------------------------------------------------------------
    // Serialize / deserialize form state
    // -------------------------------------------------------------------
    function serializeForm(form) {
        var state = {};
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || EXCLUDED_FIELDS.indexOf(el.name) !== -1) { continue; }
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked) { state[el.name] = el.value; }
            } else if (el.type !== 'file') {
                state[el.name] = el.value;
            }
        }
        return state;
    }

    function restoreForm(form, state) {
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || !(el.name in state)) { continue; }
            if (EXCLUDED_FIELDS.indexOf(el.name) !== -1) { continue; }
            if (el.type === 'radio' || el.type === 'checkbox') {
                el.checked = (el.value === state[el.name]);
            } else if (el.type !== 'file') {
                el.value = state[el.name];
            }
        }
    }

    // -------------------------------------------------------------------
    // localStorage helpers
    // -------------------------------------------------------------------
    function saveToLocal(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ ts: Date.now(), data: state }));
        } catch (e) { /* quota exceeded or private mode */ }
    }

    function loadFromLocal() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) { return null; }
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function clearLocal() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
    }

    // -------------------------------------------------------------------
    // Server save (best-effort via fetch)
    // -------------------------------------------------------------------
    function saveToServer(state) {
        if (!global.fetch) { return; }
        fetch('api/save-form-state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(state)
        }).catch(function () { /* network error – ignore, localStorage is the fallback */ });
    }

    // -------------------------------------------------------------------
    // Save status indicator
    // -------------------------------------------------------------------
    function setStatus(text, cls) {
        if (!_saveStatusEl) { return; }
        _saveStatusEl.textContent = text;
        _saveStatusEl.className = cls ? cls : '';
    }

    // -------------------------------------------------------------------
    // Core save logic
    // -------------------------------------------------------------------
    function performSave() {
        if (!_form) { return; }
        setStatus('Saving…', 'saving');
        var state = serializeForm(_form);
        saveToLocal(state);
        saveToServer(state);
        _dirty = false;

        var unsaved = document.getElementById('unsaved-indicator');
        if (unsaved) { unsaved.classList.remove('visible'); }

        setStatus('Saved ' + new Date().toLocaleTimeString(), 'saved');
        if (typeof global.NotificationSystem !== 'undefined') {
            global.NotificationSystem.success('Form progress saved.', 2500);
        }
    }

    function markDirty() {
        if (_dirty) { return; }
        _dirty = true;
        var unsaved = document.getElementById('unsaved-indicator');
        if (unsaved) { unsaved.classList.add('visible'); }
    }

    // -------------------------------------------------------------------
    // Recovery banner
    // -------------------------------------------------------------------
    function showRecoveryBanner(savedAt) {
        var banner = document.getElementById('recovery-banner');
        if (!banner) { return; }
        var timeStr = new Date(savedAt).toLocaleString();
        banner.innerHTML =
            '<i class="fa fa-history"></i>' +
            '<span>Unsaved progress from <strong>' + timeStr + '</strong> was found. ' +
            '<button id="restore-btn" class="btn btn-sm btn-warning ms-2">Restore</button>' +
            '<button id="discard-btn" class="btn btn-sm btn-outline-secondary ms-1">Discard</button>' +
            '</span>';
        banner.classList.add('visible');

        document.getElementById('restore-btn').addEventListener('click', function () {
            var saved = loadFromLocal();
            if (saved && saved.data) {
                restoreForm(_form, saved.data);
                // Fire change events so dependent UI updates
                var event = new Event('change', { bubbles: true });
                _form.querySelectorAll('input, select').forEach(function (el) {
                    el.dispatchEvent(event);
                });
                if (typeof global.NotificationSystem !== 'undefined') {
                    global.NotificationSystem.success('Form progress restored successfully.');
                }
            }
            banner.classList.remove('visible');
        });

        document.getElementById('discard-btn').addEventListener('click', function () {
            clearLocal();
            banner.classList.remove('visible');
            if (typeof global.NotificationSystem !== 'undefined') {
                global.NotificationSystem.info('Saved progress discarded.');
            }
        });
    }

    // -------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------

    /**
     * Initialise the form state manager.
     * @param {HTMLFormElement} form
     * @param {HTMLElement} [saveStatusEl] - Element to display save status text.
     */
    function init(form, saveStatusEl) {
        _form = form;
        _saveStatusEl = saveStatusEl || document.getElementById('save-status');

        // Check for previously saved state
        var saved = loadFromLocal();
        if (saved && saved.data && Object.keys(saved.data).length > 0) {
            showRecoveryBanner(saved.ts);
        }

        // Track changes
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        // Periodic auto-save
        _saveTimer = setInterval(performSave, AUTO_SAVE_INTERVAL);

        // Save immediately before page unload
        window.addEventListener('beforeunload', function (e) {
            if (_dirty) {
                performSave();
                var msg = 'You have unsaved changes. Are you sure you want to leave?';
                e.preventDefault();
                e.returnValue = msg;
                return msg;
            }
        });

        // Clear storage after successful submit
        form.addEventListener('submit', function () {
            clearLocal();
            _dirty = false;
            if (_saveTimer) { clearInterval(_saveTimer); }
        });
    }

    /**
     * Force an immediate save.
     */
    function saveNow() {
        performSave();
    }

    /**
     * Clear persisted state (call after successful registration).
     */
    function clearState() {
        clearLocal();
        _dirty = false;
    }

    global.FormStateManager = {
        init:       init,
        saveNow:    saveNow,
        clearState: clearState
    };

}(window));
