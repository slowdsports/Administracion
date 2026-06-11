<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
if (!hasRole(['admin'])) { header('Location: ' . BASE_URL . 'dashboard.php'); exit; }
$pagina = 'Usuarios del Sistema';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';
    if ($_act === 'guardar') {
        $uid  = (int)($_POST['id'] ?? 0);
        $d = ['nombre'=>trim($_POST['nombre']),'email'=>trim($_POST['email']),'rol'=>$_POST['rol']??'asistente','activo'=>isset($_POST['activo'])?1:0];
        try {
            if ($uid) {
                $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=? WHERE id=?")->execute([...array_values($d), $uid]);
                if ($_POST['new_password'] ?? '') {
                    $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'], PASSWORD_BCRYPT),$uid]);
                }
                jsonOk([],'Usuario actualizado.');
            } else {
                if (!$_POST['new_password']) jsonErr('La contraseÃ±a es requerida para nuevos usuarios.');
                $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES (?,?,?,?,?)")
                    ->execute([$d['nombre'],$d['email'],password_hash($_POST['new_password'],PASSWORD_BCRYPT),$d['rol'],$d['activo']]);
                jsonOk(['id'=>$pdo->lastInsertId()],'Usuario creado.');
            }
        } catch(PDOException $e){ jsonErr($e->getCode()==23000?'El correo ya existe.':$e->getMessage()); }
    }
    jsonErr('AcciÃ³n desconocida.');
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nombre")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1><i class="fas fa-user-shield"></i> Usuarios del Sistema</h1>
  <button class="btn-ahdeco" data-bs-toggle="modal" data-bs-target="#modal-user"><i class="fas fa-plus"></i> Nuevo Usuario</button>
</div>
<div class="card"><div class="card-body p-0">
  <table id="tbl-usr" class="table-ahdeco w-100">
    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Ãšltimo Acceso</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach($usuarios as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['nombre']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge bg-<?= $u['rol']==='admin'?'danger':($u['rol']==='contador'?'primary':'secondary') ?>"><?= ucfirst($u['rol']) ?></span></td>
        <td><?= $u['ultimo_acceso']?date('d/m/Y H:i',strtotime($u['ultimo_acceso'])):'â€”' ?></td>
        <td><?= $u['activo']?'<span class="badge bg-success">Activo</span>':'<span class="badge bg-danger">Inactivo</span>' ?></td>
        <td><button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='editUser(<?= json_encode(['id'=>$u['id'],'nombre'=>$u['nombre'],'email'=>$u['email'],'rol'=>$u['rol'],'activo'=>$u['activo']]) ?>)'><i class="fas fa-edit"></i></button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<div class="modal fade" id="modal-user" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-shield"></i> <span id="usr-title">Nuevo Usuario</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="form-user">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="_action" value="guardar">
          <input type="hidden" name="id" id="u-id" value="0">
          <div class="mb-3"><label class="form-label">Nombre completo *</label><input type="text" name="nombre" id="u-nombre" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Correo electrÃ³nico *</label><input type="email" name="email" id="u-email" class="form-control" required></div>
          <div class="mb-3">
            <label class="form-label">Rol *</label>
            <select name="rol" id="u-rol" class="form-select">
              <option value="admin">Administrador</option>
              <option value="contador">Contador</option>
              <option value="asistente" selected>Asistente</option>
              <option value="visualizador">Visualizador</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">ContraseÃ±a <small class="text-muted">(dejar vacÃ­o para no cambiar)</small></label><input type="password" name="new_password" id="u-pass" class="form-control" minlength="6"></div>
          <div class="form-check"><input type="checkbox" name="activo" class="form-check-input" id="u-activo" checked><label class="form-check-label" for="u-activo">Activo</label></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-ahdeco" onclick="saveUser()"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php
$extraJs = "
initDataTable('#tbl-usr');
function editUser(u){
  document.getElementById('u-id').value=u.id;
  document.getElementById('u-nombre').value=u.nombre;
  document.getElementById('u-email').value=u.email;
  document.getElementById('u-rol').value=u.rol;
  document.getElementById('u-activo').checked=u.activo==1;
  document.getElementById('u-pass').value='';
  document.getElementById('usr-title').textContent='Editar: '+u.nombre;
  new bootstrap.Modal(document.getElementById('modal-user')).show();
}
async function saveUser(){
  const r=await post('usuarios.php',new FormData(document.getElementById('form-user')));
  if(r.success){Toast.show(r.message,'success');setTimeout(()=>location.reload(),1000);}
  else Toast.show(r.message,'error');
}
";
include __DIR__ . '/includes/footer.php';
?>

