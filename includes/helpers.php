<?php
// Shared helpers for the shop (vendor) area and for showing images.

// Photo limit. php.ini (upload_max_filesize, post_max_size) and MySQL (max_allowed_packet) must allow it too.
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

/** Human-readable photo limit, e.g. "5 MB" (used in form hints and error messages). */
function max_upload_label(): string {
    return round(MAX_IMAGE_BYTES / 1024 / 1024) . ' MB';
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------- Images live in the database and are served by image.php ---------- */

/**
 * URL of a shop's logo, or '' when it has none.
 * The shop row needs: id, logo_mime, logo_v (and logo_url for old external links).
 * $base is '../' for pages one folder deep (shop/, customer/) and '' for index.php.
 */
function logo_src(array $shop, string $base = '../'): string {
    if (!empty($shop['logo_mime'])) {
        return $base . 'image.php?t=logo&id=' . (int)$shop['id'] . '&v=' . (int)($shop['logo_v'] ?? 0);
    }
    return media_url($shop['logo_url'] ?? '', $base);
}

/**
 * URL of a dish photo, or '' when it has none.
 * The item row needs: id, image_mime, image_v (and image_url for old external links).
 */
function dish_src(array $item, string $base = '../'): string {
    if (!empty($item['image_mime'])) {
        return $base . 'image.php?t=menu&id=' . (int)$item['id'] . '&v=' . (int)($item['image_v'] ?? 0);
    }
    return media_url($item['image_url'] ?? '', $base);
}

/** Old-style values: external https:// links, or files from the time images were saved in uploads/. */
function media_url(?string $path, string $base = '../'): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    if (strpos($path, 'uploads/') === 0) return $base . $path;
    return '';
}

/**
 * Validate an uploaded image and return ['data' => bytes, 'mime' => 'image/...'] to store in the
 * database. Returns null when nothing was chosen ($error stays null) or when something is wrong
 * ($error is set). Pass the mysqli connection so a photo bigger than MySQL's max_allowed_packet
 * gives a clear message instead of a crash.
 */
function read_uploaded_image(array $file, ?string &$error = null, ?mysqli $con = null): ?array {
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'That image is too large. Please choose one under ' . max_upload_label() . '.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'The upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        $error = 'That image is too large. Please choose one under ' . max_upload_label() . '.';
        return null;
    }

    $info    = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        $error = 'Please upload a JPG, PNG or WebP image.';
        return null;
    }

    $data = file_get_contents($file['tmp_name']);
    if ($data === false || $data === '') {
        $error = 'The upload failed. Please try again.';
        return null;
    }

    if ($con) {
        $row   = $con->query('SELECT @@max_allowed_packet')->fetch_row();
        $limit = (int)($row[0] ?? 0);
        if ($limit > 0 && strlen($data) + 8192 > $limit) {
            $error = 'That photo is bigger than the database currently accepts (MySQL max_allowed_packet is '
                   . round($limit / 1024 / 1024, 1) . ' MB). Choose a smaller photo, or raise that setting.';
            return null;
        }
    }

    return ['data' => $data, 'mime' => $allowed[$info[2]]];
}

/* ---------- CSRF + flash messages ---------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf_shop'])) {
        $_SESSION['csrf_shop'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_shop'];
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** $open = which card's window to reopen: 'profile', 'add' or 'menu'. */
function redirect_dashboard(string $open = ''): void {
    header('Location: dashboard.php' . ($open !== '' ? '?open=' . urlencode($open) : ''));
    exit;
}

/* ---------- Guards used by every shop page ---------- */

/** Must be a logged-in shop owner. Returns the owner's shop row (without the logo bytes). */
function require_shop(mysqli $con): array {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'shop') {
        header('Location: ../vendor/vendor_login.php');
        exit;
    }
    $stmt = $con->prepare("
        SELECT id, shop_name, description, logo_url, logo_mime,
               UNIX_TIMESTAMP(logo_updated_at) AS logo_v
        FROM shops WHERE owner_id = ?
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$shop) {
        http_response_code(404);
        exit('We could not find your shop.');
    }
    return $shop;
}

/** Form handlers: must be a POST with a valid token. */
function require_post_csrf(): void {
    // A photo bigger than php.ini's post_max_size arrives as an empty POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'That upload is too large. Please choose a smaller photo (under ' . max_upload_label() . ').');
        redirect_dashboard();
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !hash_equals($_SESSION['csrf_shop'] ?? '', $_POST['csrf'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
        redirect_dashboard();
    }
}