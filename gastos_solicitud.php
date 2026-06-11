<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$pagina = 'Solicitud de Gastos';

// ── AJAX ──────────────────────────────────────────────────────────
if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';

    if ($_act === 'guardar') {
        $sgId        = (int)($_POST['id'] ?? 0);
        $empleadoId  = (int)$_POST['empleado_id'];
        $firmante1Id = !empty($_POST['firmante1_id']) ? (int)$_POST['firmante1_id'] : $empleadoId;
        $d = [
            'fecha'        => $_POST['fecha'],
            'empleado_id'  => $empleadoId,
            'firmante1_id' => $firmante1Id,
            'descripcion'  => trim($_POST['descripcion']),
            'proyecto_id'  => $_POST['proyecto_id'] ?: null,
            'viaje_id'     => $_POST['viaje_id'] ?: null,
            'notas'        => trim($_POST['notas'] ?? ''),
        ];

        $items = json_decode($_POST['items'] ?? '[]', true);
        $total = array_sum(array_column($items, 'monto'));

        $pdo->beginTransaction();
        try {
            if ($sgId) {
                $pdo->prepare("UPDATE solicitud_gastos SET fecha=?,empleado_id=?,firmante1_id=?,descripcion=?,proyecto_id=?,viaje_id=?,monto_total=?,notas=? WHERE id=?")
                    ->execute([$d['fecha'],$d['empleado_id'],$d['firmante1_id'],$d['descripcion'],$d['proyecto_id'],$d['viaje_id'],$total,$d['notas'],$sgId]);
                $pdo->prepare("DELETE FROM solicitud_gastos_detalle WHERE solicitud_id=?")->execute([$sgId]);
            } else {
                $num = generarNumero($pdo,'solicitud_gastos','numero',getConfig($pdo,'prefijo_sg','SG'));
                $pdo->prepare("INSERT INTO solicitud_gastos (numero,fecha,empleado_id,firmante1_id,descripcion,proyecto_id,viaje_id,monto_total,notas,estado)
                    VALUES (?,?,?,?,?,?,?,?,?,'pendiente')")
                    ->execute([$num,$d['fecha'],$d['empleado_id'],$d['firmante1_id'],$d['descripcion'],$d['proyecto_id'],$d['viaje_id'],$total,$d['notas']]);
                $sgId = $pdo->lastInsertId();
            }
            foreach ($items as $item) {
                $pdo->prepare("INSERT INTO solicitud_gastos_detalle (solicitud_id,fecha,descripcion,cuenta_id,monto)
                    VALUES (?,?,?,?,?)")
                    ->execute([$sgId,$item['fecha']??$d['fecha'],$item['descripcion'],$item['cuenta_id']??null,$item['monto']]);
            }
            $pdo->commit();
            registrarAuditoria($pdo,'INSERT','solicitud_gastos',$sgId,'Nueva solicitud de gastos');
            jsonOk(['id'=>$sgId],'Solicitud guardada correctamente.');
        } catch(\Exception $e) { $pdo->rollBack(); jsonErr($e->getMessage()); }
    }

    if ($_act === 'cambiar_estado') {
        $sgId   = (int)$_POST['id'];
        $estado = $_POST['estado'];
        $pdo->prepare("UPDATE solicitud_gastos SET estado=?,aprobador_id=?,fecha_aprobacion=CURDATE() WHERE id=?")
            ->execute([$estado,currentUser()['id'],$sgId]);
        jsonOk([],'Estado actualizado.');
    }
    jsonErr('Acción desconocida.');
}

// ── Load ──────────────────────────────────────────────────────────
$sg = null; $detalle = []; $viaje_link = null;
$prefill_viaje_id = (int)($_GET['viaje_id'] ?? 0);
if (in_array($action,['ver','editar']) && $id) {
    $s = $pdo->prepare("SELECT sg.*,CONCAT(e.nombre,' ',e.apellidos) as emp_nombre
        FROM solicitud_gastos sg JOIN empleados e ON sg.empleado_id=e.id WHERE sg.id=?");
    $s->execute([$id]); $sg = $s->fetch();
    $s2 = $pdo->prepare("SELECT d.*, cc.nombre as cuenta_nombre FROM solicitud_gastos_detalle d
        LEFT JOIN cuentas_contables cc ON d.cuenta_id=cc.id WHERE d.solicitud_id=?");
    $s2->execute([$id]); $detalle = $s2->fetchAll();
    if ($sg && $sg['viaje_id']) {
        $sv = $pdo->prepare("SELECT gv.*, CONCAT(e.nombre,' ',e.apellidos) as emp_nombre FROM gastos_viaje gv JOIN empleados e ON gv.empleado_id=e.id WHERE gv.id=?");
        $sv->execute([$sg['viaje_id']]); $viaje_link = $sv->fetch();
    }
}

// ── Auxiliares ────────────────────────────────────────────────────
$empleados = $pdo->query("SELECT id, CONCAT(nombre,' ',apellidos) as nombre FROM empleados WHERE activo=1 ORDER BY apellidos")->fetchAll();
$proyectos = $pdo->query("SELECT id,nombre FROM proyectos WHERE estado='activo' ORDER BY nombre")->fetchAll();
$cuentas   = $pdo->query("SELECT id,codigo,nombre FROM cuentas_contables WHERE activa=1 AND tipo='gasto' ORDER BY codigo")->fetchAll();
$viajes    = $pdo->query("SELECT id, numero, destino, fecha_salida, viaticos_anticipados FROM gastos_viaje ORDER BY id DESC")->fetchAll();
$listaSolicitudes = [];
if ($action === 'list') {
    $listaSolicitudes = $pdo->query("SELECT sg.*,CONCAT(e.nombre,' ',e.apellidos) as emp_nombre
        FROM solicitud_gastos sg JOIN empleados e ON sg.empleado_id=e.id ORDER BY sg.id DESC")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="fas fa-file-invoice-dollar"></i> Solicitud de Gastos / Reembolsos</h1>
  <?php if ($action === 'list'): ?>
  <a href="?action=nuevo" class="btn-ahdeco"><i class="fas fa-plus"></i> Nueva Solicitud</a>
  <?php else: ?>
  <a href="?" class="btn-ahdeco-outline"><i class="fas fa-arrow-left"></i> Volver</a>
  <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>
<div class="card">
  <div class="card-body p-0">
    <table id="tbl-sg" class="table-ahdeco w-100">
      <thead><tr><th>Número</th><th>Fecha</th><th>Empleado</th><th>Descripción</th><th>Total</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($listaSolicitudes as $r): ?>
        <tr>
          <td class="font-mono"><?= $r['numero'] ?></td>
          <td><?= fmtFecha($r['fecha']) ?></td>
          <td><?= htmlspecialchars($r['emp_nombre']) ?></td>
          <td><?= htmlspecialchars(substr($r['descripcion'],0,60)) ?></td>
          <td class="font-mono"><?= lps($r['monto_total']) ?></td>
          <td><?= estadoBadge($r['estado']) ?></td>
          <td>
            <a href="?action=ver&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fas fa-eye"></i></a>
            <?php if ($r['estado']==='pendiente'): ?>
            <a href="?action=editar&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1"><i class="fas fa-edit"></i></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$listaSolicitudes): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No hay solicitudes registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($action === 'ver' && $sg): ?>
<!-- ── VIEW ── -->

<?php if ($viaje_link): ?>
<div class="trace-chain mb-3">
  <a href="<?= BASE_URL ?>gastos_viajes.php?action=ver&id=<?= $viaje_link['id'] ?>" class="trace-step linked">
    <i class="fas fa-plane-departure"></i>
    <span class="trace-step-label">Anticipo de Viaje</span>
    <span class="trace-step-num"><?= $viaje_link['numero'] ?></span>
    <span class="trace-step-badge"><?= estadoBadge($viaje_link['estado']) ?></span>
  </a>
  <div class="trace-step active">
    <i class="fas fa-file-invoice-dollar"></i>
    <span class="trace-step-label">Liquidación de Gastos</span>
    <span class="trace-step-num"><?= $sg['numero'] ?></span>
    <span class="trace-step-badge"><?= estadoBadge($sg['estado']) ?></span>
  </div>
</div>
<?php endif; ?>

<div id="print-area">
<div class="card mb-3">
  <div class="card-header">
    <i class="fas fa-file-invoice-dollar"></i> <?= $sg['numero'] ?> — <?= estadoBadge($sg['estado']) ?>
    <div class="ms-auto d-flex gap-2 no-print">
      <?php if ($sg['estado']==='pendiente'): ?>
      <button class="btn btn-sm btn-success" onclick="cambiarEstado('gastos_solicitud.php',<?=$sg['id']?>,'aprobada','solicitud_gastos',()=>location.reload())">
        <i class="fas fa-check"></i> Aprobar
      </button>
      <button class="btn btn-sm btn-danger" onclick="cambiarEstado('gastos_solicitud.php',<?=$sg['id']?>,'rechazada','solicitud_gastos',()=>location.reload())">
        <i class="fas fa-times"></i> Rechazar
      </button>
      <?php elseif ($sg['estado']==='aprobada'): ?>
      <button class="btn btn-sm btn-info text-white" onclick="cambiarEstado('gastos_solicitud.php',<?=$sg['id']?>,'pagada','solicitud_gastos',()=>location.reload())">
        <i class="fas fa-money-bill"></i> Pagar
      </button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>print.php?tipo=sg&id=<?= $sg['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-2" style="font-size:.85rem;">
      <div class="col-md-3"><strong>Fecha:</strong> <?= fmtFecha($sg['fecha']) ?></div>
      <div class="col-md-4"><strong>Empleado:</strong> <?= htmlspecialchars($sg['emp_nombre']) ?></div>
      <div class="col-md-5"><strong>Descripción:</strong> <?= htmlspecialchars($sg['descripcion']) ?></div>
      <?php if ($sg['notas']): ?><div class="col-12"><strong>Notas:</strong> <?= htmlspecialchars($sg['notas']) ?></div><?php endif; ?>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header"><i class="fas fa-list"></i> Detalle de Gastos</div>
  <div class="card-body p-0">
    <table class="table-ahdeco w-100">
      <thead><tr><th>#</th><th>Fecha</th><th>Descripción</th><th>Cuenta</th><th>Monto</th></tr></thead>
      <tbody>
        <?php $i=1; foreach ($detalle as $d): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= fmtFecha($d['fecha']??'') ?></td>
          <td><?= htmlspecialchars($d['descripcion']) ?></td>
          <td><?= htmlspecialchars($d['cuenta_nombre']??'—') ?></td>
          <td class="font-mono"><?= lps($d['monto']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4" class="text-end fw-bold">TOTAL</td><td class="font-mono fw-bold"><?= lps($sg['monto_total']) ?></td></tr></tfoot>
    </table>
  </div>
</div>
</div><!-- /print-area -->

<?php else: ?>
<!-- ── FORM ── -->
<div class="card">
  <div class="card-header"><i class="fas fa-plus"></i> <?= $sg ? 'Editar Solicitud: '.$sg['numero'] : 'Nueva Solicitud de Gasto' ?></div>
  <div class="card-body">
    <form id="form-sg" action="gastos_solicitud.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="_action" value="guardar">
      <input type="hidden" name="id" value="<?= $sg['id'] ?? 0 ?>">
      <input type="hidden" name="items" id="items-json">
      <input type="hidden" name="monto_total" id="monto_total">

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Fecha *</label>
          <input type="text" name="fecha" class="form-control date-input" required value="<?= $sg['fecha'] ?? date('Y-m-d') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Empleado *</label>
          <select name="empleado_id" class="form-select" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($empleados as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($sg['empleado_id']??0)==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Descripción General *</label>
          <input type="text" name="descripcion" class="form-control" required value="<?= htmlspecialchars($sg['descripcion']??'') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Firmante (Firma 1 del documento)</label>
          <select name="firmante1_id" class="form-select">
            <option value="">— Mismo que empleado —</option>
            <?php foreach ($empleados as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($sg['firmante1_id']??0)==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Si no selecciona ninguno, se usará el empleado.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Proyecto</label>
          <select name="proyecto_id" class="form-select">
            <option value="">-- General --</option>
            <?php foreach ($proyectos as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($sg['proyecto_id']??0)==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Anticipo de Viaje Relacionado <small class="text-muted">(liquidación de viáticos)</small></label>
          <select name="viaje_id" class="form-select">
            <option value="">— Ninguno —</option>
            <?php
            $sgViajeId = $sg['viaje_id'] ?? $prefill_viaje_id;
            foreach ($viajes as $v):
            ?>
            <option value="<?= $v['id'] ?>" <?= $sgViajeId==$v['id']?'selected':'' ?>>
              <?= $v['numero'] ?> — <?= htmlspecialchars($v['destino']) ?> (<?= fmtFecha($v['fecha_salida']) ?>) — Anticipo: <?= lps($v['viaticos_anticipados']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">Notas / Justificación</label>
          <input type="text" name="notas" class="form-control" value="<?= htmlspecialchars($sg['notas']??'') ?>">
        </div>
      </div>

      <div class="form-section-title mt-3">Detalle de Gastos</div>
      <div class="table-responsive">
        <table class="table-ahdeco w-100" id="tbl-items">
          <thead><tr><th>#</th><th>Fecha</th><th>Descripción</th><th>Cuenta Contable</th><th>Monto (L.)</th><th></th></tr></thead>
          <tbody id="tbody-items">
            <?php
            $initItems = $detalle ?: [['fecha'=>date('Y-m-d'),'descripcion'=>'','cuenta_id'=>'','cuenta_nombre'=>'','monto'=>0]];
            foreach ($initItems as $idx => $it): ?>
            <tr class="detail-row" id="row-<?= $idx ?>">
              <td class="row-num"><?= $idx+1 ?></td>
              <td><input type="text" class="form-control form-control-sm date-input" placeholder="Fecha" value="<?= $it['fecha']??date('Y-m-d') ?>" data-field="fecha"></td>
              <td><input type="text" class="form-control form-control-sm" placeholder="Descripción del gasto" value="<?= htmlspecialchars($it['descripcion']??'') ?>" data-field="descripcion" required></td>
              <td>
                <select class="form-select form-select-sm" data-field="cuenta_id">
                  <option value="">-- Cuenta --</option>
                  <?php foreach ($cuentas as $cc): ?>
                  <option value="<?= $cc['id'] ?>" <?= ($it['cuenta_id']??0)==$cc['id']?'selected':'' ?>><?= $cc['codigo'] ?> - <?= htmlspecialchars($cc['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" class="form-control form-control-sm row-monto text-end" step="0.01" min="0" placeholder="0.00" value="<?= $it['monto']??0 ?>" data-field="monto"></td>
              <td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeDetailRow(this)"><i class="fas fa-trash"></i></button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-end fw-bold">TOTAL:</td>
              <td class="font-mono fw-bold" id="grand-total">L. 0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="addGastoRow()">
        <i class="fas fa-plus"></i> Agregar Línea
      </button>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn-ahdeco"><i class="fas fa-save"></i> Guardar Solicitud</button>
        <a href="?" class="btn btn-outline-secondary btn-sm">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$cuentasJson = json_encode(array_map(fn($c)=>['id'=>$c['id'],'codigo'=>$c['codigo'],'nombre'=>$c['nombre']],$cuentas));
$extraJs = "
initDataTable('#tbl-sg');
recalcTotals();

function addGastoRow() {
  const idx = document.querySelectorAll('#tbody-items .detail-row').length;
  const cuentaOpts = " . $cuentasJson . ".map(c=>`<option value=\"\${c.id}\">\${c.codigo} - \${c.nombre}</option>`).join('');
  const tr = document.createElement('tr');
  tr.className='detail-row';
  tr.innerHTML = `
    <td class=\"row-num\">\${idx+1}</td>
    <td><input type=\"text\" class=\"form-control form-control-sm date-input\" placeholder=\"Fecha\" value=\"".date('Y-m-d')."\" data-field=\"fecha\"></td>
    <td><input type=\"text\" class=\"form-control form-control-sm\" placeholder=\"Descripción\" data-field=\"descripcion\" required></td>
    <td><select class=\"form-select form-select-sm\" data-field=\"cuenta_id\"><option value=\"\">-- Cuenta --</option>\${cuentaOpts}</select></td>
    <td><input type=\"number\" class=\"form-control form-control-sm row-monto text-end\" step=\"0.01\" min=\"0\" placeholder=\"0.00\" value=\"0\" data-field=\"monto\"></td>
    <td><button type=\"button\" class=\"btn btn-sm btn-outline-danger py-0 px-1\" onclick=\"removeDetailRow(this)\"><i class=\"fas fa-trash\"></i></button></td>`;
  document.getElementById('tbody-items').appendChild(tr);
  flatpickr(tr.querySelector('.date-input'), {locale:'es',dateFormat:'Y-m-d',allowInput:true});
  updateDetailRowNumbers('tbody-items');
}

document.getElementById('form-sg')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const items = [];
  document.querySelectorAll('#tbody-items .detail-row').forEach(row => {
    items.push({
      fecha:       row.querySelector('[data-field=fecha]')?.value,
      descripcion: row.querySelector('[data-field=descripcion]')?.value,
      cuenta_id:   row.querySelector('[data-field=cuenta_id]')?.value,
      monto:       row.querySelector('[data-field=monto]')?.value,
    });
  });
  document.getElementById('items-json').value = JSON.stringify(items);
  const total = items.reduce((s,i)=>s+parseFloat(i.monto||0),0);
  document.getElementById('monto_total').value = total.toFixed(2);
  const fd = new FormData(this);
  post('gastos_solicitud.php', fd).then(r => {
    if (r.success) { Toast.show(r.message,'success'); setTimeout(()=>location.href='gastos_solicitud.php',1200); }
    else Toast.show(r.message,'error');
  }).catch(()=>Toast.show('Error de conexión','error'));
});
";
include __DIR__ . '/includes/footer.php';
?>
