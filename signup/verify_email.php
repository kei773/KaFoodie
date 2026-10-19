<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/otp_helpers.php';

$token   = $_SESSION['pending_signup_token'] ?? '';
$pending = $token !== '' ? otp_get_pending($con, $token) : null;

if (!$pending) {
    unset($_SESSION['pending_signup_token']);
    $_SESSION['signup_error'] = 'Your signup session expired. Please sign up again.';
    header('Location: signup.php');
    exit;
}

$is_vendor = ($pending['role'] === 'shop');

if (empty($_SESSION['otp_csrf'])) {
    $_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
}

// ----- Form actions: verify, resend, cancel -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['otp_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['otp_error'] = 'Your session expired. Please try again.';

    } elseif ($action === 'verify') {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        if (strlen($code) !== OTP_LENGTH) {
            $_SESSION['otp_error'] = 'Please enter the ' . OTP_LENGTH . '-digit code.';
        } else {
            $role = otp_check($con, $token, $code, $err);
            if ($role !== null) {
                unset($_SESSION['pending_signup_token'], $_SESSION['otp_csrf']);
                $_SESSION['login_success'] = 'Email verified! Your account is ready. You can now log in.';
                header('Location: ' . ($role === 'shop' ? '../vendor/vendor_login.php' : '../login/login.php'));
                exit;
            }
            $_SESSION['otp_error'] = $err;
        }

    } elseif ($action === 'resend') {
        if (otp_resend($con, $token, $err)) {
            $_SESSION['otp_success'] = 'A new code was sent to your email.';
        } else {
            $_SESSION['otp_error'] = $err;
        }

    } elseif ($action === 'cancel') {
        otp_cancel($con, $token);
        unset($_SESSION['pending_signup_token'], $_SESSION['otp_csrf']);
        header('Location: ' . ($is_vendor ? '../vendor/vendor_signup.php' : 'signup.php'));
        exit;
    }

    header('Location: verify_email.php');
    exit;
}

$error   = $_SESSION['otp_error'] ?? null;
$success = $_SESSION['otp_success'] ?? null;
unset($_SESSION['otp_error'], $_SESSION['otp_success']);

// j***@gmail.com
function mask_email(string $email): string {
    [$user, $domain] = explode('@', $email, 2) + ['', ''];
    return mb_substr($user, 0, 1) . str_repeat('*', max(2, mb_strlen($user) - 1)) . '@' . $domain;
}

$cooldown = (int)$pending['cooldown_left'];
$icons    = ['drumstick', 'coffee', 'forkspoon', 'bowl', 'burger'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Verify your email</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <div class="login-icon-pattern">
      <?php for ($i = 1; $i <= 30; $i++): ?>
        <img src="../assets/images/<?php echo $icons[($i - 1) % 5]; ?>.svg" class="icon-p<?php echo $i; ?>" alt="">
      <?php endfor; ?>
    </div>

    <div class="brand-row">
      <img class="mark" src="../assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
      <span class="brand-word">KaFoodie</span>
    </div>

    <div class="pitch">
      <h1>Almost there!</h1>
      <p>We sent a code to your email to make sure it really is yours.</p>
    </div>

    <div class="foot">© 2026 KaFoodie</div>
  </div>

  <div class="login-form-side">
    <div class="login-card">
      <h2>Verify your email</h2>
      <p class="sub">
        Enter the <?php echo OTP_LENGTH; ?>-digit code we sent to
        <strong><?php echo htmlspecialchars(mask_email($pending['email']), ENT_QUOTES, 'UTF-8'); ?></strong>.
        It expires in <?php echo OTP_VALID_MINUTES; ?> minutes.
      </p>

      <?php if ($error): ?>
        <div class="alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form action="verify_email.php" method="POST" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo $_SESSION['otp_csrf']; ?>">
        <input type="hidden" name="action" value="verify">

        <div class="field">
          <label for="code">Verification code</label>
          <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}"
                 maxlength="6" placeholder="000000" autocomplete="one-time-code" required autofocus
                 style="text-align:center;font-size:24px;letter-spacing:8px;">
        </div>

        <button type="submit" class="btn-primary">Verify and create account</button>
      </form>

      <form action="verify_email.php" method="POST" style="margin-top:14px;">
        <input type="hidden" name="csrf" value="<?php echo $_SESSION['otp_csrf']; ?>">
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="btn-outline" id="resendBtn" style="width:100%;"
                <?php echo $cooldown > 0 ? 'disabled' : ''; ?>>
          <?php echo $cooldown > 0 ? 'Resend code in ' . $cooldown . 's' : 'Resend code'; ?>
        </button>
      </form>

      <form action="verify_email.php" method="POST">
        <input type="hidden" name="csrf" value="<?php echo $_SESSION['otp_csrf']; ?>">
        <input type="hidden" name="action" value="cancel">
        <p class="signup-note">
          Wrong email?
          <button type="submit" style="background:none;border:0;padding:0;font:inherit;color:var(--orange-dark);font-weight:600;cursor:pointer;">Go back and change it</button>
        </p>
      </form>
    </div>
  </div>

</div>

<script>
(function () {
  var left = <?php echo $cooldown; ?>;
  var btn  = document.getElementById('resendBtn');
  if (left <= 0) { return; }
  var timer = setInterval(function () {
    left--;
    if (left <= 0) {
      clearInterval(timer);
      btn.disabled = false;
      btn.textContent = 'Resend code';
    } else {
      btn.textContent = 'Resend code in ' + left + 's';
    }
  }, 1000);
})();
</script>
</body>
</html>
