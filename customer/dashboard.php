<?php
session_start();
require_once __DIR__ . '/../database/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$sql = "
    SELECT
        s.id AS shop_id, s.shop_name, s.description AS shop_description,
        mi.id AS item_id, mi.name AS item_name, mi.description AS item_description,
        mi.price, mi.category, mi.image_url
    FROM shops s
    JOIN menu_items mi ON mi.shop_id = s.id
    WHERE mi.is_available = 1
    ORDER BY s.shop_name, mi.category, mi.name
";
$result = $con->query($sql);

$shops = [];
$categories = [];

while ($row = $result->fetch_assoc()) {
    $shop_id = $row['shop_id'];

    if (!isset($shops[$shop_id])) {
        $shops[$shop_id] = [
            'shop_name'   => $row['shop_name'],
            'description' => $row['shop_description'],
            'items'       => [],
        ];
    }

    $shops[$shop_id]['items'][] = $row;

    $cat = trim($row['category']) !== '' ? $row['category'] : 'Uncategorized';
    if (!in_array($cat, $categories)) {
        $categories[] = $cat;
    }
}

sort($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KaFoodie — Browse</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dash-body">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content dash-content-wide">

  <div class="browse-greeting">
    <h1>Good day, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    <p>What are you craving today?</p>
  </div>

  <?php if (empty($categories)): ?>

    <div class="dash-card">
      <p class="dash-empty">No shops have any available dishes yet — check back soon.</p>
    </div>

  <?php else: ?>

    <div class="chip-row">
      <button class="chip active" data-filter="all">All</button>
      <?php foreach ($categories as $cat): ?>
        <button class="chip" data-filter="<?php echo htmlspecialchars($cat); ?>">
          <?php echo htmlspecialchars($cat); ?>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($shops as $shop_id => $shop): ?>
      <div class="shop-section">
        <div class="shop-section-head">
          <h2><?php echo htmlspecialchars($shop['shop_name']); ?></h2>
          <?php if (!empty($shop['description'])): ?>
            <p><?php echo htmlspecialchars($shop['description']); ?></p>
          <?php endif; ?>
        </div>

        <div class="item-grid">
          <?php foreach ($shop['items'] as $item): ?>
            <?php $cat = trim($item['category']) !== '' ? $item['category'] : 'Uncategorized'; ?>
            <div class="item-card" data-category="<?php echo htmlspecialchars($cat); ?>">
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
                  <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                  <span class="item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                </div>
                <div class="item-category"><?php echo htmlspecialchars($cat); ?></div>
                <?php if (!empty($item['item_description'])): ?>
                  <div class="item-desc"><?php echo htmlspecialchars($item['item_description']); ?></div>
                <?php endif; ?>

                <form action="cart_add.php" method="POST" class="add-to-cart-form">
                  <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                  <input type="hidden" name="shop_id" value="<?php echo $shop_id; ?>">
                  <button type="submit" class="btn-add-cart">Add to cart</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>

<script>
  document.querySelectorAll('.chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
      chip.classList.add('active');

      var filter = chip.dataset.filter;

      document.querySelectorAll('.item-card').forEach(function(card){
        var show = (filter === 'all' || card.dataset.category === filter);
        card.style.display = show ? '' : 'none';
      });

      document.querySelectorAll('.shop-section').forEach(function(section){
        var anyVisible = section.querySelectorAll('.item-card:not([style*="display: none"])').length > 0;
        section.style.display = anyVisible ? '' : 'none';
      });
    });
  });
</script>

</body>
</html>