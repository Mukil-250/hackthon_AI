<?php
/**
 * ai-shop — Receives status updates FROM the SlotWise Hub.
 * File: api/hub-callback.php
 *
 * The Hub calls this endpoint whenever order status changes.
 */

header('Content-Type: application/json');

// Read raw JSON
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Verify API key from header or body
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($body['api_key'] ?? '');
$expectedKey = 'ecom_secret_change_me_2026';

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$orderId    = $body['order_id']    ?? '';
$status     = $body['status']      ?? '';
$desc       = $body['description'] ?? '';
$trackingId = $body['tracking_id'] ?? '';

if (!$orderId || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id or status']);
    exit;
}

// Update ai-shop database
require_once __DIR__ . '/../config/db.php';

// Add columns if not present (safe, idempotent)
@$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS hub_status VARCHAR(50) NULL");
@$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS hub_note VARCHAR(255) NULL");
@$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_id VARCHAR(50) NULL");
@$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS hub_updated_at TIMESTAMP NULL");

// Detect order column name (order_id vs id)
$check = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_id'");
$isOrderIdStr = ($check && $check->num_rows > 0);

$column = $isOrderIdStr ? 'order_id' : 'id';

$stmt = $conn->prepare("
    UPDATE orders
    SET hub_status = ?, hub_note = ?, tracking_id = ?, hub_updated_at = NOW()
    WHERE $column = ?
");
$stmt->bind_param('ssss', $status, $desc, $trackingId, $orderId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

// Log for debugging
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
@file_put_contents(
    $logDir . '/hub_callback.log',
    sprintf("[%s] order=%s status=%s rows=%d\n",
            date('Y-m-d H:i:s'), $orderId, $status, $affected),
    FILE_APPEND | LOCK_EX
);

echo json_encode([
    'success'  => true,
    'order_id' => $orderId,
    'status'   => $status,
    'updated'  => $affected,
]);
