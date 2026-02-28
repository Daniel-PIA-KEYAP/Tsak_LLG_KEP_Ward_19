<?php
/**
 * QR Code Login Page
 */
require_once __DIR__ . '/config.php';
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Login - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
    <style>
        body { background: #e6f2e6; padding: 20px; }
    </style>
</head>
<body>
<div class="qr-login-card">
    <div class="qr-login-header">
        <i class="fa fa-qrcode d-block"></i>
        <h3 class="mb-1">QR Code Login</h3>
        <p class="mb-0 opacity-75">Scan your QR code or upload it to log in</p>
    </div>
    <div class="qr-login-body">
        <div id="alert-area"></div>

        <!-- Tabs -->
        <div class="qr-tabs" role="tablist">
            <button class="qr-tab active" onclick="switchTab('camera')" role="tab" aria-selected="true">
                <i class="fa fa-camera me-1"></i>Camera
            </button>
            <button class="qr-tab" onclick="switchTab('upload')" role="tab" aria-selected="false">
                <i class="fa fa-upload me-1"></i>Upload
            </button>
            <button class="qr-tab" onclick="switchTab('manual')" role="tab" aria-selected="false">
                <i class="fa fa-keyboard me-1"></i>Manual
            </button>
        </div>

        <!-- Camera Tab -->
        <div id="tab-camera" class="qr-tab-pane active">
            <div class="qr-scanner-container mb-3">
                <video id="qr-video" playsinline></video>
                <canvas id="qr-canvas" style="display:none;"></canvas>
                <div class="qr-scan-overlay">
                    <div class="qr-scan-line"></div>
                </div>
            </div>
            <button class="btn btn-primary w-100" onclick="startCamera()">
                <i class="fa fa-camera me-2"></i>Start Camera Scan
            </button>
        </div>

        <!-- Upload Tab -->
        <div id="tab-upload" class="qr-tab-pane">
            <div class="mb-3">
                <label for="qr-file-input" class="form-label">Upload QR Code Image</label>
                <input type="file" id="qr-file-input" class="form-control" accept="image/*">
            </div>
            <button class="btn btn-primary w-100" onclick="scanFromFile()">
                <i class="fa fa-search me-2"></i>Scan Image
            </button>
        </div>

        <!-- Manual Tab -->
        <div id="tab-manual" class="qr-tab-pane">
            <div class="mb-3">
                <label for="qr-token-input" class="form-label">Enter QR Token</label>
                <input type="text" id="qr-token-input" class="form-control" placeholder="Paste your QR token here">
            </div>
            <button class="btn btn-primary w-100" onclick="loginWithToken()">
                <i class="fa fa-sign-in-alt me-2"></i>Login
            </button>
        </div>

        <hr>
        <p class="text-center text-muted small">
            Don't have a QR code? <a href="index.php">Register here</a> or <a href="login.php">Login with password</a>
        </p>
    </div>
</div>

<!-- jsQR for QR scanning -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="js/qr-code-generator.js"></script>
<script>
const CSRF_TOKEN = <?php echo json_encode($csrf); ?>;

function switchTab(name) {
    document.querySelectorAll('.qr-tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.qr-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
    document.getElementById('tab-' + name).classList.add('active');
    const tabs = document.querySelectorAll('.qr-tab');
    const names = ['camera','upload','manual'];
    tabs[names.indexOf(name)].classList.add('active');
    tabs[names.indexOf(name)].setAttribute('aria-selected','true');
}

function showAlert(msg, type = 'danger') {
    document.getElementById('alert-area').innerHTML =
        `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
           ${msg}
           <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
         </div>`;
}

function startCamera() {
    QRCodeGenerator.startScanner('qr-video', 'qr-canvas', handleQRData);
}

function scanFromFile() {
    const file = document.getElementById('qr-file-input').files[0];
    if (!file) { showAlert('Please select a QR code image.'); return; }
    QRCodeGenerator.scanFromFile(file, handleQRData);
}

function loginWithToken() {
    const token = document.getElementById('qr-token-input').value.trim();
    if (!token) { showAlert('Please enter your QR token.'); return; }
    handleQRData(token);
}

async function handleQRData(rawData) {
    if (!rawData) { showAlert('Could not read QR code. Please try again.'); return; }
    let token = rawData;
    try {
        const parsed = JSON.parse(rawData);
        token = parsed.token || rawData;
    } catch { /* raw token */ }

    try {
        const resp = await fetch('api/qr-login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, csrf_token: CSRF_TOKEN })
        });
        const data = await resp.json();
        if (data.success) {
            showAlert(`Welcome, ${data.name || data.email}! Redirecting...`, 'success');
            setTimeout(() => { window.location.href = data.redirect || 'qr-verify.php?token=' + encodeURIComponent(token); }, 1500);
        } else {
            showAlert(data.error || 'Login failed. Please try again.');
        }
    } catch (e) {
        showAlert('Network error. Please try again.');
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
