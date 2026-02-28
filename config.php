<?php
/**
 * Configuration for real-time features and QR codes
 */

// Database configuration (update with actual values)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'kep_ward19');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// QR Code configuration
define('QR_TOKEN_LENGTH', 32);
define('QR_TOKEN_EXPIRY', 86400 * 30); // 30 days in seconds
$_qrKey = getenv('QR_SECRET_KEY');
if (!$_qrKey) {
    if (getenv('APP_ENV') === 'production') {
        error_log('CRITICAL: QR_SECRET_KEY environment variable is not set in production.');
    }
    $_qrKey = 'kep-ward19-qr-secret-change-in-prod';
}
define('QR_SECRET_KEY', $_qrKey);
unset($_qrKey);

// Real-time features
define('FORM_STATE_EXPIRY', 86400);        // 24 hours
define('EMAIL_CHECK_RATE_LIMIT', 60);      // max 60 checks/minute per IP
define('SSE_RETRY_INTERVAL', 5000);        // ms

// Application URLs
define('APP_URL',      getenv('APP_URL')      ?: 'http://localhost');
define('QR_LOGIN_URL', APP_URL . '/qr-login.php');
define('QR_VERIFY_URL',APP_URL . '/qr-verify.php');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// CSRF helpers
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get PDO database connection (lazy singleton).
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Rate-limit by IP for a given action key.
 */
function rate_limit(string $key, int $maxPerMinute): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheKey = "rl_{$key}_{$ip}";
    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = ['count' => 0, 'ts' => time()];
    }
    $rl = &$_SESSION[$cacheKey];
    if (time() - $rl['ts'] > 60) {
        $rl = ['count' => 0, 'ts' => time()];
    }
    if ($rl['count'] >= $maxPerMinute) return false;
    $rl['count']++;
    return true;
}
