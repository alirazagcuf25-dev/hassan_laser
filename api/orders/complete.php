<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['order_id', 'final_bill_amount']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT o.id, o.order_no, o.customer_party_id, p.phone, p.party_name FROM orders o JOIN parties p ON p.id = o.customer_party_id WHERE o.id = ? FOR UPDATE');
    $stmt->execute([(int)$input['order_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException('Order not found');
    }

    $up = $pdo->prepare('UPDATE orders SET status = "complete", final_bill_amount = ?, final_bill_locked = 1, updated_at = NOW() WHERE id = ?');
    $up->execute([(float)$input['final_bill_amount'], (int)$order['id']]);

    $ledgerStmt = $pdo->prepare('SELECT id FROM ledgers WHERE party_id = ? LIMIT 1');
    $ledgerStmt->execute([(int)$order['customer_party_id']]);
    $ledger = $ledgerStmt->fetch();

    if ($ledger) {
        $tx = $pdo->prepare('INSERT INTO ledger_transactions(ledger_id, order_id, tx_date, description, debit, credit, source_type, source_id, created_by) VALUES(?, ?, NOW(), ?, ?, 0, "final_bill", ?, ?)');
        $tx->execute([
            (int)$ledger['id'],
            (int)$order['id'],
            'Final bill posted for ' . $order['order_no'],
            (float)$input['final_bill_amount'],
            (int)$order['id'],
            (int)$current['id']
        ]);
    }

    $message = sprintf('Dear %s, your order %s is completed. Final bill: Rs %0.2f', $order['party_name'], $order['order_no'], (float)$input['final_bill_amount']);
    $wa = create_whatsapp_link($order['phone'] ?? '', $message);

    $log = $pdo->prepare('INSERT INTO whatsapp_logs(phone, message_body, source_type, source_id, sent_at) VALUES(?, ?, "order_complete", ?, NOW())');
    $log->execute([$order['phone'] ?? '', $message, (int)$order['id']]);

    $pdo->commit();

    json_response(['ok' => true, 'message' => 'Order marked complete', 'whatsapp_link' => $wa]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
