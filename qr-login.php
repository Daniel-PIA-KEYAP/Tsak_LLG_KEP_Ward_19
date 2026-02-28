<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Login - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
</head>
<body style="background-color:#e6f2e6;">
<?php
declare(strict_types=1);
session_start();

// If a QR-prefilled email is passed via GET, pass it to JS safely
$prefillEmail = '';
if (isset($_GET['email'])) {
    $candidate = filter_var(trim($_GET['email']), FILTER_VALIDATE_EMAIL);
    if ($candidate !== false) {
        $prefillEmail = $candidate;
    }
}

// Show a message when redirected due to missing authentication
$authMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'auth_required') {
    $authMsg = 'Please scan your QR code to log in and view your registration details.';
}
?>

    <div class="qr-login-card">
        <h1 class="text-center mb-1" style="font-size:1.5rem;font-weight:700;color:#333;">
            <i class="fas fa-qrcode me-2 text-success"></i>KEP Ward 19 Login
        </h1>
        <p class="text-center text-muted mb-4" style="font-size:0.9rem;">
            Scan your QR code or enter your credentials below.
        </p>

        <div id="alert-box" class="alert d-none" role="alert"></div>
        <?php if ($authMsg !== ''): ?>
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle me-1"></i>
            <?php echo htmlspecialchars($authMsg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <!-- Tab navigation -->
        <ul class="nav nav-tabs qr-login-tabs mb-4" id="loginTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-qr" data-bs-toggle="tab"
                        data-bs-target="#panel-qr" type="button" role="tab"
                        aria-controls="panel-qr" aria-selected="true">
                    <i class="fas fa-qrcode me-1"></i> Scan QR
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-manual" data-bs-toggle="tab"
                        data-bs-target="#panel-manual" type="button" role="tab"
                        aria-controls="panel-manual" aria-selected="false">
                    <i class="fas fa-keyboard me-1"></i> Manual Login
                </button>
            </li>
        </ul>

        <div class="tab-content" id="loginTabContent">

            <!-- QR Scan Panel -->
            <div class="tab-pane fade show active" id="panel-qr" role="tabpanel" aria-labelledby="tab-qr">
                <p class="text-muted small mb-3">
                    Point your camera at your KEP registration QR code.
                </p>

                <div class="qr-scanner-container" id="qr-scanner-container">
                    <video id="qr-video" playsinline></video>
                    <div class="qr-scanner-overlay">
                        <div class="qr-scanner-frame">
                            <div class="qr-scan-line"></div>
                        </div>
                    </div>
                </div>

                <p class="qr-scanner-status" id="qr-scan-status">Initializing camera…</p>

                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-success btn-sm" id="btn-start-scan">
                        <i class="fas fa-play me-1"></i> Start Scan
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btn-stop-scan" disabled>
                        <i class="fas fa-stop me-1"></i> Stop
                    </button>
                </div>
            </div>

            <!-- Manual Login Panel -->
            <div class="tab-pane fade" id="panel-manual" role="tabpanel" aria-labelledby="tab-manual">
                <form id="manual-login-form" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="your@email.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="qr-token-input" class="form-label fw-semibold">QR Token</label>
                        <input type="text" id="qr-token-input" name="token" class="form-control"
                               placeholder="Paste your QR token here" autocomplete="off">
                        <div class="form-text">
                            Found on your registration confirmation page or QR code data.
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-1"></i> Login with QR Token
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <hr class="my-4">
        <div class="text-center">
            <a href="index.html" class="text-muted small">
                <i class="fas fa-arrow-left me-1"></i> Back to Registration
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- jsQR for QR scanning -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="js/qr-code-generator.js"></script>
    <script>
    (function () {
        'use strict';

        var alertBox    = document.getElementById('alert-box');
        var scanStatus  = document.getElementById('qr-scan-status');
        var btnStart    = document.getElementById('btn-start-scan');
        var btnStop     = document.getElementById('btn-stop-scan');
        var video       = document.getElementById('qr-video');
        var manualForm  = document.getElementById('manual-login-form');

        var stream      = null;
        var scanning    = false;
        var scanCanvas  = document.createElement('canvas');
        var scanCtx     = scanCanvas.getContext('2d');
        var animFrame   = null;

        // ---- Alert helper ---------------------------------------------------
        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = 'alert alert-' + (type || 'danger');
            alertBox.classList.remove('d-none');
        }

        // ---- QR Login flow --------------------------------------------------
        function handleQrLogin(token) {
            setScanStatus('QR code detected. Logging in…', '');
            QRCodeManager.login(token)
                .then(function (data) {
                    if (data.success) {
                        setScanStatus('Login successful! Redirecting…', 'success');
                        stopScan();
                        window.location.href = data.redirect;
                    } else {
                        setScanStatus(data.message || 'Login failed.', 'error');
                        showAlert(data.message || 'Login failed. Please try again.', 'danger');
                    }
                })
                .catch(function () {
                    setScanStatus('Network error. Please try again.', 'error');
                    showAlert('Network error. Please try again.', 'danger');
                });
        }

        function setScanStatus(msg, cls) {
            scanStatus.textContent = msg;
            scanStatus.className = 'qr-scanner-status' + (cls ? ' ' + cls : '');
        }

        // ---- Camera scanning ------------------------------------------------
        function startScan() {
            setScanStatus('Requesting camera access…', '');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function (s) {
                    stream    = s;
                    scanning  = true;
                    video.srcObject = s;
                    video.play();
                    btnStart.disabled = true;
                    btnStop.disabled  = false;
                    setScanStatus('Scanning… point camera at your QR code.', '');
                    requestAnimationFrame(scanFrame);
                })
                .catch(function (err) {
                    setScanStatus('Camera access denied or unavailable.', 'error');
                    console.error(err);
                });
        }

        function stopScan() {
            scanning = false;
            if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            video.srcObject = null;
            btnStart.disabled = false;
            btnStop.disabled  = true;
            setScanStatus('Scan stopped.', '');
        }

        function scanFrame() {
            if (!scanning) { return; }
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                scanCanvas.width  = video.videoWidth;
                scanCanvas.height = video.videoHeight;
                scanCtx.drawImage(video, 0, 0, scanCanvas.width, scanCanvas.height);
                var imageData = scanCtx.getImageData(0, 0, scanCanvas.width, scanCanvas.height);
                var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                if (code) {
                    var payload = QRCodeManager.parsePayload(code.data);
                    if (payload && payload.token) {
                        scanning = false;    // Pause scanning while we process
                        handleQrLogin(payload.token);
                        return;
                    }
                }
            }
            animFrame = requestAnimationFrame(scanFrame);
        }

        btnStart.addEventListener('click', startScan);
        btnStop.addEventListener('click', stopScan);

        // ---- Manual login form ----------------------------------------------
        manualForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var token = document.getElementById('qr-token-input').value.trim();
            if (!token) {
                showAlert('Please enter a QR token.', 'warning');
                return;
            }
            handleQrLogin(token);
        });

        // ---- Pre-fill email from URL (QR redirect) --------------------------
        QRCodeManager.prefillEmailFromUrl(document.getElementById('email'));

    }());
    </script>
</body>
</html>
