<?php
/**
 * Form state persistence endpoint
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    json_response(['error' => 'Invalid JSON'], 400);
}

// Remove sensitive fields before persistence
unset($data['password'], $data['cpassword'], $data['token']);

// Sanitize all values
array_walk_recursive($data, function(&$v) {
    $v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
});

// Store in session (server-side, no DB required)
$_SESSION['form_state'] = [
    'data' => $data,
    'ts'   => time()
];

json_response(['saved' => true, 'ts' => time()]);
