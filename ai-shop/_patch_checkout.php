<?php
/**
 * One-shot patch for checkout.php
 * Adds Hub call after $conn->commit();
 */

$path = __DIR__ . '/checkout.php';

if (!file_exists($path)) {
    echo "❌ checkout.php not found\n";
    exit(1);
}

$content = file_get_contents($path);

// Already patched?
if (strpos($content, 'sendOrderToHub') !== false) {
    echo "✅ Already patched — nothing to do\n";
    exit(0);
}

// The block to insert
$insert = "\n            // Send to Hub\n" .
          "            require_once __DIR__ . '/includes/hub_sender.php';\n" .
          "            sendOrderToHub(['order_id' => 'ORD' . \$orderId, 'customer_name' => \$_SESSION['user_name'] ?? 'Customer', 'customer_phone' => \$phone, 'delivery_address' => \$address, 'pincode' => '627002', 'total' => \$total, 'payment_status' => 'PAID', 'products' => [['product_id' => 'P1', 'name' => 'Order Item', 'qty' => 1, 'price' => \$total, 'weight' => 0.5]]]);\n" .
          "            error_log('Order sent to Hub: ORD' . \$orderId);\n";

// Find the commit line
$needle = '$conn->commit();';
$pos = strpos($content, $needle);

if ($pos === false) {
    echo "❌ Pattern \$conn->commit(); not found\n";
    exit(1);
}

// Insert AFTER the commit
$insertAt = $pos + strlen($needle);
$content = substr($content, 0, $insertAt) . $insert . substr($content, $insertAt);

// Write back
file_put_contents($path, $content);

echo "✅ Patched successfully\n";
