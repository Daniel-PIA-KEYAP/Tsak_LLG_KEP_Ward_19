/**
 * Notification System - Toast notifications with auto-dismiss
 */
const NotificationSystem = (() => {
    let container = null;

    function init() {
        container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
    }

    const icons = {
        success: '<i class="fa fa-check-circle toast-icon"></i>',
        error:   '<i class="fa fa-times-circle toast-icon"></i>',
        warning: '<i class="fa fa-exclamation-triangle toast-icon"></i>',
        info:    '<i class="fa fa-info-circle toast-icon"></i>'
    };

    function show(message, type = 'info', duration = 4000) {
        if (!container) init();
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            ${icons[type] || icons.info}
            <div class="toast-body">${message}</div>
            <button class="toast-close" aria-label="Close">&times;</button>
            <div class="toast-progress"></div>
        `;
        toast.querySelector('.toast-close').addEventListener('click', () => dismiss(toast));
        container.appendChild(toast);

        const timer = setTimeout(() => dismiss(toast), duration);
        toast.dataset.timer = timer;
        return toast;
    }

    function dismiss(toast) {
        if (!toast || !toast.parentNode) return;
        clearTimeout(parseInt(toast.dataset.timer));
        toast.classList.add('toast-hiding');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }

    function success(msg, duration) { return show(msg, 'success', duration); }
    function error(msg, duration)   { return show(msg, 'error',   duration); }
    function warning(msg, duration) { return show(msg, 'warning', duration); }
    function info(msg, duration)    { return show(msg, 'info',    duration); }

    return { init, show, dismiss, success, error, warning, info };
})();

if (typeof module !== 'undefined') module.exports = NotificationSystem;
