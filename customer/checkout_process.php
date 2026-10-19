<?php
// "Pay at the shop" orders. (PayPal orders are saved by paypal_capture.php after the payment succeeds.)
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cart_helpers.php';
require_once __DIR__ . '/../includes/order_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

function checkout_fail(string $message): void {
    $_SESSION['checkout_error'] = $message;
    header('Location: checkout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
    checkout_fail('Your session expired. Please try again.');
}

if (($_POST['payment_method'] ?? '') !== 'pay_at_shop') {
    checkout_fail('Please choose a payment option.');
}

// Rebuild the cart from the database so prices and availability are always current
$cart = kf_load_cart($con);
if (empty($cart['items'])) {
    header('Location: cart.php');
    exit;
}

try {
    $created    = order_create($con, (int)$_SESSION['user_id'], $cart, 'pay_at_shop', 'pending');
    $order_id   = $created['id'];
    $order_code = $created['code'];
} catch (Throwable $e) {
    error_log('checkout_process failed: ' . $e->getMessage());
    checkout_fail('We could not place your order. Please try again.');
}

// Tell the shop owner. A failure here never undoes the order.
$who = trim((string)($_SESSION['name'] ?? '')) ?: 'a customer';
order_notify($con, (int)$cart['owner_id'], $order_id, 'New order ' . $order_code . ' from ' . $who . '.');

$_SESSION['cart'] = ['shop_id' => null, 'items' => []];
$_SESSION['order_notice'] = 'Order placed. Your pickup code is ' . $order_code . '. ' . $cart['shop_name'] . ' will start preparing it soon.';

header('Location: orders.php');
exit;