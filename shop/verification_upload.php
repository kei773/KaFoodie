<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/verification_helpers.php';

function upload_fail(string $message): void {
    $_SESSION['verif_error'] = $message;
    header('Location: verification.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'shop' || !isset($_SESSION['user_id'])) {
    header('Location: ../vendor/vendor_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verification.php');
    exit;
}

// A file bigger than PHP's post_max_size arrives with an empty $_POST.
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    upload_fail('That file is too large. The limit is ' . verification_format_size(VERIFICATION_MAX_BYTES) . '.');
}

if (!verification_csrf_ok()) {
    upload_fail('Your session expired. Please try again.');
}

$shop = verification_current_shop($con, (int)$_SESSION['user_id']);
if (!$shop) {
    upload_fail('No shop was found for your account.');
}

if (verification_is_locked($shop['verification_status'])) {
    upload_fail('Your documents are being reviewed or already verified, so they can\'t be changed.');
}

$doc_type = $_POST['doc_type'] ?? '';
if (!isset(VERIFICATION_DOCS[$doc_type])) {
    upload_fail('Unknown document type.');
}
$label = VERIFICATION_DOCS[$doc_type]['label'];

$file = $_FILES['document'] ?? null;
if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    upload_fail('Choose a file to upload.');
}
if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    upload_fail('That file is too large. The limit is ' . verification_format_size(VERIFICATION_MAX_BYTES) . '.');
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    upload_fail('The upload failed. Please try again.');
}
if ($file['size'] > VERIFICATION_MAX_BYTES) {
    upload_fail('That file is too large. The limit is ' . verification_format_size(VERIFICATION_MAX_BYTES) . '.');
}
if ($file['size'] === 0) {
    upload_fail('That file is empty.');
}

// Check the extension, then the real file type (not just what the browser says).
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset(VERIFICATION_TYPES[$ext])) {
    upload_fail('Only PDF, JPG and PNG files are accepted.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, VERIFICATION_TYPES[$ext], true)) {
    upload_fail('That file doesn\'t look like a real ' . strtoupper($ext) . ' file.');
}
if ($mime === 'application/pdf') {
    $head = file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($head !== '%PDF-') {
        upload_fail('That file doesn\'t look like a real PDF.');
    }
} elseif (@getimagesize($file['tmp_name']) === false) {
    upload_fail('That file doesn\'t look like a real image.');
}

// Save under a random name so it can't be guessed or overwritten.
try {
    $dir = verification_ensure_storage((int)$shop['id']);
} catch (RuntimeException $e) {
    upload_fail('The server could not prepare the storage folder.');
}

$stored_name = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored_name)) {
    upload_fail('The file could not be saved. Please try again.');
}

$original = mb_substr(basename($file['name']), 0, 255);
$size     = (int)$file['size'];
$shop_id  = (int)$shop['id'];

// Replace the previous upload of this document type, if there is one.
$old_stored = null;
$stmt = $con->prepare("SELECT stored_name FROM shop_documents WHERE shop_id = ? AND doc_type = ?");
$stmt->bind_param('is', $shop_id, $doc_type);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($existing) { $old_stored = $existing['stored_name']; }

try {
    if ($existing) {
        $stmt = $con->prepare(
            "UPDATE shop_documents
             SET original_name = ?, stored_name = ?, mime_type = ?, file_size = ?, uploaded_at = NOW()
             WHERE shop_id = ? AND doc_type = ?"
        );
        $stmt->bind_param('sssiis', $original, $stored_name, $mime, $size, $shop_id, $doc_type);
    } else {
        $stmt = $con->prepare(
            "INSERT INTO shop_documents (shop_id, doc_type, original_name, stored_name, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issssi', $shop_id, $doc_type, $original, $stored_name, $mime, $size);
    }
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    @unlink($dir . '/' . $stored_name);
    upload_fail('The file could not be saved. Please try again.');
}

if ($old_stored) {
    @unlink(verification_document_path($shop_id, $old_stored));
}

$_SESSION['verif_success'] = $label . ' uploaded.';
header('Location: verification.php');
exit;
