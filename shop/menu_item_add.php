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
$image = read_uploaded_image($_FILES['image'] ?? [], $upload_error, $con);
if ($upload_error) {
    fail_item($upload_error);
}

$price = (float)$price;

if ($image) {
    $data = $image['data'];
    $mime = $image['mime'];
    $stmt = $con->prepare("INSERT INTO menu_items
        (shop_id, name, description, price, category, image_url, image_data, image_mime, image_updated_at)
        VALUES (?, ?, ?, ?, ?, '', ?, ?, NOW())");
    $stmt->bind_param('issdsss', $shop_id, $name, $description, $price, $category, $data, $mime);
} else {
    $stmt = $con->prepare("INSERT INTO menu_items (shop_id, name, description, price, category, image_url)
        VALUES (?, ?, ?, ?, ?, '')");
    $stmt->bind_param('issds', $shop_id, $name, $description, $price, $category);
}
$stmt->execute();
$stmt->close();

flash('success', '“' . $name . '” was added to your menu.');
redirect_dashboard();