<?php
session_start();

// Already logged in as admin? Go straight to the dashboard.
if (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$login_error = $_SESSION['admin_login_error'] ?? null;
$old_email   = $_SESSION['admin_login_old_email'] ?? '';
unset($_SESSION['admin_login_error'], $_SESSION['admin_login_old_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>KaFoodie &mdash; Admin login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <div class="brand-row">
      <img class="mark" src="../assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
      <span class="brand-word">KaFoodie</span>
    </div>

    <div class="pitch">
      <h1>Admin console</h1>
      <p>Review shops and keep KaFoodie trustworthy for customers.</p>
    </div>

    <div class="foot">&copy; 2026 KaFoodie</div>
  </div>

  <div class="login-form-side">
    <div class="login-card">
      <h2>Admin login</h2>
      <p class="sub">For KaFoodie staff only.</p>

      <?php if ($login_error): ?>
        <div class="alert-error" role="alert"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form action="admin_login_process.php" method="POST">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" autocomplete="email"
                 value="<?php echo htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn-primary">Log in</button>
      </form>
    </div>
  </div>

</div>

</body>
</html>
