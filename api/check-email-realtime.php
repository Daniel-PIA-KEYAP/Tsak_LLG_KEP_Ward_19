<?php
/**
 * api/check-email-realtime.php
 * Real-time email availability check endpoint.
 * Called via AJAX with debounce from the registration form.
 *
 * Request  : POST, body = email=<value>
 * Response : JSON { "exists": true|false }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Basic validation
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'valid' => false]);
    exit;
}

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

$exists = false;

try {
    $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=' . $dbCharset;
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = (bool) $stmt->fetchColumn();
} catch (PDOException $e) {
    // DB not available – return false so the form is not blocked
    echo json_encode(['exists' => false, 'valid' => true, 'db_error' => true]);
    exit;
}

echo json_encode(['exists' => $exists, 'valid' => true]);
