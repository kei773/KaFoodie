<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/order_helpers.php';

$shop    = require_shop($con);
$shop_id = (int)$shop['id'];

$view = $_POST['view'] ?? 'active';
if (!in_array($view, ['active', 'completed', 'all'], true)) { $view = 'active'; }

function status_back(string $type, string $msg, string $view): void {
    flash($type, $msg);
    header('Location: orders.php?view=' . urlencode($view));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}
if (!hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
    status_back('error', 'Your session expired. Please try again.', $view);
}

$order_id = (int)($_POST['order_id'] ?? 0);
$from     = (string)($_POST['from'] ?? '');

// The order must belong to this shop
$stmt = $con->prepare("SELECT id, pickup_code, customer_id, status, payment_method FROM orders WHERE id = ? AND shop_id = ?");
$stmt->bind_param('ii', $order_id, $shop_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    status_back('error', 'That order was not found.', $view);
}
// Someone (or another tab) already moved it: do nothing, so a stale click cannot skip a step
if ($order['status'] !== $from) {
    status_back('error', 'Order ' . $order['pickup_code'] . ' was already updated. The list is refreshed.', $view);
}

$to = ORDER_NEXT_STATUS[$order['status']] ?? null;
if ($to === null) {
    status_back('error', 'That order is already completed.', $view);
}

if ($to === 'completed') {
    // Paying at the shop: the order counts as paid once it is handed over
    $stmt = $con->prepare(
        "UPDATE orders
         SET status = 'completed', completed_at = NOW(),
             payment_status = IF(payment_method = 'pay_at_shop', 'paid', payment_status)
         WHERE id = ? AND shop_id = ? AND status = ?"
    );
    $stmt->bind_param('iis', $order_id, $shop_id, $from);
} else {
    $stmt = $con->prepare("UPDATE orders SET status = ? WHERE id = ? AND shop_id = ? AND status = ?");
    $stmt->bind_param('siis', $to, $order_id, $shop_id, $from);
}
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();

if ($changed < 1) {
    status_back('error', 'Nothing was changed. Please try again.', $view);
}

// Let the customer know
$shop_name = $shop['shop_name'];
$code      = $order['pickup_code'];
$messages = [
    'preparing' => "Order $code: $shop_name is now preparing your order.",
    'ready'     => "Order $code is ready for pickup at $shop_name. Show this code at the shop.",
    'completed' => "Order $code is completed. Thanks for ordering from $shop_name!",
];
order_notify($con, (int)$order['customer_id'], $order_id, $messages[$to]);

// "Ready for pickup" also goes out by email. A failure is only logged and never blocks the update.
if ($to === 'ready') {
    order_send_ready_email($con, $order_id);
}

status_back('success', 'Order ' . $code . ' is now: ' . order_status_label($to) . '.', $view);
