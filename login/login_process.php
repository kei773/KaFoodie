<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

function fail(string $message, string $email = ''): void {
    $_SESSION['login_error']     = $message;
    $_SESSION['login_old_email'] = $email;
    header('Location: login.php');
    exit;
}

$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// CSRF check
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    fail('Your session expired. Please try again.', $email);
}

if (($_SESSION['login_lock_until'] ?? 0) > time()) {
    fail('Too many attempts. Please wait a few minutes and try again.', $email);
}

if ($email === '' || $password === '') {
    fail('Please fill in all fields.', $email);
}

require_once __DIR__ . '/../database/connection.php';

if (!isset($con) && isset($conn)) {
    $con = $conn;
}

if (empty($con)) {
    fail('Database connection error.', $email);
}

$stmt = $con->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = 'customer'");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$hash = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);

if (!$user || !password_verify($password, $hash)) {
    $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
    if ($_SESSION['login_fails'] >= 5) {
        $_SESSION['login_lock_until'] = time() + 300;
        $_SESSION['login_fails'] = 0;
    }
    fail('Incorrect email or password.', $email);
}

session_regenerate_id(true);
unset($_SESSION['csrf'], $_SESSION['login_fails'], $_SESSION['login_lock_until'], $_SESSION['login_old_email']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = 'customer';

header('Location: ../customer/dashboard.php');
exit;