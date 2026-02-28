<?php
/**
 * Real-time statistics endpoint (supports SSE streaming)
 */
require_once __DIR__ . '/../config.php';

$isStream = isset($_GET['stream']) && $_GET['stream'] === '1';

if ($isStream) {
    // Server-Sent Events
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    // Send initial stats
    $stats = fetch_stats();
    echo "event: stats\n";
    echo 'data: ' . json_encode($stats) . "\n\n";
    echo "retry: " . SSE_RETRY_INTERVAL . "\n\n";
    flush();

    // Keep-alive for a limited time (avoid long-running scripts)
    $end = time() + 55; // ~55 seconds max (typical PHP max_execution_time is 60)
    while (time() < $end) {
        sleep(5);
        $stats = fetch_stats();
        echo "event: stats\n";
        echo 'data: ' . json_encode($stats) . "\n\n";
        flush();
        if (connection_aborted()) break;
    }
    exit;
}

// Regular JSON response
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode(fetch_stats());

function fetch_stats(): array {
    $defaults = [
        // Fallback demo values shown when database is unavailable
        'total'         => 0,
        'employed'      => 0,
        'unemployed'    => 0,
        'self_employed' => 0,
        'student'       => 0
    ];
    try {
        $pdo = get_db();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($total === 0) return $defaults;

        $emp  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE employed='yes'")->fetchColumn();
        $self = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE non_employed_status='self_employed'")->fetchColumn();
        $stu  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE non_employed_status='student'")->fetchColumn();
        return [
            'total'         => $total,
            'employed'      => $emp,
            'unemployed'    => $total - $emp,
            'self_employed' => $self,
            'student'       => $stu
        ];
    } catch (PDOException $e) {
        return $defaults;
    }
}
