<?php
declare(strict_types=1);

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$directory = trim(dirname($script), '/');
$base = $directory === '' || $directory === '.' ? ''
    : '/'.implode('/', array_map('rawurlencode', explode('/', $directory)));

return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=sipe_grupo_5;charset=utf8mb4',
    'user' => 'root',
    'password' => '',
    'base_path' => $base,
    'secure_cookie' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'session_timeout' => 1800,
];
