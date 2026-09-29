<?php
session_start();
require_once __DIR__ . '/database/connection.php';

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
<title>KaFoodie — Create an account</title>
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
      <h1>Join your neighborhood's favorite food spots.</h1>
      <p>Create an account to start ordering, delivering, or selling on KaFoodie.</p>
    </div>

    <div class="foot">© 2026 KaFoodie</div>
  </div>

  <div class="login-form-side">
    <div class="login-card">
      <h2>Create an account</h2>
      <p class="sub">Choose your account type to get started.</p>

      <?php if ($signup_error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($signup_error); ?></div>
      <?php endif; ?>

      <form action="signup_process.php" method="POST">

        <div class="role-select" role="radiogroup" aria-label="Account type">
          <input type="radio" name="role" id="role-customer" value="customer" <?php echo (($old['role'] ?? 'customer') === 'customer') ? 'checked' : ''; ?>>
          <label for="role-customer">
            <span class="icon" style="--icon: url(images/icon-customer.svg);"></span>
            Customer
          </label>

          <input type="radio" name="role" id="role-rider" value="rider" <?php echo (($old['role'] ?? '') === 'rider') ? 'checked' : ''; ?>>
          <label for="role-rider">
            <span class="icon" style="--icon: url(images/icon-rider.svg);"></span>
            Rider
          </label>

          <input type="radio" name="role" id="role-shop" value="shop" <?php echo (($old['role'] ?? '') === 'shop') ? 'checked' : ''; ?>>
          <label for="role-shop">
            <span class="icon" style="--icon: url(images/icon-shop.svg);"></span>
            Food shop
          </label>

          <input type="radio" name="role" id="role-admin" value="admin" <?php echo (($old['role'] ?? '') === 'admin') ? 'checked' : ''; ?>>
          <label for="role-admin">
            <span class="icon" style="--icon: url(images/icon-admin.svg);"></span>
            Admin
          </label>
        </div>

        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" placeholder="Juan Dela Cruz"
                 value="<?php echo htmlspecialchars($old['name'] ?? ''); ?>" required>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" placeholder="you@email.com"
                 value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-primary">Create account</button>
      </form>

      <p class="signup-note">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div>

</div>

</body>
</html>