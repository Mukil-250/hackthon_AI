<?php
define('API_REQUEST', true);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function cartCountNow(mysqli $conn): int
{
    if (isLoggedIn()) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS n FROM cart WHERE user_id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        return $n;
    }
    $cart = $_SESSION['guest_cart'] ?? [];
    return is_array($cart) ? (int)array_sum(array_map('intval', $cart)) : 0;
}

try {
    $action = (string)($_REQUEST['action'] ?? '');

    if ($action === 'count') {
        jsonResponse(['count' => cartCountNow($conn)]);
    }

    if ($action === 'add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        $pid = filter_var($_POST['pid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($_POST['qty'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (!$pid || !$qty) {
            jsonResponse(['success' => false, 'message' => 'Invalid product or quantity'], 400);
        }

        $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ?');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }
        $stock = (int)$row['stock'];
        if ($stock < 1) {
            jsonResponse(['success' => false, 'message' => 'This product is out of stock'], 409);
        }

        if (isLoggedIn()) {
            $uid = (int)$_SESSION['user_id'];
            $q = min($qty, $stock);
            $stmt = $conn->prepare(
                'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + ?, ?)'
            );
            $stmt->bind_param('iiiii', $uid, $pid, $q, $q, $stock);
            if (!$stmt->execute()) {
                jsonResponse(['success' => false, 'message' => 'Could not update cart'], 500);
            }
            $stmt->close();
        } else {
            if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }
            $current = (int)($_SESSION['guest_cart'][$pid] ?? 0);
            $_SESSION['guest_cart'][$pid] = min($current + $qty, $stock);
        }

        jsonResponse(['success' => true, 'count' => cartCountNow($conn)]);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
} catch (Throwable $e) {
    error_log('cart_action error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
