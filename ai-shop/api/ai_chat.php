<?php
define('API_REQUEST', true);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

/** Whole-word / whole-phrase match for any of $words. */
function hasAny(string $text, array $words): bool
{
    $alts = implode('|', array_map(fn($w) => preg_quote($w, '/'), $words));
    return (bool)preg_match('/(?<![a-z0-9])(' . $alts . ')(?![a-z0-9])/u', $text);
}

function listProducts(array $rows, string $intro): string
{
    $lines = [$intro];
    foreach (array_values($rows) as $i => $r) {
        $line = ($i + 1) . '. ' . $r['name'] . ' - ' . fmtPrice($r['price']);
        if (isset($r['old_price']) && $r['old_price'] !== null && (float)$r['old_price'] > (float)$r['price']) {
            $line .= ' (was ' . fmtPrice($r['old_price']) . ', save ' . fmtPrice((float)$r['old_price'] - (float)$r['price']) . ')';
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

function fetchRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['reply' => 'Please send your message using POST.'], 405);
    }
    $raw = trim((string)($_POST['message'] ?? ''));
    if ($raw === '') {
        jsonResponse(['reply' => 'Please type a message and I will help you find products.']);
    }
    $msg = mb_strtolower(mb_substr($raw, 0, 300, 'UTF-8'), 'UTF-8');

    $budgetWords = ['cheap', 'cheapest', 'budget', 'affordable', 'low price'];
    $isBudget = hasAny($msg, $budgetWords);

    // A. Product keyword search
    $keywordMap = [
        'headphone' => 'headphone', 'headphones' => 'headphone', 'phone' => 'smartphone', 'smartphone' => 'smartphone',
        'laptop' => 'laptop', 'shoe' => 'shoe', 'shoes' => 'shoe', 'book' => 'book', 'books' => 'book',
        'watch' => 'watch', 'speaker' => 'speaker', 'tshirt' => 'shirt', 't-shirt' => 'shirt',
        'handbag' => 'handbag', 'lamp' => 'lamp', 'kitchen' => 'kitchen', 'cricket' => 'cricket',
        'yoga' => 'yoga', 'fitness' => 'fitness',
    ];
    $terms = [];
    foreach ($keywordMap as $kw => $term) {
        if (hasAny($msg, [$kw])) $terms[$term] = true;
    }
    $terms = array_keys($terms);

    if ($terms) {
        $conds = [];
        $params = [];
        foreach ($terms as $t) {
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $conds[] = '(name LIKE ? OR tags LIKE ?)';
            array_push($params, $like, $like);
        }
        $order = $isBudget ? 'price ASC, rating DESC' : 'rating DESC, price ASC';
        $rows = fetchRows($conn, 'SELECT * FROM products WHERE ' . implode(' OR ', $conds) . " ORDER BY $order LIMIT 3",
                          str_repeat('s', count($params)), $params);
        if ($rows) {
            jsonResponse(['reply' => listProducts($rows, 'Here are my top picks for "' . implode(', ', $terms) . '":')
                . "\n\nOpen the Products page to see more details."]);
        }
        $cats = array_column(fetchRows($conn, 'SELECT name FROM categories ORDER BY id'), 'name');
        jsonResponse(['reply' => 'Sorry, I could not find "' . implode(', ', $terms) . '" in our store right now. You can browse: ' . implode(', ', $cats) . '.']);
    }

    // B. Recommendation intent
    if (hasAny($msg, ['recommend', 'recommendation', 'recommendations', 'suggest', 'suggestion', 'suggestions'])) {
        $rows = [];
        $intro = 'Here are some top-rated picks you may like:';
        if (isLoggedIn()) {
            $rows = aiPersonalized($conn, (int)$_SESSION['user_id'], 4);
            if ($rows) $intro = 'Based on what you have been browsing, you might like:';
        }
        if (!$rows) $rows = aiTopRated($conn, 4);
        jsonResponse(['reply' => listProducts($rows, $intro)]);
    }

    // C. Budget intent
    if ($isBudget) {
        $rows = fetchRows($conn, 'SELECT * FROM products ORDER BY price ASC, rating DESC LIMIT 4');
        jsonResponse(['reply' => listProducts($rows, 'Our most affordable products:')]);
    }

    // D. Deal intent
    if (hasAny($msg, ['deal', 'deals', 'offer', 'offers', 'discount', 'discounts', 'sale'])) {
        $rows = fetchRows($conn, 'SELECT * FROM products WHERE old_price IS NOT NULL AND old_price > price ORDER BY (old_price - price) DESC LIMIT 4');
        jsonResponse(['reply' => listProducts($rows, 'Best discounts right now:')]);
    }

    // E. Greeting
    if (hasAny($msg, ['hello', 'hi', 'hey'])) {
        jsonResponse(['reply' => "Hello! Welcome to AI Shop.\nYou can ask me things like:\n- \"Show me headphones\"\n- \"Recommend something for me\"\n- \"Any cheap options?\"\n- \"What are the best deals?\""]);
    }

    // F. Fallback
    $cats = array_column(fetchRows($conn, 'SELECT name FROM categories ORDER BY id'), 'name');
    jsonResponse(['reply' => "I'm not sure I understood that, but I can help! We sell: " . implode(', ', $cats)
        . ".\nTry: \"best deals\", \"cheap shoes\", \"recommend a gift\" or \"show me smartwatches\"."]);
} catch (Throwable $e) {
    error_log('ai_chat error: ' . $e->getMessage());
    jsonResponse(['reply' => 'Sorry, something went wrong on my side. Please try again.'], 500);
}
