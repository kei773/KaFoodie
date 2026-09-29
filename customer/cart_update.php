<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (isset($_SESSION['cart']['items'][$item_id])) {
    if ($action === 'increase') {
        $_SESSION['cart']['items'][$item_id]++;
    } elseif ($action === 'decrease') {
        $_SESSION['cart']['items'][$item_id]--;
        if ($_SESSION['cart']['items'][$item_id] <= 0) {
            unset($_SESSION['cart']['items'][$item_id]);
        }
    }
}

if (empty($_SESSION['cart']['items'])) {
    $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
}

header('Location: cart.php');
exit;