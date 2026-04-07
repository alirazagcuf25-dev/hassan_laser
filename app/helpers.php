<?php

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function require_fields(array $source, array $fields): ?string
{
    foreach ($fields as $field) {
        if (!isset($source[$field]) || $source[$field] === '') {
            return $field;
        }
    }
    return null;
}

function next_code(string $prefix, int $id): string
{
    return sprintf('%s-%05d', $prefix, $id);
}

function convert_to_base(PDO $pdo, int $itemId, float $qty, string $fromUnit): float
{
    $stmt = $pdo->prepare('SELECT multiplier FROM unit_conversions WHERE item_id = ? AND from_unit = ? AND to_unit = "base" LIMIT 1');
    $stmt->execute([$itemId, $fromUnit]);
    $row = $stmt->fetch();

    if ($row) {
        return round($qty * (float)$row['multiplier'], 3);
    }

    $unitStmt = $pdo->prepare('SELECT 1 FROM item_units WHERE item_id = ? AND unit_name = ? AND is_base = 1 LIMIT 1');
    $unitStmt->execute([$itemId, $fromUnit]);
    if ($unitStmt->fetch()) {
        return round($qty, 3);
    }

    throw new RuntimeException('No conversion found for this unit.');
}

function create_whatsapp_link(string $phone, string $message): string
{
    $clean = preg_replace('/[^0-9]/', '', $phone);
    return 'https://wa.me/' . $clean . '?text=' . rawurlencode($message);
}
