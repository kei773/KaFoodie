<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

const MAX_QTY_PER_ITEM = 99;

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$shop_id = (int)($_POST['shop_id'] ?? 0);

if ($item_id <= 0 || $shop_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Make sure the item really exists, is available, and belongs to that shop
$stmt = $con->prepare(
    "SELECT mi.id, mi.name
     FROM menu_items mi
     JOIN shops s ON s.id = mi.shop_id
     WHERE mi.id = ? AND mi.shop_id = ? AND mi.is_available = 1
       AND s.verification_status = 'approved'"
);
$stmt->bind_param('ii', $item_id, $shop_id);
$stmt->execute();
$valid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$valid) {
    header('Location: shop_menu.php?id=' . $shop_id);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
}

// One shop per cart: switching shops starts a fresh cart
$switched = false;
if ($_SESSION['cart']['shop_id'] !== null && (int)$_SESSION['cart']['shop_id'] !== $shop_id) {
    $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
    $switched = true;
}

$_SESSION['cart']['shop_id'] = $shop_id;

$current = (int)($_SESSION['cart']['items'][$item_id] ?? 0);
$_SESSION['cart']['items'][$item_id] = min($current + 1, MAX_QTY_PER_ITEM);

// Stay on the shop's menu and confirm with a small notification
$notice = '“' . $valid['name'] . '” added to your cart.';
if ($switched) {
    $notice .= ' Your previous cart was cleared because each order is from one shop.';
}
$_SESSION['menu_notice'] = $notice;

header('Location: shop_menu.php?id=' . $shop_id . '#item-' . $item_id);
exit;