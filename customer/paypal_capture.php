<?php
// Called after the customer approves the payment in the PayPal window.
// Takes the money, checks it is really the right amount, and only THEN saves the order (as paid).
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cart_helpers.php';
require_once __DIR__ . '/../includes/order_helpers.php';
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

$customer_id = (int)$_SESSION['user_id'];
$paypal_id   = (string)($_POST['orderID'] ?? '');
$pending     = $_SESSION['paypal_pending'] ?? null;

if (!$pending || $paypal_id === '' || !hash_equals((string)$pending['id'], $paypal_id)) {
    // A repeated call for a payment that already finished: just send the customer on
    if ($paypal_id !== '' && hash_equals((string)($_SESSION['paypal_last_done'] ?? ''), $paypal_id)) {
        paypal_json(['ok' => true, 'redirect' => 'orders.php']);
    }
    paypal_json(['error' => 'This payment is not in progress. Please try again.'], 400);
}

// The cart must still be exactly what the customer was asked to pay for
$cart = kf_load_cart($con);
if (empty($cart['items'])
    || number_format($cart['total'], 2, '.', '') !== $pending['total']
    || (int)$cart['shop_id'] !== (int)$pending['shop_id']) {
    unset($_SESSION['paypal_pending']);
    paypal_json(['error' => 'Your cart changed while PayPal was open. Nothing was charged. Please check your cart and try again.'], 409);
}

$result = paypal_capture_order($cfg, $paypal_id, $error);
if ($result === null) {
    error_log('paypal_capture failed for ' . $paypal_id . ': ' . $error);
    paypal_json(['error' => 'We could not complete the payment. ' . $error], 502);
}

$capture = $result['purchase_units'][0]['payments']['captures'][0] ?? null;
$status  = $capture['status'] ?? '';
$paid    = (string)($capture['amount']['value'] ?? '');
$curr    = (string)($capture['amount']['currency_code'] ?? '');

if (($result['status'] ?? '') !== 'COMPLETED' || $status !== 'COMPLETED') {
    error_log('paypal capture not completed for ' . $paypal_id . ': ' . ($result['status'] ?? '?') . ' / ' . $status);
    paypal_json(['error' => 'PayPal has not completed this payment (status: ' . ($status ?: 'unknown') . '). Please try again.'], 402);
}
if (abs((float)$paid - (float)$pending['total']) > 0.005 || $curr !== $cfg['currency']) {
    error_log('paypal amount mismatch for ' . $paypal_id . ': paid ' . $paid . ' ' . $curr . ', expected ' . $pending['total']);
    paypal_json(['error' => 'The amount PayPal took did not match your order. Please contact support with reference '
        . ($capture['id'] ?? $paypal_id) . '.'], 409);
}

$transaction_id = (string)$capture['id'];

try {
    $created    = order_create($con, $customer_id, $cart, 'paypal_sandbox', 'paid', $transaction_id);
    $order_id   = $created['id'];
    $order_code = $created['code'];
} catch (Throwable $e) {
    // If this same payment was already saved (a retry), carry on with that order
    $stmt = $con->prepare("SELECT id, pickup_code FROM orders WHERE transaction_id = ? AND customer_id = ?");
    $stmt->bind_param('si', $transaction_id, $customer_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $order_id   = (int)$existing['id'];
        $order_code = (string)$existing['pickup_code'];
    } else {
        error_log('paypal order save failed after payment ' . $transaction_id . ': ' . $e->getMessage());
        paypal_json(['error' => 'Your payment went through (reference ' . $transaction_id
            . ') but we could not save the order. Please contact support with this reference.'], 500);
    }
}

// Tell the shop owner. A failure here never undoes the order.
$who = trim((string)($_SESSION['name'] ?? '')) ?: 'a customer';
order_notify($con, (int)$cart['owner_id'], $order_id, 'New paid order ' . $order_code . ' from ' . $who . '.');

$_SESSION['cart'] = ['shop_id' => null, 'items' => []];
unset($_SESSION['paypal_pending']);
$_SESSION['paypal_last_done'] = $paypal_id;
$_SESSION['order_notice'] = 'Payment received. Your pickup code is ' . $order_code . '. ' . $cart['shop_name'] . ' will start preparing it soon.';

paypal_json(['ok' => true, 'redirect' => 'orders.php']);
