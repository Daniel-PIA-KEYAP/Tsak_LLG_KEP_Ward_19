<?php
/**
 * QR code generation and storage endpoint
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Verify CSRF token
$token = $_POST['token'] ?? (json_decode(file_get_contents('php://input'), true)['token'] ?? '');
if (!verify_csrf_token($token)) {
    json_response(['error' => 'Invalid token'], 403);
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true) ?: $_POST;

$email          = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$registrationId = preg_replace('/[^A-Za-z0-9_-]/', '', $input['registration_id'] ?? '');

if (!$email || !$registrationId) {
    json_response(['error' => 'Invalid input'], 400);
}

// Generate a secure token
$qrToken = bin2hex(random_bytes(QR_TOKEN_LENGTH));

// Build QR payload
$payload = [
    'type'  => 'kep_registration',
    'email' => $email,
    'id'    => $registrationId,
    'token' => $qrToken,
    'ts'    => time(),
    'ver'   => 1
];

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO qr_codes (registration_id, email, token, payload, expires_at, created_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
         ON DUPLICATE KEY UPDATE token=VALUES(token), payload=VALUES(payload),
                                  expires_at=VALUES(expires_at)'
    );
    $stmt->execute([$registrationId, $email, $qrToken, json_encode($payload), QR_TOKEN_EXPIRY]);
} catch (PDOException $e) {
    json_response(['error' => 'Unable to persist QR code. Please try again later.'], 503);
}

json_response([
    'success' => true,
    'payload' => $payload,
    'qr_url'  => QR_VERIFY_URL . '?token=' . urlencode($qrToken)
]);
