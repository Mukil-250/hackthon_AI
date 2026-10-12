<?php
/**
 * ai-shop — SlotWise Hub Integration Helper
 */

if (!defined('HUB_API_URL')) {
    require_once __DIR__ . '/../config/db.php';
}

function sendOrderToHub(array $order): ?array {
    $payload = [
        'api_key'          => ECOMMERCE_API_KEY,
        'order_id'         => $order['order_id'],
        'customer_id'      => $order['customer_id'] ?? 'GUEST',
        'customer_name'    => $order['customer_name'],
        'customer_phone'   => $order['customer_phone'],
        'customer_email'   => $order['customer_email'] ?? '',
        'delivery_address' => $order['delivery_address'],
        'city'             => $order['city']    ?? 'Tirunelveli',
        'state'            => $order['state']   ?? 'Tamil Nadu',
        'pincode'          => $order['pincode'],
        'latitude'         => (float)($order['latitude']  ?? 8.7139),
        'longitude'        => (float)($order['longitude'] ?? 77.7567),
        'products'         => $order['products']      ?? [],
        'total_amount'     => (float)($order['total_amount'] ?? 0),
        'payment_status'   => $order['payment_status'] ?? 'PAID',
        'priority'         => $order['priority']       ?? 'NORMAL',
    ];

    $ch = curl_init(HUB_API_URL . '/orders/receive.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . ECOMMERCE_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    @file_put_contents(
        $logDir . '/hub_calls.log',
        sprintf("[%s] sendOrderToHub -> %s code=%d\n%s\n---\n",
                date('Y-m-d H:i:s'), HUB_API_URL, $code,
                $err ?: $response),
        FILE_APPEND | LOCK_EX
    );

    if ($response === false) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function getOrderStatusFromHub(string $orderId): ?array {
    $url = HUB_API_URL . '/orders/status.php?order_id=' . urlencode($orderId);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['X-API-KEY: ' . ECOMMERCE_API_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}
