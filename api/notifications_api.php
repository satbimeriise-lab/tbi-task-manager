<?php
// ============================================================
//  Notifications API — JSON endpoint
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
startSession();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/GoogleSheetsService.php';
$sheets  = new GoogleSheetsService();
$myEmpId = $_SESSION['employee_id'];
$action  = $_GET['action'] ?? '';

if ($action === 'unread') {
    $notifs = $sheets->findMany(SHEET_NOTIFICATIONS, 'User_ID', $myEmpId);
    $unread = array_values(array_filter($notifs, fn($n) => $n['Read_Status'] === 'unread'));
    $unread = array_slice(array_reverse($unread), 0, 10);

    echo json_encode([
        'count' => count($unread),
        'notifications' => array_map(fn($n) => [
            'id'          => $n['Notif_ID'],
            'message'     => $n['Message'],
            'type'        => $n['Type'],
            'read_status' => $n['Read_Status'],
            'created_at'  => $n['Created_At'],
        ], $unread)
    ]);
    exit;
}

if ($action === 'mark_read') {
    $id = $_GET['id'] ?? '';
    if ($id) {
        $sheets->updateById(SHEET_NOTIFICATIONS, 'Notif_ID', $id, ['Read_Status' => 'read']);
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
