<?php
header('Content-Type: text/plain; charset=utf-8');

echo "DEPLOY TEST OK\n";
echo "marker: 2026-04-08-verify-01\n";
echo "script: " . basename(__FILE__) . "\n";
echo "server_time: " . date('c') . "\n";
