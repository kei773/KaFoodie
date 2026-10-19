<?php
// Numbers for the admin dashboard: summary cards, chart data, top rated lists and featured items.

// A shop or dish counts as "high rated" when it has at least this many ratings
// AND its average is at least HIGH_RATING_AVG. Change them here and everything follows.
const MIN_RATINGS_FOR_TOP = 3;
const HIGH_RATING_AVG     = 4.0;

function admin_summary(mysqli $con): array {
    $customers = (int)$con->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()['c'];
    $row = $con->query(
        "SELECT COUNT(*) AS orders_total,
                COALESCE(SUM(status = 'completed'), 0) AS orders_completed,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) AS sales
         FROM orders"
    )->fetch_assoc();
    return [
        'customers'        => $customers,
        'orders_total'     => (int)$row['orders_total'],
        'orders_completed' => (int)$row['orders_completed'],
        'sales'            => (float)$row['sales'],
    ];
}

/** Orders per day for the last $days days (today included), with zeros for quiet days. */
function admin_orders_last_days(mysqli $con, int $days = 14): array {
    $today = $con->query("SELECT CURDATE() AS d")->fetch_assoc()['d'];

    $stmt = $con->prepare(
        "SELECT DATE(created_at) AS d, COUNT(*) AS c
         FROM orders
         WHERE created_at >= CURDATE() - INTERVAL ? DAY
         GROUP BY DATE(created_at)"
    );
    $back = $days - 1;
    $stmt->bind_param('i', $back);
    $stmt->execute();
    $found = [];
    foreach ($stmt->get_result() as $r) {
        $found[$r['d']] = (int)$r['c'];
    }
    $stmt->close();

    $labels = [];
    $values = [];
    $end = new DateTime($today);
    for ($i = $days - 1; $i >= 0; $i--) {
        $day      = (clone $end)->modify("-$i day");
        $labels[] = $day->format('M j');
        $values[] = $found[$day->format('Y-m-d')] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values];
}

function admin_orders_by_status(mysqli $con): array {
    $counts = ['placed' => 0, 'preparing' => 0, 'ready' => 0, 'completed' => 0];
    $result = $con->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
    foreach ($result as $r) {
        $counts[$r['status']] = (int)$r['c'];
    }
    return $counts;
}

/** How many 1 to 5 star ratings exist: ['shops' => [n1..n5], 'dishes' => [n1..n5]]. */
function admin_rating_distribution(mysqli $con): array {
    $out = ['shops' => [0, 0, 0, 0, 0], 'dishes' => [0, 0, 0, 0, 0]];
    foreach (['shops' => 'shop_ratings', 'dishes' => 'food_ratings'] as $key => $table) {
        $result = $con->query("SELECT stars, COUNT(*) AS c FROM $table GROUP BY stars");
        foreach ($result as $r) {
            $s = (int)$r['stars'];
            if ($s >= 1 && $s <= 5) {
                $out[$key][$s - 1] = (int)$r['c'];
            }
        }
    }
    return $out;
}

/** High rated, approved shops, best first. */
function admin_top_shops(mysqli $con, int $limit = 10): array {
    $min = MIN_RATINGS_FOR_TOP;
    $avg = HIGH_RATING_AVG;
    $stmt = $con->prepare(
        "SELECT s.id, s.shop_name, s.is_featured, r.avg_rating, r.rating_count
         FROM shops s
         JOIN (SELECT shop_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
               FROM shop_ratings GROUP BY shop_id) r ON r.shop_id = s.id
         WHERE s.verification_status = 'approved' AND r.rating_count >= ? AND r.avg_rating >= ?
         ORDER BY r.avg_rating DESC, r.rating_count DESC, s.shop_name
         LIMIT ?"
    );
    $stmt->bind_param('idi', $min, $avg, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** High rated dishes that are still on the menu, from approved shops, best first. */
function admin_top_dishes(mysqli $con, int $limit = 10): array {
    $min = MIN_RATINGS_FOR_TOP;
    $avg = HIGH_RATING_AVG;
    $stmt = $con->prepare(
        "SELECT mi.id, mi.name, mi.is_featured, s.shop_name, r.avg_rating, r.rating_count
         FROM menu_items mi
         JOIN shops s ON s.id = mi.shop_id
         JOIN (SELECT menu_item_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
               FROM food_ratings GROUP BY menu_item_id) r ON r.menu_item_id = mi.id
         WHERE s.verification_status = 'approved' AND mi.is_available = 1
           AND r.rating_count >= ? AND r.avg_rating >= ?
         ORDER BY r.avg_rating DESC, r.rating_count DESC, mi.name
         LIMIT ?"
    );
    $stmt->bind_param('idi', $min, $avg, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Everything currently featured, whether or not it still qualifies (so it can always be removed). */
function admin_featured_shops(mysqli $con): array {
    return $con->query(
        "SELECT s.id, s.shop_name, r.avg_rating, r.rating_count
         FROM shops s
         LEFT JOIN (SELECT shop_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
                    FROM shop_ratings GROUP BY shop_id) r ON r.shop_id = s.id
         WHERE s.is_featured = 1
         ORDER BY s.shop_name"
    )->fetch_all(MYSQLI_ASSOC);
}

function admin_featured_dishes(mysqli $con): array {
    return $con->query(
        "SELECT mi.id, mi.name, s.shop_name, r.avg_rating, r.rating_count
         FROM menu_items mi
         JOIN shops s ON s.id = mi.shop_id
         LEFT JOIN (SELECT menu_item_id, AVG(stars) AS avg_rating, COUNT(*) AS rating_count
                    FROM food_ratings GROUP BY menu_item_id) r ON r.menu_item_id = mi.id
         WHERE mi.is_featured = 1
         ORDER BY mi.name"
    )->fetch_all(MYSQLI_ASSOC);
}

/** Server-side check used before featuring: the item must really be high rated right now. Returns its name or null. */
function admin_qualifying_name(mysqli $con, string $kind, int $id): ?string {
    $min = MIN_RATINGS_FOR_TOP;
    $avg = HIGH_RATING_AVG;
    if ($kind === 'shop') {
        $stmt = $con->prepare(
            "SELECT s.shop_name AS n
             FROM shops s
             JOIN (SELECT shop_id, AVG(stars) AS a, COUNT(*) AS c FROM shop_ratings WHERE shop_id = ? GROUP BY shop_id) r
               ON r.shop_id = s.id
             WHERE s.id = ? AND s.verification_status = 'approved' AND r.c >= ? AND r.a >= ?"
        );
        $stmt->bind_param('iiid', $id, $id, $min, $avg);
    } else {
        $stmt = $con->prepare(
            "SELECT mi.name AS n
             FROM menu_items mi
             JOIN shops s ON s.id = mi.shop_id
             JOIN (SELECT menu_item_id, AVG(stars) AS a, COUNT(*) AS c FROM food_ratings WHERE menu_item_id = ? GROUP BY menu_item_id) r
               ON r.menu_item_id = mi.id
             WHERE mi.id = ? AND mi.is_available = 1 AND s.verification_status = 'approved'
               AND r.c >= ? AND r.a >= ?"
        );
        $stmt->bind_param('iiid', $id, $id, $min, $avg);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['n'] ?? null;
}
