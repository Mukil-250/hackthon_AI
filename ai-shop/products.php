<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$catId = filter_var($_GET['cat'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);

$catName = null;
if ($catId) {
    $stmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
    $stmt->bind_param('i', $catId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $catName = $row['name']; else $catId = false;
}

$where = [];
$types = '';
$params = [];
if ($catId) {
    $where[] = 'category_id = ?';
    $types .= 'i';
    $params[] = $catId;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(name LIKE ? OR tags LIKE ? OR description LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sql = 'SELECT * FROM products' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY rating DESC, id ASC';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = ($catName ? $catName . ' - ' : '') . 'Products - AI Shop';
require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <h1 class="h3 mb-0">
        <?php if ($catName): ?>Category: <?= h($catName) ?>
        <?php elseif ($q !== ''): ?>Results for &ldquo;<?= h($q) ?>&rdquo;
        <?php else: ?>All Products<?php endif; ?>
    </h1>
    <?php if ($catName || $q !== ''): ?><a href="products.php" class="btn btn-outline-primary btn-sm">Show all</a><?php endif; ?>
</div>

<?php if ($products): ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php foreach ($products as $p): ?>
            <div class="col"><?php include __DIR__ . '/includes/product_card.php'; ?></div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="bi bi-emoji-frown me-1"></i>No products found. <a href="products.php" class="alert-link">Browse all products</a>.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
