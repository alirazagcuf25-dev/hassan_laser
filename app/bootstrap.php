<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Karachi');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (!is_dir(__DIR__ . '/../public/uploads')) {
    mkdir(__DIR__ . '/../public/uploads', 0755, true);
}
