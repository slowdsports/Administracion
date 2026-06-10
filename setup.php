<?php
/**
 * AHDECO - Instalador del Sistema
 * Accede a: http://localhost/Administracion/setup.php
 * IMPORTANTE: Elimina este archivo después de instalar.
 */

$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$ok     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $host    = trim($_POST['host'] ?? 'localhost');
    $user    = trim($_POST['db_user'] ?? 'root');
    $pass    = $_POST['db_pass'] ?? '';
    $db      = trim($_POST['db_name'] ?? 'ahdeco_admin');

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Reconnect with the database selected in the DSN
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Run schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        // Remove DB management statements (already handled above)
        $schema = preg_replace('/^USE\s+\S+;/mi', '', $schema);
        $schema = preg_replace('/^CREATE DATABASE[^;]+;/mi', '', $schema);
        $schema = preg_replace('/^DROP DATABASE[^;]+;/mi', '', $schema);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
            if ($sql) $pdo->exec($sql);
        }

        // Run seed
        $seed = file_get_contents(__DIR__ . '/database/seed_data.sql');
        $seed = preg_replace('/^USE\s+\w+;/mi', '', $seed);
        foreach (array_filter(array_map('trim', explode(';', $seed))) as $sql) {
            if ($sql) try { $pdo->exec($sql); } catch (Exception $e) { /* ignore dupes */ }
        }

        // Create admin user from form
        $adminEmail = trim($_POST['admin_email'] ?? 'admin@ahdeco.hn');
        $adminPass  = password_hash($_POST['admin_pass'] ?? 'Admin2024!', PASSWORD_BCRYPT);
        $adminName  = trim($_POST['admin_name'] ?? 'Administrador AHDECO');

        $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol) VALUES (?,?,?,'admin') ON DUPLICATE KEY UPDATE password=VALUES(password),nombre=VALUES(nombre)")
            ->execute([$adminName, $adminEmail, $adminPass]);

        // Write config file
        $configContent = "<?php\ndefine('DB_HOST', " . var_export($host, true) . ");\ndefine('DB_NAME', " . var_export($db, true) . ");\ndefine('DB_USER', " . var_export($user, true) . ");\ndefine('DB_PASS', " . var_export($pass, true) . ");\ndefine('DB_CHARSET', 'utf8mb4');\n\ntry {\n    \$pdo = new PDO(\n        \"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME.\";charset=\".DB_CHARSET,\n        DB_USER, DB_PASS,\n        [\n            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n            PDO::ATTR_EMULATE_PREPARES   => false,\n        ]\n    );\n} catch (PDOException \$e) {\n    die('Error de conexión: ' . \$e->getMessage());\n}\n";
        file_put_contents(__DIR__ . '/config/db.php', $configContent);

        $ok = true;
        $step = 3;
    } catch (Exception $e) {
        $errors[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AHDECO - Instalación del Sistema</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/Administracion/assets/css/ahdeco.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px">
  <div class="text-center mb-4">
    <img src="/Administracion/assets/images/logo2aa.png" alt="AHDECO" style="height:60px;margin-bottom:1rem;">
    <h3 style="color:#0D2B4E">Instalación del Sistema</h3>
    <p class="text-muted">AHDECO — Sistema de Administración y Finanzas</p>
  </div>

  <?php if ($step < 3): ?>
  <div class="card shadow">
    <div class="card-header bg-white fw-bold"><i class="fas fa-database text-blue"></i> Configuración de Base de Datos</div>
    <div class="card-body">
      <?php foreach($errors as $err): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
      <form method="POST">
        <input type="hidden" name="step" value="2">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Host MySQL</label><input name="host" class="form-control" value="localhost" required></div>
          <div class="col-md-6"><label class="form-label">Nombre de la Base de Datos</label><input name="db_name" class="form-control" value="ahdeco_admin" required></div>
          <div class="col-md-6"><label class="form-label">Usuario MySQL</label><input name="db_user" class="form-control" value="root" required></div>
          <div class="col-md-6"><label class="form-label">Contraseña MySQL</label><input type="password" name="db_pass" class="form-control" placeholder="(vacía para XAMPP)"></div>
        </div>
        <hr>
        <h6 class="fw-bold"><i class="fas fa-user-shield text-blue"></i> Usuario Administrador</h6>
        <div class="row g-3">
          <div class="col-12"><label class="form-label">Nombre completo</label><input name="admin_name" class="form-control" value="Administrador AHDECO" required></div>
          <div class="col-md-6"><label class="form-label">Correo electrónico</label><input type="email" name="admin_email" class="form-control" value="admin@ahdeco.hn" required></div>
          <div class="col-md-6"><label class="form-label">Contraseña</label><input type="password" name="admin_pass" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="6"></div>
        </div>
        <div class="alert alert-info mt-3" style="font-size:.82rem;">
          <i class="fas fa-info-circle"></i>
          Este proceso creará la base de datos, importará el catálogo de cuentas contables y datos iniciales.
          <strong>Elimina setup.php después de instalar.</strong>
        </div>
        <button type="submit" class="btn-ahdeco w-100 mt-2" style="padding:.65rem"><i class="fas fa-database"></i> Instalar Sistema</button>
      </form>
    </div>
  </div>

  <?php else: ?>
  <div class="card shadow border-success">
    <div class="card-body text-center py-5">
      <i class="fas fa-circle-check fa-4x text-success mb-3 d-block"></i>
      <h4 class="text-success">¡Instalación Exitosa!</h4>
      <p>El sistema AHDECO ha sido instalado correctamente.</p>
      <div class="alert alert-warning" style="font-size:.85rem;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>IMPORTANTE:</strong> Por seguridad, elimina el archivo <code>setup.php</code> del servidor.
      </div>
      <a href="/Administracion/index.php" class="btn-ahdeco" style="padding:.65rem 1.5rem;">
        <i class="fas fa-sign-in-alt"></i> Ir al Sistema
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
