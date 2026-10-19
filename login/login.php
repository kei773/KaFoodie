<?php
session_start();

// Already logged in? Go straight to the right dashboard.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'customer') {
        header('Location: ../customer/dashboard.php');
        exit;
    }
    if ($_SESSION['role'] === 'shop') {
        header('Location: ../shop/dashboard.php');
        exit;
    }
}

// CSRF token for the form
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// One-time messages and the email to refill after a failed attempt
$login_error   = $_SESSION['login_error']     ?? null;
$login_success = $_SESSION['login_success']   ?? null;
$old_email     = $_SESSION['login_old_email'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['login_old_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaFoodie &mdash; Log in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<main class="customer-login">

  <section class="customer-login-brand" aria-label="About KaFoodie">
    <span class="customer-login-orb customer-login-orb-top" aria-hidden="true"></span>
    <span class="customer-login-orb customer-login-orb-bottom" aria-hidden="true"></span>
    <a class="customer-login-logo" href="../index.php" aria-label="Return to KaFoodie home">
      <img src="../assets/images/logo-mark-reversed.svg" alt="">
      <span>KaFoodie</span>
    </a>
    <div class="customer-login-copy">
      <h1>Good food is one<br>login away</h1>
      <p>Sign in to order from your favorite local vendors and track every delivery.</p>
    </div>
  </section>

  <section class="customer-login-form-side" aria-label="Customer sign in">
    <div class="customer-login-form-stack">
      <div class="customer-login-card">
        <a class="auth-back-link" href="../index.php">&larr; Back to home</a>
        <div class="customer-login-heading">
          <h2>Welcome back, foodie</h2>
          <p>Log in to continue your food adventure.</p>
        </div>

        <?php if ($login_success): ?>
          <div class="alert-success" role="status"><?php echo htmlspecialchars($login_success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($login_error): ?>
          <div class="alert-error" role="alert"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8'); ?>">

          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="you@email.com"
                   autocomplete="email" value="<?php echo htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password"
                   autocomplete="current-password" required>
          </div>

          <button type="submit" class="btn-primary">Log in</button>
        </form>

        <p class="customer-login-links">New to KaFoodie? <a href="../signup/signup.php">Create an account</a></p>
      </div>

      <p class="customer-login-vendor">Are you a vendor? <a href="../vendor/vendor_login.php">Vendor login</a></p>
    </div>
  </section>

</main>
</body>
</html>