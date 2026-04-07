<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['order_id', 'status']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$allowed = ['pending', 'in_process', 'complete', 'on_way', 'delivered'];
if (!in_array($input['status'], $allowed, true)) {
    json_response(['ok' => false, 'message' => 'Invalid status'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $q = $pdo->prepare('SELECT id, order_no, status, customer_party_id, transporter_party_id FROM orders WHERE id = ? FOR UPDATE');
    $q->execute([(int)$input['order_id']]);
    $order = $q->fetch();

    if (!$order) {
        throw new RuntimeException('Order not found');
    }

    $newStatus = $input['status'];

    $u = $pdo->prepare('UPDATE orders SET status = ?, transporter_party_id = ?, updated_at = NOW() WHERE id = ?');
    $transporter = $order['transporter_party_id'];

    if ($newStatus === 'on_way' && !empty($input['transporter_party_id'])) {
        $transporter = (int)$input['transporter_party_id'];
    }

    if ($newStatus === 'delivered') {
        $transporter = null;
    }

    $u->execute([$newStatus, $transporter, (int)$order['id']]);

    if ($newStatus === 'delivered' && !empty($input['received_amount'])) {
        $ledgerStmt = $pdo->prepare('SELECT l.id FROM ledgers l WHERE l.party_id = ? LIMIT 1');
        $ledgerStmt->execute([(int)$order['customer_party_id']]);
        $ledger = $ledgerStmt->fetch();

        if ($ledger) {
            $lt = $pdo->prepare('INSERT INTO ledger_transactions(ledger_id, order_id, tx_date, description, debit, credit, source_type, source_id, created_by) VALUES(?, ?, NOW(), ?, 0, ?, "delivery_payment", ?, ?)');
            $lt->execute([
                (int)$ledger['id'],
                (int)$order['id'],
                'Delivered payment for ' . $order['order_no'],
                (float)$input['received_amount'],
                (int)$order['id'],
                (int)$current['id']
            ]);
        }
    }

    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Status updated', 'order_id' => (int)$order['id']]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
