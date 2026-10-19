<?php
// Saves a rating from the "Ratings" tab on My orders.
// A customer can rate a shop, and the dishes they received, once they have a COMPLETED order there.
// One rating per shop and per dish: saving again edits the earlier one.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

function rate_back(string $type, string $message, int $shop_id = 0): void {
    $_SESSION[$type === 'ok' ? 'order_notice' : 'order_error'] = $message;
    header('Location: orders.php?tab=ratings' . ($shop_id > 0 ? '#shop-' . $shop_id : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php?tab=ratings');
    exit;
}
if (!hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
    rate_back('err', 'Your session expired. Please try again.');
}

$customer_id = (int)$_SESSION['user_id'];
$shop_id     = (int)($_POST['shop_id'] ?? 0);

// The customer must have a completed order at this shop
$stmt = $con->prepare("SELECT 1 FROM orders WHERE customer_id = ? AND shop_id = ? AND status = 'completed' LIMIT 1");
$stmt->bind_param('ii', $customer_id, $shop_id);
$stmt->execute();
$eligible = $stmt->get_result()->num_rows > 0;
$stmt->close();
if (!$eligible) {
    rate_back('err', 'You can rate a shop after one of your orders there is completed.');
}

// Dishes this customer has received from this shop (only these can be rated)
$stmt = $con->prepare(
    "SELECT DISTINCT oi.menu_item_id
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.customer_id = ? AND o.shop_id = ? AND o.status = 'completed' AND oi.menu_item_id IS NOT NULL"
);
$stmt->bind_param('ii', $customer_id, $shop_id);
$stmt->execute();
$allowed = [];
foreach ($stmt->get_result() as $r) {
    $allowed[(int)$r['menu_item_id']] = true;
}
$stmt->close();

function clean_stars($value): int {
    $n = filter_var($value, FILTER_VALIDATE_INT);
    return ($n !== false && $n >= 1 && $n <= 5) ? (int)$n : 0;
}

$shop_stars   = clean_stars($_POST['shop_stars'] ?? 0);
$shop_comment = mb_substr(trim((string)($_POST['shop_comment'] ?? '')), 0, 500);
$shop_comment = ($shop_comment === '') ? null : $shop_comment;

$dish_stars = [];
$posted     = $_POST['dish_stars'] ?? [];
if (is_array($posted)) {
    foreach ($posted as $menu_item_id => $value) {
        $menu_item_id = (int)$menu_item_id;
        $stars        = clean_stars($value);
        if ($stars > 0 && isset($allowed[$menu_item_id])) {
            $dish_stars[$menu_item_id] = $stars;
        }
    }
}

if ($shop_stars === 0 && !$dish_stars) {
    rate_back('err', 'Choose at least one star rating before saving.', $shop_id);
}

try {
    $con->begin_transaction();

    if ($shop_stars > 0) {
        $stmt = $con->prepare(
            "INSERT INTO shop_ratings (shop_id, customer_id, stars, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE stars = VALUES(stars), comment = VALUES(comment)"
        );
        $stmt->bind_param('iiis', $shop_id, $customer_id, $shop_stars, $shop_comment);
        $stmt->execute();
        $stmt->close();
    }

    if ($dish_stars) {
        $stmt = $con->prepare(
            "INSERT INTO food_ratings (menu_item_id, customer_id, stars) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE stars = VALUES(stars)"
        );
        foreach ($dish_stars as $menu_item_id => $stars) {
            $stmt->bind_param('iii', $menu_item_id, $customer_id, $stars);
            $stmt->execute();
        }
        $stmt->close();
    }

    $con->commit();
} catch (Throwable $e) {
    $con->rollback();
    error_log('rate_process failed: ' . $e->getMessage());
    rate_back('err', 'We could not save your ratings. Please try again.', $shop_id);
}

rate_back('ok', 'Thanks! Your ratings were saved.', $shop_id);