<?php
// "View all" page for the notification bell.
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['customer', 'shop'], true)) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$base    = '../' . $role . '/';

// Read the list first (to know which ones were unread), then mark everything as read
$notes = notif_recent($con, $user_id, 50);
notif_mark_all_read($con, $user_id);

function nh($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Notifications</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <div class="cart-topbar">
    <a href="<?= nh($base) ?>dashboard.php" class="cart-back">← Back to dashboard</a>
  </div>

  <div class="browse-greeting">
    <h1>Notifications</h1>
    <p>Your latest updates<?= $role === 'customer' ? ' about your orders' : ' about orders and verification' ?>.</p>
  </div>

  <div class="dash-card kf-notif-card">
    <?php if (!$notes): ?>
      <p class="dash-empty">Nothing here yet.</p>
    <?php else: ?>
      <ul class="kf-notif-list">
        <?php foreach ($notes as $n): $href = notif_href($n, $role, $base); ?>
          <li class="kf-notif<?= (int)$n['is_read'] === 0 ? ' is-unread' : '' ?>">
            <?php if ($href !== ''): ?><a class="kf-notif-body" href="<?= nh($href) ?>"><?php else: ?><div class="kf-notif-body"><?php endif; ?>
              <span class="kf-notif-msg"><?= nh($n['message']) ?></span>
              <span class="kf-notif-time"><?= nh(notif_ago((int)$n['age_sec'])) ?></span>
            <?php echo $href !== '' ? '</a>' : '</div>'; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
