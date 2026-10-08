<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

require_admin();

$shop_id = (int)($_GET['id'] ?? 0);

$stmt = $con->prepare(
    "SELECT s.id, s.shop_name, s.description, s.business_category, s.cuisine,
            s.verification_status, s.rejection_reason,
            u.name AS owner_name, u.email AS owner_email, u.created_at AS joined
     FROM shops s
     JOIN users u ON u.id = s.owner_id
     WHERE s.id = ?"
);
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$shop) {
    $_SESSION['admin_error'] = 'That shop was not found.';
    header('Location: verification.php');
    exit;
}

$status = $shop['verification_status'];
$docs   = verification_documents($con, $shop_id);
$csrf   = admin_csrf_token();

[$flash_ok, $flash_err] = admin_take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>KaFoodie &mdash; Review <?php echo verification_h($shop['shop_name']); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="dash-content">
  <a class="auth-back-link" href="verification.php">&larr; Back to shop list</a>

  <div class="verif-head">
    <h1><?php echo verification_h($shop['shop_name']); ?></h1>
    <span class="verif-status verif-status-<?php echo verification_h($status); ?>">
      <?php echo verification_h(VERIFICATION_STATUS_LABELS[$status] ?? $status); ?>
    </span>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert-success" role="status"><?php echo verification_h($flash_ok); ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert-error" role="alert"><?php echo verification_h($flash_err); ?></div>
  <?php endif; ?>
  <div class="alert-error" id="reasonError" role="alert" hidden></div>

  <section class="dash-card">
    <h2 class="admin-section-title">Shop details</h2>
    <dl class="admin-detail">
      <div><dt>Owner</dt><dd><?php echo verification_h($shop['owner_name']); ?></dd></div>
      <div><dt>Email</dt><dd><?php echo verification_h($shop['owner_email']); ?></dd></div>
      <div><dt>Category</dt><dd><?php echo verification_h($shop['business_category'] ?: 'Not set'); ?></dd></div>
      <div><dt>Cuisine</dt><dd><?php echo verification_h($shop['cuisine'] ?: 'Not set'); ?></dd></div>
      <div><dt>Account created</dt><dd><?php echo verification_h(date('M j, Y', strtotime($shop['joined']))); ?></dd></div>
      <?php if (!empty($shop['description'])): ?>
        <div class="admin-detail-wide"><dt>Description</dt><dd><?php echo verification_h($shop['description']); ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>

  <section class="dash-card admin-block">
    <h2 class="admin-section-title">Documents</h2>
    <ul class="verif-list">
      <?php foreach (VERIFICATION_DOCS as $type => $info): ?>
        <?php $doc = $docs[$type] ?? null; ?>
        <li class="verif-doc">
          <div class="verif-doc-info">
            <div class="verif-doc-title">
              <?php echo verification_h($info['label']); ?>
              <span class="verif-tag <?php echo $info['required'] ? 'verif-tag-required' : 'verif-tag-optional'; ?>">
                <?php echo $info['required'] ? 'Required' : 'Optional'; ?>
              </span>
            </div>
            <?php if ($doc): ?>
              <div class="verif-doc-file">
                <span class="verif-doc-name"><?php echo verification_h($doc['original_name']); ?></span>
                <span class="verif-doc-meta">
                  <?php echo verification_h(verification_format_size((int)$doc['file_size'])); ?>,
                  uploaded <?php echo verification_h(date('M j, Y', strtotime($doc['uploaded_at']))); ?>
                </span>
                <a href="../shop/view_document.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener">Open document</a>
              </div>
            <?php else: ?>
              <div class="verif-doc-file verif-doc-empty">Not uploaded</div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="dash-card admin-block">
    <h2 class="admin-section-title">Decision</h2>

    <?php if ($status === 'pending'): ?>
      <form action="verification_decide.php" method="POST" id="decisionForm">
        <input type="hidden" name="csrf" value="<?php echo verification_h($csrf); ?>">
        <input type="hidden" name="shop_id" value="<?php echo (int)$shop['id']; ?>">

        <div class="field">
          <label for="reason">Reason (required when rejecting)</label>
          <textarea id="reason" name="reason" rows="3" maxlength="255"
                    placeholder="Tell the vendor what to fix, for example: the ID photo is too blurry to read."></textarea>
        </div>

        <div class="admin-actions">
          <button type="submit" name="decision" value="reject" class="sh-btn-danger" id="rejectBtn">Reject</button>
          <button type="submit" name="decision" value="approve" class="btn-primary btn-inline">Approve shop</button>
        </div>
      </form>

    <?php elseif ($status === 'approved'): ?>
      <p class="admin-decided">This shop is verified.</p>

    <?php elseif ($status === 'rejected'): ?>
      <p class="admin-decided">This shop was not approved.</p>
      <?php if (!empty($shop['rejection_reason'])): ?>
        <p class="admin-decided-reason"><strong>Reason given:</strong> <?php echo verification_h($shop['rejection_reason']); ?></p>
      <?php endif; ?>
      <p class="verif-note">It will return to the pending list when the vendor submits again.</p>

    <?php else: ?>
      <p class="admin-decided">The vendor hasn't submitted documents yet.</p>
    <?php endif; ?>
  </section>
</main>

<?php if ($status === 'pending'): ?>
<script>
(function () {
  var reject = document.getElementById('rejectBtn');
  var reason = document.getElementById('reason');
  var box    = document.getElementById('reasonError');
  reject.addEventListener('click', function (e) {
    if (reason.value.trim() === '') {
      e.preventDefault();
      box.textContent = 'Please write a reason so the vendor knows what to fix.';
      box.hidden = false;
      reason.focus();
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
