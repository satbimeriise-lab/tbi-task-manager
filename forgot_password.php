<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

if (isLoggedIn()) redirect(BASE_URL . '/dashboard.php');

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            require_once __DIR__ . '/api/GoogleSheetsService.php';
            $sheets = new GoogleSheetsService();
            $user   = $sheets->findOne(SHEET_USERS, 'Email', $email);

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expiry  = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $sheets->updateById(SHEET_USERS, 'User_ID', $user['User_ID'], [
                    'Reset_Token'  => $token,
                    'Reset_Expiry' => $expiry,
                ]);

                $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                           . '://' . $_SERVER['HTTP_HOST']
                           . BASE_URL . '/reset_password.php?token=' . $token;

                // Send email via PHPMailer
                require_once __DIR__ . '/vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($email, $user['Name']);
                $mail->Subject = 'Password Reset — ' . APP_NAME;
                $mail->Body    = "Hello {$user['Name']},\n\nClick the link below to reset your password (valid 1 hour):\n\n$resetLink\n\n— TBI-MCE Task Manager";
                $mail->send();
            }

            // Always show success to prevent email enumeration
            $msg = 'If that email is registered, you will receive a reset link shortly.';
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'Could not send email. Please contact the administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
  body { background: linear-gradient(135deg,#0d2b5c,#1565c0); min-height:100vh; display:flex; align-items:center; }
  .card { max-width:420px; width:100%; margin:auto; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="card shadow-lg border-0 rounded-4 overflow-hidden" style="max-width:420px;margin:auto">
    <div class="card-header py-3 text-center" style="background:linear-gradient(135deg,#0d2b5c,#1565c0);color:#fff">
      <h5 class="mb-0 fw-700"><i class="bi bi-key me-2"></i>Forgot Password</h5>
    </div>
    <div class="card-body p-4">
      <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

      <p class="text-muted small mb-3">Enter your registered email address and we will send you a password reset link.</p>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" name="email" required placeholder="your@email.com">
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
      </form>

      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/index.php" class="small"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
