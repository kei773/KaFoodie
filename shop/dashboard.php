<?php
session_start();
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/helpers.php';

$shop    = require_shop($con);
$shop_id = (int)$shop['id'];

// Document verification status, shown on the verification card
$verif_labels = [
    'not_submitted' => 'Not submitted',
    'pending'       => 'Pending review',
    'approved'      => 'Verified',
    'rejected'      => 'Not approved',
];
$stmt = $con->prepare("SELECT verification_status FROM shops WHERE id = ?");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$vrow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$verif_status = $vrow['verification_status'] ?? 'not_submitted';
$verif_label  = $verif_labels[$verif_status] ?? 'Not submitted';

// Menu items
$stmt = $con->prepare("SELECT id, name, description, price, category, image_url, is_available FROM menu_items WHERE shop_id = ? ORDER BY id DESC");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$menu_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$item_count = count($menu_items);

// One-time message + previously typed values after a failed "add item"
$flash    = $_SESSION['flash']    ?? null; unset($_SESSION['flash']);
$item_old = $_SESSION['item_old'] ?? [];   unset($_SESSION['item_old']);

// Which window to open on load (after saving / errors)
$open = in_array($_GET['open'] ?? '', ['profile', 'add', 'menu'], true) ? $_GET['open'] : '';

$logo_src = logo_src($shop, '../');
$_SESSION['logo_url'] = $logo_src;   // used by includes/header.php
$csrf = csrf_token();

function render_flash(?array $f): void {
    if (!$f) return;
    $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="' . $cls . '" role="' . ($f['type'] === 'success' ? 'status' : 'alert') . '">' . h($f['msg']) . '</div>';
}
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
<body class="dash-body" data-open="<?= h($open) ?>">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="dash-content">

  <!-- Shop summary -->
  <div class="sh-hero">
    <span class="sh-logo" <?php if ($logo_src): ?>style="background-image:url('<?= h($logo_src) ?>')"<?php endif; ?>>
      <?= $logo_src ? '' : '🏪' ?>
    </span>
    <div>
      <h1><?= h($shop['shop_name']) ?></h1>
      <p><?= h($shop['description'] ?: 'Manage your shop, menu and photos from here.') ?></p>
    </div>
  </div>

  <?php if ($open === '') render_flash($flash); ?>

  <!-- Management cards: each one opens its own window -->
  <div class="sh-grid">

    <button type="button" class="sh-card" data-open="dlg-profile">
      <span class="sh-card-icon" aria-hidden="true">🏪</span>
      <span class="sh-card-title">Shop profile</span>
      <span class="sh-card-sub">Change your shop name, logo and description.</span>
    </button>

    <button type="button" class="sh-card" data-open="dlg-add">
      <span class="sh-card-icon" aria-hidden="true">➕</span>
      <span class="sh-card-title">Add a menu item</span>
      <span class="sh-card-sub">Add a new dish with its own photo.</span>
    </button>

    <button type="button" class="sh-card" data-open="dlg-menu">
      <span class="sh-card-icon" aria-hidden="true">📋</span>
      <span class="sh-card-title">Manage menu</span>
      <span class="sh-card-sub"><?= $item_count ?> item<?= $item_count === 1 ? '' : 's' ?> · set availability or delete.</span>
    </button>

    <a class="sh-card sh-card-link" href="verification.php">
      <span class="sh-card-icon" aria-hidden="true">📄</span>
      <span class="sh-card-title">Document verification</span>
      <span class="sh-card-sub">Upload your business documents for admin review.</span>
      <span class="sh-badge sh-badge-<?= h($verif_status) ?>"><?= h($verif_label) ?></span>
    </a>

  </div>
</div>


<!-- ================= Window: Shop profile ================= -->
<dialog class="sh-modal" id="dlg-profile" aria-labelledby="dlg-profile-title">
  <div class="sh-modal-head">
    <h2 id="dlg-profile-title">Shop profile</h2>
    <button type="button" class="sh-modal-close" data-close aria-label="Close">&times;</button>
  </div>
  <div class="sh-modal-body">
    <?php if ($open === 'profile') render_flash($flash); ?>

    <form action="shop_update.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <div class="sh-upload">
        <span class="sh-preview sh-preview-round" id="logo-preview" <?php if ($logo_src): ?>style="background-image:url('<?= h($logo_src) ?>')"<?php endif; ?>><?= $logo_src ? '' : '🏪' ?></span>
        <div class="sh-upload-fields">
          <label for="logo">Shop logo</label>
          <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logo-preview">
          <small>JPG, PNG or WebP, up to <?= max_upload_label() ?>. A square image works best.</small>
          <?php if ($logo_src): ?>
            <label class="sh-check"><input type="checkbox" name="remove_logo" value="1"> Remove my current logo</label>
          <?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="shop_name">Shop name</label>
        <input type="text" id="shop_name" name="shop_name" maxlength="100" value="<?= h($shop['shop_name']) ?>" required>
      </div>
      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="500" placeholder="Tell customers what your shop is about..."><?= h($shop['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn-primary">Save changes</button>
    </form>
  </div>
</dialog>


<!-- ================= Window: Add a menu item ================= -->
<dialog class="sh-modal" id="dlg-add" aria-labelledby="dlg-add-title">
  <div class="sh-modal-head">
    <h2 id="dlg-add-title">Add a menu item</h2>
    <button type="button" class="sh-modal-close" data-close aria-label="Close">&times;</button>
  </div>
  <div class="sh-modal-body">
    <?php if ($open === 'add') render_flash($flash); ?>

    <form action="menu_item_add.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <div class="sh-upload">
        <span class="sh-preview" id="food-preview" aria-hidden="true">🍽️</span>
        <div class="sh-upload-fields">
          <label for="image">Photo of the dish (optional)</label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="food-preview">
          <small>JPG, PNG or WebP, up to <?= max_upload_label() ?>.</small>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="name">Item name</label>
          <input type="text" id="name" name="name" maxlength="100" placeholder="e.g. Chicken Inasal" value="<?= h($item_old['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="price">Price (₱)</label>
          <div class="price-input">
            <span class="price-sign" aria-hidden="true">₱</span>
            <input type="text" id="price" name="price" class="js-price" inputmode="decimal" maxlength="11" autocomplete="off" placeholder="0.00" value="<?= h($item_old['price'] ?? '') ?>" required>
          </div>
        </div>
      </div>

      <div class="field">
        <label for="category">Category</label>
        <input type="text" id="category" name="category" maxlength="50" placeholder="e.g. Rice meals" value="<?= h($item_old['category'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="item_description">Description</label>
        <textarea id="item_description" name="description" rows="2" maxlength="500" placeholder="Short description of the dish"><?= h($item_old['description'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn-primary">Add item</button>
    </form>
  </div>
</dialog>


<!-- ================= Window: Manage menu ================= -->
<dialog class="sh-modal sh-modal-wide" id="dlg-menu" aria-labelledby="dlg-menu-title">
  <div class="sh-modal-head">
    <h2 id="dlg-menu-title">Your menu (<?= $item_count ?>)</h2>
    <button type="button" class="sh-modal-close" data-close aria-label="Close">&times;</button>
  </div>
  <div class="sh-modal-body">
    <?php if ($open === 'menu') render_flash($flash); ?>

    <?php if (empty($menu_items)): ?>
      <p class="dash-empty">You haven't added any menu items yet. Close this window and use “Add a menu item”.</p>
    <?php else: ?>
      <div class="sh-menu-list">
        <?php foreach ($menu_items as $item): $thumb = dish_src($item, '../'); ?>
          <div class="sh-menu-row<?= $item['is_available'] ? '' : ' is-off' ?>">
            <span class="sh-thumb" <?php if ($thumb): ?>style="background-image:url('<?= h($thumb) ?>')"<?php endif; ?>><?= $thumb ? '' : '🍽️' ?></span>

            <div class="sh-menu-info">
              <div class="sh-menu-name"><?= h($item['name']) ?></div>
              <div class="sh-menu-meta"><?= h($item['category'] ?: 'Uncategorized') ?> · ₱<?= number_format((float)$item['price'], 2) ?></div>
            </div>

            <div class="sh-menu-actions">
              <form action="menu_item_toggle.php" method="POST">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <button type="submit"
                        class="sh-switch <?= $item['is_available'] ? 'is-on' : '' ?>"
                        role="switch"
                        aria-checked="<?= $item['is_available'] ? 'true' : 'false' ?>"
                        title="<?= $item['is_available'] ? 'Click to mark as unavailable' : 'Click to mark as available' ?>">
                  <span class="sh-switch-track" aria-hidden="true"><span class="sh-switch-knob"></span></span>
                  <span class="sh-switch-label"><?= $item['is_available'] ? 'Available' : 'Unavailable' ?></span>
                </button>
              </form>

              <button type="button" class="sh-edit"
                      data-id="<?= (int)$item['id'] ?>"
                      data-name="<?= h($item['name']) ?>"
                      data-price="<?= h($item['price']) ?>"
                      data-category="<?= h($item['category']) ?>"
                      data-description="<?= h($item['description']) ?>"
                      data-image="<?= h($thumb) ?>">Edit</button>

              <button type="button" class="btn-delete sh-delete"
                      data-id="<?= (int)$item['id'] ?>"
                      data-name="<?= h($item['name']) ?>">Delete</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</dialog>


<!-- ================= Window: Edit a menu item ================= -->
<dialog class="sh-modal" id="dlg-edit" aria-labelledby="dlg-edit-title">
  <div class="sh-modal-head">
    <h2 id="dlg-edit-title">Edit menu item</h2>
    <button type="button" class="sh-modal-close" data-close aria-label="Close">&times;</button>
  </div>
  <div class="sh-modal-body">
    <form action="menu_item_update.php" method="POST" enctype="multipart/form-data" id="edit-form">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="item_id" id="edit-id">

      <div class="sh-upload">
        <span class="sh-preview" id="edit-preview" aria-hidden="true">🍽️</span>
        <div class="sh-upload-fields">
          <label for="edit-image">Photo of the dish</label>
          <input type="file" id="edit-image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="edit-preview">
          <small>Choose a new photo to replace the current one. JPG, PNG or WebP, up to <?= max_upload_label() ?>.</small>
          <label class="sh-check" id="edit-remove-wrap" hidden><input type="checkbox" name="remove_image" value="1"> Remove the current photo</label>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="edit-name">Item name</label>
          <input type="text" id="edit-name" name="name" maxlength="100" required>
        </div>
        <div class="field">
          <label for="edit-price">Price (₱)</label>
          <div class="price-input">
            <span class="price-sign" aria-hidden="true">₱</span>
            <input type="text" id="edit-price" name="price" class="js-price" inputmode="decimal" maxlength="11" autocomplete="off" placeholder="0.00" required>
          </div>
        </div>
      </div>

      <div class="field">
        <label for="edit-category">Category</label>
        <input type="text" id="edit-category" name="category" maxlength="50">
      </div>

      <div class="field">
        <label for="edit-description">Description</label>
        <textarea id="edit-description" name="description" rows="2" maxlength="500"></textarea>
      </div>

      <button type="submit" class="btn-primary">Save changes</button>
    </form>
  </div>
</dialog>


<!-- ================= Window: Confirm delete ================= -->
<dialog class="sh-modal sh-modal-danger" id="dlg-delete" aria-labelledby="dlg-delete-title">
  <div class="sh-modal-head">
    <h2 id="dlg-delete-title">Delete this menu item?</h2>
    <button type="button" class="sh-modal-close" data-close aria-label="Close">&times;</button>
  </div>
  <div class="sh-modal-body">
    <div class="sh-warning" role="alert">
      <strong>⚠️ This cannot be undone.</strong>
      <p>You are about to permanently delete <b id="delete-name"></b>. The dish and its photo will be removed from your menu and customers will no longer see it.</p>
      <p>Only want to hide it for a while? Close this window and switch it to <b>Unavailable</b> instead.</p>
    </div>

    <form action="menu_item_delete.php" method="POST" id="delete-form">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="item_id" id="delete-id">

      <div class="field">
        <label for="delete-confirm">To confirm, type <b>delete</b> below</label>
        <input type="text" id="delete-confirm" name="confirm_text" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="delete">
      </div>

      <div class="sh-modal-actions">
        <button type="button" class="btn-outline" data-close>Cancel</button>
        <button type="submit" class="sh-btn-danger" id="delete-submit" disabled>Delete item</button>
      </div>
    </form>
  </div>
</dialog>

<style>
  .price-input{position:relative;}
  .price-input .price-sign{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-weight:600;color:var(--muted);pointer-events:none;}
  .field .price-input input{padding-left:36px;}
</style>

<script>
(function () {
  // Open a window from its card
  document.querySelectorAll('[data-open]').forEach(function (btn) {
    if (!btn.dataset.open || btn.tagName === 'BODY') return;
    btn.addEventListener('click', function () {
      var d = document.getElementById(btn.dataset.open);
      if (d) d.showModal();
    });
  });

  // Close with × or by clicking the dark area outside
  document.querySelectorAll('dialog.sh-modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    d.querySelectorAll('[data-close]').forEach(function (x) {
      x.addEventListener('click', function () { d.close(); });
    });
  });

  // Live preview of a chosen photo
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files[0], t = document.getElementById(inp.dataset.preview);
      if (!f || !t) return;
      t.style.backgroundImage = "url('" + URL.createObjectURL(f) + "')";
      t.textContent = '';
    });
  });

  // Edit: fill the edit window with the clicked item's details
  document.querySelectorAll('.sh-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = btn.dataset, form = document.getElementById('edit-form');
      document.getElementById('edit-id').value          = d.id;
      document.getElementById('edit-name').value        = d.name;
      document.getElementById('edit-price').value       = d.price;
      formatPrice(document.getElementById('edit-price'));
      document.getElementById('edit-category').value    = d.category;
      document.getElementById('edit-description').value = d.description;
      form.elements['image'].value = '';
      form.elements['remove_image'].checked = false;

      var prev = document.getElementById('edit-preview');
      prev.style.backgroundImage = d.image ? "url('" + d.image + "')" : '';
      prev.textContent = d.image ? '' : '🍽️';
      document.getElementById('edit-remove-wrap').hidden = !d.image;

      document.getElementById('dlg-edit').showModal();
    });
  });

  // Delete: ask for confirmation, and only enable the button once "delete" is typed
  var delInput  = document.getElementById('delete-confirm');
  var delSubmit = document.getElementById('delete-submit');
  function delMatches() { return delInput.value.trim().toLowerCase() === 'delete'; }

  document.querySelectorAll('.sh-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('delete-id').value = btn.dataset.id;
      document.getElementById('delete-name').textContent = '\u201C' + btn.dataset.name + '\u201D';
      delInput.value = '';
      delSubmit.disabled = true;
      document.getElementById('dlg-delete').showModal();
      delInput.focus();
    });
  });
  delInput.addEventListener('input', function () { delSubmit.disabled = !delMatches(); });
  document.getElementById('delete-form').addEventListener('submit', function (e) {
    if (!delMatches()) e.preventDefault();
  });

  // Price fields: numbers only, max 2 decimals, shown as 99.00 when you leave the field
  function cleanPrice(v) {
    v = v.replace(/[^0-9.]/g, '');
    var i = v.indexOf('.');
    if (i !== -1) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '').slice(0, 2);
    return v;
  }
  function formatPrice(inp) {
    var v = cleanPrice(inp.value);
    if (v === '' || v === '.') { inp.value = ''; return; }
    var n = parseFloat(v);
    inp.value = isNaN(n) ? '' : n.toFixed(2);
  }
  document.querySelectorAll('.js-price').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var c = cleanPrice(inp.value);
      if (c !== inp.value) inp.value = c;
    });
    inp.addEventListener('blur', function () { formatPrice(inp); });
    inp.form.addEventListener('submit', function () { formatPrice(inp); });
    formatPrice(inp);
  });

  // Reopen the right window after saving / an error
  var auto = document.body.dataset.open;
  if (auto) {
    var d = document.getElementById('dlg-' + auto);
    if (d) d.showModal();
  }
})();
</script>

</body>
</html>