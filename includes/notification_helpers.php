<?php
// Notification bell helpers (customers and shop owners).

function notif_unread_count(mysqli $con, int $user_id): int {
    $stmt = $con->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

/** Newest first. age_sec is worked out by the database, so time zones can never skew it. */
function notif_recent(mysqli $con, int $user_id, int $limit = 8): array {
    $stmt = $con->prepare(
        "SELECT id, order_id, message, is_read, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_sec
         FROM notifications
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function notif_mark_all_read(mysqli $con, int $user_id): void {
    $stmt = $con->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/** Where a notification should lead. $base is the role's folder, e.g. '../customer/'. '' = no link. */
function notif_href(array $n, string $role, string $base): string {
    if (!empty($n['order_id'])) {
        // The customer's My orders page opens the right tab (Current or Past) for that order
        return $role === 'customer'
            ? $base . 'orders.php?order=' . (int)$n['order_id']
            : $base . 'orders.php';
    }
    return $role === 'shop' ? $base . 'verification.php' : '';
}

function notif_ago(int $seconds): string {
    if ($seconds < 60)    return 'Just now';
    if ($seconds < 3600)  return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400) return floor($seconds / 3600) . ' h ago';
    $days = (int)floor($seconds / 86400);
    return $days . ($days === 1 ? ' day ago' : ' days ago');
}