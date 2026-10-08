<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/otp_helpers.php';

$name             = trim($_POST['name'] ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role             = $_POST['role'] ?? '';

// Business details (vendors only)
$business_name     = trim($_POST['business_name'] ?? '');
$business_category = trim($_POST['business_category'] ?? '');
$cuisine           = trim($_POST['cuisine'] ?? '');

// Allowed choices. Must match the dropdowns in vendor/vendor_signup.php.
$valid_categories = ['Restaurant', 'Cafe', 'Bakery', 'Food stall / Carinderia', 'Home-based kitchen'];
$valid_cuisines   = ['Filipino', 'Fast food', 'Pizza', 'Burgers', 'Chicken', 'Asian', 'Desserts & snacks', 'Coffee & tea', 'Other'];

// Sends the person back to the right signup page with what they typed.
// $step tells the vendor form which step to reopen (1 = account, 2 = business).
function fail($message, $step = 1) {
    global $role, $name, $email, $business_name, $business_category, $cuisine;
    $_SESSION['signup_error'] = $message;
    $_SESSION['signup_step']  = $step;
    $_SESSION['signup_old'] = [
        'name' => $name, 'email' => $email, 'role' => $role,
        'business_name' => $business_name,
        'business_category' => $business_category,
        'cuisine' => $cuisine,
    ];
    // Full paths from the project root, so the redirect works from both the
    // /signup and /vendor folders.
    $is_vendor = ($role === 'shop') || (basename($_SERVER['SCRIPT_NAME']) === 'vendor_signup_process.php');
    header('Location: ' . ($is_vendor ? '../vendor/vendor_signup.php' : '../signup/signup.php'));
    exit;
}

// Only these two can be created from the website. Admin accounts are added directly in the database.
$valid_roles = ['customer', 'shop'];

if (!in_array($role, $valid_roles, true)) {
    fail('Invalid account type selected.');
}

// ----- Account fields (customers and vendors) -----
if ($name === '' || $email === '' || $password === '' || $confirm_password === '') {
    fail('Please fill in all fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.');
}

if (strlen($password) < 6) {
    fail('Password must be at least 6 characters.');
}

if ($password !== $confirm_password) {
    fail('Passwords do not match.');
}

// ----- Business fields (vendors only) -----
if ($role === 'shop') {
    if ($business_name === '' || $business_category === '' || $cuisine === '') {
        fail('Please fill in your business details.', 2);
    }
    if (mb_strlen($business_name) > 150) {
        fail('Business name must be 150 characters or fewer.', 2);
    }
    if (!in_array($business_category, $valid_categories, true)) {
        fail('Please choose a business category from the list.', 2);
    }
    if (!in_array($cuisine, $valid_cuisines, true)) {
        fail('Please choose a cuisine from the list.', 2);
    }
}

// The users table has a unique key on email alone, so one email can only
// belong to one account, whichever account type it is.
$stmt = $con->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    fail('An account with that email already exists.');
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// The account is NOT created yet. We save the details as a pending signup and
// email a one-time code. The user (and shop) are created in verify_email.php
// once the correct code is entered.
$token = otp_start_signup($con, [
    'role'              => $role,
    'name'              => $name,
    'email'             => $email,
    'password_hash'     => $password_hash,
    'business_name'     => $business_name,
    'business_category' => $business_category,
    'cuisine'           => $cuisine,
], $send_error);

if ($token === null) {
    fail($send_error ?: 'We could not send the verification email. Please try again.');
}

unset($_SESSION['signup_step'], $_SESSION['signup_old']);
$_SESSION['pending_signup_token'] = $token;
header('Location: ../signup/verify_email.php');
exit;