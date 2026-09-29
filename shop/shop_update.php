<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop') {
    header('Location: ../login.php');
    exit;
}

$owner_id    = $_SESSION['user_id'];
$shop_name   = trim($_POST['shop_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($shop_name === '') {
    $_SESSION['shop_error'] = 'Shop name cannot be empty.';
    header('Location: dashboard.php');
    exit;
}

$stmt = $con->prepare("UPDATE shops SET shop_name = ?, description = ? WHERE owner_id = ?");
$stmt->bind_param('ssi', $shop_name, $description, $owner_id);
$stmt->execute();
$stmt->close();

$_SESSION['shop_success'] = 'Shop details updated.';
header('Location: dashboard.php');
exit;