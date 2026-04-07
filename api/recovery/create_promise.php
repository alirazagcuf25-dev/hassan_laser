<?php

require_once __DIR__ . '/../../app/bootstrap.php';

$current = require_roles(['admin', 'staff']);
$input = request_json();
$missing = require_fields($input, ['party_id', 'promised_amount', 'promise_datetime']);
if ($missing) {
    json_response(['ok' => false, 'message' => 'Missing field: ' . $missing], 422);
}

$pdo = db();
$msg = sprintf('Aap ne Rs %0.2f ka promise %s par kiya hai.', (float)$input['promised_amount'], $input['promise_datetime']);

$stmt = $pdo->prepare('INSERT INTO recovery_promises(party_id, promised_amount, promise_datetime, reminder_message, created_by) VALUES(?, ?, ?, ?, ?)');
$stmt->execute([
    (int)$input['party_id'],
    (float)$input['promised_amount'],
    $input['promise_datetime'],
    $msg,
    (int)$current['id']
]);

json_response(['ok' => true, 'message' => 'Promise recorded']);
