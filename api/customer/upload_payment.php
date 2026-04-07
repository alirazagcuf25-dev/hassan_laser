<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Use POST'], 405);
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
if ($orderId <= 0 || $amount <= 0 || empty($_FILES['screenshot'])) {
    json_response(['ok' => false, 'message' => 'order_id, amount, screenshot required'], 422);
}

$pdo = db();
$partyStmt = $pdo->prepare('SELECT id FROM parties WHERE linked_user_id = ? LIMIT 1');
$partyStmt->execute([(int)$current['id']]);
$party = $partyStmt->fetch();
if (!$party) {
    json_response(['ok' => false, 'message' => 'Customer party not linked'], 422);
}

$file = $_FILES['screenshot'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
    json_response(['ok' => false, 'message' => 'Invalid file type'], 422);
}

$targetName = 'payment_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = __DIR__ . '/../../public/uploads/' . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    json_response(['ok' => false, 'message' => 'Failed to upload screenshot'], 500);
}

$stmt = $pdo->prepare('INSERT INTO payment_verifications(order_id, customer_party_id, amount, screenshot_path, status) VALUES(?, ?, ?, ?, "pending")');
$stmt->execute([$orderId, (int)$party['id'], $amount, 'uploads/' . $targetName]);

json_response(['ok' => true, 'message' => 'Payment submitted for admin approval']);
