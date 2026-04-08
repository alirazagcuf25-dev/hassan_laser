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
$userColumns = [];
foreach ($pdo->query('SHOW COLUMNS FROM users') as $col) {
    $userColumns[(string)$col['Field']] = true;
}

$partyColumns = [];
foreach ($pdo->query('SHOW COLUMNS FROM parties') as $col) {
    $partyColumns[(string)$col['Field']] = true;
}

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

if ($email !== null && isset($userColumns['email'])) {
    $emailExists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $emailExists->execute([$email]);
    if ($emailExists->fetch()) {
        json_response(['ok' => false, 'message' => 'Email already exists'], 409);
    }
}

$hash = password_hash((string)$input['password'], PASSWORD_BCRYPT);

try {
    $pdo->beginTransaction();

    $userFields = ['role', 'full_name', 'phone', 'password_hash'];
    $userValues = ['customer', $username, $phone, $hash];

    if (isset($userColumns['email'])) {
        $userFields[] = 'email';
        $userValues[] = $email;
    }

    if (isset($userColumns['is_active'])) {
        $userFields[] = 'is_active';
        $userValues[] = 1;
    }

    $userSql = 'INSERT INTO users(' . implode(', ', $userFields) . ') VALUES(' . implode(', ', array_fill(0, count($userFields), '?')) . ')';
    $stmt = $pdo->prepare($userSql);
    $stmt->execute($userValues);

    $userId = (int)$pdo->lastInsertId();

    $partyFields = [];
    $partyValues = [];

    if (isset($partyColumns['party_type'])) {
        $partyFields[] = 'party_type';
        $partyValues[] = 'customer';
    }

    if (isset($partyColumns['party_name'])) {
        $partyFields[] = 'party_name';
        $partyValues[] = $username;
    }

    if (isset($partyColumns['phone'])) {
        $partyFields[] = 'phone';
        $partyValues[] = $phone;
    }

    if (isset($partyColumns['linked_user_id'])) {
        $partyFields[] = 'linked_user_id';
        $partyValues[] = $userId;
    }

    if (isset($partyColumns['opening_balance'])) {
        $partyFields[] = 'opening_balance';
        $partyValues[] = 0;
    }

    if (isset($partyColumns['opening_balance_type'])) {
        $partyFields[] = 'opening_balance_type';
        $partyValues[] = 'dr';
    }

    $tmpCode = null;
    if (isset($partyColumns['party_code'])) {
        $tmpCode = 'CID-TMP-' . $userId;
        $partyFields[] = 'party_code';
        $partyValues[] = $tmpCode;
    }

    if (count($partyFields) >= 2) {
        $partySql = 'INSERT INTO parties(' . implode(', ', $partyFields) . ') VALUES(' . implode(', ', array_fill(0, count($partyFields), '?')) . ')';
        $party = $pdo->prepare($partySql);
        $party->execute($partyValues);

        if ($tmpCode !== null) {
            $partyId = (int)$pdo->lastInsertId();
            $finalCode = sprintf('CID-%04d', $partyId);
            $partyUpdate = $pdo->prepare('UPDATE parties SET party_code = ? WHERE id = ?');
            $partyUpdate->execute([$finalCode, $partyId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'Signup failed: ' . $e->getMessage()], 500);
}

json_response(['ok' => true, 'message' => 'Sign up successful. Please sign in.']);
