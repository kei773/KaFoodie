<?php
// "Reorder" on a past order: puts the same dishes in the cart (the customer reviews it before paying).
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

function reorder_back(string $message): void {
    $_SESSION['order_error'] = $message;
    header('Location: orders.php?tab=past');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php?tab=past');
    exit;
}
if (!hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
    reorder_back('Your session expired. Please try again.');
}

$customer_id = (int)$_SESSION['user_id'];
$order_id    = (int)($_POST['order_id'] ?? 0);

// Must be this customer's own order, from a shop that is still approved
$stmt = $con->prepare(
    "SELECT o.shop_id, s.shop_name
     FROM orders o
     JOIN shops s ON s.id = o.shop_id
     WHERE o.id = ? AND o.customer_id = ? AND s.verification_status = 'approved'"
);
$stmt->bind_param('ii', $order_id, $customer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) {
    reorder_back('That order cannot be reordered right now.');
}
$shop_id = (int)$order['shop_id'];

// The order's dishes that still exist and are still available
$stmt = $con->prepare(
    "SELECT oi.menu_item_id, oi.quantity, (mi.id IS NOT NULL AND mi.is_available = 1) AS available
     FROM order_items oi
     LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
     WHERE oi.order_id = ?"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items   = [];
$skipped = 0;
foreach ($stmt->get_result() as $row) {
    if (!empty($row['menu_item_id']) && (int)$row['available'] === 1) {
        $id = (int)$row['menu_item_id'];
        $items[$id] = ($items[$id] ?? 0) + max(1, (int)$row['quantity']);
    } else {
        $skipped++;
    }
}
$stmt->close();

if (!$items) {
    reorder_back('None of the dishes from that order are available right now.');
}

$had_other_cart = !empty($_SESSION['cart']['items']) && (int)($_SESSION['cart']['shop_id'] ?? 0) !== $shop_id;

$_SESSION['cart'] = ['shop_id' => $shop_id, 'items' => $items];

$note = 'The dishes from your earlier order are in your cart. Check them before you pay.';
if ($skipped > 0) {
    $note .= ' ' . $skipped . ($skipped === 1 ? ' dish is' : ' dishes are') . ' no longer available and was left out.';
}
if ($had_other_cart) {
    $note .= ' Your previous cart from another shop was replaced.';
}
$_SESSION['cart_notice'] = $note;

header('Location: cart.php');
exit;
