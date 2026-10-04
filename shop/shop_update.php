<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

$shop = require_shop($con);
require_post_csrf();

$shop_id     = (int)$shop['id'];
$shop_name   = mb_substr(trim($_POST['shop_name'] ?? ''), 0, 100);
$description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
$remove_logo = !empty($_POST['remove_logo']);

if ($shop_name === '') {
    flash('error', 'Shop name cannot be empty.');
    redirect_dashboard('profile');
}

// Logo: replaced only when a new file was chosen, cleared only when "remove" was ticked
$upload_error = null;
$logo = read_uploaded_image($_FILES['logo'] ?? [], $upload_error, $con);

if ($upload_error) {
    flash('error', $upload_error);
    redirect_dashboard('profile');
}

if ($logo) {
    $data = $logo['data'];
    $mime = $logo['mime'];
    $stmt = $con->prepare("UPDATE shops
        SET shop_name = ?, description = ?, logo_data = ?, logo_mime = ?, logo_updated_at = NOW(), logo_url = NULL
        WHERE id = ?");
    $stmt->bind_param('ssssi', $shop_name, $description, $data, $mime, $shop_id);
} elseif ($remove_logo) {
    $stmt = $con->prepare("UPDATE shops
        SET shop_name = ?, description = ?, logo_data = NULL, logo_mime = NULL, logo_updated_at = NULL, logo_url = NULL
        WHERE id = ?");
    $stmt->bind_param('ssi', $shop_name, $description, $shop_id);
} else {
    $stmt = $con->prepare("UPDATE shops SET shop_name = ?, description = ? WHERE id = ?");
    $stmt->bind_param('ssi', $shop_name, $description, $shop_id);
}
$stmt->execute();
$stmt->close();

flash('success', 'Shop profile updated.');
redirect_dashboard();