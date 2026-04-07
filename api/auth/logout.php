<?php

require_once __DIR__ . '/../../app/bootstrap.php';

session_unset();
session_destroy();
json_response(['ok' => true, 'message' => 'Logged out']);
