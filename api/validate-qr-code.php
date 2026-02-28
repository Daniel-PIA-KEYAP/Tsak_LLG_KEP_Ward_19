<?php
/**
 * QR code validation and authentication endpoint
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
    json_response(['valid' => false, 'error' => 'Invalid token format'], 400);
}

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM qr_codes WHERE token = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(['valid' => false, 'error' => 'Token not found or expired']);
    }

    $payload = json_decode($record['payload'], true) ?? [];
    json_response(['valid' => true, 'payload' => $payload, 'email' => $record['email']]);
} catch (PDOException $e) {
    json_response(['valid' => false, 'error' => 'Service unavailable'], 503);
}
