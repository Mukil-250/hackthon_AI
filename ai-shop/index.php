<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'AI Shop - Shop Smarter with AI';
require_once __DIR__ . '/includes/header.php';

// AI recommended: recently viewed products of the logged-in user
$recommended = [];
if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT p.*, MAX(v.viewed_at) AS last_view
         FROM product_views v JOIN products p ON p.id = v.product_id
         WHERE v.user_id = ?
         GROUP BY p.id
         ORDER BY last_view DESC
         LIMIT 8'
    );
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $recommended = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$trending = [];
$res = $conn->query('SELECT * FROM products ORDER BY rating DESC, id ASC LIMIT 8');
if ($res) $trending = $res->fetch_all(MYSQLI_ASSOC);

$categories = [];
$res = $conn->query('SELECT id, name FROM categories ORDER BY id');
if ($res) $categories = $res->fetch_all(MYSQLI_ASSOC);

$catIcons = ['Electronics' => 'bi-phone', 'Fashion' => 'bi-bag', 'Books' => 'bi-book', 'Home' => 'bi-house', 'Sports' => 'bi-trophy'];
?>
<section class="hero text-center mb-4">
    <h1 class="display-5 fw-bold">Shop Smarter with AI</h1>
    <p class="lead mb-4">Smart search, personalised picks and an assistant that helps you find the best deals.</p>
    <a href="products.php" class="btn btn-light btn-lg">Browse Products</a>
</section>

<?php if (isLoggedIn()): ?>
    <h2 class="section-title h4">AI Recommended For You <span class="badge badge-ai">AI</span></h2>
    <?php if ($recommended): ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($recommended as $p): ?>
                <div class="col"><?php include __DIR__ . '/includes/product_card.php'; ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Browse a few products and we'll tailor recommendations for you. Meanwhile, check out our top-rated picks below!</div>
    <?php endif; ?>
<?php endif; ?>

<h2 class="section-title h4">Trending Products</h2>
<div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
    <?php foreach ($trending as $p): ?>
        <div class="col"><?php include __DIR__ . '/includes/product_card.php'; ?></div>
    <?php endforeach; ?>
</div>

<h2 class="section-title h4">Browse Categories</h2>
<div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
    <?php foreach ($categories as $c): ?>
        <div class="col">
            <a href="products.php?cat=<?= (int)$c['id'] ?>" class="text-decoration-none">
                <div class="card category-card text-center h-100">
                    <div class="card-body py-4">
                        <i class="bi <?= h($catIcons[$c['name']] ?? 'bi-tag') ?> fs-1 text-primary"></i>
                        <h6 class="mt-2 mb-0 text-dark"><?= h($c['name']) ?></h6>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
