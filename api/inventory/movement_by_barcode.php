<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['barcode', 'movement_type', 'quantity', 'unit_name', 'unit_rate']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();
$itemStmt = $pdo->prepare('SELECT id FROM inventory_items WHERE barcode = ? LIMIT 1');
$itemStmt->execute([trim($input['barcode'])]);
$item = $itemStmt->fetch();

if (!$item) {
    json_response(['ok' => false, 'message' => 'Barcode item not found'], 404);
}

$payload = [
    'item_id' => (int)$item['id'],
    'movement_type' => $input['movement_type'],
    'quantity' => (float)$input['quantity'],
    'unit_name' => $input['unit_name'],
    'unit_rate' => (float)$input['unit_rate'],
    'reference_type' => $input['reference_type'] ?? 'barcode',
    'reference_id' => $input['reference_id'] ?? null
];

$baseQty = convert_to_base($pdo, $payload['item_id'], $payload['quantity'], $payload['unit_name']);
$pdo->beginTransaction();

try {
    $itemLock = $pdo->prepare('SELECT id, current_stock FROM inventory_items WHERE id = ? FOR UPDATE');
    $itemLock->execute([$payload['item_id']]);
    $lockRow = $itemLock->fetch();

    $isSale = $payload['movement_type'] === 'sale';
    $newStock = (float)$lockRow['current_stock'] + ($isSale ? -$baseQty : $baseQty);
    if ($newStock < 0) {
        throw new RuntimeException('Insufficient stock');
    }

    $totalAmount = round($payload['quantity'] * $payload['unit_rate'], 2);
    $move = $pdo->prepare('INSERT INTO stock_movements(item_id, movement_type, movement_date, quantity, unit_name, converted_base_qty, unit_rate, total_amount, reference_type, reference_id, created_by) VALUES(?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)');
    $move->execute([
        $payload['item_id'],
        $payload['movement_type'],
        $payload['quantity'],
        $payload['unit_name'],
        $baseQty,
        $payload['unit_rate'],
        $totalAmount,
        $payload['reference_type'],
        $payload['reference_id'],
        (int)$current['id']
    ]);

    $u = $pdo->prepare('UPDATE inventory_items SET current_stock = ? WHERE id = ?');
    $u->execute([$newStock, $payload['item_id']]);

    $sl = $pdo->prepare('INSERT INTO stock_ledger(item_id, tx_date, tx_type, in_qty, out_qty, balance_qty, remarks, source_type, source_id) VALUES(?, NOW(), ?, ?, ?, ?, ?, ?, ?)');
    $sl->execute([
        $payload['item_id'],
        $payload['movement_type'],
        $isSale ? 0 : $baseQty,
        $isSale ? $baseQty : 0,
        $newStock,
        'Barcode scanner entry',
        'stock_movement',
        (int)$pdo->lastInsertId()
    ]);

    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Barcode movement posted', 'new_stock' => $newStock]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
