<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=orders.php');
    exit;
}
$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT id, total, status, address, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$itemStmt = $conn->prepare(
    'SELECT oi.quantity, oi.price, p.id AS product_id, p.name
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.id'
);

$badge = [
    'Pending' => 'warning text-dark', 'Processing' => 'info text-dark', 'Shipped' => 'primary',
    'Delivered' => 'success', 'Cancelled' => 'danger',
];

$pageTitle = 'My Orders - AI Shop';
require_once __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-box-seam me-2"></i>My Orders</h1>

<?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Thank you! Your order has been placed successfully.</div>
<?php endif; ?>

<?php if (!$orders): ?>
    <div class="text-center bg-white rounded-3 shadow-sm p-5">
        <i class="bi bi-inbox display-3 text-muted"></i>
        <h4 class="mt-3">No orders yet</h4>
        <a href="products.php" class="btn btn-primary mt-2">Start Shopping</a>
    </div>
<?php else: foreach ($orders as $o):
    $oid = (int)$o['id'];
    $itemStmt->bind_param('i', $oid);
    $itemStmt->execute();
    $lines = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Order #<?= $oid ?></strong>
                <span class="badge bg-<?= h($badge[$o['status']] ?? 'secondary') ?> ms-2"><?= h($o['status']) ?></span>
            </div>
            <small class="text-muted"><?= h($o['created_at']) ?></small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-3">
                    <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Line total</th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><a href="product.php?id=<?= (int)$l['product_id'] ?>" class="text-decoration-none"><?= h($l['name']) ?></a></td>
                            <td class="text-center"><?= (int)$l['quantity'] ?></td>
                            <td class="text-end"><?= h(fmtPrice($l['price'])) ?></td>
                            <td class="text-end"><?= h(fmtPrice((float)$l['price'] * (int)$l['quantity'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="row">
                <div class="col-md-8"><strong>Shipping address:</strong><br><span class="text-muted"><?= nl2br(h($o['address'])) ?></span></div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0"><span class="text-muted">Total</span><div class="fs-5 fw-bold"><?= h(fmtPrice($o['total'])) ?></div></div>
            </div>
        </div>
    </div>
<?php endforeach; $itemStmt->close(); endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
