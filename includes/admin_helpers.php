<?php
// Shared helpers for the admin area. Every admin page calls require_admin() first.
require_once __DIR__ . '/verification_helpers.php';

// Sends anyone who is not a logged-in admin to the admin login page.
function require_admin(): void {
    if (($_SESSION['role'] ?? '') !== 'admin' || !isset($_SESSION['user_id'])) {
        header('Location: admin_login.php');
        exit;
    }
}

function admin_csrf_token(): string {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_ok(): bool {
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $sent);
}

// One-time messages: returns [success, error] and clears them.
function admin_take_flash(): array {
    $flash = [$_SESSION['admin_success'] ?? null, $_SESSION['admin_error'] ?? null];
    unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    return $flash;
}

function admin_status_counts(mysqli $con): array {
    $counts = ['not_submitted' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    $result = $con->query("SELECT verification_status, COUNT(*) AS c FROM shops GROUP BY verification_status");
    while ($row = $result->fetch_assoc()) {
        $counts[$row['verification_status']] = (int)$row['c'];
    }
    return $counts;
}
