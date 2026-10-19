<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

require_shop($con);
require_post_csrf();

$owner_id = (int)$_SESSION['user_id'];
$item_id  = (int)($_POST['item_id'] ?? 0);

$stmt = $con->prepare("
    UPDATE menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    SET mi.is_available = NOT mi.is_available
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ii', $item_id, $owner_id);
$stmt->execute();
$stmt->close();

redirect_dashboard('menu');