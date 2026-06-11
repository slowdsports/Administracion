<?php
$cur = basename($_SERVER['PHP_SELF']);
function sidebarItem(string $file, string $icon, string $label, string $cur, string $badge = ''): string {
    $active = ($cur === $file) ? 'active' : '';
    $b = $badge ? "<span class=\"badge-count\">{$badge}</span>" : '';
    return "<a href=\"" . BASE_URL . "{$file}\\" class=\"sidebar-item {$active}\" data-page=\"{$file}\">
        <i class=\"fas {$icon}\"></i> {$label}{$b}
    </a>";
}
?>
<nav id="sidebar">

  <!-- DASHBOARD -->
  <div class="sidebar-section">
    <?= sidebarItem('dashboard.php', 'fa-gauge-high', 'Dashboard', $cur) ?>
  </div>

  <!-- PERSONAL -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Personal</div>
    <?= sidebarItem('empleados.php', 'fa-users', 'Empleados', $cur) ?>
    <?= sidebarItem('planillas.php',            'fa-money-check-dollar', 'Planillas Quincenales', $cur) ?>
    <?= sidebarItem('planillas_asignacion.php', 'fa-list-check',         'Planillas de Asignación', $cur) ?>
    <?= sidebarItem('planillas_beneficios.php', 'fa-gift',               'Planillas de Beneficios', $cur) ?>
  </div>

  <!-- CONTABILIDAD -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Contabilidad</div>
    <?= sidebarItem('cuentas.php', 'fa-book', 'Catálogo de Cuentas', $cur) ?>
    <?= sidebarItem('tesoreria.php', 'fa-landmark', 'Tesorería', $cur) ?>
    <?= sidebarItem('gastos_solicitud.php', 'fa-file-invoice-dollar', 'Solicitud de Gastos', $cur) ?>
    <?= sidebarItem('gastos_viajes.php',         'fa-plane-departure',  'Gastos de Viaje',       $cur) ?>
    <?= sidebarItem('gastos_viaje_auxiliar.php', 'fa-scale-balanced',   'Auxiliar de Viáticos',  $cur) ?>
  </div>

  <!-- PROCESO DE COMPRAS -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Proceso de Compras</div>
    <?= sidebarItem('compras_solicitud.php', 'fa-cart-plus', 'Solicitud de Compra', $cur) ?>
    <?= sidebarItem('compras_cotizaciones.php', 'fa-file-lines', 'Resumen de Cotizaciones', $cur) ?>
    <?= sidebarItem('compras_orden.php', 'fa-file-circle-check', 'Orden de Compra', $cur) ?>
    <?= sidebarItem('compras_pago.php', 'fa-money-bill-transfer', 'Orden de Pago', $cur) ?>
    <?= sidebarItem('compras_recepcion.php', 'fa-boxes-stacked', 'Nota de Recepción', $cur) ?>
    <?= sidebarItem('proveedores.php', 'fa-truck', 'Proveedores', $cur) ?>
  </div>

  <!-- PROYECTOS Y PRESUPUESTO -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Proyectos y Presupuesto</div>
    <?= sidebarItem('proyectos.php', 'fa-diagram-project', 'Proyectos / Donantes', $cur) ?>
    <?= sidebarItem('presupuesto.php', 'fa-chart-pie', 'Presupuesto', $cur) ?>
  </div>

  <!-- ACTIVOS -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Activos</div>
    <?= sidebarItem('activos.php', 'fa-laptop', 'Activos Fijos', $cur) ?>
  </div>

  <!-- RECURSOS HUMANOS -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Recursos Humanos</div>
    <?= sidebarItem('vacaciones.php',  'fa-umbrella-beach',    'Control de Vacaciones', $cur) ?>
    <?= sidebarItem('permisos.php',    'fa-calendar-xmark',   'Permisos y Ausencias',  $cur) ?>
    <?= sidebarItem('asistencia.php',  'fa-calendar-check',   'Control de Asistencia', $cur) ?>
    <?= sidebarItem('prestamos.php',   'fa-hand-holding-dollar','Préstamos a Empleados', $cur) ?>
    <?= sidebarItem('constancias.php',  'fa-file-contract',    'Constancias y Refs.',   $cur) ?>
    <?= sidebarItem('liquidaciones.php','fa-file-invoice',     'Liquidaciones',          $cur) ?>
  </div>

  <!-- DOCUMENTOS INTERNOS -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Documentos Internos</div>
    <?= sidebarItem('memorandos.php', 'fa-file-lines',     'Memorandos y Comunicados', $cur) ?>
    <?= sidebarItem('actas.php',      'fa-clipboard-list', 'Actas de Reunión',          $cur) ?>
  </div>

  <!-- REPORTES -->
  <div class="sidebar-section">
    <div class="sidebar-section-title">Reportes</div>
    <?= sidebarItem('reportes.php',         'fa-chart-bar',    'Reportes Financieros', $cur) ?>
    <?= sidebarItem('reportes_legales.php', 'fa-file-export',  'Reportes Legales',     $cur) ?>
  </div>

  <!-- MI CUENTA -->
  <div class="sidebar-section">
    <?= sidebarItem('perfil.php', 'fa-circle-user', 'Mi Perfil', $cur) ?>
  </div>

  <!-- ADMIN -->
  <?php if (($_SESSION['usuario_rol'] ?? '') === 'admin'): ?>
  <div class="sidebar-section">
    <div class="sidebar-section-title">Administración</div>
    <?= sidebarItem('caja_chica.php',    'fa-cash-register',  'Caja Chica',    $cur) ?>
    <?= sidebarItem('configuracion.php', 'fa-gear',           'Configuración', $cur) ?>
    <?= sidebarItem('firmantes.php',     'fa-file-signature', 'Firmantes',     $cur) ?>
    <?= sidebarItem('usuarios.php',      'fa-user-shield',    'Usuarios',      $cur) ?>
    <?= sidebarItem('auditoria.php',     'fa-shield-halved',  'Auditoría',     $cur) ?>
  </div>
  <?php endif; ?>

  <div style="height:2rem;"></div>
</nav>
