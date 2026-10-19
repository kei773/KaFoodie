<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

require_shop($con);
require_post_csrf();

$owner_id     = (int)$_SESSION['user_id'];
$item_id      = (int)($_POST['item_id'] ?? 0);
$name         = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
$price        = trim($_POST['price'] ?? '');
$category     = mb_substr(trim($_POST['category'] ?? ''), 0, 50);
$description  = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
$remove_image = !empty($_POST['remove_image']);

// The item must belong to this owner
$stmt = $con->prepare("
    SELECT mi.image_url
    FROM menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ii', $item_id, $owner_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    flash('error', 'That menu item could not be found.');
    redirect_dashboard('menu');
}

if ($name === '' || $price === '') {
    flash('error', 'Item name and price are required.');
    redirect_dashboard('menu');
}
if (!is_numeric($price) || $price < 0) {
    flash('error', 'Please enter a valid price.');
    redirect_dashboard('menu');
}

// Photo: keep the current one unless a new file was chosen or "remove" was ticked
$image_url    = $item['image_url'];
$upload_error = null;
$new_image    = save_uploaded_image($_FILES['image'] ?? [], 'menu', $upload_error);

if ($upload_error) {
    flash('error', $upload_error);
    redirect_dashboard('menu');
}
if ($new_image) {
    $image_url = $new_image;
} elseif ($remove_image) {
    $image_url = '';
}

$price = (float)$price;
$stmt = $con->prepare("
    UPDATE menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    SET mi.name = ?, mi.description = ?, mi.price = ?, mi.category = ?, mi.image_url = ?
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ssdssii', $name, $description, $price, $category, $image_url, $item_id, $owner_id);
$stmt->execute();
$stmt->close();

if ($image_url !== $item['image_url']) {
    delete_local_image($item['image_url']);
}

flash('success', '“' . $name . '” was updated.');
redirect_dashboard('menu');