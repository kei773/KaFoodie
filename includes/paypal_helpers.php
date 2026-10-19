<?php
// Talks to the PayPal SANDBOX (never the live PayPal) using cURL.

const PAYPAL_API = 'https://api-m.sandbox.paypal.com';

/** The settings, or null when PayPal is not set up yet (the PayPal option is then switched off). */
function paypal_config(): ?array {
    $file = __DIR__ . '/paypal_config.php';
    if (!is_file($file)) {
        return null;
    }
    $cfg = require $file;
    if (!is_array($cfg) || trim((string)($cfg['client_id'] ?? '')) === '' || trim((string)($cfg['secret'] ?? '')) === '') {
        return null;
    }
    $cfg['client_id'] = trim($cfg['client_id']);
    $cfg['secret']    = trim($cfg['secret']);
    $cfg['currency']  = strtoupper(trim((string)($cfg['currency'] ?? 'PHP'))) ?: 'PHP';
    $cfg['ca_bundle'] = trim((string)($cfg['ca_bundle'] ?? ''));
    return $cfg;
}

/** Send a JSON answer and stop. Used by the two PayPal endpoints the checkout page calls. */
function paypal_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** One HTTPS call. Returns the decoded answer, or null with a readable reason in $error. */
function paypal_http(array $cfg, string $method, string $path, array $headers, ?string $body, ?string &$error = null): ?array {
    $error = null;
    $ch = curl_init(PAYPAL_API . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cfg['ca_bundle'] !== '') {
        curl_setopt($ch, CURLOPT_CAINFO, $cfg['ca_bundle']);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = 'Could not reach PayPal (' . curl_error($ch) . ').';
        curl_close($ch);
        return null;
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $error = 'PayPal sent an unreadable answer (HTTP ' . $status . ').';
        return null;
    }
    if ($status >= 400) {
        $issue  = $data['details'][0]['issue'] ?? '';
        $detail = $data['details'][0]['description'] ?? ($data['message'] ?? ($data['error_description'] ?? 'PayPal error'));
        $error  = trim(($issue !== '' ? $issue . ': ' : '') . $detail);
        return null;
    }
    return $data;
}

function paypal_access_token(array $cfg, ?string &$error = null): ?string {
    $res = paypal_http($cfg, 'POST', '/v1/oauth2/token', [
        'Authorization: Basic ' . base64_encode($cfg['client_id'] . ':' . $cfg['secret']),
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], 'grant_type=client_credentials', $error);
    return $res['access_token'] ?? null;
}

/** Creates the PayPal order for $total (a string like "329.00"). Returns PayPal's order id. */
function paypal_create_order(array $cfg, string $total, string $description, ?string &$error = null): ?string {
    $token = paypal_access_token($cfg, $error);
    if ($token === null) {
        return null;
    }
    $body = json_encode([
        'intent'         => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'kafoodie-cart',
            'description'  => mb_substr($description, 0, 120),
            'amount'       => ['currency_code' => $cfg['currency'], 'value' => $total],
        ]],
    ]);
    $res = paypal_http($cfg, 'POST', '/v2/checkout/orders', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'PayPal-Request-Id: ' . bin2hex(random_bytes(16)),
    ], $body, $error);
    return $res['id'] ?? null;
}

/** Takes the money once the customer has approved it in the PayPal window. Returns PayPal's full answer. */
function paypal_capture_order(array $cfg, string $paypal_order_id, ?string &$error = null): ?array {
    $token = paypal_access_token($cfg, $error);
    if ($token === null) {
        return null;
    }
    return paypal_http($cfg, 'POST', '/v2/checkout/orders/' . rawurlencode($paypal_order_id) . '/capture', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        // Same id on a retry returns the first result instead of charging twice
        'PayPal-Request-Id: kafoodie-capture-' . $paypal_order_id,
    ], '{}', $error);
}
