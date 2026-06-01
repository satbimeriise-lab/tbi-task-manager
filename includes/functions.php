<?php
// ============================================================
//  Shared utility functions
// ============================================================

require_once __DIR__ . '/../config/config.php';

// ── Session bootstrap ────────────────────────────────────────
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => false,   // set true when on HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    // Regenerate ID periodically to prevent fixation
    if (!isset($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }

    // Idle timeout
    if (isset($_SESSION['_last_active'])) {
        if (time() - $_SESSION['_last_active'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . '/index.php?timeout=1');
            exit;
        }
    }
    $_SESSION['_last_active'] = time();
}

// ── Auth helpers ─────────────────────────────────────────────
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && in_array($_SESSION['designation'] ?? '', ADMIN_ROLES);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/employee/dashboard.php?error=access_denied');
        exit;
    }
}

// ── CSRF ─────────────────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

// ── Output sanitisation ──────────────────────────────────────
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Date helpers ─────────────────────────────────────────────
function daysPending(string $deadline): int
{
    if (!$deadline) return 0;
    try {
        $dl   = new DateTime($deadline);
        $now  = new DateTime();
        return $now > $dl ? (int)$now->diff($dl)->days : 0;
    } catch (Exception) {
        return 0;
    }
}

function isOverdue(string $deadline, string $status): bool
{
    if (in_array($status, ['Approved', 'Completed'])) return false;
    return daysPending($deadline) > 0;
}

function formatDate(string $date): string
{
    if (!$date) return '—';
    try {
        return (new DateTime($date))->format('d M Y');
    } catch (Exception) {
        return $date;
    }
}

// ── Priority badge HTML ───────────────────────────────────────
function priorityBadge(string $p): string
{
    $map = ['High' => 'danger', 'Medium' => 'warning text-dark', 'Low' => 'success'];
    $cls = $map[$p] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . e($p) . '</span>';
}

// ── Status badge HTML ─────────────────────────────────────────
function statusBadge(string $s): string
{
    $map = [
        'Pending'     => 'secondary',
        'In Progress' => 'primary',
        'Completed'   => 'info text-dark',
        'Approved'    => 'success',
        'Rejected'    => 'danger',
    ];
    $cls = $map[$s] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . e($s) . '</span>';
}

// ── Generate unique ID ─────────────────────────────────────────
function generateId(string $prefix): string
{
    return strtoupper($prefix) . '_' . date('Ymd') . '_' . strtoupper(substr(uniqid('', true), -6));
}

// ── Redirect helper ────────────────────────────────────────────
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// ── Flash message ─────────────────────────────────────────────
function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function renderFlash(): string
{
    $f = getFlash();
    if (!$f) return '';
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'danger'  => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
         . e($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

// ── Employee stats helper ─────────────────────────────────────
function employeeStats(array $tasks, string $employeeId): array
{
    $mine = array_filter($tasks, fn($t) => $t['Employee_ID'] === $employeeId);
    $total     = count($mine);
    $completed = count(array_filter($mine, fn($t) => in_array($t['Status'], ['Completed', 'Approved'])));
    $approved  = count(array_filter($mine, fn($t) => $t['Status'] === 'Approved'));
    $pending   = count(array_filter($mine, fn($t) => in_array($t['Status'], ['Pending', 'In Progress'])));
    $rejected  = count(array_filter($mine, fn($t) => $t['Status'] === 'Rejected'));
    $overdue   = count(array_filter($mine, fn($t) => isOverdue($t['Deadline'] ?? '', $t['Status'] ?? '')));
    $approvalPct = $total > 0 ? round(($approved / $total) * 100) : 0;

    return compact('total', 'completed', 'approved', 'pending', 'rejected', 'overdue', 'approvalPct');
}
