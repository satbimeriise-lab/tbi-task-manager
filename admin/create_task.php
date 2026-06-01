<?php
// ============================================================
//  Admin — Create / Edit Task
// ============================================================
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../api/GoogleSheetsService.php';

$sheets    = new GoogleSheetsService();
$employees = $sheets->getAll(SHEET_EMPLOYEES);

$editId = $_GET['edit'] ?? '';
$task   = $editId ? $sheets->findOne(SHEET_TASKS, 'Task_ID', $editId) : null;
$isEdit = (bool)$task;

// ── Handle form submit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $employeeId  = $_POST['employee_id']  ?? '';
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority']    ?? 'Medium';
    $startDate   = $_POST['start_date']  ?? date('Y-m-d');
    $deadline    = $_POST['deadline']    ?? '';
    $status      = $isEdit ? ($_POST['status'] ?? 'Pending') : 'Pending';

    if (!$employeeId || !$title || !$deadline) {
        setFlash('danger', 'Employee, Title, and Deadline are required.');
        redirect(BASE_URL . '/admin/create_task.php' . ($isEdit ? '?edit=' . urlencode($editId) : ''));
    }

    // Handle file upload
    $fileUrl = $task['File_URL'] ?? '';
    if (!empty($_FILES['attachment']['name'])) {
        $origName  = $_FILES['attachment']['name'];
        $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (in_array($ext, ALLOWED_EXTENSIONS) && $_FILES['attachment']['size'] <= MAX_UPLOAD_SIZE) {
            $newName = generateId('FILE') . '.' . $ext;
            $dest    = UPLOAD_DIR . $newName;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                $fileUrl = BASE_URL . '/uploads/' . $newName;
            }
        }
    }

    if ($isEdit) {
        $sheets->updateById(SHEET_TASKS, 'Task_ID', $editId, [
            'Employee_ID'   => $employeeId,
            'Task_Title'    => $title,
            'Description'   => $description,
            'Priority'      => $priority,
            'Assigned_Date' => $startDate,
            'Deadline'      => $deadline,
            'Status'        => $status,
            'File_URL'      => $fileUrl,
            'Assigned_By'   => $_SESSION['name'],
        ]);
        setFlash('success', 'Task updated successfully.');
    } else {
        $taskId = generateId('TSK');
        $sheets->appendRow(SHEET_TASKS, [
            $taskId,       // Task_ID
            $employeeId,   // Employee_ID
            $title,        // Task_Title
            $description,  // Description
            $priority,     // Priority
            $startDate,    // Assigned_Date
            $deadline,     // Deadline
            'Pending',     // Status
            0,             // Days_Pending
            $_SESSION['name'], // Assigned_By
            $fileUrl,      // File_URL
            '',            // Notes
        ]);

        // Create notification
        $sheets->appendRow(SHEET_NOTIFICATIONS, [
            generateId('NOTIF'),
            $employeeId,
            "New task assigned: $title",
            'task_assigned',
            'unread',
            date('Y-m-d H:i:s'),
        ]);

        setFlash('success', 'Task created and assigned successfully.');
    }
    redirect(BASE_URL . '/admin/tasks.php');
}

$pageTitle = $isEdit ? 'Edit Task' : 'Create Task';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h4 class="fw-700 mb-0">
    <i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle' ?> me-2 text-primary"></i>
    <?= $isEdit ? 'Edit Task' : 'Create New Task' ?>
  </h4>
</div>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

          <div class="row g-3">

            <!-- Employee -->
            <div class="col-12">
              <label class="form-label fw-500">Assign To <span class="text-danger">*</span></label>
              <select class="form-select" name="employee_id" required>
                <option value="">Select Employee…</option>
                <?php
                $order = ['CEO','COO','Software Associate','Finance Associate','Innovation Associate','Supporting Staff'];
                usort($employees, fn($a,$b) => array_search($a['Designation'],$order) <=> array_search($b['Designation'],$order));
                foreach ($employees as $emp): ?>
                <option value="<?= e($emp['Employee_ID']) ?>"
                  <?= ($task['Employee_ID'] ?? '') === $emp['Employee_ID'] ? 'selected' : '' ?>>
                  <?= e($emp['Name']) ?> — <?= e($emp['Designation']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Title -->
            <div class="col-12">
              <label class="form-label fw-500">Task Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="title"
                     value="<?= e($task['Task_Title'] ?? '') ?>"
                     placeholder="Enter task title" required maxlength="200">
            </div>

            <!-- Description -->
            <div class="col-12">
              <label class="form-label fw-500">Description</label>
              <textarea class="form-control" name="description" rows="4"
                        placeholder="Detailed task description…"><?= e($task['Description'] ?? '') ?></textarea>
            </div>

            <!-- Priority -->
            <div class="col-6 col-md-4">
              <label class="form-label fw-500">Priority</label>
              <select class="form-select" name="priority">
                <?php foreach (PRIORITIES as $p): ?>
                <option value="<?= $p ?>" <?= ($task['Priority'] ?? 'Medium') === $p ? 'selected' : '' ?>>
                  <?= $p ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Start Date -->
            <div class="col-6 col-md-4">
              <label class="form-label fw-500">Start Date</label>
              <input type="date" class="form-control" name="start_date"
                     value="<?= e($task['Assigned_Date'] ?? date('Y-m-d')) ?>">
            </div>

            <!-- Deadline -->
            <div class="col-6 col-md-4">
              <label class="form-label fw-500">Deadline <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="deadline"
                     value="<?= e($task['Deadline'] ?? '') ?>"
                     min="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- Status (edit only) -->
            <?php if ($isEdit): ?>
            <div class="col-6 col-md-4">
              <label class="form-label fw-500">Status</label>
              <select class="form-select" name="status">
                <?php foreach (TASK_STATUSES as $s): ?>
                <option value="<?= $s ?>" <?= ($task['Status'] ?? '') === $s ? 'selected' : '' ?>>
                  <?= $s ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <!-- File Attachment -->
            <div class="col-12">
              <label class="form-label fw-500">Attachment (PDF / DOC / Image)</label>
              <input type="file" class="form-control" name="attachment"
                     accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip">
              <?php if (!empty($task['File_URL'])): ?>
              <div class="mt-1 small text-muted">
                Current: <a href="<?= e($task['File_URL']) ?>" target="_blank"><?= e(basename($task['File_URL'])) ?></a>
              </div>
              <?php endif; ?>
              <div class="form-text">Max 10 MB. Allowed: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, ZIP</div>
            </div>

          </div><!-- /row -->

          <div class="d-flex gap-3 mt-4">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-<?= $isEdit ? 'save' : 'plus-circle' ?> me-2"></i>
              <?= $isEdit ? 'Save Changes' : 'Create Task' ?>
            </button>
            <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
