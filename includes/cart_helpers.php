<?php
// Reads the cart from the session and checks every line against the database.
// Prices always come from the database, never from the browser.
// Requires includes/helpers.php (for dish_src) to be loaded by the calling page.

/**
 * Returns ['shop_id', 'shop_name', 'owner_id', 'items' => [...], 'total'].
 * 'items' is empty when the cart is empty, the shop is not approved, or nothing in it is still available.
 */
function kf_load_cart(mysqli $con): array {
    $out  = ['shop_id' => 0, 'shop_name' => '', 'owner_id' => 0, 'items' => [], 'total' => 0.0];
    $cart = $_SESSION['cart'] ?? ['shop_id' => null, 'items' => []];

    if (empty($cart['items']) || empty($cart['shop_id'])) {
        return $out;
    }
    $shop_id = (int)$cart['shop_id'];

    // Customers can only order from shops the admin has approved
    $stmt = $con->prepare("SELECT shop_name, owner_id FROM shops WHERE id = ? AND verification_status = 'approved'");
    $stmt->bind_param('i', $shop_id);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$shop) {
        return $out;
    }

    $item_ids = array_map('intval', array_keys($cart['items']));
    $ph       = implode(',', array_fill(0, count($item_ids), '?'));
    $stmt = $con->prepare(
        "SELECT id, name, price, image_url FROM menu_items
         WHERE shop_id = ? AND is_available = 1 AND id IN ($ph)"
    );
    $stmt->bind_param('i' . str_repeat('i', count($item_ids)), $shop_id, ...$item_ids);
    $stmt->execute();
    $found = [];
    foreach ($stmt->get_result() as $row) {
        $found[(int)$row['id']] = $row;
    }
    $stmt->close();

    foreach ($cart['items'] as $id => $qty) {
        $id  = (int)$id;
        $qty = (int)$qty;
        if ($qty < 1 || !isset($found[$id])) {
            continue;
        }
        $price    = (float)$found[$id]['price'];
        $subtotal = round($price * $qty, 2);
        $out['total'] += $subtotal;
        $out['items'][] = [
            'id'       => $id,
            'name'     => $found[$id]['name'],
            'image'    => dish_src($found[$id], '../'),
            'price'    => $price,
            'quantity' => $qty,
            'subtotal' => $subtotal,
        ];
    }

    if ($out['items']) {
        $out['shop_id']   = $shop_id;
        $out['shop_name'] = $shop['shop_name'];
        $out['owner_id']  = (int)$shop['owner_id'];
        $out['total']     = round($out['total'], 2);
    }
    return $out;
}
