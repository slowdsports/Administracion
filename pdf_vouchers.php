<?php
// Redirige a pdf_download.php que usa Edge --print-to-pdf (texto seleccionable, gradientes incluidos)
$tipo   = $_GET['tipo'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$empId  = (int)($_GET['emp_id'] ?? 0);

$params = http_build_query(array_filter([
    'tipo'   => $tipo,
    'id'     => $id ?: null,
    'emp_id' => $empId ?: null,
]));

header('Location: ' . BASE_URL . 'pdf_download.php?' . $params);
exit;
