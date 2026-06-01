<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Custom CSS -->
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<meta name="base-url" content="<?= BASE_URL ?>">
</head>
<body>

<!-- ── Navbar ─────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark tbi-navbar sticky-top">
  <div class="container-fluid px-3">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/<?= isAdmin() ? 'admin' : 'employee' ?>/dashboard.php">
      <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="TBI-MCE" height="38"
           onerror="this.style.display='none'">
      <div class="d-none d-sm-block">
        <div class="fw-700 lh-1" style="font-size:.95rem">TBI – MCE Hassan</div>
        <div style="font-size:.65rem;opacity:.8">Task Manager</div>
      </div>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <?php if (isAdmin()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/tasks.php"><i class="bi bi-list-task me-1"></i>Tasks</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/employees.php"><i class="bi bi-people me-1"></i>Employees</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/approvals.php"><i class="bi bi-check2-circle me-1"></i>Approvals</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/analytics.php"><i class="bi bi-bar-chart me-1"></i>Analytics</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/reports.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Reports</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/employee/dashboard.php"><i class="bi bi-grid me-1"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/employee/tasks.php"><i class="bi bi-list-task me-1"></i>My Tasks</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/employee/profile.php"><i class="bi bi-person me-1"></i>Profile</a></li>
        <?php endif; ?>

        <!-- Notifications -->
        <li class="nav-item dropdown">
          <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" id="notifBell">
            <i class="bi bi-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-count d-none">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end notif-dropdown p-2" style="min-width:300px">
            <li><h6 class="dropdown-header">Notifications</h6></li>
            <li id="notifList"><div class="text-center text-muted small py-2">No new notifications</div></li>
          </ul>
        </li>

        <!-- Dark mode -->
        <li class="nav-item">
          <button class="btn btn-sm btn-outline-light ms-1" id="darkToggle" title="Toggle dark mode">
            <i class="bi bi-moon-fill"></i>
          </button>
        </li>

        <!-- User menu -->
        <li class="nav-item dropdown ms-1">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
            <div class="avatar-sm">
              <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
            </div>
            <span class="d-none d-lg-inline"><?= e($_SESSION['name'] ?? 'User') ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($_SESSION['designation'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider my-1"></li>
            <?php if (!isAdmin()): ?>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/employee/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>

      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid px-3 px-md-4 py-3">
<?php $flash = getFlash(); if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
