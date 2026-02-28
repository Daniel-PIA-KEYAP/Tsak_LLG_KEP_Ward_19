<?php
/**
 * QR Code Verification and Data Access Page
 */
require_once __DIR__ . '/config.php';

$rawToken = $_GET['token'] ?? '';
$token = preg_replace('/[^A-Za-z0-9]/', '', $rawToken);
$user = null;
$error = null;

if ($token) {
    try {
        $pdo  = get_db();
        $stmt = $pdo->prepare(
            'SELECT u.first_name, u.surname, u.email, u.mobile, u.village, u.tribe,
                    u.council_ward, u.district, u.province, u.nationality,
                    u.marital_status, u.employed, u.registration_id, u.created_at
             FROM qr_codes qr
             JOIN users u ON qr.registration_id = u.registration_id
             WHERE qr.token = ? AND qr.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if (!$user) $error = 'QR code is invalid or has expired.';
    } catch (PDOException $e) {
        $error = 'Service temporarily unavailable. Please try again later.';
    }
} else {
    $error = 'No QR token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Verification - KEP Ward 19</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/qr-code-ui.css">
    <style>body { background: #e6f2e6; padding: 30px 15px; }</style>
</head>
<body>
<div class="container">
<?php if ($error): ?>
    <div class="alert alert-danger text-center mt-5">
        <i class="fa fa-exclamation-circle fa-2x mb-3 d-block"></i>
        <h5><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></h5>
        <a href="qr-login.php" class="btn btn-outline-danger mt-2">Try Again</a>
        <a href="index.php"    class="btn btn-outline-secondary mt-2 ms-2">Register</a>
    </div>
<?php else: ?>
    <div class="user-profile-card">
        <div class="user-profile-header">
            <img src="https://via.placeholder.com/80?text=<?php echo urlencode(substr($user['first_name'],0,1).substr($user['surname'],0,1)); ?>"
                 alt="Profile" class="user-profile-avatar">
            <div class="user-profile-info">
                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['surname'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="verified-badge"><i class="fa fa-check-circle me-1"></i>Verified Member</span>
            </div>
        </div>

        <h6 class="text-muted mb-3">Registration Details</h6>
        <?php
        $fields = [
            'Registration ID' => $user['registration_id'],
            'Village'         => $user['village'],
            'Tribe'           => $user['tribe'],
            'Council Ward'    => $user['council_ward'],
            'District'        => $user['district'],
            'Province'        => $user['province'],
            'Nationality'     => $user['nationality'],
            'Marital Status'  => ucfirst($user['marital_status']),
            'Employment'      => ucfirst($user['employed']),
            'Registered On'   => date('d M Y', strtotime($user['created_at']))
        ];
        foreach ($fields as $label => $value): ?>
        <div class="profile-detail-row">
            <span class="profile-detail-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="profile-detail-value"><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endforeach; ?>

        <div class="mt-4 d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary"><i class="fa fa-home me-1"></i>Home</a>
            <a href="qr-login.php" class="btn btn-outline-primary"><i class="fa fa-qrcode me-1"></i>Scan Another</a>
        </div>
    </div>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
