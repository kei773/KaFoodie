<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$shop_id = (int)($_POST['shop_id'] ?? 0);

if ($item_id <= 0 || $shop_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
}

if ($_SESSION['cart']['shop_id'] !== null && $_SESSION['cart']['shop_id'] != $shop_id) {
    $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
    $_SESSION['cart_notice'] = "Starting a new cart — your previous shop's items were cleared.";
}

$_SESSION['cart']['shop_id'] = $shop_id;

if (isset($_SESSION['cart']['items'][$item_id])) {
    $_SESSION['cart']['items'][$item_id]++;
} else {
    $_SESSION['cart']['items'][$item_id] = 1;
}

header('Location: cart.php');
exit;