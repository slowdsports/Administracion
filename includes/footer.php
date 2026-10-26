</main><!-- /main-content -->

<footer class="app-footer">
  <span>&copy; <?= date('Y') ?> AHDECO &mdash; Sistema de Administración y Finanzas</span>
  <span>Versión 1.0</span>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>
<script src="<?= BASE_URL ?>assets/js/ahdeco.js"></script>
<script>
  // Flatpickr en todos los campos de fecha
  flatpickr('.date-input', { locale: 'es', dateFormat: 'Y-m-d', allowInput: true });

  // Select2: agrega buscador a todos los <select> del sistema (excepto los marcados con .no-search)
  $(function () {
    function initSelect2(context) {
      $(context).find('select').not('.no-search, .select2-hidden-accessible, .dataTables_length select').each(function () {
        const $el = $(this);
        $el.select2({
          theme: 'bootstrap-5',
          language: 'es',
          width: '100%',
          dropdownAutoPosition: true,
          placeholder: $el.data('placeholder') || $el.attr('data-placeholder') || 'Seleccione...',
          allowClear: $el.hasClass('allow-clear'),
          dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : $(document.body)
        });
      });
    }
    initSelect2(document);

    // Reaplica a selects agregados dinámicamente (filas nuevas, modales, etc.)
    const mo = new MutationObserver((mutations) => {
      mutations.forEach((m) => {
        m.addedNodes.forEach((node) => {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches('select')) initSelect2(node.parentNode);
          else if (node.querySelector && node.querySelector('select')) initSelect2(node);
        });
      });
    });
    mo.observe(document.body, { childList: true, subtree: true });
  });

  // Sync footer margin with sidebar collapse (mirrors #main-content.expanded logic)
  const sidebar = document.getElementById('sidebar');
  const footer  = document.querySelector('.app-footer');
  const observer = new MutationObserver(() => {
    if (!footer) return;
    const collapsed = sidebar?.classList.contains('collapsed');
    footer.style.marginLeft = collapsed ? '0' : 'var(--sidebar-w)';
  });
  if (sidebar) observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
</script>
<?php if (isset($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
