<?php
define('API_REQUEST', true);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') < 2) {
        jsonResponse(['items' => []]);
    }
    $q = mb_substr(mb_strtolower($q, 'UTF-8'), 0, 100, 'UTF-8');

    $parts = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $words = [];
    foreach ($parts as $w) {
        if (mb_strlen($w, 'UTF-8') < 2) continue;
        $words[$w] = true;
        // simple plural handling: "shoes" also matches "shoe"
        if (mb_strlen($w, 'UTF-8') > 3 && mb_substr($w, -1, 1, 'UTF-8') === 's') {
            $words[mb_substr($w, 0, -1, 'UTF-8')] = true;
        }
    }
    $words = array_slice(array_keys($words), 0, 8);
    if (!$words) {
        jsonResponse(['items' => []]);
    }

    $conds = [];
    $params = [];
    foreach ($words as $w) {
        $like = '%' . addcslashes($w, '%_\\') . '%';
        $conds[] = '(name LIKE ? OR tags LIKE ? OR description LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $sql = 'SELECT id, name, description, price, image, rating, tags FROM products WHERE '
         . implode(' OR ', $conds) . ' LIMIT 100';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $name = mb_strtolower($r['name'], 'UTF-8');
        $tags = mb_strtolower($r['tags'], 'UTF-8');
        $desc = mb_strtolower($r['description'], 'UTF-8');
        $score = 0;
        foreach ($words as $w) {
            if (mb_strpos($name, $w) !== false) $score += 10;
            if (mb_strpos($tags, $w) !== false) $score += 5;
            if (mb_strpos($desc, $w) !== false) $score += 2;
        }
        if (mb_strpos($name, $q) !== false) $score += 15; // whole phrase in the name
        $r['_score'] = $score;
    }
    unset($r);

    usort($rows, function ($a, $b) {
        return [$b['_score'], (float)$b['rating']] <=> [$a['_score'], (float)$a['rating']];
    });

    jsonResponse(['items' => array_map('productToJson', array_slice($rows, 0, 6))]);
} catch (Throwable $e) {
    error_log('ai_search error: ' . $e->getMessage());
    jsonResponse(['items' => [], 'message' => 'Server error'], 500);
}
