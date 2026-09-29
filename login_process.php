<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

function fail($message) {
    $_SESSION['login_error'] = $message;
    header('Location: login.php');
    exit;
}

if ($email === '' || $password === '' || $role === '') {
    fail('Please fill in all fields.');
}

$stmt = $con->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = ?");
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    fail('No account found with that email for the selected account type.');
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password_hash'])) {
    fail('Incorrect password.');
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = $role;

switch ($role) {
    case 'customer':
        header('Location: customer/dashboard.php');
        break;
    case 'rider':
        header('Location: rider/dashboard.php');
        break;
    case 'shop':
        header('Location: shop/dashboard.php');
        break;
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    default:
        header('Location: login.php');
}
exit;