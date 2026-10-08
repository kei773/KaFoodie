<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

function admin_login_fail(string $message, string $email = ''): void {
    $_SESSION['admin_login_error']     = $message;
    $_SESSION['admin_login_old_email'] = $email;
    header('Location: admin_login.php');
    exit;
}

$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!hash_equals($_SESSION['admin_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    admin_login_fail('Your session expired. Please try again.', $email);
}

if (($_SESSION['admin_lock_until'] ?? 0) > time()) {
    admin_login_fail('Too many attempts. Please wait a few minutes and try again.', $email);
}

if ($email === '' || $password === '') {
    admin_login_fail('Please fill in all fields.', $email);
}

require_once __DIR__ . '/../database/connection.php';

$stmt = $con->prepare("SELECT id, name, password_hash FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Same timing whether or not the account exists
$hash = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);

if (!$user || !password_verify($password, $hash)) {
    $_SESSION['admin_fails'] = ($_SESSION['admin_fails'] ?? 0) + 1;
    if ($_SESSION['admin_fails'] >= 5) {
        $_SESSION['admin_lock_until'] = time() + 300;
        $_SESSION['admin_fails'] = 0;
    }
    admin_login_fail('Incorrect email or password.', $email);
}

session_regenerate_id(true);
unset($_SESSION['admin_csrf'], $_SESSION['admin_fails'], $_SESSION['admin_lock_until'], $_SESSION['admin_login_old_email']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = 'admin';

header('Location: dashboard.php');
exit;
