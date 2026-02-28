<?php
/**
 * api/get-user-by-qr.php
 * Retrieve User Data Using QR Code Token
 *
 * Accepts a JSON POST body with:
 *   - token  (string, required)
 *
 * Returns JSON:
 *   { success: true,  user: { email, reg_id, verified, registered_at, ... } }
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

const QR_USER_RATE_LIMIT  = 20;   // max requests per window
const QR_USER_RATE_WINDOW = 300;  // seconds (5 min)

$now = time();

if (!isset($_SESSION['qr_user_attempts'])) {
    $_SESSION['qr_user_attempts']      = 0;
    $_SESSION['qr_user_window_start']  = $now;
}

if (($now - $_SESSION['qr_user_window_start']) > QR_USER_RATE_WINDOW) {
    $_SESSION['qr_user_attempts']      = 0;
    $_SESSION['qr_user_window_start']  = $now;
}

$_SESSION['qr_user_attempts']++;

if ($_SESSION['qr_user_attempts'] > QR_USER_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------------------
// Require QR authentication
// ---------------------------------------------------------------------------
if (empty($_SESSION['qr_authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please scan your QR code to log in.']);
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

// Ensure the authenticated session matches the requested token
$tokens = $_SESSION['qr_tokens'] ?? [];

if (!isset($tokens[$token])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'QR code not found or expired.']);
    exit;
}

$entry = $tokens[$token];

// Cross-check: the authenticated user must own this token
if ($entry['reg_id'] !== ($_SESSION['qr_user_reg_id'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (time() > $entry['expires_at']) {
    unset($_SESSION['qr_tokens'][$token]);
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'QR code has expired. Please generate a new one.']);
    exit;
}

// ---------------------------------------------------------------------------
// Retrieve full user profile
// In production, query the database using $entry['reg_id'].
// Here we return the session-stored data as a placeholder.
// ---------------------------------------------------------------------------
$user = [
    'email'          => $entry['email'],
    'reg_id'         => $entry['reg_id'],
    'verified'       => (bool) $entry['verified'],
    'registered_at'  => date(DATE_ATOM, $entry['timestamp']),
    'expires_at'     => date(DATE_ATOM, $entry['expires_at']),
];

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------
$logEntry = sprintf(
    "[%s] QR user data access | reg_id=%s | ip=%s\n",
    date('Y-m-d H:i:s'),
    $entry['reg_id'],
    htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8')
);
@file_put_contents(__DIR__ . '/../logs/qr_audit.log', $logEntry, FILE_APPEND | LOCK_EX);

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
echo json_encode([
    'success' => true,
    'user'    => $user,
]);
