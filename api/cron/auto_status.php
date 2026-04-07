<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_roles(['admin']);
$pdo = db();

$stmt = $pdo->prepare('UPDATE orders SET status = "in_process", updated_at = NOW() WHERE status = "pending" AND delivery_date <= NOW()');
$stmt->execute();

json_response(['ok' => true, 'updated_rows' => $stmt->rowCount()]);
