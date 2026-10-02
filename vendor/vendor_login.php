<?php
session_start();

// Already logged in? Go straight to the right dashboard.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'shop') {
        header('Location: shop/dashboard.php');
        exit;
    }
    if ($_SESSION['role'] === 'customer') {
        header('Location: customer/dashboard.php');
        exit;
    }
}

$login_error   = $_SESSION['login_error']   ?? null;
$login_success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_error'], $_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KaFoodie &mdash; Vendor login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="vendor-login">

  <section class="vendor-login-panel" aria-label="Vendor benefits">
    <span class="vendor-login-orb vendor-login-orb-top" aria-hidden="true"></span>
    <span class="vendor-login-orb vendor-login-orb-bottom" aria-hidden="true"></span>
    <a class="vendor-login-brand" href="index.php" aria-label="Return to KaFoodie home">
      <img src="assets/images/logo-mark-reversed.svg" alt="">
      <span>KaFoodie</span>
    </a>
    <div class="vendor-login-copy">
      <h1>Grow your <span>food business</span> with KaFoodie</h1>
      <ul class="vendor-login-benefits">
        <li><span class="vendor-login-check" aria-hidden="true">&#10003;</span><span>Reach thousands of hungry customers nearby</span></li>
        <li><span class="vendor-login-check" aria-hidden="true">&#10003;</span><span>Manage your menu and orders in one dashboard</span></li>
        <li><span class="vendor-login-check" aria-hidden="true">&#10003;</span><span>Get verified fast and start selling in days</span></li>
      </ul>
    </div>
    <img class="vendor-login-shop" src="assets/images/shop.svg" alt="" aria-hidden="true">
  </section>

  <section class="vendor-login-form-side" aria-label="Vendor sign in">
    <div class="vendor-login-form-stack">
      <div class="vendor-login-card">
        <a class="auth-back-link" href="index.php">&larr; Back to home</a>
        <div class="vendor-login-heading">
          <h2>Vendor login</h2>
          <p>Manage your shop, menu and orders.</p>
        </div>

        <?php if ($login_success): ?>
          <div class="alert-success" role="status"><?php echo htmlspecialchars($login_success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($login_error): ?>
          <div class="alert-error" role="alert"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="vendor_login_process.php" method="POST">
          <div class="field">
            <label for="email">Business email</label>
            <input type="email" id="email" name="email" placeholder="vendor@shop.com" autocomplete="email" required>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn-primary">Log in</button>
        </form>

        <p class="signup-note">Want to sell on KaFoodie? <a href="vendor_signup.php">Apply as a vendor</a></p>
      </div>
      <p class="vendor-login-customer-link">Are you a customer? <a href="login.php">Customer login</a></p>
    </div>
  </section>

</main>
</body>
</html>