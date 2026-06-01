<?php
// ============================================================
//  Employee — Task List + Status Update + Completion Submit
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets  = new GoogleSheetsService();
$myEmpId = $_SESSION['employee_id'];

// ── Update task status (mark In Progress or Completed) ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $taskId    = $_POST['task_id']  ?? '';
    $newStatus = $_POST['status']   ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    $task = $sheets->findOne(SHEET_TASKS, 'Task_ID', $taskId);
    if ($task && $task['Employee_ID'] === $myEmpId) {

        if ($newStatus === 'Completed') {
            // Mark task as completed
            $sheets->updateById(SHEET_TASKS, 'Task_ID', $taskId, [
                'Status' => 'Completed',
                'Notes'  => $notes,
            ]);

            // Create approval request
            $approvalId = generateId('APR');
            $sheets->appendRow(SHEET_APPROVALS, [
                $approvalId,
                $taskId,
                $myEmpId,
                'Pending',          // Status
                '',                 // Approved_By
                '',                 // Comments
                '',                 // Approval_Date
                date('Y-m-d H:i:s'),// Submission_Date
            ]);

            setFlash('success', 'Task marked as completed and sent for approval.');
        } elseif (in_array($newStatus, ['Pending', 'In Progress'])) {
            $sheets->updateById(SHEET_TASKS, 'Task_ID', $taskId, [
                'Status' => $newStatus,
                'Notes'  => $notes,
            ]);
            setFlash('success', 'Task status updated.');
        }
    }
    redirect(BASE_URL . '/employee/tasks.php');
}

$allTasks = $sheets->findMany(SHEET_TASKS, 'Employee_ID', $myEmpId);

// Tab filter
$tab      = $_GET['tab']    ?? 'all';
$taskView = $_GET['task']   ?? '';
$fSearch  = trim($_GET['q'] ?? '');

$tabs = [
    'all'        => 'All Tasks',
    'pending'    => 'Pending',
    'inprogress' => 'In Progress',
    'completed'  => 'Completed',
    'approved'   => 'Approved',
    'rejected'   => 'Rejected',
    'overdue'    => 'Overdue',
];

$filtered = match($tab) {
    'pending'    => array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Pending')),
    'inprogress' => array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'In Progress')),
    'completed'  => array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Completed')),
    'approved'   => array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Approved')),
    'rejected'   => array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Rejected')),
    'overdue'    => array_values(array_filter($allTasks, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? ''))),
    default      => array_values($allTasks),
};

if ($fSearch) {
    $filtered = array_values(array_filter($filtered,
        fn($t) => stripos($t['Task_Title'] . $t['Description'], $fSearch) !== false
    ));
}

// Task detail modal
$detailTask = $taskView ? $sheets->findOne(SHEET_TASKS, 'Task_ID', $taskView) : null;
if ($detailTask && $detailTask['Employee_ID'] !== $myEmpId) $detailTask = null;

$pageTitle = 'My Tasks';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-700 mb-0"><i class="bi bi-list-task me-2 text-primary"></i>My Tasks</h4>
</div>

<!-- Tab nav -->
<ul class="nav nav-pills flex-wrap gap-1 mb-3">
  <?php foreach ($tabs as $k => $v):
    $cnt = match($k) {
        'pending'    => count(array_filter($allTasks, fn($t) => $t['Status'] === 'Pending')),
        'inprogress' => count(array_filter($allTasks, fn($t) => $t['Status'] === 'In Progress')),
        'completed'  => count(array_filter($allTasks, fn($t) => $t['Status'] === 'Completed')),
        'approved'   => count(array_filter($allTasks, fn($t) => $t['Status'] === 'Approved')),
        'rejected'   => count(array_filter($allTasks, fn($t) => $t['Status'] === 'Rejected')),
        'overdue'    => count(array_filter($allTasks, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? ''))),
        default      => count($allTasks),
    };
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $k ? 'active' : '' ?>"
       href="?tab=<?= $k ?><?= $fSearch ? '&q='.urlencode($fSearch) : '' ?>">
      <?= $v ?>
      <?php if ($cnt > 0): ?>
      <span class="badge <?= $tab === $k ? 'bg-white text-primary' : 'bg-secondary' ?> ms-1"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Search -->
<form method="GET" class="mb-3 d-flex gap-2">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <input type="text" class="form-control" name="q" placeholder="Search tasks…" value="<?= e($fSearch) ?>">
  <button type="submit" class="btn btn-primary">Search</button>
  <?php if ($fSearch): ?><a href="?tab=<?= e($tab) ?>" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
</form>

<!-- Tasks -->
<div class="row g-3">
  <?php foreach ($filtered as $t):
    $overdue = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
    $days    = daysPending($t['Deadline'] ?? '');
    $canAct  = in_array($t['Status'], ['Pending','In Progress']);
  ?>
  <div class="col-12 col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100 <?= $overdue ? 'border-start border-danger border-3' : '' ?>">
      <div class="card-body">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="fw-600 mb-0 flex-fill me-2"><?= e($t['Task_Title']) ?></h6>
          <div class="d-flex gap-1 flex-shrink-0">
            <?= priorityBadge($t['Priority'] ?? '') ?>
            <?= statusBadge($t['Status'] ?? '') ?>
          </div>
        </div>

        <!-- Description -->
        <?php if (!empty($t['Description'])): ?>
        <p class="text-muted small mb-2"><?= e(substr($t['Description'],0,120)) ?><?= strlen($t['Description'])>120 ? '…' : '' ?></p>
        <?php endif; ?>

        <!-- Dates -->
        <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
          <span><i class="bi bi-calendar-plus me-1"></i><?= formatDate($t['Assigned_Date'] ?? '') ?></span>
          <span class="<?= $overdue ? 'text-danger fw-600' : '' ?>">
            <i class="bi bi-calendar-x me-1"></i><?= formatDate($t['Deadline'] ?? '') ?>
          </span>
        </div>

        <?php if ($overdue): ?>
        <div class="badge bg-danger bg-opacity-10 text-danger mb-2">
          <i class="bi bi-exclamation-circle me-1"></i><?= $days ?> day<?= $days > 1 ? 's' : '' ?> overdue
        </div>
        <?php endif; ?>

        <?php if (!empty($t['Notes'])): ?>
        <div class="alert alert-light py-1 small mb-2">
          <i class="bi bi-chat-text me-1"></i><strong>Feedback:</strong> <?= e($t['Notes']) ?>
        </div>
        <?php endif; ?>

        <!-- Attachment -->
        <?php if (!empty($t['File_URL'])): ?>
        <a href="<?= e($t['File_URL']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-2">
          <i class="bi bi-paperclip me-1"></i>Attachment
        </a>
        <?php endif; ?>

        <!-- Status control -->
        <div class="mb-2 mt-auto">
          <div class="btn-group w-100">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle w-100"
                    data-bs-toggle="dropdown" aria-expanded="false">
              Change Status
            </button>
            <ul class="dropdown-menu w-100">
              <?php foreach (['Pending','In Progress','Completed'] as $statusOption): ?>
              <li>
                <?php if ($statusOption === 'Completed'): ?>
                  <button type="button" class="dropdown-item<?= $t['Status'] === $statusOption ? ' active' : '' ?>"
                          onclick="openCompleteModal('<?= e($t['Task_ID']) ?>','<?= e(addslashes($t['Task_Title'])) ?>')">
                    <?= $statusOption ?>
                  </button>
                <?php else: ?>
                  <button type="button" class="dropdown-item<?= $t['Status'] === $statusOption ? ' active' : '' ?>"
                          onclick="submitTaskStatus('<?= e($t['Task_ID']) ?>','<?= $statusOption ?>')">
                    <?= $statusOption ?>
                  </button>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <?php if ($t['Status'] === 'Completed'): ?>
        <div class="alert alert-info py-1 small mt-2 mb-0">
          <i class="bi bi-clock me-1"></i>Awaiting admin approval…
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($filtered)): ?>
  <div class="col-12">
    <div class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      No tasks found<?= $fSearch ? ' for "'.e($fSearch).'"' : '' ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Complete Task Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title fw-700"><i class="bi bi-check2-circle me-2"></i>Mark Task as Completed</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="task_id"    id="completeTaskId">
        <input type="hidden" name="status"     value="Completed">
        <div class="modal-body">
          <p class="mb-2">Mark <strong id="completeTaskTitle"></strong> as completed?</p>
          <div class="mb-3">
            <label class="form-label">Completion Notes / Work Summary</label>
            <textarea class="form-control" name="notes" rows="3"
                      placeholder="Briefly describe what was accomplished…"></textarea>
          </div>
          <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>This will be sent to admin for approval.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Submit for Approval</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openCompleteModal(taskId, taskTitle) {
  document.getElementById('completeTaskId').value = taskId;
  document.getElementById('completeTaskTitle').textContent = taskTitle;
  new bootstrap.Modal(document.getElementById('completeModal')).show();
}

function submitTaskStatus(taskId, status) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '';
  form.style.display = 'none';

  const token = document.createElement('input');
  token.type = 'hidden';
  token.name = 'csrf_token';
  token.value = '<?= csrfToken() ?>';
  form.appendChild(token);

  const taskInput = document.createElement('input');
  taskInput.type = 'hidden';
  taskInput.name = 'task_id';
  taskInput.value = taskId;
  form.appendChild(taskInput);

  const statusInput = document.createElement('input');
  statusInput.type = 'hidden';
  statusInput.name = 'status';
  statusInput.value = status;
  form.appendChild(statusInput);

  document.body.appendChild(form);
  form.submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
