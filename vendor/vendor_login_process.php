<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

function vendor_login_fail($message) {
    $_SESSION['login_error'] = $message;
    header('Location: vendor_login.php');
    exit;
}

if ($email === '' || $password === '') {
    vendor_login_fail('Please fill in all fields.');
}

$stmt = $con->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = 'shop'");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    vendor_login_fail('No vendor account found with that email.');
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password_hash'])) {
    vendor_login_fail('Incorrect password.');
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['name'] = $user['name'];
$_SESSION['role'] = 'shop';

header('Location: shop/dashboard.php');
exit;
