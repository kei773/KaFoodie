<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/verification_helpers.php';

if (($_SESSION['role'] ?? '') !== 'shop' || !isset($_SESSION['user_id'])) {
    header('Location: ../vendor/vendor_login.php');
    exit;
}

$shop = verification_current_shop($con, (int)$_SESSION['user_id']);
if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

$status  = $shop['verification_status'];
$docs    = verification_documents($con, (int)$shop['id']);
$missing = verification_missing_required($docs);
$locked  = verification_is_locked($status);
$csrf    = verification_csrf_token();

// One-time messages from the upload and submit pages
$error   = $_SESSION['verif_error']   ?? null;
$success = $_SESSION['verif_success'] ?? null;
unset($_SESSION['verif_error'], $_SESSION['verif_success']);

$status_text = [
    'not_submitted' => 'Upload your required documents, then submit them for review. Customers can see your shop once it is verified.',
    'pending'       => 'Your documents are waiting for review. You can\'t change them until the review is done.',
    'approved'      => 'Your shop is verified. Thanks for completing the review.',
    'rejected'      => 'Your documents weren\'t approved. Replace the ones that need fixing, then submit again.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KaFoodie &mdash; Document verification</title>
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
    <h1>Document verification</h1>
    <span class="verif-status verif-status-<?php echo verification_h($status); ?>">
      <?php echo verification_h(VERIFICATION_STATUS_LABELS[$status] ?? $status); ?>
    </span>
  </div>
  <p class="verif-lead"><?php echo verification_h($status_text[$status] ?? ''); ?></p>

  <?php if ($status === 'rejected' && !empty($shop['rejection_reason'])): ?>
    <div class="alert-error" role="alert">
      <strong>Reason from the reviewer:</strong>
      <?php echo verification_h($shop['rejection_reason']); ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert-success" role="status"><?php echo verification_h($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error" role="alert"><?php echo verification_h($error); ?></div>
  <?php endif; ?>

  <section class="dash-card">
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
            <div class="verif-doc-help"><?php echo verification_h($info['help']); ?></div>

            <?php if ($doc): ?>
              <div class="verif-doc-file">
                <span class="verif-doc-name"><?php echo verification_h($doc['original_name']); ?></span>
                <span class="verif-doc-meta">
                  <?php echo verification_h(verification_format_size((int)$doc['file_size'])); ?>,
                  uploaded <?php echo verification_h(date('M j, Y', strtotime($doc['uploaded_at']))); ?>
                </span>
                <a href="view_document.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener">View</a>
              </div>
            <?php else: ?>
              <div class="verif-doc-file verif-doc-empty">Nothing uploaded yet</div>
            <?php endif; ?>
          </div>

          <?php if (!$locked): ?>
            <form class="verif-upload" action="verification_upload.php" method="POST" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?php echo verification_h($csrf); ?>">
              <input type="hidden" name="doc_type" value="<?php echo verification_h($type); ?>">
              <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                     aria-label="Choose file for <?php echo verification_h($info['label']); ?>" required>
              <button type="submit" class="btn-outline"><?php echo $doc ? 'Replace' : 'Upload'; ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="verif-note">PDF, JPG or PNG, up to <?php echo verification_h(verification_format_size(VERIFICATION_MAX_BYTES)); ?> each. Only you and the KaFoodie reviewer can open these files.</p>
  </section>

  <?php if (!$locked): ?>
    <form class="verif-submit" action="verification_submit.php" method="POST">
      <input type="hidden" name="csrf" value="<?php echo verification_h($csrf); ?>">
      <button type="submit" class="btn-primary btn-inline" <?php echo $missing ? 'disabled' : ''; ?>>
        Submit for review
      </button>
      <?php if ($missing): ?>
        <span class="verif-submit-hint">Still needed: <?php echo verification_h(implode(', ', $missing)); ?></span>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
