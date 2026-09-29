<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];

$stmt = $con->prepare("SELECT id FROM shops WHERE owner_id = ?");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();
$shop_id = $shop['id'];

$name        = trim($_POST['name'] ?? '');
$price       = $_POST['price'] ?? '';
$category    = trim($_POST['category'] ?? '');
$image_url   = trim($_POST['image_url'] ?? '');
$description = trim($_POST['description'] ?? '');

function fail_item($message, $old) {
    $_SESSION['item_error'] = $message;
    $_SESSION['item_old'] = $old;
    header('Location: dashboard.php');
    exit;
}

$old = compact('name', 'price', 'category', 'image_url', 'description');

if ($name === '' || $price === '') {
    fail_item('Item name and price are required.', $old);
}

if (!is_numeric($price) || $price < 0) {
    fail_item('Please enter a valid price.', $old);
}

$stmt = $con->prepare("INSERT INTO menu_items (shop_id, name, description, price, category, image_url) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('issdss', $shop_id, $name, $description, $price, $category, $image_url);
$stmt->execute();
$stmt->close();

header('Location: dashboard.php');
exit;