<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pid = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = null;

if ($pid) {
    $stmt = $conn->prepare(
        'SELECT p.*, c.name AS category_name FROM products p
         JOIN categories c ON c.id = p.category_id WHERE p.id = ?'
    );
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($product) {
    // Track the view for AI recommendations
    $uid = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sid = (string)$_SESSION['session_id'];
    $stmt = $conn->prepare('INSERT INTO product_views (user_id, session_id, product_id) VALUES (?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isi', $uid, $sid, $pid);
        $stmt->execute();
        $stmt->close();
    }
    $pageTitle = $product['name'] . ' - AI Shop';
} else {
    http_response_code(404);
    $pageTitle = 'Product not found - AI Shop';
}

require_once __DIR__ . '/includes/header.php';

if (!$product): ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">404 - Product not found</h4>
        <p class="mb-0">The product you are looking for does not exist. <a href="products.php" class="alert-link">Browse all products</a>.</p>
    </div>
<?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
endif;

$id = (int)$product['id'];
$stock = (int)$product['stock'];
$hasOld = $product['old_price'] !== null && (float)$product['old_price'] > (float)$product['price'];
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="products.php?cat=<?= (int)$product['category_id'] ?>"><?= h($product['category_name']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= h($product['name']) ?></li>
    </ol>
</nav>

<div class="row g-4 bg-white rounded-3 shadow-sm p-3">
    <div class="col-md-5">
        <img src="<?= h(imgUrl($product['image'], $product['name'])) ?>" alt="<?= h($product['name']) ?>" class="img-fluid rounded w-100">
    </div>
    <div class="col-md-7">
        <h1 class="h3"><?= h($product['name']) ?></h1>
        <p><span class="rating-badge"><?= h(number_format((float)$product['rating'], 1)) ?> <i class="bi bi-star-fill"></i></span></p>
        <p class="mb-2">
            <span class="price fs-3"><?= h(fmtPrice($product['price'])) ?></span>
            <?php if ($hasOld): ?>
                <span class="old-price fs-6"><?= h(fmtPrice($product['old_price'])) ?></span>
                <span class="badge bg-success ms-1"><?= (int)round((1 - $product['price'] / $product['old_price']) * 100) ?>% off</span>
            <?php endif; ?>
        </p>
        <p class="text-muted"><?= nl2br(h($product['description'])) ?></p>
        <p>
            <?php if ($stock > 10): ?><span class="badge bg-success">In Stock</span>
            <?php elseif ($stock > 0): ?><span class="badge bg-warning text-dark">Only <?= $stock ?> left</span>
            <?php else: ?><span class="badge bg-danger">Out of Stock</span><?php endif; ?>
        </p>
        <?php if ($stock > 0): ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <label for="qty" class="mb-0 me-1">Qty</label>
                <input type="number" id="qty" class="form-control" style="width:90px" min="1" max="<?= $stock ?>" value="1">
                <button type="button" class="btn btn-primary"
                        onclick="addToCart(<?= $id ?>, Math.max(1, parseInt(document.getElementById('qty').value, 10) || 1))">
                    <i class="bi bi-cart-plus"></i> Add to Cart
                </button>
                <a href="cart.php" class="btn btn-outline-primary">Go to Cart</a>
            </div>
        <?php else: ?>
            <a href="cart.php" class="btn btn-outline-primary">Go to Cart</a>
        <?php endif; ?>
    </div>
</div>

<h2 class="section-title h4">Similar Products <span class="badge badge-ai">AI</span></h2>
<div id="similarSection" class="row row-cols-2 row-cols-md-4 g-3">
    <div class="col-12 text-muted">Loading similar products...</div>
</div>

<script>
(function () {
    const box = document.getElementById('similarSection');
    const inr = (n) => '₹' + Number(n).toLocaleString('en-IN');

    function card(item) {
        const col = document.createElement('div');
        col.className = 'col';
        const c = document.createElement('div');
        c.className = 'card product-card h-100';

        const link = document.createElement('a');
        link.href = 'product.php?id=' + encodeURIComponent(item.id);
        const img = document.createElement('img');
        img.className = 'card-img-top';
        img.src = item.image_url;
        img.alt = item.name;
        img.loading = 'lazy';
        link.appendChild(img);

        const body = document.createElement('div');
        body.className = 'card-body d-flex flex-column';
        const title = document.createElement('h6');
        title.className = 'card-title';
        const tl = document.createElement('a');
        tl.href = link.href;
        tl.textContent = item.name;
        title.appendChild(tl);
        const price = document.createElement('div');
        price.className = 'price mb-3';
        price.textContent = inr(item.price);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary btn-sm mt-auto';
        btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to Cart';
        btn.addEventListener('click', () => addToCart(item.id));

        body.append(title, price, btn);
        c.append(link, body);
        col.appendChild(c);
        return col;
    }

    fetch('api/ai_recommend.php?type=similar&id=<?= $id ?>', { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((data) => {
            box.innerHTML = '';
            const items = Array.isArray(data.items) ? data.items.slice(0, 4) : [];
            if (!items.length) {
                const p = document.createElement('div');
                p.className = 'col-12 text-muted';
                p.textContent = 'No similar products found.';
                box.appendChild(p);
                return;
            }
            items.forEach((it) => box.appendChild(card(it)));
        })
        .catch((err) => {
            console.error('Similar products error:', err);
            box.innerHTML = '';
            const p = document.createElement('div');
            p.className = 'col-12 text-muted';
            p.textContent = 'Could not load similar products.';
            box.appendChild(p);
        });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
