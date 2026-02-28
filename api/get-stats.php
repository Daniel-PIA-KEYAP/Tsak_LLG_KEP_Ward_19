<?php
/**
 * api/get-stats.php
 * Returns community registration statistics.
 *
 * Regular request : GET/POST → JSON response.
 * Streaming (SSE) : GET ?stream=1 → text/event-stream with periodic pushes.
 */

// Load config
$configFile = dirname(__DIR__) . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$dbHost    = defined('DB_HOST')    ? DB_HOST    : 'localhost';
$dbName    = defined('DB_NAME')    ? DB_NAME    : 'kep_ward19';
$dbUser    = defined('DB_USER')    ? DB_USER    : 'root';
$dbPass    = defined('DB_PASS')    ? DB_PASS    : '';
$dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

/**
 * Fetch statistics from the database.
 * Falls back to static defaults when the DB is unavailable.
 *
 * @return array
 */
function fetchStats($dbHost, $dbName, $dbUser, $dbPass, $dbCharset) {
    try {
        $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=' . $dbCharset;
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $employed = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE employed = 'yes'"
        )->fetchColumn();
        $selfEmployed = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE non_employed_status = 'self_employed'"
        )->fetchColumn();
        $students = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE non_employed_status = 'student'"
        )->fetchColumn();

        return [
            'total'        => $total,
            'employed'     => $employed,
            'not_employed' => $total - $employed,
            'self_employed' => $selfEmployed,
            'students'     => $students,
            'source'       => 'db',
        ];
    } catch (PDOException $e) {
        // Return static community statistics as fallback
        return [
            'total'        => 750,
            'employed'     => 200,
            'not_employed' => 550,
            'self_employed' => 100,
            'students'     => 150,
            'source'       => 'static',
        ];
    }
}

$isStream = isset($_GET['stream']) && $_GET['stream'] === '1';

if ($isStream) {
    // Server-Sent Events mode
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // Disable Nginx buffering

    // Disable output buffering
    if (ob_get_level()) { ob_end_clean(); }

    $iterations = 0;
    $maxIterations = 60; // stop after 60 pushes (~30 minutes at 30s interval)

    while ($iterations < $maxIterations) {
        $stats = fetchStats($dbHost, $dbName, $dbUser, $dbPass, $dbCharset);
        echo 'event: stats' . "\n";
        echo 'data: ' . json_encode($stats) . "\n\n";
        flush();

        if (connection_aborted()) { break; }
        sleep(30);
        $iterations++;
    }
} else {
    // Single JSON response
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $stats = fetchStats($dbHost, $dbName, $dbUser, $dbPass, $dbCharset);
    echo json_encode($stats);
}
