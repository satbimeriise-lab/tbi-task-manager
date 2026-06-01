<?php
// ============================================================
//  Employee Dashboard
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets   = new GoogleSheetsService();
$myEmpId  = $_SESSION['employee_id'];

$allTasks   = $sheets->findMany(SHEET_TASKS, 'Employee_ID', $myEmpId);
$approvals  = $sheets->findMany(SHEET_APPROVALS, 'Employee_ID', $myEmpId);
$notifs     = $sheets->findMany(SHEET_NOTIFICATIONS, 'User_ID', $myEmpId);
$unreadNotifs = array_filter($notifs, fn($n) => $n['Read_Status'] === 'unread');

// Task breakdowns
$pending   = array_values(array_filter($allTasks, fn($t) => in_array($t['Status'], ['Pending','In Progress'])));
$completed = array_values(array_filter($allTasks, fn($t) => in_array($t['Status'], ['Completed','Approved'])));
$overdue   = array_values(array_filter($allTasks, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '')));
$rejected  = array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Rejected'));
$approved  = array_values(array_filter($allTasks, fn($t) => $t['Status'] === 'Approved'));

$stats = employeeStats($allTasks, $myEmpId);

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-700 mb-0"><i class="bi bi-grid me-2 text-primary"></i>My Dashboard</h4>
    <small class="text-muted"><?= e($_SESSION['name']) ?> &bull; <?= e($_SESSION['designation']) ?> &bull; <?= date('l, d F Y') ?></small>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-primary border-4 text-center">
      <div class="stat-val text-primary"><?= $stats['total'] ?></div>
      <div class="stat-lbl">Total Tasks</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-success border-4 text-center">
      <div class="stat-val text-success"><?= $stats['approved'] ?></div>
      <div class="stat-lbl">Approved</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-info border-4 text-center">
      <div class="stat-val text-info"><?= $stats['completed'] ?></div>
      <div class="stat-lbl">Completed</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-warning border-4 text-center">
      <div class="stat-val text-warning"><?= $stats['pending'] ?></div>
      <div class="stat-lbl">Pending</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-danger border-4 text-center">
      <div class="stat-val text-danger"><?= $stats['overdue'] ?></div>
      <div class="stat-lbl">Overdue</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-lg-2">
    <div class="stat-card border-start border-4 text-center" style="border-color:#6f42c1!important">
      <div class="stat-val" style="color:#6f42c1"><?= $stats['approvalPct'] ?>%</div>
      <div class="stat-lbl">Approval %</div>
    </div>
  </div>
</div>

<!-- Notifications Banner -->
<?php if (count($unreadNotifs) > 0): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-bell-fill fs-5"></i>
  <div>
    You have <strong><?= count($unreadNotifs) ?></strong> unread notification<?= count($unreadNotifs) > 1 ? 's' : '' ?>.
    <a href="#notifSection" class="alert-link">View below</a>
  </div>
</div>
<?php endif; ?>

<!-- Overdue Alert -->
<?php if (count($overdue) > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div>
    <strong><?= count($overdue) ?> overdue task<?= count($overdue) > 1 ? 's' : '' ?></strong> need immediate attention!
    <a href="<?= BASE_URL ?>/employee/tasks.php?status=overdue" class="alert-link">View overdue tasks</a>
  </div>
</div>
<?php endif; ?>

<!-- Charts & Quick View -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-600">Task Overview</div>
      <div class="card-body d-flex align-items-center justify-content-center" style="height:200px">
        <canvas id="myTaskChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <!-- Urgent Tasks -->
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between">
        <span class="fw-600"><i class="bi bi-exclamation-circle text-danger me-2"></i>Priority / Overdue Tasks</span>
        <a href="<?= BASE_URL ?>/employee/tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <?php
          // Merge overdue and high-priority pending, sort by deadline
          $urgent = array_merge($overdue, array_filter($pending, fn($t) => $t['Priority'] === 'High'));
          usort($urgent, fn($a,$b) => strcmp($a['Deadline'] ?? '', $b['Deadline'] ?? ''));
          $urgent = array_slice(array_unique($urgent, SORT_REGULAR), 0, 6);
          foreach ($urgent as $t):
            $od = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
          ?>
          <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3">
            <div class="flex-fill">
              <div class="fw-500 small"><?= e($t['Task_Title']) ?></div>
              <div class="d-flex gap-2 mt-1">
                <?= priorityBadge($t['Priority'] ?? '') ?>
                <?= statusBadge($t['Status'] ?? '') ?>
                <?php if ($od): ?>
                <span class="badge bg-danger"><?= daysPending($t['Deadline']) ?>d overdue</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end small text-muted">
              <?= formatDate($t['Deadline'] ?? '') ?>
            </div>
            <a href="<?= BASE_URL ?>/employee/tasks.php?task=<?= urlencode($t['Task_ID']) ?>"
               class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-eye"></i>
            </a>
          </div>
          <?php endforeach; ?>
          <?php if (empty($urgent)): ?>
          <div class="list-group-item text-center text-muted py-4">
            <i class="bi bi-check2-circle text-success me-1"></i>No urgent tasks
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Tasks -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-600"><i class="bi bi-clock-history me-2"></i>Recent Assignments</span>
    <a href="<?= BASE_URL ?>/employee/tasks.php" class="btn btn-sm btn-outline-primary">All Tasks</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Task</th><th>Priority</th><th>Deadline</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach (array_slice(array_reverse($allTasks), 0, 8) as $t):
            $od = isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '');
          ?>
          <tr class="<?= $od ? 'table-danger' : '' ?>">
            <td>
              <div class="fw-500 small"><?= e($t['Task_Title']) ?></div>
              <?php if (!empty($t['Description'])): ?>
              <div class="text-muted" style="font-size:.7rem"><?= e(substr($t['Description'],0,60)) ?>…</div>
              <?php endif; ?>
            </td>
            <td><?= priorityBadge($t['Priority'] ?? '') ?></td>
            <td class="small <?= $od ? 'text-danger fw-600' : '' ?>"><?= formatDate($t['Deadline'] ?? '') ?></td>
            <td><?= statusBadge($t['Status'] ?? '') ?></td>
            <td>
              <a href="<?= BASE_URL ?>/employee/tasks.php?task=<?= urlencode($t['Task_ID']) ?>"
                 class="btn btn-xs btn-outline-secondary"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Notifications Section -->
<div class="card border-0 shadow-sm" id="notifSection">
  <div class="card-header fw-600"><i class="bi bi-bell me-2"></i>Notifications</div>
  <div class="list-group list-group-flush">
    <?php foreach (array_slice(array_reverse($notifs), 0, 10) as $n):
      $isNew = $n['Read_Status'] === 'unread';
      $icon  = match($n['Type'] ?? '') {
          'task_assigned'  => 'bi-list-task text-primary',
          'approval_result'=> 'bi-check2-circle text-success',
          default          => 'bi-bell text-muted',
      };
    ?>
    <div class="list-group-item d-flex align-items-center gap-3 py-2 <?= $isNew ? 'bg-light fw-500' : '' ?>">
      <i class="bi <?= $icon ?> fs-5"></i>
      <div class="flex-fill">
        <div class="small"><?= e($n['Message'] ?? '') ?></div>
        <div class="text-muted" style="font-size:.7rem"><?= formatDate($n['Created_At'] ?? '') ?></div>
      </div>
      <?php if ($isNew): ?><span class="badge bg-primary rounded-pill">New</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notifs)): ?>
    <div class="list-group-item text-center text-muted py-3">No notifications</div>
    <?php endif; ?>
  </div>
</div>

<script>
new Chart(document.getElementById('myTaskChart'), {
  type: 'doughnut',
  data: {
    labels: ['Approved','In Progress','Pending','Rejected'],
    datasets:[{ data: [<?= $stats['approved'] ?>,<?= count(array_filter($allTasks,fn($t)=>$t['Status']==='In Progress')) ?>,<?= count(array_filter($allTasks,fn($t)=>$t['Status']==='Pending')) ?>,<?= $stats['rejected'] ?>],
      backgroundColor:['#198754','#0d6efd','#ffc107','#dc3545'], borderWidth:2 }]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, padding:6, font:{ size:11 } } } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
