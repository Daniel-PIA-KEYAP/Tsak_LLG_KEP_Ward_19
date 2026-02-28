/**
 * notification-system.js
 * Toast notification system with auto-dismiss for the KEP registration form.
 */

(function (global) {
    'use strict';

    var ICONS = {
        success: '<i class="fa fa-check-circle"></i>',
        error:   '<i class="fa fa-times-circle"></i>',
        warning: '<i class="fa fa-exclamation-triangle"></i>',
        info:    '<i class="fa fa-info-circle"></i>'
    };

    var container = null;

    function getContainer() {
        if (!container) {
            container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }
        }
        return container;
    }

    /**
     * Show a toast notification.
     * @param {string} message    - Text to display.
     * @param {'success'|'error'|'warning'|'info'} type - Toast type.
     * @param {number} [duration] - Auto-dismiss delay in ms (0 = no auto-dismiss).
     */
    function show(message, type, duration) {
        if (typeof type === 'undefined') { type = 'info'; }
        if (typeof duration === 'undefined') { duration = 4000; }

        const ct = getContainer();
        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        toast.innerHTML =
            '<span class="toast-icon">' + (ICONS[type] || ICONS.info) + '</span>' +
            '<span class="toast-body">' + escapeHtml(message) + '</span>' +
            '<button class="toast-close" aria-label="Close">&times;</button>';

        ct.appendChild(toast);

        // Trigger transition
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('show');
            });
        });

        function dismiss() {
            toast.classList.add('hiding');
            toast.classList.remove('show');
            toast.addEventListener('transitionend', function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, { once: true });
        }

        toast.querySelector('.toast-close').addEventListener('click', dismiss);
        toast.addEventListener('click', dismiss);

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }

        return { dismiss: dismiss };
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    global.NotificationSystem = {
        show: show,
        success: function (msg, dur) { return show(msg, 'success', dur); },
        error:   function (msg, dur) { return show(msg, 'error',   dur); },
        warning: function (msg, dur) { return show(msg, 'warning', dur); },
        info:    function (msg, dur) { return show(msg, 'info',    dur); }
    };

}(window));
