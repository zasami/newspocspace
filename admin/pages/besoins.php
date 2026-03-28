<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$besoinsRows = Db::fetchAll(
    "SELECT bc.*, m.code AS module_code, m.nom AS module_nom, f.code AS fonction_code, f.nom AS fonction_nom
     FROM besoins_couverture bc
     JOIN modules m ON m.id = bc.module_id
     JOIN fonctions f ON f.id = bc.fonction_id
     ORDER BY m.ordre, f.ordre, bc.jour_semaine"
);
$besoinsModules   = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre");
$besoinsFonctions = Db::fetchAll("SELECT id, code, nom FROM fonctions ORDER BY ordre");
?>
<style>
/* Besoins page classes */
.b-hidden { display: none; }
.b-spinner { width: 2rem; height: 2rem; }
.b-grid-wrap { max-height: calc(100vh - 230px); }
.b-empty-icon { font-size: 2.5rem; opacity: 0.3; }
.b-fte-day { min-width: 44px; }
.b-fte-day-label { font-size: 0.7rem; opacity: 0.7; }
.b-fte-day-value { font-size: 0.95rem; }
.b-col-header { min-width: 200px; max-width: 260px; }
.b-col-day { min-width: 56px; }
.b-col-action { min-width: 40px; }
.b-mod-sep { cursor: pointer; }
.b-chevron-icon { font-size: 0.75rem; }
.b-mod-subtotal { opacity: 0.6; font-weight: 400; font-size: 0.75rem; }
.b-cell-muted { font-size: 0.75rem; opacity: 0.6; }
.b-fn-cell { padding-left: 18px; }
.b-input {
  width: 42px;
  text-align: center;
  border: 1px solid var(--cl-border);
  border-radius: var(--cl-radius-xs);
  padding: 2px 0;
  font-size: 0.8rem;
  background: transparent;
}
.b-tfoot-row { font-weight: 700; background: var(--cl-bg); }
.b-tfoot-label { text-align: right; padding-right: 12px; }

/* Input save feedback states */
.b-input--saving {
  border-color: var(--cl-accent);
  background: rgba(25,25,24,0.04);
}
.b-input--success {
  border-color: var(--zt-green);
  background: rgba(22,163,74,0.06);
}
.b-input--error {
  border-color: var(--zt-red);
  background: rgba(220,38,38,0.06);
}
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold">Besoins de couverture</h5>
    <small class="text-muted">Nombre de collaborateurs requis par module, fonction et jour de la semaine</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-secondary btn-sm" id="besoins-copy-module" title="Copier les besoins d'un module vers un autre">
      <i class="bi bi-copy"></i> Dupliquer
    </button>
    <button class="btn btn-outline-primary btn-sm" id="besoins-collapse-all">
      <i class="bi bi-arrows-collapse"></i> Tout replier
    </button>
    <button class="btn btn-outline-primary btn-sm" id="besoins-expand-all">
      <i class="bi bi-arrows-expand"></i> Tout deplier
    </button>
  </div>
</div>

<!-- FTE Summary banner -->
<div id="besoins-fte-summary" class="card mb-3 b-hidden">
  <div class="card-body py-2 px-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-people-fill text-primary"></i>
        <span class="fw-semibold small">Postes requis / jour</span>
      </div>
      <div class="d-flex gap-3 flex-wrap" id="besoins-fte-days"></div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">Total semaine :</span>
        <span class="badge bg-primary" id="besoins-fte-total">0</span>
      </div>
    </div>
  </div>
</div>

<div id="besoins-loading" class="text-center py-5 b-hidden">
  <div class="admin-spinner b-spinner"></div>
  <p class="text-muted mt-2 small">Chargement...</p>
</div>

<div id="besoins-grid-wrap" class="tr-grid-wrap b-grid-wrap">
  <table class="tr-grid" id="besoins-table">
    <thead id="besoins-thead"></thead>
    <tbody id="besoins-tbody"></tbody>
    <tfoot id="besoins-tfoot"></tfoot>
  </table>
</div>

<div id="besoins-empty" class="card b-hidden">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-exclamation-triangle b-empty-icon"></i>
    <p class="mt-2">Aucun module ou fonction configures.</p>
    <p class="small">Configurez d'abord vos modules et fonctions dans les pages correspondantes.</p>
  </div>
</div>

<!-- Copy Module Modal -->
<div class="modal fade" id="besoinsCopyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-semibold"><i class="bi bi-copy me-2"></i>Dupliquer les besoins</h6>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-medium">Module source</label>
          <div class="zs-select" id="besoinsCopySource" data-placeholder="Sélectionner"></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Module cible</label>
          <div class="zs-select" id="besoinsCopyTarget" data-placeholder="Sélectionner"></div>
          <div class="form-text text-danger">Les besoins existants du module cible seront remplaces.</div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary btn-sm" id="besoinsCopyConfirm">
          <i class="bi bi-copy me-1"></i>Dupliquer
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
function initBesoinsPage() {
  const JOURS = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
  let modules = <?= json_encode(array_values($besoinsModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  let fonctions = <?= json_encode(array_values($besoinsFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  let besoinsMap = {};
  let collapsedModules = new Set();
  let copyModal = null;

  // Build initial besoinsMap from injected data
  const initBesoins = <?= json_encode(array_values($besoinsRows), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  initBesoins.forEach(b => {
    besoinsMap[b.module_id + '_' + b.fonction_id + '_' + b.jour_semaine] = parseInt(b.nb_requis) || 0;
  });
  if (modules.length && fonctions.length) {
    document.getElementById('besoins-grid-wrap').classList.remove('b-hidden');
    renderGrid();
  } else {
    document.getElementById('besoins-empty').classList.remove('b-hidden');
  }

  document.getElementById('besoins-collapse-all').addEventListener('click', () => {
    modules.forEach(m => collapsedModules.add(m.id));
    renderGrid();
  });
  document.getElementById('besoins-expand-all').addEventListener('click', () => {
    collapsedModules.clear();
    renderGrid();
  });

  // Copy module modal
  const copyModalEl = document.getElementById('besoinsCopyModal');
  if (copyModalEl) copyModal = new bootstrap.Modal(copyModalEl);
  document.getElementById('besoins-copy-module')?.addEventListener('click', openCopyModal);
  document.getElementById('besoinsCopyConfirm')?.addEventListener('click', doCopyModule);

  async function loadBesoins() {
    const res = await adminApiPost('admin_get_besoins');
    document.getElementById('besoins-loading').classList.add('b-hidden');
    if (!res.success) {
      showToast(res.message || 'Erreur chargement', 'error');
      return;
    }
    modules = res.modules || [];
    fonctions = res.fonctions || [];
    if (!modules.length || !fonctions.length) {
      document.getElementById('besoins-empty').classList.remove('b-hidden');
      return;
    }
    besoinsMap = {};
    (res.besoins || []).forEach(b => {
      besoinsMap[b.module_id + '_' + b.fonction_id + '_' + b.jour_semaine] = parseInt(b.nb_requis) || 0;
    });
    document.getElementById('besoins-grid-wrap').classList.remove('b-hidden');
    renderGrid();
  }

  function getVal(moduleId, fonctionId, jour) {
    return besoinsMap[moduleId + '_' + fonctionId + '_' + jour] || 0;
  }

  function renderFteSummary(dayTotals, grandTotal) {
    const wrap = document.getElementById('besoins-fte-summary');
    const daysEl = document.getElementById('besoins-fte-days');
    const totalEl = document.getElementById('besoins-fte-total');
    if (!wrap || !daysEl) return;

    let html = '';
    for (let j = 0; j < 7; j++) {
      const isWe = j >= 5;
      const cls = isWe ? 'text-warning' : 'text-body';
      html += '<div class="text-center b-fte-day">';
      html += '<div class="' + cls + ' b-fte-day-label">' + JOURS[j] + '</div>';
      html += '<div class="fw-bold ' + cls + ' b-fte-day-value">' + dayTotals[j] + '</div>';
      html += '</div>';
    }
    daysEl.innerHTML = html;
    totalEl.textContent = grandTotal + ' postes/sem';
    wrap.classList.remove('b-hidden');
  }

  function renderGrid() {
    // Header
    const thead = document.getElementById('besoins-thead');
    let hhtml = '<tr><th class="col-user b-col-header">Module / Fonction</th>';
    JOURS.forEach((j, i) => {
      const cls = (i >= 5) ? ' th-we' : '';
      hhtml += '<th class="b-col-day' + cls + '">' + j + '</th>';
    });
    hhtml += '<th class="col-total b-col-day">Total</th><th class="b-col-action"></th></tr>';
    thead.innerHTML = hhtml;

    // Body
    const tbody = document.getElementById('besoins-tbody');
    let bhtml = '';
    const dayTotals = [0,0,0,0,0,0,0];
    let grandTotal = 0;

    modules.forEach(mod => {
      const isCollapsed = collapsedModules.has(mod.id);
      // Module separator row
      const chevron = isCollapsed ? 'bi-chevron-right' : 'bi-chevron-down';
      let modTotal = 0;
      fonctions.forEach(fn => {
        for (let j = 1; j <= 7; j++) modTotal += getVal(mod.id, fn.id, j);
      });

      bhtml += '<tr class="mod-sep b-mod-sep" data-mod-toggle="' + mod.id + '">';
      bhtml += '<td><i class="bi ' + chevron + ' me-2 b-chevron-icon"></i>' + escapeHtml(mod.code + ' - ' + mod.nom);
      bhtml += ' <span class="b-mod-subtotal">(' + modTotal + ' postes/sem)</span>';
      bhtml += '</td>';
      // Module day subtotals in header row
      let modDayTotals = [0,0,0,0,0,0,0];
      fonctions.forEach(fn => {
        for (let j = 1; j <= 7; j++) modDayTotals[j-1] += getVal(mod.id, fn.id, j);
      });
      for (let j = 0; j < 7; j++) {
        const cls = (j >= 5) ? ' td-we' : '';
        bhtml += '<td class="b-cell-muted' + cls + '">' + (modDayTotals[j] || '') + '</td>';
      }
      bhtml += '<td class="col-total b-cell-muted">' + modTotal + '</td>';
      bhtml += '<td><button class="btn btn-sm p-0 text-danger besoin-reset-mod" data-mod-id="' + mod.id + '" data-mod-name="' + escapeHtml(mod.code) + '" title="Remettre a zero" data-action="reset-mod"><i class="bi bi-arrow-counterclockwise b-chevron-icon"></i></button></td>';
      bhtml += '</tr>';

      if (!isCollapsed) {
        fonctions.forEach(fn => {
          let rowTotal = 0;
          bhtml += '<tr data-mod-rows="' + mod.id + '">';
          bhtml += '<td class="col-user b-fn-cell"><span class="fn-badge">' + escapeHtml(fn.code) + '</span> ' + escapeHtml(fn.nom) + '</td>';
          for (let j = 1; j <= 7; j++) {
            const val = getVal(mod.id, fn.id, j);
            const cls = (j >= 6) ? ' td-we' : '';
            rowTotal += val;
            dayTotals[j-1] += val;
            grandTotal += val;
            bhtml += '<td class="dc' + cls + '">';
            bhtml += '<input type="number" min="0" max="99" value="' + val + '" ';
            bhtml += 'data-mod="' + mod.id + '" data-fn="' + fn.id + '" data-jour="' + j + '" ';
            bhtml += 'class="besoin-input b-input">';
            bhtml += '</td>';
          }
          bhtml += '<td class="col-total">' + rowTotal + '</td>';
          bhtml += '<td></td>';
          bhtml += '</tr>';
        });
      } else {
        // Still count for totals even when collapsed
        fonctions.forEach(fn => {
          for (let j = 1; j <= 7; j++) {
            const val = getVal(mod.id, fn.id, j);
            dayTotals[j-1] += val;
            grandTotal += val;
          }
        });
      }
    });
    tbody.innerHTML = bhtml;

    // Footer totals
    const tfoot = document.getElementById('besoins-tfoot');
    let fhtml = '<tr class="b-tfoot-row">';
    fhtml += '<td class="col-user b-tfoot-label">Total / jour</td>';
    for (let j = 0; j < 7; j++) {
      const cls = (j >= 5) ? ' td-we' : '';
      fhtml += '<td class="' + cls + '">' + dayTotals[j] + '</td>';
    }
    fhtml += '<td class="col-total">' + grandTotal + '</td>';
    fhtml += '<td></td>';
    fhtml += '</tr>';
    tfoot.innerHTML = fhtml;

    // FTE summary banner
    renderFteSummary(dayTotals, grandTotal);

    // Event delegation on tbody
    bindTableEvents(tbody);
  }

  function bindTableEvents(tbody) {
    // Use event delegation for all tbody interactions
    tbody.addEventListener('change', function(e) {
      if (e.target.classList.contains('besoin-input')) {
        handleInputChange(e);
      }
    });
    tbody.addEventListener('focus', function(e) {
      if (e.target.classList.contains('besoin-input')) {
        e.target.select();
      }
    }, true);
    tbody.addEventListener('click', function(e) {
      // Reset module button (stop propagation to prevent mod-toggle)
      const resetBtn = e.target.closest('[data-action="reset-mod"]');
      if (resetBtn) {
        e.stopPropagation();
        resetModuleBesoins(resetBtn.dataset.modId, resetBtn.dataset.modName);
        return;
      }
      // Module toggle
      const toggleRow = e.target.closest('[data-mod-toggle]');
      if (toggleRow) {
        const modId = toggleRow.dataset.modToggle;
        if (collapsedModules.has(modId)) collapsedModules.delete(modId);
        else collapsedModules.add(modId);
        renderGrid();
      }
    });
  }

  async function handleInputChange(e) {
    const input = e.target;
    const moduleId = input.dataset.mod;
    const fonctionId = input.dataset.fn;
    const jour = parseInt(input.dataset.jour);
    const val = parseInt(input.value) || 0;
    input.value = val;

    // Update local map
    besoinsMap[moduleId + '_' + fonctionId + '_' + jour] = val;

    // Visual feedback
    input.classList.remove('b-input--success', 'b-input--error');
    input.classList.add('b-input--saving');

    const res = await adminApiPost('admin_save_besoin', {
      module_id: moduleId,
      fonction_id: fonctionId,
      jour_semaine: jour,
      nb_requis: val
    });

    if (res.success) {
      input.classList.remove('b-input--saving');
      input.classList.add('b-input--success');
      setTimeout(() => {
        input.classList.remove('b-input--success');
      }, 800);
      // Re-render to update totals
      renderGrid();
    } else {
      input.classList.remove('b-input--saving');
      input.classList.add('b-input--error');
      showToast(res.message || 'Erreur sauvegarde', 'error');
    }
  }

  async function resetModuleBesoins(moduleId, moduleName) {
    if (!confirm('Remettre a zero tous les besoins du module ' + moduleName + ' ?')) return;
    const res = await adminApiPost('admin_reset_module_besoins', { module_id: moduleId });
    if (res.success) {
      // Clear local map for this module
      fonctions.forEach(fn => {
        for (let j = 1; j <= 7; j++) {
          delete besoinsMap[moduleId + '_' + fn.id + '_' + j];
        }
      });
      showToast('Besoins du module ' + moduleName + ' remis a zero', 'success');
      renderGrid();
    } else {
      showToast(res.message || 'Erreur', 'error');
    }
  }

  function openCopyModal() {
    if (modules.length < 2) {
      showToast('Il faut au moins 2 modules pour dupliquer', 'error');
      return;
    }
    const srcEl = document.getElementById('besoinsCopySource');
    const tgtEl = document.getElementById('besoinsCopyTarget');
    const opts = modules.map(m => ({value: m.id, label: m.code + ' - ' + m.nom}));
    zerdaSelect.destroy(srcEl);
    zerdaSelect.destroy(tgtEl);
    zerdaSelect.init(srcEl, opts, { value: modules[0].id });
    zerdaSelect.init(tgtEl, opts, { value: modules.length > 1 ? modules[1].id : modules[0].id });
    if (copyModal) copyModal.show();
  }

  async function doCopyModule() {
    const sourceId = zerdaSelect.getValue('#besoinsCopySource');
    const targetId = zerdaSelect.getValue('#besoinsCopyTarget');
    if (sourceId === targetId) {
      showToast('Les modules source et cible doivent etre differents', 'error');
      return;
    }
    const btn = document.getElementById('besoinsCopyConfirm');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const res = await adminApiPost('admin_copy_module_besoins', {
      source_module_id: sourceId,
      target_module_id: targetId
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-copy me-1"></i>Dupliquer';

    if (res.success) {
      if (copyModal) copyModal.hide();
      showToast(res.message, 'success');
      // Reload to get fresh data
      document.getElementById('besoins-loading').classList.remove('b-hidden');
      document.getElementById('besoins-grid-wrap').classList.add('b-hidden');
      loadBesoins();
    } else {
      showToast(res.message || 'Erreur', 'error');
    }
  }
}
window.initBesoinsPage = initBesoinsPage;
</script>
