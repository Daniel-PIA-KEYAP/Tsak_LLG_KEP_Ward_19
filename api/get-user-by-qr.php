<?php
/**
 * Retrieve user data using QR code token
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$rawToken = $_GET['token'] ?? $_POST['token'] ?? '';
$token = preg_replace('/[^A-Za-z0-9]/', '', $rawToken);

if (strlen($token) < 16) {
    json_response(['error' => 'Invalid token'], 400);
}

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'SELECT u.first_name, u.surname, u.email, u.mobile, u.village, u.tribe,
                u.council_ward, u.district, u.province, u.nationality,
                u.marital_status, u.employed, u.registration_id,
                u.created_at, qr.expires_at
         FROM qr_codes qr
         JOIN users u ON qr.registration_id = u.registration_id
         WHERE qr.token = ? AND qr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['error' => 'Token not found or expired'], 404);
    }

    // Remove sensitive information
    unset($user['password_hash']);

    json_response(['success' => true, 'user' => $user]);
} catch (PDOException $e) {
    json_response(['error' => 'Service unavailable'], 503);
}
