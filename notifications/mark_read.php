<?php
// Called by the bell when it is opened. Marks the signed-in user's notifications as read.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['customer', 'shop'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals($_SESSION['csrf_notif'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    notif_mark_all_read($con, (int)$_SESSION['user_id']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('mark_read failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
