<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pagina = 'Firmantes en Documentos';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['_action'] ?? '') === 'guardar') {
        $data = $_POST['firmantes'] ?? [];
        $stmt = $pdo->prepare("INSERT INTO firmantes (documento, posicion, empleado_id, etiqueta)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE empleado_id=VALUES(empleado_id), etiqueta=VALUES(etiqueta)");
        foreach ($data as $doc => $positions) {
            foreach ($positions as $pos => $vals) {
                $empId   = !empty($vals['empleado_id']) ? (int)$vals['empleado_id'] : null;
                $etiqueta = trim($vals['etiqueta'] ?? '');
                $stmt->execute([$doc, (int)$pos, $empId, $etiqueta]);
            }
        }
        jsonOk([], 'Firmantes actualizados correctamente.');
    }
    jsonErr('Acción desconocida.');
}

// Cargar firmantes existentes
$rows = $pdo->query("SELECT f.*, CONCAT(e.nombre,' ',e.apellidos) AS emp_nombre
    FROM firmantes f LEFT JOIN empleados e ON f.empleado_id = e.id
    ORDER BY f.documento, f.posicion")->fetchAll();

$firmantesMap = [];
foreach ($rows as $r) {
    $firmantesMap[$r['documento']][$r['posicion']] = $r;
}

$empleados = $pdo->query("SELECT id, CONCAT(nombre,' ',apellidos) AS nombre, cargo
    FROM empleados WHERE activo=1 ORDER BY apellidos, nombre")->fetchAll();

$docTypes = [
    'sc'         => ['label' => 'Solicitud de Compra',        'icon' => 'fa-cart-plus'],
    'oc'         => ['label' => 'Orden de Compra',             'icon' => 'fa-file-circle-check'],
    'op'         => ['label' => 'Orden de Pago',               'icon' => 'fa-money-bill-transfer'],
    'pl'         => ['label' => 'Planilla Quincenal',          'icon' => 'fa-users'],
    'gv'         => ['label' => 'Gasto de Viaje / Viáticos',   'icon' => 'fa-plane'],
    'sg'         => ['label' => 'Solicitud de Gastos',         'icon' => 'fa-receipt'],
    'pa'         => ['label' => 'Planilla de Asignación',      'icon' => 'fa-list-check'],
    'pb'         => ['label' => 'Planilla de Beneficios',      'icon' => 'fa-gift'],
    'vac'        => ['label' => 'Solicitud de Vacaciones',     'icon' => 'fa-umbrella-beach'],
    'per'        => ['label' => 'Permiso / Ausencia',          'icon' => 'fa-calendar-xmark'],
    'constancia' => ['label' => 'Constancias y Referencias',   'icon' => 'fa-file-contract'],
    'liq'        => ['label' => 'Liquidación de Personal',     'icon' => 'fa-file-invoice'],
    'cc'         => ['label' => 'Caja Chica',                 'icon' => 'fa-cash-register'],
    'memo'       => ['label' => 'Memorándum',                 'icon' => 'fa-file-lines'],
    'circ'       => ['label' => 'Circular',                   'icon' => 'fa-bullhorn'],
    'com'        => ['label' => 'Comunicado',                 'icon' => 'fa-envelope-open-text'],
    'acta'       => ['label' => 'Acta de Reunión',            'icon' => 'fa-clipboard-list'],
];

include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1><i class="fas fa-file-signature"></i> Firmantes en Documentos</h1>
</div>
<p style="font-size:.84rem;color:var(--text-2);margin-bottom:1.5rem;">
  Configure qué empleados aparecen en cada bloque de firma de los PDF. Si no asigna un empleado, se mostrará la <strong>etiqueta</strong> como texto de referencia.
</p>

<form id="form-firmantes">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="_action" value="guardar">

  <div class="row g-3">
    <?php foreach ($docTypes as $doc => $info): ?>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header">
          <i class="fas <?= $info['icon'] ?>"></i>
          <?= $info['label'] ?>
          <span class="badge bg-secondary ms-1" style="font-size:.65rem;letter-spacing:.06em"><?= strtoupper($doc) ?></span>
        </div>
        <div class="card-body p-0">
          <table class="table-ahdeco w-100 mb-0">
            <thead>
              <tr>
                <th style="width:2.5rem;text-align:center">#</th>
                <th>Empleado</th>
                <th>Etiqueta / Cargo</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($pos = 1; $pos <= 3; $pos++):
                $f = $firmantesMap[$doc][$pos] ?? null; ?>
              <tr>
                <td style="text-align:center;font-weight:600;color:var(--text-3)"><?= $pos ?></td>
                <td>
                  <select name="firmantes[<?= $doc ?>][<?= $pos ?>][empleado_id]" class="form-select form-select-sm">
                    <option value="">— sin asignar —</option>
                    <?php foreach ($empleados as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($f && $f['empleado_id'] == $e['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($e['nombre']) ?><?= $e['cargo'] ? ' · ' . htmlspecialchars($e['cargo']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <input type="text"
                    name="firmantes[<?= $doc ?>][<?= $pos ?>][etiqueta]"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars($f['etiqueta'] ?? '') ?>"
                    placeholder="ej: Dirección Ejecutiva">
                </td>
              </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4 d-flex gap-2 align-items-center">
    <button type="button" class="btn-ahdeco" onclick="saveFirmantes()">
      <i class="fas fa-save"></i> Guardar cambios
    </button>
    <span id="save-status" style="font-size:.82rem;color:var(--text-3)"></span>
  </div>
</form>

<?php
$extraJs = <<<'JS'
async function saveFirmantes() {
  const btn = document.querySelector('.btn-ahdeco');
  const status = document.getElementById('save-status');
  btn.disabled = true;
  status.textContent = 'Guardando…';
  try {
    const r = await post('firmantes.php', new FormData(document.getElementById('form-firmantes')));
    if (r.success) {
      Toast.show(r.message, 'success');
      status.textContent = '✓ Guardado';
      setTimeout(() => status.textContent = '', 3000);
    } else {
      Toast.show(r.message, 'error');
      status.textContent = '';
    }
  } finally {
    btn.disabled = false;
  }
}
JS;
include __DIR__ . '/includes/footer.php';
?>
