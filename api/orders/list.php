<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_login();
$pdo = db();

$status = $_GET['status'] ?? null;
$params = [];
$sql = 'SELECT o.id, o.order_no, o.order_date, o.delivery_date, o.status, o.estimate_amount, o.advance_amount, o.final_bill_amount, p.party_name customer_name FROM orders o JOIN parties p ON p.id = o.customer_party_id';

if ($status) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY o.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
