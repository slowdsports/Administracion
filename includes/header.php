<?php
if (!defined('BASE_URL')) {
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $appRoot  = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__))), '/');
    $relative = (strpos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
    define('BASE_URL', ($relative ?: '') . '/');
}
$pagina  = $pagina ?? 'Dashboard';
$csrf    = csrfToken();

// Hydrate foto from DB once per session (handles sessions started before foto column existed)
if (!isset($_SESSION['usuario_foto']) && isset($_SESSION['usuario_id'])) {
    $__s = $pdo->prepare("SELECT foto FROM usuarios WHERE id=?");
    $__s->execute([$_SESSION['usuario_id']]);
    $_SESSION['usuario_foto'] = $__s->fetchColumn() ?: '';
}
$user = currentUser();

$_avatarFoto = $user['foto'] ? BASE_URL . $user['foto'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf" content="<?= $csrf ?>">
  <title><?= htmlspecialchars($pagina) ?> | AHDECO Admin</title>
  <script>
    // Aplica el tema antes de que el navegador pinte para evitar el flash
    (function(){
      var t = localStorage.getItem('ahdeco-theme') || 'light';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/ahdeco.css">
</head>
<body>

<!-- TOPBAR -->
<nav id="topbar">
  <button class="btn-hamburger" id="btn-toggle-sidebar" title="Menú">
    <i class="fas fa-bars"></i>
  </button>
  <div class="brand">
    <img src="<?= BASE_URL ?>assets/images/logo2.png" alt="AHDECO">
  </div>
  <span class="topbar-title">Sistema de Administración y Finanzas</span>
  <div class="topbar-right">
    <a href="<?= BASE_URL ?>perfil.php" class="topbar-user d-none d-md-flex" style="text-decoration:none;color:inherit">
      <div class="avatar" style="background:none;padding:0;overflow:hidden;flex-shrink:0">
        <img src="<?= $_avatarFoto ?: BASE_URL . 'assets/images/logo2.png' ?>"
             alt=""
             style="width:27px;height:27px;object-fit:cover;border-radius:50%;display:block;">
      </div>
      <span><?= htmlspecialchars($user['nombre']) ?></span>
      <span class="topbar-role"><?= ucfirst($user['rol']) ?></span>
    </a>
    <button id="btn-theme" onclick="toggleTheme()" title="Cambiar tema">
      <i class="fas fa-moon" id="theme-icon"></i>
    </button>
    <a href="<?= BASE_URL ?>logout.php" class="btn-logout" title="Cerrar sesión">
      <i class="fas fa-sign-out-alt"></i> <span class="d-none d-md-inline">Salir</span>
    </a>
  </div>
</nav>

<?php include __DIR__ . '/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main id="main-content">
  <div id="toast-container"></div>
