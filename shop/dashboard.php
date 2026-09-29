<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop') {
    header('Location: ../login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];

$stmt = $con->prepare("SELECT id, shop_name, description FROM shops WHERE owner_id = ?");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();

$shop_id = $shop['id'];

// Get this shop's menu items
$stmt = $con->prepare("SELECT id, name, description, price, category, image_url, is_available FROM menu_items WHERE shop_id = ? ORDER BY id DESC");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$menu_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$shop_error   = $_SESSION['shop_error']   ?? null; unset($_SESSION['shop_error']);
$shop_success = $_SESSION['shop_success'] ?? null; unset($_SESSION['shop_success']);
$item_error   = $_SESSION['item_error']   ?? null; unset($_SESSION['item_error']);
$item_old     = $_SESSION['item_old']     ?? [];   unset($_SESSION['item_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Shop dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <!-- Shop info -->
  <div class="dash-card">
    <div class="dash-card-head"><h2>Shop details</h2></div>

    <?php if ($shop_success): ?>
      <div class="alert-success"><?php echo htmlspecialchars($shop_success); ?></div>
    <?php endif; ?>
    <?php if ($shop_error): ?>
      <div class="alert-error"><?php echo htmlspecialchars($shop_error); ?></div>
    <?php endif; ?>

    <form action="shop_update.php" method="POST" class="dash-form">
      <div class="field">
        <label for="shop_name">Shop name</label>
        <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($shop['shop_name']); ?>" required>
      </div>
      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" placeholder="Tell customers what your shop is about...">
<?php echo htmlspecialchars($shop['description'] ?? ''); ?></textarea>
      </div>
      <button type="submit" class="btn-primary btn-inline">Save changes</button>
    </form>
  </div>

  <!-- Add menu item -->
  <div class="dash-card">
    <div class="dash-card-head"><h2>Add a menu item</h2></div>

    <?php if ($item_error): ?>
      <div class="alert-error"><?php echo htmlspecialchars($item_error); ?></div>
    <?php endif; ?>

    <form action="menu_item_add.php" method="POST" class="dash-form">
      <div class="field-row">
        <div class="field">
          <label for="name">Item name</label>
          <input type="text" id="name" name="name" placeholder="e.g. Chicken Inasal"
                 value="<?php echo htmlspecialchars($item_old['name'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label for="price">Price (₱)</label>
          <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00"
                 value="<?php echo htmlspecialchars($item_old['price'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="category">Category</label>
          <input type="text" id="category" name="category" placeholder="e.g. Rice meals"
                 value="<?php echo htmlspecialchars($item_old['category'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="image_url">Image URL (optional)</label>
          <input type="text" id="image_url" name="image_url" placeholder="https://..."
                 value="<?php echo htmlspecialchars($item_old['image_url'] ?? ''); ?>">
        </div>
      </div>

      <div class="field">
        <label for="item_description">Description</label>
        <textarea id="item_description" name="description" rows="2" placeholder="Short description of the dish">
<?php echo htmlspecialchars($item_old['description'] ?? ''); ?></textarea>
      </div>

      <button type="submit" class="btn-primary btn-inline">Add item</button>
    </form>
  </div>

  <!-- Menu list -->
  <div class="dash-card">
    <div class="dash-card-head"><h2>Your menu (<?php echo count($menu_items); ?>)</h2></div>

    <?php if (empty($menu_items)): ?>
      <p class="dash-empty">You haven't added any menu items yet. Use the form above to add your first dish.</p>
    <?php else: ?>
      <div class="menu-item-list">
        <?php foreach ($menu_items as $item): ?>
          <div class="menu-item-row">
            <div class="menu-item-info">
              <div class="menu-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="menu-item-meta">
                <?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?>
                · ₱<?php echo number_format($item['price'], 2); ?>
              </div>
              <?php if (!empty($item['description'])): ?>
                <div class="menu-item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
              <?php endif; ?>
            </div>

            <div class="menu-item-actions">
              <form action="menu_item_toggle.php" method="POST">
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="tag-toggle <?php echo $item['is_available'] ? 'tag-available' : 'tag-unavailable'; ?>">
                  <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                </button>
              </form>

              <form action="menu_item_delete.php" method="POST" onsubmit="return confirm('Remove this item from your menu?');">
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="btn-delete">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>