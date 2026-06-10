<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
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
