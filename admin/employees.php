<?php
// ============================================================
//  Admin — Employee Management
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$employees = $sheets->getAll(SHEET_EMPLOYEES);
$tasks     = $sheets->getAll(SHEET_TASKS);
$users     = $sheets->getAll(SHEET_USERS);

// ── Handle Add/Edit Employee ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $empId       = generateId('EMP');
        $name        = trim($_POST['name']  ?? '');
        $designation = $_POST['designation'] ?? '';
        $email       = trim($_POST['email']  ?? '');
        $phone       = trim($_POST['phone']  ?? '');
        $photoUrl    = trim($_POST['photo_url'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (!$name || !$designation || !$email || !$username || !$password) {
            setFlash('danger', 'All fields are required.');
        } elseif ($sheets->findOne(SHEET_USERS, 'Username', $username)) {
            setFlash('danger', 'Username already exists.');
        } else {
            // Add to Employees sheet
            $sheets->appendRow(SHEET_EMPLOYEES, [$empId, $name, $designation, $email, $phone, $photoUrl, 'Active']);

            // Add to Users sheet
            $userId   = generateId('USR');
            $pwdHash  = password_hash($password, PASSWORD_BCRYPT);
            $sheets->appendRow(SHEET_USERS, [
                $userId, $username, $pwdHash, $designation, $empId, $email, $name, '', ''
            ]);

            setFlash('success', "Employee $name added successfully.");
        }
        redirect(BASE_URL . '/admin/employees.php');
    }

    if ($action === 'reset_password') {
        $userId   = $_POST['user_id'] ?? '';
        $newPwd   = $_POST['new_password'] ?? '';
        if ($userId && strlen($newPwd) >= 8) {
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $sheets->updateById(SHEET_USERS, 'User_ID', $userId, ['Password_Hash' => $hash]);
            setFlash('success', 'Password reset successfully.');
        } else {
            setFlash('danger', 'Password must be at least 8 characters.');
        }
        redirect(BASE_URL . '/admin/employees.php');
    }
}

// Build user lookup by employee_id
$userByEmp = [];
foreach ($users as $u) $userByEmp[$u['Employee_ID']] = $u;

$order = ['CEO','COO','Software Associate','Finance Associate','Innovation Associate','Supporting Staff'];
usort($employees, fn($a,$b) =>
    (array_search($a['Designation'],$order) ?: 99) <=> (array_search($b['Designation'],$order) ?: 99)
);

$pageTitle = 'Employee Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-700 mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Employee Management</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmpModal">
    <i class="bi bi-person-plus me-1"></i>Add Employee
  </button>
</div>

<div class="row g-3">
  <?php foreach ($employees as $emp):
    $stats    = employeeStats($tasks, $emp['Employee_ID']);
    $user     = $userByEmp[$emp['Employee_ID']] ?? null;
    $initials = substr(implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $emp['Name']))), 0, 2);
  ?>
  <div class="col-12 col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100" onclick="window.location='<?= BASE_URL ?>/admin/tasks.php?employee=<?= urlencode($emp['Employee_ID']) ?>'" style="cursor:pointer">
      <div class="card-body">

        <div class="d-flex align-items-center gap-3 mb-3">
          <?php if (!empty($emp['Photo_URL'])): ?>
            <img src="<?= e($emp['Photo_URL']) ?>" class="rounded-circle" width="60" height="60" style="object-fit:cover">
          <?php else: ?>
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-700"
                 style="width:60px;height:60px;background:linear-gradient(135deg,#0d2b5c,#1565c0);color:#fff;font-size:1.1rem">
              <?= e($initials) ?>
            </div>
          <?php endif; ?>
          <div>
            <div class="fw-700"><?= e($emp['Name']) ?></div>
            <div class="text-muted small"><?= e($emp['Designation']) ?></div>
            <div class="text-muted" style="font-size:.7rem"><?= e($emp['Email'] ?? '') ?></div>
          </div>
        </div>

        <div class="row g-2 text-center mb-3">
          <div class="col-3">
            <div class="fw-700 text-primary"><?= $stats['total'] ?></div>
            <div style="font-size:.65rem;color:#888">Total</div>
          </div>
          <div class="col-3">
            <div class="fw-700 text-success"><?= $stats['completed'] ?></div>
            <div style="font-size:.65rem;color:#888">Done</div>
          </div>
          <div class="col-3">
            <div class="fw-700 text-warning"><?= $stats['pending'] ?></div>
            <div style="font-size:.65rem;color:#888">Pending</div>
          </div>
          <div class="col-3">
            <div class="fw-700 text-danger"><?= $stats['overdue'] ?></div>
            <div style="font-size:.65rem;color:#888">Overdue</div>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between mb-1">
            <small class="text-muted">Approval Rate</small>
            <small class="fw-600"><?= $stats['approvalPct'] ?>%</small>
          </div>
          <div class="progress" style="height:5px">
            <div class="progress-bar bg-<?= $stats['approvalPct'] >= 70 ? 'success' : ($stats['approvalPct'] >= 40 ? 'warning' : 'danger') ?>"
                 style="width:<?= $stats['approvalPct'] ?>%"></div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <a href="<?= BASE_URL ?>/admin/tasks.php?employee=<?= urlencode($emp['Employee_ID']) ?>"
             class="btn btn-sm btn-outline-primary flex-fill">
            <i class="bi bi-list-task me-1"></i>Tasks
          </a>
          <?php if ($user): ?>
          <button class="btn btn-sm btn-outline-warning"
                  onclick="event.stopPropagation(); openResetPwd('<?= e($user['User_ID']) ?>','<?= e($emp['Name']) ?>')"
                  title="Reset Password">
            <i class="bi bi-key"></i>
          </button>
          <?php endif; ?>
        </div>

        <?php if ($user): ?>
        <div class="mt-2 text-muted" style="font-size:.7rem">
          <i class="bi bi-person-circle me-1"></i>Login: <code><?= e($user['Username']) ?></code>
        </div>
        <?php endif; ?>

      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Add Employee Modal ─────────────────────────────────────── -->
<div class="modal fade" id="addEmpModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Employee</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_employee">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-500">Full Name *</label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-500">Designation *</label>
              <select class="form-select" name="designation" required>
                <option value="">Select…</option>
                <?php foreach (DESIGNATIONS as $d): ?>
                <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-500">Email *</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-500">Phone</label>
              <input type="tel" class="form-control" name="phone" placeholder="+91 XXXXX XXXXX">
            </div>
            <div class="col-12">
              <label class="form-label fw-500">Photo URL</label>
              <input type="url" class="form-control" name="photo_url" placeholder="https://…">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-500">Username *</label>
              <input type="text" class="form-control" name="username" required autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-500">Password *</label>
              <input type="password" class="form-control" name="password" required minlength="8"
                     placeholder="Min 8 characters" autocomplete="new-password">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Reset Password Modal ───────────────────────────────────── -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content border-0">
      <div class="modal-header">
        <h6 class="modal-title fw-700">Reset Password</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="resetUserId">
        <div class="modal-body">
          <p class="small text-muted mb-2">Reset password for <strong id="resetEmpName"></strong></p>
          <input type="password" class="form-control" name="new_password" minlength="8" required placeholder="New password (min 8 chars)">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-warning">Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openResetPwd(userId, empName) {
  document.getElementById('resetUserId').value = userId;
  document.getElementById('resetEmpName').textContent = empName;
  new bootstrap.Modal(document.getElementById('resetPwdModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
