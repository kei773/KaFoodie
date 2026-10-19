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

// Logo: keep the current one unless a new file was chosen or "remove" was ticked
$logo_url     = $shop['logo_url'];
$upload_error = null;
$new_logo     = save_uploaded_image($_FILES['logo'] ?? [], 'logos', $upload_error);

if ($upload_error) {
    flash('error', $upload_error);
    redirect_dashboard('profile');
}

if ($new_logo) {
    $logo_url = $new_logo;
} elseif ($remove_logo) {
    $logo_url = null;
}

$stmt = $con->prepare("UPDATE shops SET shop_name = ?, description = ?, logo_url = ? WHERE id = ?");
$stmt->bind_param('sssi', $shop_name, $description, $logo_url, $shop_id);
$stmt->execute();
$stmt->close();

// Remove the old file only after the database points at the new state
if ($logo_url !== $shop['logo_url']) {
    delete_local_image($shop['logo_url']);
}

flash('success', 'Shop profile updated.');
redirect_dashboard();