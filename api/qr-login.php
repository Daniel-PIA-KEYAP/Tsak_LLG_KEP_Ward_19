<?php
/**
 * api/qr-login.php
 * Handle QR Code-Based Login
 *
 * Accepts a JSON POST body with:
 *   - token  (string, required)
 *
 * Validates the QR token, establishes a session for the associated user,
 * and returns JSON with a redirect URL.
 *
 * Returns JSON:
 *   { success: true,  redirect: "dashboard.php", email: "..." }
 *   { success: false, message: "..." }
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// ---------------------------------------------------------------------------
// Rate limiting
// ---------------------------------------------------------------------------
session_start();

const QR_LOGIN_RATE_LIMIT  = 10;   // max login attempts per window
const QR_LOGIN_RATE_WINDOW = 300;  // seconds (5 min)

$now = time();

if (!isset($_SESSION['qr_login_attempts'])) {
    $_SESSION['qr_login_attempts']      = 0;
    $_SESSION['qr_login_window_start']  = $now;
}

if (($now - $_SESSION['qr_login_window_start']) > QR_LOGIN_RATE_WINDOW) {
    $_SESSION['qr_login_attempts']      = 0;
    $_SESSION['qr_login_window_start']  = $now;
}

$_SESSION['qr_login_attempts']++;

if ($_SESSION['qr_login_attempts'] > QR_LOGIN_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------------------
// Parse & validate input
// ---------------------------------------------------------------------------
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$token = isset($input['token']) ? trim((string) $input['token']) : '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid token format.']);
    exit;
}

// ---------------------------------------------------------------------------
// Token lookup
// ---------------------------------------------------------------------------
$tokens = $_SESSION['qr_tokens'] ?? [];

if (!isset($tokens[$token])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'QR code not found or already used.']);
    exit;
}

$entry = $tokens[$token];

if (time() > $entry['expires_at']) {
    unset($_SESSION['qr_tokens'][$token]);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'QR code has expired. Please generate a new one.']);
    exit;
}

// ---------------------------------------------------------------------------
// Establish authenticated session
// Regenerate session ID to prevent session fixation attacks
// ---------------------------------------------------------------------------
session_regenerate_id(true);

$_SESSION['qr_authenticated'] = true;
$_SESSION['qr_user_email']    = $entry['email'];
$_SESSION['qr_user_reg_id']   = $entry['reg_id'];
$_SESSION['qr_login_time']    = $now;

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------
$logEntry = sprintf(
    "[%s] QR login | reg_id=%s | ip=%s\n",
    date('Y-m-d H:i:s'),
    $entry['reg_id'],
    htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8')
);
@file_put_contents(__DIR__ . '/../logs/qr_audit.log', $logEntry, FILE_APPEND | LOCK_EX);

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
echo json_encode([
    'success'  => true,
    'email'    => $entry['email'],
    'reg_id'   => $entry['reg_id'],
    'redirect' => 'qr-verify.php?token=' . urlencode($token),
]);
