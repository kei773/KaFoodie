<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$name             = trim($_POST['name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role             = $_POST['role'] ?? '';

function fail($message, $name, $email, $role) {
    $_SESSION['signup_error'] = $message;
    $_SESSION['signup_old'] = ['name' => $name, 'email' => $email, 'role' => $role];
    header('Location: ' . ($role === 'shop' ? 'vendor_signup.php' : 'signup.php'));
    exit;
}

$valid_roles = ['customer', 'rider', 'shop', 'admin'];

if ($name === '' || $email === '' || $password === '' || $confirm_password === '' || $role === '') {
    fail('Please fill in all fields.', $name, $email, $role);
}

if (!in_array($role, $valid_roles)) {
    fail('Invalid account type selected.', $name, $email, $role);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.', $name, $email, $role);
}

if (strlen($password) < 6) {
    fail('Password must be at least 6 characters.', $name, $email, $role);
}

if ($password !== $confirm_password) {
    fail('Passwords do not match.', $name, $email, $role);
}

// Check if email is already used for this role
$stmt = $con->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    fail('An account with that email already exists for this account type.', $name, $email, $role);
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $con->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $name, $email, $password_hash, $role);

if (!$stmt->execute()) {
    fail('Something went wrong creating your account. Please try again.', $name, $email, $role);
}
$stmt->close();

// If the role is "shop", also create their shop row so it's ready for the dashboard step
if ($role === 'shop') {
    $owner_id = $con->insert_id;
    $default_shop_name = $name . "'s Shop";
    $stmt = $con->prepare("INSERT INTO shops (owner_id, shop_name) VALUES (?, ?)");
    $stmt->bind_param('is', $owner_id, $default_shop_name);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['login_success'] = 'Account created! You can now log in.';
header('Location: ' . ($role === 'shop' ? 'vendor_login.php' : 'login.php'));
exit;
