<?php
/**
 * Registration Confirmation Page with QR Code
 */
require_once __DIR__ . '/config.php';

// Validate that we have registration data in the session
if (empty($_SESSION['registration'])) {
    header('Location: index.php');
    exit;
}

$reg   = $_SESSION['registration'];
$csrf  = generate_csrf_token();
$email = htmlspecialchars($reg['email'] ?? '', ENT_QUOTES, 'UTF-8');
$regId = htmlspecialchars($reg['registration_id'] ?? '', ENT_QUOTES, 'UTF-8');
$name  = htmlspecialchars(($reg['first_name'] ?? '') . ' ' . ($reg['surname'] ?? ''), ENT_QUOTES, 'UTF-8');

// Build QR payload (token will be generated client-side via API)
$qrPayload = json_encode([
    'email' => $reg['email'] ?? '',
    'id'    => $reg['registration_id'] ?? '',
    'csrf'  => $csrf
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
    <style>body { background: #e6f2e6; padding: 30px 15px; }</style>
</head>
<body>
<div class="container">
    <div class="confirm-card">
        <div class="confirm-header">
            <div class="success-icon">✓</div>
            <h3 class="mb-1">Registration Successful!</h3>
            <p class="mb-0 opacity-75">Welcome to KEP Ward 19, <?php echo $name; ?></p>
        </div>
        <div class="confirm-body">
            <p class="text-muted">Your registration has been submitted successfully. Please save your QR code for future access.</p>

            <div class="mb-3">
                <label class="form-label fw-semibold">Registration ID</label>
                <div class="reg-id-display"><?php echo $regId; ?></div>
            </div>

            <!-- QR Code Section -->
            <div class="qr-code-container">
                <h5 class="mb-3"><i class="fa fa-qrcode me-2"></i>Your QR Code</h5>
                <p class="text-muted small">Use this QR code to quickly access your profile and log in.</p>
                <div class="qr-code-wrapper">
                    <div id="qr-code-display"></div>
                </div>
                <div class="qr-code-actions">
                    <button class="btn btn-outline-primary" onclick="QRCodeGenerator.download('qr-code-display', 'kep-qr-<?php echo $regId; ?>.png')">
                        <i class="fa fa-download"></i>Download
                    </button>
                    <button class="btn btn-outline-secondary" onclick="QRCodeGenerator.print('qr-code-display')">
                        <i class="fa fa-print"></i>Print
                    </button>
                </div>
            </div>

            <!-- Hidden payload data -->
            <div id="qr-payload" data-payload="<?php echo htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;"></div>

            <hr>
            <div class="d-flex gap-2 flex-wrap justify-content-center">
                <a href="login.php"    class="btn btn-primary"><i class="fa fa-sign-in-alt me-1"></i>Login Now</a>
                <a href="qr-login.php" class="btn btn-outline-primary"><i class="fa fa-qrcode me-1"></i>QR Login</a>
                <a href="index.php"    class="btn btn-outline-secondary"><i class="fa fa-home me-1"></i>Home</a>
            </div>
        </div>
    </div>
</div>

<!-- QR Code library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="js/qr-code-generator.js"></script>
<script>
    QRCodeGenerator.initConfirmationPage();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
