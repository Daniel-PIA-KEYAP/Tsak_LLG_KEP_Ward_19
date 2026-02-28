<?php
/**
 * Registration endpoint – validates form data, creates session and QR code reference.
 */
require_once __DIR__ . '/../config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

// CSRF validation
$csrfToken = $_POST['token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    header('Location: ../index.html?error=csrf');
    exit;
}

// Rate limiting
if (!rate_limit('register', 10)) {
    http_response_code(429);
    header('Location: ../index.html?error=ratelimit');
    exit;
}

// Sanitise / validate inputs
$firstName = trim($_POST['first_name'] ?? '');
$surname   = trim($_POST['surname']    ?? '');
$email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$mobile    = trim($_POST['mobile'] ?? '');
$password  = $_POST['password']  ?? '';
$cpassword = $_POST['cpassword'] ?? '';

// Required field checks
if (!$firstName || !$surname || !$email) {
    header('Location: ../index.html?error=missing_fields');
    exit;
}

// Phone number: must start with +675 followed by 7 or 8 digits
if (!preg_match('/^\+675[0-9]{7,8}$/', $mobile)) {
    header('Location: ../index.html?error=invalid_phone');
    exit;
}

// Password checks
if (strlen($password) < 8 || $password !== $cpassword) {
    header('Location: ../index.html?error=password');
    exit;
}

// Generate a unique registration ID
$registrationId = 'KEP-' . strtoupper(bin2hex(random_bytes(6)));

// Generate secure QR token
$qrToken = bin2hex(random_bytes(QR_TOKEN_LENGTH));

// Build QR payload (includes phone in PNG +675 format)
$qrPayload = [
    'type'   => 'kep_registration',
    'email'  => $email,
    'phone'  => $mobile,
    'id'     => $registrationId,
    'token'  => $qrToken,
    'ts'     => time(),
    'status' => 'registered',
    'ver'    => 1
];

// Persist QR code reference (non-fatal if DB is unavailable)
try {
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO qr_codes (registration_id, email, token, payload, expires_at, created_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
         ON DUPLICATE KEY UPDATE
             token      = IF(expires_at < NOW(), VALUES(token),      token),
             payload    = IF(expires_at < NOW(), VALUES(payload),    payload),
             expires_at = IF(expires_at < NOW(), VALUES(expires_at), expires_at)'
    );
    $stmt->execute([
        $registrationId,
        $email,
        $qrToken,
        json_encode($qrPayload),
        QR_TOKEN_EXPIRY
    ]);
} catch (PDOException $e) {
    // DB unavailable – continue without persisting; QR still generated from session
    error_log('register.php: DB unavailable – ' . $e->getMessage());
}

// Store registration data in session for the confirmation page
$_SESSION['registration'] = [
    'first_name'      => $firstName,
    'surname'         => $surname,
    'email'           => $email,
    'phone'           => $mobile,
    'registration_id' => $registrationId,
    'qr_token'        => $qrToken,
    'qr_payload'      => $qrPayload,
];

header('Location: ../register-confirm.php');
exit;
