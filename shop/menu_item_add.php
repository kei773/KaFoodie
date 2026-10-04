<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

$shop = require_shop($con);
require_post_csrf();

$shop_id     = (int)$shop['id'];
$name        = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
$price       = trim($_POST['price'] ?? '');
$category    = mb_substr(trim($_POST['category'] ?? ''), 0, 50);
$description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);

function fail_item(string $message): void {
    global $name, $price, $category, $description;
    flash('error', $message);
    $_SESSION['item_old'] = compact('name', 'price', 'category', 'description');
    redirect_dashboard('add');
}

if ($name === '' || $price === '') {
    fail_item('Item name and price are required.');
}
if (!is_numeric($price) || $price < 0) {
    fail_item('Please enter a valid price.');
}

$upload_error = null;
$image_url    = save_uploaded_image($_FILES['image'] ?? [], 'menu', $upload_error);
if ($upload_error) {
    fail_item($upload_error);
}
$image_url = $image_url ?? '';

$price = (float)$price;
$stmt = $con->prepare("INSERT INTO menu_items (shop_id, name, description, price, category, image_url) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('issdss', $shop_id, $name, $description, $price, $category, $image_url);
$stmt->execute();
$stmt->close();

flash('success', '“' . $name . '” was added to your menu.');
redirect_dashboard();