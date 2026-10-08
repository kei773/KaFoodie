<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

require_admin();

function decide_back(int $shop_id, string $type, string $message): void {
    $_SESSION[$type === 'ok' ? 'admin_success' : 'admin_error'] = $message;
    header('Location: verification_review.php?id=' . $shop_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verification.php');
    exit;
}

$shop_id  = (int)($_POST['shop_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$reason   = trim($_POST['reason'] ?? '');

if (!admin_csrf_ok()) {
    decide_back($shop_id, 'err', 'Your session expired. Please try again.');
}

if (!in_array($decision, ['approve', 'reject'], true)) {
    decide_back($shop_id, 'err', 'Choose Approve or Reject.');
}

// Find the shop and its owner
$stmt = $con->prepare("SELECT id, owner_id, verification_status FROM shops WHERE id = ?");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shop) {
    $_SESSION['admin_error'] = 'That shop was not found.';
    header('Location: verification.php');
    exit;
}

if ($shop['verification_status'] !== 'pending') {
    decide_back($shop_id, 'err', 'This shop is not waiting for review anymore.');
}

if ($decision === 'reject') {
    if ($reason === '') {
        decide_back($shop_id, 'err', 'Please write a reason before rejecting.');
    }
    if (mb_strlen($reason) > 255) {
        decide_back($shop_id, 'err', 'The reason must be 255 characters or fewer.');
    }
    $stmt = $con->prepare(
        "UPDATE shops SET verification_status = 'rejected', rejection_reason = ?
         WHERE id = ? AND verification_status = 'pending'"
    );
    $stmt->bind_param('si', $reason, $shop_id);
    $message = 'Your documents were not approved. Open Document verification to see why.';
    $done    = 'Shop rejected. The vendor can see your reason.';
} else {
    $stmt = $con->prepare(
        "UPDATE shops SET verification_status = 'approved', rejection_reason = NULL
         WHERE id = ? AND verification_status = 'pending'"
    );
    $stmt->bind_param('i', $shop_id);
    $message = 'Your shop has been verified. Thanks for completing the review.';
    $done    = 'Shop approved.';
}

$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();

if ($changed < 1) {
    decide_back($shop_id, 'err', 'Nothing was changed. Someone else may have decided already.');
}

// Let the vendor know. A failure here should never undo the decision.
try {
    $owner_id = (int)$shop['owner_id'];
    $stmt = $con->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param('is', $owner_id, $message);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    // ignore
}

$_SESSION['admin_success'] = $done;
header('Location: verification.php');
exit;
