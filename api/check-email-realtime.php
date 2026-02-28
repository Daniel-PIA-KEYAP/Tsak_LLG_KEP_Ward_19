<?php
/**
 * Real-time email availability check endpoint
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Rate limiting
if (!rate_limit('email_check', EMAIL_CHECK_RATE_LIMIT)) {
    json_response(['error' => 'Too many requests', 'exists' => false], 429);
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    json_response(['exists' => false, 'valid' => false]);
}

try {
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = (int)$stmt->fetchColumn() > 0;
    json_response(['exists' => $exists, 'valid' => true]);
} catch (PDOException $e) {
    // If DB is unavailable, allow registration to proceed
    json_response(['exists' => false, 'valid' => true]);
}
