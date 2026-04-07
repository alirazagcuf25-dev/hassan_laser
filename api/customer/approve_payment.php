<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['verification_id', 'status']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

if (!in_array($input['status'], ['approved', 'rejected'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid status'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT * FROM payment_verifications WHERE id = ? FOR UPDATE');
    $stmt->execute([(int)$input['verification_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('Verification record not found');
    }

    $u = $pdo->prepare('UPDATE payment_verifications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? WHERE id = ?');
    $u->execute([$input['status'], (int)$current['id'], $input['remarks'] ?? null, (int)$row['id']]);

    if ($input['status'] === 'approved') {
        $ledgerStmt = $pdo->prepare('SELECT id FROM ledgers WHERE party_id = ? LIMIT 1');
        $ledgerStmt->execute([(int)$row['customer_party_id']]);
        $ledger = $ledgerStmt->fetch();

        if ($ledger) {
            $tx = $pdo->prepare('INSERT INTO ledger_transactions(ledger_id, order_id, tx_date, description, debit, credit, source_type, source_id, created_by) VALUES(?, ?, NOW(), ?, 0, ?, "payment_verification", ?, ?)');
            $tx->execute([
                (int)$ledger['id'],
                (int)$row['order_id'],
                'Verified payment screenshot approval',
                (float)$row['amount'],
                (int)$row['id'],
                (int)$current['id']
            ]);
        }
    }

    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Verification updated']);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
