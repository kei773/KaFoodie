<?php

$nav_role = $_SESSION['role'] ?? '';
$nav_name = $_SESSION['name'] ?? '';

// Shop owners are stored with the role 'shop'. Their logo URL is set by shop/dashboard.php.
$nav_logo = ($nav_role === 'shop') ? trim((string)($_SESSION['logo_url'] ?? '')) : '';
?>
<div class="dash-nav">
  <div class="dash-nav-brand">
    <span class="dash-nav-logo" role="img" aria-label="KaFoodie logo"></span>
    <span class="dash-nav-word">KaFoodie</span>
  </div>
  <div class="dash-nav-user">
    <div class="dash-nav-profile">
      <?php if ($nav_role === 'shop'): ?>
        <?php if ($nav_logo !== ''): ?>
          <img class="dash-nav-avatar" src="<?php echo htmlspecialchars($nav_logo, ENT_QUOTES); ?>" alt="Your shop logo">
        <?php else: ?>
          <span class="dash-nav-avatar dash-nav-avatar-placeholder" role="img" aria-label="Shop logo placeholder">🏪</span>
        <?php endif; ?>
      <?php endif; ?>
      <span class="dash-nav-name"><?php echo htmlspecialchars($nav_name); ?></span>
    </div>
    <span class="dash-nav-role"><?php echo htmlspecialchars(ucfirst($nav_role)); ?></span>
    <a href="../logout.php" class="dash-nav-logout">Log out</a>
  </div>
</div>