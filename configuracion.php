<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
if (!hasRole(['admin'])) { header('Location: /Administracion/dashboard.php'); exit; }
$pagina = 'Configuración';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($_POST as $k => $v) {
        if (in_array($k,['csrf_token','_action'])) continue;
        $pdo->prepare("UPDATE configuracion SET valor=? WHERE clave=?")->execute([trim($v),$k]);
    }
    jsonOk([],'Configuración guardada.');
}

$cfg = getAllConfig($pdo);
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1><i class="fas fa-gear"></i> Configuración del Sistema</h1>
</div>
<div class="card">
  <div class="card-body">
    <form id="form-config">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-section-title">Datos de la Organización</div>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Nombre Corto</label><input type="text" name="nombre_organizacion" class="form-control" value="<?= htmlspecialchars($cfg['nombre_organizacion']??'') ?>"></div>
        <div class="col-md-9"><label class="form-label">Nombre Completo</label><input type="text" name="nombre_completo" class="form-control" value="<?= htmlspecialchars($cfg['nombre_completo']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label">RTN</label><input type="text" name="rtn" class="form-control" value="<?= htmlspecialchars($cfg['rtn']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label">Teléfono</label><input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($cfg['telefono']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label">Correo</label><input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($cfg['correo']??'') ?>"></div>
        <div class="col-md-3"><label class="form-label">Moneda</label><select name="moneda" class="form-select"><option value="HNL" <?= ($cfg['moneda']??'HNL')==='HNL'?'selected':'' ?>>Lempiras (HNL)</option><option value="USD" <?= ($cfg['moneda']??'')==='USD'?'selected':'' ?>>Dólares (USD)</option></select></div>
        <div class="col-12"><label class="form-label">Dirección</label><input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($cfg['direccion']??'') ?>"></div>
      </div>

      <div class="form-section-title">Prefijos de Numeración</div>
      <div class="row g-3">
        <?php $prefijos=['prefijo_sg'=>'Sol. Gastos','prefijo_gv'=>'Gastos Viaje','prefijo_pl'=>'Planillas','prefijo_sc'=>'Sol. Compra','prefijo_co'=>'Cotizaciones','prefijo_oc'=>'Orden Compra','prefijo_op'=>'Orden Pago','prefijo_nr'=>'Nota Recepción']; ?>
        <?php foreach($prefijos as $k=>$lbl): ?>
        <div class="col-md-3"><label class="form-label"><?= $lbl ?></label><input type="text" name="<?= $k ?>" class="form-control" maxlength="5" value="<?= htmlspecialchars($cfg[$k]??'') ?>" style="text-transform:uppercase"></div>
        <?php endforeach; ?>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn-ahdeco"><i class="fas fa-save"></i> Guardar Configuración</button>
      </div>
    </form>
  </div>
</div>
<?php
$extraJs = "bindAjaxForm('form-config');";
include __DIR__ . '/includes/footer.php';
?>
