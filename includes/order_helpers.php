<?php
// Shared by the customer and shop order pages (pickup only, no delivery).

const ORDER_FLOW = ['placed', 'preparing', 'ready', 'completed'];

const ORDER_STATUS_LABELS = [
    'placed'    => 'Order placed',
    'preparing' => 'Preparing',
    'ready'     => 'Ready for pickup',
    'completed' => 'Completed',
];

// Short labels for the progress tracker
const ORDER_STEP_LABELS = [
    'placed'    => 'Placed',
    'preparing' => 'Preparing',
    'ready'     => 'Ready',
    'completed' => 'Completed',
];

// What a shop can move an order to, and the button text for it
const ORDER_NEXT_STATUS = ['placed' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'];
const ORDER_NEXT_LABEL  = [
    'placed'    => 'Start preparing',
    'preparing' => 'Mark ready for pickup',
    'ready'     => 'Mark completed',
];

const ORDER_PAYMENT_LABELS = [
    'pay_at_shop'    => 'Pay at the shop',
    'paypal_sandbox' => 'PayPal',
];

function order_status_label(string $status): string {
    return ORDER_STATUS_LABELS[$status] ?? $status;
}

/** All items of the given orders, grouped by order id: [order_id => [rows...]]. */
function order_items_by_order(mysqli $con, array $order_ids): array {
    $by_order = [];
    if (!$order_ids) {
        return $by_order;
    }
    $order_ids = array_map('intval', $order_ids);
    $ph   = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $con->prepare(
        "SELECT oi.id, oi.order_id, oi.menu_item_id, oi.item_name, oi.quantity, oi.unit_price, mi.image_url
         FROM order_items oi
         LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
         WHERE oi.order_id IN ($ph)
         ORDER BY oi.id"
    );
    $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $by_order[(int)$row['order_id']][] = $row;
    }
    $stmt->close();
    return $by_order;
}

/** Save a notification. A failure here must never break the order, so it is swallowed. */
function order_notify(mysqli $con, int $user_id, int $order_id, string $message): void {
    try {
        $message = mb_substr($message, 0, 255);
        $stmt = $con->prepare("INSERT INTO notifications (user_id, order_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $user_id, $order_id, $message);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('order_notify failed: ' . $e->getMessage());
    }
}

/**
 * Email the customer that their order is ready for pickup.
 * Never throws: if the email cannot be sent, the order and the bell message are unaffected.
 */
function order_send_ready_email(mysqli $con, int $order_id): void {
    try {
        $stmt = $con->prepare(
            "SELECT o.id, o.total_amount, o.payment_method, o.payment_status,
                    u.name AS customer_name, u.email AS customer_email, s.shop_name
             FROM orders o
             JOIN users u ON u.id = o.customer_id
             JOIN shops s ON s.id = o.shop_id
             WHERE o.id = ?"
        );
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $o = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$o || trim((string)$o['customer_email']) === '') {
            return;
        }

        $esc   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $items = order_items_by_order($con, [$order_id])[$order_id] ?? [];

        $rows = '';
        foreach ($items as $it) {
            $name  = $it['item_name'] !== '' ? $it['item_name'] : 'Dish';
            $rows .= '<tr><td style="padding:6px 0;">' . $esc($name) . '</td>'
                   . '<td style="padding:6px 0;text-align:right;white-space:nowrap;">'
                   . (int)$it['quantity'] . ' × ₱' . number_format((float)$it['unit_price'], 2) . '</td></tr>';
        }

        $first = explode(' ', trim((string)$o['customer_name']))[0] ?: 'there';
        $pay   = ($o['payment_method'] === 'pay_at_shop' && $o['payment_status'] !== 'paid')
               ? '<p>Please pay at the shop when you pick it up.</p>' : '';

        $content =
            '<p>Hi ' . $esc($first) . ',</p>'
          . '<p>Good news! Your order <strong>#' . (int)$o['id'] . '</strong> from <strong>'
          . $esc($o['shop_name']) . '</strong> is ready for pickup.</p>'
          . '<table style="width:100%;border-collapse:collapse;border-top:1px solid #ECD9C9;">' . $rows . '</table>'
          . '<p style="margin:12px 0 0;font-weight:700;">Total: ₱' . number_format((float)$o['total_amount'], 2) . '</p>'
          . $pay
          . '<p>Tell the shop your order number when you collect it.</p>';

        require_once __DIR__ . '/mailer.php';
        $error = null;
        $ok = send_mail(
            $o['customer_email'], (string)$o['customer_name'],
            'Your order #' . (int)$o['id'] . ' is ready for pickup',
            mail_layout('Your order is ready', $content),
            $error
        );
        if (!$ok) {
            error_log('Ready email for order #' . $order_id . ' failed: ' . $error);
        }
    } catch (Throwable $e) {
        error_log('order_send_ready_email failed: ' . $e->getMessage());
    }
}

/**
 * Saves an order and all its items in one transaction and returns the new order id.
 * Throws if anything fails (nothing is saved in that case).
 * $cart comes from kf_load_cart(), so prices and dish names are the database's, never the browser's.
 */
function order_create(mysqli $con, int $customer_id, array $cart, string $payment_method,
                      string $payment_status, ?string $transaction_id = null): int {
    $shop_id = (int)$cart['shop_id'];
    $total   = number_format((float)$cart['total'], 2, '.', '');

    try {
        $con->begin_transaction();

        $stmt = $con->prepare(
            "INSERT INTO orders (customer_id, shop_id, status, payment_method, payment_status, transaction_id, total_amount)
             VALUES (?, ?, 'placed', ?, ?, ?, ?)"
        );
        $stmt->bind_param('iissss', $customer_id, $shop_id, $payment_method, $payment_status, $transaction_id, $total);
        $stmt->execute();
        $order_id = (int)$con->insert_id;
        $stmt->close();

        $stmt = $con->prepare(
            "INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($cart['items'] as $item) {
            $menu_item_id = (int)$item['id'];
            $name         = mb_substr($item['name'], 0, 150);
            $qty          = (int)$item['quantity'];
            $unit_price   = number_format((float)$item['price'], 2, '.', '');
            $stmt->bind_param('iisis', $order_id, $menu_item_id, $name, $qty, $unit_price);
            $stmt->execute();
        }
        $stmt->close();

        $con->commit();
        return $order_id;
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }
}
