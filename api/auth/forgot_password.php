<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$input = request_json();
$missing = require_fields($input, ['username', 'new_password']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

if (strlen((string)$input['new_password']) < 6) {
    json_response(['ok' => false, 'message' => 'New password must be at least 6 characters'], 422);
}

$username = trim((string)$input['username']);
$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE full_name = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) {
    json_response(['ok' => false, 'message' => 'Username not found'], 404);
}

$hash = password_hash((string)$input['new_password'], PASSWORD_BCRYPT);
$up = $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?');
$up->execute([$hash, (int)$user['id']]);

json_response(['ok' => true, 'message' => 'Password reset successful. Please sign in.']);
