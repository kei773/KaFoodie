<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$cart = $_SESSION['cart'] ?? ['shop_id' => null, 'items' => []];
$cart_notice = $_SESSION['cart_notice'] ?? null;
unset($_SESSION['cart_notice']);

$cart_items = [];
$shop = null;
$total = 0;

if (!empty($cart['items'])) {
    $item_ids = array_keys($cart['items']);
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $types = str_repeat('i', count($item_ids));

    $stmt = $con->prepare("SELECT id, name, price FROM menu_items WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$item_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $qty = $cart['items'][$row['id']];
        $subtotal = $row['price'] * $qty;
        $total += $subtotal;
        $cart_items[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'quantity' => $qty,
            'subtotal' => $subtotal,
        ];
    }
    $stmt->close();

    if ($cart['shop_id']) {
        $stmt = $con->prepare("SELECT shop_name FROM shops WHERE id = ?");
        $stmt->bind_param('i', $cart['shop_id']);
        $stmt->execute();
        $shop = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Your cart</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <div class="browse-greeting">
    <h1>Your cart</h1>
    <?php if ($shop): ?><p>Ordering from <?php echo htmlspecialchars($shop['shop_name']); ?></p><?php endif; ?>
  </div>

  <?php if ($cart_notice): ?>
    <div class="alert-success"><?php echo htmlspecialchars($cart_notice); ?></div>
  <?php endif; ?>

  <?php if (empty($cart_items)): ?>
    <div class="dash-card">
      <p class="dash-empty">Your cart is empty. <a href="dashboard.php">Browse shops</a> to add something.</p>
    </div>
  <?php else: ?>

    <div class="dash-card">
      <div class="cart-list">
        <?php foreach ($cart_items as $item): ?>
          <div class="cart-row">
            <div class="cart-row-info">
              <div class="cart-row-name"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="cart-row-meta">₱<?php echo number_format($item['price'], 2); ?> each</div>
            </div>

            <form action="cart_update.php" method="POST" class="cart-qty-form">
              <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
              <button type="submit" name="action" value="decrease" class="qty-btn">−</button>
              <span class="qty-value"><?php echo $item['quantity']; ?></span>
              <button type="submit" name="action" value="increase" class="qty-btn">+</button>
            </form>

            <div class="cart-row-subtotal">₱<?php echo number_format($item['subtotal'], 2); ?></div>

            <form action="cart_remove.php" method="POST">
              <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
              <button type="submit" class="btn-delete">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cart-total-row">
        <span>Total</span>
        <span>₱<?php echo number_format($total, 2); ?></span>
      </div>

      <a href="checkout.php" class="btn-primary btn-checkout">Proceed to checkout</a>
    </div>

  <?php endif; ?>

</div>

</body>
</html>