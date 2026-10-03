<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

if (!function_exists('kf_h')) {
    function kf_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// ---------- Shops (one row per shop, with menu summary) ----------
$sql = "
    SELECT
        s.id, s.shop_name, s.description, s.logo_url,
        COUNT(mi.id)  AS item_count,
        MIN(mi.price) AS min_price,
        GROUP_CONCAT(DISTINCT NULLIF(TRIM(mi.category), '') ORDER BY NULLIF(TRIM(mi.category), '') SEPARATOR '||') AS cats
    FROM shops s
    LEFT JOIN menu_items mi ON mi.shop_id = s.id AND mi.is_available = 1
    GROUP BY s.id, s.shop_name, s.description, s.logo_url
    ORDER BY (COUNT(mi.id) = 0), s.shop_name
";
$result = $con->query($sql);

$shops          = [];
$all_categories = [];
$load_failed    = ($result === false);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['cat_list'] = ($row['cats'] !== null && $row['cats'] !== '') ? explode('||', $row['cats']) : [];
        foreach ($row['cat_list'] as $c) {
            $all_categories[$c] = true;   // keys de-duplicate for us
        }
        $shops[] = $row;
    }
    $result->free();
} else {
    error_log('dashboard.php shop query failed: ' . $con->error);
}
$all_categories = array_keys($all_categories);
sort($all_categories);

$cart_count = array_sum(array_map('intval', $_SESSION['cart']['items'] ?? []));

$initial_q = trim($_GET['q'] ?? '');

$emojis = ['🍳', '🍢', '🍜', '🍗', '🍔', '☕', '🥘', '🍧'];
$first  = explode(' ', trim($_SESSION['name'] ?? ''))[0] ?: 'foodie';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Browse shops</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="sd-wrap">

  <div class="sd-top">
    <div>
      <h1>Good day, <?php echo kf_h($first); ?></h1>
      <p>Pick a shop to see its menu and start your order.</p>
    </div>
    <a href="cart.php" class="sd-cartbtn" aria-label="Open your cart (<?php echo $cart_count; ?> items)">
      <span class="cart-icon" aria-hidden="true"></span>
      <span>Cart</span>
      <span class="sd-badge<?php echo $cart_count === 0 ? ' is-zero' : ''; ?>"><?php echo $cart_count; ?></span>
    </a>
  </div>

  <?php if ($load_failed): ?>

    <div class="alert-error">We couldn't load the shops right now. Please refresh the page or try again in a moment.</div>

  <?php elseif (empty($shops)): ?>

    <div class="dash-card">
      <p class="dash-empty">No shops have joined KaFoodie yet — check back soon.</p>
    </div>

  <?php else: ?>

    <!-- Search + category filter -->
    <div class="sd-toolbar">
      <div class="sd-search">
        <span aria-hidden="true">🔎</span>
        <input type="search" id="shopSearch" placeholder="Search shops or cuisines…"
               aria-label="Search shops or cuisines"
               value="<?php echo kf_h($initial_q); ?>" autocomplete="off">
      </div>

      <?php if (!empty($all_categories)): ?>
        <div class="sd-chips" id="chipRow">
          <button type="button" class="sd-chip active" data-filter="all">All</button>
          <?php foreach ($all_categories as $cat): ?>
            <button type="button" class="sd-chip" data-filter="<?php echo kf_h($cat); ?>"><?php echo kf_h($cat); ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Shop grid -->
    <div class="sd-section-head">
      <h2>Shops</h2>
      <span class="sd-count" id="shopCount" aria-live="polite"></span>
    </div>

    <div class="sd-grid" id="shopGrid">
      <?php foreach ($shops as $shop): ?>
        <?php
          $id        = (int)$shop['id'];
          $items     = (int)$shop['item_count'];
          $open      = $items > 0;
          $search    = mb_strtolower($shop['shop_name'] . ' ' . ($shop['description'] ?? '') . ' ' . implode(' ', $shop['cat_list']));
          $shown_cat = array_slice($shop['cat_list'], 0, 3);
          $extra_cat = count($shop['cat_list']) - count($shown_cat);
          $card_attr = 'class="sd-card' . ($open ? '' : ' sd-card-closed') . '"'
                     . ' data-search="' . kf_h($search) . '"'
                     . ' data-cats="' . kf_h(json_encode($shop['cat_list'], JSON_UNESCAPED_UNICODE)) . '"';
        ?>
        <?php if ($open): ?>
          <a href="shop_menu.php?id=<?php echo $id; ?>" <?php echo $card_attr; ?>>
        <?php else: ?>
          <div <?php echo $card_attr; ?> aria-disabled="true">
        <?php endif; ?>

          <div class="sd-cover g<?php echo $id % 4; ?>">
            <?php if (!empty($shop['logo_url'])): ?>
              <img class="sd-cover-logo" src="<?php echo kf_h(media_url($shop['logo_url'], '../')); ?>" alt="">
            <?php else: ?>
              <?php echo $emojis[$id % count($emojis)]; ?>
            <?php endif; ?>
            <span class="sd-pill"><?php echo $open ? $items . ' dish' . ($items === 1 ? '' : 'es') : 'No dishes yet'; ?></span>
          </div>

          <div class="sd-body">
            <h3 class="sd-name"><?php echo kf_h($shop['shop_name']); ?></h3>
            <p class="sd-desc"><?php echo kf_h($shop['description'] ?: 'Fresh, home-style food from a local kitchen.'); ?></p>

            <?php if (!empty($shown_cat)): ?>
              <div class="sd-tags">
                <?php foreach ($shown_cat as $c): ?>
                  <span class="sd-tag"><?php echo kf_h($c); ?></span>
                <?php endforeach; ?>
                <?php if ($extra_cat > 0): ?><span class="sd-tag">+<?php echo $extra_cat; ?></span><?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="sd-foot">
              <span class="sd-from">
                <?php if ($open): ?>From <strong>₱<?php echo number_format((float)$shop['min_price'], 2); ?></strong><?php else: ?>Coming soon<?php endif; ?>
              </span>
              <span class="sd-go"><?php echo $open ? 'View menu →' : 'Unavailable'; ?></span>
            </div>
          </div>

        <?php echo $open ? '</a>' : '</div>'; ?>
      <?php endforeach; ?>
    </div>

    <div class="sd-empty" id="noResults">
      <div class="big">🍽️</div>
      <strong>No shops match your search</strong>
      <p>Try a different word or pick another category.</p>
    </div>

  <?php endif; ?>

</div>

<script>
(function () {
  var search  = document.getElementById('shopSearch');
  var grid    = document.getElementById('shopGrid');
  if (!search || !grid) return;

  var cards   = Array.prototype.slice.call(grid.querySelectorAll('.sd-card'));
  var chips   = document.querySelectorAll('.sd-chip');
  var counter = document.getElementById('shopCount');
  var empty   = document.getElementById('noResults');
  var filter  = 'all';

  function apply() {
    var q = search.value.trim().toLowerCase();
    var visible = 0;

    cards.forEach(function (card) {
      var cats = [];
      try { cats = JSON.parse(card.dataset.cats || '[]'); } catch (e) {}

      var matchCat  = (filter === 'all') || cats.indexOf(filter) !== -1;
      var matchText = (q === '') || (card.dataset.search || '').indexOf(q) !== -1;
      var show = matchCat && matchText;

      card.classList.toggle('is-hidden', !show);
      if (show) visible++;
    });

    counter.textContent = visible + (visible === 1 ? ' shop' : ' shops');
    empty.classList.toggle('show', visible === 0);
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      chips.forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      filter = chip.dataset.filter;
      apply();
    });
  });

  search.addEventListener('input', apply);
  apply(); // honors ?q= coming from the landing page search
})();
</script>

</body>
</html>