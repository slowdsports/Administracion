<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$config  = getAllConfig($pdo);
$org     = $config['nombre_organizacion'] ?? 'AHDECO';
$orgFull = $config['nombre_completo']     ?? 'Asociación Hondureña para el Desarrollo Integral Comunitario';
$orgDir  = $config['direccion']           ?? 'Tegucigalpa, Honduras';

// Cuentas con comprometido / proyectado
$cuentas = $pdo->query("
    SELECT cb.*,
        COALESCE(SUM(CASE WHEN op.estado IN ('pendiente','aprobada') THEN op.monto ELSE 0 END),0) AS comprometido,
        cb.saldo_disponible - (cb.saldo_minimo + COALESCE(SUM(CASE WHEN op.estado IN ('pendiente','aprobada') THEN op.monto ELSE 0 END),0)) AS proyectado,
        cb.saldo_disponible - cb.saldo_minimo AS saldo_efectivo
    FROM cuentas_bancarias cb
    LEFT JOIN ordenes_pago op ON op.cuenta_bancaria_id = cb.id
    WHERE cb.activa = 1
    GROUP BY cb.id
    ORDER BY cb.banco, cb.nombre")->fetchAll();

$totalDisp    = array_sum(array_column($cuentas,'saldo_disponible'));
$totalMinimos = array_sum(array_column($cuentas,'saldo_minimo'));
$totalEfect   = $totalDisp - $totalMinimos;
$totalComp    = array_sum(array_column($cuentas,'comprometido'));
$totalProy    = array_sum(array_column($cuentas,'proyectado'));

// Agrupar por banco
$porBanco = [];
foreach ($cuentas as $c) $porBanco[$c['banco']][] = $c;
ksort($porBanco);

$fecha = date('d/m/Y');
$hora  = date('H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Disponibilidad de Cuentas — <?= $fecha ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }

    /* ── Print toolbar (hidden when printing) ── */
    .print-toolbar {
      background: #1e3a5f; color: #fff; padding: .55rem 1.2rem;
      display: flex; align-items: center; gap: .75rem;
      position: sticky; top: 0; z-index: 10;
    }
    .print-toolbar h2 { font-size: .9rem; font-weight: 600; flex: 1; }
    .print-toolbar button, .print-toolbar a {
      background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
      color: #fff; padding: .3rem .85rem; border-radius: 4px; font-size: .78rem;
      cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: .3rem;
    }
    .print-toolbar button:hover, .print-toolbar a:hover { background: rgba(255,255,255,.28); }

    /* ── Document ── */
    .doc { max-width: 820px; margin: 0 auto; padding: 1.2rem 1.5rem 3rem; }

    /* Header */
    .doc-header { display: flex; align-items: center; gap: 1.2rem; border-bottom: 3px solid #1e3a5f; padding-bottom: .9rem; margin-bottom: 1rem; }
    .doc-header img { height: 52px; }
    .doc-header-text h1 { font-size: 1rem; font-weight: 700; color: #1e3a5f; line-height: 1.3; }
    .doc-header-text p  { font-size: .72rem; color: #555; margin-top: .1rem; }
    .doc-meta { margin-left: auto; text-align: right; font-size: .72rem; color: #555; line-height: 1.6; }
    .doc-meta strong { font-size: .85rem; color: #1e3a5f; display: block; }

    /* Report title */
    .report-title { text-align: center; margin: 1rem 0 1.4rem; }
    .report-title h2 { font-size: 1.05rem; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: .05em; }
    .report-title p  { font-size: .75rem; color: #666; margin-top: .2rem; }

    /* KPI summary strip */
    .kpi-strip { display: flex; gap: .6rem; margin-bottom: 1.4rem; }
    .kpi-box { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: .5rem .7rem; text-align: center; }
    .kpi-box .kv { font-size: .95rem; font-weight: 700; margin-top: .15rem; }
    .kpi-box .kl { font-size: .65rem; color: #666; text-transform: uppercase; letter-spacing: .03em; }
    .kpi-box.green { border-color: #28a745; background: #f0fff4; }
    .kpi-box.teal  { border-color: #17a2b8; background: #f0feff; }
    .kpi-box.yellow{ border-color: #ffc107; background: #fffdf0; }
    .kpi-box.blue  { border-color: #007bff; background: #f0f8ff; }
    .kpi-box.red   { border-color: #dc3545; background: #fff0f0; }

    /* Table */
    table { width: 100%; border-collapse: collapse; margin-bottom: 1.2rem; }
    th { background: #1e3a5f; color: #fff; font-size: .72rem; font-weight: 600; padding: .4rem .6rem; text-align: left; }
    th.r { text-align: right; }
    td { padding: .35rem .6rem; font-size: .78rem; border-bottom: 1px solid #e8e8e8; vertical-align: middle; }
    td.r { text-align: right; font-family: 'Courier New', monospace; }
    tr:nth-child(even) td { background: #f9f9fb; }

    /* Bank group header */
    .bank-hdr td { background: #eef2fa !important; font-weight: 700; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #1e3a5f; padding: .45rem .6rem; }

    /* Bank subtotal */
    .bank-sub td { background: #dce4f4 !important; font-weight: 600; font-size: .76rem; color: #1e3a5f; }

    /* Grand total */
    .grand-total td { background: #1e3a5f !important; color: #fff !important; font-weight: 700; font-size: .82rem; }
    .grand-total td.r { font-family: 'Courier New', monospace; }

    /* Colors */
    .c-green  { color: #1a7a3c; }
    .c-teal   { color: #0d6e7a; }
    .c-yellow { color: #856404; }
    .c-red    { color: #c0392b; }
    .c-gray   { color: #888; }
    .fw7 { font-weight: 700; }

    /* Footer */
    .doc-footer { margin-top: 2rem; border-top: 1px solid #ccc; padding-top: .6rem; display: flex; justify-content: space-between; font-size: .68rem; color: #888; }

    /* Print rules */
    @media print {
      .print-toolbar { display: none !important; }
      body { font-size: 11px; }
      .doc { padding: 0; max-width: 100%; }
      .kpi-strip { break-inside: avoid; }
      tr { break-inside: avoid; }
    }
  </style>
</head>
<body>

<div class="print-toolbar">
  <h2><i class="fas fa-landmark"></i> Tesorería — Disponibilidad de Cuentas</h2>
  <button onclick="window.print()"><i class="fas fa-print"></i> Imprimir / PDF</button>
  <a href="<?= BASE_URL ?>tesoreria.php">← Volver</a>
</div>

<div class="doc">

  <!-- Header -->
  <div class="doc-header">
    <img src="<?= BASE_URL ?>assets/images/logo2aa.png" alt="<?= htmlspecialchars($org) ?>">
    <div class="doc-header-text">
      <h1><?= htmlspecialchars($org) ?></h1>
      <p><?= htmlspecialchars($orgFull) ?><br><?= htmlspecialchars($orgDir) ?></p>
    </div>
    <div class="doc-meta">
      <strong>Disponibilidad de Cuentas</strong>
      Fecha: <?= $fecha ?><br>
      Hora: <?= $hora ?>
    </div>
  </div>

  <!-- KPI strip -->
  <div class="kpi-strip">
    <div class="kpi-box green">
      <div class="kl">Disponible Total</div>
      <div class="kv c-green"><?= lps($totalDisp) ?></div>
    </div>
    <div class="kpi-box teal">
      <div class="kl">Efectivo Operativo</div>
      <div class="kv c-teal"><?= lps($totalEfect) ?></div>
    </div>
    <div class="kpi-box yellow">
      <div class="kl">Comprometido</div>
      <div class="kv c-yellow"><?= lps($totalComp) ?></div>
    </div>
    <div class="kpi-box <?= $totalProy >= 0 ? 'blue' : 'red' ?>">
      <div class="kl">Proyectado</div>
      <div class="kv <?= $totalProy >= 0 ? '' : 'c-red' ?>"><?= lps($totalProy) ?></div>
    </div>
  </div>

  <!-- Main table -->
  <table>
    <thead>
      <tr>
        <th>Cuenta</th>
        <th class="r">Disponible</th>
        <th class="r">Mínimo</th>
        <th class="r">Operativo</th>
        <th class="r">Comprometido</th>
        <th class="r">Proyectado</th>
        <th>Actualización</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($porBanco as $banco => $cuentasBanco):
      $bDisp = array_sum(array_column($cuentasBanco,'saldo_disponible'));
      $bMin  = array_sum(array_column($cuentasBanco,'saldo_minimo'));
      $bEfec = $bDisp - $bMin;
      $bComp = array_sum(array_column($cuentasBanco,'comprometido'));
      $bProy = array_sum(array_column($cuentasBanco,'proyectado'));
    ?>
      <tr class="bank-hdr">
        <td colspan="7"><i class="fas fa-building-columns" style="margin-right:.35rem"></i><?= htmlspecialchars($banco) ?></td>
      </tr>
      <?php foreach ($cuentasBanco as $c): ?>
      <tr>
        <td>
          <span class="fw7"><?= htmlspecialchars($c['nombre']) ?></span>
          <?php if ($c['numero_cuenta']): ?>
          <span class="c-gray" style="font-size:.7rem"> · <?= htmlspecialchars($c['numero_cuenta']) ?></span>
          <?php endif; ?>
          <br><span class="c-gray" style="font-size:.68rem"><?= ucfirst($c['tipo_cuenta']) ?> / <?= $c['moneda'] ?></span>
        </td>
        <td class="r fw7"><?= lps((float)$c['saldo_disponible']) ?></td>
        <td class="r c-gray"><?= $c['saldo_minimo'] > 0 ? lps((float)$c['saldo_minimo']) : '—' ?></td>
        <td class="r c-teal fw7"><?= lps((float)$c['saldo_efectivo']) ?></td>
        <td class="r c-yellow"><?= $c['comprometido'] > 0 ? lps((float)$c['comprometido']) : '—' ?></td>
        <td class="r fw7 <?= (float)$c['proyectado'] >= 0 ? 'c-green' : 'c-red' ?>"><?= lps((float)$c['proyectado']) ?></td>
        <td class="c-gray" style="font-size:.7rem;white-space:nowrap"><?= $c['fecha_saldo'] ? fmtFecha($c['fecha_saldo']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="bank-sub">
        <td style="padding-left:1.2rem">Subtotal <?= htmlspecialchars($banco) ?></td>
        <td class="r"><?= lps($bDisp) ?></td>
        <td class="r" style="color:#555"><?= lps($bMin) ?></td>
        <td class="r c-teal"><?= lps($bEfec) ?></td>
        <td class="r c-yellow"><?= $bComp > 0 ? lps($bComp) : '—' ?></td>
        <td class="r <?= $bProy >= 0 ? 'c-green' : 'c-red' ?>"><?= lps($bProy) ?></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="grand-total">
        <td>TOTAL GENERAL</td>
        <td class="r"><?= lps($totalDisp) ?></td>
        <td class="r"><?= lps($totalMinimos) ?></td>
        <td class="r"><?= lps($totalEfect) ?></td>
        <td class="r"><?= lps($totalComp) ?></td>
        <td class="r"><?= lps($totalProy) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <div class="doc-footer">
    <span>Generado el <?= $fecha ?> a las <?= $hora ?></span>
    <span><?= htmlspecialchars($org) ?> — Sistema de Administración y Finanzas</span>
  </div>

</div>
</body>
</html>
