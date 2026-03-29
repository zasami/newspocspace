<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$pvAdminModules = Db::fetchAll("SELECT id, nom, code FROM modules ORDER BY ordre, nom");
$pvAdminEtages = Db::fetchAll("SELECT e.id, e.nom, e.code, m.id AS module_id, m.code AS module_code FROM etages e JOIN modules m ON m.id = e.module_id ORDER BY m.ordre, e.ordre");
$pvAdminFonctions = Db::fetchAll("SELECT id, nom, code FROM fonctions ORDER BY ordre, nom");
$pvAdminUsers = Db::fetchAll("SELECT u.id, u.prenom, u.nom, u.fonction_id, u.email, u.photo, f.nom AS fonction_nom FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id ORDER BY u.nom, u.prenom");
?>
<!-- PV List Page -->
<style>
/* PV Badge Colors - Theme aligned */
.pv-badge-finalise { background-color: #bcd2cb !important; color: #2d4a43 !important; }
.pv-badge-enregistrement { background-color: #E8E5E0; color: var(--cl-text, #1A1A1A); font-weight: 600; }
.pv-badge-brouillon { background-color: #F0EDE8; color: var(--cl-text-secondary, #6B6B6B); }
.pv-badge-en_validation { background-color: #FFF3CD; color: #856404; font-weight: 600; }

/* Status icon squares */
.pv-status-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 9px; font-size: 1rem;
  flex-shrink: 0; vertical-align: middle;
}
.pv-status-icon.si-brouillon { background: #F0EDE8; color: #8A857D; }
.pv-status-icon.si-enregistrement { background: #E8E5E0; color: #5A5550; }
.pv-status-icon.si-en_validation { background: #FFF3CD; color: #856404; }
.pv-status-icon.si-finalise { background: #bcd2cbb0; color: #2d4a4399; }

/* Table hover & padding */
#pvTable th, #pvTable td { padding-left: 1rem; padding-right: 1.1rem; }
#pvTable th:first-child, #pvTable td:first-child { padding-left: .85rem; }
#pvTable th:last-child, #pvTable td:last-child { padding-right: 1.4rem; }
#pvTable tbody tr { transition: background-color 0.2s; }
#pvTable tbody tr:hover { background-color: rgba(25, 25, 24, 0.04); }

/* Table column widths */
.pv-col-titre { width: 40%; }
.pv-col-createur { width: 15%; }
.pv-col-module { width: 15%; }
.pv-col-date { width: 12%; }
.pv-col-statut { width: 10%; }
.pv-col-actions { width: 8%; text-align: center; }

/* Delete button */
.pv-btn-del, .pv-btn-archive {
  color: var(--cl-text-secondary, #6B6B6B);
  border: 1px solid var(--cl-border, #E8E5E0);
  background: transparent;
  border-radius: 6px;
  padding: 2px 7px;
  transition: all 0.2s;
}
.pv-btn-del:hover {
  background: #F5E6E0;
  color: #9B2C2C;
  border-color: #E2B8AE;
}
.pv-btn-archive:hover {
  background: #E8E5DC;
  color: #5B4B3B;
  border-color: #D4C4A8;
}

/* Archive filter toggle — match .zs-toggle height (38px) */
.pv-archive-filter .btn {
  height: 38px; padding: 0 .75rem; font-size: .88rem; font-weight: 500;
  border-radius: 8px; border-width: 1.5px; display: inline-flex; align-items: center;
}
.pv-archive-filter .btn.active {
  background: var(--cl-accent, #191918);
  color: #fff;
  border-color: var(--cl-accent, #191918);
}
/* Reset button — match .zs-toggle height */
#btnFilterClear {
  height: 38px; padding: 0 .75rem; font-size: .88rem; font-weight: 500;
  border-radius: 8px; border-width: 1.5px; display: inline-flex; align-items: center;
}
.pv-badge-archive { background-color: #E8E5DC !important; color: #5B4B3B !important; }

/* Reusable: filter select max-width */
.pv-filter-select { max-width: 200px; }

/* Reusable: muted placeholder color */
.pv-color-muted { color: #999; }

/* Participant cards (same style as email recipients) */
.pv-participant-card {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px; border-radius: 8px; border: 2px solid transparent;
  cursor: pointer; transition: all 0.15s ease; background: #fff;
  user-select: none;
}
.pv-participant-card:hover { background: #F5F3EE; border-color: #D4C4A8; }
.pv-participant-card.selected { border-color: #191918; background: #FAFAF8; }
.pv-participant-card .pv-pc-avatar {
  width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.pv-participant-card .pv-pc-avatar-placeholder {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  background: #E8E5DC; display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .8rem; color: #5B4B3B; text-transform: uppercase;
}
.pv-participant-card .pv-pc-info { flex: 1; min-width: 0; }
.pv-participant-card .pv-pc-name { font-size: .85rem; font-weight: 600; color: #191918; }
.pv-participant-card .pv-pc-fonction { font-size: .75rem; color: #999; font-weight: 400; }
.pv-participant-card .pv-pc-check {
  width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s ease;
  opacity: 0; background: transparent; border: none;
}
.pv-participant-card .pv-pc-check .bi { font-size: .85rem; }
.pv-participant-card.selected .pv-pc-check {
  opacity: 1; background: #191918; color: #fff; border-radius: 50%;
}

/* Accent button (reusable) */
.pv-btn-accent {
  background-color: var(--cl-accent, #191918);
  border: none;
  color: #fff;
  font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px);
  transition: all 0.2s;
}
.pv-btn-accent:hover {
  background-color: var(--cl-accent-hover, #000);
  color: #fff;
}

/* Participants count badge */
.pv-participants-badge {
  background: var(--cl-accent, #191918);
  font-size: .7rem;
  min-width: 22px;
}

/* Participants section */
.pv-participants-wrap { background: #FAFAF8; }
.pv-participants-toolbar { background: #fff; }
.pv-participants-search-icon {
  left: 10px; top: 50%; transform: translateY(-50%);
  font-size: .78rem; color: #999;
}
.pv-participants-search-input {
  padding-left: 30px; padding-right: 28px;
  font-size: .82rem; border-radius: 6px;
}
.pv-participants-search-clear {
  right: 4px; top: 50%; transform: translateY(-50%);
  width: 20px; height: 20px; padding: 0;
  border: none; background: transparent;
}
.pv-participants-search-clear .bi { font-size: .68rem; color: #999; }
.pv-participants-fonction-filter {
  width: auto; min-width: 100px;
  font-size: .82rem; border-radius: 6px;
}
.pv-participants-list { max-height: 200px; overflow-y: auto; }

/* Confirm modal content */
.pv-confirm-modal-content {
  border-radius: var(--cl-radius, 16px);
  border: none;
  box-shadow: var(--cl-shadow-md);
}

/* Confirm modal */
.pv-confirm-icon {
  width: 48px; height: 48px; border-radius: 50%;
  background: #F5E6E0; color: #9B2C2C;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem;
  font-size: 1.3rem;
}
.pv-btn-confirm-del {
  background: #9B2C2C; border: none; color: #fff; font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-confirm-del:hover { background: #7B1F1F; color: #fff; }

/* Create modal dialog width */
.pv-modal-create .modal-dialog { max-width: 580px; }

/* Utility: clickable row */
.pv-row-clickable { cursor: pointer; }

/* Utility: nowrap actions cell */
.pv-cell-nowrap { white-space: nowrap; }

/* Utility: visibility toggle */
.pv-hidden { display: none !important; }
.pv-visible-inline { display: inline-block !important; }
.pv-visible-flex { display: flex !important; }

/* zerdaSelect filter width */
#pvModuleFilter,
#pvEtageFilter,
#pvFonctionFilter { max-width: 200px; }
</style>

<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-file-earmark-text"></i> Procès-Verbaux
    </h1>
    <button class="btn pv-btn-accent" id="btnCreatePv">
      <i class="bi bi-plus-lg"></i> Nouveau PV
    </button>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body py-2">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="btn-group btn-group-sm pv-archive-filter" id="pvArchiveFilter">
          <button class="btn btn-outline-secondary active" data-archive="0"><i class="bi bi-file-earmark-text me-1"></i>Actifs</button>
          <button class="btn btn-outline-secondary" data-archive="1"><i class="bi bi-archive me-1"></i>Archivés</button>
        </div>
        <div class="zs-select pv-filter-select" id="pvModuleFilter" data-placeholder="Choisir le module"></div>
        <div class="zs-select pv-filter-select" id="pvEtageFilter" data-placeholder="Choisir l'étage"></div>
        <div class="zs-select pv-filter-select" id="pvFonctionFilter" data-placeholder="Choisir la fonction"></div>
        <button class="btn btn-sm btn-outline-secondary" id="btnFilterClear">
          <i class="bi bi-arrow-clockwise"></i> Réinitialiser
        </button>
      </div>
    </div>
  </div>

  <!-- PV List Table -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="pvTable">
        <thead class="table-light">
          <tr>
            <th class="pv-col-titre">Titre</th>
            <th class="pv-col-createur">Créateur</th>
            <th class="pv-col-module">Module / Étage</th>
            <th class="pv-col-date">Date</th>
            <th class="pv-col-statut">Statut</th>
            <th class="pv-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody id="pvTableBody">
          <tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-center text-muted small">
      <span id="pvCount">—</span> procès-verbaux
    </div>
  </div>

  <!-- Pagination -->
  <div class="d-flex justify-content-center mt-4">
    <nav>
      <ul class="pagination pagination-sm" id="pvPagination"></ul>
    </nav>
  </div>
</div>

<!-- Modal: Create PV -->
<div class="modal fade pv-modal-create" id="modalCreatePv" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> Nouveau PV</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Titre *</label>
          <input type="text" class="form-control form-control-sm" id="pvFormTitre" placeholder="Ex: Réunion d'équipe - Mars 2026">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Description</label>
          <textarea class="form-control form-control-sm" id="pvFormDescription" rows="3" placeholder="Contexte, ordre du jour..."></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small">Module</label>
            <div class="zs-select" id="pvFormModule" data-placeholder="—"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Étage</label>
            <div class="zs-select" id="pvFormEtage" data-placeholder="—"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Fonction concernée</label>
          <div class="zs-select" id="pvFormFonction" data-placeholder="—"></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold d-flex align-items-center justify-content-between">
            <span>Participants</span>
            <span id="pvParticipantsCount" class="badge rounded-pill pv-participants-badge pv-hidden">0</span>
          </label>
          <div class="border rounded overflow-hidden pv-participants-wrap">
            <div class="d-flex gap-2 p-2 border-bottom pv-participants-toolbar">
              <div class="position-relative flex-grow-1">
                <i class="bi bi-search position-absolute pv-participants-search-icon"></i>
                <input type="text" id="pvParticipantSearch" class="form-control form-control-sm pv-participants-search-input" placeholder="Rechercher…">
                <button type="button" id="pvParticipantSearchClear" class="btn btn-sm position-absolute align-items-center justify-content-center pv-participants-search-clear pv-hidden"><i class="bi bi-x-lg"></i></button>
              </div>
              <div class="zs-select pv-participants-fonction-filter" id="pvParticipantFonctionFilter" data-placeholder="Tous"></div>
            </div>
            <div id="pvFormParticipantsList" class="p-2 d-flex flex-column gap-1 pv-participants-list"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm pv-btn-accent" id="btnSaveCreatePv">
          <i class="bi bi-arrow-right"></i> Créer et enregistrer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Delete -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content pv-confirm-modal-content">
      <div class="modal-body text-center py-4 px-4">
        <div class="pv-confirm-icon">
          <i class="bi bi-trash3"></i>
        </div>
        <h6 class="fw-bold mb-2">Supprimer ce PV ?</h6>
        <p class="text-muted small mb-0">Cette action est irréversible. Le procès-verbal et toutes ses données seront définitivement supprimés.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm px-4 pv-btn-confirm-del" id="btnConfirmDeletePv">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
document.addEventListener('DOMContentLoaded', function() {
  const refs = {
      success: true,
      modules: <?= json_encode(array_values($pvAdminModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
      etages: <?= json_encode(array_values($pvAdminEtages), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
      fonctions: <?= json_encode(array_values($pvAdminFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
      users: <?= json_encode(array_values($pvAdminUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>
  };
  if (!refs.success) return;

  // Fill selects
  const modules = refs.modules || [];
  const etages = refs.etages || [];
  const fonctions = refs.fonctions || [];

  const filterPlaceholders = {
    pvModuleFilter: 'Choisir le module',
    pvEtageFilter: "Choisir l'étage",
    pvFonctionFilter: 'Choisir la fonction',
  };

  const buildOptions = (items, labelKey = 'nom', valueKey = 'id', addTous = false) => {
    const opts = addTous ? [{ value: '', label: 'Tous' }] : [{ value: '', label: '—' }];
    items.forEach(item => opts.push({ value: item[valueKey], label: item[labelKey] + ' (' + item.code + ')' }));
    return opts;
  };

  const initZs = (id, items, labelKey = 'nom', valueKey = 'id', onSelectCb) => {
    const el = document.getElementById(id);
    if (!el) return;
    const isFilter = !!filterPlaceholders[id];
    const opts = buildOptions(items, labelKey, valueKey, isFilter);
    zerdaSelect.init(el, opts, { onSelect: onSelectCb, value: '', search: false, width: 'auto' });
  };

  initZs('pvModuleFilter', modules, 'nom', 'id', () => {
    const modId = zerdaSelect.getValue('#pvModuleFilter');
    const modEtages = etages.filter(et => et.module_id === modId);
    zerdaSelect.destroy(document.getElementById('pvEtageFilter'));
    initZs('pvEtageFilter', modEtages, 'nom', 'id', () => loadPvList());
    zerdaSelect.destroy(document.getElementById('pvFormEtage'));
    initZs('pvFormEtage', modEtages);
    loadPvList();
  });
  initZs('pvEtageFilter', etages, 'nom', 'id', () => loadPvList());
  initZs('pvFonctionFilter', fonctions, 'nom', 'id', () => loadPvList());
  initZs('pvFormModule', modules);
  initZs('pvFormFonction', fonctions);
  initZs('pvFormEtage', etages);

  // Build fonction map for display
  const fonctionMap = {};
  (refs.fonctions || []).forEach(f => { fonctionMap[f.id] = f.nom || f.code; });

  // Participants card list
  const _pvUsers = refs.users || [];
  const _pvFonctions = refs.fonctions || [];
  const _pvSelectedParticipants = new Set();

  // Populate fonction dropdown
  const pvFonctionSel = document.getElementById('pvParticipantFonctionFilter');
  const fonctionOpts = [{ value: '', label: 'Tous' }].concat(_pvFonctions.map(f => ({ value: f.nom, label: f.nom })));
  zerdaSelect.init(pvFonctionSel, fonctionOpts, { onSelect: () => renderParticipants(), value: '', search: false, width: 'auto' });

  function renderParticipants() {
    const list = document.getElementById('pvFormParticipantsList');
    const query = (document.getElementById('pvParticipantSearch').value || '').toLowerCase().trim();
    const fonctionFilter = zerdaSelect.getValue('#pvParticipantFonctionFilter');

    const filtered = _pvUsers.filter(u => {
      const fNom = u.fonction_nom || fonctionMap[u.fonction_id] || '';
      if (fonctionFilter && fNom !== fonctionFilter) return false;
      if (query) {
        const full = `${u.prenom} ${u.nom} ${fNom}`.toLowerCase();
        if (!full.includes(query)) return false;
      }
      return true;
    });

    list.innerHTML = filtered.map(u => {
      const sel = _pvSelectedParticipants.has(u.id);
      const initials = ((u.prenom||'').charAt(0) + (u.nom||'').charAt(0)).toUpperCase();
      const fNom = u.fonction_nom || fonctionMap[u.fonction_id] || '';
      const avatarHtml = u.photo
        ? `<img src="${escapeHtml(u.photo)}" alt="" class="pv-pc-avatar">`
        : `<div class="pv-pc-avatar-placeholder">${initials}</div>`;
      return `<div class="pv-participant-card${sel ? ' selected' : ''}" data-uid="${u.id}">
        ${avatarHtml}
        <div class="pv-pc-info">
          <div class="pv-pc-name">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</div>
          ${fNom ? `<div class="pv-pc-fonction">${escapeHtml(fNom)}</div>` : ''}
        </div>
        <div class="pv-pc-check"><i class="bi bi-check-lg"></i></div>
      </div>`;
    }).join('') || '<div class="text-muted small text-center py-3">Aucun résultat</div>';

    list.querySelectorAll('.pv-participant-card').forEach(card => {
      card.addEventListener('click', () => {
        const uid = card.dataset.uid;
        if (_pvSelectedParticipants.has(uid)) { _pvSelectedParticipants.delete(uid); card.classList.remove('selected'); }
        else { _pvSelectedParticipants.add(uid); card.classList.add('selected'); }
        updateParticipantsCount();
      });
    });
    updateParticipantsCount();
  }

  function updateParticipantsCount() {
    const count = _pvSelectedParticipants.size;
    const badge = document.getElementById('pvParticipantsCount');
    badge.textContent = count;
    badge.classList.toggle('pv-hidden', count === 0);
    badge.classList.toggle('pv-visible-inline', count > 0);
  }

  // Search + filter events
  const pvSearchInput = document.getElementById('pvParticipantSearch');
  const pvSearchClear = document.getElementById('pvParticipantSearchClear');
  pvSearchInput.addEventListener('input', () => {
    pvSearchClear.classList.toggle('pv-hidden', pvSearchInput.value.length === 0);
    pvSearchClear.classList.toggle('pv-visible-flex', pvSearchInput.value.length > 0);
    renderParticipants();
  });
  pvSearchClear.addEventListener('click', () => {
    pvSearchInput.value = '';
    pvSearchClear.classList.add('pv-hidden');
    pvSearchClear.classList.remove('pv-visible-flex');
    renderParticipants();
    pvSearchInput.focus();
  });
  renderParticipants();

  // Archive filter
  let currentArchiveFilter = '0';
  document.querySelectorAll('#pvArchiveFilter .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#pvArchiveFilter .btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentArchiveFilter = btn.dataset.archive;
      loadPvList();
    });
  });

  // Load PV list
  const loadPvList = async (page = 1) => {
    const params = {
      page,
      archived: currentArchiveFilter,
      module_id: zerdaSelect.getValue('#pvModuleFilter'),
      etage_id: zerdaSelect.getValue('#pvEtageFilter'),
      fonction_id: zerdaSelect.getValue('#pvFonctionFilter'),
      search: (document.getElementById('topbarSearchInput') || {}).value || '',
    };
    const result = await adminApiPost('admin_get_pv_list', params);
    if (!result.success) { toast(result.message || 'Erreur de chargement', 'error'); return; }

    const tbody = document.getElementById('pvTableBody');
    tbody.innerHTML = '';
    (result.list || []).forEach(pv => {
      let badgeClass = 'pv-badge-brouillon';
      if (pv.statut === 'finalisé') badgeClass = 'pv-badge-finalise';
      else if (pv.statut === 'en_validation') badgeClass = 'pv-badge-en_validation';
      else if (pv.statut === 'enregistrement') badgeClass = 'pv-badge-enregistrement';

      let statusIcon = '<span class="pv-status-icon si-brouillon me-2"><i class="bi bi-file-earmark-text"></i></span>';
      if (pv.statut === 'finalisé') {
          statusIcon = '<span class="pv-status-icon si-finalise me-2"><i class="bi bi-check-lg"></i></span>';
      } else if (pv.statut === 'en_validation') {
          statusIcon = '<span class="pv-status-icon si-en_validation me-2"><i class="bi bi-hourglass-split"></i></span>';
      } else if (pv.statut === 'enregistrement') {
          statusIcon = '<span class="pv-status-icon si-enregistrement me-2"><i class="bi bi-mic-fill"></i></span>';
      } else if (pv.statut === 'brouillon') {
          statusIcon = '<span class="pv-status-icon si-brouillon me-2"><i class="bi bi-pencil-square"></i></span>';
      }

      const tr = document.createElement('tr');
      tr.className = 'pv-row-clickable';
      tr.innerHTML = `
          <td><div class="d-flex align-items-center gap-2">${statusIcon}<strong>${escapeHtml(pv.titre)}</strong></div></td>
          <td>${pv.prenom || '?'} ${pv.nom || ''}</td>
          <td>${pv.module_code ? pv.module_code : '—'} ${pv.etage_code ? '/ ' + pv.etage_code : ''}</td>
          <td><small>${new Date(pv.created_at).toLocaleDateString('fr-FR')}</small></td>
          <td><span class="badge ${badgeClass}">${pv.statut === 'en_validation' ? 'En validation' : pv.statut}</span></td>
          <td class="pv-col-actions pv-cell-nowrap"></td>
      `;
      tr.addEventListener('click', () => goToPvDetail(pv.id));
      const actionsCell = tr.querySelector('td:last-child');

      if (currentArchiveFilter === '1') {
        // Archived view: show unarchive button
        const unarchBtn = document.createElement('button');
        unarchBtn.className = 'pv-btn-archive me-1';
        unarchBtn.title = 'Désarchiver';
        unarchBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
        unarchBtn.addEventListener('click', (e) => { e.stopPropagation(); unarchivePv(pv.id); });
        actionsCell.appendChild(unarchBtn);
      } else {
        // Active view: show archive button
        const archBtn = document.createElement('button');
        archBtn.className = 'pv-btn-archive me-1';
        archBtn.title = 'Archiver';
        archBtn.innerHTML = '<i class="bi bi-archive"></i>';
        archBtn.addEventListener('click', (e) => { e.stopPropagation(); archivePv(pv.id); });
        actionsCell.appendChild(archBtn);
      }

      const delBtn = document.createElement('button');
      delBtn.className = 'pv-btn-del';
      delBtn.title = 'Supprimer';
      delBtn.innerHTML = '<i class="bi bi-trash3"></i>';
      delBtn.addEventListener('click', (e) => { e.stopPropagation(); showDeleteConfirm(pv.id); });
      actionsCell.appendChild(delBtn);
      tbody.appendChild(tr);
    });

    document.getElementById('pvCount').textContent = result.total;

    // Pagination
    const pag = document.getElementById('pvPagination');
    pag.innerHTML = '';
    for (let p = 1; p <= result.pages; p++) {
      const li = document.createElement('li');
      li.className = 'page-item' + (p === page ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = p;
      a.addEventListener('click', (e) => { e.preventDefault(); loadPvList(p); });
      li.appendChild(a);
      pag.appendChild(li);
    }
  };

  window.goToPvDetail = (pvId) => {
    window.location.href = AdminURL.page('pv-detail', pvId);
  };

  // Delete confirm modal
  let deleteTargetId = null;
  const modalConfirmDelete = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));

  window.showDeleteConfirm = (pvId) => {
    deleteTargetId = pvId;
    modalConfirmDelete.show();
  };

  document.getElementById('btnConfirmDeletePv').addEventListener('click', async () => {
    if (!deleteTargetId) return;
    const btn = document.getElementById('btnConfirmDeletePv');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';
    const r = await adminApiPost('admin_delete_pv', { id: deleteTargetId });
    if (r.success) { toast('PV supprimé'); loadPvList(); }
    else toast(r.message || 'Erreur', 'error');
    btn.disabled = false;
    btn.textContent = 'Supprimer';
    deleteTargetId = null;
    modalConfirmDelete.hide();
  });

  async function archivePv(pvId) {
    const r = await adminApiPost('admin_archive_pv', { id: pvId });
    if (r.success) { toast('PV archivé'); loadPvList(); }
    else toast(r.message || 'Erreur', 'error');
  }

  async function unarchivePv(pvId) {
    const r = await adminApiPost('admin_unarchive_pv', { id: pvId });
    if (r.success) { toast('PV désarchivé'); loadPvList(); }
    else toast(r.message || 'Erreur', 'error');
  }

  window.loadPvList = loadPvList;

  // Filters are handled by zerdaSelect onSelect callbacks above

  // Wire topbar search input for PV filtering (debounced)
  const topbarSearch = document.getElementById('topbarSearchInput');
  let pvSearchTimer = null;
  if (topbarSearch) {
    topbarSearch.addEventListener('input', () => {
      clearTimeout(pvSearchTimer);
      pvSearchTimer = setTimeout(() => loadPvList(), 300);
    });
  }

  document.getElementById('btnFilterClear').addEventListener('click', () => {
    ['pvModuleFilter', 'pvEtageFilter', 'pvFonctionFilter'].forEach(id => {
      zerdaSelect.setValue(document.getElementById(id), '');
    });
    if (topbarSearch) topbarSearch.value = '';
    loadPvList();
  });

  // Create PV dialog
  const modalCreatePv = new bootstrap.Modal(document.getElementById('modalCreatePv'));
  document.getElementById('btnCreatePv').addEventListener('click', () => modalCreatePv.show());

  document.getElementById('btnSaveCreatePv').addEventListener('click', async () => {
    const titre = document.getElementById('pvFormTitre').value.trim();
    if (!titre) { toast('Titre requis', 'error'); return; }

    const participants = [..._pvSelectedParticipants].map(uid => {
      const u = _pvUsers.find(u => u.id === uid);
      return u ? { id: u.id, prenom: u.prenom, nom: u.nom } : null;
    }).filter(Boolean);

    const data = {
      titre,
      description: document.getElementById('pvFormDescription').value,
      module_id: zerdaSelect.getValue('#pvFormModule'),
      etage_id: zerdaSelect.getValue('#pvFormEtage'),
      fonction_id: zerdaSelect.getValue('#pvFormFonction'),
      participants,
    };

    const r = await adminApiPost('admin_create_pv', data);
    if (r.success) {
      toast('PV créé');
      modalCreatePv.hide();
      window.location.href = AdminURL.page('pv-record', r.id);
    } else {
      toast(r.message || 'Erreur', 'error');
    }
  });

  // Initial load
  loadPvList();
});
</script>
