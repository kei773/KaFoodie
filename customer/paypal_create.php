<?php
// Called by the PayPal button on the checkout page when the customer clicks it.
// Rebuilds the cart from the database, creates the PayPal sandbox order for that exact total,
// remembers what was asked, and hands PayPal's order id back to the page as JSON.
// paypal_capture.php later checks that the cart still matches before it saves the order.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cart_helpers.php';
require_once __DIR__ . '/../includes/paypal_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    paypal_json(['error' => 'Please log in again.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
    paypal_json(['error' => 'Your session expired. Please refresh the page.'], 400);
}

$cfg = paypal_config();
if (!$cfg) {
    paypal_json(['error' => 'PayPal is not set up yet.'], 503);
}

// Prices and availability always come from the database, never from the browser
$cart = kf_load_cart($con);
if (empty($cart['items'])) {
    paypal_json(['error' => 'Your cart is empty.'], 400);
}

$total = number_format((float)$cart['total'], 2, '.', '');
if ((float)$total <= 0) {
    paypal_json(['error' => 'Your cart total is not valid.'], 400);
}

$error = null;
$paypal_id = paypal_create_order($cfg, $total, 'KaFoodie order from ' . $cart['shop_name'], $error);
if ($paypal_id === null) {
    error_log('paypal_create failed: ' . $error);
    paypal_json(['error' => 'Could not start the payment. ' . $error], 502);
}

// paypal_capture.php compares the cart against this before taking the money
$_SESSION['paypal_pending'] = [
    'id'      => $paypal_id,
    'total'   => $total,
    'shop_id' => (int)$cart['shop_id'],
];

paypal_json(['id' => $paypal_id]);
