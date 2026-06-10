<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$tipo = $_GET['tipo'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
if (!$id || !in_array($tipo, ['sc','oc','op','pl','plv','plc','gv','sg','proceso','pa','pb','pav','pbv','vac','per','trabajo','salario','referencia','tiempo','liq','cc','memo','acta'])) {
    http_response_code(400); die('Parámetros inválidos.');
}

// ── Capturar HTML de print.php ────────────────────────────────────
ob_start();
include __DIR__ . '/print.php';
$html = ob_get_clean();

// ── Escribir HTML a archivo temporal ─────────────────────────────
$uid     = 'ahdeco_' . uniqid();
$tmpDir  = sys_get_temp_dir();
$tmpHtml = $tmpDir . DIRECTORY_SEPARATOR . $uid . '.html';
$tmpPdf  = $tmpDir . DIRECTORY_SEPARATOR . $uid . '.pdf';

file_put_contents($tmpHtml, $html);

// ── Llamar Edge en modo headless ──────────────────────────────────
$edge    = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
$fileUrl = 'file:///' . str_replace('\\', '/', $tmpHtml);

$cmd = '"' . $edge . '" --headless=new --disable-gpu --no-sandbox'
     . ' --print-to-pdf="' . $tmpPdf . '"'
     . ' --no-pdf-header-footer'
     . ' "' . $fileUrl . '" 2>&1';

exec($cmd, $output, $exitCode);

// ── Verificar resultado ───────────────────────────────────────────
if (!file_exists($tmpPdf) || filesize($tmpPdf) === 0) {
    @unlink($tmpHtml);
    http_response_code(500);
    header('Content-Type: text/plain');
    die("Error generando PDF (exit: $exitCode).\n" . implode("\n", $output));
}

// ── Nombre del archivo ────────────────────────────────────────────
$docNum  = preg_replace('/[^\w\-]/', '_', "$tipo-$id");
$empSlug = '';
if (!empty($_GET['emp_id'])) {
    $se = $pdo->prepare("SELECT CONCAT(nombre,' ',apellidos) FROM empleados WHERE id=?");
    $se->execute([(int)$_GET['emp_id']]);
    $empSlug = '-' . preg_replace('/[^\w]/', '_', $se->fetchColumn() ?: $_GET['emp_id']);
}
$filename = "AHDECO-{$docNum}{$empSlug}.pdf";

// ── Enviar al navegador ───────────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpPdf));
header('Cache-Control: private, no-cache');
readfile($tmpPdf);

// ── Limpieza ──────────────────────────────────────────────────────
@unlink($tmpHtml);
@unlink($tmpPdf);
