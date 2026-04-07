<?php

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        json_response(['ok' => false, 'message' => 'Forbidden'], 403);
    }
    return $user;
}
