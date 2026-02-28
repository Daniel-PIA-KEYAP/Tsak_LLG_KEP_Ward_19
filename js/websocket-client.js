/**
 * WebSocket / SSE Client - Live statistics updates
 */
const WebSocketClient = (() => {
    let evtSource = null;
    let retryTimeout = null;
    const RETRY_DELAY = 5000;

    function connect() {
        if (!window.EventSource) return;
        if (evtSource) evtSource.close();

        evtSource = new EventSource('api/get-stats.php?stream=1');

        evtSource.addEventListener('stats', (e) => {
            try {
                const stats = JSON.parse(e.data);
                updateStatsDisplay(stats);
            } catch (err) { /* ignore malformed data */ }
        });

        evtSource.addEventListener('registration', (e) => {
            try {
                const info = JSON.parse(e.data);
                if (typeof NotificationSystem !== 'undefined') {
                    NotificationSystem.info(`New member registered: ${info.village || 'community'}`);
                }
            } catch { /* ignore */ }
        });

        evtSource.onerror = () => {
            evtSource.close();
            // Clear any existing poll timer to avoid running both SSE and polling
            if (retryTimeout) { clearTimeout(retryTimeout); retryTimeout = null; }
            // Fallback: poll every 30s
            retryTimeout = setTimeout(() => pollStats(), RETRY_DELAY);
        };
    }

    async function pollStats() {
        try {
            const resp = await fetch('api/get-stats.php');
            if (!resp.ok) return;
            const stats = await resp.json();
            updateStatsDisplay(stats);
        } catch { /* network error, try again later */ }
        retryTimeout = setTimeout(pollStats, 30000);
    }

    function updateStatsDisplay(stats) {
        const fields = {
            'stat-total':        stats.total,
            'stat-employed':     stats.employed,
            'stat-unemployed':   stats.unemployed,
            'stat-self-employed':stats.self_employed,
            'stat-student':      stats.student
        };
        Object.entries(fields).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el && val !== undefined) el.textContent = val;
        });

        // Update percentages
        if (stats.total > 0) {
            setStatPercent('stat-employed-pct',      stats.employed,      stats.total);
            setStatPercent('stat-unemployed-pct',    stats.unemployed,    stats.total);
            setStatPercent('stat-self-employed-pct', stats.self_employed, stats.total);
            setStatPercent('stat-student-pct',       stats.student,       stats.total);
        }
    }

    function setStatPercent(id, value, total) {
        const el = document.getElementById(id);
        if (el) el.textContent = `${((value / total) * 100).toFixed(1)}%`;
    }

    function disconnect() {
        if (evtSource) { evtSource.close(); evtSource = null; }
        if (retryTimeout) { clearTimeout(retryTimeout); retryTimeout = null; }
    }

    function init() {
        // Try SSE first, fall back to polling
        connect();
    }

    return { init, connect, disconnect, updateStatsDisplay };
})();

if (typeof module !== 'undefined') module.exports = WebSocketClient;
