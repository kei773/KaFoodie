<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

require_admin();

$counts  = admin_status_counts($con);
$pending = $counts['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>KaFoodie &mdash; Admin dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="dash-content">
  <div class="verif-head">
    <h1>Admin dashboard</h1>
  </div>
  <div class="sh-grid">
    <a class="sh-card" href="verification.php">
      <span class="sh-card-icon" aria-hidden="true">✅</span>
      <span class="sh-card-title">Shop verification</span>
      <span class="sh-card-sub">Check uploaded documents, then approve or reject each shop.</span>
      <?php if ($pending > 0): ?>
        <span class="sh-badge sh-badge-pending"><?php echo $pending; ?> waiting for review</span>
      <?php else: ?>
        <span class="sh-badge sh-badge-not_submitted">Nothing waiting</span>
      <?php endif; ?>
    </a>
  </div>
</main>
</body>
</html>
