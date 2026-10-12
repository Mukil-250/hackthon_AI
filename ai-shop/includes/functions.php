<?php
/**
 * Shared helper functions for AI Shop.
 */

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function h($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Site-relative image path, or a placehold.co fallback if the file is missing. */
function imgUrl($filename, $productName = ''): string
{
    $file = basename((string)$filename);
    if ($file !== '' && is_file(dirname(__DIR__) . '/assets/img/' . $file)) {
        return 'assets/img/' . rawurlencode($file);
    }
    $text = trim((string)$productName) !== '' ? (string)$productName : 'AI Shop';
    $text = function_exists('mb_substr') ? mb_substr($text, 0, 40, 'UTF-8') : substr($text, 0, 40);
    return 'https://placehold.co/600x600/2874f0/ffffff?text=' . rawurlencode($text);
}

function fmtPrice($n): string
{
    $n = (float)$n;
    $decimals = (floor($n) == $n) ? 0 : 2;
    return '₹' . number_format($n, $decimals);
}

/** Send a JSON response and stop. Discards any stray buffered output first. */
function jsonResponse(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Allow only simple local page paths such as "checkout.php" or "products.php?cat=2". */
function safeRedirectTarget($target, string $default = 'index.php'): string
{
    $target = trim((string)$target);
    if ($target !== '' && preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&\-]*)?$/', $target)) {
        return $target;
    }
    return $default;
}

/** Cart lines for the current visitor: [id,name,price,image,stock,quantity]. */
function getCartItems(mysqli $conn): array
{
    $items = [];
    if (isLoggedIn()) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare(
            'SELECT p.id, p.name, p.price, p.image, p.stock, c.quantity
             FROM cart c JOIN products p ON p.id = c.product_id
             WHERE c.user_id = ? ORDER BY c.id'
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    $cart = $_SESSION['guest_cart'] ?? [];
    if (!is_array($cart) || !$cart) return [];
    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), fn($i) => $i > 0));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT id, name, price, image, stock FROM products WHERE id IN ($ph)");
    if (!$stmt) return [];
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['quantity'] = (int)($cart[$row['id']] ?? 0);
        if ($row['quantity'] > 0) $items[] = $row;
    }
    $stmt->close();
    return $items;
}

/** Move the guest session cart into the DB cart (respecting stock), then clear it. */
function migrateGuestCart(mysqli $conn, int $userId): void
{
    $cart = $_SESSION['guest_cart'] ?? [];
    if (is_array($cart) && $cart) {
        $sel = $conn->prepare('SELECT stock FROM products WHERE id = ?');
        $up  = $conn->prepare(
            'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + ?, ?)'
        );
        if ($sel && $up) {
            foreach ($cart as $pid => $qty) {
                $pid = (int)$pid;
                $qty = (int)$qty;
                if ($pid < 1 || $qty < 1) continue;
                $sel->bind_param('i', $pid);
                $sel->execute();
                $row = $sel->get_result()->fetch_assoc();
                if (!$row || (int)$row['stock'] < 1) continue;
                $stock = (int)$row['stock'];
                $q = min($qty, $stock);
                $up->bind_param('iiiii', $userId, $pid, $q, $q, $stock);
                $up->execute();
            }
            $sel->close();
            $up->close();
        }
    }
    unset($_SESSION['guest_cart']);
}

/** Compact array for JSON APIs. */
function productToJson(array $row): array
{
    return [
        'id'        => (int)$row['id'],
        'name'      => (string)$row['name'],
        'price'     => (float)$row['price'],
        'image_url' => imgUrl($row['image'], $row['name']),
    ];
}

function aiTopRated(mysqli $conn, int $limit = 8, array $excludeIds = []): array
{
    $limit = max(1, min(50, $limit));
    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), fn($i) => $i > 0));
    $sql = 'SELECT * FROM products';
    $types = '';
    if ($excludeIds) {
        $sql .= ' WHERE id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $types = str_repeat('i', count($excludeIds));
    }
    $sql .= " ORDER BY rating DESC, created_at DESC, id ASC LIMIT $limit";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') $stmt->bind_param($types, ...$excludeIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Does this user have any view history? */
function aiHasHistory(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM product_views WHERE user_id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $has = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $has;
}

/**
 * Personalized picks: products from the user's most-viewed categories,
 * excluding already-viewed products, ordered by category preference, rating, recency.
 * Tops up with top-rated products if fewer than $limit are found.
 */
function aiPersonalized(mysqli $conn, int $userId, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    if (!aiHasHistory($conn, $userId)) return [];

    $sql = "SELECT t.* FROM (
              SELECT p.*,
                (SELECT COUNT(*) FROM product_views v2
                   JOIN products p2 ON p2.id = v2.product_id
                  WHERE v2.user_id = ? AND p2.category_id = p.category_id) AS pref
              FROM products p
              WHERE p.id NOT IN (SELECT product_id FROM product_views WHERE user_id = ?)
            ) t
            WHERE t.pref > 0
            ORDER BY t.pref DESC, t.rating DESC, t.created_at DESC
            LIMIT $limit";
    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if (count($rows) < $limit) {
        $have = array_map(fn($r) => (int)$r['id'], $rows);
        $rows = array_merge($rows, aiTopRated($conn, $limit - count($rows), $have));
    }
    return $rows;
}
