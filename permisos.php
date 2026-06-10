<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$pagina = 'Permisos y Ausencias';

$TIPOS = [
    'enfermedad'   => ['label' => 'Enfermedad / Incapacidad', 'icon' => 'fa-kit-medical',     'color' => '#dc2626'],
    'personal'     => ['label' => 'Personal',                  'icon' => 'fa-user-clock',      'color' => '#2563eb'],
    'maternidad'   => ['label' => 'Maternidad',                'icon' => 'fa-baby',             'color' => '#7c3aed'],
    'paternidad'   => ['label' => 'Paternidad',                'icon' => 'fa-person',           'color' => '#0891b2'],
    'luto'         => ['label' => 'Luto / Duelo',              'icon' => 'fa-heart-crack',      'color' => '#475569'],
    'capacitacion' => ['label' => 'Capacitación / Formación',  'icon' => 'fa-graduation-cap',   'color' => '#16a34a'],
    'otro'         => ['label' => 'Otro',                      'icon' => 'fa-ellipsis',         'color' => '#64748b'],
];

// ── AJAX ──────────────────────────────────────────────────────────
if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';

    if ($_act === 'crear') {
        $empId   = (int)$_POST['empleado_id'];
        $inicio  = $_POST['fecha_inicio'];
        $fin     = $_POST['fecha_fin'];
        $dias    = (float)$_POST['dias'];
        $tipo    = array_key_exists($_POST['tipo'] ?? '', $TIPOS) ? $_POST['tipo'] : 'personal';
        $goce    = !empty($_POST['con_goce']) ? 1 : 0;
        $motivo  = trim($_POST['motivo']);
        $notas   = trim($_POST['notas'] ?? '');
        if (!$motivo) jsonErr('El motivo es obligatorio.');
        $num = generarNumero($pdo, 'permisos', 'numero', getConfig($pdo, 'prefijo_per', 'PER'));
        $pdo->prepare("INSERT INTO permisos (numero,empleado_id,fecha_solicitud,fecha_inicio,fecha_fin,dias,tipo,con_goce,motivo,notas)
            VALUES (?,?,CURDATE(),?,?,?,?,?,?,?)")
            ->execute([$num, $empId, $inicio, $fin, $dias, $tipo, $goce, $motivo, $notas]);
        $perId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INSERT', 'permisos', $perId, "Permiso creado: $num ($tipo)");
        jsonOk(['id' => $perId], 'Permiso registrado.');
    }

    if ($_act === 'cambiar_estado') {
        $pid    = (int)$_POST['id'];
        $estado = in_array($_POST['estado'], ['aprobado','rechazado']) ? $_POST['estado'] : '';
        if (!$estado) jsonErr('Estado inválido.');
        $pdo->prepare("UPDATE permisos SET estado=?, aprobador_id=? WHERE id=?")
            ->execute([$estado, currentUser()['id'], $pid]);
        registrarAuditoria($pdo, 'UPDATE', 'permisos', $pid, "Estado permiso → $estado");
        jsonOk([], 'Estado actualizado.');
    }

    if ($_act === 'eliminar') {
        $peid = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM permisos WHERE id=? AND estado='pendiente'")->execute([$peid]);
        registrarAuditoria($pdo, 'DELETE', 'permisos', $peid, 'Permiso eliminado');
        jsonOk([], 'Permiso eliminado.');
    }

    jsonErr('Acción desconocida.');
}

// ── Carga ──────────────────────────────────────────────────────────
$empleados = $pdo->query("SELECT id, CONCAT(nombre,' ',apellidos) AS nombre, cargo
    FROM empleados WHERE activo=1 ORDER BY apellidos, nombre")->fetchAll();

$permiso = null;
if ($action === 'ver' && $id) {
    $s = $pdo->prepare("SELECT p.*, CONCAT(e.nombre,' ',e.apellidos) AS emp_nombre, e.cargo,
        CONCAT(a.nombre,' ',a.apellidos) AS aprobador_nombre
        FROM permisos p JOIN empleados e ON p.empleado_id=e.id
        LEFT JOIN empleados a ON p.aprobador_id=a.id WHERE p.id=?");
    $s->execute([$id]); $permiso = $s->fetch();
    if (!$permiso) { header('Location: permisos.php'); exit; }
}

$lista = [];
if ($action === 'list') {
    $lista = $pdo->query("SELECT p.*, CONCAT(e.nombre,' ',e.apellidos) AS emp_nombre, e.cargo
        FROM permisos p JOIN empleados e ON p.empleado_id=e.id
        ORDER BY p.id DESC")->fetchAll();
}

// Resumen de días por empleado este año
$resumen = $pdo->query("SELECT p.empleado_id, CONCAT(e.nombre,' ',e.apellidos) AS emp_nombre,
    p.tipo, SUM(p.dias) AS total_dias
    FROM permisos p JOIN empleados e ON p.empleado_id=e.id
    WHERE p.estado='aprobado' AND YEAR(p.fecha_inicio)=YEAR(CURDATE())
    GROUP BY p.empleado_id, p.tipo ORDER BY e.apellidos")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="fas fa-user-clock"></i> Permisos y Ausencias</h1>
  <?php if ($action === 'list'): ?>
  <button class="btn-ahdeco" data-bs-toggle="modal" data-bs-target="#modal-nuevo">
    <i class="fas fa-plus"></i> Nuevo Permiso
  </button>
  <?php else: ?>
  <a href="permisos.php" class="btn-ahdeco-outline"><i class="fas fa-arrow-left"></i> Volver</a>
  <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>

<!-- Resumen anual -->
<?php if ($resumen): ?>
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-chart-pie"></i> Días de Permiso Aprobados — <?= date('Y') ?></div>
  <div class="card-body p-0">
    <table class="table-ahdeco w-100">
      <thead><tr><th>Empleado</th><?php foreach ($TIPOS as $k => $t): ?><th class="text-center" style="font-size:.7rem"><?= $t['label'] ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php
        $byEmp = [];
        foreach ($resumen as $r) $byEmp[$r['emp_nombre']][$r['tipo']] = $r['total_dias'];
        foreach ($byEmp as $nombre => $dias):
        ?>
        <tr>
          <td><?= htmlspecialchars($nombre) ?></td>
          <?php foreach ($TIPOS as $k => $t): ?>
          <td class="text-center font-mono" style="font-size:.82rem">
            <?= isset($dias[$k]) ? $dias[$k] : '<span style="color:var(--text-3)">—</span>' ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Lista de permisos -->
<div class="card">
  <div class="card-body p-0">
    <table id="tbl-per" class="table-ahdeco w-100">
      <thead><tr><th>Número</th><th>Empleado</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Días</th><th>Goce</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($lista as $p):
          $ti = $TIPOS[$p['tipo']] ?? $TIPOS['otro'];
        ?>
        <tr>
          <td class="font-mono"><?= htmlspecialchars($p['numero']) ?></td>
          <td><?= htmlspecialchars($p['emp_nombre']) ?></td>
          <td>
            <i class="fas <?= $ti['icon'] ?>" style="color:<?= $ti['color'] ?>"></i>
            <span style="font-size:.82rem"><?= $ti['label'] ?></span>
          </td>
          <td><?= fmtFecha($p['fecha_inicio']) ?></td>
          <td><?= fmtFecha($p['fecha_fin']) ?></td>
          <td class="font-mono"><?= $p['dias'] ?></td>
          <td><?= $p['con_goce'] ? '<span class="badge bg-success">Con goce</span>' : '<span class="badge bg-warning text-dark">Sin goce</span>' ?></td>
          <td><?= estadoBadge($p['estado']) ?></td>
          <td>
            <a href="?action=ver&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fas fa-eye"></i></a>
            <?php if ($p['estado'] === 'pendiente'): ?>
            <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-1"
              onclick="deleteRecord('permisos.php',<?= $p['id'] ?>,()=>location.reload())">
              <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No hay permisos registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($action === 'ver' && $permiso):
  $ti = $TIPOS[$permiso['tipo']] ?? $TIPOS['otro'];
?>
<div class="card mb-3">
  <div class="card-header">
    <i class="fas <?= $ti['icon'] ?>" style="color:<?= $ti['color'] ?>"></i>
    <strong><?= htmlspecialchars($permiso['numero']) ?></strong> — <?= $ti['label'] ?> — <?= estadoBadge($permiso['estado']) ?>
    <div class="ms-auto d-flex gap-2">
      <?php if ($permiso['estado'] === 'pendiente'): ?>
      <button class="btn btn-sm btn-success" onclick="cambiarEstPer(<?= $id ?>,'aprobado')"><i class="fas fa-check"></i> Aprobar</button>
      <button class="btn btn-sm btn-danger"  onclick="cambiarEstPer(<?= $id ?>,'rechazado')"><i class="fas fa-times"></i> Rechazar</button>
      <?php endif; ?>
      <a href="/Administracion/print.php?tipo=per&id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3" style="font-size:.85rem">
      <div class="col-md-4"><strong>Empleado:</strong> <?= htmlspecialchars($permiso['emp_nombre']) ?></div>
      <div class="col-md-4"><strong>Cargo:</strong> <?= htmlspecialchars($permiso['cargo'] ?? '—') ?></div>
      <div class="col-md-4"><strong>F. Solicitud:</strong> <?= fmtFecha($permiso['fecha_solicitud']) ?></div>
      <div class="col-md-3"><strong>Inicio:</strong> <?= fmtFecha($permiso['fecha_inicio']) ?></div>
      <div class="col-md-3"><strong>Fin:</strong> <?= fmtFecha($permiso['fecha_fin']) ?></div>
      <div class="col-md-3"><strong>Días:</strong> <span class="font-mono fw-bold"><?= $permiso['dias'] ?></span></div>
      <div class="col-md-3"><strong>Goce de sueldo:</strong> <?= $permiso['con_goce'] ? 'Sí' : 'No' ?></div>
      <div class="col-12"><strong>Motivo:</strong> <?= htmlspecialchars($permiso['motivo']) ?></div>
      <?php if ($permiso['aprobador_nombre']): ?>
      <div class="col-12"><strong>Gestionado por:</strong> <?= htmlspecialchars($permiso['aprobador_nombre']) ?></div>
      <?php endif; ?>
      <?php if ($permiso['notas']): ?>
      <div class="col-12"><strong>Notas:</strong> <?= htmlspecialchars($permiso['notas']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Nuevo Permiso -->
<div class="modal fade" id="modal-nuevo" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-clock"></i> Registrar Permiso / Ausencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-per">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="_action" value="crear">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Empleado *</label>
              <select name="empleado_id" class="form-select" required>
                <option value="">— Seleccione —</option>
                <?php foreach ($empleados as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de permiso *</label>
              <select name="tipo" class="form-select" required>
                <?php foreach ($TIPOS as $k => $t): ?>
                <option value="<?= $k ?>"><?= $t['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha Inicio *</label>
              <input type="text" name="fecha_inicio" id="per-inicio" class="form-control date-input" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha Fin *</label>
              <input type="text" name="fecha_fin" id="per-fin" class="form-control date-input" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Días</label>
              <input type="number" name="dias" id="per-dias" class="form-control" step="0.5" min="0.5" value="1" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="con_goce" id="chk-goce" value="1" checked>
                <label class="form-check-label" for="chk-goce">Con goce</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Motivo / Justificación *</label>
              <textarea name="motivo" class="form-control" rows="2" required placeholder="Descripción del motivo del permiso…"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Notas internas</label>
              <input type="text" name="notas" class="form-control" placeholder="Observaciones adicionales (solo uso interno)">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-ahdeco" onclick="crearPermiso()"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
initDataTable('#tbl-per');

function calcDiasPer() {
  const ini = document.getElementById('per-inicio')._flatpickr?.selectedDates[0];
  const fin = document.getElementById('per-fin')._flatpickr?.selectedDates[0];
  if (!ini || !fin || fin < ini) return;
  let dias = 0, cur = new Date(ini);
  while (cur <= fin) { if (cur.getDay() !== 0 && cur.getDay() !== 6) dias++; cur.setDate(cur.getDate()+1); }
  document.getElementById('per-dias').value = dias || 1;
}
document.getElementById('per-inicio')?.addEventListener('change', calcDiasPer);
document.getElementById('per-fin')?.addEventListener('change', calcDiasPer);

async function crearPermiso() {
  const r = await post('permisos.php', new FormData(document.getElementById('form-per')));
  if (r.success) { Toast.show(r.message,'success'); setTimeout(()=>location.href='permisos.php?action=ver&id='+r.id,800); }
  else Toast.show(r.message,'error');
}

async function cambiarEstPer(id, estado) {
  const fd = new FormData();
  fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
  fd.append('_action','cambiar_estado'); fd.append('id',id); fd.append('estado',estado);
  const r = await post('permisos.php', fd);
  if (r.success) { Toast.show(r.message,'success'); setTimeout(()=>location.reload(),800); }
  else Toast.show(r.message,'error');
}
JS;
include __DIR__ . '/includes/footer.php';
?>
