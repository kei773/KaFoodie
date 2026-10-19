<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/verification_helpers.php';

function submit_fail(string $message): void {
    $_SESSION['verif_error'] = $message;
    header('Location: verification.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'shop' || !isset($_SESSION['user_id'])) {
    header('Location: ../vendor/vendor_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verification_csrf_ok()) {
    submit_fail('Your session expired. Please try again.');
}

$shop = verification_current_shop($con, (int)$_SESSION['user_id']);
if (!$shop) {
    submit_fail('No shop was found for your account.');
}

if (verification_is_locked($shop['verification_status'])) {
    submit_fail('Your documents have already been submitted.');
}

$docs    = verification_documents($con, (int)$shop['id']);
$missing = verification_missing_required($docs);
if ($missing) {
    submit_fail('Upload these first: ' . implode(', ', $missing) . '.');
}

$shop_id = (int)$shop['id'];
$stmt = $con->prepare(
    "UPDATE shops SET verification_status = 'pending', rejection_reason = NULL WHERE id = ?"
);
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$stmt->close();

$_SESSION['verif_success'] = 'Submitted. Your documents are now waiting for review.';
header('Location: verification.php');
exit;
