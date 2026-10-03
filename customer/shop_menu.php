<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login/login.php');
    exit;
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    header('Location: dashboard.php');
    exit;
}

$shop_id = (int)$_GET['id'];

// Shop details
$shop_stmt = $con->prepare("SELECT shop_name, description, logo_url FROM shops WHERE id = ?");
$shop_stmt->bind_param("i", $shop_id);
$shop_stmt->execute();
$shop_result = $shop_stmt->get_result();
$shop = $shop_result->fetch_assoc();
$shop_stmt->close();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Menu items
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
$menu_stmt->close();

$cart_count = array_sum(array_map('intval', $_SESSION['cart']['items'] ?? []));

// One-time "added to cart" message from cart_add.php
$menu_notice = $_SESSION['menu_notice'] ?? null;
unset($_SESSION['menu_notice']);

// Make an image URL safe to drop inside CSS url('...') (htmlspecialchars alone
// would let a quote or bracket in the URL break out of the CSS string).
function kf_css_url(string $url): string {
    return htmlspecialchars(
        str_replace(["'", '"', '(', ')', '\\', ' '], ['%27', '%22', '%28', '%29', '%5C', '%20'], $url),
        ENT_QUOTES
    );
}
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
            <div class="shop-menu-nav">
                <a href="dashboard.php" class="cart-back">← Back to all shops</a>
                <a href="cart.php" class="sd-cartbtn" aria-label="Open your cart (<?php echo $cart_count; ?> items)">
                    <span class="cart-icon" aria-hidden="true"></span>
                    <span>Cart</span>
                    <span class="sd-badge<?php echo $cart_count === 0 ? ' is-zero' : ''; ?>"><?php echo $cart_count; ?></span>
                </a>
            </div>

            <div class="shop-info-row">
                <?php if (!empty($shop['logo_url'])): ?>
                    <div class="shop-logo">
                        <img src="<?php echo htmlspecialchars(media_url($shop['logo_url'], '../'), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($shop['shop_name']); ?> logo">
                    </div>
                <?php else: ?>
                    <div class="shop-logo-placeholder">
                        <span class="shop-logo-emoji">🏪</span>
                    </div>
                <?php endif; ?>
                <div class="shop-details">
                    <h1><?php echo htmlspecialchars($shop['shop_name']); ?></h1>
                    <p><?php echo htmlspecialchars($shop['description'] ?: 'No description available.'); ?></p>
                </div>
            </div>
        </div>

        <hr class="shop-divider">

        <?php if (empty($items)): ?>
            <div class="dash-card">
                <p class="dash-empty">This shop doesn't have any dishes available right now — check back soon!</p>
            </div>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach ($items as $item): ?>
                    <?php $cat = trim((string)$item['category']) !== '' ? trim($item['category']) : 'Uncategorized'; ?>
                    <div class="item-card" id="item-<?php echo (int)$item['id']; ?>">
                        <?php /* image_url is per-item data, so it is the one inline style that has to stay */ ?>
                        <div class="item-thumb"
                             <?php if (!empty($item['image_url'])): ?>
                                style="background-image:url('<?php echo kf_css_url(media_url($item['image_url'], '../')); ?>');"
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
                                <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                                <input type="hidden" name="shop_id" value="<?php echo $shop_id; ?>">
                                <button type="submit" class="btn-add-cart">Add to cart</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($menu_notice): ?>
        <div class="kf-toast" id="kfToast" role="status" aria-live="polite">
            <span class="kf-toast-icon" aria-hidden="true">✓</span>
            <span class="kf-toast-text"><?php echo htmlspecialchars($menu_notice); ?></span>
            <a href="cart.php" class="kf-toast-link">View cart</a>
            <button type="button" class="kf-toast-close" id="kfToastClose" aria-label="Dismiss">&times;</button>
        </div>
        <script>
        (function () {
            var toast = document.getElementById('kfToast');
            if (!toast) return;
            function hide() {
                toast.classList.add('is-leaving');
                setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
            }
            document.getElementById('kfToastClose').addEventListener('click', hide);
            setTimeout(hide, 5000);
        })();
        </script>
    <?php endif; ?>

</body>
</html>