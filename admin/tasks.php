<?php
// ============================================================
//  Admin — Task List with Search/Filter
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$tasks     = $sheets->getAll(SHEET_TASKS);
$employees = $sheets->getAll(SHEET_EMPLOYEES);

// Build employee lookup
$empMap = [];
foreach ($employees as $e) $empMap[$e['Employee_ID']] = $e;

// ── Delete task ───────────────────────────────────────────────
if (isset($_GET['delete']) && $_GET['delete']) {
    $sheets->deleteById(SHEET_TASKS, 'Task_ID', $_GET['delete']);
    setFlash('success', 'Task deleted.');
    $redirect = BASE_URL . '/admin/tasks.php';
    if (!empty($_GET['employee'])) {
        $redirect .= '?employee=' . urlencode($_GET['employee']);
    }
    redirect($redirect);
}

// ── Update task status ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task_status') {
    verifyCsrf();
    $taskId    = $_POST['task_id'] ?? '';
    $newStatus = $_POST['status']  ?? '';

    if ($taskId && in_array($newStatus, ['Pending', 'In Progress', 'Completed'], true)) {
        $sheets->updateById(SHEET_TASKS, 'Task_ID', $taskId, ['Status' => $newStatus]);
        setFlash('success', 'Task status updated to ' . e($newStatus) . '.');
    }

    redirect(BASE_URL . '/admin/tasks.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ── Filter params ─────────────────────────────────────────────
$fEmployee = $_GET['employee'] ?? '';
$fStatus   = $_GET['status']   ?? '';
$fPriority = $_GET['priority'] ?? '';
$fSearch   = trim($_GET['q']   ?? '');
$fDate     = $_GET['date']     ?? '';

// Apply filters
$filtered = array_filter($tasks, function($t) use ($fEmployee, $fStatus, $fPriority, $fSearch, $fDate) {
    if ($fEmployee && $t['Employee_ID'] !== $fEmployee) return false;
    if ($fStatus   && $t['Status']      !== $fStatus)   return false;
    if ($fPriority && $t['Priority']    !== $fPriority) return false;
    if ($fDate     && substr($t['Assigned_Date'] ?? '', 0, 10) !== $fDate) return false;
    if ($fSearch) {
        $hay = strtolower($t['Task_Title'] . ' ' . $t['Description']);
        if (strpos($hay, strtolower($fSearch)) === false) return false;
    }
    return true;
});
$filtered = array_values($filtered);

$pageTitle = 'Task Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-700 mb-0"><i class="bi bi-list-task me-2 text-primary"></i>Task Management</h4>
  <a href="<?= BASE_URL ?>/admin/create_task.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Create Task
  </a>
</div>

<!-- ── Filters ────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-lg-3">
        <input type="text" class="form-control" name="q" placeholder="Search tasks…" value="<?= e($fSearch) ?>">
      </div>
      <div class="col-6 col-sm-4 col-lg-2">
        <select class="form-select" name="employee">
          <option value="">All Employees</option>
          <?php foreach ($employees as $emp): ?>
          <option value="<?= e($emp['Employee_ID']) ?>" <?= $fEmployee === $emp['Employee_ID'] ? 'selected' : '' ?>>
            <?= e($emp['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-lg-2">
        <select class="form-select" name="status">
          <option value="">All Status</option>
          <?php foreach (TASK_STATUSES as $s): ?>
          <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-lg-2">
        <select class="form-select" name="priority">
          <option value="">All Priority</option>
          <?php foreach (PRIORITIES as $p): ?>
          <option value="<?= $p ?>" <?= $fPriority === $p ? 'selected' : '' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-lg-2">
        <input type="date" class="form-control" name="date" value="<?= e($fDate) ?>">
      </div>
      <div class="col-12 col-sm-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-outline-secondary">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php if ($fEmployee && isset($empMap[$fEmployee])): ?>
<div class="alert alert-primary bg-opacity-10 border-primary mb-4">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="fw-700 mb-1">Viewing tasks for <?= e($empMap[$fEmployee]['Name']) ?></div>
      <div class="small text-muted">
        <?= e($empMap[$fEmployee]['Designation']) ?>
        <?php if (!empty($empMap[$fEmployee]['Email'])): ?>· <?= e($empMap[$fEmployee]['Email']) ?><?php endif; ?>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-sm btn-outline-primary">View all tasks</a>
  </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <small class="text-muted">Showing <?= count($filtered) ?> of <?= count($tasks) ?> tasks</small>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-outline-success" onclick="exportTableExcel('taskTable','TBI_Tasks')">
      <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </button>
    <button class="btn btn-sm btn-outline-danger" onclick="exportTablePDF('taskTable','Task Report')">
      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
    </button>
  </div>
</div>

<!-- ── Task Table ─────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="taskTable">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Task Title</th>
            <th>Employee</th>
            <th>Priority</th>
            <th>Assigned</th>
            <th>Deadline</th>
            <th>Days Pending</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $i => $t):
            $emp     = $empMap[$t['Employee_ID']] ?? null;
            $overdue = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
            $days    = daysPending($t['Deadline'] ?? '');
          ?>
          <tr class="<?= $overdue ? 'table-danger' : '' ?>">
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="fw-500"><?= e($t['Task_Title']) ?></div>
              <?php if (!empty($t['Description'])): ?>
              <small class="text-muted"><?= e(substr($t['Description'], 0, 60)) ?>…</small>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($emp): ?>
              <div class="small fw-500"><?= e($emp['Name']) ?></div>
              <div class="text-muted" style="font-size:.7rem"><?= e($emp['Designation']) ?></div>
              <?php else: ?>
              <span class="text-muted"><?= e($t['Employee_ID']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= priorityBadge($t['Priority'] ?? '') ?></td>
            <td class="small"><?= formatDate($t['Assigned_Date'] ?? '') ?></td>
            <td class="small <?= $overdue ? 'text-danger fw-600' : '' ?>">
              <?= formatDate($t['Deadline'] ?? '') ?>
            </td>
            <td>
              <?php if ($days > 0): ?>
                <span class="badge bg-danger"><?= $days ?>d overdue</span>
              <?php elseif (in_array($t['Status'] ?? '', ['Pending','In Progress'])): ?>
                <span class="badge bg-secondary">0</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($t['Status'] ?? '') ?></td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= BASE_URL ?>/admin/create_task.php?edit=<?= urlencode($t['Task_ID']) ?>"
                   class="btn btn-xs btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>

                <div class="btn-group">
                  <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle"
                          data-bs-toggle="dropdown" aria-expanded="false">
                    Change Status
                  </button>
                  <ul class="dropdown-menu">
                    <?php foreach (['Pending','In Progress','Completed'] as $statusOption): ?>
                    <li>
                      <form method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="update_task_status">
                        <input type="hidden" name="task_id" value="<?= e($t['Task_ID']) ?>">
                        <input type="hidden" name="status" value="<?= $statusOption ?>">
                        <button type="submit" class="dropdown-item<?= $t['Status'] === $statusOption ? ' active' : '' ?>">
                          <?= $statusOption ?>
                        </button>
                      </form>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <a href="#" class="btn btn-xs btn-outline-danger"
                   title="Delete"
                   onclick="confirmDelete('<?= BASE_URL ?>/admin/tasks.php?delete=<?= urlencode($t['Task_ID']) ?><?= $fEmployee ? '&employee=' . urlencode($fEmployee) : '' ?>')">
                  <i class="bi bi-trash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($filtered)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No tasks found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
