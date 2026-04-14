<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_login();
$pdo = db();

$partyId = (int)($_GET['party_id'] ?? 0);
if ($partyId <= 0) {
    json_response(['ok' => false, 'message' => 'party_id required'], 422);
}

$partyStmt = $pdo->prepare('SELECT id, party_name, party_code, phone, party_type FROM parties WHERE id = ? LIMIT 1');
$partyStmt->execute([$partyId]);
$party = $partyStmt->fetch();
if (!$party) {
    json_response(['ok' => false, 'message' => 'Party not found'], 404);
}

$ledgerStmt = $pdo->prepare('SELECT id FROM ledgers WHERE party_id = ? LIMIT 1');
$ledgerStmt->execute([$partyId]);
$ledger = $ledgerStmt->fetch();

if (!$ledger) {
    json_response(['ok' => true, 'party' => $party, 'rows' => [], 'total_debit' => 0, 'total_credit' => 0, 'balance' => 0]);
}

$ledgerId = (int)$ledger['id'];

$txStmt = $pdo->prepare(
    'SELECT lt.id, lt.tx_date, lt.description, lt.debit, lt.credit, lt.source_type, lt.source_id
     FROM ledger_transactions lt
     WHERE lt.ledger_id = ?
     ORDER BY lt.tx_date ASC, lt.id ASC'
);
$txStmt->execute([$ledgerId]);
$rows = $txStmt->fetchAll();

$totalDebit = 0;
$totalCredit = 0;
$running = 0;

foreach ($rows as &$row) {
    $totalDebit += (float)$row['debit'];
    $totalCredit += (float)$row['credit'];
    $running += (float)$row['debit'] - (float)$row['credit'];
    $row['balance'] = round($running, 2);
    $row['debit'] = (float)$row['debit'];
    $row['credit'] = (float)$row['credit'];
}
unset($row);

json_response([
    'ok' => true,
    'party' => $party,
    'rows' => $rows,
    'total_debit' => round($totalDebit, 2),
    'total_credit' => round($totalCredit, 2),
    'balance' => round($running, 2)
]);
