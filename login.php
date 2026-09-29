<?php
session_start();
require_once __DIR__ . '/database/connection.php';

$login_error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

$login_success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Log in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <div class="rings" aria-hidden="true"></div>

    <div class="brand-row">
      <img class="mark" src="assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
      <span class="brand-word">KaFoodie</span>
    </div>

    <div class="pitch">
      <h1>Your neighborhood's food, a few taps away.</h1>
      <p>Cravings, Delivered.</p>
    </div>

    <div class="foot">© 2026 KaFoodie</div>
  </div>

  <div class="login-form-side">
    <div class="login-card">
      <h2>Log in</h2>
      <p class="sub">Choose your account type to continue.</p>

      <?php if ($login_success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($login_success); ?></div>
      <?php endif; ?>

      <?php if ($login_error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($login_error); ?></div>
      <?php endif; ?>

      <form action="login_process.php" method="POST">

        <div class="role-select" role="radiogroup" aria-label="Account type">
          <input type="radio" name="role" id="role-customer" value="customer" checked>
          <label for="role-customer">
            <span class="icon" style="--icon: url(images/icon-customer.svg);"></span>
            Customer
          </label>

          <input type="radio" name="role" id="role-rider" value="rider">
          <label for="role-rider">
            <span class="icon" style="--icon: url(images/icon-rider.svg);"></span>
            Rider
          </label>

          <input type="radio" name="role" id="role-shop" value="shop">
          <label for="role-shop">
            <span class="icon" style="--icon: url(images/icon-shop.svg);"></span>
            Food shop
          </label>

          <input type="radio" name="role" id="role-admin" value="admin">
          <label for="role-admin">
            <span class="icon" style="--icon: url(images/icon-admin.svg);"></span>
            Admin
          </label>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" placeholder="you@email.com" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-primary">Log in</button>
      </form>

      <p class="signup-note">New to KaFoodie? <a href="signup.php">Create an account</a></p>
    </div>
  </div>

</div>

</body>
</html>