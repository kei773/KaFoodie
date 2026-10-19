<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/order_helpers.php';

$shop    = require_shop($con);
$shop_id = (int)$shop['id'];
$csrf    = csrf_token();

$tabs = [
    'active'    => 'In progress',
    'completed' => 'Completed',
    'all'       => 'All orders',
];
$filter = $_GET['view'] ?? 'active';
if (!isset($tabs[$filter])) { $filter = 'active'; }

// Tab counts
$by_status = ['placed' => 0, 'preparing' => 0, 'ready' => 0, 'completed' => 0];
$stmt = $con->prepare("SELECT status, COUNT(*) AS c FROM orders WHERE shop_id = ? GROUP BY status");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
foreach ($stmt->get_result() as $row) {
    $by_status[$row['status']] = (int)$row['c'];
}
$stmt->close();
$counts = [
    'active'    => $by_status['placed'] + $by_status['preparing'] + $by_status['ready'],
    'completed' => $by_status['completed'],
    'all'       => array_sum($by_status),
];

// Orders for the chosen tab. Oldest first for the kitchen queue, newest first elsewhere.
$where = '';
if ($filter === 'active')    { $where = "AND o.status <> 'completed'"; }
if ($filter === 'completed') { $where = "AND o.status = 'completed'"; }
$dir = ($filter === 'active') ? 'ASC' : 'DESC';

$stmt = $con->prepare(
    "SELECT o.id, o.pickup_code, o.status, o.payment_method, o.payment_status, o.transaction_id, o.total_amount, o.created_at,
            u.name AS customer_name
     FROM orders o
     JOIN users u ON u.id = o.customer_id
     WHERE o.shop_id = ? $where
     ORDER BY o.id $dir
     LIMIT 100"
);
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items_by_order = order_items_by_order($con, array_column($orders, 'id'));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Orders</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="dash-content">
  <a class="auth-back-link" href="dashboard.php">&larr; Back to dashboard</a>

  <div class="verif-head">
    <h1>Orders</h1>
  </div>
  <p class="verif-lead">Customers pick up their orders at your shop and show you the pickup code. Move each order forward as you go and the customer sees the update.</p>

  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>"
         role="<?= $flash['type'] === 'success' ? 'status' : 'alert' ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <nav class="admin-tabs" aria-label="Filter orders">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="admin-tab <?= $key === $filter ? 'is-active' : '' ?>"
         href="orders.php?view=<?= h($key) ?>"
         <?= $key === $filter ? 'aria-current="page"' : '' ?>>
        <?= h($label) ?>
        <span class="admin-tab-count"><?= (int)$counts[$key] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$orders): ?>
    <section class="dash-card admin-empty">
      <p>No orders in this list.</p>
    </section>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o): $items = $items_by_order[(int)$o['id']] ?? []; ?>
        <section class="dash-card order-card">
          <div class="order-head">
            <div>
              <div class="order-title">Order <span class="pickup-chip"><?= h($o['pickup_code']) ?></span> · <?= h($o['customer_name']) ?></div>
              <div class="order-sub"><?= h(date('M j, Y g:i A', strtotime($o['created_at']))) ?></div>
            </div>
            <span class="order-status order-status-<?= h($o['status']) ?>"><?= h(order_status_label($o['status'])) ?></span>
          </div>

          <ul class="order-items">
            <?php foreach ($items as $it): ?>
              <li class="order-item order-item-plain">
                <span class="order-item-name"><?= h($it['item_name'] !== '' ? $it['item_name'] : 'Removed dish') ?></span>
                <span class="order-item-qty"><?= (int)$it['quantity'] ?> × ₱<?= number_format((float)$it['unit_price'], 2) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="order-foot">
            <span class="order-sub">
              <?= h(ORDER_PAYMENT_LABELS[$o['payment_method']] ?? $o['payment_method']) ?>
              · <?= $o['payment_status'] === 'paid' ? 'Paid' : 'Not paid yet' ?>
              <?php if (!empty($o['transaction_id'])): ?>· Ref <?= h($o['transaction_id']) ?><?php endif; ?>
            </span>
            <span class="order-total">₱<?= number_format((float)$o['total_amount'], 2) ?></span>
          </div>

          <?php if (isset(ORDER_NEXT_STATUS[$o['status']])): ?>
            <form action="order_status.php" method="POST" class="order-actions">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="from" value="<?= h($o['status']) ?>">
              <input type="hidden" name="view" value="<?= h($filter) ?>">
              <button type="submit" class="btn-primary btn-inline"><?= h(ORDER_NEXT_LABEL[$o['status']]) ?></button>
            </form>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
