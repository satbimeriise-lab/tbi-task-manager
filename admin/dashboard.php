<?php
// ============================================================
//  Admin Dashboard — Home with all employee cards
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$sheets->refreshPendingDays();

$employees = $sheets->getAll(SHEET_EMPLOYEES);
$tasks     = $sheets->getAll(SHEET_TASKS);
$approvals = $sheets->getAll(SHEET_APPROVALS);

// Global stats
$totalTasks     = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => in_array($t['Status'], ['Completed','Approved'])));
$pendingTasks   = count(array_filter($tasks, fn($t) => in_array($t['Status'], ['Pending','In Progress'])));
$approvedTasks  = count(array_filter($tasks, fn($t) => $t['Status'] === 'Approved'));
$overdueTasks   = count(array_filter($tasks, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '')));
$approvalRate   = $totalTasks > 0 ? round(($approvedTasks / $totalTasks) * 100) : 0;

// Pending approvals (tasks marked Completed awaiting admin action)
$pendingApprovals = count(array_filter($approvals, fn($a) => $a['Status'] === 'Pending'));

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-700 mb-0"><i class="bi bi-grid-fill me-2 text-primary"></i>Admin Dashboard</h4>
    <small class="text-muted">Welcome back, <?= e($_SESSION['name']) ?> &bull; <?= date('l, d F Y') ?></small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/admin/create_task.php" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i>New Task
    </a>
    <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-secondary">
      <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
    </a>
  </div>
</div>

<!-- ── Global Stats ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-primary border-4">
      <div class="stat-icon text-primary"><i class="bi bi-list-task"></i></div>
      <div class="stat-val"><?= $totalTasks ?></div>
      <div class="stat-lbl">Total Tasks</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-success border-4">
      <div class="stat-icon text-success"><i class="bi bi-check2-all"></i></div>
      <div class="stat-val"><?= $approvedTasks ?></div>
      <div class="stat-lbl">Approved</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-info border-4">
      <div class="stat-icon text-info"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-val"><?= $completedTasks ?></div>
      <div class="stat-lbl">Completed</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-warning border-4">
      <div class="stat-icon text-warning"><i class="bi bi-clock"></i></div>
      <div class="stat-val"><?= $pendingTasks ?></div>
      <div class="stat-lbl">Pending</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-danger border-4">
      <div class="stat-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-val"><?= $overdueTasks ?></div>
      <div class="stat-lbl">Overdue</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <?php if ($pendingApprovals > 0): ?>
    <a href="<?= BASE_URL ?>/admin/approvals.php" class="text-decoration-none">
    <?php endif; ?>
    <div class="stat-card border-start border-4" style="border-color:#6f42c1!important">
      <div class="stat-icon" style="color:#6f42c1"><i class="bi bi-bell"></i></div>
      <div class="stat-val"><?= $pendingApprovals ?></div>
      <div class="stat-lbl">Pending Approvals <?= $pendingApprovals > 0 ? '<span class="badge bg-danger">!</span>' : '' ?></div>
    </div>
    <?php if ($pendingApprovals > 0): ?></a><?php endif; ?>
  </div>
</div>

<!-- ── Employee Cards ────────────────────────────────────────── -->
<h5 class="fw-700 mb-3"><i class="bi bi-people-fill me-2 text-primary"></i>Employee Overview</h5>

<div class="row g-3 mb-4" id="employeeCards">
<?php
$order = ['CEO','COO','Software Associate','Finance Associate','Innovation Associate','Supporting Staff'];
usort($employees, function($a,$b) use ($order) {
    $ai = array_search($a['Designation'],$order) !== false ? array_search($a['Designation'],$order) : 99;
    $bi = array_search($b['Designation'],$order) !== false ? array_search($b['Designation'],$order) : 99;
    return $ai <=> $bi;
});
foreach ($employees as $emp):
    $stats = employeeStats($tasks, $emp['Employee_ID']);
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $emp['Name'])));
    $initials = substr($initials, 0, 2);
?>
<div class="col-12 col-sm-6 col-lg-4">
  <a href="<?= BASE_URL ?>/admin/tasks.php?employee=<?= urlencode($emp['Employee_ID']) ?>" class="emp-card text-decoration-none text-reset">

    <div class="emp-card-header">
      <?php if (!empty($emp['Photo_URL'])): ?>
        <img src="<?= e($emp['Photo_URL']) ?>" alt="<?= e($emp['Name']) ?>" class="emp-avatar">
      <?php else: ?>
        <div class="emp-avatar-initials"><?= e($initials) ?></div>
      <?php endif; ?>
      <div>
        <div class="emp-name"><?= e($emp['Name']) ?></div>
        <div class="emp-desig">
          <?php
          $icon = match($emp['Designation']) {
              'CEO'                 => 'bi-person-badge-fill',
              'COO'                 => 'bi-person-workspace',
              'Software Associate'  => 'bi-code-slash',
              'Finance Associate'   => 'bi-currency-rupee',
              'Innovation Associate'=> 'bi-lightbulb',
              default               => 'bi-person',
          };
          ?>
          <i class="bi <?= $icon ?> me-1"></i><?= e($emp['Designation']) ?>
        </div>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-3 text-center">
        <div class="emp-stat-val"><?= $stats['total'] ?></div>
        <div class="emp-stat-lbl">Total</div>
      </div>
      <div class="col-3 text-center">
        <div class="emp-stat-val text-success"><?= $stats['completed'] ?></div>
        <div class="emp-stat-lbl">Done</div>
      </div>
      <div class="col-3 text-center">
        <div class="emp-stat-val text-warning"><?= $stats['pending'] ?></div>
        <div class="emp-stat-lbl">Pending</div>
      </div>
      <div class="col-3 text-center">
        <div class="emp-stat-val text-danger"><?= $stats['overdue'] ?></div>
        <div class="emp-stat-lbl">Overdue</div>
      </div>
    </div>

    <!-- Approval rate bar -->
    <div class="mt-3">
      <div class="d-flex justify-content-between mb-1">
        <small class="text-muted">Approval Rate</small>
        <small class="fw-600 <?= $stats['approvalPct'] >= 70 ? 'text-success' : ($stats['approvalPct'] >= 40 ? 'text-warning' : 'text-danger') ?>">
          <?= $stats['approvalPct'] ?>%
        </small>
      </div>
      <div class="progress" style="height:6px">
        <div class="progress-bar bg-<?= $stats['approvalPct'] >= 70 ? 'success' : ($stats['approvalPct'] >= 40 ? 'warning' : 'danger') ?>"
             style="width:<?= $stats['approvalPct'] ?>%"></div>
      </div>
    </div>

    <?php if ($stats['overdue'] > 0): ?>
    <div class="mt-2">
      <span class="badge bg-danger bg-opacity-10 text-danger">
        <i class="bi bi-exclamation-circle me-1"></i><?= $stats['overdue'] ?> overdue task<?= $stats['overdue'] > 1 ? 's' : '' ?>
      </span>
    </div>
    <?php endif; ?>

    <div class="emp-card-footer">
      <small><i class="bi bi-envelope me-1"></i><?= e($emp['Email'] ?? '—') ?></small>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Charts Row ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-600">Task Distribution</div>
      <div class="card-body d-flex align-items-center justify-content-center" style="height:250px">
        <canvas id="taskDistChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-600">Employee Performance</div>
      <div class="card-body" style="height:250px">
        <canvas id="empPerfChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Tasks ──────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-600"><i class="bi bi-clock-history me-2"></i>Recent Tasks</span>
    <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Task</th><th>Employee</th><th>Priority</th><th>Deadline</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recent = array_slice(array_reverse($tasks), 0, 10);
          foreach ($recent as $t):
            $emp = null;
            foreach ($employees as $e) if ($e['Employee_ID'] === $t['Employee_ID']) { $emp = $e; break; }
            $overdue = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
          ?>
          <tr class="<?= $overdue ? 'table-danger' : '' ?>">
            <td>
              <div class="fw-500"><?= e($t['Task_Title']) ?></div>
              <?php if ($overdue): ?>
              <small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Overdue <?= daysPending($t['Deadline']) ?>d</small>
              <?php endif; ?>
            </td>
            <td><?= e($emp['Name'] ?? $t['Employee_ID']) ?></td>
            <td><?= priorityBadge($t['Priority'] ?? '') ?></td>
            <td><?= formatDate($t['Deadline'] ?? '') ?></td>
            <td><?= statusBadge($t['Status'] ?? '') ?></td>
            <td>
              <a href="<?= BASE_URL ?>/admin/tasks.php?edit=<?= urlencode($t['Task_ID']) ?>" class="btn btn-xs btn-outline-secondary">
                <i class="bi bi-pencil"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No tasks found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Task Distribution Doughnut
(function(){
  const ctx = document.getElementById('taskDistChart');
  if(!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Approved','Completed','In Progress','Pending','Rejected'],
      datasets:[{
        data: [
          <?= count(array_filter($tasks, fn($t) => $t['Status']==='Approved')) ?>,
          <?= count(array_filter($tasks, fn($t) => $t['Status']==='Completed')) ?>,
          <?= count(array_filter($tasks, fn($t) => $t['Status']==='In Progress')) ?>,
          <?= count(array_filter($tasks, fn($t) => $t['Status']==='Pending')) ?>,
          <?= count(array_filter($tasks, fn($t) => $t['Status']==='Rejected')) ?>
        ],
        backgroundColor:['#198754','#0dcaf0','#0d6efd','#ffc107','#dc3545'],
        borderWidth:2
      }]
    },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8 } } } }
  });
})();

// Employee Performance Bar
(function(){
  const ctx = document.getElementById('empPerfChart');
  if(!ctx) return;
  const labels = <?= json_encode(array_column($employees, 'Name')) ?>;
  const names  = <?= json_encode(array_column($employees, 'Employee_ID')) ?>;
  // Build data arrays from PHP
  const taskData = <?= json_encode(array_map(function($e) use ($tasks) {
      return employeeStats($tasks, $e['Employee_ID']);
  }, $employees)) ?>;

  new Chart(ctx, {
    type:'bar',
    data:{
      labels: labels,
      datasets:[
        { label:'Total',     data: taskData.map(d=>d.total),     backgroundColor:'rgba(13,110,253,.7)' },
        { label:'Completed', data: taskData.map(d=>d.completed), backgroundColor:'rgba(25,135,84,.7)' },
        { label:'Pending',   data: taskData.map(d=>d.pending),   backgroundColor:'rgba(255,193,7,.7)' },
      ]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'top' } },
      scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
    }
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
