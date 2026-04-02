<?php
// ─── Données serveur (statut en_attente, année courante) ──────────────────
$vacAnnee = (int) date('Y');

$vacancesRaw = Db::fetchAll(
    "SELECT a.id, a.user_id, a.date_debut, a.date_fin, a.statut, a.motif,
            a.valide_par, a.valide_at, a.created_at,
            u.prenom, u.nom, u.taux, u.photo,
            f.code AS fonction_code,
            m.code AS module_code, m.nom AS module_nom,
            v.prenom AS valide_prenom, v.nom AS valide_nom
     FROM absences a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     LEFT JOIN users v ON v.id = a.valide_par
     WHERE a.type = 'vacances' AND a.statut = 'en_attente'
       AND a.date_debut <= ? AND a.date_fin >= ?
     ORDER BY a.date_debut DESC",
    ["{$vacAnnee}-12-31", "{$vacAnnee}-01-01"]
);
// Compute workdays
foreach ($vacancesRaw as &$vac) {
    $workdays = 0;
    $d = new DateTime($vac['date_debut']);
    $e = new DateTime($vac['date_fin']);
    while ($d <= $e) { if ((int)$d->format('N') <= 5) $workdays++; $d->modify('+1 day'); }
    $vac['jours_ouvres'] = $workdays;
}
unset($vac);

$vacModules = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre");
?>
<style>
/* ── Shared action button base ── */
.btn-desir-valider,
.btn-desir-refuser,
.btn-blocked-edit,
.btn-blocked-delete {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }
.btn-blocked-edit { color: var(--cl-text-secondary); border-radius: 8px; }
.btn-blocked-edit:hover { background: var(--cl-accent, #191918); color: #fff; border-color: var(--cl-accent, #191918); }
.btn-blocked-delete { color: #7B3B2C; border-radius: 8px; }
.btn-blocked-delete:hover { background: #7B3B2C; color: #fff; border-color: #7B3B2C; }

/* ── Filter selects ── */
.vac-filter-select { width: auto; }

/* ── Bulk action buttons ── */
.btn-bulk { font-weight: 600; border-radius: 8px; transition: all .2s; }
.btn-bulk-validate { background: #bcd2cb; color: #2d4a43; border: 1px solid #bcd2cb; }
.btn-bulk-refuse { background: #E2B8AE; color: #7B3B2C; border: 1px solid #E2B8AE; }

/* ── Tab content visibility ── */
.vac-tab-hidden { display: none; }

/* ── Grid overflow wrapper ── */
.vac-grid-overflow { overflow-x: auto; }
.tr-grid-wrap { max-height: 500px; }

/* ── Accent button (add blocked, modal submit) ── */
.btn-accent { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: 8px; }

/* ── Modal narrow ── */
.modal-narrow { max-width: 480px; }

/* ── Modal close button ── */
.btn-modal-close {
  width: 32px; height: 32px; border-radius: 50%;
  border: 1px solid var(--cl-border, #e5e7eb);
}
.btn-modal-close i { font-size: .85rem; }

/* ── Avatar (list view) ── */
.vac-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.vac-avatar-initials {
  width: 32px; height: 32px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B;
  display: flex; align-items: center; justify-content: center;
  font-size: .7rem; font-weight: 600; flex-shrink: 0;
}

/* ── Badges ── */
.badge-blue { background: #B8C9D4; color: #3B4F6B; }
.badge-green { background: #bcd2cb; color: #2d4a43; }
.badge-red { background: #E2B8AE; color: #7B3B2C; }
.badge-beige { background: #D4C4A8; color: #6B5B3E; }

/* ── Grid: validated check icon ── */
.vac-check-icon { font-size: 1.1em; vertical-align: middle; color: #2d4a43; }

/* ── Grid: clickable pending cell ── */
.dc-clickable { cursor: pointer; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <h5 class="mb-0"><i class="bi bi-sun"></i> Vacances</h5>
    <div class="zs-select vac-filter-select" id="vacStatutFilter" data-placeholder="Tous les statuts"></div>
    <div class="zs-select vac-filter-select" id="vacModuleFilter" data-placeholder="Tous les modules"></div>
    <div class="d-flex align-items-center gap-1">
      <button class="btn btn-sm btn-outline-secondary" id="vacPrevYear"><i class="bi bi-chevron-left"></i></button>
      <span class="fw-bold" id="vacYear"><?= $vacAnnee ?></span>
      <button class="btn btn-sm btn-outline-secondary" id="vacNextYear"><i class="bi bi-chevron-right"></i></button>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" id="vacFullscreen" title="Plein écran">
      <i class="bi bi-arrows-fullscreen"></i>
    </button>
    <button class="btn btn-sm btn-bulk btn-bulk-validate d-none" id="vacBulkValidate">
      <i class="bi bi-check-all"></i> Valider sélection
    </button>
    <button class="btn btn-sm btn-bulk btn-bulk-refuse d-none" id="vacBulkRefuse">
      <i class="bi bi-x-lg"></i> Refuser sélection
    </button>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="vacTabs">
  <li class="nav-item">
    <a class="nav-link active" href="#" data-tab="list"><i class="bi bi-list-ul"></i> Liste</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="#" data-tab="grid"><i class="bi bi-calendar3"></i> Grille annuelle</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="#" data-tab="blocked"><i class="bi bi-shield-lock"></i> Périodes bloquées</a>
  </li>
</ul>

<!-- TAB: Liste des demandes -->
<div id="vacTabList">
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th><input type="checkbox" id="vacCheckAll"></th>
            <th>Collaborateur</th>
            <th>Module</th>
            <th>Du</th>
            <th>Au</th>
            <th>Jours ouvrés</th>
            <th>Déposée le</th>
            <th>Statut</th>
            <th>Validée par</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="vacTableBody">
          <?php if (empty($vacancesRaw)): ?>
          <tr><td colspan="10" class="text-center py-4 text-muted">Aucune demande en attente</td></tr>
          <?php else: ?>
          <?php foreach ($vacancesRaw as $v):
              $badgeCls = ['valide'=>'badge-green','refuse'=>'badge-red','en_attente'=>'badge-beige'][$v['statut']] ?? 'badge-beige';
              $canAct = $v['statut'] === 'en_attente';
              $validePar = $v['valide_prenom'] ? h($v['valide_prenom'].' '.$v['valide_nom']).'<br><small class="text-muted">'.h($v['valide_at']??'').'</small>' : '—';
              $initials = mb_strtoupper(mb_substr($v['prenom']??'',0,1).mb_substr($v['nom']??'',0,1));
          ?>
          <tr>
            <td><?= $canAct ? '<input type="checkbox" class="vac-check" value="'.h($v['id']).'">' : '' ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($v['photo'])): ?>
                  <img src="<?= h($v['photo']) ?>" class="vac-avatar-img">
                <?php else: ?>
                  <div class="vac-avatar-initials"><?= h($initials) ?></div>
                <?php endif; ?>
                <div>
                  <strong><?= h($v['prenom'].' '.$v['nom']) ?></strong><br>
                  <small class="text-muted"><?= h($v['fonction_code']??'') ?> · <?= (int)$v['taux'] ?>%</small>
                </div>
              </div>
            </td>
            <td><small><?= h($v['module_code']??'—') ?></small></td>
            <td><?= h($v['date_debut']) ?></td>
            <td><?= h($v['date_fin']) ?></td>
            <td class="text-center"><span class="badge badge-blue"><?= (int)$v['jours_ouvres'] ?>j</span></td>
            <td><small><?= h(substr($v['created_at']??'',0,10)) ?></small></td>
            <td><span class="badge <?= $badgeCls ?>"><?= h($v['statut']) ?></span></td>
            <td><small><?= $validePar ?></small></td>
            <td>
              <?php if ($canAct): ?>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-sm btn-desir-valider me-1" data-action="validate-vac" data-id="<?= h($v['id']) ?>" title="Valider"><i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-sm btn-desir-refuser" data-action="refuse-vac" data-id="<?= h($v['id']) ?>" title="Refuser"><i class="bi bi-x-lg"></i></button>
                </div>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- TAB: Grille annuelle -->
<div id="vacTabGrid" class="vac-tab-hidden">
  <div id="vacGridContent" class="vac-grid-overflow"></div>
</div>

<!-- TAB: Périodes bloquées -->
<div id="vacTabBlocked" class="vac-tab-hidden">
  <div class="mb-3">
    <button class="btn btn-sm btn-accent" id="vacAddBlocked"><i class="bi bi-plus-lg"></i> Ajouter une période bloquée</button>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Du</th>
            <th>Au</th>
            <th>Motif</th>
            <th>Créée par</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="blockedTableBody">
          <tr><td colspan="5" class="text-center py-4 text-muted">Chargement...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: ajouter/modifier période bloquée -->
<div class="modal fade" id="blockedModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-narrow">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="confirm-modal-icon icon-primary"><i class="bi bi-shield-lock"></i></div>
          <h6 class="modal-title mb-0" id="blockedModalTitle">Période bloquée</h6>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center btn-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="blockedEditId">
        <div class="mb-3">
          <label class="form-label small fw-bold">Du</label>
          <input type="date" class="form-control form-control-sm" id="blockedDebut">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Au</label>
          <input type="date" class="form-control form-control-sm" id="blockedFin">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Motif</label>
          <input type="text" class="form-control form-control-sm" id="blockedMotif" placeholder="Ex: Fêtes de fin d'année">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-accent" id="blockedSubmit"><i class="bi bi-check-lg"></i> Ajouter</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
const MO = ['Jan','F\u00e9v','Mar','Avr','Mai','Juin','Juil','Ao\u00fb','Sep','Oct','Nov','D\u00e9c'];
const DJ = ['D','L','M','M','J','V','S'];
let vacYear = <?= $vacAnnee ?>;
// Données initiales injectées côté serveur
let vacData = {
    vacances: <?= json_encode(array_values($vacancesRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modules:  <?= json_encode(array_values($vacModules),  JSON_HEX_TAG | JSON_HEX_APOS) ?>
};
let activeTab = 'list';

function renderAvatar(photo, prenom, nom) {
    const initials = ((prenom?.[0] || '') + (nom?.[0] || '')).toUpperCase();
    return photo
        ? `<img src="${escapeHtml(photo)}" class="vac-avatar-img">`
        : `<div class="vac-avatar-initials">${initials}</div>`;
}

function el(id) { return document.getElementById(id); }

function initVacancesPage() {
    // Populate module filter from injected data
    const moduleOpts = [{ value: '', label: 'Tous les modules' }];
    (vacData.modules || []).forEach(m => moduleOpts.push({ value: m.id, label: `${m.code} \u2014 ${m.nom}` }));
    zerdaSelect.init('#vacModuleFilter', moduleOpts, { onSelect: () => loadVacances(), value: '', search: true });

    zerdaSelect.init('#vacStatutFilter', [
        { value: 'en_attente', label: 'En attente' },
        { value: '', label: 'Tous les statuts' },
        { value: 'valide', label: 'Valid\u00e9es' },
        { value: 'refuse', label: 'Refus\u00e9es' }
    ], { onSelect: () => loadVacances(), value: 'en_attente' });

    el('vacPrevYear')?.addEventListener('click', () => { vacYear--; el('vacYear').textContent = vacYear; loadVacances(); });
    el('vacNextYear')?.addEventListener('click', () => { vacYear++; el('vacYear').textContent = vacYear; loadVacances(); });

    el('vacCheckAll')?.addEventListener('change', e => {
        document.querySelectorAll('.vac-check').forEach(c => c.checked = e.target.checked);
        updateBulkBtns();
    });
    el('vacBulkValidate')?.addEventListener('click', () => bulkAction('valide'));
    el('vacBulkRefuse')?.addEventListener('click', () => bulkAction('refuse'));

    el('vacTabGrid')?.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-valid-vac]');
        if (cell) validVacance(cell.dataset.validVac, 'valide');
    });

    el('vacFullscreen')?.addEventListener('click', () => {
        const on = !document.body.classList.contains('ss-immersive');
        document.body.classList.toggle('ss-immersive', on);
        localStorage.setItem('ss_immersive', on ? '1' : '0');
        const icon = document.querySelector('#vacFullscreen i');
        if (icon) icon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        // Also update topbar button
        const tbIcon = document.querySelector('#immersiveToggle i');
        if (tbIcon) tbIcon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    });

    el('vacAddBlocked')?.addEventListener('click', () => {
        el('blockedEditId').value = '';
        el('blockedDebut').value = '';
        el('blockedFin').value = '';
        el('blockedMotif').value = '';
        el('blockedModalTitle').innerHTML = '<i class="bi bi-shield-lock"></i> Nouvelle p\u00e9riode bloqu\u00e9e';
        el('blockedSubmit').innerHTML = '<i class="bi bi-check-lg"></i> Ajouter';
        bootstrap.Modal.getOrCreateInstance(el('blockedModal')).show();
    });
    el('blockedSubmit')?.addEventListener('click', submitBlocked);

    el('blockedTableBody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const { action, id } = btn.dataset;
        if (action === 'edit-blocked') {
            el('blockedEditId').value = id;
            el('blockedDebut').value = btn.dataset.debut;
            el('blockedFin').value = btn.dataset.fin;
            el('blockedMotif').value = btn.dataset.motif;
            el('blockedModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Modifier la p\u00e9riode';
            el('blockedSubmit').innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
            bootstrap.Modal.getOrCreateInstance(el('blockedModal')).show();
        } else if (action === 'delete-blocked') {
            if (!await adminConfirm({ title: 'Supprimer la p\u00e9riode', text: 'Supprimer cette p\u00e9riode bloqu\u00e9e ? Cette action est irr\u00e9versible.', icon: 'bi-calendar-x', type: 'danger', okText: 'Supprimer' })) return;
            btn.disabled = true;
            const res = await adminApiPost('admin_delete_periode_bloquee', { id });
            if (res.success) { showToast('P\u00e9riode supprim\u00e9e', 'success'); loadBlocked(); }
            else { showToast(res.message || 'Erreur', 'error'); btn.disabled = false; }
        }
    });

    el('vacTableBody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        if (btn.dataset.action === 'validate-vac') await validVacance(id, 'valide');
        else if (btn.dataset.action === 'refuse-vac') await validVacance(id, 'refuse');
    });

    // Bind checkboxes on SSR-rendered rows
    document.querySelectorAll('.vac-check').forEach(c => c.addEventListener('change', updateBulkBtns));
    updateBulkBtns();

    // Tab switching
    document.querySelectorAll('#vacTabs a[data-tab]').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            document.querySelectorAll('#vacTabs a').forEach(t => t.classList.remove('active'));
            a.classList.add('active');
            activeTab = a.dataset.tab;
            el('vacTabList').classList.toggle('vac-tab-hidden', activeTab !== 'list');
            el('vacTabGrid').classList.toggle('vac-tab-hidden', activeTab !== 'grid');
            el('vacTabBlocked').classList.toggle('vac-tab-hidden', activeTab !== 'blocked');
            if (activeTab === 'grid') renderGrid();
            if (activeTab === 'blocked') loadBlocked();
        });
    });
}

async function loadVacances() {
    const statut = zerdaSelect.getValue('#vacStatutFilter') || '';
    const moduleId = zerdaSelect.getValue('#vacModuleFilter') || '';

    const res = await adminApiPost('admin_get_vacances', { statut, annee: vacYear, module_id: moduleId });
    vacData = res;
    const vacances = res.vacances || [];

    if (res.modules) {
        const prev = zerdaSelect.getValue('#vacModuleFilter') || '';
        const moduleOpts = [{ value: '', label: 'Tous les modules' }];
        res.modules.forEach(m => moduleOpts.push({ value: m.id, label: `${m.code} \u2014 ${m.nom}` }));
        zerdaSelect.init('#vacModuleFilter', moduleOpts, { onSelect: () => loadVacances(), value: prev, search: true });
    }

    renderList(vacances);
    if (activeTab === 'grid') renderGrid();
}

function renderList(vacances) {
    const tbody = el('vacTableBody');
    if (!vacances.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Aucune demande</td></tr>';
        updateBulkBtns();
        return;
    }

    const statusBadgeCls = { valide: 'badge-green', refuse: 'badge-red', en_attente: 'badge-beige' };
    tbody.innerHTML = vacances.map(v => {
        const badgeCls = statusBadgeCls[v.statut] || statusBadgeCls.en_attente;
        const canAct = v.statut === 'en_attente';
        const validePar = v.valide_prenom ? `${escapeHtml(v.valide_prenom)} ${escapeHtml(v.valide_nom)}<br><small class="text-muted">${escapeHtml(v.valide_at || '')}</small>` : '\u2014';
        return `<tr>
          <td>${canAct ? `<input type="checkbox" class="vac-check" value="${v.id}">` : ''}</td>
          <td><div class="d-flex align-items-center gap-2">${renderAvatar(v.photo, v.prenom, v.nom)}<div><strong>${escapeHtml(v.prenom)} ${escapeHtml(v.nom)}</strong><br><small class="text-muted">${escapeHtml(v.fonction_code || '')} \u00b7 ${Math.round(v.taux)}%</small></div></div></td>
          <td><small>${escapeHtml(v.module_code || '\u2014')}</small></td>
          <td>${escapeHtml(v.date_debut)}</td>
          <td>${escapeHtml(v.date_fin)}</td>
          <td class="text-center"><span class="badge badge-blue">${v.jours_ouvres}j</span></td>
          <td><small>${escapeHtml((v.created_at || '').substring(0, 10))}</small></td>
          <td><span class="badge ${badgeCls}">${escapeHtml(v.statut)}</span></td>
          <td><small>${validePar}</small></td>
          <td>
            ${canAct ? `
              <div class="btn-group btn-group-sm">
                <button class="btn btn-sm btn-desir-valider me-1" data-action="validate-vac" data-id="${v.id}" title="Valider"><i class="bi bi-check-lg"></i></button>
                <button class="btn btn-sm btn-desir-refuser" data-action="refuse-vac" data-id="${v.id}" title="Refuser"><i class="bi bi-x-lg"></i></button>
              </div>
            ` : '\u2014'}
          </td>
        </tr>`;
    }).join('');

    document.querySelectorAll('.vac-check').forEach(c => c.addEventListener('change', updateBulkBtns));
    updateBulkBtns();
}

function updateBulkBtns() {
    const hasChecked = document.querySelectorAll('.vac-check:checked').length > 0;
    el('vacBulkValidate').classList.toggle('d-none', !hasChecked);
    el('vacBulkRefuse').classList.toggle('d-none', !hasChecked);
}

async function validVacance(id, statut) {
    const label = statut === 'valide' ? 'Valider' : 'Refuser';
    if (!await adminConfirm({ title: `${label} la demande`, text: `${label} cette demande de vacances ?`, icon: statut === 'valide' ? 'bi-check-circle' : 'bi-x-circle', type: statut === 'valide' ? 'success' : 'danger', okText: label })) return;
    const res = await adminApiPost('admin_validate_vacances', { id, statut });
    if (res.success) { showToast(res.message, 'success'); location.reload(); }
    else showToast(res.message || 'Erreur', 'error');
}

async function bulkAction(statut) {
    const ids = [...document.querySelectorAll('.vac-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const label = statut === 'valide' ? 'Valider' : 'Refuser';
    if (!await adminConfirm({ title: `${label} en masse`, text: `${label} <strong>${ids.length}</strong> demande(s) de vacances ?`, icon: statut === 'valide' ? 'bi-check2-all' : 'bi-x-circle', type: statut === 'valide' ? 'success' : 'danger', okText: `${label} (${ids.length})` })) return;
    const res = await adminApiPost('admin_bulk_validate_vacances', { ids, statut });
    if (res.success) { showToast(res.message, 'success'); location.reload(); }
    else showToast(res.message || 'Erreur', 'error');
}

// ═══ GRILLE ANNUELLE ═══
function renderGrid() {
    const container = el('vacGridContent');
    adminApiPost('admin_get_vacances', { annee: vacYear, statut: '' }).then(res => {
        const allVac = res.vacances || [];
        const vacMap = {};
        allVac.forEach(v => { if (!vacMap[v.user_id]) vacMap[v.user_id] = []; vacMap[v.user_id].push(v); });
        const userMap = {};
        allVac.forEach(v => { if (!userMap[v.user_id]) userMap[v.user_id] = { id: v.user_id, prenom: v.prenom, nom: v.nom, fn: v.fonction_code, mod: v.module_code || 'AUTRE', modNom: v.module_nom || 'Autre' }; });
        const users = Object.values(userMap);
        const groups = {};
        users.forEach(u => { if (!groups[u.mod]) groups[u.mod] = { label: u.modNom, list: [] }; groups[u.mod].list.push(u); });
        adminApiPost('admin_get_periodes_bloquees', { annee: vacYear }).then(bres => renderGridHTML(container, groups, vacMap, bres.periodes || []));
    });
}

function renderGridHTML(container, groups, vacMap, blocked) {
    const todayStr = new Date().toISOString().slice(0, 10);
    let h = '';
    for (let m = 0; m < 12; m++) {
        const dim = new Date(vacYear, m + 1, 0).getDate();
        const days = [];
        for (let d = 1; d <= dim; d++) days.push(new Date(vacYear, m, d));
        h += `<div class="mb-3"><h6 class="fw-bold">${MO[m]} ${vacYear}</h6>`;
        h += '<div class="tr-grid-wrap"><table class="tr-grid"><thead><tr><th class="col-user">Collaborateur</th>';
        days.forEach(d => {
            const dow = d.getDay(), we = dow === 0 || dow === 6;
            h += `<th class="${we ? 'th-we' : ''}">${DJ[dow]}<br>${d.getDate()}</th>`;
        });
        h += '</tr></thead><tbody>';
        Object.entries(groups).forEach(([mod, grp]) => {
            h += `<tr class="mod-sep"><td colspan="${dim + 1}">${escapeHtml(grp.label)} <span class="badge badge-beige ms-2">${grp.list.length} emp.</span></td></tr>`;
            grp.list.forEach(u => {
                const uVacs = vacMap[u.id] || [];
                h += `<tr><td class="col-user"><span class="fn-badge">${escapeHtml(u.fn || '?')}</span> ${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</td>`;
                days.forEach(d => {
                    const ds = iso(d), dow = d.getDay(), we = dow === 0 || dow === 6;
                    const td = ds === todayStr;
                    const bl = blocked.some(b => ds >= b.date_debut && ds <= b.date_fin);
                    const vac = uVacs.find(v => ds >= v.date_debut && ds <= v.date_fin);
                    let cls = 'dc';
                    if (we) cls += ' td-we';
                    if (td) cls += ' td-today';
                    if (bl) cls += ' dc-bl';
                    if (vac) { if (vac.statut === 'valide') cls += ' dc-vv'; else if (vac.statut === 'en_attente') cls += ' dc-va'; else cls += ' dc-vr'; }
                    let title = '';
                    if (vac) title = `${vac.statut} \u00b7 ${vac.date_debut} \u2192 ${vac.date_fin}`;
                    if (bl) title += (title ? ' | ' : '') + 'Bloqu\u00e9';
                    if (vac && vac.statut === 'en_attente') cls += ' dc-clickable';
                    const dataAttr = vac && vac.statut === 'en_attente' ? ` data-valid-vac="${vac.id}" title="Cliquer pour valider"` : '';
                    const icon = (!we && cls.includes('dc-vv')) ? '<i class="bi bi-check-lg vac-check-icon"></i>' : '';
                    h += `<td class="${cls}" title="${escapeHtml(title)}"${dataAttr}>${icon}</td>`;
                });
                h += '</tr>';
            });
        });
        h += '</tbody></table></div></div>';
    }
    if (!Object.keys(groups).length) h = '<p class="text-muted p-3">Aucune demande de vacances cette ann\u00e9e</p>';
    container.innerHTML = h;
}

function iso(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }

// ═══ PERIODES BLOQUEES ═══
async function loadBlocked() {
    const tbody = el('blockedTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</td></tr>';
    let res;
    try { res = await adminApiPost('admin_get_periodes_bloquees', { annee: vacYear }); }
    catch(e) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Erreur de chargement</td></tr>'; return; }
    const periodes = res.periodes || [];
    if (!periodes.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Aucune p\u00e9riode bloqu\u00e9e</td></tr>'; return; }
    tbody.innerHTML = periodes.map(p => `<tr>
      <td>${escapeHtml(p.date_debut)}</td>
      <td>${escapeHtml(p.date_fin)}</td>
      <td>${escapeHtml(p.motif || '\u2014')}</td>
      <td><small>${escapeHtml((p.created_by_prenom || '') + ' ' + (p.created_by_nom || ''))}</small></td>
      <td>
        <button class="btn btn-sm btn-blocked-edit me-1" data-action="edit-blocked" data-id="${p.id}" data-debut="${escapeHtml(p.date_debut)}" data-fin="${escapeHtml(p.date_fin)}" data-motif="${escapeHtml(p.motif || '')}" title="Modifier"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-blocked-delete" data-action="delete-blocked" data-id="${p.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
}

async function submitBlocked() {
    const editId = el('blockedEditId')?.value;
    const debut = el('blockedDebut')?.value;
    const fin = el('blockedFin')?.value;
    const motif = el('blockedMotif')?.value;
    if (!debut || !fin) { showToast('Dates requises', 'error'); return; }
    const btn = el('blockedSubmit');
    btn.disabled = true;
    try {
        let res;
        if (editId) res = await adminApiPost('admin_update_periode_bloquee', { id: editId, date_debut: debut, date_fin: fin, motif });
        else res = await adminApiPost('admin_add_periode_bloquee', { date_debut: debut, date_fin: fin, motif });
        if (res.success) {
            bootstrap.Modal.getOrCreateInstance(el('blockedModal'))?.hide();
            showToast(res.message, 'success');
            await loadBlocked();
        } else showToast(res.message || 'Erreur', 'error');
    } catch (err) { console.error('submitBlocked error:', err); showToast('Erreur', 'error'); }
    finally { btn.disabled = false; }
}

window.initVacancesPage = initVacancesPage;
</script>
