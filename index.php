<?php
session_start();
require_once __DIR__ . '/database/connection.php';
require_once __DIR__ . '/includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'customer') {
        header('Location: customer/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'shop') {
        header('Location: shop/dashboard.php');
        exit;
    }
}

$vendors = [];
$sql = "
    SELECT s.id, s.shop_name, s.description, s.logo_url, s.logo_mime,
           UNIX_TIMESTAMP(s.logo_updated_at) AS logo_v,
           COUNT(mi.id) AS item_count
    FROM shops s
    LEFT JOIN menu_items mi ON mi.shop_id = s.id AND mi.is_available = 1
    GROUP BY s.id, s.shop_name, s.description, s.logo_url, s.logo_mime, s.logo_updated_at
    ORDER BY s.shop_name
";
$result = $con->query($sql);
if ($result) {
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Your neighborhood's food, delivered</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="landing-body">

<!-- ================= NAVBAR ================= -->
<header class="landing-nav">
  <div class="landing-nav-brand">
    <img class="landing-nav-logo" src="assets/images/logo-mark.svg" alt="KaFoodie logo">
    <span class="landing-nav-word">KaFoodie</span>
  </div>

  <div class="landing-nav-actions">
    <a href="login/login.php" class="btn-text">Log in</a>
    <a href="login/login.php" class="btn-primary btn-inline">Order now</a>
  </div>
</header>

<!-- ================= HERO ================= -->
<section class="hero" id="home">
  <div class="hero-decor" aria-hidden="true">
    <span class="hero-blob hero-blob-1"></span>
    <span class="hero-blob hero-blob-2"></span>
  </div>

  <div class="hero-inner">
    <div class="hero-copy">
      <h1>Your favorite Filipino <span>comfort food</span>, delivered.</h1>
      <p class="hero-sub">Order from local vendors near you — from silog breakfasts to sizzling sisig and cold halo-halo — fresh, fast, and always kapamilya-friendly.</p>

      <div class="hero-buttons">
        <a href="login/login.php" class="btn-primary hero-btn">Order as customer</a>
        <a href="vendor/vendor_login.php" class="btn-outline hero-btn">Sell on KaFoodie</a>
      </div>

      <form action="customer/dashboard.php" method="GET" class="hero-search">
        <span class="hero-search-icon" aria-hidden="true">🔎</span>
        <input type="text" name="q" placeholder="What are you craving today?">
        <button type="submit" class="btn-primary btn-inline">Search</button>
      </form>
    </div>

    <div class="hero-photo-wrap">
      <div class="hero-photo">
        <img src="assets/landing/food1.jpg" alt="A plate of Filipino comfort food">
      </div>
    </div>
  </div>
</section>

<div class="landing-transition" aria-hidden="true">
  <span></span>
  <p>Explore local vendors</p>
  <span></span>
</div>

<!-- ================= POPULAR VENDORS ================= -->
<section class="popular-vendors" id="vendors">
  <div class="section-header">
    <div class="section-titles">
      <h2>Popular vendors</h2>
      <p>Loved by your neighbors, freshly made.</p>
    </div>
    <a href="login/login.php" class="btn-outline">Browse all vendors</a>
  </div>

  <?php if (empty($vendors)): ?>
    <div class="dash-card">
      <p class="dash-empty">No vendors have joined KaFoodie yet — check back soon.</p>
    </div>
  <?php else: ?>
    <div class="vendor-grid">
      <?php foreach ($vendors as $v): $logo_src = logo_src($v, ''); ?>
        <a href="customer/shop_menu.php?id=<?php echo $v['id']; ?>" class="vendor-card" style="text-decoration: none; color: inherit; display: block;">
          <div class="vendor-card-cover placeholder-photo<?php echo $logo_src !== '' ? ' has-logo' : ''; ?>">
            <?php if ($logo_src !== ''): ?><img class="vendor-card-logo" src="<?php echo htmlspecialchars($logo_src, ENT_QUOTES); ?>" alt=""><?php else: ?><span class="placeholder-emoji">🏪</span><?php endif; ?>
          </div>
          <div class="vendor-card-body">
            <div class="vendor-card-top">
              <span class="vendor-card-name"><?php echo htmlspecialchars($v['shop_name']); ?></span>
            </div>
            <?php if (!empty($v['description'])): ?>
              <div class="vendor-card-tag"><?php echo htmlspecialchars($v['description']); ?></div>
            <?php endif; ?>
            <div class="vendor-card-time">
              <?php echo (int)$v['item_count']; ?> dish<?php echo $v['item_count'] == 1 ? '' : 'es'; ?> available
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="how-it-works" id="how-it-works">
  <div class="section-titles centered">
    <h2>How it works</h2>
    <p>Three easy steps from craving to doorstep.</p>
  </div>

  <div class="steps">
    <div class="step">
      <div class="step-number">1</div>
      <h3>Pick a vendor</h3>
      <p>Browse local Filipino kitchens near you.</p>
    </div>
    <div class="step">
      <div class="step-number">2</div>
      <h3>Choose your food</h3>
      <p>Open the shop, explore the menu, and add to cart.</p>
    </div>
    <div class="step">
      <div class="step-number">3</div>
      <h3>Get it delivered</h3>
      <p>Track your order and enjoy your meal.</p>
    </div>
  </div>
</section>

<!-- ================= CTA BAND ================= -->
<section class="cta-band">
  <div class="cta-band-icons" aria-hidden="true">
    <?php
      $cta_icons = ['🍔', '🥣', '🍗', '🥢', '🥬', '☕', '🍴'];
      for ($i = 0; $i < 27; $i++) {
          echo '<span>' . $cta_icons[$i % count($cta_icons)] . '</span>';
      }
    ?>
  </div>

  <div class="cta-band-inner">
    <div class="cta-col">
      <h3>Hungry?<br>Order now</h3>
      <p>Browse local Filipino vendors and get your favorites delivered fast.</p>
      <a href="login/login.php" class="btn-primary">Order now</a>
    </div>
    <div class="cta-divider" aria-hidden="true"></div>
    <div class="cta-col">
      <h3>Own a food business?<br>Join KaFoodie</h3>
      <p>Become a KaFoodie vendor and reach thousands of hungry customers.</p>
      <a href="vendor/vendor_signup.php" class="btn-primary btn-light">Become a vendor</a>
    </div>
  </div>
</section>

<!-- ================= FOOTER ================= -->
<footer class="landing-footer" id="about">
  <div class="footer-top">
    <div class="footer-brand">
      <div class="footer-brand-row">
        <img class="landing-nav-logo" src="assets/images/logo-mark-reversed.svg" alt="KaFoodie logo">
        <span class="landing-nav-word">KaFoodie</span>
      </div>
      <p>Connecting Filipino food lovers with local vendors, one order at a time.</p>
    </div>

    <div class="footer-col">
      <h4>Company</h4>
      <a href="#about">About us</a>
      <a href="#">Careers</a>
      <a href="#">Press</a>
      <a href="#">Contact</a>
    </div>

    <div class="footer-col">
      <h4>For customers</h4>
      <a href="#how-it-works">How it works</a>
      <a href="#vendors">Browse vendors</a>
      <a href="#">Help center</a>
      <a href="login/login.php">Log in</a>
    </div>

    <div class="footer-col">
      <h4>For vendors</h4>
      <a href="vendor/vendor_signup.php">Become a vendor</a>
      <a href="vendor/vendor_login.php">Vendor login</a>
      <a href="#">Vendor guidelines</a>
    </div>
  </div>

  <div class="footer-divider"></div>

  <div class="footer-bottom">
    <span>© 2026 KaFoodie. All rights reserved.</span>
    <span>Privacy Policy &nbsp;·&nbsp; Terms of Service</span>
  </div>
</footer>

</body>
</html>