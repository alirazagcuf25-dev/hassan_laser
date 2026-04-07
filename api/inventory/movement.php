<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['item_id', 'movement_type', 'quantity', 'unit_name', 'unit_rate']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

if (!in_array($input['movement_type'], ['purchase', 'sale', 'adjustment', 'return'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid movement type'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $baseQty = convert_to_base($pdo, (int)$input['item_id'], (float)$input['quantity'], $input['unit_name']);

    $itemStmt = $pdo->prepare('SELECT id, current_stock, avg_cost FROM inventory_items WHERE id = ? FOR UPDATE');
    $itemStmt->execute([(int)$input['item_id']]);
    $item = $itemStmt->fetch();
    if (!$item) {
        throw new RuntimeException('Item not found');
    }

    $isSale = $input['movement_type'] === 'sale';
    $newStock = (float)$item['current_stock'] + ($isSale ? -$baseQty : $baseQty);

    if ($newStock < 0) {
        throw new RuntimeException('Insufficient stock');
    }

    $totalAmount = round((float)$input['unit_rate'] * (float)$input['quantity'], 2);

    $move = $pdo->prepare('INSERT INTO stock_movements(item_id, movement_type, movement_date, quantity, unit_name, converted_base_qty, unit_rate, total_amount, reference_type, reference_id, created_by) VALUES(?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)');
    $move->execute([
        (int)$input['item_id'],
        $input['movement_type'],
        (float)$input['quantity'],
        $input['unit_name'],
        $baseQty,
        (float)$input['unit_rate'],
        $totalAmount,
        $input['reference_type'] ?? null,
        $input['reference_id'] ?? null,
        (int)$current['id']
    ]);

    $u = $pdo->prepare('UPDATE inventory_items SET current_stock = ?, avg_cost = CASE WHEN ? = "purchase" THEN ? ELSE avg_cost END WHERE id = ?');
    $u->execute([$newStock, $input['movement_type'], (float)$input['unit_rate'], (int)$input['item_id']]);

    $ledger = $pdo->prepare('INSERT INTO stock_ledger(item_id, tx_date, tx_type, in_qty, out_qty, balance_qty, remarks, source_type, source_id) VALUES(?, NOW(), ?, ?, ?, ?, ?, ?, ?)');
    $ledger->execute([
        (int)$input['item_id'],
        $input['movement_type'],
        $isSale ? 0 : $baseQty,
        $isSale ? $baseQty : 0,
        $newStock,
        $input['remarks'] ?? null,
        'stock_movement',
        (int)$pdo->lastInsertId()
    ]);

    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Stock movement posted', 'new_stock' => $newStock]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
