<?php
/** Expects $p (product row). */
$pid = (int)$p['id'];
$hasOld = isset($p['old_price']) && $p['old_price'] !== null && (float)$p['old_price'] > (float)$p['price'];
$inStock = !isset($p['stock']) || (int)$p['stock'] > 0;
?>
<div class="card product-card h-100">
    <a href="product.php?id=<?= $pid ?>">
        <img src="<?= h(imgUrl($p['image'], $p['name'])) ?>" class="card-img-top" alt="<?= h($p['name']) ?>" loading="lazy">
    </a>
    <div class="card-body d-flex flex-column">
        <h6 class="card-title"><a href="product.php?id=<?= $pid ?>"><?= h($p['name']) ?></a></h6>
        <div class="mb-2"><span class="rating-badge"><?= h(number_format((float)$p['rating'], 1)) ?> <i class="bi bi-star-fill"></i></span></div>
        <div class="mb-3">
            <span class="price"><?= h(fmtPrice($p['price'])) ?></span>
            <?php if ($hasOld): ?><span class="old-price"><?= h(fmtPrice($p['old_price'])) ?></span><?php endif; ?>
        </div>
        <?php if ($inStock): ?>
            <button type="button" class="btn btn-primary btn-sm mt-auto" onclick="addToCart(<?= $pid ?>)">
                <i class="bi bi-cart-plus"></i> Add to Cart
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm mt-auto" disabled>Out of Stock</button>
        <?php endif; ?>
    </div>
</div>
