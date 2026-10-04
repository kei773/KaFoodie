<?php
session_start();

// Error message and previously typed values after a failed attempt
$signup_error = $_SESSION['signup_error'] ?? null;
unset($_SESSION['signup_error']);

$old = $_SESSION['signup_old'] ?? [];
unset($_SESSION['signup_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KaFoodie &mdash; Vendor registration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<main class="vendor-login">

  <section class="vendor-login-panel" aria-label="Vendor benefits">
    <span class="vendor-login-orb vendor-login-orb-top" aria-hidden="true"></span>
    <span class="vendor-login-orb vendor-login-orb-bottom" aria-hidden="true"></span>
    <a class="vendor-login-brand" href="../index.php" aria-label="Return to KaFoodie home">
      <img src="../assets/images/logo-mark-reversed.svg" alt="">
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
    <img class="vendor-login-shop" src="../assets/images/shop.svg" alt="" aria-hidden="true">
  </section>

  <section class="vendor-login-form-side" aria-label="Vendor registration">
    <div class="vendor-login-form-stack">
      <div class="vendor-login-card">
        <a class="auth-back-link" href="../index.php">&larr; Back to home</a>
        <div class="vendor-login-heading">
          <h2>Create a vendor account</h2>
          <p>Register your business to start selling.</p>
        </div>

        <?php if ($signup_error): ?>
          <div class="alert-error" role="alert"><?php echo htmlspecialchars($signup_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="vendor_signup_process.php" method="POST">
          <div class="field">
            <label for="name">Full name (business owner)</label>
            <input type="text" id="name" name="name" placeholder="Juan Dela Cruz" autocomplete="name"
                   value="<?php echo htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
          <div class="field">
            <label for="email">Business email</label>
            <input type="email" id="email" name="email" placeholder="vendor@shop.com" autocomplete="email"
                   value="<?php echo htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" autocomplete="new-password" required>
          </div>
          <button type="submit" class="btn-primary">Create account</button>
        </form>

        <p class="signup-note">Already have an account? <a href="vendor_login.php">Log in</a></p>
      </div>
      <p class="vendor-login-customer-link">Looking for food? <a href="../signup/signup.php">Sign up as a customer</a></p>
    </div>
  </section>

</main>
</body>
</html>