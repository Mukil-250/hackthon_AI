<?php
define('API_REQUEST', true);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function tagList($tags): array
{
    $out = [];
    foreach (explode(',', mb_strtolower((string)$tags, 'UTF-8')) as $t) {
        $t = trim($t);
        if ($t !== '') $out[$t] = true;
    }
    return array_keys($out);
}

try {
    $type = (string)($_GET['type'] ?? 'similar');

    if ($type === 'similar') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            jsonResponse(['items' => [], 'message' => 'Invalid product id'], 400);
        }

        $stmt = $conn->prepare('SELECT id, category_id, tags FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $cur = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$cur) {
            jsonResponse(['items' => [], 'message' => 'Product not found'], 404);
        }

        $catId = (int)$cur['category_id'];
        $curTags = tagList($cur['tags']);

        $stmt = $conn->prepare('SELECT id, name, price, image, rating, tags FROM products WHERE category_id = ? AND id <> ?');
        $stmt->bind_param('ii', $catId, $id);
        $stmt->execute();
        $cands = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($cands as &$c) {
            $c['_score'] = count(array_intersect($curTags, tagList($c['tags'])));
        }
        unset($c);
        usort($cands, function ($a, $b) {
            return [$b['_score'], (float)$b['rating'], $a['id']] <=> [$a['_score'], (float)$a['rating'], $b['id']];
        });

        jsonResponse(['items' => array_map('productToJson', array_slice($cands, 0, 4))]);
    }

    if ($type === 'personalized') {
        if (!isLoggedIn()) {
            jsonResponse(['items' => [], 'message' => 'Log in to get personalized recommendations']);
        }
        $rows = aiPersonalized($conn, (int)$_SESSION['user_id'], 8);
        jsonResponse(['items' => array_map('productToJson', $rows)]);
    }

    jsonResponse(['items' => [], 'message' => 'Unknown type'], 400);
} catch (Throwable $e) {
    error_log('ai_recommend error: ' . $e->getMessage());
    jsonResponse(['items' => [], 'message' => 'Server error'], 500);
}
