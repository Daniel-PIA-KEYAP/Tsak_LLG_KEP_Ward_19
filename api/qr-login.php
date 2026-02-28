<?php
/**
 * Handle QR code-based login
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true) ?: $_POST;

$rawToken = $input['token'] ?? '';
$token = preg_replace('/[^A-Za-z0-9]/', '', $rawToken);

if (strlen($token) < 16) {
    json_response(['success' => false, 'error' => 'Invalid token'], 400);
}

try {
    $pdo  = get_db();

    // Find QR code record
    $stmt = $pdo->prepare(
        'SELECT qr.*, u.id as user_id, u.first_name, u.surname, u.email as user_email
         FROM qr_codes qr
         LEFT JOIN users u ON qr.registration_id = u.registration_id
         WHERE qr.token = ? AND qr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(['success' => false, 'error' => 'Token not found or expired']);
    }

    // Log the user in via session
    $_SESSION['user_id']   = $record['user_id'];
    $_SESSION['user_email']= $record['user_email'];
    $_SESSION['user_name'] = $record['first_name'] . ' ' . $record['surname'];
    $_SESSION['qr_login']  = true;

    json_response([
        'success'   => true,
        'email'     => $record['user_email'],
        'name'      => $_SESSION['user_name'],
        'redirect'  => 'qr-verify.php?token=' . urlencode($token)
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'error' => 'Service unavailable'], 503);
}
