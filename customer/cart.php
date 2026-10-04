<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

$cart = $_SESSION['cart'] ?? ['shop_id' => null, 'items' => []];
$cart_notice = $_SESSION['cart_notice'] ?? null;
unset($_SESSION['cart_notice']);

$cart_items = [];
$shop = null;
$total = 0.0;

if (!empty($cart['items']) && !empty($cart['shop_id'])) {
    $shop_id  = (int)$cart['shop_id'];
    $item_ids = array_map('intval', array_keys($cart['items']));
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $types = 'i' . str_repeat('i', count($item_ids));

    // Only items that still exist, are still available, and belong to the cart's shop
    $stmt = $con->prepare(
        "SELECT id, name, price, image_url, image_mime, UNIX_TIMESTAMP(image_updated_at) AS image_v FROM menu_items
         WHERE shop_id = ? AND is_available = 1 AND id IN ($placeholders)"
    );
    $stmt->bind_param($types, $shop_id, ...$item_ids);
    $stmt->execute();
    $found = [];
    foreach ($stmt->get_result() as $row) {
        $found[(int)$row['id']] = $row;
    }
    $stmt->close();

    // Keep the order the customer added things, and drop stale items from the session
    foreach ($cart['items'] as $id => $qty) {
        $id = (int)$id;
        if (!isset($found[$id])) {
            unset($_SESSION['cart']['items'][$id]);
            continue;
        }
        $price    = (float)$found[$id]['price'];
        $subtotal = $price * (int)$qty;
        $total   += $subtotal;
        $cart_items[] = [
            'id'       => $id,
            'name'     => $found[$id]['name'],
            'image'    => dish_src($found[$id], '../'),
            'price'    => $price,
            'quantity' => (int)$qty,
            'subtotal' => $subtotal,
        ];
    }

    if (empty($_SESSION['cart']['items'])) {
        $_SESSION['cart'] = ['shop_id' => null, 'items' => []];
        $cart['shop_id']  = null;
    } else {
        $stmt = $con->prepare("SELECT shop_name FROM shops WHERE id = ?");
        $stmt->bind_param('i', $shop_id);
        $stmt->execute();
        $shop = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Back button: return to the menu of the shop being ordered from,
// or to the shop list when the cart is empty.
if (!empty($cart['shop_id'])) {
    $back_url   = 'shop_menu.php?id=' . (int)$cart['shop_id'];
    $back_label = '← Back to menu';
} else {
    $back_url   = 'dashboard.php';
    $back_label = '← Back to shops';
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

  <div class="cart-topbar">
    <a href="<?php echo htmlspecialchars($back_url); ?>" class="cart-back"><?php echo htmlspecialchars($back_label); ?></a>
  </div>

  <div class="browse-greeting">
    <div class="cart-title-row">
      <span class="cart-icon cart-logo" aria-hidden="true"></span>
      <div>
        <h1>Your cart</h1>
        <?php if ($shop): ?><p>Ordering from <?php echo htmlspecialchars($shop['shop_name']); ?></p><?php endif; ?>
      </div>
    </div>
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
              <?php if ($item['image'] !== ''): ?>
                <img class="cart-thumb" src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES); ?>" alt="">
              <?php else: ?>
                <span class="cart-thumb" aria-hidden="true">🍽️</span>
              <?php endif; ?>
              <div class="cart-row-text">
                <div class="cart-row-name"><?php echo htmlspecialchars($item['name']); ?></div>
                <div class="cart-row-meta">₱<?php echo number_format($item['price'], 2); ?> each</div>
              </div>
            </div>

            <form action="cart_update.php" method="POST" class="cart-qty-form">
              <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
              <button type="submit" name="action" value="decrease" class="qty-btn" aria-label="Decrease quantity">−</button>
              <span class="qty-value"><?php echo $item['quantity']; ?></span>
              <button type="submit" name="action" value="increase" class="qty-btn" aria-label="Increase quantity">+</button>
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