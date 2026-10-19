<?php
// Feature or un-feature a shop or a dish from the admin dashboard.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_stats.php';

require_admin();

function feature_back(string $type, string $message): void {
    $_SESSION[$type === 'ok' ? 'admin_success' : 'admin_error'] = $message;
    header('Location: dashboard.php#featured');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
if (!admin_csrf_ok()) {
    feature_back('err', 'Your session expired. Please try again.');
}

$kind   = $_POST['kind'] ?? '';
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if (!in_array($kind, ['shop', 'dish'], true) || !in_array($action, ['feature', 'unfeature'], true) || $id < 1) {
    feature_back('err', 'That request was not valid.');
}

$table = $kind === 'shop' ? 'shops' : 'menu_items';
$col   = $kind === 'shop' ? 'shop_name' : 'name';

if ($action === 'feature') {
    // Only shops and dishes that are really high rated right now can be featured
    $name = admin_qualifying_name($con, $kind, $id);
    if ($name === null) {
        feature_back('err', 'Only high rated items can be featured (at least ' . MIN_RATINGS_FOR_TOP
            . ' ratings and an average of ' . number_format(HIGH_RATING_AVG, 1) . ' or more).');
    }
    $flag = 1;
} else {
    $stmt = $con->prepare("SELECT $col AS n FROM $table WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $name = $stmt->get_result()->fetch_assoc()['n'] ?? null;
    $stmt->close();
    if ($name === null) {
        feature_back('err', 'That item was not found.');
    }
    $flag = 0;
}

$stmt = $con->prepare("UPDATE $table SET is_featured = ? WHERE id = ?");
$stmt->bind_param('ii', $flag, $id);
$stmt->execute();
$stmt->close();

$what = $kind === 'shop' ? 'shop' : 'dish';
feature_back('ok', $flag === 1
    ? $name . ' is now a featured ' . $what . '.'
    : $name . ' is no longer featured.');
