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
    // Serializa la numeración entre usuarios concurrentes: el bloqueo con nombre se
    // mantiene hasta que termina la petición (después del INSERT/commit del documento),
    // así dos usuarios guardando a la vez no obtienen el mismo correlativo.
    static $bloqueos = [];
    if (!isset($bloqueos[$tabla])) {
        $l = $pdo->prepare("SELECT GET_LOCK(?, 15)");
        $l->execute(["ahdeco_num_{$tabla}"]);
        if ((int)$l->fetchColumn() !== 1) {
            throw new RuntimeException('Otro usuario está generando un documento; intente de nuevo en unos segundos.');
        }
        $bloqueos[$tabla] = true;
    }
    // Dentro de una transacción, FOR UPDATE lee el último número confirmado (no una foto vieja)
    $lectura = $pdo->inTransaction() ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($campo, ?) AS UNSIGNED)) FROM $tabla WHERE $campo LIKE ?$lectura");
    $stmt->execute([strlen($prefijo . substr((string)$anio, -2) . '-') + 1, $prefijo . substr((string)$anio, -2) . '-%']);
    $max  = (int) $stmt->fetchColumn();
    return $prefijo . substr((string)$anio, -2) . '-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
}

// ── Número de proceso compartido (anticipo ↔ liquidación ↔ orden de pago) ───
function generarProceso(PDO $pdo): int {
    $pdo->exec("INSERT INTO procesos_viaticos () VALUES ()");
    return (int)$pdo->lastInsertId();
}

function formatProceso(int $procesoId): string {
    return 'P' . substr((string)date('Y'), -2) . '-' . str_pad($procesoId, 4, '0', STR_PAD_LEFT);
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
function calcularPlanilla(float $sueldoMensual, array $_config = []): array {
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

// ── Distribución de planillas por proyecto ────────────────────────
// Filas sin distribución se tratan como 100% "Sin proyecto" (proyecto_id 0).

/** Limpia y valida las filas enviadas por el editor; cada campo % debe sumar 100. Responde jsonErr si no es válido. */
function parseDistribucion(array $rows, array $campos): array {
    $labels = ['pct_salario' => 'salario', 'pct_patronal' => 'costo patronal', 'porcentaje' => 'beneficio'];
    $out = [];
    foreach ($rows as $r) {
        $pid = (int)($r['proyecto_id'] ?? 0);
        if (!$pid) continue;
        if (isset($out[$pid])) jsonErr('Un proyecto aparece más de una vez en la distribución.');
        $fila = ['proyecto_id' => $pid];
        foreach ($campos as $c) {
            $v = round((float)str_replace(',', '', $r[$c] ?? '0'), 6);
            if ($v < 0 || $v > 100) jsonErr('Los porcentajes deben estar entre 0 y 100.');
            $fila[$c] = $v;
        }
        if (array_sum(array_intersect_key($fila, array_flip($campos))) > 0) $out[$pid] = $fila;
    }
    if ($out) {
        foreach ($campos as $c) {
            $suma = array_sum(array_column($out, $c));
            if (abs($suma - 100) > 0.001) {
                jsonErr('El % de ' . ($labels[$c] ?? $c) . ' suma ' . number_format($suma, 2) . '%; debe sumar 100%.');
            }
        }
    }
    return array_values($out);
}

/** Reparte $monto según porcentajes [clave => %]; el proyecto con mayor % absorbe el redondeo. */
function repartirMonto(float $monto, array $pcts): array {
    $res = []; $acum = 0.0;
    $mayor = array_search(max($pcts), $pcts, true);
    foreach ($pcts as $k => $p) {
        if ($k === $mayor) continue;
        $res[$k] = round($monto * $p / 100, 2);
        $acum += $res[$k];
    }
    $res[$mayor] = round($monto - $acum, 2);
    return $res;
}

/**
 * Reparte los montos de una fila de planilla entre sus proyectos.
 * $grupos: [campo_pct => [columnas a repartir con ese %]].
 * Devuelve [proyecto_id => ['proyecto_id', campo_pct..., columna...]].
 */
function repartirFila(array $fila, array $dist, array $grupos): array {
    if (!$dist) $dist = [['proyecto_id' => 0] + array_fill_keys(array_keys($grupos), 100)];
    $out = [];
    foreach ($dist as $d) $out[(int)$d['proyecto_id']] = ['proyecto_id' => (int)$d['proyecto_id']];
    foreach ($grupos as $campoPct => $columnas) {
        $pcts = [];
        foreach ($dist as $d) {
            $pid = (int)$d['proyecto_id'];
            $pcts[$pid] = (float)$d[$campoPct];
            $out[$pid][$campoPct] = (float)$d[$campoPct];
        }
        foreach ($columnas as $col) {
            foreach (repartirMonto((float)$fila[$col], $pcts) as $pid => $v) $out[$pid][$col] = $v;
        }
    }
    return $out;
}

/**
 * Reparte todas las filas de una planilla y acumula un resumen por proyecto.
 * Devuelve [[detalle_id => reparto], [proyecto_id => totales]] con "Sin proyecto" (0) al final.
 */
function resumirDistribucion(array $detalle, array $dist, array $grupos, callable $derivar): array {
    $porFila = $resumen = [];
    foreach ($detalle as $d) {
        $rep = repartirFila($d, $dist[$d['id']] ?? [], $grupos);
        foreach ($rep as $pid => $r) {
            $rep[$pid] = $r = $derivar($r);
            $resumen[$pid]['proyecto_id'] = $pid;
            $resumen[$pid]['empleados']   = ($resumen[$pid]['empleados'] ?? 0) + 1;
            foreach ($r as $k => $v) {
                if ($k === 'proyecto_id' || isset($grupos[$k])) continue;
                $resumen[$pid][$k] = round(($resumen[$pid][$k] ?? 0) + $v, 2);
            }
        }
        $porFila[$d['id']] = $rep;
    }
    uksort($resumen, fn($a, $b) => ($a === 0) <=> ($b === 0) ?: $a <=> $b);
    return [$porFila, $resumen];
}

/** Planilla quincenal: % salario reparte bruto y deducciones; % patronal reparte IHSS/RAP patronal. */
function distribuirQuincenal(array $detalle, array $dist): array {
    return resumirDistribucion($detalle, $dist,
        ['pct_salario' => ['total_bruto', 'total_deducciones'], 'pct_patronal' => ['ihss_patronal', 'rap_patronal']],
        function (array $r): array {
            $r['sueldo_neto'] = round($r['total_bruto'] - $r['total_deducciones'], 2);
            $r['costo_total'] = round($r['total_bruto'] + $r['ihss_patronal'] + $r['rap_patronal'], 2);
            return $r;
        });
}

/** Planilla de beneficios: el % reparte el beneficio y su deducción. */
function distribuirBeneficios(array $detalle, array $dist): array {
    return resumirDistribucion($detalle, $dist,
        ['porcentaje' => ['sueldo_mensual', 'deduccion']],
        function (array $r): array {
            $r['neto'] = round($r['sueldo_mensual'] - $r['deduccion'], 2);
            return $r;
        });
}

/** Porcentaje legible: hasta 2 decimales, sin ceros sobrantes (42.857143 → "42.86"). */
function fmtPct(float $v): string {
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

/** Etiqueta corta de un proyecto para badges / reportes. */
function proyectoLabel(array $proyMap, int $pid, bool $largo = false): string {
    if (!$pid || !isset($proyMap[$pid])) return 'Sin proyecto';
    return $largo ? $proyMap[$pid]['codigo'] . ' — ' . $proyMap[$pid]['nombre'] : $proyMap[$pid]['codigo'];
}

/** Distribución guardada de varias filas de detalle: [detalle_id => [filas]]. */
function cargarDistribucion(PDO $pdo, string $tabla, array $detalleIds): array {
    if (!in_array($tabla, ['planilla_detalle_proyectos', 'planilla_beneficios_detalle_proyectos'], true) || !$detalleIds) return [];
    $marks = implode(',', array_fill(0, count($detalleIds), '?'));
    $s = $pdo->prepare("SELECT * FROM `{$tabla}` WHERE detalle_id IN ($marks) ORDER BY id");
    $s->execute(array_values($detalleIds));
    $out = [];
    foreach ($s->fetchAll() as $r) $out[$r['detalle_id']][] = $r;
    return $out;
}

/** Catálogo de proyectos indexado por id (incluye cerrados, para planillas históricas). */
function proyectosMap(PDO $pdo): array {
    $map = [];
    foreach ($pdo->query("SELECT id, codigo, nombre, estado FROM proyectos ORDER BY nombre")->fetchAll() as $p) $map[$p['id']] = $p;
    return $map;
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

// ── Tipo de Anticipo (viaje/viáticos o compra de insumos/activos) ──
function tipoAnticipoLabel(string $tipo): string {
    return $tipo === 'compra' ? 'Compra de Insumos/Activos' : 'Viaje (Viáticos)';
}

function tipoAnticipoIcon(string $tipo): string {
    return $tipo === 'compra' ? 'fa-box-open' : 'fa-plane-departure';
}

function tipoAnticipoBadge(string $tipo): string {
    $cls = $tipo === 'compra' ? 'secondary' : 'primary';
    return "<span class=\"badge bg-{$cls}\"><i class=\"fas " . tipoAnticipoIcon($tipo) . "\"></i> " . tipoAnticipoLabel($tipo) . "</span>";
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
