<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['voucher_type', 'entries']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$allowed = ['cash_receipt', 'cash_payment', 'bank_receipt', 'bank_payment', 'journal'];
if (!in_array($input['voucher_type'], $allowed, true)) {
    json_response(['ok' => false, 'message' => 'Invalid voucher type'], 422);
}

if (!is_array($input['entries']) || count($input['entries']) < 2) {
    json_response(['ok' => false, 'message' => 'At least 2 entries required'], 422);
}

$dr = 0;
$cr = 0;
foreach ($input['entries'] as $entry) {
    if (($entry['entry_type'] ?? '') === 'dr') {
        $dr += (float)($entry['amount'] ?? 0);
    }
    if (($entry['entry_type'] ?? '') === 'cr') {
        $cr += (float)($entry['amount'] ?? 0);
    }
}

if (round($dr, 2) !== round($cr, 2)) {
    json_response(['ok' => false, 'message' => 'Double-entry mismatch. Debit must equal credit.'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $voucherDate = $input['voucher_date'] ?? date('Y-m-d H:i:s');
    $ins = $pdo->prepare('INSERT INTO vouchers(voucher_no, voucher_type, voucher_date, narration, created_by) VALUES("TEMP", ?, ?, ?, ?)');
    $ins->execute([$input['voucher_type'], $voucherDate, $input['narration'] ?? null, (int)$current['id']]);

    $voucherId = (int)$pdo->lastInsertId();
    $voucherNo = next_code('VCH', $voucherId);
    $up = $pdo->prepare('UPDATE vouchers SET voucher_no = ? WHERE id = ?');
    $up->execute([$voucherNo, $voucherId]);

    $entryStmt = $pdo->prepare('INSERT INTO voucher_entries(voucher_id, ledger_id, entry_type, amount, line_narration) VALUES(?, ?, ?, ?, ?)');
    $ledgerTx = $pdo->prepare('INSERT INTO ledger_transactions(ledger_id, tx_date, description, debit, credit, source_type, source_id, created_by) VALUES(?, ?, ?, ?, ?, "voucher", ?, ?)');

    foreach ($input['entries'] as $entry) {
        $entryStmt->execute([
            $voucherId,
            (int)$entry['ledger_id'],
            $entry['entry_type'],
            (float)$entry['amount'],
            $entry['line_narration'] ?? null
        ]);

        $debit = $entry['entry_type'] === 'dr' ? (float)$entry['amount'] : 0;
        $credit = $entry['entry_type'] === 'cr' ? (float)$entry['amount'] : 0;
        $ledgerTx->execute([
            (int)$entry['ledger_id'],
            $voucherDate,
            'Voucher ' . $voucherNo,
            $debit,
            $credit,
            $voucherId,
            (int)$current['id']
        ]);
    }

    $pdo->commit();
    json_response(['ok' => true, 'voucher_no' => $voucherNo]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
