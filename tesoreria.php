<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pagina = 'Tesorería';

// ── AJAX ─────────────────────────────────────────────────────────────
if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['_action'] ?? '';

    // ── Guardar cuenta bancaria ──────────────────────────────────────
    if ($act === 'guardar_cuenta') {
        $id        = (int)($_POST['id'] ?? 0);
        $nombre    = trim($_POST['nombre'] ?? '');
        $banco     = trim($_POST['banco']  ?? '');
        if (!$nombre || !$banco) jsonErr('Nombre y banco son requeridos.');
        $saldo     = (float)($_POST['saldo_disponible'] ?? 0);
        $saldoMin  = max(0, (float)($_POST['saldo_minimo'] ?? 0));
        $fecha     = $_POST['fecha_saldo'] ?: null;
        $num       = trim($_POST['numero_cuenta'] ?? '');
        $tipo      = in_array($_POST['tipo_cuenta'] ?? '', ['ahorros','cheques']) ? $_POST['tipo_cuenta'] : 'ahorros';
        $mon       = in_array($_POST['moneda'] ?? '', ['HNL','USD']) ? $_POST['moneda'] : 'HNL';
        $notas     = trim($_POST['notas'] ?? '');
        if ($id) {
            $pdo->prepare("UPDATE cuentas_bancarias SET nombre=?,banco=?,numero_cuenta=?,tipo_cuenta=?,moneda=?,saldo_disponible=?,saldo_minimo=?,fecha_saldo=?,notas=? WHERE id=?")
                ->execute([$nombre,$banco,$num,$tipo,$mon,$saldo,$saldoMin,$fecha,$notas,$id]);
            jsonOk([],'Cuenta actualizada.');
        } else {
            $pdo->prepare("INSERT INTO cuentas_bancarias (nombre,banco,numero_cuenta,tipo_cuenta,moneda,saldo_disponible,saldo_minimo,fecha_saldo,notas) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$nombre,$banco,$num,$tipo,$mon,$saldo,$saldoMin,$fecha,$notas]);
            jsonOk(['id'=>$pdo->lastInsertId()],'Cuenta creada.');
        }
    }

    // ── Actualizar saldo ─────────────────────────────────────────────
    if ($act === 'actualizar_saldo') {
        $id    = (int)$_POST['id'];
        $saldo = (float)$_POST['saldo_disponible'];
        $fecha = $_POST['fecha_saldo'] ?: date('Y-m-d');
        $pdo->prepare("UPDATE cuentas_bancarias SET saldo_disponible=?,fecha_saldo=? WHERE id=?")->execute([$saldo,$fecha,$id]);
        jsonOk([],'Saldo actualizado.');
    }

    // ── Eliminar cuenta ──────────────────────────────────────────────
    if ($act === 'eliminar_cuenta') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE cuentas_bancarias SET activa=0 WHERE id=?")->execute([$id]);
        jsonOk([],'Cuenta desactivada.');
    }

    // ── Asignar cuenta bancaria a OP ─────────────────────────────────
    if ($act === 'asignar_cuenta_op') {
        $opId = (int)$_POST['op_id'];
        $cid  = ($_POST['cuenta_bancaria_id'] !== '') ? (int)$_POST['cuenta_bancaria_id'] : null;
        $pdo->prepare("UPDATE ordenes_pago SET cuenta_bancaria_id=? WHERE id=?")->execute([$cid, $opId]);
        jsonOk([],'Banco asignado.');
    }

    // ── Crear movimiento (simulado o ejecutado) ──────────────────────
    if ($act === 'crear_movimiento') {
        $origenId  = (int)$_POST['cuenta_origen_id'];
        $destinoId = (int)$_POST['cuenta_destino_id'];
        $monto     = (float)$_POST['monto'];
        $comision  = max(0, (float)$_POST['comision_ach']);
        $desc      = trim($_POST['descripcion'] ?? '');
        $ejecutar  = ($_POST['ejecutar'] ?? '0') === '1';

        if ($origenId <= 0 || $destinoId <= 0) jsonErr('Seleccione ambas cuentas.');
        if ($origenId === $destinoId) jsonErr('Las cuentas de origen y destino deben ser diferentes.');
        if ($monto <= 0) jsonErr('El monto debe ser mayor a cero.');

        // Validar saldo de origen
        $stmt = $pdo->prepare("SELECT saldo_disponible, saldo_minimo, nombre, banco FROM cuentas_bancarias WHERE id=? AND activa=1");
        $stmt->execute([$origenId]);
        $origen = $stmt->fetch();
        if (!$origen) jsonErr('Cuenta de origen no encontrada.');

        $saldoEfectivo = (float)$origen['saldo_disponible'] - (float)$origen['saldo_minimo'];
        $totalSale     = $monto + $comision;

        if ($totalSale > $saldoEfectivo) {
            jsonErr(sprintf(
                'Saldo insuficiente en "%s". Disponible efectivo: L. %s · Necesario (monto + comisión): L. %s.',
                $origen['nombre'],
                number_format($saldoEfectivo, 2, '.', ','),
                number_format($totalSale, 2, '.', ',')
            ));
        }

        $estado = $ejecutar ? 'ejecutado' : 'simulado';
        $pdo->prepare("INSERT INTO movimientos_bancarios (fecha,cuenta_origen_id,cuenta_destino_id,monto,comision_ach,descripcion,estado,usuario_id) VALUES (CURDATE(),?,?,?,?,?,?,?)")
            ->execute([$origenId,$destinoId,$monto,$comision,$desc,$estado,currentUser()['id']]);

        if ($ejecutar) {
            $pdo->prepare("UPDATE cuentas_bancarias SET saldo_disponible=saldo_disponible-?,fecha_saldo=CURDATE() WHERE id=?")
                ->execute([$totalSale, $origenId]);
            $pdo->prepare("UPDATE cuentas_bancarias SET saldo_disponible=saldo_disponible+?,fecha_saldo=CURDATE() WHERE id=?")
                ->execute([$monto, $destinoId]);
            jsonOk([],'Transferencia ejecutada y saldos actualizados.');
        }
        jsonOk([],'Movimiento simulado registrado.');
    }

    // ── Ejecutar movimiento simulado ─────────────────────────────────
    if ($act === 'ejecutar_movimiento') {
        $movId = (int)$_POST['id'];
        $stmt  = $pdo->prepare("SELECT * FROM movimientos_bancarios WHERE id=? AND estado='simulado'");
        $stmt->execute([$movId]);
        $mov = $stmt->fetch();
        if (!$mov) jsonErr('Movimiento no encontrado o ya procesado.');

        $stmt = $pdo->prepare("SELECT saldo_disponible, saldo_minimo, nombre FROM cuentas_bancarias WHERE id=?");
        $stmt->execute([$mov['cuenta_origen_id']]);
        $origen = $stmt->fetch();

        $saldoEfectivo = (float)$origen['saldo_disponible'] - (float)$origen['saldo_minimo'];
        $totalSale     = (float)$mov['monto'] + (float)$mov['comision_ach'];

        if ($totalSale > $saldoEfectivo) {
            jsonErr(sprintf(
                'Saldo insuficiente al ejecutar. Disponible efectivo: L. %s · Necesario: L. %s.',
                number_format($saldoEfectivo, 2, '.', ','),
                number_format($totalSale, 2, '.', ',')
            ));
        }

        $pdo->prepare("UPDATE movimientos_bancarios SET estado='ejecutado' WHERE id=?")->execute([$movId]);
        $pdo->prepare("UPDATE cuentas_bancarias SET saldo_disponible=saldo_disponible-?,fecha_saldo=CURDATE() WHERE id=?")->execute([$totalSale,$mov['cuenta_origen_id']]);
        $pdo->prepare("UPDATE cuentas_bancarias SET saldo_disponible=saldo_disponible+?,fecha_saldo=CURDATE() WHERE id=?")->execute([$mov['monto'],$mov['cuenta_destino_id']]);
        jsonOk([],'Transferencia ejecutada correctamente.');
    }

    // ── Anular movimiento simulado ───────────────────────────────────
    if ($act === 'anular_movimiento') {
        $movId = (int)$_POST['id'];
        $pdo->prepare("UPDATE movimientos_bancarios SET estado='anulado' WHERE id=? AND estado='simulado'")->execute([$movId]);
        jsonOk([],'Movimiento anulado.');
    }

    jsonErr('Acción desconocida.');
}

// ── Datos: cuentas con comprometido y efectivo ────────────────────────
$cuentas = $pdo->query("
    SELECT cb.*,
        COALESCE(SUM(CASE WHEN op.estado IN ('pendiente','aprobada') THEN op.monto ELSE 0 END),0) AS comprometido,
        cb.saldo_disponible - (cb.saldo_minimo + COALESCE(SUM(CASE WHEN op.estado IN ('pendiente','aprobada') THEN op.monto ELSE 0 END),0)) AS proyectado,
        cb.saldo_disponible - cb.saldo_minimo AS saldo_efectivo
    FROM cuentas_bancarias cb
    LEFT JOIN ordenes_pago op ON op.cuenta_bancaria_id = cb.id
    WHERE cb.activa = 1
    GROUP BY cb.id
    ORDER BY cb.nombre")->fetchAll();

$totalDisp     = array_sum(array_column($cuentas,'saldo_disponible'));
$totalMinimos  = array_sum(array_column($cuentas,'saldo_minimo'));
$totalEfectivo = $totalDisp - $totalMinimos;
$totalComp     = array_sum(array_column($cuentas,'comprometido'));

// OPs pendientes
$ops = $pdo->query("
    SELECT op.id, op.numero, op.fecha, op.beneficiario, op.concepto,
           op.monto, op.forma_pago, op.estado, op.cuenta_bancaria_id,
           cb.nombre AS banco_asig
    FROM ordenes_pago op
    LEFT JOIN cuentas_bancarias cb ON op.cuenta_bancaria_id = cb.id
    WHERE op.estado IN ('pendiente','aprobada')
    ORDER BY op.fecha ASC, op.id ASC")->fetchAll();

$totalPend    = (float)array_sum(array_column($ops,'monto'));
$totalProy    = $totalEfectivo - $totalPend;
$sinAsignarCt = count(array_filter($ops, fn($o)=>!$o['cuenta_bancaria_id']));
$sinAsignarM  = (float)array_sum(array_column(array_values(array_filter($ops,fn($o)=>!$o['cuenta_bancaria_id'])),'monto'));

// Movimientos bancarios
$movimientos = $pdo->query("
    SELECT mb.*,
        co.nombre AS origen_nombre, co.banco AS origen_banco,
        cd.nombre AS destino_nombre, cd.banco AS destino_banco
    FROM movimientos_bancarios mb
    JOIN cuentas_bancarias co ON mb.cuenta_origen_id = co.id
    JOIN cuentas_bancarias cd ON mb.cuenta_destino_id = cd.id
    ORDER BY mb.created_at DESC LIMIT 50")->fetchAll();

$movSimulados = count(array_filter($movimientos, fn($m)=>$m['estado']==='simulado'));

// Configuración
$comisionACH    = (float)getConfig($pdo,'comision_ach','25.00');
$cuentasActivas = $pdo->query("SELECT id, nombre, banco, CONCAT(nombre,' — ',banco) AS label FROM cuentas_bancarias WHERE activa=1 ORDER BY nombre")->fetchAll();
$bancosLista    = $pdo->query("SELECT nombre FROM cat_bancos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$csrf           = csrfToken();

// Agrupar cuentas por banco para la tabla
$porBanco = [];
foreach ($cuentas as $c) {
    $porBanco[$c['banco']][] = $c;
}
ksort($porBanco);

$cuentasJsonEsc = json_encode($cuentas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$csrfJs         = json_encode($csrf);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="fas fa-landmark"></i> Tesorería</h1>
  <div class="d-flex gap-2">
    <a href="/Administracion/print_tesoreria.php" target="_blank" class="btn-ahdeco-outline"><i class="fas fa-file-pdf"></i> PDF / Imprimir</a>
    <button class="btn-ahdeco" onclick="openCuentaModal(0)"><i class="fas fa-plus"></i> Nueva Cuenta</button>
  </div>
</div>

<!-- ── KPIs ── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-green kpi-sm kpi-hover">
      <div class="kpi-icon"><i class="fas fa-building-columns"></i></div>
      <div class="kpi-value"><?= lps($totalDisp) ?></div>
      <div class="kpi-label">Disponible Total</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-teal kpi-sm kpi-hover">
      <div class="kpi-icon"><i class="fas fa-shield-halved"></i></div>
      <div class="kpi-value"><?= lps($totalEfectivo) ?></div>
      <div class="kpi-label">Efectivo Operativo</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-yellow kpi-sm kpi-hover">
      <div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
      <div class="kpi-value"><?= lps($totalPend) ?></div>
      <div class="kpi-label">Compromisos Pendientes</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card <?= $totalProy >= 0 ? 'kpi-blue' : 'kpi-red' ?> kpi-sm kpi-hover">
      <div class="kpi-icon"><i class="fas fa-scale-balanced"></i></div>
      <div class="kpi-value"><?= lps($totalProy) ?></div>
      <div class="kpi-label">Saldo Proyectado</div>
    </div>
  </div>
</div>

<!-- ── Cuentas Bancarias ── -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-building-columns"></i> Cuentas Bancarias
    <?php if ($movSimulados > 0): ?>
    <span class="badge bg-warning ms-2"><?= $movSimulados ?> simulación<?= $movSimulados>1?'es':'' ?> pendiente<?= $movSimulados>1?'s':'' ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if ($cuentas): ?>
    <table class="table-ahdeco w-100">
      <thead>
        <tr>
          <th>Cuenta</th>
          <th class="text-end">Saldo Disponible</th>
          <th class="text-end">Saldo Mínimo</th>
          <th class="text-end">Operativo Efectivo</th>
          <th class="text-end">Comprometido</th>
          <th class="text-end">Proyectado</th>
          <th>Actualización</th>
          <th class="no-print"></th>
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
        <!-- Banco header -->
        <tr style="background:var(--surface-2)">
          <td colspan="8" style="padding:.45rem .9rem;font-size:.78rem;font-weight:700;letter-spacing:.04em;color:var(--text-2);text-transform:uppercase">
            <i class="fas fa-building-columns" style="margin-right:.4rem;color:var(--primary)"></i><?= htmlspecialchars($banco) ?>
          </td>
        </tr>
        <?php foreach ($cuentasBanco as $c): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($c['nombre']) ?></div>
            <div style="font-size:.78rem;color:var(--text-3)"><?= $c['numero_cuenta'] ? $c['numero_cuenta'].' · ' : '' ?><?= ucfirst($c['tipo_cuenta']) ?> / <?= $c['moneda'] ?></div>
          </td>
          <td class="font-mono fw-bold text-end"><?= lps((float)$c['saldo_disponible']) ?></td>
          <td class="font-mono text-end" style="color:var(--text-3)"><?= $c['saldo_minimo'] > 0 ? lps((float)$c['saldo_minimo']) : '—' ?></td>
          <td class="font-mono fw-bold text-end" style="color:var(--ah-teal)"><?= lps((float)$c['saldo_efectivo']) ?></td>
          <td class="font-mono text-end" style="color:var(--ah-yellow)"><?= $c['comprometido'] > 0 ? lps((float)$c['comprometido']) : '—' ?></td>
          <td class="font-mono fw-bold text-end" style="color:<?= (float)$c['proyectado'] >= 0 ? 'var(--ah-green)' : 'var(--ah-red)' ?>">
            <?= lps((float)$c['proyectado']) ?>
          </td>
          <td style="font-size:.8rem;color:var(--text-3)"><?= $c['fecha_saldo'] ? fmtFecha($c['fecha_saldo']) : '—' ?></td>
          <td class="no-print" style="white-space:nowrap">
            <button class="btn btn-sm btn-outline-success py-0 px-2" title="Actualizar saldo"
              onclick="openSaldoModal(<?= $c['id'] ?>,<?= $c['saldo_disponible'] ?>,'<?= $c['fecha_saldo'] ?>','<?= htmlspecialchars(addslashes($c['nombre'])) ?>')">
              <i class="fas fa-arrows-rotate"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1" title="Editar"
              onclick="openCuentaModal(<?= $c['id'] ?>)">
              <i class="fas fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" title="Desactivar"
              onclick="eliminarCuenta(<?= $c['id'] ?>,'<?= htmlspecialchars(addslashes($c['nombre'])) ?>')">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <!-- Subtotal por banco -->
        <tr style="background:var(--surface-2);font-weight:600;font-size:.82rem">
          <td style="padding:.4rem .9rem .4rem 2rem;color:var(--text-2)">Subtotal <?= htmlspecialchars($banco) ?></td>
          <td class="font-mono text-end" style="padding:.4rem .9rem"><?= lps($bDisp) ?></td>
          <td class="font-mono text-end" style="padding:.4rem .9rem;color:var(--text-3)"><?= lps($bMin) ?></td>
          <td class="font-mono text-end" style="padding:.4rem .9rem;color:var(--ah-teal)"><?= lps($bEfec) ?></td>
          <td class="font-mono text-end" style="padding:.4rem .9rem;color:var(--ah-yellow)"><?= $bComp > 0 ? lps($bComp) : '—' ?></td>
          <td class="font-mono text-end" style="padding:.4rem .9rem;color:<?= $bProy >= 0 ? 'var(--ah-green)' : 'var(--ah-red)' ?>"><?= lps($bProy) ?></td>
          <td colspan="2" style="padding:.4rem .9rem"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;background:var(--surface-2)">
          <td style="padding:.6rem .9rem">TOTAL</td>
          <td class="font-mono text-end" style="padding:.6rem .9rem"><?= lps($totalDisp) ?></td>
          <td class="font-mono text-end" style="padding:.6rem .9rem;color:var(--text-3)"><?= lps($totalMinimos) ?></td>
          <td class="font-mono text-end" style="padding:.6rem .9rem;color:var(--ah-teal)"><?= lps($totalEfectivo) ?></td>
          <td class="font-mono text-end" style="padding:.6rem .9rem;color:var(--ah-yellow)"><?= lps($totalComp) ?></td>
          <td class="font-mono text-end" style="padding:.6rem .9rem;color:<?= $totalProy>=0?'var(--ah-green)':'var(--ah-red)' ?>"><?= lps($totalProy) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    <?php else: ?>
    <div class="text-center text-muted py-5">
      <i class="fas fa-building-columns fa-2x mb-2 d-block"></i>
      No hay cuentas bancarias registradas.
      <button class="btn-ahdeco d-block mx-auto mt-3" onclick="openCuentaModal(0)"><i class="fas fa-plus"></i> Agregar primera cuenta</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Movimiento / Transferencia entre cuentas ── -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-right-left"></i> Nuevo Movimiento entre Cuentas</div>
  <div class="card-body">
    <?php if (count($cuentas) < 2): ?>
    <p class="text-muted mb-0">Se necesitan al menos 2 cuentas bancarias para registrar movimientos.</p>
    <?php else: ?>

    <div class="row g-3 align-items-end">
      <!-- Origen -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Cuenta Origen</label>
        <select id="mov-origen" class="form-select" onchange="calcularMovimiento()">
          <option value="">— Seleccionar —</option>
          <?php foreach ($cuentas as $c): ?>
          <option value="<?= $c['id'] ?>" data-banco="<?= htmlspecialchars($c['banco']) ?>"><?= htmlspecialchars($c['nombre']) ?> — <?= htmlspecialchars($c['banco']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Destino -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Cuenta Destino</label>
        <select id="mov-destino" class="form-select" onchange="calcularMovimiento()">
          <option value="">— Seleccionar —</option>
          <?php foreach ($cuentas as $c): ?>
          <option value="<?= $c['id'] ?>" data-banco="<?= htmlspecialchars($c['banco']) ?>"><?= htmlspecialchars($c['nombre']) ?> — <?= htmlspecialchars($c['banco']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Monto -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Monto a Transferir (L.)</label>
        <input type="number" id="mov-monto" class="form-control" step="0.01" min="0.01" placeholder="0.00" oninput="calcularMovimiento()">
      </div>
      <!-- Comisión ACH -->
      <div class="col-md-4">
        <label class="form-label">Comisión ACH <span id="mov-ach-tag" class="badge bg-secondary ms-1" style="display:none;font-size:.65rem">Diferente banco</span></label>
        <div class="input-group">
          <span class="input-group-text" style="font-size:.8rem">L.</span>
          <input type="number" id="mov-comision" class="form-control" step="0.01" min="0" value="0" oninput="calcularMovimiento()">
        </div>
        <div style="font-size:.72rem;color:var(--text-3);margin-top:.2rem" id="mov-ach-hint"></div>
      </div>
      <!-- Total que sale -->
      <div class="col-md-4">
        <label class="form-label">Total que sale de origen</label>
        <div class="form-control" id="mov-total-sale" style="background:var(--surface-2);font-weight:700;color:var(--ah-red)">—</div>
      </div>
      <!-- Descripción -->
      <div class="col-md-4">
        <label class="form-label">Descripción</label>
        <input type="text" id="mov-descripcion" class="form-control" placeholder="Ej: Pago nómina, traslado fondos…">
      </div>
    </div>

    <!-- Preview antes/después -->
    <div id="mov-preview" style="display:none" class="mt-4">
      <div class="row g-3">
        <!-- Origen -->
        <div class="col-md-5">
          <div id="prev-card-origen" class="card" style="border:2px solid var(--border)">
            <div class="card-body p-3">
              <div class="fw-bold mb-2" style="font-size:.9rem" id="prev-origen-nombre">—</div>
              <table class="w-100" style="font-size:.82rem">
                <tr>
                  <td style="color:var(--text-3);padding:.18rem 0">Saldo actual</td>
                  <td class="font-mono text-end fw-bold" id="prev-origen-actual">—</td>
                </tr>
                <tr id="prev-minimo-row">
                  <td style="color:var(--text-3);padding:.18rem 0">Saldo mínimo (seguridad)</td>
                  <td class="font-mono text-end" style="color:var(--text-3)" id="prev-origen-minimo">—</td>
                </tr>
                <tr>
                  <td style="color:var(--text-3);padding:.18rem 0">Disponible operativo</td>
                  <td class="font-mono text-end" style="color:var(--ah-teal)" id="prev-origen-efect">—</td>
                </tr>
                <tr>
                  <td style="color:var(--text-3);padding:.18rem 0">Sale (monto + comisión)</td>
                  <td class="font-mono text-end" style="color:var(--ah-red)" id="prev-total-sale-det">—</td>
                </tr>
                <tr style="border-top:2px solid var(--border)">
                  <td style="font-weight:700;padding:.4rem 0 0">Nuevo saldo</td>
                  <td class="font-mono text-end fw-bold" id="prev-origen-nuevo" style="padding-top:.4rem">—</td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        <!-- Flecha -->
        <div class="col-md-2 d-flex align-items-center justify-content-center">
          <div style="font-size:1.8rem;color:var(--text-3)"><i class="fas fa-arrow-right"></i></div>
        </div>
        <!-- Destino -->
        <div class="col-md-5">
          <div class="card" style="border:2px solid var(--border)">
            <div class="card-body p-3">
              <div class="fw-bold mb-2" style="font-size:.9rem" id="prev-destino-nombre">—</div>
              <table class="w-100" style="font-size:.82rem">
                <tr>
                  <td style="color:var(--text-3);padding:.18rem 0">Saldo actual</td>
                  <td class="font-mono text-end fw-bold" id="prev-destino-actual">—</td>
                </tr>
                <tr>
                  <td style="color:var(--text-3);padding:.18rem 0">Recibe</td>
                  <td class="font-mono text-end" style="color:var(--ah-green)" id="prev-destino-recibe">—</td>
                </tr>
                <tr style="border-top:2px solid var(--border)">
                  <td style="font-weight:700;padding:.4rem 0 0">Nuevo saldo</td>
                  <td class="font-mono text-end fw-bold" id="prev-destino-nuevo" style="padding-top:.4rem">—</td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        <!-- Estado -->
        <div class="col-12">
          <div id="mov-status" class="alert py-2 mb-0"></div>
        </div>
        <!-- Botones -->
        <div class="col-12 d-flex gap-2 flex-wrap">
          <button id="btn-simular" class="btn-ahdeco-outline" onclick="submitMovimiento(0)" disabled>
            <i class="fas fa-flask"></i> Guardar como Simulación
          </button>
          <button id="btn-ejecutar" class="btn-ahdeco" onclick="submitMovimiento(1)" disabled>
            <i class="fas fa-bolt"></i> Ejecutar Transferencia
          </button>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>

<!-- ── Historial de Movimientos ── -->
<?php if ($movimientos): ?>
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-clock-rotate-left"></i> Historial de Movimientos</div>
  <div class="card-body p-0">
    <table class="table-ahdeco w-100">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Origen</th>
          <th></th>
          <th>Destino</th>
          <th class="text-end">Monto</th>
          <th class="text-end">Comisión ACH</th>
          <th>Descripción</th>
          <th>Estado</th>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movimientos as $m): ?>
        <tr id="mov-row-<?= $m['id'] ?>">
          <td style="white-space:nowrap;font-size:.82rem"><?= fmtFecha($m['fecha']) ?></td>
          <td>
            <div style="font-weight:600;font-size:.83rem"><?= htmlspecialchars($m['origen_nombre']) ?></div>
            <div style="font-size:.73rem;color:var(--text-3)"><?= htmlspecialchars($m['origen_banco']) ?></div>
          </td>
          <td style="color:var(--text-3);font-size:.9rem"><i class="fas fa-arrow-right"></i></td>
          <td>
            <div style="font-weight:600;font-size:.83rem"><?= htmlspecialchars($m['destino_nombre']) ?></div>
            <div style="font-size:.73rem;color:var(--text-3)"><?= htmlspecialchars($m['destino_banco']) ?></div>
          </td>
          <td class="font-mono fw-bold text-end"><?= lps((float)$m['monto']) ?></td>
          <td class="font-mono text-end" style="color:var(--text-3)"><?= $m['comision_ach'] > 0 ? lps((float)$m['comision_ach']) : '—' ?></td>
          <td style="font-size:.82rem;color:var(--text-2)"><?= htmlspecialchars($m['descripcion'] ?? '') ?: '<span style="color:var(--text-3)">—</span>' ?></td>
          <td>
            <?php
            $cls = ['simulado'=>'warning','ejecutado'=>'success','anulado'=>'secondary'][$m['estado']] ?? 'secondary';
            echo "<span class=\"badge bg-{$cls}\">" . ucfirst($m['estado']) . "</span>";
            ?>
          </td>
          <td class="no-print" style="white-space:nowrap">
            <?php if ($m['estado'] === 'simulado'): ?>
            <button class="btn btn-sm btn-outline-success py-0 px-2" title="Ejecutar"
              onclick="ejecutarMovimiento(<?= $m['id'] ?>)">
              <i class="fas fa-bolt"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" title="Anular"
              onclick="anularMovimiento(<?= $m['id'] ?>)">
              <i class="fas fa-ban"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Auxiliar de Pagos Pendientes ── -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-list-check"></i> Auxiliar de Pagos Pendientes
    <span class="ms-2" style="font-size:.8rem;color:var(--text-3)"><?= count($ops) ?> orden<?= count($ops)!=1?'es':'' ?> · <?= lps($totalPend) ?></span>
    <a href="/Administracion/compras_pago.php" class="ms-auto" style="font-size:.76rem">Ver todas las OP</a>
  </div>
  <div class="card-body p-0">
    <?php if ($ops): ?>
    <table class="table-ahdeco w-100">
      <thead>
        <tr><th>Número</th><th>Fecha</th><th>Beneficiario</th><th>Concepto</th><th class="text-end">Monto</th><th>Estado</th><th>Banco Asignado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($ops as $op): ?>
        <tr>
          <td class="font-mono"><a href="/Administracion/compras_pago.php?action=ver&id=<?= $op['id'] ?>"><?= htmlspecialchars($op['numero']) ?></a></td>
          <td style="white-space:nowrap"><?= fmtFecha($op['fecha']) ?></td>
          <td><?= htmlspecialchars($op['beneficiario']) ?></td>
          <td style="color:var(--text-2);font-size:.82rem"><?= htmlspecialchars(mb_substr($op['concepto'],0,45)) ?><?= mb_strlen($op['concepto'])>45?'…':'' ?></td>
          <td class="font-mono fw-bold text-end"><?= lps((float)$op['monto']) ?></td>
          <td><?= estadoBadge($op['estado']) ?></td>
          <td style="min-width:200px">
            <select class="form-select form-select-sm" onchange="asignarCuenta(<?= $op['id'] ?>,this.value)">
              <option value="">— Sin asignar —</option>
              <?php foreach ($cuentasActivas as $ca): ?>
              <option value="<?= $ca['id'] ?>" <?= $op['cuenta_bancaria_id']==$ca['id']?'selected':'' ?>><?= htmlspecialchars($ca['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="text-center text-muted py-5" style="font-size:.85rem">
      <i class="fas fa-circle-check fa-2x mb-2 d-block" style="color:var(--ah-green)"></i>
      Sin órdenes de pago pendientes
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Modal: Nueva / Editar Cuenta ── -->
<div class="modal fade" id="modalCuenta" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-cuenta-title">Nueva Cuenta Bancaria</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-cuenta">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="guardar_cuenta">
        <input type="hidden" name="id" id="cuenta-id" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre / Alias *</label>
              <input type="text" name="nombre" class="form-control" placeholder="Ej: Cuenta BAC Principal" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Banco *</label>
              <select name="banco" class="form-select" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($bancosLista as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Número de Cuenta</label>
              <input type="text" name="numero_cuenta" class="form-control" placeholder="XXXX-XXXX-XXXX">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select name="tipo_cuenta" class="form-select">
                <option value="ahorros">Ahorros</option>
                <option value="cheques">Cheques</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Moneda</label>
              <select name="moneda" class="form-select">
                <option value="HNL">Lempiras (HNL)</option>
                <option value="USD">Dólares (USD)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Saldo Disponible</label>
              <input type="number" name="saldo_disponible" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Saldo Mínimo (Seguridad)</label>
              <input type="number" name="saldo_minimo" id="cuenta-saldo-min" class="form-control" step="0.01" min="0" value="0"
                     title="Monto que siempre debe quedar en la cuenta — el sistema bloqueará transferencias que lo afecten">
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha del Saldo</label>
              <input type="text" name="fecha_saldo" class="form-control date-input">
            </div>
            <div class="col-12">
              <label class="form-label">Notas</label>
              <input type="text" name="notas" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-ahdeco"><i class="fas fa-save"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: Actualizar Saldo ── -->
<div class="modal fade" id="modalSaldo" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Actualizar Saldo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-saldo">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="actualizar_saldo">
        <input type="hidden" name="id" id="saldo-id" value="">
        <div class="modal-body">
          <div class="mb-1 fw-bold" style="font-size:.9rem" id="saldo-nombre"></div>
          <div class="mb-3">
            <label class="form-label">Saldo disponible (L.) *</label>
            <input type="number" name="saldo_disponible" id="saldo-val" class="form-control" step="0.01" min="0" required>
          </div>
          <div>
            <label class="form-label">Fecha del saldo *</label>
            <input type="text" name="fecha_saldo" id="saldo-fecha" class="form-control date-input" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-ahdeco"><i class="fas fa-arrows-rotate"></i> Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = "
const _cuentasData  = {$cuentasJsonEsc};
const _csrf         = {$csrfJs};
const _comisionACH  = " . json_encode($comisionACH) . ";

// ── Helpers ──────────────────────────────────────────────────────────
function fmtLps(v) {
  return 'L. ' + parseFloat(v||0).toLocaleString('es-HN',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function ahdPost(data) {
  const fd = new FormData();
  fd.append('csrf_token', _csrf);
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  return fetch('tesoreria.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
    .then(r => r.json());
}

// ── Cuentas modals ──────────────────────────────────────────────────
function openCuentaModal(id) {
  const f = document.getElementById('form-cuenta');
  f.reset();
  document.getElementById('cuenta-id').value = id;
  if (id) {
    const c = _cuentasData.find(x => x.id == id);
    if (c) {
      f.nombre.value          = c.nombre;
      f.banco.value           = c.banco;
      f.numero_cuenta.value   = c.numero_cuenta || '';
      f.tipo_cuenta.value     = c.tipo_cuenta;
      f.moneda.value          = c.moneda;
      f.saldo_disponible.value= c.saldo_disponible;
      f.saldo_minimo.value    = c.saldo_minimo || 0;
      f.fecha_saldo.value     = c.fecha_saldo || '';
      f.notas.value           = c.notas || '';
    }
    document.getElementById('modal-cuenta-title').textContent = 'Editar Cuenta';
  } else {
    document.getElementById('modal-cuenta-title').textContent = 'Nueva Cuenta Bancaria';
  }
  new bootstrap.Modal(document.getElementById('modalCuenta')).show();
}

function openSaldoModal(id, saldo, fecha, nombre) {
  document.getElementById('saldo-id').value    = id;
  document.getElementById('saldo-val').value   = saldo;
  document.getElementById('saldo-fecha').value = fecha || '';
  document.getElementById('saldo-nombre').textContent = nombre;
  new bootstrap.Modal(document.getElementById('modalSaldo')).show();
}

function eliminarCuenta(id, nombre) {
  if (!confirm('¿Desactivar la cuenta «' + nombre + '»?')) return;
  ahdPost({ _action:'eliminar_cuenta', id }).then(r => {
    if (r.success) location.reload(); else Toast.show(r.message, 'danger');
  });
}

function asignarCuenta(opId, cuentaId) {
  ahdPost({ _action:'asignar_cuenta_op', op_id:opId, cuenta_bancaria_id:cuentaId }).then(r => {
    Toast.show(r.success ? 'Banco asignado' : r.message, r.success ? 'success' : 'danger');
  });
}

// ── Movimientos: cálculo en tiempo real ──────────────────────────────
function calcularMovimiento() {
  const origenId  = parseInt(document.getElementById('mov-origen').value)  || 0;
  const destinoId = parseInt(document.getElementById('mov-destino').value) || 0;
  const monto     = parseFloat(document.getElementById('mov-monto').value) || 0;

  const origen  = _cuentasData.find(x => x.id == origenId);
  const destino = _cuentasData.find(x => x.id == destinoId);
  const preview = document.getElementById('mov-preview');

  if (!origen || !destino || monto <= 0 || origenId === destinoId) {
    preview.style.display = 'none';
    document.getElementById('mov-total-sale').textContent = '—';
    return;
  }

  // ACH: auto-fill cuando son bancos distintos
  const mismoBanco = origen.banco === destino.banco;
  const comInput   = document.getElementById('mov-comision');
  const achTag     = document.getElementById('mov-ach-tag');
  const achHint    = document.getElementById('mov-ach-hint');

  if (mismoBanco) {
    comInput.value = '0';
    achTag.style.display = 'none';
    achHint.textContent  = 'Mismo banco — sin comisión ACH.';
  } else {
    if (parseFloat(comInput.value) === 0 || comInput.dataset.touched !== '1') {
      comInput.value = _comisionACH.toFixed(2);
    }
    achTag.style.display = '';
    achHint.textContent  = 'Comisión ACH aplicada por transferencia entre bancos distintos.';
  }

  const comision  = parseFloat(comInput.value) || 0;
  const totalSale = monto + comision;
  const saldoEfect = parseFloat(origen.saldo_disponible) - parseFloat(origen.saldo_minimo || 0);
  const valido     = totalSale <= saldoEfect;

  const nuevoOrigen  = parseFloat(origen.saldo_disponible)  - totalSale;
  const nuevoDestino = parseFloat(destino.saldo_disponible) + monto;

  // Total sale display
  document.getElementById('mov-total-sale').textContent = fmtLps(totalSale);

  // Preview cells
  document.getElementById('prev-origen-nombre').textContent    = origen.nombre + ' — ' + origen.banco;
  document.getElementById('prev-origen-actual').textContent    = fmtLps(origen.saldo_disponible);
  document.getElementById('prev-origen-minimo').textContent    = fmtLps(origen.saldo_minimo || 0);
  document.getElementById('prev-origen-efect').textContent     = fmtLps(saldoEfect);
  document.getElementById('prev-total-sale-det').textContent   = '− ' + fmtLps(totalSale);
  document.getElementById('prev-origen-nuevo').textContent     = fmtLps(nuevoOrigen);
  document.getElementById('prev-origen-nuevo').style.color     = nuevoOrigen >= 0 ? 'var(--ah-green)' : 'var(--ah-red)';

  document.getElementById('prev-destino-nombre').textContent   = destino.nombre + ' — ' + destino.banco;
  document.getElementById('prev-destino-actual').textContent   = fmtLps(destino.saldo_disponible);
  document.getElementById('prev-destino-recibe').textContent   = '+ ' + fmtLps(monto);
  document.getElementById('prev-destino-nuevo').textContent    = fmtLps(nuevoDestino);

  document.getElementById('prev-minimo-row').style.display = (origen.saldo_minimo > 0) ? '' : 'none';

  // Borde del card origen según validez
  document.getElementById('prev-card-origen').style.borderColor = valido ? 'var(--ah-green)' : 'var(--ah-red)';

  // Estado alert
  const statusEl = document.getElementById('mov-status');
  if (valido) {
    statusEl.className = 'alert alert-success py-2 mb-0';
    statusEl.innerHTML = '<i class=\"fas fa-circle-check\"></i> Transferencia válida. El saldo operativo es suficiente.';
  } else {
    const deficit = totalSale - saldoEfect;
    statusEl.className = 'alert alert-danger py-2 mb-0';
    statusEl.innerHTML = '<i class=\"fas fa-circle-xmark\"></i> Saldo operativo insuficiente. Faltan <strong>' + fmtLps(deficit) + '</strong> para cubrir monto + comisión.';
  }

  document.getElementById('btn-simular').disabled  = !valido;
  document.getElementById('btn-ejecutar').disabled = !valido;
  preview.style.display = '';
}

// Marcar comision como editada manualmente
document.getElementById('mov-comision')?.addEventListener('input', function(){
  this.dataset.touched = '1';
  calcularMovimiento();
});

// ── Crear movimiento ─────────────────────────────────────────────────
function submitMovimiento(ejecutar) {
  const origenId  = document.getElementById('mov-origen').value;
  const destinoId = document.getElementById('mov-destino').value;
  const monto     = document.getElementById('mov-monto').value;
  const comision  = document.getElementById('mov-comision').value;
  const desc      = document.getElementById('mov-descripcion').value;

  const label = ejecutar ? 'Ejecutar' : 'Simular';
  if (!confirm(ejecutar
    ? '¿Ejecutar la transferencia? Se actualizarán los saldos inmediatamente.'
    : '¿Guardar como simulación? Podrá ejecutarla después desde el historial.')) return;

  ahdPost({ _action:'crear_movimiento', cuenta_origen_id:origenId, cuenta_destino_id:destinoId,
            monto, comision_ach:comision, descripcion:desc, ejecutar:ejecutar?'1':'0' })
    .then(r => {
      if (r.success) { Toast.show(r.message, 'success'); setTimeout(()=>location.reload(), 1000); }
      else Toast.show(r.message, 'danger');
    });
}

// ── Ejecutar / anular desde historial ───────────────────────────────
function ejecutarMovimiento(id) {
  if (!confirm('¿Ejecutar esta simulación? Se actualizarán los saldos.')) return;
  ahdPost({ _action:'ejecutar_movimiento', id }).then(r => {
    if (r.success) { Toast.show(r.message, 'success'); setTimeout(()=>location.reload(), 1000); }
    else Toast.show(r.message, 'danger');
  });
}

function anularMovimiento(id) {
  if (!confirm('¿Anular este movimiento simulado?')) return;
  ahdPost({ _action:'anular_movimiento', id }).then(r => {
    if (r.success) { Toast.show(r.message, 'success'); location.reload(); }
    else Toast.show(r.message, 'danger');
  });
}

bindAjaxForm('form-cuenta', r => { Toast.show(r.message,'success'); setTimeout(()=>location.reload(),900); });
bindAjaxForm('form-saldo',  r => { Toast.show(r.message,'success'); setTimeout(()=>location.reload(),900); });
";
include __DIR__ . '/includes/footer.php';
?>
