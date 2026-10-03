<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

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
<title>KaFoodie — Vendor Registration</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <div class="login-icon-pattern">
        <img src="../assets/images/drumstick.svg" class="icon-p1" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p2" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p3" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p4" alt="">
        <img src="../assets/images/burger.svg" class="icon-p5" alt="">
        <img src="../assets/images/drumstick.svg" class="icon-p6" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p7" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p8" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p9" alt="">
        <img src="../assets/images/burger.svg" class="icon-p10" alt="">
        <img src="../assets/images/drumstick.svg" class="icon-p11" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p12" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p13" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p14" alt="">
        <img src="../assets/images/burger.svg" class="icon-p15" alt="">
        <img src="../assets/images/drumstick.svg" class="icon-p16" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p17" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p18" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p19" alt="">
        <img src="../assets/images/burger.svg" class="icon-p20" alt="">
        <img src="../assets/images/drumstick.svg" class="icon-p21" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p22" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p23" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p24" alt="">
        <img src="../assets/images/burger.svg" class="icon-p25" alt="">
        <img src="../assets/images/drumstick.svg" class="icon-p26" alt="">
        <img src="../assets/images/coffee.svg" class="icon-p27" alt="">
        <img src="../assets/images/forkspoon.svg" class="icon-p28" alt="">
        <img src="../assets/images/bowl.svg" class="icon-p29" alt="">
        <img src="../assets/images/burger.svg" class="icon-p30" alt="">
    </div>

    <div class="brand-row">
      <img class="mark" src="../assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
      <span class="brand-word">KaFoodie</span>
    </div>

    <div class="pitch">
      <h1>Grow your food business with KaFoodie.</h1>
      <p>Reach thousands of hungry customers and manage your shop with ease.</p>
    </div>

    <div class="foot">© 2026 KaFoodie</div>
  </div>

  <div class="login-form-side">
    <div class="login-card">
      <a class="auth-back-link" href="../index.php" aria-label="Return to KaFoodie home">&larr; Back to home</a>
      <h2>Create a Vendor Account</h2>
      <p class="sub">Register your business to start selling.</p>

      <?php if ($signup_error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($signup_error); ?></div>
      <?php endif; ?>

      <form action="vendor_signup_process.php" method="POST">

        <div class="field">
          <label for="name">Full name (Business Owner)</label>
          <input type="text" id="name" name="name" placeholder="Juan Dela Cruz"
                 value="<?php echo htmlspecialchars($old['name'] ?? ''); ?>" required>
        </div>

        <div class="field">
          <label for="email">Business email</label>
          <input type="email" id="email" name="email" placeholder="vendor@shop.com"
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

      <div class="signup-note" style="text-align:center; margin-bottom:15px;">
        Looking for food? <a href="../signup/signup.php">Sign up as a customer</a>
      </div>
      <p class="signup-note">Already have an account? <a href="vendor_login.php">Log in</a></p>
    </div>
  </div>

</div>

</body>
</html>