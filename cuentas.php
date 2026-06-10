<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pagina = 'CatÃ¡logo de Cuentas';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';
    if ($_act === 'guardar') {
        $cId = (int)($_POST['id'] ?? 0);
        $d = ['codigo'=>trim($_POST['codigo']),'nombre'=>trim($_POST['nombre']),
              'tipo'=>$_POST['tipo'],'subtipo'=>trim($_POST['subtipo']??''),
              'cuenta_padre_id'=>$_POST['cuenta_padre_id']?:null,
              'nivel'=>(int)($_POST['nivel']??1),'descripcion'=>trim($_POST['descripcion']??''),
              'activa'=>isset($_POST['activa'])?1:0];
        try {
            if ($cId) {
                $pdo->prepare("UPDATE cuentas_contables SET codigo=?,nombre=?,tipo=?,subtipo=?,cuenta_padre_id=?,nivel=?,descripcion=?,activa=? WHERE id=?")
                    ->execute([...array_values($d), $cId]);
                jsonOk([],'Cuenta actualizada.');
            } else {
                $pdo->prepare("INSERT INTO cuentas_contables (codigo,nombre,tipo,subtipo,cuenta_padre_id,nivel,descripcion,activa) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute(array_values($d));
                jsonOk(['id'=>$pdo->lastInsertId()],'Cuenta creada.');
            }
        } catch(PDOException $e){ jsonErr($e->getCode()==23000?'El cÃ³digo ya existe.':$e->getMessage()); }
    }
    if ($_act === 'eliminar') {
        $cId=(int)$_POST['id'];
        $pdo->prepare("UPDATE cuentas_contables SET activa=0 WHERE id=?")->execute([$cId]);
        jsonOk([],'Cuenta desactivada.');
    }
    jsonErr('AcciÃ³n desconocida.');
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$cc = null;
if ($action==='editar' && $id) { $s=$pdo->prepare("SELECT * FROM cuentas_contables WHERE id=?"); $s->execute([$id]); $cc=$s->fetch(); }

$cuentas = $pdo->query("SELECT c.*,p.nombre as padre_nombre FROM cuentas_contables c LEFT JOIN cuentas_contables p ON c.cuenta_padre_id=p.id ORDER BY c.codigo")->fetchAll();
$padres  = $pdo->query("SELECT id,codigo,nombre FROM cuentas_contables WHERE nivel=1 ORDER BY codigo")->fetchAll();
$tipos   = ['activo'=>'Activo','pasivo'=>'Pasivo','capital'=>'Capital','ingreso'=>'Ingreso','gasto'=>'Gasto','otro_ingreso'=>'Otro Ingreso','otro_gasto'=>'Otro Gasto'];

include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1><i class="fas fa-book"></i> CatÃ¡logo de Cuentas Contables</h1>
  <div class="d-flex gap-2">
    <button class="btn-ahdeco-outline" data-bs-toggle="collapse" data-bs-target="#filtros"><i class="fas fa-filter"></i> Filtrar</button>
    <button class="btn-ahdeco" data-bs-toggle="modal" data-bs-target="#modal-cuenta"><i class="fas fa-plus"></i> Nueva Cuenta</button>
  </div>
</div>

<!-- Filtro rÃ¡pido por tipo -->
<div class="collapse mb-3" id="filtros">
  <div class="card card-body">
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="">Todas</button>
      <?php foreach($tipos as $k=>$v): ?>
      <button class="btn btn-sm btn-outline-primary filter-btn" data-filter="<?= $k ?>"><?= $v ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table id="tbl-cuentas" class="table-ahdeco w-100">
      <thead><tr><th>CÃ³digo</th><th>Nombre</th><th>Tipo</th><th>Subtipo</th><th>Nivel</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($cuentas as $c): ?>
        <tr data-tipo="<?= $c['tipo'] ?>">
          <td class="font-mono"><?= htmlspecialchars($c['codigo']) ?></td>
          <td><?= str_repeat('&nbsp;&nbsp;&nbsp;', $c['nivel']-1) ?><?= htmlspecialchars($c['nombre']) ?></td>
          <td><?= $tipos[$c['tipo']]??$c['tipo'] ?></td>
          <td><?= htmlspecialchars($c['subtipo']) ?></td>
          <td><span class="badge bg-secondary"><?= $c['nivel'] ?></span></td>
          <td><?= $c['activa'] ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-danger">Inactiva</span>' ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='editCuenta(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" onclick="deleteRecord('cuentas.php',<?= $c['id'] ?>,()=>location.reload())"><i class="fas fa-ban"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal cuenta -->
<div class="modal fade" id="modal-cuenta" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-book"></i> <span id="modal-title-text">Nueva Cuenta</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="form-cuenta">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="_action" value="guardar">
          <input type="hidden" name="id" id="f-id" value="0">
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label">CÃ³digo *</label><input type="text" name="codigo" id="f-codigo" class="form-control" required maxlength="20"></div>
            <div class="col-md-7"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="f-nombre" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Nivel</label><input type="number" name="nivel" id="f-nivel" class="form-control" min="1" max="5" value="2"></div>
            <div class="col-md-4">
              <label class="form-label">Tipo *</label>
              <select name="tipo" id="f-tipo" class="form-select" required>
                <?php foreach($tipos as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Subtipo</label><input type="text" name="subtipo" id="f-subtipo" class="form-control" placeholder="ej: Banco, NÃ³mina, etc."></div>
            <div class="col-md-4">
              <label class="form-label">Cuenta Padre</label>
              <select name="cuenta_padre_id" id="f-padre" class="form-select">
                <option value="">-- Ninguna --</option>
                <?php foreach($padres as $p): ?><option value="<?= $p['id'] ?>"><?= $p['codigo'] ?> â€” <?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">DescripciÃ³n</label><textarea name="descripcion" id="f-desc" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><div class="form-check"><input type="checkbox" name="activa" class="form-check-input" id="f-activa" checked><label class="form-check-label" for="f-activa">Activa</label></div></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-ahdeco" onclick="saveCuenta()"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = "
initDataTable('#tbl-cuentas', {order:[[0,'asc']]});

document.querySelectorAll('.filter-btn').forEach(b=>{
  b.addEventListener('click',function(){
    document.querySelectorAll('.filter-btn').forEach(x=>x.classList.remove('active'));
    this.classList.add('active');
    const f=this.dataset.filter;
    document.querySelectorAll('#tbl-cuentas tbody tr').forEach(r=>{
      r.style.display=(!f||r.dataset.tipo===f)?'':'none';
    });
  });
});

function editCuenta(c){
  document.getElementById('f-id').value=c.id;
  document.getElementById('f-codigo').value=c.codigo;
  document.getElementById('f-nombre').value=c.nombre;
  document.getElementById('f-nivel').value=c.nivel;
  document.getElementById('f-tipo').value=c.tipo;
  document.getElementById('f-subtipo').value=c.subtipo||'';
  document.getElementById('f-padre').value=c.cuenta_padre_id||'';
  document.getElementById('f-desc').value=c.descripcion||'';
  document.getElementById('f-activa').checked=c.activa==1;
  document.getElementById('modal-title-text').textContent='Editar: '+c.codigo+' '+c.nombre;
  new bootstrap.Modal(document.getElementById('modal-cuenta')).show();
}

async function saveCuenta(){
  const r=await post('cuentas.php',new FormData(document.getElementById('form-cuenta')));
  if(r.success){Toast.show(r.message,'success');setTimeout(()=>location.reload(),1000);}
  else Toast.show(r.message,'error');
}
";
include __DIR__ . '/includes/footer.php';
?>

