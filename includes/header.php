<?php
// Shared top navbar for every dashboard.
$cart_count = 0;
if (($_SESSION['role'] ?? '') === 'customer' && !empty($_SESSION['cart']['items'])) {
    $cart_count = array_sum($_SESSION['cart']['items']);
}
?>
<div class="dash-nav">
  <div class="dash-nav-brand">
    <img class="dash-nav-logo" src="../assets/images/logo-mark.svg" alt="KaFoodie logo">
    <span class="dash-nav-word">KaFoodie</span>
  </div>
  <div class="dash-nav-user">
    <?php if ($_SESSION['role'] === 'customer'): ?>
      <a href="cart.php" class="dash-nav-cart">
        Cart
        <?php if ($cart_count > 0): ?><span class="dash-nav-cart-count"><?php echo $cart_count; ?></span><?php endif; ?>
      </a>
    <?php endif; ?>
    <span class="dash-nav-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
    <span class="dash-nav-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></span>
    <a href="../logout.php" class="dash-nav-logout">Log out</a>
  </div>
</div>