<?php
// ============================================================
//  Admin — Analytics Dashboard
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$tasks     = $sheets->getAll(SHEET_TASKS);
$employees = $sheets->getAll(SHEET_EMPLOYEES);
$approvals = $sheets->getAll(SHEET_APPROVALS);

// Monthly stats (last 6 months)
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey   = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    $monthTasks = array_filter($tasks, fn($t) => substr($t['Assigned_Date'] ?? '', 0, 7) === $monthKey);
    $monthDone  = array_filter($monthTasks, fn($t) => in_array($t['Status'], ['Completed','Approved']));
    $monthlyData[] = [
        'label'     => $monthLabel,
        'total'     => count($monthTasks),
        'completed' => count($monthDone),
    ];
}

// Ranking
$ranking = [];
foreach ($employees as $emp) {
    $stats = employeeStats($tasks, $emp['Employee_ID']);
    $ranking[] = ['name' => $emp['Name'], 'designation' => $emp['Designation'], 'stats' => $stats];
}
usort($ranking, fn($a, $b) => $b['stats']['approvalPct'] <=> $a['stats']['approvalPct']);

$pageTitle = 'Analytics';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-700 mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Analytics Dashboard</h4>
  <span class="text-muted small">Data updated in real-time from Google Sheets</span>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
  <?php
  $totalTasks    = count($tasks);
  $approved      = count(array_filter($tasks, fn($t) => $t['Status'] === 'Approved'));
  $completed     = count(array_filter($tasks, fn($t) => in_array($t['Status'], ['Completed','Approved'])));
  $pending       = count(array_filter($tasks, fn($t) => in_array($t['Status'], ['Pending','In Progress'])));
  $overdue       = count(array_filter($tasks, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '')));
  $approvalRate  = $totalTasks > 0 ? round(($approved / $totalTasks) * 100) : 0;
  $completionRate= $totalTasks > 0 ? round(($completed / $totalTasks) * 100) : 0;
  ?>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-primary border-4 text-center">
      <div class="stat-val text-primary"><?= $totalTasks ?></div>
      <div class="stat-lbl">Total Tasks</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-success border-4 text-center">
      <div class="stat-val text-success"><?= $approved ?></div>
      <div class="stat-lbl">Approved</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-info border-4 text-center">
      <div class="stat-val text-info"><?= $completed ?></div>
      <div class="stat-lbl">Completed</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-warning border-4 text-center">
      <div class="stat-val text-warning"><?= $pending ?></div>
      <div class="stat-lbl">Pending</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-danger border-4 text-center">
      <div class="stat-val text-danger"><?= $overdue ?></div>
      <div class="stat-lbl">Overdue</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card border-start border-4 text-center" style="border-color:#6f42c1!important">
      <div class="stat-val" style="color:#6f42c1"><?= $approvalRate ?>%</div>
      <div class="stat-lbl">Approval Rate</div>
    </div>
  </div>
</div>

<!-- Charts Row 1 -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-600">Monthly Task Productivity (Last 6 Months)</div>
      <div class="card-body" style="height:280px">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-600">Priority Breakdown</div>
      <div class="card-body d-flex align-items-center" style="height:280px">
        <canvas id="priorityChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-600">Status Distribution</div>
      <div class="card-body d-flex align-items-center" style="height:260px">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-600">Employee Task Load</div>
      <div class="card-body" style="height:260px">
        <canvas id="empLoadChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Ranking Table -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header fw-600"><i class="bi bi-trophy me-2 text-warning"></i>Employee Performance Ranking</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Employee</th><th>Designation</th><th>Total</th>
            <th>Completed</th><th>Pending</th><th>Overdue</th><th>Approval %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ranking as $i => $r):
            $medal = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => ($i + 1) };
          ?>
          <tr>
            <td class="fw-700"><?= $medal ?></td>
            <td class="fw-600"><?= e($r['name']) ?></td>
            <td class="text-muted small"><?= e($r['designation']) ?></td>
            <td><?= $r['stats']['total'] ?></td>
            <td class="text-success"><?= $r['stats']['completed'] ?></td>
            <td class="text-warning"><?= $r['stats']['pending'] ?></td>
            <td class="<?= $r['stats']['overdue'] > 0 ? 'text-danger fw-600' : '' ?>"><?= $r['stats']['overdue'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-fill" style="height:6px">
                  <div class="progress-bar bg-<?= $r['stats']['approvalPct'] >= 70 ? 'success' : ($r['stats']['approvalPct'] >= 40 ? 'warning' : 'danger') ?>"
                       style="width:<?= $r['stats']['approvalPct'] ?>%"></div>
                </div>
                <span class="fw-600 small"><?= $r['stats']['approvalPct'] ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const monthlyLabels = <?= json_encode(array_column($monthlyData, 'label')) ?>;
const monthlyTotal  = <?= json_encode(array_column($monthlyData, 'total')) ?>;
const monthlyDone   = <?= json_encode(array_column($monthlyData, 'completed')) ?>;

// Monthly line chart
new Chart(document.getElementById('monthlyChart'), {
  type: 'line',
  data: {
    labels: monthlyLabels,
    datasets: [
      { label:'Total Assigned', data: monthlyTotal, borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,.1)', tension:.3, fill:true },
      { label:'Completed',      data: monthlyDone,  borderColor:'#198754', backgroundColor:'rgba(25,135,84,.1)',  tension:.3, fill:true },
    ]
  },
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});

// Priority donut
new Chart(document.getElementById('priorityChart'), {
  type:'doughnut',
  data:{
    labels:['High','Medium','Low'],
    datasets:[{ data:[
      <?= count(array_filter($tasks, fn($t)=>$t['Priority']==='High')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Priority']==='Medium')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Priority']==='Low')) ?>
    ], backgroundColor:['#dc3545','#ffc107','#198754'] }]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
});

// Status pie
new Chart(document.getElementById('statusChart'), {
  type:'pie',
  data:{
    labels:['Pending','In Progress','Completed','Approved','Rejected'],
    datasets:[{ data:[
      <?= count(array_filter($tasks, fn($t)=>$t['Status']==='Pending')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Status']==='In Progress')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Status']==='Completed')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Status']==='Approved')) ?>,
      <?= count(array_filter($tasks, fn($t)=>$t['Status']==='Rejected')) ?>
    ], backgroundColor:['#6c757d','#0d6efd','#0dcaf0','#198754','#dc3545'] }]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right' } } }
});

// Employee load horizontal bar
const empNames = <?= json_encode(array_column($employees, 'Name')) ?>;
const empData  = <?= json_encode(array_map(fn($e) => employeeStats($tasks, $e['Employee_ID']), $employees)) ?>;
new Chart(document.getElementById('empLoadChart'), {
  type:'bar',
  data:{
    labels: empNames,
    datasets:[
      { label:'Total',     data: empData.map(d=>d.total),     backgroundColor:'rgba(13,110,253,.7)' },
      { label:'Completed', data: empData.map(d=>d.completed), backgroundColor:'rgba(25,135,84,.7)' },
      { label:'Pending',   data: empData.map(d=>d.pending),   backgroundColor:'rgba(255,193,7,.7)' },
    ]
  },
  options:{
    indexAxis:'y',
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'top' } },
    scales:{ x:{ beginAtZero:true } }
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
