<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_roles(['admin', 'staff']);
$pdo = db();

$sql = 'SELECT rp.id, rp.promised_amount, rp.promise_datetime, p.party_name, p.phone
        FROM recovery_promises rp
        JOIN parties p ON p.id = rp.party_id
        WHERE rp.status = "open" AND DATE(rp.promise_datetime) = CURDATE()';

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();

$out = [];
foreach ($rows as $row) {
    $msg = sprintf('Reminder: %s ne Rs %0.2f ka promise %s par kiya hai.', $row['party_name'], (float)$row['promised_amount'], $row['promise_datetime']);
    $out[] = [
        'party' => $row['party_name'],
        'phone' => $row['phone'],
        'message' => $msg,
        'whatsapp_link' => create_whatsapp_link($row['phone'] ?? '', $msg)
    ];
}

json_response(['ok' => true, 'reminders' => $out]);
