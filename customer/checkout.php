<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cart_helpers.php';
require_once __DIR__ . '/../includes/paypal_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

$cart = kf_load_cart($con);
if (empty($cart['items'])) {
    header('Location: cart.php');
    exit;
}

$error = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_error']);
$csrf = csrf_token();

// null when PayPal has not been set up yet: the PayPal option is then shown as unavailable
$pp = paypal_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Checkout</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <div class="cart-topbar">
    <a href="cart.php" class="cart-back">← Back to cart</a>
  </div>

  <div class="browse-greeting">
    <h1>Checkout</h1>
    <p>Ordering from <?= h($cart['shop_name']) ?>. Pickup only: you collect your order at the shop.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert-error" role="alert"><?= h($error) ?></div>
  <?php endif; ?>

  <form action="checkout_process.php" method="POST" id="checkoutForm">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <div class="dash-card">
      <h2 class="admin-section-title">Your order</h2>
      <div class="cart-list">
        <?php foreach ($cart['items'] as $item): ?>
          <div class="co-row">
            <div class="cart-row-info">
              <?php if ($item['image'] !== ''): ?>
                <img class="cart-thumb" src="<?= h($item['image']) ?>" alt="">
              <?php else: ?>
                <span class="cart-thumb" aria-hidden="true">🍽️</span>
              <?php endif; ?>
              <div class="cart-row-text">
                <div class="cart-row-name"><?= h($item['name']) ?></div>
                <div class="cart-row-meta"><?= (int)$item['quantity'] ?> × ₱<?= number_format($item['price'], 2) ?></div>
              </div>
            </div>
            <div class="cart-row-subtotal">₱<?= number_format($item['subtotal'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cart-total-row">
        <span>Total</span>
        <span>₱<?= number_format($cart['total'], 2) ?></span>
      </div>
    </div>

    <div class="dash-card co-block">
      <h2 class="admin-section-title">Payment</h2>

      <label class="pay-option">
        <input type="radio" name="payment_method" value="pay_at_shop" checked>
        <span class="pay-option-text">
          <strong>Pay at the shop</strong>
          <small>Pay when you pick up your order.</small>
        </span>
      </label>

      <label class="pay-option<?= $pp ? '' : ' is-disabled' ?>">
        <input type="radio" name="payment_method" value="paypal_sandbox"<?= $pp ? '' : ' disabled' ?>>
        <span class="pay-option-text">
          <strong>PayPal</strong>
          <small><?= $pp ? 'Pay now with PayPal (sandbox test payment, no real money).' : 'Not available yet.' ?></small>
        </span>
      </label>

      <button type="submit" class="btn-primary btn-checkout" id="placeBtn">Place order</button>

      <?php if ($pp): ?>
        <div class="paypal-wrap" id="paypalWrap" hidden>
          <p class="paypal-note">You will pay ₱<?= number_format($cart['total'], 2) ?> in the PayPal window. Your order is placed as soon as the payment goes through.</p>
          <div id="paypalButtons"></div>
          <p class="pay-status" id="payStatus" role="status" hidden></p>
        </div>
      <?php endif; ?>
      <div class="alert-error" id="payError" role="alert" hidden></div>
    </div>
  </form>

</div>

<?php if ($pp): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h(rawurlencode($pp['client_id'])) ?>&amp;currency=<?= h($pp['currency']) ?>&amp;intent=capture&amp;components=buttons"></script>
<?php endif; ?>
<script>
(function () {
  var form     = document.getElementById('checkoutForm');
  var placeBtn = document.getElementById('placeBtn');
  var wrap     = document.getElementById('paypalWrap');
  var errBox   = document.getElementById('payError');
  var status   = document.getElementById('payStatus');
  var csrf     = <?= json_encode($csrf) ?>;
  var rendered = false;

  function selected() {
    var c = form.querySelector('input[name="payment_method"]:checked');
    return c ? c.value : 'pay_at_shop';
  }
  function showError(msg) { errBox.textContent = msg; errBox.hidden = false; }
  function clearError()   { errBox.hidden = true; }

  function post(url, params) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params)
    }).then(function (r) { return r.json(); });
  }

  function renderPaypal() {
    if (typeof paypal === 'undefined') {
      showError('PayPal could not load. Check your internet connection and refresh the page.');
      return;
    }
    rendered = true;
    paypal.Buttons({
      style: { layout: 'vertical', shape: 'pill', label: 'paypal' },

      createOrder: function () {
        clearError();
        return post('paypal_create.php', { csrf: csrf }).then(function (d) {
          if (!d.id) { throw new Error(d.error || 'Could not start the payment.'); }
          return d.id;
        });
      },

      onApprove: function (data) {
        status.textContent = 'Confirming your payment…';
        status.hidden = false;
        return post('paypal_capture.php', { csrf: csrf, orderID: data.orderID }).then(function (d) {
          if (d.ok) {
            window.location.href = d.redirect;
          } else {
            status.hidden = true;
            showError(d.error || 'We could not confirm your payment.');
          }
        }).catch(function () {
          status.hidden = true;
          showError('We could not confirm your payment. Please check My orders before trying again.');
        });
      },

      onCancel: function () { showError('Payment cancelled. Your cart is unchanged.'); },
      onError: function (err) {
        showError(err && err.message ? err.message : 'Something went wrong with PayPal. Please try again.');
      }
    }).render('#paypalButtons');
  }

  // Pay at the shop shows the Place order button; PayPal shows the PayPal buttons instead
  function sync() {
    var usePaypal = wrap && selected() === 'paypal_sandbox';
    placeBtn.hidden = !!usePaypal;
    if (wrap) { wrap.hidden = !usePaypal; }
    clearError();
    if (usePaypal && !rendered) { renderPaypal(); }
  }

  form.querySelectorAll('input[name="payment_method"]').forEach(function (r) {
    r.addEventListener('change', sync);
  });

  // Stop double clicks from placing a pay-at-the-shop order twice
  form.addEventListener('submit', function () {
    placeBtn.disabled = true;
    placeBtn.textContent = 'Placing your order…';
  });

  sync();
})();
</script>

</body>
</html>
