<?php
// ============================================================
//  Admin — Approval Workflow
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$approvals = $sheets->getAll(SHEET_APPROVALS);
$tasks     = $sheets->getAll(SHEET_TASKS);
$employees = $sheets->getAll(SHEET_EMPLOYEES);

// Build lookups
$taskMap = [];
foreach ($tasks     as $t) $taskMap[$t['Task_ID']]         = $t;
$empMap  = [];
foreach ($employees as $e) $empMap[$e['Employee_ID']]      = $e;

// ── Process approval action ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $approvalId = $_POST['approval_id'] ?? '';
    $action     = $_POST['action']      ?? '';   // 'approve' or 'reject'
    $comments   = trim($_POST['comments'] ?? '');

    if ($approvalId && in_array($action, ['approve', 'reject'])) {
        $approval = $sheets->findOne(SHEET_APPROVALS, 'Approval_ID', $approvalId);

        if ($approval) {
            $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

            // Update Approvals sheet
            $sheets->updateById(SHEET_APPROVALS, 'Approval_ID', $approvalId, [
                'Status'        => $newStatus,
                'Approved_By'   => $_SESSION['name'],
                'Comments'      => $comments,
                'Approval_Date' => date('Y-m-d H:i:s'),
            ]);

            // Update Task status
            $sheets->updateById(SHEET_TASKS, 'Task_ID', $approval['Task_ID'], [
                'Status' => $newStatus,
                'Notes'  => $comments,
            ]);

            // Notify employee
            $sheets->appendRow(SHEET_NOTIFICATIONS, [
                generateId('NOTIF'),
                $approval['Employee_ID'],
                "Your task has been {$newStatus}: " . ($taskMap[$approval['Task_ID']]['Task_Title'] ?? ''),
                'approval_result',
                'unread',
                date('Y-m-d H:i:s'),
            ]);

            setFlash('success', "Task {$newStatus} successfully.");
        }
    }
    redirect(BASE_URL . '/admin/approvals.php');
}

// Split approvals
$pendingApprovals  = array_filter($approvals, fn($a) => $a['Status'] === 'Pending');
$decidedApprovals  = array_filter($approvals, fn($a) => $a['Status'] !== 'Pending');
$pendingApprovals  = array_values($pendingApprovals);
$decidedApprovals  = array_values(array_reverse($decidedApprovals));

$pageTitle = 'Approvals';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <h4 class="fw-700 mb-0">
    <i class="bi bi-check2-circle me-2 text-primary"></i>Approval Workflow
    <?php if (count($pendingApprovals) > 0): ?>
    <span class="badge bg-danger"><?= count($pendingApprovals) ?> pending</span>
    <?php endif; ?>
  </h4>
</div>

<!-- ── Pending Approvals ──────────────────────────────────────── -->
<h5 class="fw-600 mb-3 text-warning"><i class="bi bi-clock me-2"></i>Awaiting Review (<?= count($pendingApprovals) ?>)</h5>

<?php if (empty($pendingApprovals)): ?>
<div class="alert alert-success"><i class="bi bi-check2-all me-2"></i>No pending approvals. All tasks are reviewed!</div>
<?php endif; ?>

<div class="row g-3 mb-5">
<?php foreach ($pendingApprovals as $appr):
  $task = $taskMap[$appr['Task_ID']] ?? [];
  $emp  = $empMap[$appr['Employee_ID']] ?? [];
  $initials = substr(implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $emp['Name'] ?? 'U'))), 0, 2);
?>
<div class="col-12 col-md-6 col-xl-4">
  <div class="card border-0 shadow border-start border-warning border-4 h-100">
    <div class="card-body">

      <div class="d-flex align-items-center gap-2 mb-3">
        <?php if (!empty($emp['Photo_URL'])): ?>
          <img src="<?= e($emp['Photo_URL']) ?>" class="rounded-circle" width="42" height="42" style="object-fit:cover">
        <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-700 text-white"
               style="width:42px;height:42px;background:linear-gradient(135deg,#0d2b5c,#1565c0);font-size:.85rem">
            <?= e($initials) ?>
          </div>
        <?php endif; ?>
        <div>
          <div class="fw-600"><?= e($emp['Name'] ?? '—') ?></div>
          <div class="text-muted small"><?= e($emp['Designation'] ?? '') ?></div>
        </div>
        <div class="ms-auto"><?= priorityBadge($task['Priority'] ?? '') ?></div>
      </div>

      <div class="fw-600 mb-1"><?= e($task['Task_Title'] ?? '—') ?></div>
      <?php if (!empty($task['Description'])): ?>
      <div class="text-muted small mb-2"><?= e(substr($task['Description'], 0, 100)) ?></div>
      <?php endif; ?>

      <div class="d-flex gap-3 text-muted small mb-3">
        <span><i class="bi bi-calendar me-1"></i>Deadline: <?= formatDate($task['Deadline'] ?? '') ?></span>
        <span><i class="bi bi-clock me-1"></i>Submitted: <?= formatDate($appr['Submission_Date'] ?? '') ?></span>
      </div>

      <?php if (!empty($task['File_URL'])): ?>
      <div class="mb-3">
        <a href="<?= e($task['File_URL']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-paperclip me-1"></i>View Attachment
        </a>
      </div>
      <?php endif; ?>

      <!-- Approve/Reject Form -->
      <form method="POST">
        <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
        <input type="hidden" name="approval_id" value="<?= e($appr['Approval_ID']) ?>">
        <div class="mb-2">
          <textarea class="form-control form-control-sm" name="comments" rows="2"
                    placeholder="Add review comments (optional)…"></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" name="action" value="approve"
                  class="btn btn-success btn-sm flex-fill"
                  onclick="return confirm('Approve this task?')">
            <i class="bi bi-check2 me-1"></i>Approve
          </button>
          <button type="submit" name="action" value="reject"
                  class="btn btn-danger btn-sm flex-fill"
                  onclick="return confirm('Reject this task?')">
            <i class="bi bi-x me-1"></i>Reject
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Approval History ───────────────────────────────────────── -->
<h5 class="fw-600 mb-3"><i class="bi bi-clock-history me-2"></i>Approval History</h5>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Employee</th><th>Task</th><th>Submitted</th>
            <th>Reviewed By</th><th>Decision</th><th>Comments</th><th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($decidedApprovals as $appr):
            $task = $taskMap[$appr['Task_ID']] ?? [];
            $emp  = $empMap[$appr['Employee_ID']] ?? [];
          ?>
          <tr>
            <td>
              <div class="small fw-500"><?= e($emp['Name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.7rem"><?= e($emp['Designation'] ?? '') ?></div>
            </td>
            <td class="small"><?= e($task['Task_Title'] ?? $appr['Task_ID']) ?></td>
            <td class="small"><?= formatDate($appr['Submission_Date'] ?? '') ?></td>
            <td class="small"><?= e($appr['Approved_By'] ?? '—') ?></td>
            <td><?= statusBadge($appr['Status']) ?></td>
            <td class="small text-muted"><?= e($appr['Comments'] ?? '—') ?></td>
            <td class="small"><?= formatDate($appr['Approval_Date'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($decidedApprovals)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No history yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
