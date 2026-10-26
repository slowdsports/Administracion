<?php
$cur = basename($_SERVER['PHP_SELF']);
function sidebarItem(string $file, string $icon, string $label, string $cur, string $badge = ''): string {
    if (!puedeAcceder($file)) return '';
    $active = ($cur === $file) ? 'active' : '';
    $b = $badge ? "<span class=\"badge-count\">{$badge}</span>" : '';
    return "<a href=\"" . BASE_URL . "{$file}\" class=\"sidebar-item {$active}\" data-page=\"{$file}\">
        <i class=\"fas {$icon}\"></i> {$label}{$b}
    </a>";
}
// Sección del menú; se oculta si el rol no tiene acceso a ninguno de sus módulos
function sidebarSection(string $title, string ...$items): string {
    $html = implode("\n    ", array_filter($items));
    if ($html === '') return '';
    $t = $title ? "<div class=\"sidebar-section-title\">{$title}</div>\n    " : '';
    return "<div class=\"sidebar-section\">\n    {$t}{$html}\n  </div>";
}
?>
<nav id="sidebar">

  <?= sidebarSection('',
    sidebarItem('dashboard.php', 'fa-gauge-high', 'Dashboard', $cur)) ?>

  <?= sidebarSection('Personal',
    sidebarItem('empleados.php',            'fa-users',              'Empleados', $cur),
    sidebarItem('planillas.php',            'fa-money-check-dollar', 'Planillas Quincenales', $cur),
    sidebarItem('planillas_asignacion.php', 'fa-list-check',         'Planillas de Asignación', $cur),
    sidebarItem('planillas_beneficios.php', 'fa-gift',               'Planillas de Beneficios', $cur)) ?>

  <?= sidebarSection('Contabilidad',
    sidebarItem('cuentas.php',               'fa-book',                'Catálogo de Cuentas', $cur),
    sidebarItem('tesoreria.php',             'fa-landmark',            'Tesorería', $cur),
    sidebarItem('gastos_solicitud.php',      'fa-file-invoice-dollar', 'Liquidaciones / Reembolsos', $cur),
    sidebarItem('gastos_viajes.php',         'fa-hand-holding-dollar', 'Anticipos', $cur),
    sidebarItem('gastos_viaje_auxiliar.php', 'fa-scale-balanced',      'Auxiliar de Anticipos', $cur)) ?>

  <?= sidebarSection('Proceso de Compras',
    sidebarItem('compras_solicitud.php',    'fa-cart-plus',            'Solicitud de Compra', $cur),
    sidebarItem('compras_cotizaciones.php', 'fa-file-lines',           'Resumen de Cotizaciones', $cur),
    sidebarItem('compras_orden.php',        'fa-file-circle-check',    'Orden de Compra', $cur),
    sidebarItem('compras_pago.php',         'fa-money-bill-transfer',  'Orden de Pago', $cur),
    sidebarItem('compras_recepcion.php',    'fa-boxes-stacked',        'Nota de Recepción', $cur),
    sidebarItem('proveedores.php',          'fa-truck',                'Proveedores', $cur)) ?>

  <?= sidebarSection('Proyectos y Presupuesto',
    sidebarItem('proyectos.php',   'fa-diagram-project', 'Proyectos / Donantes', $cur),
    sidebarItem('presupuesto.php', 'fa-chart-pie',       'Presupuesto', $cur)) ?>

  <?= sidebarSection('Activos',
    sidebarItem('activos.php', 'fa-laptop', 'Activos Fijos', $cur)) ?>

  <?= sidebarSection('Recursos Humanos',
    sidebarItem('vacaciones.php',    'fa-umbrella-beach',      'Control de Vacaciones', $cur),
    sidebarItem('permisos.php',      'fa-calendar-xmark',      'Permisos y Ausencias', $cur),
    sidebarItem('asistencia.php',    'fa-calendar-check',      'Control de Asistencia', $cur),
    sidebarItem('prestamos.php',     'fa-hand-holding-dollar', 'Préstamos a Empleados', $cur),
    sidebarItem('constancias.php',   'fa-file-contract',       'Constancias y Refs.', $cur),
    sidebarItem('liquidaciones.php', 'fa-file-invoice',        'Liquidaciones', $cur)) ?>

  <?= sidebarSection('Documentos Internos',
    sidebarItem('memorandos.php', 'fa-file-lines',     'Memorandos y Comunicados', $cur),
    sidebarItem('actas.php',      'fa-clipboard-list', 'Actas de Reunión', $cur)) ?>

  <?= sidebarSection('Reportes',
    sidebarItem('reportes.php',         'fa-chart-bar',   'Reportes Financieros', $cur),
    sidebarItem('reportes_legales.php', 'fa-file-export', 'Reportes Legales', $cur)) ?>

  <!-- MI CUENTA -->
  <?= sidebarSection('',
    sidebarItem('perfil.php', 'fa-circle-user', 'Mi Perfil', $cur)) ?>

  <!-- ADMIN -->
  <?php if (($_SESSION['usuario_rol'] ?? '') === 'admin'): ?>
  <?= sidebarSection('Administración',
    sidebarItem('caja_chica.php',    'fa-cash-register',  'Caja Chica', $cur),
    sidebarItem('configuracion.php', 'fa-gear',           'Configuración', $cur),
    sidebarItem('firmantes.php',     'fa-file-signature', 'Firmantes', $cur),
    sidebarItem('usuarios.php',      'fa-user-shield',    'Usuarios', $cur),
    sidebarItem('auditoria.php',     'fa-shield-halved',  'Auditoría', $cur)) ?>
  <?php endif; ?>

  <div style="height:2rem;"></div>
</nav>
