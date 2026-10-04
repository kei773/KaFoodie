<?php
// Sends one stored picture to the browser:
//   image.php?t=logo&id=<shop id>   (shop logo)
//   image.php?t=menu&id=<item id>   (dish photo)
// Logos and dish photos are public (the landing page shows them to everyone).
require_once __DIR__ . '/database/connection.php';

$type = $_GET['t'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if ($type === 'logo') {
    $sql = "SELECT logo_data AS data, logo_mime AS mime FROM shops WHERE id = ?";
} elseif ($type === 'menu') {
    $sql = "SELECT image_data AS data, image_mime AS mime FROM menu_items WHERE id = ?";
} else {
    http_response_code(404);
    exit;
}

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $con->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!$row || $row['data'] === null || $row['data'] === '' || !in_array($row['mime'], $allowed, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $row['mime']);
header('Content-Length: ' . strlen($row['data']));
header('X-Content-Type-Options: nosniff');
// The page URLs carry a version number (?v=...) that changes when the photo changes,
// so browsers can keep each picture for a long time.
header('Cache-Control: public, max-age=31536000, immutable');
echo $row['data'];
