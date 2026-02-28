<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Verification - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
</head>
<body style="background-color:#e6f2e6;">
<?php
declare(strict_types=1);

session_start();

// Require QR-authenticated session
if (empty($_SESSION['qr_authenticated'])) {
    header('Location: qr-login.php?msg=auth_required');
    exit;
}

// Validate token from query string
$token = '';
if (isset($_GET['token'])) {
    $candidate = trim((string) $_GET['token']);
    if (preg_match('/^[a-f0-9]{64}$/', $candidate)) {
        $token = $candidate;
    }
}

if ($token === '') {
    header('Location: qr-login.php');
    exit;
}

$tokenHtml = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
?>

    <div class="qr-verify-card">
        <h1 style="font-size:1.5rem;font-weight:700;color:#333;" class="mb-1">
            <i class="fas fa-id-card me-2 text-success"></i>Registration Profile
        </h1>
        <p class="text-muted mb-4" style="font-size:0.9rem;">
            Your identity has been verified via QR code.
        </p>

        <div id="alert-box" class="alert d-none" role="alert"></div>

        <!-- Profile placeholder; populated by JS -->
        <div id="profile-loading" class="qr-loading">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading profile…</span>
            </div>
            <span>Loading your registration details…</span>
        </div>

        <div id="profile-content" class="d-none">
            <!-- Verification status badge -->
            <div class="mb-3" id="verification-badge"></div>

            <table class="table table-bordered user-info-table">
                <tbody id="profile-table-body">
                    <!-- Rows inserted by JS -->
                </tbody>
            </table>

            <!-- Re-generate QR section -->
            <div class="mt-4 text-center">
                <p class="text-muted small mb-2">
                    Your current QR code is valid until:
                    <strong id="expires-at-label">—</strong>
                </p>
                <div class="qr-code-container mx-auto">
                    <p class="qr-code-title">Your QR Code</p>
                    <div class="qr-code-wrapper" id="qr-wrapper"></div>
                    <div class="qr-code-actions">
                        <button class="btn btn-success btn-sm" id="btn-download">
                            <i class="fas fa-download me-1"></i> Download
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="btn-print">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="qr-login.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-qrcode me-1"></i> Back to QR Login
            </a>
            <a href="index.html" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-home me-1"></i> Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="js/qr-code-generator.js"></script>
    <script>
    (function () {
        'use strict';

        var token       = <?php echo json_encode($tokenHtml); ?>;
        var alertBox    = document.getElementById('alert-box');
        var loading     = document.getElementById('profile-loading');
        var content     = document.getElementById('profile-content');
        var tableBody   = document.getElementById('profile-table-body');
        var badgeEl     = document.getElementById('verification-badge');
        var expiresEl   = document.getElementById('expires-at-label');
        var qrWrapper   = document.getElementById('qr-wrapper');

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = 'alert alert-' + (type || 'danger');
            alertBox.classList.remove('d-none');
        }

        function addRow(label, value) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<th scope="row">' + label + '</th><td>' + value + '</td>';
            tableBody.appendChild(tr);
        }

        function formatDate(iso) {
            try { return new Date(iso).toLocaleString(); } catch (e) { return iso; }
        }

        // Fetch user profile
        QRCodeManager.getUserByToken(token)
            .then(function (data) {
                loading.classList.add('d-none');

                if (!data.success) {
                    showAlert(data.message || 'Could not load profile.', 'danger');
                    return;
                }

                var user = data.user;
                content.classList.remove('d-none');

                // Badge
                if (user.verified) {
                    badgeEl.innerHTML = '<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified Registration</span>';
                } else {
                    badgeEl.innerHTML = '<span class="unverified-badge"><i class="fas fa-clock"></i> Verification Pending</span>';
                }

                // Table rows
                addRow('Email', user.email || '—');
                addRow('Registration ID', '<code>' + (user.reg_id || '—') + '</code>');
                addRow('Verification Status', user.verified ? '<span class="text-success fw-semibold">Verified</span>' : '<span class="text-warning fw-semibold">Pending</span>');
                addRow('Registered At', formatDate(user.registered_at));

                expiresEl.textContent = formatDate(user.expires_at);

                // Render QR code
                QRCodeManager.generate(qrWrapper, {
                    email:     user.email,
                    reg_id:    user.reg_id,
                    token:     token,
                    timestamp: Math.floor(Date.now() / 1000),
                    verified:  user.verified
                })
                .then(function () {
                    document.getElementById('btn-download').addEventListener('click', function () {
                        QRCodeManager.download(qrWrapper, 'kep-qr-' + user.reg_id + '.png');
                    });
                    document.getElementById('btn-print').addEventListener('click', function () {
                        QRCodeManager.print(qrWrapper, 'KEP Ward 19 – ' + user.email);
                    });
                });
            })
            .catch(function (err) {
                loading.classList.add('d-none');
                showAlert('Network error. Please try again.', 'danger');
                console.error(err);
            });

    }());
    </script>
</body>
</html>
