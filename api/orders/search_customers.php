<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_roles(['admin', 'staff']);
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || strlen($q) < 2) {
    json_response(['ok' => true, 'rows' => []]);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, party_name, party_code, phone FROM parties WHERE party_type = "customer" AND party_name LIKE ? ORDER BY party_name ASC LIMIT 20');
$stmt->execute(['%' . $q . '%']);

json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
