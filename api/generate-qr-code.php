<?php
/**
 * api/generate-qr-code.php
 * QR Code Generation and Storage Endpoint
 *
 * Accepts a JSON POST body with:
 *   - email      (string, required)
 *   - reg_id     (string, required) – the user's registration ID
 *
 * Returns JSON:
 *   { success: true,  qr_data: { email, reg_id, token, timestamp, verified }, expires_at: <ISO8601> }
 *   { success: false, message: "..." }
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// ---------------------------------------------------------------------------
// Rate limiting (simple in-session counter – replace with DB/cache in production)
// ---------------------------------------------------------------------------
session_start();

const QR_RATE_LIMIT    = 10;           // max attempts per window
const QR_RATE_WINDOW   = 300;          // seconds (5 min)
const QR_TOKEN_TTL     = 86400;        // seconds QR token is valid (24 h)

$now = time();

if (!isset($_SESSION['qr_gen_attempts'])) {
    $_SESSION['qr_gen_attempts'] = 0;
    $_SESSION['qr_gen_window_start'] = $now;
}

if (($now - $_SESSION['qr_gen_window_start']) > QR_RATE_WINDOW) {
    $_SESSION['qr_gen_attempts']      = 0;
    $_SESSION['qr_gen_window_start']  = $now;
}

$_SESSION['qr_gen_attempts']++;

if ($_SESSION['qr_gen_attempts'] > QR_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------------------
// Parse input
// ---------------------------------------------------------------------------
$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

// Validate required fields
$email  = isset($input['email'])  ? trim((string) $input['email'])  : '';
$reg_id = isset($input['reg_id']) ? trim((string) $input['reg_id']) : '';

if ($email === '' || $reg_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'email and reg_id are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Sanitise reg_id to alphanumeric + hyphens/underscores only
if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $reg_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid registration ID format.']);
    exit;
}

// ---------------------------------------------------------------------------
// Generate cryptographically secure token
// ---------------------------------------------------------------------------
$token     = bin2hex(random_bytes(32));   // 64 hex chars
$timestamp = $now;
$expiresAt = $now + QR_TOKEN_TTL;

// ---------------------------------------------------------------------------
// Persist the token (session-based placeholder).
// In production, store in a qr_tokens database table.
// ---------------------------------------------------------------------------
if (!isset($_SESSION['qr_tokens'])) {
    $_SESSION['qr_tokens'] = [];
}

// Remove any previously issued token for this reg_id
foreach ($_SESSION['qr_tokens'] as $k => $v) {
    if (isset($v['reg_id']) && $v['reg_id'] === $reg_id) {
        unset($_SESSION['qr_tokens'][$k]);
    }
}

$_SESSION['qr_tokens'][$token] = [
    'email'      => $email,
    'reg_id'     => $reg_id,
    'timestamp'  => $timestamp,
    'expires_at' => $expiresAt,
    'verified'   => false,
];

// ---------------------------------------------------------------------------
// Audit log (append to server-side log file)
// ---------------------------------------------------------------------------
$logEntry = sprintf(
    "[%s] QR generated | reg_id=%s | ip=%s\n",
    date('Y-m-d H:i:s'),
    $reg_id,
    htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8')
);
@file_put_contents(__DIR__ . '/../logs/qr_audit.log', $logEntry, FILE_APPEND | LOCK_EX);

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
echo json_encode([
    'success'    => true,
    'qr_data'    => [
        'email'     => $email,
        'reg_id'    => $reg_id,
        'token'     => $token,
        'timestamp' => $timestamp,
        'verified'  => false,
    ],
    'expires_at' => date(DATE_ATOM, $expiresAt),
]);
