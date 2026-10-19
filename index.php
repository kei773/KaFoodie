<?php
session_start();
require_once __DIR__ . '/database/connection.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/rating_helpers.php';

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
    SELECT s.id, s.shop_name, s.description, s.logo_url,
           r.avg_rating, r.rating_count,
           COUNT(mi.id) AS item_count
    FROM shops s
    LEFT JOIN menu_items mi ON mi.shop_id = s.id AND mi.is_available = 1
    LEFT JOIN (SELECT shop_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
               FROM shop_ratings GROUP BY shop_id) r ON r.shop_id = s.id
    WHERE s.verification_status = 'approved'
    GROUP BY s.id, s.shop_name, s.description, s.logo_url, r.avg_rating, r.rating_count
    ORDER BY (r.avg_rating IS NULL), r.avg_rating DESC, s.shop_name
";
$result = $con->query($sql);
if ($result) {
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
}

// Featured by the admin: up to 3 shops and 4 dishes, best rated first.
// If anything fails (for example the featured columns do not exist yet), this section is simply left out.
$featured_shops  = [];
$featured_dishes = [];
try {
    $res = $con->query("
        SELECT s.id, s.shop_name, s.description, s.logo_url, r.avg_rating, r.rating_count
        FROM shops s
        LEFT JOIN (SELECT shop_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
                   FROM shop_ratings GROUP BY shop_id) r ON r.shop_id = s.id
        WHERE s.verification_status = 'approved' AND s.is_featured = 1
        ORDER BY r.avg_rating DESC, s.shop_name
        LIMIT 3
    ");
    if ($res) { $featured_shops = $res->fetch_all(MYSQLI_ASSOC); }

    $res = $con->query("
        SELECT mi.id, mi.shop_id, mi.name, mi.price, mi.image_url, s.shop_name, r.avg_rating, r.rating_count
        FROM menu_items mi
        JOIN shops s ON s.id = mi.shop_id
        LEFT JOIN (SELECT menu_item_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
                   FROM food_ratings GROUP BY menu_item_id) r ON r.menu_item_id = mi.id
        WHERE mi.is_featured = 1 AND mi.is_available = 1 AND s.verification_status = 'approved'
        ORDER BY r.avg_rating DESC, mi.name
        LIMIT 4
    ");
    if ($res) { $featured_dishes = $res->fetch_all(MYSQLI_ASSOC); }
} catch (Throwable $e) {
    error_log('index.php featured failed: ' . $e->getMessage());
    $featured_shops  = [];
    $featured_dishes = [];
}

// Shops already shown under "Featured" are not repeated in the list below
$featured_ids = array_map('intval', array_column($featured_shops, 'id'));
$vendors = array_values(array_filter($vendors, fn($v) => !in_array((int)$v['id'], $featured_ids, true)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Your neighborhood's food.</title>
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
      <h1>Your favorite Filipino <span>comfort foods</span>.</h1>
      <p class="hero-sub">Order from local vendors near you — from silog breakfasts to sizzling sisig and cold halo-halo.</p>

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

<!-- ================= FEATURED (chosen by the admin) ================= -->
<?php if ($featured_shops || $featured_dishes): ?>
<section class="popular-vendors featured-section" id="featured">
  <div class="section-header">
    <div class="section-titles">
      <h2>Featured on KaFoodie</h2>
      <p>Hand-picked shops and dishes our customers rate highly.</p>
    </div>
  </div>

  <?php if ($featured_shops): ?>
    <h3 class="featured-sub">Featured shops</h3>
    <div class="vendor-grid">
      <?php foreach ($featured_shops as $f): $f_logo = logo_src($f, ''); ?>
        <a href="customer/shop_menu.php?id=<?php echo (int)$f['id']; ?>" class="vendor-card">
          <div class="vendor-card-cover placeholder-photo<?php echo $f_logo !== '' ? ' has-logo' : ''; ?>">
            <span class="vendor-featured">★ Featured</span>
            <?php if ($f_logo !== ''): ?><img class="vendor-card-logo" src="<?php echo h($f_logo); ?>" alt=""><?php else: ?><span class="placeholder-emoji">🏪</span><?php endif; ?>
          </div>
          <div class="vendor-card-body">
            <div class="vendor-card-top">
              <span class="vendor-card-name"><?php echo h($f['shop_name']); ?></span>
            </div>
            <div class="vendor-card-rating"><?php echo rating_badge($f['avg_rating'] !== null ? (float)$f['avg_rating'] : null, (int)$f['rating_count']); ?></div>
            <?php if (!empty($f['description'])): ?>
              <div class="vendor-card-tag"><?php echo h($f['description']); ?></div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($featured_dishes): ?>
    <h3 class="featured-sub">Featured dishes</h3>
    <div class="vendor-grid vendor-grid-4">
      <?php foreach ($featured_dishes as $d): $d_img = dish_src($d, ''); ?>
        <a href="customer/shop_menu.php?id=<?php echo (int)$d['shop_id']; ?>#item-<?php echo (int)$d['id']; ?>" class="vendor-card">
          <div class="vendor-card-cover placeholder-photo">
            <span class="vendor-featured">★ Featured</span>
            <?php if ($d_img !== ''): ?><img src="<?php echo h($d_img); ?>" alt=""><?php else: ?><span class="placeholder-emoji">🍽️</span><?php endif; ?>
          </div>
          <div class="vendor-card-body">
            <div class="vendor-card-top">
              <span class="vendor-card-name"><?php echo h($d['name']); ?></span>
            </div>
            <div class="vendor-card-tag vendor-card-tag-short">from <?php echo h($d['shop_name']); ?></div>
            <div class="vendor-card-meta">
              <?php echo rating_badge($d['avg_rating'] !== null ? (float)$d['avg_rating'] : null, (int)$d['rating_count']); ?>
              <span class="vendor-card-price">₱<?php echo number_format((float)$d['price'], 2); ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ================= POPULAR VENDORS ================= -->
<?php if ($vendors || !$featured_shops): ?>
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
      <p class="dash-empty">No vendors are open on KaFoodie yet — check back soon.</p>
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
            <div class="vendor-card-rating"><?php echo rating_badge($v['avg_rating'] !== null ? (float)$v['avg_rating'] : null, (int)$v['rating_count']); ?></div>
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
<?php endif; ?>

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
      <p>Browse local Filipino vendors and get your favorites.</p>
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