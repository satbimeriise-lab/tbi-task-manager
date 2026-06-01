<?php
// ============================================================
//  Admin — Reports
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$tasks     = $sheets->getAll(SHEET_TASKS);
$employees = $sheets->getAll(SHEET_EMPLOYEES);

$empMap = [];
foreach ($employees as $e) $empMap[$e['Employee_ID']] = $e;

// ── Report filters ────────────────────────────────────────────
$reportType = $_GET['type']     ?? 'daily';
$fEmployee  = $_GET['employee'] ?? '';
$fFrom      = $_GET['from']     ?? date('Y-m-01');
$fTo        = $_GET['to']       ?? date('Y-m-d');

// Date range filter
$filtered = array_filter($tasks, function($t) use ($fFrom, $fTo, $fEmployee) {
    $date = substr($t['Assigned_Date'] ?? '', 0, 10);
    if ($fFrom && $date < $fFrom) return false;
    if ($fTo   && $date > $fTo)   return false;
    if ($fEmployee && $t['Employee_ID'] !== $fEmployee) return false;
    return true;
});
$filtered = array_values($filtered);

// Report period labels
$periodLabels = [
    'daily'    => 'Daily Report — ' . date('d M Y'),
    'weekly'   => 'Weekly Report — Week ' . date('W, Y'),
    'monthly'  => 'Monthly Report — ' . date('F Y'),
    'custom'   => "Custom Report: $fFrom to $fTo",
    'pending'  => 'Pending Task Report',
    'employee' => 'Employee Productivity Report',
];

if ($reportType === 'pending') {
    $filtered = array_values(array_filter($filtered, fn($t) => in_array($t['Status'], ['Pending','In Progress'])));
}

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-700 mb-0"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Reports</h4>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-success" onclick="exportTableExcel('reportTable','TBI_Report_<?= $reportType ?>')">
      <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </button>
    <button class="btn btn-outline-danger" onclick="exportTablePDF('reportTable','<?= e($periodLabels[$reportType] ?? 'Report') ?>')">
      <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
    </button>
    <button class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<!-- Report Type Tabs -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php
      $types = ['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','custom'=>'Custom Range','pending'=>'Pending Tasks','employee'=>'Employee Productivity'];
      foreach ($types as $k => $v):
      ?>
      <a href="?type=<?= $k ?>&employee=<?= urlencode($fEmployee) ?>&from=<?= $fFrom ?>&to=<?= $fTo ?>"
         class="btn btn-sm <?= $reportType === $k ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= $v ?>
      </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="type" value="<?= e($reportType) ?>">
      <div class="col-auto">
        <label class="form-label small">Employee</label>
        <select class="form-select form-select-sm" name="employee">
          <option value="">All Employees</option>
          <?php foreach ($employees as $emp): ?>
          <option value="<?= e($emp['Employee_ID']) ?>" <?= $fEmployee === $emp['Employee_ID'] ? 'selected' : '' ?>>
            <?= e($emp['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small">From</label>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($fFrom) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label small">To</label>
        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($fTo) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
      </div>
    </form>
  </div>
</div>

<!-- Report Header -->
<div class="card border-0 shadow-sm mb-3 d-print-block">
  <div class="card-body">
    <div class="text-center mb-3 d-none d-print-block">
      <h4>Technology Business Incubator – MCE Hassan</h4>
      <h5><?= e($periodLabels[$reportType] ?? 'Report') ?></h5>
      <p class="text-muted small">Generated on <?= date('d M Y H:i') ?> by <?= e($_SESSION['name']) ?></p>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <?php
      $rTotal    = count($filtered);
      $rApproved = count(array_filter($filtered, fn($t) => $t['Status'] === 'Approved'));
      $rCompleted= count(array_filter($filtered, fn($t) => in_array($t['Status'], ['Completed','Approved'])));
      $rPending  = count(array_filter($filtered, fn($t) => in_array($t['Status'], ['Pending','In Progress'])));
      $rOverdue  = count(array_filter($filtered, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '')));
      ?>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-primary"><?= $rTotal ?></div><div class="small text-muted">Total Tasks</div>
      </div>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-success"><?= $rApproved ?></div><div class="small text-muted">Approved</div>
      </div>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-info"><?= $rCompleted ?></div><div class="small text-muted">Completed</div>
      </div>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-warning"><?= $rPending ?></div><div class="small text-muted">Pending</div>
      </div>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-danger"><?= $rOverdue ?></div><div class="small text-muted">Overdue</div>
      </div>
      <div class="col-6 col-md-2 text-center">
        <div class="fw-700 fs-4 text-purple"><?= $rTotal > 0 ? round(($rApproved/$rTotal)*100) : 0 ?>%</div>
        <div class="small text-muted">Approval Rate</div>
      </div>
    </div>

    <?php if ($reportType === 'employee'): ?>
    <!-- Employee-wise summary -->
    <h6 class="fw-600 mb-3">Employee Productivity Summary</h6>
    <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm" id="reportTable">
        <thead class="table-primary">
          <tr>
            <th>Employee</th><th>Designation</th><th>Total</th><th>Completed</th>
            <th>Approved</th><th>Pending</th><th>Overdue</th><th>Approval %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp):
            $empTasks = array_filter($filtered, fn($t) => $t['Employee_ID'] === $emp['Employee_ID']);
            $empStats = employeeStats($filtered, $emp['Employee_ID']);
          ?>
          <tr>
            <td class="fw-500"><?= e($emp['Name']) ?></td>
            <td><?= e($emp['Designation']) ?></td>
            <td><?= $empStats['total'] ?></td>
            <td class="text-success"><?= $empStats['completed'] ?></td>
            <td class="text-success fw-600"><?= $empStats['approved'] ?></td>
            <td class="text-warning"><?= $empStats['pending'] ?></td>
            <td class="<?= $empStats['overdue'] > 0 ? 'text-danger fw-600' : '' ?>"><?= $empStats['overdue'] ?></td>
            <td><?= $empStats['approvalPct'] ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Detailed task table -->
    <h6 class="fw-600 mb-3">Task Details (<?= count($filtered) ?> tasks)</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-sm" id="<?= $reportType !== 'employee' ? 'reportTable' : 'detailTable' ?>">
        <thead class="table-primary">
          <tr>
            <th>#</th><th>Task</th><th>Employee</th><th>Priority</th>
            <th>Assigned</th><th>Deadline</th><th>Days Pending</th><th>Status</th><th>Assigned By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $i => $t):
            $emp     = $empMap[$t['Employee_ID']] ?? null;
            $overdue = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
          ?>
          <tr class="<?= $overdue ? 'table-danger' : '' ?>">
            <td><?= $i + 1 ?></td>
            <td><?= e($t['Task_Title']) ?></td>
            <td><?= e($emp['Name'] ?? $t['Employee_ID']) ?></td>
            <td><?= e($t['Priority'] ?? '') ?></td>
            <td><?= formatDate($t['Assigned_Date'] ?? '') ?></td>
            <td><?= formatDate($t['Deadline'] ?? '') ?></td>
            <td><?= $overdue ? daysPending($t['Deadline']).'d' : '—' ?></td>
            <td><?= e($t['Status'] ?? '') ?></td>
            <td><?= e($t['Assigned_By'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($filtered)): ?>
          <tr><td colspan="9" class="text-center text-muted py-3">No data for selected period</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
