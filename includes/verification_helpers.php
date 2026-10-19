<?php
// Shared settings and helpers for shop document verification.

const VERIFICATION_MAX_BYTES = 5 * 1024 * 1024; // 5 MB per file

// The documents a shop can upload. 'required' ones must be present before submitting.
const VERIFICATION_DOCS = [
    'bir' => [
        'label'    => 'BIR Certificate of Registration',
        'help'     => 'Your business registration from the BIR (Form 2303).',
        'required' => true,
    ],
    'gov_id' => [
        'label'    => 'Owner\'s valid government ID',
        'help'     => 'A clear photo or scan of the front of the ID.',
        'required' => true,
    ],
    'business_permit' => [
        'label'    => 'Business permit',
        'help'     => 'Mayor\'s permit or DTI registration.',
        'required' => false,
    ],
];

// Allowed extensions and the real file types each one may have.
const VERIFICATION_TYPES = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
];

const VERIFICATION_STATUS_LABELS = [
    'not_submitted' => 'Not submitted',
    'pending'       => 'Pending review',
    'approved'      => 'Verified',
    'rejected'      => 'Not approved',
];

function verification_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Folder for one shop's documents. Creates it, and the protection file, if missing.
function verification_ensure_storage(int $shop_id): string {
    $root = dirname(__DIR__) . '/storage';
    $dir  = $root . '/verification/' . $shop_id;

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not create the storage folder.');
    }

    $htaccess = $root . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

function verification_document_path(int $shop_id, string $stored_name): string {
    return dirname(__DIR__) . '/storage/verification/' . $shop_id . '/' . basename($stored_name);
}

// The shop that belongs to this logged-in owner.
function verification_current_shop(mysqli $con, int $user_id): ?array {
    $stmt = $con->prepare(
        "SELECT id, shop_name, verification_status, rejection_reason
         FROM shops WHERE owner_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $shop ?: null;
}

// The shop's uploaded documents, keyed by document type.
function verification_documents(mysqli $con, int $shop_id): array {
    $stmt = $con->prepare(
        "SELECT id, doc_type, original_name, stored_name, mime_type, file_size, uploaded_at
         FROM shop_documents WHERE shop_id = ?"
    );
    $stmt->bind_param('i', $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $docs = [];
    while ($row = $result->fetch_assoc()) {
        $docs[$row['doc_type']] = $row;
    }
    $stmt->close();
    return $docs;
}

// Labels of required documents that are still missing.
function verification_missing_required(array $docs): array {
    $missing = [];
    foreach (VERIFICATION_DOCS as $type => $info) {
        if ($info['required'] && !isset($docs[$type])) {
            $missing[] = $info['label'];
        }
    }
    return $missing;
}

// Documents can only be changed before review or after a rejection.
function verification_is_locked(string $status): bool {
    return in_array($status, ['pending', 'approved'], true);
}

function verification_csrf_token(): string {
    if (empty($_SESSION['verif_csrf'])) {
        $_SESSION['verif_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['verif_csrf'];
}

function verification_csrf_ok(): bool {
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['verif_csrf']) && hash_equals($_SESSION['verif_csrf'], $sent);
}

function verification_format_size(int $bytes): string {
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    return max(1, round($bytes / 1024)) . ' KB';
}