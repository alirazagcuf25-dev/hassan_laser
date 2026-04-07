<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['customer']);
$pdo = db();

$partyStmt = $pdo->prepare('SELECT id FROM parties WHERE linked_user_id = ? LIMIT 1');
$partyStmt->execute([(int)$current['id']]);
$party = $partyStmt->fetch();
if (!$party) {
    json_response(['ok' => false, 'rows' => []]);
}

$stmt = $pdo->prepare('SELECT order_no, order_date, delivery_date, status, estimate_amount, final_bill_amount FROM orders WHERE customer_party_id = ? ORDER BY id DESC');
$stmt->execute([(int)$party['id']]);
json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
