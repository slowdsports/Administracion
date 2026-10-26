<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$ocId   = (int)($_GET['oc_id'] ?? 0);
$sgId   = (int)($_GET['sg_id'] ?? 0);
$pagina = 'Orden de Pago';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';
    if ($_act === 'guardar') {
        $opId = (int)($_POST['id'] ?? 0);
        $d = [
            'fecha'=>$_POST['fecha'],'beneficiario'=>trim($_POST['beneficiario']),
            'beneficiario_tipo'=>$_POST['beneficiario_tipo']??'proveedor',
            'proveedor_id'=>$_POST['proveedor_id']?:null,'empleado_id'=>$_POST['empleado_id']?:null,
            'concepto'=>trim($_POST['concepto']),'monto'=>(float)$_POST['monto'],
            'forma_pago'=>$_POST['forma_pago']??'transferencia','numero_cheque'=>trim($_POST['numero_cheque']??''),
            'factura_proveedor'=>trim($_POST['factura_proveedor']??''),
            'banco'=>trim($_POST['banco']??''),'cuenta_bancaria'=>trim($_POST['cuenta_bancaria']??''),
            'banco_tipo'=>$_POST['banco_tipo']??null,
            'orden_compra_id'=>$_POST['orden_compra_id']?:null,
            'solicitud_gastos_id'=>$_POST['solicitud_gastos_id']?:null,
            'cuenta_id'=>$_POST['cuenta_id']?:null,
            'proyecto_id'=>$_POST['proyecto_id']?:null,'notas'=>trim($_POST['notas']??''),
            'cuenta_bancaria_id'=>$_POST['cuenta_bancaria_id']?:null,
        ];
        try {
            if ($opId) {
                $pdo->prepare("UPDATE ordenes_pago SET fecha=?,beneficiario=?,beneficiario_tipo=?,proveedor_id=?,empleado_id=?,concepto=?,monto=?,forma_pago=?,numero_cheque=?,factura_proveedor=?,banco=?,cuenta_bancaria=?,banco_tipo=?,orden_compra_id=?,solicitud_gastos_id=?,cuenta_id=?,proyecto_id=?,notas=?,cuenta_bancaria_id=? WHERE id=?")
                    ->execute([$d['fecha'],$d['beneficiario'],$d['beneficiario_tipo'],$d['proveedor_id'],$d['empleado_id'],$d['concepto'],$d['monto'],$d['forma_pago'],$d['numero_cheque'],$d['factura_proveedor'],$d['banco'],$d['cuenta_bancaria'],$d['banco_tipo'],$d['orden_compra_id'],$d['solicitud_gastos_id'],$d['cuenta_id'],$d['proyecto_id'],$d['notas'],$d['cuenta_bancaria_id'],$opId]);
                jsonOk([],'Orden de pago actualizada.');
            } else {
                // Heredar proceso_id desde la liquidación vinculada
                $procesoId = null;
                if ($d['solicitud_gastos_id']) {
                    $ps = $pdo->prepare("SELECT proceso_id FROM solicitud_gastos WHERE id=?");
                    $ps->execute([$d['solicitud_gastos_id']]);
                    $procesoId = $ps->fetchColumn() ?: null;
                }
                $num = generarNumero($pdo,'ordenes_pago','numero',getConfig($pdo,'prefijo_op','OP'));
                $pdo->prepare("INSERT INTO ordenes_pago (numero,proceso_id,fecha,beneficiario,beneficiario_tipo,proveedor_id,empleado_id,concepto,monto,forma_pago,numero_cheque,factura_proveedor,banco,cuenta_bancaria,banco_tipo,orden_compra_id,solicitud_gastos_id,cuenta_id,proyecto_id,notas,cuenta_bancaria_id,estado,aprobador1_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pendiente',?)")
                    ->execute([$num,$procesoId,$d['fecha'],$d['beneficiario'],$d['beneficiario_tipo'],$d['proveedor_id'],$d['empleado_id'],$d['concepto'],$d['monto'],$d['forma_pago'],$d['numero_cheque'],$d['factura_proveedor'],$d['banco'],$d['cuenta_bancaria'],$d['banco_tipo'],$d['orden_compra_id'],$d['solicitud_gastos_id'],$d['cuenta_id'],$d['proyecto_id'],$d['notas'],$d['cuenta_bancaria_id'],currentUser()['id']]);
                $opId = $pdo->lastInsertId();
                jsonOk(['id'=>$opId],'Orden de pago creada.');
            }
        } catch(\Exception $e){ jsonErr($e->getMessage()); }
    }
    if ($_act === 'cambiar_estado') {
        $opId=(int)$_POST['id'];
        $estado=$_POST['estado'];
        $upd = "UPDATE ordenes_pago SET estado=?";
        $params = [$estado];
        if ($estado==='aprobada') { $upd .= ",aprobador2_id=?"; $params[] = currentUser()['id']; }
        if ($estado==='pagada') { $upd .= ",fecha_pago=CURDATE()"; }
        $upd .= " WHERE id=?"; $params[] = $opId;
        $pdo->prepare($upd)->execute($params);
        jsonOk([],'Estado actualizado.');
    }
    jsonErr('AcciÃ³n desconocida.');
}

// Pre-carga desde Orden de Compra
$ocData = null;
if ($ocId) {
    $s=$pdo->prepare("SELECT oc.*,p.nombre as prov_nombre,sc.descripcion as sc_descripcion FROM ordenes_compra oc JOIN proveedores p ON oc.proveedor_id=p.id LEFT JOIN solicitud_compra sc ON oc.solicitud_id=sc.id WHERE oc.id=?");
    $s->execute([$ocId]); $ocData=$s->fetch();
}

// Pre-carga desde Solicitud de Gastos (liquidación de viáticos)
$sgData = null;
if ($sgId) {
    $s=$pdo->prepare("SELECT sg.*,CONCAT(e.nombre,' ',e.apellidos) as emp_nombre,
        gv.numero as gv_numero, gv.id as gv_id
        FROM solicitud_gastos sg
        JOIN empleados e ON sg.empleado_id=e.id
        LEFT JOIN gastos_viaje gv ON sg.viaje_id=gv.id
        WHERE sg.id=?");
    $s->execute([$sgId]); $sgData=$s->fetch();
}

$op = null;
$traza = ['sc'=>null,'co'=>null,'oc'=>null,'sg'=>null,'gv'=>null];
if (in_array($action,['ver','editar']) && $id) {
    $s=$pdo->prepare("SELECT op.*,p.nombre as prov_nombre FROM ordenes_pago op LEFT JOIN proveedores p ON op.proveedor_id=p.id WHERE op.id=?");
    $s->execute([$id]); $op=$s->fetch();
    if ($action==='ver' && $op) {
        if ($op['orden_compra_id']) {
            $t=$pdo->prepare("SELECT id,numero,estado,solicitud_id,cotizacion_id FROM ordenes_compra WHERE id=?");
            $t->execute([$op['orden_compra_id']]); $traza['oc']=$t->fetch();
            if ($traza['oc']) {
                if ($traza['oc']['solicitud_id']) {
                    $t2=$pdo->prepare("SELECT id,numero,estado FROM solicitud_compra WHERE id=?");
                    $t2->execute([$traza['oc']['solicitud_id']]); $traza['sc']=$t2->fetch();
                }
                if ($traza['oc']['cotizacion_id']) {
                    $t3=$pdo->prepare("SELECT id,numero,estado FROM cotizaciones WHERE id=?");
                    $t3->execute([$traza['oc']['cotizacion_id']]); $traza['co']=$t3->fetch();
                }
            }
        }
        if ($op['solicitud_gastos_id'] ?? null) {
            try {
                $t4=$pdo->prepare("SELECT sg.id,sg.numero,sg.estado,sg.proceso_id,sg.viaje_id,
                    CONCAT(e.nombre,' ',e.apellidos) as emp_nombre
                    FROM solicitud_gastos sg JOIN empleados e ON sg.empleado_id=e.id
                    WHERE sg.id=?");
                $t4->execute([$op['solicitud_gastos_id']]); $traza['sg']=$t4->fetch();
                if ($traza['sg'] && ($traza['sg']['viaje_id'] ?? null)) {
                    $t5=$pdo->prepare("SELECT id,numero,estado,tipo FROM gastos_viaje WHERE id=?");
                    $t5->execute([$traza['sg']['viaje_id']]); $traza['gv']=$t5->fetch();
                }
            } catch (\Exception $e) { /* columnas pendientes de migración */ }
        }
    }
}

$proveedores  = $pdo->query("SELECT id,nombre,banco,banco_cuenta,banco_tipo FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
$empleados    = $pdo->query("SELECT id,CONCAT(nombre,' ',apellidos) as nombre,banco,cuenta_banco FROM empleados WHERE activo=1 ORDER BY apellidos")->fetchAll();
$proyectos    = $pdo->query("SELECT id,nombre FROM proyectos WHERE estado='activo'")->fetchAll();
$cuentas      = $pdo->query("SELECT id,codigo,nombre FROM cuentas_contables WHERE activa=1 AND tipo='gasto' ORDER BY codigo")->fetchAll();
$ocList       = $pdo->query("SELECT id,numero,total FROM ordenes_compra WHERE estado IN ('emitida','parcial') ORDER BY numero DESC")->fetchAll();
try {
    $sgList = $pdo->query("SELECT sg.*, CONCAT(e.nombre,' ',e.apellidos) as emp_nombre
        FROM solicitud_gastos sg JOIN empleados e ON sg.empleado_id=e.id
        WHERE sg.estado='aprobada' ORDER BY sg.id DESC")->fetchAll();
} catch (\Exception $e) {
    $sgList = [];
}
$bancos           = $pdo->query("SELECT nombre FROM cat_bancos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$cuentasBancarias = $pdo->query("SELECT id, CONCAT(nombre,' — ',banco) AS label FROM cuentas_bancarias WHERE activa=1 ORDER BY nombre")->fetchAll();
$lista = [];
if ($action==='list') $lista=$pdo->query("SELECT op.*, p.nombre as prov_nombre FROM ordenes_pago op LEFT JOIN proveedores p ON op.proveedor_id=p.id ORDER BY op.id DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="workflow-steps mb-3">
  <a href="<?= BASE_URL ?>compras_solicitud.php" class="wf-step done"><i class="fas fa-cart-plus"></i><span>Solicitud de Compra</span></a>
  <a href="<?= BASE_URL ?>compras_cotizaciones.php" class="wf-step done"><i class="fas fa-file-lines"></i><span>Cotizaciones</span></a>
  <a href="<?= BASE_URL ?>compras_orden.php" class="wf-step done"><i class="fas fa-file-circle-check"></i><span>Orden de Compra</span></a>
  <div class="wf-step active"><i class="fas fa-money-bill-transfer"></i><span>Orden de Pago</span></div>
  <a href="<?= BASE_URL ?>compras_recepcion.php" class="wf-step"><i class="fas fa-boxes-stacked"></i><span>Nota de RecepciÃ³n</span></a>
</div>

<div class="page-header">
  <h1><i class="fas fa-money-bill-transfer"></i> Orden de Pago</h1>
  <?php if ($action==='list'): ?>
  <a href="?action=nuevo" class="btn-ahdeco"><i class="fas fa-plus"></i> Nueva OP</a>
  <?php else: ?>
  <a href="?" class="btn-ahdeco-outline"><i class="fas fa-arrow-left"></i> Volver</a>
  <?php endif; ?>
</div>

<?php if ($action==='list'): ?>
<div class="card"><div class="card-body p-0">
  <table id="tbl-op" class="table-ahdeco w-100">
    <thead><tr><th>Proceso</th><th>Número</th><th>Fecha</th><th>Beneficiario</th><th>Concepto</th><th>Monto</th><th>Forma Pago</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach($lista as $r): ?>
      <tr>
        <td class="font-mono text-primary fw-bold"><?= ($r['proceso_id'] ?? null) ? formatProceso($r['proceso_id']) : '—' ?></td>
        <td class="font-mono"><?= $r['numero'] ?></td>
        <td><?= fmtFecha($r['fecha']) ?></td>
        <td><?= htmlspecialchars($r['beneficiario']) ?></td>
        <td><?= htmlspecialchars(substr($r['concepto'],0,50)) ?></td>
        <td class="font-mono fw-bold"><?= lps($r['monto']) ?></td>
        <td><?= ucfirst($r['forma_pago']) ?></td>
        <td><?= estadoBadge($r['estado']) ?></td>
        <td>
          <a href="?action=ver&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fas fa-eye"></i></a>
          <?php if($r['estado']==='pendiente'): ?>
          <button class="btn btn-sm btn-outline-success py-0 px-2 ms-1" onclick="cambiarEstado('compras_pago.php',<?=$r['id']?>,'aprobada','ordenes_pago',()=>location.reload())" title="Aprobar"><i class="fas fa-check"></i></button>
          <?php elseif($r['estado']==='aprobada'): ?>
          <button class="btn btn-sm btn-outline-info py-0 px-2 ms-1" onclick="cambiarEstado('compras_pago.php',<?=$r['id']?>,'pagada','ordenes_pago',()=>location.reload())" title="Marcar Pagada"><i class="fas fa-money-bill"></i></button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$lista): ?><tr><td colspan="9" class="text-center text-muted py-4">Sin órdenes de pago</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>

<?php elseif ($action==='ver' && $op): ?>

<!-- Cadena de proceso: viáticos o compras -->
<?php if ($traza['gv'] || $traza['sg']): ?>
<div class=”trace-chain mb-3”>
  <?php if($traza['gv']): ?>
  <a href=”<?= BASE_URL ?>gastos_viajes.php?action=ver&id=<?= $traza['gv']['id'] ?>” class=”trace-step linked”>
    <i class=”fas <?= tipoAnticipoIcon($traza['gv']['tipo'] ?? 'viaje') ?>”></i>
    <span class=”trace-step-label”>Anticipo<?= ($traza['gv']['tipo'] ?? '') === 'compra' ? ' de Compra' : ' de Viaje' ?></span>
    <span class=”trace-step-num”><?= $traza['gv']['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($traza['gv']['estado']) ?></span>
  </a>
  <?php endif; ?>
  <?php if($traza['sg']): ?>
  <a href=”<?= BASE_URL ?>gastos_solicitud.php?action=ver&id=<?= $traza['sg']['id'] ?>” class=”trace-step linked”>
    <i class=”fas fa-file-invoice-dollar”></i>
    <span class=”trace-step-label”>Liquidación de Gastos</span>
    <span class=”trace-step-num”><?= $traza['sg']['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($traza['sg']['estado']) ?></span>
  </a>
  <?php endif; ?>
  <div class=”trace-step active”>
    <i class=”fas fa-money-bill-transfer”></i>
    <span class=”trace-step-label”>Orden de Pago</span>
    <span class=”trace-step-num”><?= $op['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($op['estado']) ?></span>
  </div>
</div>
<?php else: ?>
<div class=”trace-chain”>
  <?php if($traza['sc']): ?>
  <a href=”<?= BASE_URL ?>compras_solicitud.php?action=ver&id=<?= $traza['sc']['id'] ?>” class=”trace-step linked”>
    <i class=”fas fa-cart-plus”></i>
    <span class=”trace-step-label”>Solicitud</span>
    <span class=”trace-step-num”><?= $traza['sc']['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($traza['sc']['estado']) ?></span>
  </a>
  <?php else: ?>
  <div class=”trace-step inactive”>
    <i class=”fas fa-cart-plus”></i>
    <span class=”trace-step-label”>Solicitud</span>
    <span class=”trace-step-num”>—</span>
  </div>
  <?php endif; ?>
  <?php if($traza['co']): ?>
  <a href=”<?= BASE_URL ?>compras_cotizaciones.php?action=ver&id=<?= $traza['co']['id'] ?>” class=”trace-step linked”>
    <i class=”fas fa-file-lines”></i>
    <span class=”trace-step-label”>Cotización</span>
    <span class=”trace-step-num”><?= $traza['co']['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($traza['co']['estado']) ?></span>
  </a>
  <?php else: ?>
  <div class=”trace-step inactive”>
    <i class=”fas fa-file-lines”></i>
    <span class=”trace-step-label”>Cotización</span>
    <span class=”trace-step-num”>—</span>
  </div>
  <?php endif; ?>
  <?php if($traza['oc']): ?>
  <a href=”<?= BASE_URL ?>compras_orden.php?action=ver&id=<?= $traza['oc']['id'] ?>” class=”trace-step linked”>
    <i class=”fas fa-file-circle-check”></i>
    <span class=”trace-step-label”>Orden de Compra</span>
    <span class=”trace-step-num”><?= $traza['oc']['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($traza['oc']['estado']) ?></span>
  </a>
  <?php else: ?>
  <div class=”trace-step inactive”>
    <i class=”fas fa-file-circle-check”></i>
    <span class=”trace-step-label”>Orden de Compra</span>
    <span class=”trace-step-num”>—</span>
  </div>
  <?php endif; ?>
  <div class=”trace-step active”>
    <i class=”fas fa-money-bill-transfer”></i>
    <span class=”trace-step-label”>Orden de Pago</span>
    <span class=”trace-step-num”><?= $op['numero'] ?></span>
    <span class=”trace-step-badge”><?= estadoBadge($op['estado']) ?></span>
  </div>
</div>
<?php endif; ?>

<div id="print-area">
<div class="card">
  <div class="card-header">
    <i class=”fas fa-money-bill-transfer”></i> ORDEN DE PAGO <?= $op['numero'] ?> — <?= estadoBadge($op['estado']) ?>
    <?php if ($op['proceso_id'] ?? null): ?>
    <span class=”badge bg-primary ms-2 font-mono” title=”Número de proceso compartido”>Proceso <?= formatProceso($op['proceso_id']) ?></span>
    <?php endif; ?>
    <div class="ms-auto d-flex gap-2 no-print">
      <?php if($op['estado']==='pendiente'): ?>
      <button class="btn btn-sm btn-success" onclick="cambiarEstado('compras_pago.php',<?=$op['id']?>,'aprobada','ordenes_pago',()=>location.reload())"><i class="fas fa-check"></i> Aprobar</button>
      <?php elseif($op['estado']==='aprobada'): ?>
      <button class="btn btn-sm btn-info text-white" onclick="cambiarEstado('compras_pago.php',<?=$op['id']?>,'pagada','ordenes_pago',()=>location.reload())"><i class="fas fa-money-bill"></i> Pagar</button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>print.php?tipo=op&id=<?= $op['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> PDF</a>
      <?php if ($traza['sc']): ?>
      <a href="<?= BASE_URL ?>print.php?tipo=proceso&id=<?= $traza['sc']['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-layer-group"></i> Proceso Completo</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3" style="font-size:.85rem;">
      <div class="col-md-3"><strong>Fecha:</strong> <?= fmtFecha($op['fecha']) ?></div>
      <div class="col-md-5"><strong>Beneficiario:</strong> <?= htmlspecialchars($op['beneficiario']) ?></div>
      <div class="col-md-2"><strong>Forma de Pago:</strong> <?= ucfirst($op['forma_pago']) ?></div>
      <div class=”col-md-2”><strong>No. Cheque:</strong> <?= $op['numero_cheque'] ?: '—' ?></div>
      <?php if($op['factura_proveedor']): ?>
      <div class=”col-md-2”><strong>No. Factura:</strong> <span class=”font-mono”><?= htmlspecialchars($op['factura_proveedor']) ?></span></div>
      <?php endif; ?>
      <div class=”col-md-3”><strong>Banco:</strong> <?= htmlspecialchars($op['banco']??'—') ?></div>
      <div class=”col-md-3”><strong>Cuenta:</strong> <?= htmlspecialchars($op['cuenta_bancaria']??'—') ?></div>
      <?php if($op['banco_tipo']): ?><div class=”col-md-2”><strong>Tipo Cuenta:</strong> <?= ucfirst($op['banco_tipo']) ?></div><?php endif; ?>
      <div class="col-md-3"><strong>Fecha Pago:</strong> <?= fmtFecha($op['fecha_pago']??'') ?></div>
      <div class="col-12"><strong>Concepto:</strong> <?= htmlspecialchars($op['concepto']) ?></div>
      <?php if($op['notas']): ?><div class="col-12"><strong>Notas:</strong> <?= htmlspecialchars($op['notas']) ?></div><?php endif; ?>
    </div>
    <div class="mt-3 p-3 bg-light rounded text-center">
      <div class="text-muted" style="font-size:.8rem;">MONTO A PAGAR</div>
      <div style="font-size:2rem;font-weight:700;color:var(--ah-blue);"><?= lps($op['monto']) ?></div>
    </div>
    <!-- Firma lines -->
    <div class="row mt-4 text-center d-print-flex">
      <div class="col-4 border-top pt-2 mx-auto">Elaborado por</div>
      <div class="col-4 border-top pt-2 mx-auto">Aprobado por</div>
      <div class="col-4 border-top pt-2 mx-auto">Recibido por</div>
    </div>
  </div>
</div>
</div>

<?php else: ?>
<div class="card"><div class="card-header"><i class="fas fa-plus"></i> Nueva Orden de Pago</div>
<div class="card-body">
  <form id="form-op" action="compras_pago.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="_action" value="guardar">
    <input type="hidden" name="id" value="0">
    <div class="row g-3">
      <div class="col-md-2"><label class="form-label">Fecha *</label><input type="text" name="fecha" class="form-control date-input" required value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-3">
        <label class="form-label">Tipo Beneficiario *</label>
        <select name="beneficiario_tipo" class="form-select" id="tipo-ben" onchange="updateBenType()">
          <option value="proveedor">Proveedor</option>
          <option value="empleado">Empleado</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div class="col-md-4" id="prov-wrap">
        <label class="form-label">Proveedor</label>
        <select name="proveedor_id" class="form-select" id="sel-prov-op" onchange="setBenef(this)">
          <option value="">--</option>
          <?php foreach($proveedores as $p): ?><option value="<?= $p['id'] ?>" <?= ($ocData&&$ocData['proveedor_id']==$p['id'])?'selected':'' ?>><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4" id="emp-wrap" style="display:none">
        <label class="form-label">Empleado</label>
        <select name="empleado_id" class="form-select" onchange="setBenef(this)">
          <option value="">--</option>
          <?php foreach($empleados as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5"><label class="form-label">Beneficiario (Nombre) *</label><input type="text" name="beneficiario" class="form-control" required id="inp-benef" value="<?= htmlspecialchars($ocData['prov_nombre']??'') ?>"></div>
      <div class="col-md-3"><label class="form-label">Monto (L.) *</label><input type="number" name="monto" class="form-control" step="0.01" min="0" required id="inp-monto" value="<?= $ocData?$ocData['total']:0 ?>"></div>
      <div class="col-md-3">
        <label class="form-label">Forma de Pago *</label>
        <select name="forma_pago" class="form-select" id="sel-forma" onchange="toggleCheque()">
          <option value="transferencia">Transferencia Bancaria</option>
          <option value="cheque">Cheque</option>
          <option value="efectivo">Efectivo</option>
        </select>
      </div>
      <div class="col-md-3" id="cheque-wrap" style="display:none"><label class="form-label">Número de Cheque</label><input type="text" name="numero_cheque" class="form-control"></div>
      <div class="col-md-3"><label class="form-label">No. Factura del Proveedor</label><input type="text" name="factura_proveedor" class="form-control" placeholder="Ej: F-0001"></div>
      <div class="col-md-4">
        <label class="form-label">Cuenta Bancaria (Tesorería)</label>
        <select name="cuenta_bancaria_id" class="form-select">
          <option value="">— Sin asignar —</option>
          <?php foreach($cuentasBancarias as $cb): ?>
          <option value="<?= $cb['id'] ?>"><?= htmlspecialchars($cb['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Banco (referencia)</label>
        <select name="banco" class="form-select" id="sel-banco">
          <option value="">-- Seleccionar --</option>
          <?php foreach($bancos as $b): ?>
          <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3"><label class="form-label">No. Cuenta / Referencia</label><input type="text" name="cuenta_bancaria" class="form-control" id="inp-cuenta-bancaria"></div>
      <div class="col-md-2">
        <label class="form-label">Tipo de Cuenta</label>
        <select name="banco_tipo" class="form-select" id="sel-banco-tipo">
          <option value="">--</option>
          <option value="ahorros">Ahorros</option>
          <option value="cheques">Cheques</option>
        </select>
      </div>
      <div class=”col-md-4”>
        <label class=”form-label”>Orden de Compra</label>
        <select name=”orden_compra_id” class=”form-select”>
          <option value=””>-- Ninguna --</option>
          <?php foreach($ocList as $oc): ?><option value=”<?= $oc['id'] ?>” <?= $ocId==$oc['id']?'selected':'' ?>><?= $oc['numero'] ?> — <?= lps($oc['total']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class=”col-md-8”>
        <label class=”form-label”>Liquidación de Viáticos (Solicitud de Gastos) <small class=”text-muted”>— hereda el número de proceso</small></label>
        <select name=”solicitud_gastos_id” class=”form-select” id=”sel-sg-op” onchange=”setSgData(this)”>
          <option value=””>— Ninguna —</option>
          <?php
          $selSgId = $sgData ? $sgData['id'] : ($op['solicitud_gastos_id'] ?? 0);
          foreach($sgList as $sg): ?>
          <option value=”<?= $sg['id'] ?>”
            data-monto=”<?= $sg['monto_total'] ?>”
            data-emp=”<?= htmlspecialchars($sg['emp_nombre']) ?>”
            data-proceso=”<?= ($sg['proceso_id'] ?? null) ? formatProceso($sg['proceso_id']) : '' ?>”
            <?= $selSgId==$sg['id']?'selected':'' ?>>
            <?= $sg['numero'] ?><?= ($sg['proceso_id'] ?? null) ? ' [Proceso '.formatProceso($sg['proceso_id']).']' : '' ?>
            — <?= htmlspecialchars($sg['emp_nombre']) ?>
            — <?= lps($sg['monto_total']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div id=”sg-proceso-badge” class=”mt-1”></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Proyecto</label>
        <select name="proyecto_id" class="form-select"><option value="">--</option><?php foreach($proyectos as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Cuenta Contable</label>
        <select name="cuenta_id" class="form-select"><option value="">--</option><?php foreach($cuentas as $c): ?><option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> <?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-12"><label class="form-label">Concepto / DescripciÃ³n *</label><textarea name="concepto" class="form-control" rows="2" required><?= $ocData?htmlspecialchars($ocData['sc_descripcion'] ?: ('Pago OC '.$ocData['numero'].' - '.$ocData['prov_nombre'])):'' ?></textarea></div>
      <div class="col-12"><label class="form-label">Notas</label><input type="text" name="notas" class="form-control"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-ahdeco"><i class="fas fa-save"></i> Crear Orden de Pago</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Cancelar</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php
$provBancosJson = json_encode(array_column(array_map(fn($p)=>[
    'id'=>$p['id'],'banco'=>$p['banco']??'','cuenta'=>$p['banco_cuenta']??'','tipo'=>$p['banco_tipo']??''
],$proveedores),null,'id'));
$empBancosJson = json_encode(array_column(array_map(fn($e)=>[
    'id'=>$e['id'],'banco'=>$e['banco']??'','cuenta'=>$e['cuenta_banco']??'','tipo'=>''
],$empleados),null,'id'));

// Pre-fill desde sg_id (botón Crear OP desde la liquidación)
$sgPrefill = '';
if ($sgData) {
    $sgPrefill = "
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('sel-sg-op');
    if (sel) { sel.value = '{$sgData['id']}'; $(sel).trigger('change'); setSgData(sel); }
});";
}

$extraJs = "
initDataTable('#tbl-op');
const provBancos=" . $provBancosJson . ";
const empBancos=" . $empBancosJson . ";
function toggleCheque(){
  const v=document.getElementById('sel-forma')?.value;
  document.getElementById('cheque-wrap').style.display=v==='cheque'?'':'none';
}
function updateBenType(){
  const v=document.getElementById('tipo-ben')?.value;
  document.getElementById('prov-wrap').style.display=v==='proveedor'?'':'none';
  document.getElementById('emp-wrap').style.display=v==='empleado'?'':'none';
  const activeSel=v==='proveedor'?document.getElementById('sel-prov-op'):v==='empleado'?document.querySelector('[name=empleado_id]'):null;
  if(activeSel&&activeSel.value) setBenef(activeSel);
}
function setSgData(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !sel.value) {
    document.getElementById('sg-proceso-badge').innerHTML = '';
    return;
  }
  const monto   = opt.dataset.monto;
  const emp     = opt.dataset.emp;
  const proceso = opt.dataset.proceso;
  const inpMonto  = document.getElementById('inp-monto');
  const inpBenef  = document.getElementById('inp-benef');
  const tipoBen   = document.getElementById('tipo-ben');
  if (inpMonto && monto)  inpMonto.value = parseFloat(monto).toFixed(2);
  if (inpBenef && emp)    inpBenef.value = emp;
  if (tipoBen)            tipoBen.value  = 'empleado';
  updateBenType();
  if (proceso) {
    document.getElementById('sg-proceso-badge').innerHTML =
      '<span class=\"badge bg-primary font-mono\">Proceso ' + proceso + ' será asignado a esta OP</span>';
  }
}
function setBenef(sel){
  const name=sel.options[sel.selectedIndex]?.text||'';
  document.getElementById('inp-benef').value=name;
  const tipo=document.getElementById('tipo-ben')?.value;
  const id=sel.value;
  let b=null;
  if(tipo==='proveedor'&&id) b=provBancos[id];
  else if(tipo==='empleado'&&id) b=empBancos[id];
  if(b){
    const selBanco=document.getElementById('sel-banco');
    const inpCuenta=document.getElementById('inp-cuenta-bancaria');
    const selTipo=document.getElementById('sel-banco-tipo');
    if(selBanco&&b.banco){ selBanco.value=b.banco; $(selBanco).trigger('change'); }
    if(inpCuenta) inpCuenta.value=b.cuenta||'';
    if(selTipo){ selTipo.value=b.tipo||''; $(selTipo).trigger('change'); }
  }
}
bindAjaxForm('form-op',r=>{ Toast.show(r.message,'success'); setTimeout(()=>location.href='compras_pago.php',1200); });
" . $sgPrefill . "
";
include __DIR__ . '/includes/footer.php';
?>

