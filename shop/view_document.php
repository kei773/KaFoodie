<?php
// Streams one uploaded document. Documents are never opened by direct URL.
// Allowed: the shop owner who uploaded it, or an admin.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/verification_helpers.php';

$role    = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id === 0 || !in_array($role, ['shop', 'admin'], true)) {
    http_response_code(403);
    exit('Please log in to view this document.');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $con->prepare(
    "SELECT d.shop_id, d.stored_name, d.original_name, d.mime_type, s.owner_id
     FROM shop_documents d
     JOIN shops s ON s.id = d.shop_id
     WHERE d.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

if ($role === 'shop' && (int)$doc['owner_id'] !== $user_id) {
    http_response_code(403);
    exit('You can\'t view this document.');
}

$path = verification_document_path((int)$doc['shop_id'], $doc['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('The file is missing from storage.');
}

// Keep the download name safe for the header.
$safe_name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $doc['original_name']);

header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $safe_name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
