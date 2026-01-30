<?php
require __DIR__ . '/config.php';

// Return product history as JSON
header('Content-Type: application/json');

$productId = (int)($_GET['id'] ?? 0);

if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID required']);
    exit;
}

$history = get_product_history($productId);
echo json_encode($history);
exit;
