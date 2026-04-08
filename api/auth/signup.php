<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$input = request_json();
$missing = require_fields($input, ['username', 'phone', 'password']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$username = trim((string)$input['username']);
if (strlen($username) < 3) {
    json_response(['ok' => false, 'message' => 'Username must be at least 3 characters'], 422);
}

if (strlen((string)$input['password']) < 6) {
    json_response(['ok' => false, 'message' => 'Password must be at least 6 characters'], 422);
}

$phone = trim((string)$input['phone']);
if ($phone === '') {
    json_response(['ok' => false, 'message' => 'Phone is required'], 422);
}

$email = !empty($input['email']) ? trim((string)$input['email']) : null;

$pdo = db();
$exists = $pdo->prepare('SELECT id FROM users WHERE full_name = ? LIMIT 1');
$exists->execute([$username]);
if ($exists->fetch()) {
    json_response(['ok' => false, 'message' => 'Username already exists'], 409);
}

$phoneExists = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
$phoneExists->execute([$phone]);
if ($phoneExists->fetch()) {
    json_response(['ok' => false, 'message' => 'Phone already exists'], 409);
}

if ($email !== null) {
    $emailExists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $emailExists->execute([$email]);
    if ($emailExists->fetch()) {
        json_response(['ok' => false, 'message' => 'Email already exists'], 409);
    }
}

$hash = password_hash((string)$input['password'], PASSWORD_BCRYPT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO users(role, full_name, phone, email, password_hash, is_active) VALUES("customer", ?, ?, ?, ?, 1)');
    $stmt->execute([$username, $phone, $email, $hash]);

    $userId = (int)$pdo->lastInsertId();
    $tmpCode = 'CID-TMP-' . $userId;

    $party = $pdo->prepare('INSERT INTO parties(party_type, party_code, party_name, phone, linked_user_id, opening_balance, opening_balance_type) VALUES("customer", ?, ?, ?, ?, 0, "dr")');
    $party->execute([$tmpCode, $username, $phone, $userId]);

    $partyId = (int)$pdo->lastInsertId();
    $finalCode = sprintf('CID-%04d', $partyId);
    $partyUpdate = $pdo->prepare('UPDATE parties SET party_code = ? WHERE id = ?');
    $partyUpdate->execute([$finalCode, $partyId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'Signup failed: ' . $e->getMessage()], 500);
}

json_response(['ok' => true, 'message' => 'Sign up successful. Please sign in.']);
