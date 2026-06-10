<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: /Administracion/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $u['id'];
            $_SESSION['usuario_nombre'] = $u['nombre'];
            $_SESSION['usuario_rol']    = $u['rol'];
            $_SESSION['usuario_email']  = $u['email'];
            $_SESSION['usuario_foto']   = $u['foto'] ?? '';

            $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$u['id']]);
            header('Location: /Administracion/dashboard.php');
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    } else {
        $error = 'Ingrese su correo y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AHDECO - Iniciar Sesión</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="/Administracion/assets/css/ahdeco.css">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <img src="/Administracion/assets/images/logo2aa.png" alt="AHDECO" class="logo mb-2" style="height:70px;">
    <p class="org-name">AHDECO</p>
    <p class="text-muted" style="font-size:.78rem;margin-bottom:1.5rem;">
      Asociación Hondureña para el Desarrollo Integral Comunitario<br>
      <strong>Sistema de Administración y Finanzas</strong>
    </p>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2" style="font-size:.85rem;">
        <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb-3 text-start">
        <label class="form-label"><i class="fas fa-envelope text-blue"></i> Correo electrónico</label>
        <input type="email" name="email" class="form-control" placeholder="usuario@ahdeco.hn"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="mb-3 text-start">
        <label class="form-label"><i class="fas fa-lock text-blue"></i> Contraseña</label>
        <div class="input-group">
          <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
            <i class="fas fa-eye" id="pwd-icon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
      </button>
    </form>

    <p class="mt-3 text-muted" style="font-size:.72rem;">
      ¿Problemas para acceder? Contacte al administrador del sistema.
    </p>
  </div>
</div>
<script>
function togglePwd() {
  const f = document.getElementById('pwd');
  const i = document.getElementById('pwd-icon');
  if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash'; }
  else { f.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
