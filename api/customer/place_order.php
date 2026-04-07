<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['customer']);
$input = request_json();
$missing = require_fields($input, ['delivery_date', 'estimate_amount']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();

$partyStmt = $pdo->prepare('SELECT id FROM parties WHERE linked_user_id = ? LIMIT 1');
$partyStmt->execute([(int)$current['id']]);
$party = $partyStmt->fetch();
if (!$party) {
    json_response(['ok' => false, 'message' => 'Customer party not linked. Contact admin.'], 422);
}

$stmt = $pdo->prepare('INSERT INTO orders(order_no, customer_party_id, sales_user_id, order_date, delivery_date, status, estimate_amount, advance_amount, notes) VALUES("TEMP", ?, ?, NOW(), ?, "pending", ?, 0, ?)');
$stmt->execute([
    (int)$party['id'],
    (int)$current['id'],
    $input['delivery_date'],
    (float)$input['estimate_amount'],
    $input['notes'] ?? null
]);

$orderId = (int)$pdo->lastInsertId();
$orderNo = next_code('ORD', $orderId);
$u = $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?');
$u->execute([$orderNo, $orderId]);

json_response(['ok' => true, 'order_no' => $orderNo, 'status' => 'pending']);
