<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

// 1. Authentication check: Must be logged in as a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

// 2. Validate shop ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    header('Location: ../index.php');
    exit;
}

$shop_id = (int)$_GET['id'];

// 3. Fetch Shop Details
$shop_stmt = $con->prepare("SELECT shop_name, description FROM shops WHERE id = ?");
$shop_stmt->bind_param("i", $shop_id);
$shop_stmt->execute();
$shop_result = $shop_stmt->get_result();
$shop = $shop_result->fetch_assoc();

if (!$shop) {
    // Shop not found
    header('Location: ../index.php');
    exit;
}

// 4. Fetch Available Menu Items
$menu_stmt = $con->prepare("
    SELECT id, name, description, price, category, image_url
    FROM menu_items
    WHERE shop_id = ? AND is_available = 1
    ORDER BY category, name
");
$menu_stmt->bind_param("i", $shop_id);
$menu_stmt->execute();
$menu_result = $menu_stmt->get_result();
$items = $menu_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaFoodie — <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="dash-content dash-content-wide">

        <div class="shop-menu-header">
            <a href="dashboard.php" class="btn-text" style="margin-bottom: 20px; display: inline-block;">← Back to all shops</a>

            <div class="shop-info-row">
                <div class="shop-logo-placeholder">
                    <span class="placeholder-emoji" style="font-size: 3rem;">🏪</span>
                </div>
                <div class="shop-details">
                    <h1><?php echo htmlspecialchars($shop['shop_name']); ?></h1>
                    <p><?php echo htmlspecialchars($shop['description'] ?? 'No description available.'); ?></p>
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">

        <?php if (empty($items)): ?>
            <div class="dash-card">
                <p class="dash-empty">This shop doesn't have any dishes available right now — check back soon!</p>
            </div>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach ($items as $item): ?>
                    <?php $cat = trim($item['category']) !== '' ? $item['category'] : 'Uncategorized'; ?>
                    <div class="item-card">
                        <div class="item-thumb"
                             <?php if (!empty($item['image_url'])): ?>
                                style="background-image:url('<?php echo htmlspecialchars($item['image_url']); ?>');"
                             <?php endif; ?>>
                            <?php if (empty($item['image_url'])): ?>
                                <span class="item-thumb-fallback">🍽️</span>
                            <?php endif; ?>
                        </div>
                        <div class="item-body">
                            <div class="item-top">
                                <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                            <div class="item-category"><?php echo htmlspecialchars($cat); ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                            <?php endif; ?>

                            <form action="cart_add.php" method="POST" class="add-to-cart-form">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="shop_id" value="<?php echo $shop_id; ?>">
                                <button type="submit" class="btn-add-cart">Add to cart</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .shop-menu-header {
            margin-bottom: 30px;
        }
        .shop-info-row {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 20px;
        }
        .shop-logo-placeholder {
            width: 100px;
            height: 100px;
            background: #fff;
            border: 2px dashed #FF6B35;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .shop-details h1 {
            margin: 0;
            color: #333;
            font-size: 2rem;
        }
        .shop-details p {
            margin: 5px 0 0 0;
            color: #666;
        }
    </style>
</body>
</html>
