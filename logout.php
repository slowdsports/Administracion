<?php
session_start();
require_once __DIR__ . '/config/functions.php';
session_destroy();
header('Location: ' . BASE_URL . 'index.php');
exit;
