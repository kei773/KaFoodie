<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

require_shop($con);
require_post_csrf();

$owner_id = (int)$_SESSION['user_id'];
$item_id  = (int)($_POST['item_id'] ?? 0);

// The vendor must have typed the word "delete" (checked here too, not just in the browser)
if (strtolower(trim($_POST['confirm_text'] ?? '')) !== 'delete') {
    flash('error', 'Nothing was deleted. Type the word “delete” to confirm.');
    redirect_dashboard('menu');
}

// Deletes the row (and its photo, which is stored in the same row) only if it belongs to this owner
$stmt = $con->prepare("
    DELETE mi FROM menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ii', $item_id, $owner_id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if ($deleted) {
    flash('success', 'Item removed from your menu.');
}

redirect_dashboard('menu');