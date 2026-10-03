<?php
// Shared helpers for the shop (vendor) area.

const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // change this number to raise/lower the photo limit (php.ini must allow it too)
const UPLOAD_ROOT     = __DIR__ . '/../uploads';

/** Human-readable photo limit, e.g. "5 MB" (used in form hints and error messages). */
function max_upload_label(): string {
    return round(MAX_IMAGE_BYTES / 1024 / 1024) . ' MB';
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Turn a stored image value into a URL the current page can use.
 * Old external links (https://...) are returned as-is; uploaded files are
 * stored as "uploads/..." and need the right prefix for the page's folder.
 * Pages one folder deep (shop/, customer/) use '../'; index.php uses ''.
 */
function media_url(?string $path, string $base = '../'): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return $base . ltrim($path, '/');
}

/**
 * Validate and store an uploaded image. Returns 'uploads/<folder>/<file>' on
 * success, or null when nothing was uploaded / something is wrong ($error is
 * set only for real problems, not for "no file chosen").
 */
function save_uploaded_image(array $file, string $folder, ?string &$error = null): ?string {
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
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        $error = 'Please upload a JPG, PNG or WebP image.';
        return null;
    }

    $dir = UPLOAD_ROOT . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $error = 'Could not save the image on the server.';
        return null;
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'Could not save the image on the server.';
        return null;
    }

    return 'uploads/' . $folder . '/' . $name;
}

/** Delete a previously uploaded file. Ignores external links and anything outside uploads/. */
function delete_local_image(?string $path): void {
    $path = trim((string)$path);
    if ($path === '' || preg_match('#^https?://#i', $path)) return;
    if (strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false) return;

    $full = realpath(__DIR__ . '/../' . $path);
    $root = realpath(UPLOAD_ROOT);
    if ($full && $root && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
        @unlink($full);
    }
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

/** Must be a logged-in shop owner. Returns the owner's shop row. */
function require_shop(mysqli $con): array {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'shop') {
        header('Location: ../vendor/vendor_login.php');
        exit;
    }
    $stmt = $con->prepare("SELECT id, shop_name, description, logo_url FROM shops WHERE owner_id = ?");
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