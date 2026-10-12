<?php
require_once __DIR__ . '/config/db.php';

// Ensure notifications table exists
@$conn->query("
    CREATE TABLE IF NOT EXISTS customer_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        order_id VARCHAR(50),
        title VARCHAR(200),
        message TEXT,
        type VARCHAR(20) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $conn->query("UPDATE customer_notifications SET is_read=1 WHERE id=$id");
    header('Location: notifications.php');
    exit;
}

// Helper: get products for an order reference like "ORD13" or "LIVE091049"
function getProductsForOrder($conn, $orderRef) {
    if (!$orderRef) return '';

    $names = [];
    $numericId = (int)preg_replace('/[^0-9]/', '', $orderRef);
    if ($numericId <= 0) return '';

    $stmt = $conn->prepare("
        SELECT product_name, quantity
        FROM order_items
        WHERE order_id = ?
        ORDER BY id
        LIMIT 5
    ");

    if ($stmt) {
        $stmt->bind_param('i', $numericId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $names[] = $row['product_name'] . ' x ' . $row['quantity'];
            }
        }
        $stmt->close();
    }

    return implode(', ', $names);
}

$notifs = $conn->query("SELECT * FROM customer_notifications ORDER BY created_at DESC LIMIT 50");
$unread = 0;
if ($notifs && $notifs->num_rows >= 0) {
    $r = $conn->query("SELECT COUNT(*) c FROM customer_notifications WHERE is_read=0");
    $unread = $r ? (int)$r->fetch_assoc()['c'] : 0;
}
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications - Nova Shop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: #f1f5f9; font-family: system-ui; padding: 24px; }
.box { max-width: 720px; margin: 0 auto; }
.notif { background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,.06); border-left: 4px solid #cbd5e1; }
.notif.unread { border-left-color: #2563eb; background: #eff6ff; }
</style>
</head><body>
<div class="box">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">
      <i class="bi bi-bell"></i> Notifications
      <?php if ($unread > 0): ?>
        <span class="badge bg-danger"><?= $unread ?> new</span>
      <?php endif; ?>
    </h2>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Shop
    </a>
  </div>

  <?php if (!$notifs || $notifs->num_rows === 0): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-bell-slash fs-1"></i>
      <p class="mt-3">No notifications yet.</p>
    </div>
  <?php else: ?>
    <?php while ($n = $notifs->fetch_assoc()):
      $titleLower = strtolower(trim($n['title']));
      $showSlotBtn = false;
      if (strpos($titleLower, 'select delivery') !== false
          || strpos($titleLower, 'waiting for slot') !== false) {
          $showSlotBtn = true;
      }
      $productList = getProductsForOrder($conn, $n['order_id']);
      $oid = $n['order_id'];
    ?>
      <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
        <div class="d-flex justify-content-between mb-1">
          <strong><?= htmlspecialchars($n['title']) ?></strong>
          <?php if (!$n['is_read']): ?>
            <span class="badge bg-primary">New</span>
          <?php endif; ?>
        </div>

        <div class="text-muted small"><?= htmlspecialchars($n['message']) ?></div>

        <?php if ($productList !== ''): ?>
          <div class="text-muted small mt-1">
            <i class="bi bi-box-seam"></i>
            <strong>Products:</strong> <?= htmlspecialchars($productList) ?>
          </div>
        <?php endif; ?>

        <div class="text-muted small mt-1">
          <i class="bi bi-clock"></i>
          <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
        </div>

        <?php if ($oid): ?>
          <div class="mt-2 d-flex gap-2">
            <?php if ($showSlotBtn): ?>
              <a href="select_slot.php?order=<?= urlencode($oid) ?>"
                 class="btn btn-primary btn-sm">
                <i class="bi bi-calendar-check"></i> Select Delivery Slot
              </a>
            <?php endif; ?>
            <a href="track.php?order=<?= urlencode($oid) ?>"
               class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-geo-alt"></i> Track Order
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  <?php endif; ?>
</div>
</body></html>