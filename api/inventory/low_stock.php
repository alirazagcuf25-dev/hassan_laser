<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_roles(['admin', 'staff']);
$pdo = db();

$frequency = $_GET['frequency'] ?? 'daily';
if (!in_array($frequency, ['daily', 'weekly'], true)) {
    $frequency = 'daily';
}

$stmt = $pdo->prepare('SELECT id, item_name, sales_unit, minimum_stock, current_stock FROM inventory_items WHERE current_stock <= minimum_stock AND is_active = 1');
$stmt->execute();
$items = $stmt->fetchAll();

$created = [];
foreach ($items as $item) {
    $po = $pdo->prepare('INSERT INTO purchase_orders(po_no, item_id, suggested_qty, unit_name, generated_on, generated_frequency) VALUES("TEMP", ?, ?, ?, CURDATE(), ?)');
    $suggested = max(1, (float)$item['minimum_stock'] - (float)$item['current_stock']);
    $po->execute([(int)$item['id'], $suggested, $item['sales_unit'], $frequency]);

    $id = (int)$pdo->lastInsertId();
    $poNo = next_code('PO', $id);
    $update = $pdo->prepare('UPDATE purchase_orders SET po_no = ? WHERE id = ?');
    $update->execute([$poNo, $id]);

    $created[] = ['po_no' => $poNo, 'item' => $item['item_name'], 'suggested_qty' => $suggested];
}

json_response(['ok' => true, 'generated' => $created, 'count' => count($created)]);
