<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('America/Tegucigalpa');

// El helper ajax() del frontend envía application/json cuando los datos no son
// un FormData (p.ej. deleteRecord y otras llamadas de un solo campo). PHP no
// llena $_POST para ese Content-Type, así que se parsea aquí manualmente.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $_POST += $jsonBody;
    }
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'ahdeco_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
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
