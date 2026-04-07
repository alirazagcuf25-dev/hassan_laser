<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_roles(['admin']);
$input = request_json();

$missing = require_fields($input, ['role', 'full_name', 'phone', 'password']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

if (!in_array($input['role'], ['admin', 'staff', 'customer'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid role'], 422);
}

$pdo = db();
$hash = password_hash($input['password'], PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO users(role, full_name, phone, email, password_hash) VALUES(?, ?, ?, ?, ?)');
$stmt->execute([
    $input['role'],
    trim($input['full_name']),
    trim($input['phone']),
    $input['email'] ?? null,
    $hash
]);

json_response(['ok' => true, 'message' => 'User created', 'created_by' => $user['id']]);
