<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/order_helpers.php';
require_once __DIR__ . '/../includes/rating_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$csrf        = csrf_token();

$notice = $_SESSION['order_notice'] ?? null;
$error  = $_SESSION['order_error'] ?? null;
unset($_SESSION['order_notice'], $_SESSION['order_error']);

// ---------- Orders ----------
$stmt = $con->prepare(
    "SELECT o.id, o.pickup_code, o.shop_id, o.status, o.payment_method, o.payment_status, o.transaction_id,
            o.total_amount, o.created_at, s.shop_name, s.logo_url
     FROM orders o
     JOIN shops s ON s.id = o.shop_id
     WHERE o.customer_id = ?
     ORDER BY o.id DESC
     LIMIT 200"
);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items_by_order = order_items_by_order($con, array_column($orders, 'id'));

$current = array_values(array_filter($orders, fn($o) => $o['status'] !== 'completed'));
$past    = array_values(array_filter($orders, fn($o) => $o['status'] === 'completed'));

// ---------- This customer's existing ratings ----------
// If the ratings tables are missing (step 3 SQL not run) the Ratings tab is simply left out.
$ratings_ok   = true;
$shop_ratings = [];   // shop_id => ['stars' => n, 'comment' => text]
$dish_ratings = [];   // menu_item_id => stars
try {
    $stmt = $con->prepare("SELECT shop_id, stars, comment FROM shop_ratings WHERE customer_id = ?");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $shop_ratings[(int)$r['shop_id']] = ['stars' => (int)$r['stars'], 'comment' => (string)$r['comment']];
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT menu_item_id, stars FROM food_ratings WHERE customer_id = ?");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $dish_ratings[(int)$r['menu_item_id']] = (int)$r['stars'];
    }
    $stmt->close();
} catch (Throwable $e) {
    $ratings_ok = false;
    error_log('orders.php ratings failed: ' . $e->getMessage());
}

// ---------- Ratings tab: one card per shop the customer has a completed order from ----------
$rate_shops = [];
foreach ($past as $o) {
    $sid = (int)$o['shop_id'];
    if (!isset($rate_shops[$sid])) {
        $rate_shops[$sid] = ['id' => $sid, 'name' => $o['shop_name'], 'logo_url' => $o['logo_url'], 'orders' => 0, 'dishes' => []];
    }
    $rate_shops[$sid]['orders']++;
    foreach ($items_by_order[(int)$o['id']] ?? [] as $it) {
        if (!empty($it['menu_item_id'])) {
            $rate_shops[$sid]['dishes'][(int)$it['menu_item_id']] = $it['item_name'];
        }
    }
}
$to_rate = 0;
foreach ($rate_shops as $sid => $rs) {
    $unrated = 0;
    foreach ($rs['dishes'] as $mid => $name) {
        if (!isset($dish_ratings[$mid])) { $unrated++; }
    }
    $rate_shops[$sid]['shop_rating'] = $shop_ratings[$sid] ?? null;
    $rate_shops[$sid]['needs']       = !isset($shop_ratings[$sid]) || $unrated > 0;
    if ($rate_shops[$sid]['needs']) { $to_rate++; }
}
// Shops that still need a rating first
uasort($rate_shops, fn($a, $b) => (int)$b['needs'] <=> (int)$a['needs']);

// ---------- Which tab ----------
$tabs = ['current' => 'Current', 'past' => 'Past', 'ratings' => 'Ratings'];
if (!$ratings_ok) { unset($tabs['ratings']); }

$focus_order = (int)($_GET['order'] ?? 0);   // from a notification link
$tab = $_GET['tab'] ?? '';
if ($focus_order > 0 && !isset($_GET['tab'])) {
    foreach ($orders as $o) {
        if ((int)$o['id'] === $focus_order) {
            $tab = ($o['status'] === 'completed') ? 'past' : 'current';
        }
    }
}
if (!isset($tabs[$tab])) { $tab = 'current'; }

$counts = ['current' => count($current), 'past' => count($past), 'ratings' => $to_rate];

// ---------- Small helpers for this page ----------
function items_summary(array $items): string {
    $parts = [];
    foreach ($items as $it) {
        $name = $it['item_name'] !== '' ? $it['item_name'] : 'Removed dish';
        $parts[] = $name . ((int)$it['quantity'] > 1 ? ' ×' . (int)$it['quantity'] : '');
    }
    $shown = array_slice($parts, 0, 2);
    $more  = count($parts) - count($shown);
    return implode(', ', $shown) . ($more > 0 ? ', +' . $more . ' more' : '');
}

function payment_line(array $o, bool $with_ref): string {
    $text = (ORDER_PAYMENT_LABELS[$o['payment_method']] ?? $o['payment_method'])
          . ' · ' . ($o['payment_status'] === 'paid' ? 'Paid' : 'Not paid yet');
    if ($with_ref && !empty($o['transaction_id'])) {
        $text .= ' · Ref ' . $o['transaction_id'];
    }
    return $text;
}

function render_item_list(array $items): void { ?>
    <ul class="order-items">
      <?php foreach ($items as $it): $img = dish_src($it, '../'); ?>
        <li class="order-item">
          <?php if ($img !== ''): ?>
            <img class="cart-thumb" src="<?= h($img) ?>" alt="">
          <?php else: ?>
            <span class="cart-thumb" aria-hidden="true">🍽️</span>
          <?php endif; ?>
          <span class="order-item-name"><?= h($it['item_name'] !== '' ? $it['item_name'] : 'Removed dish') ?></span>
          <span class="order-item-qty"><?= (int)$it['quantity'] ?> × ₱<?= number_format((float)$it['unit_price'], 2) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
<?php }

function render_current_order(array $o, array $items): void {
    $idx = array_search($o['status'], ORDER_FLOW, true); ?>
    <section class="dash-card order-card" id="order-<?= (int)$o['id'] ?>">
      <div class="order-head">
        <div>
          <div class="order-title"><?= h($o['shop_name']) ?></div>
          <div class="order-sub"><?= h(date('M j, Y g:i A', strtotime($o['created_at']))) ?></div>
        </div>
        <span class="order-status order-status-<?= h($o['status']) ?>"><?= h(order_status_label($o['status'])) ?></span>
      </div>

      <div class="pickup-code<?= $o['status'] === 'ready' ? ' is-ready' : '' ?>">
        <span class="pickup-code-label"><?= $o['status'] === 'ready' ? 'Ready! Show this code at the shop' : 'Your pickup code' ?></span>
        <span class="pickup-code-value"><?= h($o['pickup_code']) ?></span>
      </div>

      <ol class="order-steps" aria-label="Order progress">
        <?php foreach (ORDER_FLOW as $i => $step): ?>
          <li class="order-step<?= $i <= $idx ? ' is-done' : '' ?><?= $i === $idx ? ' is-current' : '' ?>"
              <?= $i === $idx ? 'aria-current="step"' : '' ?>>
            <?= h(ORDER_STEP_LABELS[$step]) ?>
          </li>
        <?php endforeach; ?>
      </ol>

      <?php render_item_list($items); ?>

      <div class="order-foot">
        <span class="order-sub"><?= h(payment_line($o, false)) ?></span>
        <span class="order-total">₱<?= number_format((float)$o['total_amount'], 2) ?></span>
      </div>
    </section>
<?php }

function render_past_order(array $o, array $items, bool $ratings_ok, array $shop_ratings, array $dish_ratings,
                           string $csrf, int $focus_order): void {
    $logo = logo_src($o, '../');
    $sid  = (int)$o['shop_id'];

    // Is everything from this order rated already?
    $all_rated = isset($shop_ratings[$sid]);
    foreach ($items as $it) {
        if (!empty($it['menu_item_id']) && !isset($dish_ratings[(int)$it['menu_item_id']])) { $all_rated = false; }
    }
    $n_items = array_sum(array_map(fn($it) => (int)$it['quantity'], $items));
    ?>
    <section class="dash-card past-card" id="order-<?= (int)$o['id'] ?>">
      <details class="past-details"<?= $focus_order === (int)$o['id'] ? ' open' : '' ?>>
        <summary class="past-summary">
          <span class="past-logo">
            <?php if ($logo !== ''): ?><img src="<?= h($logo) ?>" alt=""><?php else: ?><span aria-hidden="true">🏪</span><?php endif; ?>
          </span>
          <span class="past-main">
            <span class="past-title"><?= h($o['shop_name']) ?></span>
            <span class="past-sub"><?= h(date('M j, Y g:i A', strtotime($o['created_at']))) ?> · <?= $n_items ?> item<?= $n_items === 1 ? '' : 's' ?></span>
            <span class="past-items"><?= h(items_summary($items)) ?></span>
          </span>
          <span class="past-side">
            <span class="past-total">₱<?= number_format((float)$o['total_amount'], 2) ?></span>
            <span class="order-status order-status-completed">Completed</span>
          </span>
        </summary>

        <div class="past-body">
          <?php render_item_list($items); ?>
          <div class="order-foot">
            <span class="order-sub">
              Pickup code <span class="pickup-chip"><?= h($o['pickup_code']) ?></span>
              · <?= h(payment_line($o, true)) ?>
            </span>
          </div>
        </div>
      </details>

      <div class="past-actions">
        <form action="reorder.php" method="POST">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button type="submit" class="past-btn">Reorder</button>
        </form>
        <?php if ($ratings_ok): ?>
          <?php if ($all_rated): ?>
            <a class="past-link" href="orders.php?tab=ratings#shop-<?= $sid ?>">★ You rated <?= (int)$shop_ratings[$sid]['stars'] ?> · Edit</a>
          <?php else: ?>
            <a class="past-link past-link-todo" href="orders.php?tab=ratings#shop-<?= $sid ?>">Rate this order</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
<?php }

function render_rate_shop(array $rs, array $shop_ratings, array $dish_ratings, string $csrf): void {
    $sid  = (int)$rs['id'];
    $logo = logo_src($rs, '../');
    $mine = $rs['shop_rating']; ?>
    <section class="dash-card rate-card" id="shop-<?= $sid ?>">
      <div class="rate-card-head">
        <span class="past-logo">
          <?php if ($logo !== ''): ?><img src="<?= h($logo) ?>" alt=""><?php else: ?><span aria-hidden="true">🏪</span><?php endif; ?>
        </span>
        <div class="rate-card-title">
          <div class="order-title"><?= h($rs['name']) ?></div>
          <div class="order-sub"><?= (int)$rs['orders'] ?> completed order<?= (int)$rs['orders'] === 1 ? '' : 's' ?></div>
        </div>
        <?php if ($rs['needs']): ?>
          <span class="rate-chip rate-chip-todo"><?= $mine ? 'Dishes to rate' : 'Not rated yet' ?></span>
        <?php else: ?>
          <span class="rate-chip rate-chip-done">★ You rated <?= (int)$mine['stars'] ?></span>
        <?php endif; ?>
      </div>

      <details class="rate-box"<?= $rs['needs'] ? ' open' : '' ?>>
        <summary class="rate-summary"><?= $rs['needs'] ? 'Rate now' : 'Edit your ratings' ?></summary>

        <form action="rate_process.php" method="POST" class="rate-form">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="shop_id" value="<?= $sid ?>">

          <div class="rate-block">
            <div class="rate-label">How was <?= h($rs['name']) ?>?</div>
            <?= rating_star_input('shop_stars', 's' . $sid . '-shop', (int)($mine['stars'] ?? 0)) ?>
            <div class="field">
              <label for="s<?= $sid ?>-comment" class="vh">Comment about the shop</label>
              <textarea id="s<?= $sid ?>-comment" name="shop_comment" rows="2" maxlength="500"
                        placeholder="Tell others about your experience (optional)"><?= h($mine['comment'] ?? '') ?></textarea>
            </div>
          </div>

          <?php if ($rs['dishes']): ?>
            <div class="rate-block">
              <div class="rate-label">Rate the dishes you had</div>
              <?php foreach ($rs['dishes'] as $mid => $dish_name): ?>
                <div class="rate-dish">
                  <span class="rate-dish-name"><?= h($dish_name) ?></span>
                  <?= rating_star_input('dish_stars[' . (int)$mid . ']', 's' . $sid . '-d' . (int)$mid, (int)($dish_ratings[$mid] ?? 0)) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn-primary btn-inline">Save ratings</button>
        </form>
      </details>
    </section>
<?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — My orders</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <div class="cart-topbar">
    <a href="dashboard.php" class="cart-back">← Back to shops</a>
  </div>

  <div class="browse-greeting">
    <h1>My orders</h1>
    <p>Follow your current orders, look back at past ones, and rate what you ordered.</p>
  </div>

  <?php if ($notice): ?>
    <div class="alert-success" role="status"><?= h($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error" role="alert"><?= h($error) ?></div>
  <?php endif; ?>

  <nav class="admin-tabs" aria-label="My orders sections">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="admin-tab <?= $key === $tab ? 'is-active' : '' ?>"
         href="orders.php?tab=<?= h($key) ?>"
         <?= $key === $tab ? 'aria-current="page"' : '' ?>>
        <?= h($label) ?>
        <?php if ((int)$counts[$key] > 0): ?><span class="admin-tab-count"><?= (int)$counts[$key] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'current'): ?>

    <?php if (!$current): ?>
      <div class="dash-card">
        <p class="dash-empty">You have no current orders. <a href="dashboard.php">Browse shops</a> to order something.</p>
      </div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($current as $o) render_current_order($o, $items_by_order[(int)$o['id']] ?? []); ?>
      </div>
    <?php endif; ?>

  <?php elseif ($tab === 'past'): ?>

    <?php if (!$past): ?>
      <div class="dash-card">
        <p class="dash-empty">Your completed orders will show up here.</p>
      </div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($past as $o) render_past_order($o, $items_by_order[(int)$o['id']] ?? [], $ratings_ok, $shop_ratings, $dish_ratings, $csrf, $focus_order); ?>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <?php if (!$rate_shops): ?>
      <div class="dash-card">
        <p class="dash-empty">Once an order is completed, you can rate the shop and the food here.</p>
      </div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($rate_shops as $rs) render_rate_shop($rs, $shop_ratings, $dish_ratings, $csrf); ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<script>
(function () {
  // Jump to the order or shop a link points at, opening its details if they are folded
  var target = null;
  <?php if ($focus_order > 0): ?>target = document.getElementById('order-<?= $focus_order ?>');<?php endif; ?>
  if (!target && location.hash.length > 1) { target = document.getElementById(location.hash.slice(1)); }
  if (!target) return;
  var d = target.querySelector('details');
  if (d) d.open = true;
  target.scrollIntoView({ block: 'center' });
  target.classList.add('is-focused');
})();
</script>

</body>
</html>
