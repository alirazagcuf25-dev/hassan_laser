<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_login();
$pdo = db();

$type = trim((string)($_GET['type'] ?? ''));
$allowed = ['customer', 'supplier', 'employee', 'owner'];
if (!in_array($type, $allowed, true)) {
    json_response(['ok' => false, 'message' => 'Invalid party type'], 422);
}

$stmt = $pdo->prepare('SELECT p.id, p.party_name, p.party_code, p.phone FROM parties p WHERE p.party_type = ? ORDER BY p.party_name ASC');
$stmt->execute([$type]);

json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
