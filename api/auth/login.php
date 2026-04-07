<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$input = request_json();
$missing = require_fields($input, ['username', 'password']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();
$username = trim((string)$input['username']);
$stmt = $pdo->prepare('SELECT id, role, full_name, phone, email, password_hash, is_active FROM users WHERE full_name = ? OR (? = "admin" AND role = "admin") LIMIT 1');
$stmt->execute([$username, strtolower($username)]);
$found = $stmt->fetch();

if (!$found || !$found['is_active'] || !password_verify($input['password'], $found['password_hash'])) {
    json_response(['ok' => false, 'message' => 'Invalid username or password'], 401);
}

$_SESSION['user'] = [
    'id' => (int)$found['id'],
    'role' => $found['role'],
    'full_name' => $found['full_name'],
    'phone' => $found['phone']
];

json_response(['ok' => true, 'message' => 'Login successful', 'user' => $_SESSION['user']]);
