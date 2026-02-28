/**
 * websocket-client.js
 * Connects to the server-sent events (SSE) endpoint for live statistics updates.
 * Falls back gracefully to HTTP polling when SSE is unavailable.
 *
 * Note: Named 'WebSocketClient' per project spec but implements SSE/polling,
 * not native WebSocket protocol.
 */

(function (global) {
    'use strict';

    var SSE_URL = 'api/get-stats.php?stream=1';
    var POLL_URL = 'api/get-stats.php';
    var POLL_INTERVAL = 30000; // 30 seconds fallback polling
    var _eventSource = null;
    var _pollTimer = null;

    // -------------------------------------------------------------------
    // Update statistics in the DOM
    // -------------------------------------------------------------------
    function applyStats(stats) {
        if (!stats) { return; }

        var fields = {
            'stat-total':        stats.total,
            'stat-employed':     stats.employed,
            'stat-not-employed': stats.not_employed,
            'stat-self-employed': stats.self_employed,
            'stat-students':     stats.students
        };

        Object.keys(fields).forEach(function (id) {
            var el = document.getElementById(id);
            if (!el || fields[id] === undefined) { return; }
            var newVal = fields[id];
            if (el.textContent !== String(newVal)) {
                el.textContent = newVal;
                el.classList.add('updated');
                setTimeout(function () { el.classList.remove('updated'); }, 1500);
            }
        });

        // Update percentages
        if (stats.total > 0) {
            updatePct('stat-employed-pct',     stats.employed,     stats.total);
            updatePct('stat-not-employed-pct', stats.not_employed, stats.total);
            updatePct('stat-self-employed-pct', stats.self_employed, stats.total);
            updatePct('stat-students-pct',     stats.students,     stats.total);
        }
    }

    function updatePct(id, value, total) {
        var el = document.getElementById(id);
        if (!el) { return; }
        var pct = ((value / total) * 100).toFixed(1);
        var newText = pct + '%';
        if (el.textContent !== newText) {
            el.textContent = newText;
            el.classList.add('updated');
            setTimeout(function () { el.classList.remove('updated'); }, 1500);
        }
    }

    // -------------------------------------------------------------------
    // SSE (Server-Sent Events)
    // -------------------------------------------------------------------
    function connectSSE() {
        if (!global.EventSource) {
            startPolling();
            return;
        }

        _eventSource = new EventSource(SSE_URL);

        _eventSource.addEventListener('stats', function (e) {
            try {
                var data = JSON.parse(e.data);
                applyStats(data);
            } catch (err) { /* malformed JSON */ }
        });

        _eventSource.addEventListener('error', function () {
            _eventSource.close();
            _eventSource = null;
            // Fallback to polling after SSE error
            startPolling();
        });
    }

    // -------------------------------------------------------------------
    // Polling fallback
    // -------------------------------------------------------------------
    function fetchStats() {
        if (!global.fetch) { return; }
        fetch(POLL_URL)
            .then(function (r) { return r.json(); })
            .then(applyStats)
            .catch(function () { /* network error – ignore */ });
    }

    function startPolling() {
        if (_pollTimer) { return; }
        fetchStats(); // Immediate first fetch
        _pollTimer = setInterval(fetchStats, POLL_INTERVAL);
    }

    // -------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------

    /**
     * Start the real-time statistics connection.
     * Uses SSE when available, falls back to HTTP polling.
     */
    function connect() {
        connectSSE();
    }

    /**
     * Disconnect and stop all updates.
     */
    function disconnect() {
        if (_eventSource) { _eventSource.close(); _eventSource = null; }
        if (_pollTimer)   { clearInterval(_pollTimer); _pollTimer = null; }
    }

    global.WebSocketClient = {
        connect:    connect,
        disconnect: disconnect,
        applyStats: applyStats
    };

}(window));
