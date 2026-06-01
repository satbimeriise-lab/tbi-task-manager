<?php
// ============================================================
//  TBI-MCE Task Manager — Central Configuration
//  Values can be overridden by environment variables
//  (set them in Railway / Render / .env for local dev)
// ============================================================

// ── Helper: env with fallback ────────────────────────────────
function env(string $key, string $default = ''): string {
    return getenv($key) !== false ? getenv($key) : $default;
}

// ── Application ──────────────────────────────────────────────
define('APP_NAME',    'TBI-MCE Task Manager');
define('APP_TITLE',   'Technology Business Incubator – MCE Hassan');
define('APP_VERSION', '1.0.0');

// BASE_URL: empty string when deployed at domain root (Railway/Render/000webhost)
//           '/tbi_task_manager' if placed in a subdirectory on shared hosting
define('BASE_URL', env('BASE_URL', ''));

// ── Google Sheets ─────────────────────────────────────────────
define('SPREADSHEET_ID', env('SPREADSHEET_ID', 'YOUR_SPREADSHEET_ID_HERE'));

// credentials.json can be provided as a file OR as a base64 env var
// (base64 approach is easier on Railway/Render where file upload is awkward)
$_credPath = __DIR__ . '/credentials.json';
if (!file_exists($_credPath) && getenv('GOOGLE_CREDENTIALS_BASE64')) {
    $decoded = base64_decode(getenv('GOOGLE_CREDENTIALS_BASE64'));
    if ($decoded) file_put_contents($_credPath, $decoded);
}
define('CREDENTIALS_PATH', $_credPath);
unset($_credPath);

// ── Sheet Names ──────────────────────────────────────────────
define('SHEET_EMPLOYEES',     'Employees');
define('SHEET_TASKS',         'Tasks');
define('SHEET_APPROVALS',     'Approvals');
define('SHEET_USERS',         'Users');
define('SHEET_NOTIFICATIONS', 'Notifications');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',    'TBI_SESS');
define('SESSION_TIMEOUT', 7200);

// ── Email (PHPMailer / Gmail SMTP) ────────────────────────────
define('SMTP_HOST',  env('SMTP_HOST',  'smtp.gmail.com'));
define('SMTP_PORT',  (int) env('SMTP_PORT', '587'));
define('SMTP_USER',  env('SMTP_USER',  'your-email@gmail.com'));
define('SMTP_PASS',  env('SMTP_PASS',  'your-app-password'));
define('FROM_EMAIL', env('FROM_EMAIL', 'noreply@tbi-mce.ac.in'));
define('FROM_NAME',  'TBI-MCE Task Manager');

// ── File Uploads ─────────────────────────────────────────────
define('UPLOAD_DIR',         __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE',    10 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip']);

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Employee Designations ─────────────────────────────────────
define('DESIGNATIONS', [
    'CEO', 'COO', 'Software Associate',
    'Finance Associate', 'Innovation Associate', 'Supporting Staff',
]);
define('ADMIN_ROLES',    ['CEO', 'COO']);
define('PRIORITIES',     ['High', 'Medium', 'Low']);
define('TASK_STATUSES',  ['Pending', 'In Progress', 'Completed', 'Approved', 'Rejected']);
