<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];
$item_id  = $_POST['item_id'] ?? 0;

$stmt = $con->prepare("
    DELETE mi FROM menu_items mi
    JOIN shops s ON mi.shop_id = s.id
    WHERE mi.id = ? AND s.owner_id = ?
");
$stmt->bind_param('ii', $item_id, $owner_id);
$stmt->execute();
$stmt->close();

header('Location: dashboard.php');
exit;