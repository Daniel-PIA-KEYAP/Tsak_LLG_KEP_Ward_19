<?php
/**
 * api/validate-qr-code.php
 * QR Code Validation and Authentication Endpoint
 *
 * Accepts a JSON POST body with:
 *   - token  (string, required)
 *
 * Returns JSON:
 *   { success: true,  email: "...", reg_id: "...", verified: bool }
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

const QR_VALIDATE_RATE_LIMIT  = 20;   // max attempts per window
const QR_VALIDATE_RATE_WINDOW = 300;  // seconds (5 min)

$now = time();

if (!isset($_SESSION['qr_validate_attempts'])) {
    $_SESSION['qr_validate_attempts']      = 0;
    $_SESSION['qr_validate_window_start']  = $now;
}

if (($now - $_SESSION['qr_validate_window_start']) > QR_VALIDATE_RATE_WINDOW) {
    $_SESSION['qr_validate_attempts']      = 0;
    $_SESSION['qr_validate_window_start']  = $now;
}

$_SESSION['qr_validate_attempts']++;

if ($_SESSION['qr_validate_attempts'] > QR_VALIDATE_RATE_LIMIT) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many validation attempts. Please try again later.']);
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
// Token lookup (session-based placeholder)
// ---------------------------------------------------------------------------
$tokens = $_SESSION['qr_tokens'] ?? [];

if (!isset($tokens[$token])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'QR code not found or already used.']);
    exit;
}

$entry = $tokens[$token];

// Check expiry
if (time() > $entry['expires_at']) {
    // Remove expired token
    unset($_SESSION['qr_tokens'][$token]);
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'QR code has expired. Please generate a new one.']);
    exit;
}

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------
$logEntry = sprintf(
    "[%s] QR validated | reg_id=%s | ip=%s\n",
    date('Y-m-d H:i:s'),
    $entry['reg_id'],
    htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8')
);
@file_put_contents(__DIR__ . '/../logs/qr_audit.log', $logEntry, FILE_APPEND | LOCK_EX);

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
echo json_encode([
    'success'    => true,
    'email'      => $entry['email'],
    'reg_id'     => $entry['reg_id'],
    'verified'   => (bool) $entry['verified'],
    'expires_at' => date(DATE_ATOM, $entry['expires_at']),
]);
