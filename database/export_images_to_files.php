<?php
// ONE-TIME SCRIPT: copies pictures that are stored inside the database out into files in uploads/,
// and points logo_url / image_url at them.
//
// Order:  1) copy the new project files   2) run this script   3) check the site
//         4) run remove_image_blobs.sql   5) delete this file
//
// Open in your browser once:  http://localhost/KaFoodie/database/export_images_to_files.php

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Run this on localhost only.');
}

require_once __DIR__ . '/connection.php';
header('Content-Type: text/plain; charset=utf-8');

$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function write_image_file(string $folder, string $data, string $mime, array $ext): ?string {
    if (!isset($ext[$mime])) return null;
    $dir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $name = bin2hex(random_bytes(16)) . '.' . $ext[$mime];
    if (file_put_contents($dir . '/' . $name, $data) === false) return null;
    return 'uploads/' . $folder . '/' . $name;
}

$done = 0;
$skipped = 0;

// Shop logos
$res = $con->query("SELECT id, logo_data, logo_mime FROM shops
                    WHERE logo_data IS NOT NULL AND (logo_url IS NULL OR logo_url NOT LIKE 'uploads/%')");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $id   = (int)$row['id'];
    $path = write_image_file('logos', $row['logo_data'], (string)$row['logo_mime'], $ext);
    if (!$path) { echo "SKIPPED logo of shop #$id (unknown image type or folder not writable)\n"; $skipped++; continue; }
    $stmt = $con->prepare("UPDATE shops SET logo_url = ? WHERE id = ?");
    $stmt->bind_param('si', $path, $id);
    $stmt->execute();
    $stmt->close();
    echo "Saved logo of shop #$id as $path\n";
    $done++;
}

// Dish photos
$res = $con->query("SELECT id, image_data, image_mime FROM menu_items
                    WHERE image_data IS NOT NULL AND (image_url IS NULL OR image_url NOT LIKE 'uploads/%')");
foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
    $id   = (int)$row['id'];
    $path = write_image_file('menu', $row['image_data'], (string)$row['image_mime'], $ext);
    if (!$path) { echo "SKIPPED photo of menu item #$id (unknown image type or folder not writable)\n"; $skipped++; continue; }
    $stmt = $con->prepare("UPDATE menu_items SET image_url = ? WHERE id = ?");
    $stmt->bind_param('si', $path, $id);
    $stmt->execute();
    $stmt->close();
    echo "Saved photo of menu item #$id as $path\n";
    $done++;
}

echo "\nDone. Saved: $done, skipped: $skipped.\n";
echo "Check the site. If every picture shows, run remove_image_blobs.sql and delete this file.\n";
