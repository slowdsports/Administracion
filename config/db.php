<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('America/Tegucigalpa');

$currentHost = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$currentHost = preg_replace('/:\d+$/', '', $currentHost);
$isLocal = in_array($currentHost, ['localhost', '127.0.0.1', '::1'], true);

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ahdeco_admin');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u6613652_ahdeco_admin');
    define('DB_USER', 'u6613652_root');
    define('DB_PASS', 'sJmJPvG5?Q0n-+6Y');
}

define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
