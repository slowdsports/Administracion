<?php
// Calcula BASE_URL dinámicamente usando realpath() para resolver symlinks
(function () {
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $appRoot  = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__))), '/');
    $relative = (strpos($appRoot, $docRoot) === 0)
        ? substr($appRoot, strlen($docRoot))
        : '';
    define('BASE_URL', ($relative ?: '') . '/');
})();

// ── Número correlativo ────────────────────────────────────────────
function generarNumero(PDO $pdo, string $tabla, string $campo, string $prefijo, int $anio = 0): string {
    if (!$anio) $anio = (int) date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($campo, ?) AS UNSIGNED)) FROM $tabla WHERE $campo LIKE ?");
    $len  = strlen($prefijo) + 5; // prefijo + año(2) + '-' => len after prefix+year
    $stmt->execute([strlen($prefijo . substr((string)$anio, -2) . '-') + 1, $prefijo . substr((string)$anio, -2) . '-%']);
    $max  = (int) $stmt->fetchColumn();
    return $prefijo . substr((string)$anio, -2) . '-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
}

// ── Formateo de moneda ────────────────────────────────────────────
function lps(float $v): string {
    return 'L. ' . number_format($v, 2, '.', ',');
}

function fmtFecha(string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    return date('d/m/Y', strtotime($d));
}

// ── Cálculos de Planilla Honduras ─────────────────────────────────
function calcularPlanilla(float $sueldoMensual, array $config = []): array {
    // Todas las deducciones se ingresan manualmente en cada planilla
    $q = $sueldoMensual / 2;
    return [
        'sueldo_quincenal' => $q,
        'ihss_empleado'    => 0.0,
        'ihss_patronal'    => 0.0,
        'rap_empleado'     => 0.0,
        'rap_patronal'     => 0.0,
        'infop'            => 0.0,
        'isr'              => 0.0,
        'total_deducciones'=> 0.0,
        'sueldo_neto'      => $q,
    ];
}

function calcularISRQuincenal(float $sueldoQ, float $exentoAnual = 142771.50): float {
    $anual = $sueldoQ * 24;
    if ($anual <= $exentoAnual) return 0;

    $t1Max = 214157.26;
    $t2Max = 321235.88;
    $t3Max = 642471.75;

    $isr = 0;
    $sujeto = $anual - $exentoAnual;

    if ($anual <= $t1Max) {
        $isr = $sujeto * 0.15;
    } elseif ($anual <= $t2Max) {
        $isr  = ($t1Max - $exentoAnual) * 0.15;
        $isr += ($anual - $t1Max) * 0.20;
    } elseif ($anual <= $t3Max) {
        $isr  = ($t1Max - $exentoAnual) * 0.15;
        $isr += ($t2Max - $t1Max) * 0.20;
        $isr += ($anual - $t2Max) * 0.25;
    } else {
        $isr  = ($t1Max - $exentoAnual) * 0.15;
        $isr += ($t2Max - $t1Max) * 0.20;
        $isr += ($t3Max - $t2Max) * 0.25;
        $isr += ($anual - $t3Max) * 0.25;
    }

    return round($isr / 24, 2);
}

// ── Config helper ──────────────────────────────────────────────────
function getConfig(PDO $pdo, string $clave, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$clave])) {
        $s = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $s->execute([$clave]);
        $cache[$clave] = $s->fetchColumn() ?: $default;
    }
    return $cache[$clave];
}

function getAllConfig(PDO $pdo): array {
    $rows = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll();
    $cfg  = [];
    foreach ($rows as $r) $cfg[$r['clave']] = $r['valor'];
    return $cfg;
}

// ── Respuestas JSON ────────────────────────────────────────────────
function jsonOk(array $data = [], string $msg = 'OK'): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $msg] + $data);
    exit;
}

function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function isAjax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

// ── Estado badge ───────────────────────────────────────────────────
function estadoBadge(string $estado): string {
    $map = [
        'pendiente'   => 'warning',
        'aprobada'    => 'success',
        'rechazada'   => 'danger',
        'pagada'      => 'info',
        'liquidada'   => 'info',
        'borrador'    => 'secondary',
        'procesada'   => 'primary',
        'emitida'     => 'primary',
        'completa'    => 'success',
        'parcial'     => 'warning',
        'cotizando'   => 'warning',
        'adjudicada'  => 'success',
        'anulada'     => 'dark',
        'activo'      => 'success',
        'cerrado'     => 'secondary',
        'suspendido'  => 'warning',
    ];
    $cls = $map[$estado] ?? 'secondary';
    return "<span class=\"badge bg-{$cls}\">" . ucfirst($estado) . "</span>";
}

// ── Paginación ────────────────────────────────────────────────────
function paginate(int $total, int $page, int $perPage = 20, string $url = ''): array {
    $pages   = (int) ceil($total / $perPage);
    $offset  = ($page - 1) * $perPage;
    return compact('total', 'page', 'pages', 'perPage', 'offset');
}

// ── Urgencia badge ────────────────────────────────────────────────
function urgenciaBadge(string $urgencia): string {
    $map = ['normal' => 'secondary', 'urgente' => 'warning', 'muy_urgente' => 'danger'];
    $cls = $map[$urgencia] ?? 'secondary';
    $lbl = str_replace('_', ' ', ucfirst($urgencia));
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}
