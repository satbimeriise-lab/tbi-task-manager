<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

require_once __DIR__ . '/api/GoogleSheetsService.php';
$sheets = new GoogleSheetsService();

// Validate token
$user = null;
if ($token) {
    $all = $sheets->getAll(SHEET_USERS);
    foreach ($all as $u) {
        if (($u['Reset_Token'] ?? '') === $token) {
            $expiry = $u['Reset_Expiry'] ?? '';
            if ($expiry && strtotime($expiry) > time()) {
                $user = $u;
            }
            break;
        }
    }
}

if (!$token || !$user) {
    $error = 'Invalid or expired reset link. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $pwd1 = $_POST['password']  ?? '';
    $pwd2 = $_POST['password2'] ?? '';

    if (strlen($pwd1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pwd1 !== $pwd2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pwd1, PASSWORD_BCRYPT);
        $sheets->updateById(SHEET_USERS, 'User_ID', $user['User_ID'], [
            'Password_Hash' => $hash,
            'Reset_Token'   => '',
            'Reset_Expiry'  => '',
        ]);
        $success = 'Password updated successfully! You can now login.';
        $user = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#0d2b5c,#1565c0);min-height:100vh;display:flex;align-items:center}</style>
</head>
<body>
<div class="container py-4">
  <div class="card shadow-lg border-0 rounded-4 overflow-hidden" style="max-width:420px;margin:auto">
    <div class="card-header py-3 text-center" style="background:linear-gradient(135deg,#0d2b5c,#1565c0);color:#fff">
      <h5 class="mb-0 fw-700"><i class="bi bi-shield-lock me-2"></i>Reset Password</h5>
    </div>
    <div class="card-body p-4">
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary w-100">Go to Login</a>
      <?php elseif ($user): ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" name="password" minlength="8" required placeholder="Min 8 characters">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" class="form-control" name="password2" minlength="8" required placeholder="Repeat password">
          </div>
          <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-primary w-100">Request New Reset Link</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
