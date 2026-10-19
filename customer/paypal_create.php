<?php
// Called by the PayPal button when the customer clicks it: creates the PayPal order for the cart total.
// Nothing is saved as a KaFoodie order yet; that only happens after the payment is captured.
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

// The amount is always worked out here from the database, never taken from the browser
$cart = kf_load_cart($con);
if (empty($cart['items'])) {
    paypal_json(['error' => 'Your cart is empty.'], 400);
}
$total = number_format($cart['total'], 2, '.', '');

$paypal_id = paypal_create_order($cfg, $total, 'KaFoodie order from ' . $cart['shop_name'], $error);
if ($paypal_id === null) {
    error_log('paypal_create failed: ' . $error);
    paypal_json(['error' => 'Could not start the PayPal payment. ' . $error], 502);
}

// Remember what this payment is for, so the capture step can check nothing changed
$_SESSION['paypal_pending'] = [
    'id'      => $paypal_id,
    'total'   => $total,
    'shop_id' => (int)$cart['shop_id'],
];

paypal_json(['id' => $paypal_id]);
