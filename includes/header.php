<?php
// Shared top bar for customers, shop owners and admins.
// Every link is built from $nav_base, so this works from any page that sits one folder deep.
require_once __DIR__ . '/notification_helpers.php';

$nav_role = $_SESSION['role'] ?? '';
$nav_name = trim((string)($_SESSION['name'] ?? ''));
$nav_logo = ($nav_role === 'shop') ? trim((string)($_SESSION['logo_url'] ?? '')) : '';

$nav_base = in_array($nav_role, ['customer', 'shop', 'admin'], true) ? '../' . $nav_role . '/' : '../';
$nav_file = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav_dir  = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// [label, file, pages that count as "this section", icon]
if ($nav_role === 'customer') {
    $nav_links = [
        ['Shops',     'dashboard.php', ['dashboard.php', 'shop_menu.php', 'cart.php', 'checkout.php'], 'store'],
        ['My orders', 'orders.php',    ['orders.php'], 'receipt'],
    ];
} elseif ($nav_role === 'shop') {
    $nav_links = [
        ['Dashboard', 'dashboard.php', ['dashboard.php'], 'grid'],
        ['Orders',    'orders.php',    ['orders.php'], 'receipt'],
    ];
} elseif ($nav_role === 'admin') {
    $nav_links = [
        ['Dashboard',    'dashboard.php',    ['dashboard.php'], 'grid'],
        ['Verification', 'verification.php', ['verification.php', 'verification_review.php'], 'shield'],
    ];
} else {
    $nav_links = [];
}

if (!function_exists('kf_icon')) {
    function kf_icon(string $name): string {
        $paths = [
            'store'   => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
            'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
            'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'shield'  => '<path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
            'bell'    => '<path d="M6 9a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
            'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'chevron' => '<path d="M6 9l6 6 6-6"/>',
        ];
        return '<svg class="kf-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
             . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             . ($paths[$name] ?? '') . '</svg>';
    }
}

// Notification bell (customers and shops). If anything fails (for example the step 2 SQL
// has not been run yet) the bell is simply left out and the page still works.
$nav_bell   = false;
$nav_unread = 0;
$nav_recent = [];
if (in_array($nav_role, ['customer', 'shop'], true) && isset($_SESSION['user_id'])
    && isset($con) && $con instanceof mysqli) {
    try {
        $nav_unread = notif_unread_count($con, (int)$_SESSION['user_id']);
        $nav_recent = notif_recent($con, (int)$_SESSION['user_id'], 8);
        $nav_bell   = true;
    } catch (Throwable $nav_e) {
        error_log('header bell failed: ' . $nav_e->getMessage());
    }
}
if (empty($_SESSION['csrf_notif'])) {
    $_SESSION['csrf_notif'] = bin2hex(random_bytes(16));
}

$nav_initial = $nav_name !== '' ? mb_strtoupper(mb_substr($nav_name, 0, 1)) : '?';
$nav_badge   = $nav_unread > 9 ? '9+' : (string)$nav_unread;
?>
<header class="kf-nav">
  <a class="kf-brand" href="<?= htmlspecialchars($nav_base, ENT_QUOTES) ?>dashboard.php" aria-label="KaFoodie home">
    <span class="dash-nav-logo" role="img" aria-label="KaFoodie logo"></span>
    <span class="kf-brand-word">KaFoodie</span>
  </a>

  <nav class="kf-links" id="kfLinks" aria-label="Main">
    <?php foreach ($nav_links as [$nav_label, $nav_target, $nav_matches, $nav_icon]): ?>
      <?php $nav_active = ($nav_dir === $nav_role) && in_array($nav_file, $nav_matches, true); ?>
      <a class="kf-link<?= $nav_active ? ' is-active' : '' ?>"
         href="<?= htmlspecialchars($nav_base . $nav_target, ENT_QUOTES) ?>"
         <?= $nav_active ? 'aria-current="page"' : '' ?>>
        <?= kf_icon($nav_icon) ?><span><?= htmlspecialchars($nav_label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="kf-right">

    <?php if ($nav_bell): ?>
    <div class="kf-bell">
      <button type="button" class="kf-iconbtn" id="kfBellBtn" aria-haspopup="true" aria-expanded="false"
              aria-controls="kfBellPanel"
              aria-label="Notifications<?= $nav_unread > 0 ? ', ' . $nav_unread . ' unread' : '' ?>"
              data-csrf="<?= htmlspecialchars($_SESSION['csrf_notif'], ENT_QUOTES) ?>"
              data-unread="<?= (int)$nav_unread ?>">
        <?= kf_icon('bell') ?>
        <span class="kf-badge" id="kfBadge"<?= $nav_unread > 0 ? '' : ' hidden' ?>><?= htmlspecialchars($nav_badge) ?></span>
      </button>

      <div class="kf-panel kf-panel-bell" id="kfBellPanel" hidden>
        <div class="kf-panel-head">Notifications</div>
        <?php if (!$nav_recent): ?>
          <p class="kf-panel-empty">Nothing new yet.</p>
        <?php else: ?>
          <ul class="kf-notif-list">
            <?php foreach ($nav_recent as $nav_n): $nav_href = notif_href($nav_n, $nav_role, $nav_base); ?>
              <li class="kf-notif<?= (int)$nav_n['is_read'] === 0 ? ' is-unread' : '' ?>">
                <?php if ($nav_href !== ''): ?><a class="kf-notif-body" href="<?= htmlspecialchars($nav_href, ENT_QUOTES) ?>"><?php else: ?><div class="kf-notif-body"><?php endif; ?>
                  <span class="kf-notif-msg"><?= htmlspecialchars($nav_n['message']) ?></span>
                  <span class="kf-notif-time"><?= htmlspecialchars(notif_ago((int)$nav_n['age_sec'])) ?></span>
                <?php echo $nav_href !== '' ? '</a>' : '</div>'; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <a class="kf-panel-foot" href="../notifications/index.php">View all notifications</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="kf-user">
      <button type="button" class="kf-userbtn" id="kfUserBtn" aria-haspopup="true" aria-expanded="false"
              aria-controls="kfUserPanel">
        <?php if ($nav_logo !== ''): ?>
          <img class="kf-avatar" src="<?= htmlspecialchars($nav_logo, ENT_QUOTES) ?>" alt="">
        <?php else: ?>
          <span class="kf-avatar" aria-hidden="true"><?= htmlspecialchars($nav_initial) ?></span>
        <?php endif; ?>
        <span class="kf-username"><?= htmlspecialchars($nav_name) ?></span>
        <?= kf_icon('chevron') ?>
      </button>

      <div class="kf-panel kf-panel-user" id="kfUserPanel" hidden>
        <div class="kf-user-info">
          <strong><?= htmlspecialchars($nav_name) ?></strong>
          <span><?= htmlspecialchars(ucfirst($nav_role)) ?></span>
        </div>
        <a class="kf-menu-item" href="../logout.php">Log out</a>
      </div>
    </div>

    <?php if ($nav_links): ?>
    <button type="button" class="kf-iconbtn kf-burger" id="kfBurger" aria-expanded="false"
            aria-controls="kfLinks" aria-label="Menu">
      <?= kf_icon('menu') ?>
    </button>
    <?php endif; ?>

  </div>
</header>

<script>
(function () {
  var bellBtn   = document.getElementById('kfBellBtn');
  var bellPanel = document.getElementById('kfBellPanel');
  var userBtn   = document.getElementById('kfUserBtn');
  var userPanel = document.getElementById('kfUserPanel');
  var burger    = document.getElementById('kfBurger');
  var links     = document.getElementById('kfLinks');
  var badge     = document.getElementById('kfBadge');

  function setOpen(btn, panel, open) {
    if (!btn || !panel) return;
    panel.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function closeAll(except) {
    if (except !== 'bell') {
      if (bellPanel && !bellPanel.hidden) {
        // Opened messages are now read: drop the unread highlight once the panel closes
        bellPanel.querySelectorAll('.is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
      }
      setOpen(bellBtn, bellPanel, false);
    }
    if (except !== 'user') setOpen(userBtn, userPanel, false);
    if (except !== 'burger' && burger && links) {
      links.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    }
  }

  if (bellBtn) {
    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var opening = bellPanel.hidden;
      closeAll(opening ? 'bell' : '');
      setOpen(bellBtn, bellPanel, opening);

      // First time it is opened with unread messages: tell the server and clear the badge
      if (opening && parseInt(bellBtn.dataset.unread, 10) > 0) {
        bellBtn.dataset.unread = '0';
        if (badge) badge.hidden = true;
        bellBtn.setAttribute('aria-label', 'Notifications');
        fetch('../notifications/mark_read.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'csrf=' + encodeURIComponent(bellBtn.dataset.csrf)
        }).catch(function () {});
      }
    });
  }

  if (userBtn) {
    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var opening = userPanel.hidden;
      closeAll(opening ? 'user' : '');
      setOpen(userBtn, userPanel, opening);
    });
  }

  if (burger && links) {
    burger.addEventListener('click', function (e) {
      e.stopPropagation();
      var opening = !links.classList.contains('is-open');
      closeAll(opening ? 'burger' : '');
      links.classList.toggle('is-open', opening);
      burger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
  }

  // Click anywhere else, or press Escape, to close
  document.addEventListener('click', function (e) {
    if (e.target.closest('.kf-panel')) return;
    closeAll('');
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll('');
  });
})();
</script>