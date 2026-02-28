<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
</head>
<body style="background-color:#e6f2e6;">
<?php
declare(strict_types=1);

session_start();

// Guard: only reachable after a successful registration
// In production, verify a registration success flag stored in the session.
$email  = isset($_SESSION['reg_email'])  ? htmlspecialchars((string) $_SESSION['reg_email'],  ENT_QUOTES, 'UTF-8') : '';
$reg_id = isset($_SESSION['reg_id'])     ? htmlspecialchars((string) $_SESSION['reg_id'],     ENT_QUOTES, 'UTF-8') : '';
$name   = isset($_SESSION['reg_name'])   ? htmlspecialchars((string) $_SESSION['reg_name'],   ENT_QUOTES, 'UTF-8') : 'Registrant';

// Fallback for direct testing: accept GET params (remove in production)
if ($email === '' && isset($_GET['email'])) {
    $email = htmlspecialchars(filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ?: '', ENT_QUOTES, 'UTF-8');
}
if ($reg_id === '' && isset($_GET['reg_id'])) {
    $reg_id = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['reg_id']), ENT_QUOTES, 'UTF-8');
}

// Redirect back to registration if no data
if ($email === '' || $reg_id === '') {
    header('Location: index.html');
    exit;
}
?>

    <main class="confirm-card">
        <div class="confirm-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 class="confirm-title">Registration Successful!</h1>
        <p class="confirm-subtitle">
            Welcome, <strong><?php echo $name; ?></strong>. Your registration with
            <strong>KEP – Miok Kep Tribe of Tsak Valley</strong> has been received.
        </p>

        <p class="text-muted mb-4">
            Your unique QR code is displayed below. Save or print it — you can use it
            to quickly access your profile and log in.
        </p>

        <!-- QR Code Display -->
        <div class="qr-code-container mx-auto">
            <p class="qr-code-title">Your Registration QR Code</p>
            <p class="qr-code-subtitle">
                Scan this code to log in or view your registration details.
            </p>

            <div class="qr-code-wrapper" id="qr-wrapper">
                <div class="qr-loading" id="qr-loading">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Generating QR code…</span>
                    </div>
                    <span>Generating QR code…</span>
                </div>
            </div>

            <p class="qr-expiry-notice">
                <i class="fas fa-clock me-1"></i>
                This QR code is valid for 24 hours.
            </p>

            <div class="qr-code-actions mt-3">
                <button class="btn btn-success" id="btn-download">
                    <i class="fas fa-download me-1"></i> Download PNG
                </button>
                <button class="btn btn-outline-secondary" id="btn-print">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="qr-login.php" class="btn btn-primary">
                <i class="fas fa-qrcode me-1"></i> QR Login
            </a>
            <a href="index.html" class="btn btn-outline-secondary">
                <i class="fas fa-home me-1"></i> Home
            </a>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="js/qr-code-generator.js"></script>
    <script>
        (function () {
            var email  = <?php echo json_encode($email); ?>;
            var reg_id = <?php echo json_encode($reg_id); ?>;
            var wrapper = document.getElementById('qr-wrapper');
            var loading = document.getElementById('qr-loading');

            QRCodeManager.generateFromServer(wrapper, { email: email, reg_id: reg_id })
                .then(function (data) {
                    loading.remove();
                    document.getElementById('btn-download').addEventListener('click', function () {
                        QRCodeManager.download(wrapper, 'kep-qr-' + reg_id + '.png');
                    });
                    document.getElementById('btn-print').addEventListener('click', function () {
                        QRCodeManager.print(wrapper, 'KEP Ward 19 – ' + email);
                    });
                })
                .catch(function (err) {
                    loading.innerHTML = '<p class="text-danger">Could not generate QR code. Please try again later.</p>';
                    console.error(err);
                });
        }());
    </script>
</body>
</html>
