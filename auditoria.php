<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
if (!hasRole(['admin'])) { header('Location: /Administracion/dashboard.php'); exit; }

$pagina = 'Registro de Auditoría';

// ── Filtros ────────────────────────────────────────────────────────
$fUsuario = (int)($_GET['usuario_id'] ?? 0);
$fTabla   = trim($_GET['tabla'] ?? '');
$fAccion  = trim($_GET['accion'] ?? '');
$fDesde   = $_GET['desde'] ?? '';
$fHasta   = $_GET['hasta'] ?? '';
$fBuscar  = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 50;

// ── Export CSV ─────────────────────────────────────────────────────
$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    [$sql, $params] = buildQuery($fUsuario, $fTabla, $fAccion, $fDesde, $fHasta, $fBuscar, false);
    $rows = $pdo->prepare($sql);
    $rows->execute($params);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Fecha/Hora', 'Usuario', 'Acción', 'Tabla', 'Registro ID', 'IP', 'Detalle']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['created_at'], $r['usuario_nombre'] ?? '—',
            $r['accion'], $r['tabla_afectada'], $r['registro_id'], $r['ip'], $r['detalle']]);
    }
    fclose($out);
    exit;
}

function buildQuery(int $fU, string $fT, string $fA, string $fD, string $fH, string $fQ, bool $withLimit = true): array {
    $where  = [];
    $params = [];

    if ($fU)  { $where[] = 'a.usuario_id = ?';           $params[] = $fU; }
    if ($fT)  { $where[] = 'a.tabla_afectada = ?';        $params[] = $fT; }
    if ($fA)  { $where[] = 'a.accion = ?';                $params[] = $fA; }
    if ($fD)  { $where[] = 'DATE(a.created_at) >= ?';     $params[] = $fD; }
    if ($fH)  { $where[] = 'DATE(a.created_at) <= ?';     $params[] = $fH; }
    if ($fQ)  { $where[] = '(a.detalle LIKE ? OR u.nombre LIKE ?)';
                $params[] = "%$fQ%"; $params[] = "%$fQ%"; }

    $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT a.id, a.accion, a.tabla_afectada, a.registro_id, a.ip, a.detalle, a.created_at,
                   COALESCE(u.nombre, '—') AS usuario_nombre
            FROM auditoria a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            $whereStr
            ORDER BY a.id DESC";

    return [$sql, $params];
}

// Total para paginación
[$sqlAll, $paramsAll] = buildQuery($fUsuario, $fTabla, $fAccion, $fDesde, $fHasta, $fBuscar, false);
$total = (int)$pdo->prepare("SELECT COUNT(*) FROM ($sqlAll) t")->execute($paramsAll) ?
    $pdo->prepare("SELECT COUNT(*) FROM ($sqlAll) t")->execute($paramsAll) : 0;
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ($sqlAll) AS t");
$stmtCount->execute($paramsAll);
$total = (int)$stmtCount->fetchColumn();
$pg    = paginate($total, $page, $perPage);

// Registros paginados
[$sql, $params] = buildQuery($fUsuario, $fTabla, $fAccion, $fDesde, $fHasta, $fBuscar, true);
$stmtRows = $pdo->prepare($sql . " LIMIT {$perPage} OFFSET {$pg['offset']}");
$stmtRows->execute($params);
$registros = $stmtRows->fetchAll();

// Listas para filtros
$usuarios = $pdo->query("SELECT DISTINCT u.id, u.nombre FROM auditoria a
    JOIN usuarios u ON a.usuario_id = u.id ORDER BY u.nombre")->fetchAll();
$tablas   = $pdo->query("SELECT DISTINCT tabla_afectada FROM auditoria ORDER BY tabla_afectada")->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas rápidas
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(accion='INSERT') AS inserts,
        SUM(accion='UPDATE') AS updates,
        SUM(accion='DELETE') AS deletes,
        COUNT(DISTINCT usuario_id) AS usuarios,
        COUNT(DISTINCT tabla_afectada) AS tablas
    FROM auditoria
")->fetch();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="fas fa-shield-halved"></i> Registro de Auditoría</h1>
  <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
     class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> Exportar CSV</a>
</div>

<!-- Estadísticas -->
<div class="row g-3 mb-3">
  <?php
  $sts = [
      ['fa-list',        'Total eventos',    'kpi-gray',   $stats['total'],    'auditoria.php'],
      ['fa-plus-circle', 'Creaciones',       'kpi-green',  $stats['inserts'],  'auditoria.php?accion=INSERT'],
      ['fa-pen',         'Modificaciones',   'kpi-yellow', $stats['updates'],  'auditoria.php?accion=UPDATE'],
      ['fa-trash',       'Eliminaciones',    'kpi-red',    $stats['deletes'],  'auditoria.php?accion=DELETE'],
      ['fa-user',        'Usuarios activos', 'kpi-teal',   $stats['usuarios'], 'usuarios.php'],
      ['fa-table',       'Módulos',          'kpi-blue',   $stats['tablas'],   'auditoria.php'],
  ];
  foreach ($sts as [$icon, $label, $color, $val, $href]): ?>
  <div class="col-6 col-md-2">
    <a href="/Administracion/<?= $href ?>" class="kpi-card <?= $color ?>">
      <div class="kpi-icon"><i class="fas <?= $icon ?>"></i></div>
      <div class="kpi-value"><?= number_format($val) ?></div>
      <div class="kpi-label"><?= $label ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Buscar</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($fBuscar) ?>" placeholder="Detalle, usuario…">
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Usuario</label>
        <select name="usuario_id" class="form-select form-select-sm">
          <option value="">Todos</option>
          <?php foreach ($usuarios as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $u['id']==$fUsuario?'selected':'' ?>><?= htmlspecialchars($u['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Módulo / Tabla</label>
        <select name="tabla" class="form-select form-select-sm">
          <option value="">Todas</option>
          <?php foreach ($tablas as $t): ?>
          <option value="<?= $t ?>" <?= $t===$fTabla?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label" style="font-size:.8rem">Acción</label>
        <select name="accion" class="form-select form-select-sm">
          <option value="">Todas</option>
          <option value="INSERT" <?= $fAccion==='INSERT'?'selected':'' ?>>INSERT</option>
          <option value="UPDATE" <?= $fAccion==='UPDATE'?'selected':'' ?>>UPDATE</option>
          <option value="DELETE" <?= $fAccion==='DELETE'?'selected':'' ?>>DELETE</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Desde</label>
        <input type="text" name="desde" class="form-control form-control-sm date-input" value="<?= htmlspecialchars($fDesde) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Hasta</label>
        <input type="text" name="hasta" class="form-control form-control-sm date-input" value="<?= htmlspecialchars($fHasta) ?>">
      </div>
      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-outline-primary btn-sm flex-fill"><i class="fas fa-search"></i></button>
        <a href="auditoria.php" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="fas fa-xmark"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de registros -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-list"></i>
    Eventos
    <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
    <?php if ($pg['pages'] > 1): ?>
    <span class="ms-2" style="font-size:.78rem;color:var(--text-3)">
      Pág. <?= $pg['page'] ?> / <?= $pg['pages'] ?>
    </span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table-ahdeco w-100" style="font-size:.82rem">
      <thead>
        <tr>
          <th style="width:130px">Fecha / Hora</th>
          <th>Usuario</th>
          <th style="width:80px">Acción</th>
          <th>Módulo</th>
          <th style="width:60px">ID</th>
          <th>Detalle</th>
          <th style="width:90px">IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registros as $r):
          $accionColor = match($r['accion']) {
              'INSERT' => 'success',
              'UPDATE' => 'warning',
              'DELETE' => 'danger',
              default  => 'secondary',
          };
          $tablaLabel = str_replace('_', ' ', ucwords($r['tabla_afectada'], '_'));
        ?>
        <tr>
          <td class="font-mono" style="white-space:nowrap;color:var(--text-2)">
            <?= date('d/m/Y', strtotime($r['created_at'])) ?><br>
            <span style="font-size:.75rem"><?= date('H:i:s', strtotime($r['created_at'])) ?></span>
          </td>
          <td><?= htmlspecialchars($r['usuario_nombre']) ?></td>
          <td>
            <span class="badge bg-<?= $accionColor ?>"><?= $r['accion'] ?></span>
          </td>
          <td style="color:var(--text-2)"><?= htmlspecialchars($tablaLabel) ?></td>
          <td class="font-mono text-center" style="color:var(--text-3)"><?= $r['registro_id'] ?></td>
          <td><?= htmlspecialchars($r['detalle'] ?? '—') ?></td>
          <td class="font-mono" style="font-size:.75rem;color:var(--text-3)"><?= htmlspecialchars($r['ip']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$registros): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No hay registros con los filtros aplicados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($pg['pages'] > 1):
    $baseUrl = '?' . http_build_query(array_diff_key($_GET, ['p'=>''])); ?>
  <div class="card-body border-top d-flex justify-content-center gap-1 flex-wrap py-2">
    <?php if ($pg['page'] > 1): ?>
    <a href="<?= $baseUrl ?>&p=<?= $pg['page']-1 ?>" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>

    <?php
    $start = max(1, $pg['page'] - 3);
    $end   = min($pg['pages'], $pg['page'] + 3);
    if ($start > 1) echo '<span class="btn btn-sm disabled">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
    <a href="<?= $baseUrl ?>&p=<?= $i ?>"
       class="btn btn-sm <?= $i === $pg['page'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= $i ?>
    </a>
    <?php endfor;
    if ($end < $pg['pages']) echo '<span class="btn btn-sm disabled">…</span>'; ?>

    <?php if ($pg['page'] < $pg['pages']): ?>
    <a href="<?= $baseUrl ?>&p=<?= $pg['page']+1 ?>" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php
$extraJs = '';
include __DIR__ . '/includes/footer.php';
?>
