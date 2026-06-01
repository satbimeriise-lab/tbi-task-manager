<?php
// ============================================================
//  Login Page
// ============================================================
require_once __DIR__ . '/includes/functions.php';
startSession();

if (isLoggedIn()) {
    redirect(BASE_URL . (isAdmin() ? '/admin/dashboard.php' : '/employee/dashboard.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter username and password.';
    } else {
        try {
            require_once __DIR__ . '/api/GoogleSheetsService.php';
            $sheets = new GoogleSheetsService();
            $user   = $sheets->findOne(SHEET_USERS, 'Username', $username);

            if ($user && password_verify($password, $user['Password_Hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['User_ID'];
                $_SESSION['username']    = $user['Username'];
                $_SESSION['name']        = $user['Name'];
                $_SESSION['designation'] = $user['Designation'];
                $_SESSION['employee_id'] = $user['Employee_ID'];
                $_SESSION['email']       = $user['Email'];
                $_SESSION['_last_active'] = time();

                $dest = $_GET['redirect'] ?? (in_array($user['Designation'], ADMIN_ROLES)
                    ? BASE_URL . '/admin/dashboard.php'
                    : BASE_URL . '/employee/dashboard.php');
                redirect($dest);
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'System error. Please check Google Sheets configuration.';
            error_log($e->getMessage());
        }
    }
}

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
  body { background: linear-gradient(135deg, #0d2b5c 0%, #1565c0 60%, #1976d2 100%); min-height:100vh; display:flex; align-items:center; }
  .login-card { max-width:440px; width:100%; margin:auto; }
  .login-header { background: linear-gradient(135deg,#0d2b5c,#1565c0); color:#fff; border-radius:16px 16px 0 0; padding:2rem; text-align:center; }
  .login-body { background:#fff; border-radius:0 0 16px 16px; padding:2rem; }
  [data-bs-theme="dark"] .login-body { background:#1e2330; }
  .login-logo { width:80px; height:80px; object-fit:contain; filter:drop-shadow(0 4px 8px rgba(0,0,0,.3)); }
</style>
</head>
<body>

<div class="container py-4">
  <div class="login-card shadow-lg">

    <div class="login-header">
      <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="TBI-MCE Logo" class="login-logo mb-3"
           onerror="this.style.display='none'">
      <h4 class="fw-700 mb-0">TBI – MCE Hassan</h4>
      <div class="opacity-75 small mt-1">Technology Business Incubator</div>
      <div class="opacity-60" style="font-size:.75rem">Malnad College of Engineering</div>
    </div>

    <div class="login-body">
      <h5 class="text-center mb-4 fw-600">Sign In to Task Manager</h5>

      <?php if ($timeout): ?>
        <div class="alert alert-warning py-2"><i class="bi bi-clock me-1"></i>Session expired. Please login again.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="mb-3">
          <label class="form-label fw-500">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   placeholder="Enter your username" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-500">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" name="password"
                   placeholder="Enter your password" required id="pwdField">
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd()">
              <i class="bi bi-eye" id="pwdEye"></i>
            </button>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rememberMe">
            <label class="form-check-label small" for="rememberMe">Remember me</label>
          </div>
          <a href="<?= BASE_URL ?>/forgot_password.php" class="small text-primary">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <hr class="my-4">
      <div class="text-center text-muted small">
        <i class="bi bi-shield-check me-1 text-success"></i>Secure login protected by session encryption
      </div>

      <!-- Demo credentials hint (remove in production) -->
      <div class="alert alert-info mt-3 py-2 small">
        <strong>Login Credentials:</strong><br>
        Admin (CEO): <code>geetha</code> / <code>Admin@123</code><br>
        Admin (COO): <code>mohana</code> / <code>Admin@123</code><br>
        Software: <code>darshan</code> / <code>Employee@123</code><br>
        Finance: <code>ramya</code> / <code>Employee@123</code>
      </div>
    </div>
  </div>
</div>

<button class="btn btn-sm btn-outline-light position-fixed bottom-0 end-0 m-3" id="darkToggle">
  <i class="bi bi-moon-fill"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function togglePwd() {
  const f = document.getElementById('pwdField');
  const e = document.getElementById('pwdEye');
  if (f.type === 'password') { f.type = 'text'; e.className = 'bi bi-eye-slash'; }
  else { f.type = 'password'; e.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
