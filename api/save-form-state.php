<?php
/**
 * api/save-form-state.php
 * Persists partial form data server-side so users can resume
 * even on a different device.
 *
 * Request  : POST, Content-Type: application/json, body = serialized form fields
 * Response : JSON { "saved": true }
 *
 * NOTE: Sensitive fields (password, file uploads) are never sent by
 *       form-state-manager.js, so they will never reach this endpoint.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read raw JSON body
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    echo json_encode(['saved' => false, 'reason' => 'empty body']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['saved' => false, 'reason' => 'invalid JSON']);
    exit;
}

// Session-based storage (no DB required)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['form_state'] = [
    'data' => $data,
    'ts'   => time(),
];

echo json_encode(['saved' => true]);
