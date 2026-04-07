<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['customer_party_id', 'delivery_date', 'estimate_amount', 'advance_amount']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $c = $pdo->prepare('SELECT id FROM parties WHERE id = ? AND party_type = "customer" LIMIT 1');
    $c->execute([(int)$input['customer_party_id']]);
    if (!$c->fetch()) {
        throw new RuntimeException('Customer not found');
    }

    $stmt = $pdo->prepare('INSERT INTO orders(order_no, customer_party_id, sales_user_id, order_date, delivery_date, status, estimate_amount, advance_amount, notes) VALUES("TEMP", ?, ?, NOW(), ?, "pending", ?, ?, ?)');
    $stmt->execute([
        (int)$input['customer_party_id'],
        (int)$current['id'],
        $input['delivery_date'],
        (float)$input['estimate_amount'],
        (float)$input['advance_amount'],
        $input['notes'] ?? null
    ]);

    $orderId = (int)$pdo->lastInsertId();
    $orderNo = next_code('ORD', $orderId);

    $u = $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?');
    $u->execute([$orderNo, $orderId]);

    $ledgerStmt = $pdo->prepare('SELECT l.id FROM ledgers l JOIN parties p ON p.id = l.party_id WHERE p.id = ? LIMIT 1');
    $ledgerStmt->execute([(int)$input['customer_party_id']]);
    $ledger = $ledgerStmt->fetch();

    if ($ledger && (float)$input['advance_amount'] > 0) {
        $tx = $pdo->prepare('INSERT INTO ledger_transactions(ledger_id, order_id, tx_date, description, debit, credit, source_type, source_id, created_by) VALUES(?, ?, NOW(), ?, 0, ?, "order_advance", ?, ?)');
        $tx->execute([
            (int)$ledger['id'],
            $orderId,
            'Advance payment received against ' . $orderNo,
            (float)$input['advance_amount'],
            $orderId,
            (int)$current['id']
        ]);
    }

    $pdo->commit();

    $printData = [
        'customer_estimate' => 'Customer Estimate Slip - ' . $orderNo,
        'workshop_copy' => 'Workshop/Karkhana Copy - ' . $orderNo
    ];

    json_response([
        'ok' => true,
        'message' => 'Order created with pending status',
        'order_id' => $orderId,
        'order_no' => $orderNo,
        'prints' => $printData
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
