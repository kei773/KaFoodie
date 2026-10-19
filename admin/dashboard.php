<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_stats.php';
require_once __DIR__ . '/../includes/rating_helpers.php';

require_admin();

function ah($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function rating_or_null($avg): ?float {
    return $avg !== null ? (float)$avg : null;
}

$counts   = admin_status_counts($con);
$pending  = $counts['pending'];
$summary  = admin_summary($con);
$orders14 = admin_orders_last_days($con, 14);
$dist     = admin_rating_distribution($con);

$top_shops   = admin_top_shops($con);
$top_dishes  = admin_top_dishes($con);
$feat_shops  = admin_featured_shops($con);
$feat_dishes = admin_featured_dishes($con);

$csrf = admin_csrf_token();
[$flash_ok, $flash_err] = admin_take_flash();

// Everything the charts need, handed to the page as JSON
$chart = [
    'orders'       => $orders14,
    'verification' => ['labels' => ['Not submitted', 'Pending review', 'Verified', 'Not approved'],
                       'values' => [$counts['not_submitted'], $counts['pending'], $counts['approved'], $counts['rejected']]],
    'ratings'      => $dist,
];

// One "Feature" / "Remove" button
function feature_form(string $kind, int $id, string $action, string $csrf): void { ?>
    <form action="feature_toggle.php" method="POST" class="rank-action">
      <input type="hidden" name="csrf" value="<?= ah($csrf) ?>">
      <input type="hidden" name="kind" value="<?= ah($kind) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="<?= ah($action) ?>">
      <?php if ($action === 'feature'): ?>
        <button type="submit" class="feat-btn">Feature</button>
      <?php else: ?>
        <button type="submit" class="feat-btn feat-btn-remove">Remove</button>
      <?php endif; ?>
    </form>
<?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>KaFoodie &mdash; Admin dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="dash-content dash-content-wide">
  <div class="verif-head">
    <h1>Admin dashboard</h1>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert-success" role="status"><?= ah($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert-error" role="alert"><?= ah($flash_err) ?></div>
  <?php endif; ?>

  <!-- Summary numbers -->
  <section class="stat-grid" aria-label="Summary">
    <div class="stat-card">
      <span class="stat-label">Verified shops</span>
      <span class="stat-value"><?= (int)$counts['approved'] ?></span>
      <span class="stat-hint">of <?= (int)array_sum($counts) ?> registered</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Customers</span>
      <span class="stat-value"><?= (int)$summary['customers'] ?></span>
      <span class="stat-hint">with an account</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Orders</span>
      <span class="stat-value"><?= (int)$summary['orders_total'] ?></span>
      <span class="stat-hint"><?= (int)$summary['orders_completed'] ?> completed</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Completed sales</span>
      <span class="stat-value">₱<?= number_format($summary['sales'], 2) ?></span>
      <span class="stat-hint">from completed orders</span>
    </div>
  </section>

  <!-- Document verification (unchanged) -->
  <div class="sh-grid admin-block">
    <a class="sh-card" href="verification.php">
      <span class="sh-card-icon" aria-hidden="true">✅</span>
      <span class="sh-card-title">Shop verification</span>
      <span class="sh-card-sub">Check uploaded documents, then approve or reject each shop.</span>
      <?php if ($pending > 0): ?>
        <span class="sh-badge sh-badge-pending"><?= $pending; ?> waiting for review</span>
      <?php else: ?>
        <span class="sh-badge sh-badge-not_submitted">Nothing waiting</span>
      <?php endif; ?>
    </a>
  </div>

  <!-- Charts -->
  <section class="chart-grid admin-block" aria-label="Charts">
    <div class="dash-card chart-card chart-wide">
      <h2 class="admin-section-title">Orders, last 14 days</h2>
      <div class="chart-box"><canvas id="chOrders" role="img" aria-label="Line chart of orders per day for the last 14 days"></canvas></div>
    </div>
    <div class="dash-card chart-card">
      <h2 class="admin-section-title">Shops by verification</h2>
      <div class="chart-box"><canvas id="chVerification" role="img" aria-label="Doughnut chart of shops by verification status"></canvas></div>
    </div>
    <div class="dash-card chart-card">
      <div class="chart-head">
        <h2 class="admin-section-title">Ratings given</h2>
        <div class="chart-toggles" id="ratingToggles" role="group" aria-label="Choose which ratings to show">
          <span class="chart-toggles-hint">Click to show or hide:</span>
          <button type="button" class="toggle-chip is-on" id="tgShops" aria-pressed="true">
            <span class="toggle-dot toggle-dot-shops" aria-hidden="true"></span>Shops
          </button>
          <button type="button" class="toggle-chip is-on" id="tgDishes" aria-pressed="true">
            <span class="toggle-dot toggle-dot-dishes" aria-hidden="true"></span>Dishes
          </button>
        </div>
      </div>
      <div class="chart-box"><canvas id="chRatings" role="img" aria-label="Bar chart of how many 1 to 5 star ratings shops and dishes received"></canvas></div>
    </div>
    <p class="chart-fallback" id="chartFallback" hidden>The charts could not load. They need an internet connection the first time the page opens.</p>
  </section>

  <!-- Top rated + featured -->
  <section class="rank-grid admin-block" id="top-rated" aria-label="Top rated">
    <div class="dash-card">
      <h2 class="admin-section-title">Top rated shops</h2>
      <p class="rank-note">Needs at least <?= MIN_RATINGS_FOR_TOP ?> ratings and an average of <?= number_format(HIGH_RATING_AVG, 1) ?> or more.</p>
      <?php if (!$top_shops): ?>
        <p class="rank-empty">No shop qualifies yet.</p>
      <?php else: ?>
        <ol class="rank-list">
          <?php foreach ($top_shops as $i => $r): ?>
            <li class="rank-row">
              <span class="rank-pos"><?= $i + 1 ?></span>
              <span class="rank-main"><span class="rank-name"><?= ah($r['shop_name']) ?></span></span>
              <span class="rank-rating"><?= rating_badge(rating_or_null($r['avg_rating']), (int)$r['rating_count']) ?></span>
              <?php if ((int)$r['is_featured'] === 1): ?>
                <span class="feat-pill">★ Featured</span>
              <?php else: ?>
                <?php feature_form('shop', (int)$r['id'], 'feature', $csrf); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>

    <div class="dash-card">
      <h2 class="admin-section-title">Top rated dishes</h2>
      <p class="rank-note">Same rule: at least <?= MIN_RATINGS_FOR_TOP ?> ratings and <?= number_format(HIGH_RATING_AVG, 1) ?> or more.</p>
      <?php if (!$top_dishes): ?>
        <p class="rank-empty">No dish qualifies yet.</p>
      <?php else: ?>
        <ol class="rank-list">
          <?php foreach ($top_dishes as $i => $r): ?>
            <li class="rank-row">
              <span class="rank-pos"><?= $i + 1 ?></span>
              <span class="rank-main">
                <span class="rank-name"><?= ah($r['name']) ?></span>
                <span class="rank-sub"><?= ah($r['shop_name']) ?></span>
              </span>
              <span class="rank-rating"><?= rating_badge(rating_or_null($r['avg_rating']), (int)$r['rating_count']) ?></span>
              <?php if ((int)$r['is_featured'] === 1): ?>
                <span class="feat-pill">★ Featured</span>
              <?php else: ?>
                <?php feature_form('dish', (int)$r['id'], 'feature', $csrf); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </section>

  <section class="dash-card admin-block" id="featured">
    <h2 class="admin-section-title">Currently featured</h2>
    <p class="rank-note">Featured items are shown on the public landing page (up to 3 shops and 4 dishes, best rated first). Featured shops also appear first on the customer dashboard, and featured dishes get a badge on the shop menu.</p>
    <?php if (!$feat_shops && !$feat_dishes): ?>
      <p class="rank-empty">Nothing is featured yet. Use the Feature buttons above.</p>
    <?php else: ?>
      <ul class="rank-list">
        <?php foreach ($feat_shops as $r): ?>
          <li class="rank-row">
            <span class="rank-kind">Shop</span>
            <span class="rank-main"><span class="rank-name"><?= ah($r['shop_name']) ?></span></span>
            <span class="rank-rating"><?= rating_badge(rating_or_null($r['avg_rating']), (int)($r['rating_count'] ?? 0)) ?></span>
            <?php feature_form('shop', (int)$r['id'], 'unfeature', $csrf); ?>
          </li>
        <?php endforeach; ?>
        <?php foreach ($feat_dishes as $r): ?>
          <li class="rank-row">
            <span class="rank-kind">Dish</span>
            <span class="rank-main">
              <span class="rank-name"><?= ah($r['name']) ?></span>
              <span class="rank-sub"><?= ah($r['shop_name']) ?></span>
            </span>
            <span class="rank-rating"><?= rating_badge(rating_or_null($r['avg_rating']), (int)($r['rating_count'] ?? 0)) ?></span>
            <?php feature_form('dish', (int)$r['id'], 'unfeature', $csrf); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
  var data = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  if (typeof Chart === 'undefined') {
    document.getElementById('chartFallback').hidden = false;
    document.querySelectorAll('.chart-card').forEach(function (c) { c.hidden = true; });
    return;
  }

  var css = getComputedStyle(document.documentElement);
  function v(name, fallback) { return css.getPropertyValue(name).trim() || fallback; }
  var orange = v('--orange', '#FF6B35'), orangeDark = v('--orange-dark', '#D94A1B');
  var charcoal = v('--charcoal', '#1F2933'), muted = v('--muted', '#6B7280'), line = v('--line-soft', '#F0E3D8');

  Chart.defaults.font.family = 'Poppins, Arial, sans-serif';
  Chart.defaults.color = muted;

  function sum(a) { return a.reduce(function (t, n) { return t + n; }, 0); }

  // A chart with nothing to show gets a short message instead of an empty box
  function emptyNote(id, text) {
    var box = document.getElementById(id).parentNode;
    box.innerHTML = '<p class="chart-empty">' + text + '</p>';
  }

  var axis = { grid: { color: line }, ticks: { precision: 0 }, beginAtZero: true };

  // Orders per day
  new Chart(document.getElementById('chOrders'), {
    type: 'line',
    data: { labels: data.orders.labels, datasets: [{
      label: 'Orders', data: data.orders.values,
      borderColor: orangeDark, backgroundColor: 'rgba(255,107,53,.15)',
      fill: true, tension: .3, pointRadius: 3, pointBackgroundColor: orangeDark
    }]},
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { x: { grid: { display: false } }, y: axis } }
  });

  function doughnut(id, labels, values, colors, emptyText) {
    if (sum(values) === 0) { emptyNote(id, emptyText); return; }
    new Chart(document.getElementById(id), {
      type: 'doughnut',
      data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }
    });
  }
  doughnut('chVerification', data.verification.labels, data.verification.values,
           ['#B8BEC6', '#F5A524', '#38A169', '#D64545'], 'No shops yet.');

  // Ratings given
  if (sum(data.ratings.shops) + sum(data.ratings.dishes) === 0) {
    emptyNote('chRatings', 'No ratings yet.');
    document.getElementById('ratingToggles').hidden = true;
  } else {
    var ratingsChart = new Chart(document.getElementById('chRatings'), {
      type: 'bar',
      data: { labels: ['1 star', '2 stars', '3 stars', '4 stars', '5 stars'], datasets: [
        { label: 'Shops',  data: data.ratings.shops,  backgroundColor: orange, borderRadius: 6 },
        { label: 'Dishes', data: data.ratings.dishes, backgroundColor: charcoal, borderRadius: 6 }
      ]},
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { grid: { display: false } }, y: axis } }
    });

    // Shops / Dishes buttons show or hide that group (at least one always stays on)
    function wireToggle(buttonId, index) {
      var btn = document.getElementById(buttonId);
      btn.addEventListener('click', function () {
        var isOn = ratingsChart.isDatasetVisible(index);
        var visible = ratingsChart.data.datasets.filter(function (d, i) { return ratingsChart.isDatasetVisible(i); }).length;
        if (isOn && visible === 1) return;
        ratingsChart.setDatasetVisibility(index, !isOn);
        ratingsChart.update();
        btn.classList.toggle('is-on', !isOn);
        btn.setAttribute('aria-pressed', String(!isOn));
      });
    }
    wireToggle('tgShops', 0);
    wireToggle('tgDishes', 1);
  }
})();
</script>
</body>
</html>