<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

require_admin();

$tabs = [
    'pending'       => 'Pending review',
    'approved'      => 'Verified',
    'rejected'      => 'Not approved',
    'not_submitted' => 'Not submitted',
    'all'           => 'All shops',
];

$filter = $_GET['status'] ?? 'pending';
if (!isset($tabs[$filter])) { $filter = 'pending'; }

$counts = admin_status_counts($con);
$counts['all'] = array_sum($counts);

$sql = "SELECT s.id, s.shop_name, s.business_category, s.cuisine, s.verification_status,
               u.name AS owner_name, u.email AS owner_email,
               (SELECT MAX(d.uploaded_at) FROM shop_documents d WHERE d.shop_id = s.id) AS last_upload,
               (SELECT COUNT(*) FROM shop_documents d WHERE d.shop_id = s.id) AS doc_count
        FROM shops s
        JOIN users u ON u.id = s.owner_id";

if ($filter === 'all') {
    $result = $con->query($sql . " ORDER BY s.id DESC");
} else {
    // Oldest first for the pending queue, newest first elsewhere
    $order = ($filter === 'pending') ? 'ASC' : 'DESC';
    $stmt = $con->prepare($sql . " WHERE s.verification_status = ? ORDER BY last_upload $order, s.id $order");
    $stmt->bind_param('s', $filter);
    $stmt->execute();
    $result = $stmt->get_result();
}
$shops = $result->fetch_all(MYSQLI_ASSOC);

[$flash_ok, $flash_err] = admin_take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>KaFoodie &mdash; Shop verification</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="dash-content">
  <a class="auth-back-link" href="dashboard.php">&larr; Back to dashboard</a>

  <div class="verif-head">
    <h1>Shop verification</h1>
  </div>
  <p class="verif-lead">Open a shop to read its documents and decide.</p>

  <?php if ($flash_ok): ?>
    <div class="alert-success" role="status"><?php echo verification_h($flash_ok); ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert-error" role="alert"><?php echo verification_h($flash_err); ?></div>
  <?php endif; ?>

  <nav class="admin-tabs" aria-label="Filter shops by status">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="admin-tab <?php echo $key === $filter ? 'is-active' : ''; ?>"
         href="verification.php?status=<?php echo verification_h($key); ?>"
         <?php echo $key === $filter ? 'aria-current="page"' : ''; ?>>
        <?php echo verification_h($label); ?>
        <span class="admin-tab-count"><?php echo (int)($counts[$key] ?? 0); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$shops): ?>
    <section class="dash-card admin-empty">
      <p>No shops in this list.</p>
    </section>
  <?php else: ?>
    <ul class="admin-list">
      <?php foreach ($shops as $s): ?>
        <li>
          <a class="admin-row" href="verification_review.php?id=<?php echo (int)$s['id']; ?>">
            <span class="admin-row-main">
              <span class="admin-row-title"><?php echo verification_h($s['shop_name']); ?></span>
              <span class="admin-row-sub">
                <?php echo verification_h($s['owner_name']); ?>, <?php echo verification_h($s['owner_email']); ?>
              </span>
              <span class="admin-row-sub">
                <?php echo verification_h($s['business_category'] ?: 'No category'); ?>,
                <?php echo verification_h($s['cuisine'] ?: 'No cuisine'); ?>
              </span>
            </span>
            <span class="admin-row-side">
              <span class="verif-status verif-status-<?php echo verification_h($s['verification_status']); ?>">
                <?php echo verification_h(VERIFICATION_STATUS_LABELS[$s['verification_status']] ?? $s['verification_status']); ?>
              </span>
              <span class="admin-row-sub">
                <?php echo (int)$s['doc_count']; ?> document<?php echo (int)$s['doc_count'] === 1 ? '' : 's'; ?>
                <?php if ($s['last_upload']): ?>
                  , last upload <?php echo verification_h(date('M j, Y', strtotime($s['last_upload']))); ?>
                <?php endif; ?>
              </span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
</body>
</html>
