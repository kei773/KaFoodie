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
    SELECT mi.id
    FROM menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ii', $item_id, $owner_id);
$stmt->execute();
$found = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$found) {
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

// Photo: replaced only when a new file was chosen, cleared only when "remove" was ticked
$upload_error = null;
$image = read_uploaded_image($_FILES['image'] ?? [], $upload_error, $con);
if ($upload_error) {
    flash('error', $upload_error);
    redirect_dashboard('menu');
}

$price = (float)$price;

if ($image) {
    $data = $image['data'];
    $mime = $image['mime'];
    $stmt = $con->prepare("
        UPDATE menu_items mi
        JOIN shops s ON mi.shop_id = s.id
        SET mi.name = ?, mi.description = ?, mi.price = ?, mi.category = ?,
            mi.image_url = '', mi.image_data = ?, mi.image_mime = ?, mi.image_updated_at = NOW()
        WHERE mi.id = ? AND s.owner_id = ?
    ");
    $stmt->bind_param('ssdsssii', $name, $description, $price, $category, $data, $mime, $item_id, $owner_id);
} elseif ($remove_image) {
    $stmt = $con->prepare("
        UPDATE menu_items mi
        JOIN shops s ON mi.shop_id = s.id
        SET mi.name = ?, mi.description = ?, mi.price = ?, mi.category = ?,
            mi.image_url = '', mi.image_data = NULL, mi.image_mime = NULL, mi.image_updated_at = NULL
        WHERE mi.id = ? AND s.owner_id = ?
    ");
    $stmt->bind_param('ssdsii', $name, $description, $price, $category, $item_id, $owner_id);
} else {
    $stmt = $con->prepare("
        UPDATE menu_items mi
        JOIN shops s ON mi.shop_id = s.id
        SET mi.name = ?, mi.description = ?, mi.price = ?, mi.category = ?
        WHERE mi.id = ? AND s.owner_id = ?
    ");
    $stmt->bind_param('ssdsii', $name, $description, $price, $category, $item_id, $owner_id);
}
$stmt->execute();
$stmt->close();

flash('success', '“' . $name . '” was updated.');
redirect_dashboard('menu');