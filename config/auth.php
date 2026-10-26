<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Roles ──────────────────────────────────────────────────────────
const ROLES = [
    'admin'        => 'Administrador',
    'contador'     => 'Contador',
    'asistente'    => 'Asistente',
    'visualizador' => 'Visualizador',
    'compras'      => 'Compras y Contabilidad',
];

// Roles con acceso limitado a ciertos módulos. Los roles que no están aquí
// conservan el acceso completo de siempre.
//   paginas    → archivos .php que puede abrir (incluye sus llamadas AJAX)
//   documentos → tipos de print.php / pdf_download.php que puede imprimir
//   inicio     → página a la que se le envía al entrar o si intenta otra
const ROLES_RESTRINGIDOS = [
    'compras' => [
        'paginas' => [
            // Contabilidad (sin Catálogo de Cuentas)
            'gastos_solicitud.php', 'gastos_viajes.php', 'gastos_viaje_auxiliar.php',
            // Compras
            'compras_solicitud.php', 'compras_cotizaciones.php', 'compras_orden.php',
            'compras_pago.php', 'compras_recepcion.php', 'proveedores.php',
            // Proyectos
            'proyectos.php',
            // Soporte: perfil propio (contraseña) e impresión de sus documentos
            'perfil.php', 'print.php', 'pdf_download.php', 'pdf_vouchers.php',
        ],
        'documentos' => ['sc', 'oc', 'op', 'proceso', 'sg', 'gv', 'gva'],
        'inicio'     => 'compras_solicitud.php',
    ],
];

function rolLabel(string $rol): string {
    return ROLES[$rol] ?? ucfirst($rol);
}

function puedeAcceder(string $pagina): bool {
    $r = ROLES_RESTRINGIDOS[$_SESSION['usuario_rol'] ?? ''] ?? null;
    return !$r || in_array($pagina, $r['paginas'], true);
}

function puedeVerDocumento(string $tipo): bool {
    $r = ROLES_RESTRINGIDOS[$_SESSION['usuario_rol'] ?? ''] ?? null;
    return !$r || in_array($tipo, $r['documentos'], true);
}

function paginaInicio(): string {
    return ROLES_RESTRINGIDOS[$_SESSION['usuario_rol'] ?? '']['inicio'] ?? 'dashboard.php';
}

function requireLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
    // Refresca rol y estado desde la BD: un cambio de rol o una baja aplica
    // de inmediato, sin esperar a que el usuario cierre sesión.
    if (isset($GLOBALS['pdo'])) {
        $s = $GLOBALS['pdo']->prepare("SELECT rol, activo FROM usuarios WHERE id=?");
        $s->execute([$_SESSION['usuario_id']]);
        $u = $s->fetch();
        if (!$u || !$u['activo']) {
            session_destroy();
            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
        $_SESSION['usuario_rol'] = $u['rol'];
    }
    if (!puedeAcceder(basename($_SERVER['SCRIPT_NAME'] ?? ''))) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'message' => 'No tiene permiso para este módulo.']));
        }
        header('Location: ' . BASE_URL . paginaInicio());
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function currentUser() {
    return [
        'id'     => $_SESSION['usuario_id'] ?? null,
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'rol'    => $_SESSION['usuario_rol'] ?? '',
        'email'  => $_SESSION['usuario_email'] ?? '',
        'foto'   => $_SESSION['usuario_foto']  ?? '',
    ];
}

function hasRole(array $roles) {
    return in_array($_SESSION['usuario_rol'] ?? '', $roles);
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']));
    }
}

function registrarAuditoria(PDO $pdo, string $accion, string $tabla, int $id, string $detalle = '') {
    $u = currentUser();
    $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip, detalle) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$u['id'], $accion, $tabla, $id, $_SERVER['REMOTE_ADDR'] ?? '', $detalle]);
}
