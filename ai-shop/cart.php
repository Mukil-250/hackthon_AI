<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

function productStock(mysqli $conn, int $pid): ?int
{
    $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['stock'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $pid = filter_var($_POST['pid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $msg = '';

    if ($pid && in_array($action, ['update', 'remove'], true)) {
        $uid = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

        if ($action === 'remove') {
            if ($uid) {
                $stmt = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?');
                $stmt->bind_param('ii', $uid, $pid);
                $stmt->execute();
                $stmt->close();
            } else {
                unset($_SESSION['guest_cart'][$pid]);
            }
            $msg = 'removed';
        } else {
            $qty = filter_var($_POST['qty'] ?? null, FILTER_VALIDATE_INT);
            $stock = productStock($conn, $pid);
            if ($qty === false || $qty === null || $stock === null) {
                $msg = 'invalid';
            } elseif ($qty < 1) {
                if ($uid) {
                    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?');
                    $stmt->bind_param('ii', $uid, $pid);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    unset($_SESSION['guest_cart'][$pid]);
                }
                $msg = 'removed';
            } else {
                $final = min($qty, $stock);
                if ($final < 1) {
                    $msg = 'stock';
                } else {
                    if ($uid) {
                        $stmt = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
                        $stmt->bind_param('iii', $final, $uid, $pid);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $_SESSION['guest_cart'][$pid] = $final;
                    }
                    $msg = ($final < $qty) ? 'stock' : 'updated';
                }
            }
        }
    }
    header('Location: cart.php' . ($msg ? '?msg=' . $msg : ''));
    exit;
}

$messages = [
    'updated' => ['success', 'Cart updated.'],
    'removed' => ['info', 'Item removed from your cart.'],
    'stock'   => ['warning', 'Quantity was adjusted to the available stock.'],
    'invalid' => ['danger', 'Invalid quantity.'],
    'empty'   => ['warning', 'Your cart is empty. Add some products before checking out.'],
];
$flash = $messages[$_GET['msg'] ?? ''] ?? null;

$items = getCartItems($conn);
$subtotal = 0.0;
foreach ($items as $it) {
    $subtotal += (float)$it['price'] * (int)$it['quantity'];
}

$pageTitle = 'Your Cart - AI Shop';
require_once __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-cart3 me-2"></i>Shopping Cart</h1>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash[0]) ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

<?php if (!$items): ?>
    <div class="text-center bg-white rounded-3 shadow-sm p-5">
        <i class="bi bi-cart-x display-3 text-muted"></i>
        <h4 class="mt-3">Your cart is empty</h4>
        <p class="text-muted">Looks like you haven't added anything yet.</p>
        <a href="products.php" class="btn btn-primary">Shop Now</a>
    </div>
<?php else: ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?php foreach ($items as $it):
            $pid = (int)$it['id'];
            $line = (float)$it['price'] * (int)$it['quantity'];
        ?>
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-3 col-md-2">
                            <img src="<?= h(imgUrl($it['image'], $it['name'])) ?>" class="cart-thumb w-100" alt="<?= h($it['name']) ?>">
                        </div>
                        <div class="col-9 col-md-4">
                            <a href="product.php?id=<?= $pid ?>" class="text-decoration-none text-dark fw-semibold"><?= h($it['name']) ?></a>
                            <div class="text-muted small"><?= h(fmtPrice($it['price'])) ?> each</div>
                            <?php if ((int)$it['stock'] < (int)$it['quantity']): ?>
                                <div class="text-danger small">Only <?= (int)$it['stock'] ?> in stock</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <form method="post" action="cart.php" class="d-flex gap-1">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="pid" value="<?= $pid ?>">
                                <input type="number" name="qty" class="form-control form-control-sm" min="1" max="<?= max(1, (int)$it['stock']) ?>" value="<?= (int)$it['quantity'] ?>" aria-label="Quantity">
                                <button class="btn btn-outline-primary btn-sm" type="submit">Update</button>
                            </form>
                        </div>
                        <div class="col-6 col-md-3 text-end">
                            <div class="fw-bold mb-1"><?= h(fmtPrice($line)) ?></div>
                            <form method="post" action="cart.php">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="pid" value="<?= $pid ?>">
                                <button class="btn btn-link text-danger btn-sm p-0" type="submit"><i class="bi bi-trash"></i> Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Order Summary</h5>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= h(fmtPrice($subtotal)) ?></span></div>
                <div class="d-flex justify-content-between"><span>Shipping</span><span class="text-success">FREE</span></div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= h(fmtPrice($subtotal)) ?></span></div>
                <a href="checkout.php" class="btn btn-primary w-100 mt-3">Proceed to Checkout</a>
                <?php if (!isLoggedIn()): ?>
                    <p class="small text-muted mt-2 mb-0">You'll be asked to log in or sign up at checkout. Your cart will be saved.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
