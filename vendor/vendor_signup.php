<?php
session_start();

// Error message and previously typed values after a failed attempt
$signup_error = $_SESSION['signup_error'] ?? null;
unset($_SESSION['signup_error']);

$old = $_SESSION['signup_old'] ?? [];
unset($_SESSION['signup_old']);

// Which step to reopen after an error (1 = account, 2 = business details)
$start_step = (int)($_SESSION['signup_step'] ?? 1);
unset($_SESSION['signup_step']);
if ($start_step !== 2) { $start_step = 1; }

// Keep these lists identical to the ones in signup/signup_process.php
$categories = ['Restaurant', 'Cafe', 'Bakery', 'Food stall / Carinderia', 'Home-based kitchen'];
$cuisines   = ['Filipino', 'Fast food', 'Pizza', 'Burgers', 'Chicken', 'Asian', 'Desserts & snacks', 'Coffee & tea', 'Other'];

function old_val($old, $key, $default = '') {
    return htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
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

        <ol class="signup-steps" aria-label="Registration progress">
          <li class="signup-step" id="stepTab1" data-step="1">Your account</li>
          <li class="signup-step" id="stepTab2" data-step="2">Your business</li>
        </ol>

        <?php if ($signup_error): ?>
          <div class="alert-error" role="alert"><?php echo htmlspecialchars($signup_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="alert-error" id="stepError" role="alert" hidden></div>

        <form action="vendor_signup_process.php" method="POST" id="vendorSignupForm" novalidate>

          <!-- Step 1: account -->
          <div class="signup-pane" id="pane1" data-step="1">
            <div class="field">
              <label for="name">Full name (business owner)</label>
              <input type="text" id="name" name="name" placeholder="Juan Dela Cruz" autocomplete="name"
                     value="<?php echo old_val($old, 'name'); ?>" required>
            </div>
            <div class="field">
              <label for="email">Business email</label>
              <input type="email" id="email" name="email" placeholder="vendor@shop.com" autocomplete="email"
                     value="<?php echo old_val($old, 'email'); ?>" required>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" placeholder="At least 6 characters"
                     autocomplete="new-password" minlength="6" required>
            </div>
            <div class="field">
              <label for="confirm_password">Confirm password</label>
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password"
                     autocomplete="new-password" required>
            </div>
            <button type="button" class="btn-primary" id="nextBtn">Continue to business details</button>
          </div>

          <!-- Step 2: business details -->
          <div class="signup-pane" id="pane2" data-step="2">
            <div class="field">
              <label for="business_name">Business name</label>
              <input type="text" id="business_name" name="business_name" placeholder="Pizza Town" maxlength="150"
                     value="<?php echo old_val($old, 'business_name'); ?>" required>
            </div>
            <div class="field">
              <label for="business_category">Business category</label>
              <select id="business_category" name="business_category" required>
                <option value="" disabled <?php echo empty($old['business_category']) ? 'selected' : ''; ?>>Choose a category</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo (($old['business_category'] ?? '') === $c) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="cuisine">Cuisine</label>
              <select id="cuisine" name="cuisine" required>
                <option value="" disabled <?php echo empty($old['cuisine']) ? 'selected' : ''; ?>>Choose a cuisine</option>
                <?php foreach ($cuisines as $c): ?>
                  <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo (($old['cuisine'] ?? '') === $c) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="signup-actions">
              <button type="button" class="btn-outline" id="backBtn">Back</button>
              <button type="submit" class="btn-primary">Create account</button>
            </div>
          </div>

        </form>

        <p class="signup-note">Already have an account? <a href="vendor_login.php">Log in</a></p>
      </div>
      <p class="vendor-login-customer-link">Looking for food? <a href="../signup/signup.php">Sign up as a customer</a></p>
    </div>
  </section>

</main>

<script>
(function () {
  var form   = document.getElementById('vendorSignupForm');
  var panes  = { 1: document.getElementById('pane1'), 2: document.getElementById('pane2') };
  var tabs   = { 1: document.getElementById('stepTab1'), 2: document.getElementById('stepTab2') };
  var errBox = document.getElementById('stepError');

  function showStep(n) {
    for (var i = 1; i <= 2; i++) {
      panes[i].hidden = (i !== n);
      tabs[i].classList.toggle('is-current', i === n);
      tabs[i].classList.toggle('is-done', i < n);
      if (i === n) { tabs[i].setAttribute('aria-current', 'step'); }
      else { tabs[i].removeAttribute('aria-current'); }
    }
    errBox.hidden = true;
    var first = panes[n].querySelector('input, select');
    if (first) { first.focus(); }
  }

  function stepOneError() {
    var name = form.name.value.trim();
    var email = form.email.value.trim();
    if (!name || !email || !form.password.value || !form.confirm_password.value) {
      return 'Please fill in all fields.';
    }
    if (!form.email.checkValidity()) { return 'Please enter a valid email address.'; }
    if (form.password.value.length < 6) { return 'Password must be at least 6 characters.'; }
    if (form.password.value !== form.confirm_password.value) { return 'Passwords do not match.'; }
    return '';
  }

  function stepTwoError() {
    if (!form.business_name.value.trim() || !form.business_category.value || !form.cuisine.value) {
      return 'Please fill in your business details.';
    }
    return '';
  }

  function showError(msg) {
    errBox.textContent = msg;
    errBox.hidden = false;
  }

  document.getElementById('nextBtn').addEventListener('click', function () {
    var msg = stepOneError();
    if (msg) { showError(msg); return; }
    showStep(2);
  });

  document.getElementById('backBtn').addEventListener('click', function () { showStep(1); });

  form.addEventListener('submit', function (e) {
    var msg = stepOneError();
    if (msg) { e.preventDefault(); showStep(1); showError(msg); return; }
    msg = stepTwoError();
    if (msg) { e.preventDefault(); showError(msg); }
  });

  // Without JavaScript both panes stay visible; with it, show one step at a time.
  showStep(<?php echo $start_step; ?>);
})();
</script>
</body>
</html>