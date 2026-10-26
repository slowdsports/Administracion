/* AHDECO - Sistema de Administración | JS principal */
'use strict';

// ── Toast notifications ──────────────────────────────────────────
const Toast = {
  show(msg, type = 'info', duration = 4000) {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const el = document.createElement('div');
    el.className = `toast-msg ${type}`;
    el.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${msg}`;
    c.appendChild(el);
    setTimeout(() => { el.style.animation = 'fadeOut .4s forwards'; setTimeout(() => el.remove(), 400); }, duration);
  }
};

// ── AJAX helper ──────────────────────────────────────────────────
async function ajax(url, data = null, method = 'GET') {
  const opts = {
    method,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  };
  if (data) {
    if (data instanceof FormData) {
      opts.body = data;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    }
  }
  const res = await fetch(url, opts);
  if (!res.ok) {
    let body;
    try { body = await res.json(); } catch (_) {}
    throw new Error(body?.message || `HTTP ${res.status}`);
  }
  return res.json();
}

async function post(url, data) { return ajax(url, data, 'POST'); }

// ── Dark / Light mode ─────────────────────────────────────────────
function toggleTheme() {
  const html    = document.documentElement;
  const isDark  = html.getAttribute('data-theme') === 'dark';
  const next    = isDark ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('ahdeco-theme', next);
  _syncThemeIcon(next);
  document.dispatchEvent(new CustomEvent('ahdeco:themechange', { detail: { theme: next } }));
}

function _syncThemeIcon(theme) {
  const icon = document.getElementById('theme-icon');
  if (!icon) return;
  if (theme === 'dark') {
    icon.classList.replace('fa-moon', 'fa-sun');
  } else {
    icon.classList.replace('fa-sun', 'fa-moon');
  }
}

// ── Sidebar toggle ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Sync icon with stored theme on load
  _syncThemeIcon(localStorage.getItem('ahdeco-theme') || 'light');
  const sidebar = document.getElementById('sidebar');
  const main    = document.getElementById('main-content');
  const btn     = document.getElementById('btn-toggle-sidebar');

  // persist state
  const collapsed = localStorage.getItem('sidebar-collapsed') === '1';
  if (collapsed) { sidebar?.classList.add('collapsed'); main?.classList.add('expanded'); }

  btn?.addEventListener('click', () => {
    const isMobile = window.innerWidth < 768;
    if (isMobile) {
      sidebar?.classList.toggle('open');
    } else {
      sidebar?.classList.toggle('collapsed');
      main?.classList.toggle('expanded');
      localStorage.setItem('sidebar-collapsed', sidebar?.classList.contains('collapsed') ? '1' : '0');
    }
  });

  // Mark active sidebar link
  const current = window.location.pathname.split('/').pop().replace(/\?.*$/, '');
  document.querySelectorAll('.sidebar-item[data-page]').forEach(el => {
    if (el.dataset.page === current) el.classList.add('active');
  });

  // Sub-menus toggle
  document.querySelectorAll('.sidebar-group-title').forEach(t => {
    t.addEventListener('click', () => {
      const sub = t.nextElementSibling;
      if (sub?.classList.contains('sidebar-submenu')) {
        sub.style.display = sub.style.display === 'none' ? 'block' : 'none';
      }
    });
  });

  // Auto-close sidebar overlay on mobile when clicking outside
  document.addEventListener('click', (e) => {
    if (window.innerWidth < 768 && sidebar?.classList.contains('open')) {
      if (!sidebar.contains(e.target) && e.target !== btn) {
        sidebar.classList.remove('open');
      }
    }
  });
});

// ── Confirm dialog ────────────────────────────────────────────────
function confirmAction(msg, callback) {
  if (confirm(msg)) callback();
}

// ── Table row actions (generic) ───────────────────────────────────
function setupTableActions(tableId, callbacks = {}) {
  document.getElementById(tableId)?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const id     = btn.dataset.id;
    if (callbacks[action]) callbacks[action](id, btn);
  });
}

// ── Dynamic form rows (for detail tables) ─────────────────────────
function addDetailRow(containerId, templateFn, counter) {
  const c = document.getElementById(containerId);
  const idx = c.querySelectorAll('.detail-row').length;
  const row = document.createElement('tr');
  row.className = 'detail-row';
  row.innerHTML = templateFn(idx);
  c.appendChild(row);
  updateDetailRowNumbers(containerId);
  return row;
}

function removeDetailRow(btn) {
  btn.closest('.detail-row')?.remove();
  recalcTotals();
}

function updateDetailRowNumbers(containerId) {
  document.querySelectorAll(`#${containerId} .detail-row`).forEach((r, i) => {
    const num = r.querySelector('.row-num');
    if (num) num.textContent = i + 1;
  });
}

// ── Recalc totals in forms ─────────────────────────────────────────
function recalcTotals() {
  let total = 0;
  document.querySelectorAll('.detail-row .row-monto').forEach(el => {
    total += parseFloat(el.value || 0);
  });
  document.querySelectorAll('.detail-row .row-total').forEach(el => {
    const qty   = parseFloat(el.closest('.detail-row').querySelector('.row-qty')?.value || 1);
    const price = parseFloat(el.closest('.detail-row').querySelector('.row-price')?.value || 0);
    el.value = (qty * price).toFixed(2);
  });
  // Sum all visible totals
  total = 0;
  document.querySelectorAll('.detail-row .row-total, .detail-row .row-monto').forEach(el => {
    total += parseFloat(el.value || 0);
  });
  const totalEl = document.getElementById('grand-total');
  if (totalEl) totalEl.textContent = 'L. ' + total.toLocaleString('es-HN', { minimumFractionDigits: 2 });

  const totalInput = document.getElementById('monto_total');
  if (totalInput) totalInput.value = total.toFixed(2);
}

// ── Qty × price auto-calc ─────────────────────────────────────────
document.addEventListener('input', (e) => {
  if (e.target.matches('.row-qty, .row-price')) {
    const row   = e.target.closest('.detail-row');
    const qty   = parseFloat(row.querySelector('.row-qty')?.value || 1);
    const price = parseFloat(row.querySelector('.row-price')?.value || 0);
    const tot   = row.querySelector('.row-total');
    if (tot) tot.value = (qty * price).toFixed(2);
    recalcTotals();
  }
  if (e.target.matches('.row-monto')) recalcTotals();
});

// ── Form submit with AJAX ──────────────────────────────────────────
function bindAjaxForm(formId, onSuccess) {
  const form = document.getElementById(formId);
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('[type=submit]');
    const orig = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'; }
    try {
      const fd  = new FormData(form);
      const res = await post(form.action || window.location.href, fd);
      if (res.success) {
        Toast.show(res.message || 'Guardado exitosamente.', 'success');
        if (onSuccess) onSuccess(res);
      } else {
        Toast.show(res.message || 'Error al guardar.', 'error');
      }
    } catch (err) {
      Toast.show(err.message || 'Error de comunicación con el servidor.', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  });
}

// ── Status change action ───────────────────────────────────────────
async function cambiarEstado(url, id, estado, tabla, onDone) {
  const token = document.querySelector('meta[name="csrf"]')?.content || '';
  const fd = new FormData();
  fd.append('_action', 'cambiar_estado');
  fd.append('id', id);
  fd.append('estado', estado);
  fd.append('csrf_token', token);
  try {
    const res = await post(url, fd);
    if (res.success) {
      Toast.show(res.message || 'Estado actualizado.', 'success');
      if (onDone) onDone(res);
    } else {
      Toast.show(res.message || 'No se pudo cambiar el estado.', 'error');
    }
  } catch {
    Toast.show('Error de conexión.', 'error');
  }
}

// ── Print document ────────────────────────────────────────────────
function printDoc(selector = '#print-area') {
  const content = document.querySelector(selector)?.innerHTML;
  if (!content) return window.print();
  const w = window.open('', '_blank');
  w.document.write(`<!DOCTYPE html><html><head>
    <title>AHDECO - Documento</title>
    <link rel="stylesheet" href="/Administracion/assets/css/ahdeco.css">
    <style>body{padding:20px} @media print{.no-print{display:none}}</style>
  </head><body>${content}</body></html>`);
  w.document.close();
  setTimeout(() => { w.print(); }, 500);
}

// ── Date helpers ──────────────────────────────────────────────────
function today() { return new Date().toISOString().split('T')[0]; }

// ── Number formatting ─────────────────────────────────────────────
function lps(n) {
  return 'L. ' + parseFloat(n || 0).toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── DataTable default init ────────────────────────────────────────
function initDataTable(selector, opts = {}) {
  if (typeof $ === 'undefined' || !$.fn.DataTable) return;
  $(selector).DataTable(Object.assign({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json'
    },
    pageLength: 20,
    responsive: true,
    dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
  }, opts));
}

// ── Delete with confirmation ──────────────────────────────────────
async function deleteRecord(url, id, onDone) {
  if (!confirm('¿Está seguro que desea eliminar este registro? Esta acción no se puede deshacer.')) return;
  const token = document.querySelector('meta[name="csrf"]')?.content || '';
  try {
    const res = await post(url, { id, _action: 'eliminar', csrf_token: token });
    if (res.success) {
      Toast.show(res.message || 'Registro eliminado.', 'success');
      if (onDone) onDone();
    } else {
      Toast.show(res.message || 'No se pudo eliminar.', 'error');
    }
  } catch { Toast.show('Error de conexión.', 'error'); }
}

// ── Editor de distribución por proyecto (empleados / planillas) ───
// Agrega filas [proyecto + %] a un <tbody>. Totales en celdas [data-tot="campo"]
// y mensaje en [data-dist-msg] dentro de la misma tabla. El primer campo % se
// copia a los demás mientras éstos no se editen a mano.
// Opcional monto: { campo, base: () => L. } agrega una columna de monto que se
// calcula desde el % de `campo` y, si se escribe el monto, recalcula el %.
class DistEditor {
  static labels = { pct_salario: '% Salario', pct_patronal: '% Patronal', porcentaje: '% Beneficio' };

  constructor(tbody, { proyectos, campos, prefix = 'dist', onChange = null, monto = null }) {
    Object.assign(this, { tbody, proyectos, campos, prefix, onChange, monto, n: 0 });
    this.table = tbody.closest('table');
  }

  static pct(v) { return String(+(+v).toFixed(6)); }

  // Recalcula los montos desde los % (p. ej. al cambiar el sueldo)
  refreshMontos() {
    if (!this.monto) return;
    [...this.tbody.rows].forEach(tr => {
      const p = tr.querySelector(`input[data-campo="${this.monto.campo}"]`);
      tr.querySelector('input[data-monto]').value = (this.monto.base() * (parseFloat(p.value) || 0) / 100).toFixed(2);
    });
    this.update();
  }

  set(rows) {
    this.tbody.innerHTML = '';
    (rows || []).forEach(r => this.add(r, true));
    this.update();
  }

  add(r = {}, silent = false) {
    if (!silent && r.proyecto_id === undefined) {
      const t = this.totals();
      this.campos.forEach(c => r[c] = Math.max(0, +(100 - t[c]).toFixed(6)));
    }
    const i = this.n++, tr = document.createElement('tr');
    const sel = document.createElement('select');
    sel.className = 'form-select form-select-sm no-search';
    sel.name = `${this.prefix}[${i}][proyecto_id]`;
    sel.add(new Option('— Proyecto —', ''));
    this.proyectos.forEach(p => {
      const activo = p.estado === 'activo';
      if (!activo && String(p.id) !== String(r.proyecto_id)) return;
      sel.add(new Option(`${p.codigo} — ${p.nombre}${activo ? '' : ' (' + p.estado + ')'}`, p.id));
    });
    sel.value = r.proyecto_id ?? '';
    sel.addEventListener('change', () => this.update());
    tr.insertCell().appendChild(sel);

    const inputs = this.campos.map((c, k) => {
      const inp = document.createElement('input');
      Object.assign(inp, { type: 'number', step: 'any', min: '0', max: '100',
        name: `${this.prefix}[${i}][${c}]`, className: 'form-control form-control-sm text-end font-mono' });
      inp.dataset.campo = c;
      inp.value = r[c] !== undefined ? DistEditor.pct(r[c]) : '';
      if (k > 0 && r.proyecto_id !== undefined && parseFloat(r[c]) !== parseFloat(r[this.campos[0]])) inp.dataset.manual = '1';
      tr.insertCell().appendChild(inp);
      return inp;
    });

    // Columna de monto (solo informativa; el % es lo que se guarda)
    let montoInp = null;
    const pctMonto = this.monto ? inputs[this.campos.indexOf(this.monto.campo)] : null;
    const syncMonto = () => {
      if (montoInp) montoInp.value = (this.monto.base() * (parseFloat(pctMonto.value) || 0) / 100).toFixed(2);
    };
    if (this.monto) {
      montoInp = document.createElement('input');
      Object.assign(montoInp, { type: 'number', step: '0.01', min: '0',
        className: 'form-control form-control-sm text-end font-mono' });
      montoInp.dataset.monto = '1';
      tr.insertCell().appendChild(montoInp);
      syncMonto();
    }

    const propagar = () => inputs.slice(1).forEach(o => { if (!o.dataset.manual) o.value = inputs[0].value; });
    inputs.forEach((inp, k) => inp.addEventListener('input', () => {
      if (k === 0) propagar(); else inp.dataset.manual = '1';
      syncMonto();
      this.update();
    }));
    if (montoInp) montoInp.addEventListener('input', () => {
      const base = this.monto.base();
      if (base <= 0) { Toast.show('Ingrese primero el sueldo mensual.', 'error'); return; }
      pctMonto.value = DistEditor.pct((parseFloat(montoInp.value) || 0) / base * 100);
      if (pctMonto === inputs[0]) propagar();
      this.update();
    });

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn btn-sm btn-outline-danger py-0 px-2';
    del.title = 'Quitar proyecto';
    del.innerHTML = '<i class="fas fa-times"></i>';
    del.addEventListener('click', () => { tr.remove(); this.update(); });
    const tdDel = tr.insertCell();
    tdDel.className = 'text-center';
    tdDel.appendChild(del);

    this.tbody.appendChild(tr);
    if (!silent) this.update();
    return tr;
  }

  rows() {
    return [...this.tbody.rows].map(tr => {
      const r = { proyecto_id: tr.querySelector('select').value };
      tr.querySelectorAll('input[data-campo]').forEach(inp => r[inp.dataset.campo] = parseFloat(inp.value) || 0);
      return r;
    }).filter(r => r.proyecto_id);
  }

  totals() {
    const t = Object.fromEntries(this.campos.map(c => [c, 0]));
    this.rows().forEach(r => this.campos.forEach(c => t[c] += r[c]));
    return t;
  }

  error() {
    const rows = this.rows();
    if (!rows.length) return '';
    const ids = rows.map(r => r.proyecto_id);
    if (new Set(ids).size !== ids.length) return 'Un proyecto aparece más de una vez.';
    const t = this.totals();
    for (const c of this.campos) {
      if (Math.abs(t[c] - 100) > 0.001) return `${DistEditor.labels[c] || c} suma ${t[c].toFixed(2)}%; debe sumar 100%.`;
    }
    return '';
  }

  update() {
    const t = this.totals(), hay = this.rows().length > 0;
    this.campos.forEach(c => {
      const cell = this.table.querySelector(`[data-tot="${c}"]`);
      if (!cell) return;
      cell.textContent = t[c].toFixed(2) + '%';
      cell.classList.toggle('text-danger', hay && Math.abs(t[c] - 100) > 0.001);
      cell.classList.toggle('text-success', hay && Math.abs(t[c] - 100) <= 0.001);
    });
    const totMonto = this.table.querySelector('[data-tot="monto"]');
    if (totMonto && this.monto) {
      let suma = 0;
      [...this.tbody.rows].forEach(tr => {
        if (tr.querySelector('select').value) suma += parseFloat(tr.querySelector('input[data-monto]').value) || 0;
      });
      totMonto.textContent = 'L. ' + suma.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      totMonto.title = 'Quincena: L. ' + this.monto.base().toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const msg = this.table.querySelector('[data-dist-msg]');
    if (msg) {
      const err = this.error();
      msg.textContent = hay ? err : 'Sin proyectos asignados: se reporta 100% como "Sin proyecto".';
      msg.className = err ? 'text-danger' : 'text-muted';
    }
    if (this.onChange) this.onChange(this);
  }
}

// ── Motion: card stagger · count-up · button ripple ──────────────

document.addEventListener('DOMContentLoaded', () => {
  // Stagger .card entrance animations via per-card animation-delay
  document.querySelectorAll('.card').forEach((el, i) => {
    el.style.animationDelay = Math.min(i * 0.055, 0.44) + 's';
  });

  // Count-up animation for KPI values (triggered when they enter viewport)
  if (typeof IntersectionObserver !== 'undefined') {
    const cuObs = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) { _ahdCountUp(e.target); cuObs.unobserve(e.target); }
      });
    }, { threshold: 0.6 });
    document.querySelectorAll('.kpi-value').forEach(el => cuObs.observe(el));
  }
});

// Ease-out cubic count-up for a single .kpi-value element
function _ahdCountUp(el) {
  const txt  = el.textContent.trim();
  const isLp = txt.includes('L.');
  const raw  = parseFloat(txt.replace(/[^0-9.]/g, ''));
  if (!raw || raw < 2) return;

  const dur = 850, t0 = performance.now();
  (function tick(ts) {
    const p   = Math.min((ts - t0) / dur, 1);
    const val = raw * (1 - Math.pow(1 - p, 3));
    el.textContent = isLp
      ? 'L. ' + val.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      : Math.round(val).toLocaleString('es-HN');
    if (p < 1) requestAnimationFrame(tick);
  })(t0);
}

// Ripple effect on button clicks (.btn, .btn-ahdeco, .btn-ahdeco-outline)
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn:not(.btn-link), .btn-ahdeco, .btn-ahdeco-outline');
  if (!btn) return;
  const rect = btn.getBoundingClientRect();
  const size = Math.max(rect.width, rect.height) * 2.2;
  const spot = document.createElement('span');
  spot.className = 'btn-ripple-el';
  spot.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - rect.left}px;top:${e.clientY - rect.top}px`;
  if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
  btn.style.overflow = 'hidden';
  btn.appendChild(spot);
  setTimeout(() => spot.remove(), 750);
}, true);
