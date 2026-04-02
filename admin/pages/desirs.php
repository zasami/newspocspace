<?php
// ─── Données serveur (mois suivant, statut en_attente) ────────────────────
$nextMonth = date('Y-m', strtotime('+1 month'));

$initDesirs = Db::fetchAll(
    "SELECT d.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
            ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
            ht.heure_debut AS horaire_debut, ht.heure_fin AS horaire_fin,
            ht.duree_effective AS horaire_duree,
            dp.jour_semaine AS permanent_jour_semaine
     FROM desirs d
     JOIN users u ON u.id = d.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN horaires_types ht ON ht.id = d.horaire_type_id
     LEFT JOIN desirs_permanents dp ON dp.id = d.permanent_id
     WHERE d.statut = 'en_attente' AND d.mois_cible = ?
     ORDER BY d.date_souhaitee ASC, u.nom ASC",
    [$nextMonth]
);

$initPermanents = Db::fetchAll(
    "SELECT dp.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
            ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
            dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
            ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
     FROM desirs_permanents dp
     JOIN users u ON u.id = dp.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
     LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
     LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
     WHERE dp.statut = 'en_attente'
     ORDER BY dp.created_at ASC"
);
?>
<style>
/* ── Action buttons ── */
.btn-desir-valider {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }

/* ── Badges ── */
.badge-permanent { background: rgba(25,25,24,0.1); color: var(--cl-accent); font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(25,25,24,0.25); border-radius: 20px; }
.badge-modif { background: #D4C4A8; color: #6B5B3E; font-size: 0.72rem; font-weight: 600; border: 1px solid #D4C4A8; border-radius: 20px; }
.badge-ss-valid { background: #bcd2cb; color: #2d4a43; }
.badge-ss-refuse { background: #E2B8AE; color: #7B3B2C; }
.badge-ss-attente { background: #D4C4A8; color: #6B5B3E; }
.badge-ss-jour-off { background: #B8C9D4; color: #3B4F6B; }
.badge-ss-horaire-special { background: #D0C4D8; color: #5B4B6B; }
.badge-ss-horaire-dynamic { color: #fff; font-size: 0.82rem; letter-spacing: 0.5px; }
.badge-ss-status-lg { font-size: 0.85rem; }

/* ── Perm comparison ── */
.perm-ancien { background: #f8f9fa; border-radius: 6px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border-left: 3px solid #7B3B2C; margin-bottom: 0.3rem; }
.perm-nouveau { background: #f0fff4; border-radius: 6px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border-left: 3px solid #2d4a43; margin-bottom: 0.3rem; }

/* ── Filter controls ── */
.desir-filter-select { width: auto; }
.desir-count { font-size: 0.82rem; }

/* ── Table column widths ── */
.desir-th-collab { min-width: 180px; }
.desir-th-actions { width: 90px; }

/* ── Avatar ── */
.desir-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.desir-avatar-initials { width: 32px; height: 32px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 600; flex-shrink: 0; }

/* ── Clickable rows ── */
.desir-click { cursor: pointer; }

/* ── Modal header states ── */
.desir-header-attente { background: #fff8e1; border-bottom: 2px solid #ffc107; }
.desir-header-valide { background: #e8f5e9; border-bottom: 2px solid #4caf50; }
.desir-header-refuse { background: #ffebee; border-bottom: 2px solid #f44336; }

/* ── Modal body blocks ── */
.desir-perm-block { background: rgba(255,193,7,0.08); border: 1px dashed rgba(255,193,7,0.4); }
.desir-perm-icon { color: #d4a017; font-size: 1.1rem; }
.desir-perm-label { font-size: 0.85rem; }
.desir-detail-block { background: #f8f9fa; }
.desir-detail-text { font-size: 0.9rem; }
.desir-comment-block { background: #f8f9fa; }
.desir-horaire-block { background: #f8f9fa; }

/* ── Button size for modal footer ── */
.desir-btn { font-size: 0.9rem; }

/* ── Section header ── */
.desir-section-icon { color: #6B5B3E; }
.desir-section-badge { background: #D4C4A8; color: #6B5B3E; }

/* ── Hidden toggle ── */
.desir-hidden { display: none; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2">
    <div class="zs-select desir-filter-select" id="desirStatutFilter" data-placeholder="Tous les statuts"></div>
    <input type="month" class="form-control form-control-sm desir-filter-select" id="desirMoisFilter" value="<?= h($nextMonth) ?>">
  </div>
  <span id="desirsCount" class="text-muted desir-count"><?= count($initDesirs) ? count($initDesirs).' désir(s)' : '' ?></span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 desirs-main-table" id="desirsTable">
      <thead>
        <tr>
          <th class="desir-th-collab">Collaborateur</th>
          <th>Date souhaitée</th>
          <th>Type</th>
          <th>Horaire</th>
          <th>Détail</th>
          <th>Statut</th>
          <th class="desir-th-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="desirsTableBody">
        <?php if (empty($initDesirs)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">Aucun désir pour cette période</td></tr>
        <?php else: ?>
        <?php foreach ($initDesirs as $idx => $d):
            $statusCls = $d['statut'] === 'valide' ? 'ss-valid' : ($d['statut'] === 'refuse' ? 'ss-refuse' : 'ss-attente');
            $statusLabel = $d['statut'] === 'valide' ? 'Validé' : ($d['statut'] === 'refuse' ? 'Refusé' : 'En attente');
            $isPermanent = !empty($d['permanent_id']);
            $initials = mb_strtoupper(mb_substr($d['prenom']??'',0,1).mb_substr($d['nom']??'',0,1));
        ?>
        <tr class="desir-row" data-idx="<?= $idx ?>">
          <td class="desir-click desir-collab">
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($d['photo'])): ?>
                <img src="<?= h($d['photo']) ?>" class="desir-avatar">
              <?php else: ?>
                <div class="desir-avatar-initials"><?= h($initials) ?></div>
              <?php endif; ?>
              <div>
                <strong><?= h($d['prenom'].' '.$d['nom']) ?></strong><br>
                <small class="text-muted"><?= h($d['fonction_code']??'') ?></small>
              </div>
            </div>
          </td>
          <td class="desir-click"><?= h($d['date_souhaitee']) ?></td>
          <td class="desir-click">
            <?= $d['type'] === 'jour_off' ? '<span class="badge badge-ss-jour-off">Jour off</span>' : '<span class="badge badge-ss-horaire-special">Horaire spécial</span>' ?>
            <?php if ($isPermanent): ?><span class="badge badge-permanent" title="Désir permanent"><i class="bi bi-pin-angle-fill"></i> Permanent</span><?php endif; ?>
          </td>
          <td class="desir-click">
            <?php if ($d['type'] === 'horaire_special' && !empty($d['horaire_code'])): ?>
              <span class="badge badge-ss-horaire-dynamic" style="background:<?= h($d['horaire_couleur']??'#9B51E0') ?>"><?= h($d['horaire_code']) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="desir-click"><?= !empty($d['detail']) ? '<small>'.h($d['detail']).'</small>' : '' ?></td>
          <td class="desir-click"><span class="badge badge-<?= $statusCls ?>"><?= h($statusLabel) ?></span></td>
          <td class="text-nowrap">
            <?php if ($d['statut'] === 'en_attente'): ?>
              <button class="btn btn-sm btn-desir-valider me-1" data-action="valide" data-id="<?= h($d['id']) ?>" title="Valider"><i class="bi bi-check-lg"></i></button>
              <button class="btn btn-sm btn-desir-refuser" data-action="refuse" data-id="<?= h($d['id']) ?>" title="Refuser"><i class="bi bi-x-lg"></i></button>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Permanents en attente -->
<div id="permanentsPendingSection" class="mt-4<?= empty($initPermanents) ? ' desir-hidden' : '' ?>">
  <h6 class="fw-600 mb-2"><i class="bi bi-pin-angle-fill desir-section-icon"></i> Désirs permanents en attente <span id="permanentsPendingCount" class="badge ms-1 desir-section-badge"><?= count($initPermanents) ?></span></h6>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Collaborateur</th>
            <th>Type</th>
            <th>Jour</th>
            <th>Horaire</th>
            <th>Modification</th>
            <th class="desir-th-actions">Actions</th>
          </tr>
        </thead>
        <tbody id="permanentsPendingBody">
          <?php
          $joursSemainePhp = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
          foreach ($initPermanents as $p):
              $jour = $joursSemainePhp[$p['jour_semaine']] ?? '?';
              $isModification = !empty($p['replaces_id']);
              $pInitials = mb_strtoupper(mb_substr($p['prenom']??'',0,1).mb_substr($p['nom']??'',0,1));
          ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($p['photo'])): ?>
                  <img src="<?= h($p['photo']) ?>" class="desir-avatar">
                <?php else: ?>
                  <div class="desir-avatar-initials"><?= h($pInitials) ?></div>
                <?php endif; ?>
                <div>
                  <strong><?= h($p['prenom'].' '.$p['nom']) ?></strong><br>
                  <small class="text-muted"><?= h($p['fonction_code']??'') ?></small>
                </div>
              </div>
            </td>
            <td><?= $p['type'] === 'jour_off' ? '<span class="badge badge-ss-jour-off">Jour off</span>' : '<span class="badge badge-ss-horaire-special">Horaire spécial</span>' ?></td>
            <td><strong><?= h($jour) ?></strong></td>
            <td>
              <?php if (!empty($p['horaire_code'])): ?>
                <span class="badge badge-ss-horaire-dynamic" style="background:<?= h($p['horaire_couleur']??'#9B51E0') ?>"><?= h($p['horaire_code']) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td>
              <?php if ($isModification):
                  $ancienJour = $joursSemainePhp[$p['ancien_jour_semaine']] ?? '?';
                  $ancienType = $p['ancien_type'] === 'jour_off' ? 'Jour off' : 'Horaire';
                  $ancienHoraire = !empty($p['ancien_horaire_code']) ? ' ('.h($p['ancien_horaire_code']).')' : '';
                  $nouveauHoraire = !empty($p['horaire_code']) ? ' ('.h($p['horaire_code']).')' : '';
              ?>
                <div class="perm-ancien"><small class="text-muted">Avant :</small> <?= h($ancienJour) ?> — <?= $ancienType.$ancienHoraire ?></div>
                <div class="perm-nouveau"><small class="text-muted">Après :</small> <?= h($jour) ?> — <?= ($p['type']==='jour_off'?'Jour off':'Horaire').$nouveauHoraire ?></div>
              <?php else: ?>
                <span class="badge badge-permanent">Nouveau</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <button class="btn btn-sm btn-desir-valider me-1" data-perm-action="valide" data-perm-id="<?= h($p['id']) ?>" title="Valider"><i class="bi bi-check-lg"></i></button>
              <button class="btn btn-sm btn-desir-refuser" data-perm-action="refuse" data-perm-id="<?= h($p['id']) ?>" title="Refuser"><i class="bi bi-x-lg"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal détail désir -->
<div class="modal fade" id="desirModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-warning">
    <div class="modal-content">
      <div class="modal-header" id="desirModalHeader">
        <div>
          <h6 class="modal-title mb-0" id="desirModalTitle"></h6>
          <small class="text-muted" id="desirModalSubtitle"></small>
        </div>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="desirModalBody"></div>
      <div class="modal-footer d-flex" id="desirModalFooter">
        <button type="button" class="btn btn-outline-secondary px-3 py-1 desir-btn" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    // Données initiales injectées côté serveur
    let desirsData = <?= json_encode(array_values($initDesirs), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let permsData  = <?= json_encode(array_values($initPermanents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let modalInstance = null;

    const joursSemaine = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

    function initDesirsPage() {
        zerdaSelect.init('#desirStatutFilter', [
            { value: '', label: 'Tous les statuts' },
            { value: 'en_attente', label: 'En attente' },
            { value: 'valide', label: 'Valid\u00e9' },
            { value: 'refuse', label: 'Refus\u00e9' }
        ], { onSelect: () => loadDesirs(), value: 'en_attente' });

        document.getElementById('desirMoisFilter')?.addEventListener('change', loadDesirs);
        modalInstance = new bootstrap.Modal(document.getElementById('desirModal'));

        // Event delegation — table rows (click cells)
        document.getElementById('desirsTableBody').addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (btn) {
                e.stopPropagation();
                const id = btn.dataset.id;
                if (btn.dataset.action === 'valide') { doValidate(id, 'valide'); return; }
                const tr = btn.closest('.desir-row');
                const idx = parseInt(tr?.dataset.idx);
                if (!isNaN(idx)) openDesirModal(idx, true);
                return;
            }
            const td = e.target.closest('.desir-click');
            if (td) {
                const tr = td.closest('.desir-row');
                const idx = parseInt(tr?.dataset.idx);
                if (!isNaN(idx)) openDesirModal(idx);
            }
        });

        // Event delegation — permanents table
        document.getElementById('permanentsPendingBody').addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-perm-action]');
            if (!btn) return;
            e.stopPropagation();
            const id = btn.dataset.permId;
            const action = btn.dataset.permAction;
            if (action === 'valide') {
                await doValidatePermanent(id, 'valide');
            } else {
                const commentaire = prompt('Motif du refus (optionnel) :') || '';
                await doValidatePermanent(id, 'refuse', commentaire);
            }
        });
        // Initial render already done server-side
    }

    function formatDateFr(dateStr) {
        if (!dateStr) return '\u2014';
        const d = new Date(dateStr + 'T00:00:00');
        const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        const mois = ['jan', 'f\u00e9v', 'mar', 'avr', 'mai', 'jun', 'jul', 'ao\u00fb', 'sep', 'oct', 'nov', 'd\u00e9c'];
        return `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]} ${d.getFullYear()}`;
    }

    async function loadDesirs() {
        const statut = zerdaSelect.getValue('#desirStatutFilter') || '';
        const mois = document.getElementById('desirMoisFilter')?.value || '';

        const res = await adminApiPost('admin_get_desirs', { statut, mois });
        desirsData = res.desirs || [];
        const tbody = document.getElementById('desirsTableBody');
        const countEl = document.getElementById('desirsCount');

        countEl.textContent = desirsData.length ? `${desirsData.length} d\u00e9sir(s)` : '';

        if (!desirsData.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Aucun d\u00e9sir pour cette p\u00e9riode</td></tr>';
            return;
        }

        tbody.innerHTML = desirsData.map((d, idx) => {
            const statusCls = d.statut === 'valide' ? 'ss-valid' : d.statut === 'refuse' ? 'ss-refuse' : 'ss-attente';
            const statusLabel = d.statut === 'valide' ? 'Valid\u00e9' : d.statut === 'refuse' ? 'Refus\u00e9' : 'En attente';
            const isPermanent = !!d.permanent_id;
            const initials = ((d.prenom?.[0] || '') + (d.nom?.[0] || '')).toUpperCase();
            const avatar = d.photo
                ? `<img src="${escapeHtml(d.photo)}" class="desir-avatar">`
                : `<div class="desir-avatar-initials">${initials}</div>`;

            const typeBadge = d.type === 'jour_off'
                ? '<span class="badge badge-ss-jour-off">Jour off</span>'
                : '<span class="badge badge-ss-horaire-special">Horaire sp\u00e9cial</span>';

            let horaireCell = '<span class="text-muted">\u2014</span>';
            if (d.type === 'horaire_special' && d.horaire_code) {
                const couleur = d.horaire_couleur || '#9B51E0';
                horaireCell = `<span class="badge badge-ss-horaire-dynamic" style="background:${escapeHtml(couleur)}">${escapeHtml(d.horaire_code)}</span>`;
            }

            const detailCell = d.detail ? `<small>${escapeHtml(d.detail)}</small>` : '';
            const dateFmt = formatDateFr(d.date_souhaitee);
            const permIcon = isPermanent ? ' <span class="badge badge-permanent" title="D\u00e9sir permanent"><i class="bi bi-pin-angle-fill"></i> Permanent</span>' : '';

            let actionBtns = '';
            if (d.statut === 'en_attente') {
                actionBtns = `
                  <button class="btn btn-sm btn-desir-valider me-1" data-action="valide" data-id="${d.id}" title="Valider"><i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-sm btn-desir-refuser" data-action="refuse" data-id="${d.id}" title="Refuser"><i class="bi bi-x-lg"></i></button>`;
            } else {
                actionBtns = '<span class="text-muted">\u2014</span>';
            }

            return `<tr class="desir-row" data-idx="${idx}">
              <td class="desir-click desir-collab"><div class="d-flex align-items-center gap-2">${avatar}<div><strong>${escapeHtml(d.prenom)} ${escapeHtml(d.nom)}</strong><br><small class="text-muted">${escapeHtml(d.fonction_code || '')}</small></div></div></td>
              <td class="desir-click">${dateFmt}</td>
              <td class="desir-click">${typeBadge}${permIcon}</td>
              <td class="desir-click">${horaireCell}</td>
              <td class="desir-click">${detailCell}</td>
              <td class="desir-click"><span class="badge badge-${statusCls}">${statusLabel}</span></td>
              <td class="text-nowrap">${actionBtns}</td>
            </tr>`;
        }).join('');
    }

    function openDesirModal(idx, startRefus) {
        const d = desirsData[idx];
        if (!d) return;

        const statusCls = d.statut === 'valide' ? 'ss-valid' : d.statut === 'refuse' ? 'ss-refuse' : 'ss-attente';
        const statusLabel = d.statut === 'valide' ? 'Valid\u00e9' : d.statut === 'refuse' ? 'Refus\u00e9' : 'En attente';
        const isPermanent = !!d.permanent_id;

        const permLabel = isPermanent ? ' <span class="badge badge-permanent"><i class="bi bi-pin-angle-fill"></i> Permanent</span>' : '';
        document.getElementById('desirModalTitle').innerHTML = `${escapeHtml(d.prenom)} ${escapeHtml(d.nom)}${permLabel}`;
        document.getElementById('desirModalSubtitle').textContent = `${d.fonction_code || ''} \u2014 D\u00e9sir pour le ${formatDateFr(d.date_souhaitee)}`;

        const header = document.getElementById('desirModalHeader');
        header.className = 'modal-header';
        header.classList.add(d.statut === 'en_attente' ? 'desir-header-attente' : d.statut === 'valide' ? 'desir-header-valide' : 'desir-header-refuse');

        const typeBadge = d.type === 'jour_off'
            ? '<span class="badge badge-ss-jour-off badge-ss-status-lg"><i class="bi bi-moon"></i> Jour off</span>'
            : '<span class="badge badge-ss-horaire-special badge-ss-status-lg"><i class="bi bi-clock"></i> Horaire sp\u00e9cial</span>';

        let permBlock = '';
        if (isPermanent) {
            const jourLabel = d.permanent_jour_semaine !== undefined ? joursSemaine[d.permanent_jour_semaine] : '';
            permBlock = `
            <div class="d-flex align-items-center gap-2 p-2 rounded mb-3 desir-perm-block">
              <i class="bi bi-pin-angle-fill desir-perm-icon"></i>
              <div>
                <span class="fw-600 desir-perm-label">D\u00e9sir permanent</span>
                ${jourLabel ? `<small class="text-muted d-block">Chaque ${jourLabel} \u2014 valid\u00e9 automatiquement</small>` : ''}
              </div>
            </div>`;
        }

        let horaireBlock = '';
        if (d.type === 'horaire_special') {
            const couleur = d.horaire_couleur || '#9B51E0';
            if (d.horaire_code) {
                horaireBlock = `
                <div class="d-flex align-items-center gap-3 p-3 rounded mb-3 desir-horaire-block" style="border-left:4px solid ${escapeHtml(couleur)}">
                  <span class="fw-bold" style="font-size:1.5rem;color:${escapeHtml(couleur)}">${escapeHtml(d.horaire_code)}</span>
                  <div>
                    <div class="fw-600">${escapeHtml(d.horaire_nom || '')}</div>
                    <small class="text-muted">${escapeHtml(d.horaire_debut || '')} \u2014 ${escapeHtml(d.horaire_fin || '')}</small>
                    ${d.horaire_duree ? `<small class="text-muted ms-2">(${d.horaire_duree}h)</small>` : ''}
                  </div>
                </div>`;
            }
        }

        let detailBlock = '';
        if (d.detail) {
            detailBlock = `
            <div class="p-2 rounded mb-3 desir-detail-block">
              <small class="text-muted"><i class="bi bi-pencil"></i> D\u00e9tail</small>
              <div class="desir-detail-text">${escapeHtml(d.detail)}</div>
            </div>`;
        }

        let statusBlock = `
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="badge bg-${statusCls} badge-ss-status-lg">${statusLabel}</span>
          ${d.valide_at ? `<small class="text-muted">le ${formatDateFr(d.valide_at?.substring(0, 10))}</small>` : ''}
        </div>`;

        let commentBlock = '';
        if (d.commentaire_chef) {
            commentBlock = `
            <div class="p-3 rounded desir-comment-block">
              <small class="text-muted d-block mb-1"><i class="bi bi-chat-dots"></i> Commentaire du responsable</small>
              <span>${escapeHtml(d.commentaire_chef)}</span>
            </div>`;
        }

        let refusCommentHtml = '';
        if (d.statut === 'en_attente') {
            refusCommentHtml = `
            <div id="refusCommentBlock" class="mt-3${startRefus ? '' : ' desir-hidden'}">
              <label class="form-label fw-600"><i class="bi bi-chat-dots"></i> Motif du refus (optionnel)</label>
              <textarea class="form-control" id="refusCommentaire" rows="2" placeholder="Raison du refus..."></textarea>
            </div>`;
        }

        const createdAt = d.created_at ? `<small class="text-muted"><i class="bi bi-clock-history"></i> Soumis le ${formatDateFr(d.created_at?.substring(0, 10))}</small>` : '';

        document.getElementById('desirModalBody').innerHTML = `
            <div class="mb-3 d-flex align-items-center justify-content-between">
              ${typeBadge}
              ${createdAt}
            </div>
            ${permBlock}
            ${horaireBlock}
            ${detailBlock}
            ${statusBlock}
            ${commentBlock}
            ${refusCommentHtml}
        `;

        const footer = document.getElementById('desirModalFooter');
        if (d.statut === 'en_attente') {
            footer.innerHTML = `
              <button type="button" class="btn btn-outline-secondary px-3 py-1 desir-btn" data-bs-dismiss="modal">Fermer</button>
              <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-desir-refuser px-3 py-1 desir-btn" id="btnRefuserDesir"><i class="bi bi-x-circle"></i> Refuser</button>
                <button type="button" class="btn btn-desir-valider px-3 py-1 desir-btn" id="btnValiderDesir"><i class="bi bi-check-circle"></i> Valider</button>
              </div>`;

            footer.querySelector('#btnValiderDesir').addEventListener('click', () => doValidate(d.id, 'valide'));
            footer.querySelector('#btnRefuserDesir').addEventListener('click', () => {
                const block = document.getElementById('refusCommentBlock');
                if (block.classList.contains('desir-hidden')) {
                    block.classList.remove('desir-hidden');
                    document.getElementById('refusCommentaire').focus();
                    footer.querySelector('#btnRefuserDesir').innerHTML = '<i class="bi bi-x-circle"></i> Confirmer le refus';
                    footer.querySelector('#btnRefuserDesir').classList.replace('btn-desir-refuser', 'btn-danger');
                } else {
                    doValidate(d.id, 'refuse', document.getElementById('refusCommentaire').value.trim());
                }
            });

            if (startRefus) setTimeout(() => document.getElementById('refusCommentaire')?.focus(), 300);
        } else {
            footer.innerHTML = `<button type="button" class="btn btn-outline-secondary px-3 py-1 ms-auto desir-btn" data-bs-dismiss="modal">Fermer</button>`;
        }

        modalInstance.show();
    }

    async function doValidate(id, statut, commentaire) {
        commentaire = commentaire || '';
        const res = await adminApiPost('admin_validate_desir', { id, statut, commentaire });
        if (res.success) {
            modalInstance.hide();
            showToast(res.message, 'success');
            location.reload();
        } else { showToast(res.message || 'Erreur', 'error'); }
    }

    async function loadPermanentsPending() {
        const res = await adminApiPost('admin_get_permanents_pending');
        permsData = res.permanents || [];
        const section = document.getElementById('permanentsPendingSection');
        const tbody = document.getElementById('permanentsPendingBody');
        const countEl = document.getElementById('permanentsPendingCount');

        if (!permsData.length) { section.classList.add('desir-hidden'); return; }
        section.classList.remove('desir-hidden');
        countEl.textContent = permsData.length;

        tbody.innerHTML = permsData.map(p => {
            const jour = joursSemaine[p.jour_semaine] || '?';
            const isModification = !!p.replaces_id;
            const typeBadge = p.type === 'jour_off'
                ? '<span class="badge badge-ss-jour-off">Jour off</span>'
                : '<span class="badge badge-ss-horaire-special">Horaire sp\u00e9cial</span>';

            let horaireCell = '<span class="text-muted">\u2014</span>';
            if (p.horaire_code) {
                const c = p.horaire_couleur || '#9B51E0';
                horaireCell = `<span class="badge badge-ss-horaire-dynamic" style="background:${escapeHtml(c)}">${escapeHtml(p.horaire_code)}</span>`;
            }

            let modifCell = '';
            if (isModification) {
                const ancienJour = joursSemaine[p.ancien_jour_semaine] || '?';
                const ancienType = p.ancien_type === 'jour_off' ? 'Jour off' : 'Horaire';
                const ancienHoraire = p.ancien_horaire_code ? ` (${escapeHtml(p.ancien_horaire_code)})` : '';
                modifCell = `
                    <div class="perm-ancien"><small class="text-muted">Avant :</small> ${ancienJour} \u2014 ${ancienType}${ancienHoraire}</div>
                    <div class="perm-nouveau"><small class="text-muted">Apr\u00e8s :</small> ${jour} \u2014 ${p.type === 'jour_off' ? 'Jour off' : 'Horaire'}${p.horaire_code ? ' (' + escapeHtml(p.horaire_code) + ')' : ''}</div>`;
            } else {
                modifCell = '<span class="badge badge-permanent">Nouveau</span>';
            }

            const pInitials = ((p.prenom?.[0] || '') + (p.nom?.[0] || '')).toUpperCase();
            const pAvatar = p.photo
                ? `<img src="${escapeHtml(p.photo)}" class="desir-avatar">`
                : `<div class="desir-avatar-initials">${pInitials}</div>`;

            return `<tr>
              <td><div class="d-flex align-items-center gap-2">${pAvatar}<div><strong>${escapeHtml(p.prenom)} ${escapeHtml(p.nom)}</strong><br><small class="text-muted">${escapeHtml(p.fonction_code || '')}</small></div></div></td>
              <td>${typeBadge}</td>
              <td><strong>${jour}</strong></td>
              <td>${horaireCell}</td>
              <td>${modifCell}</td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-desir-valider me-1" data-perm-action="valide" data-perm-id="${p.id}" title="Valider"><i class="bi bi-check-lg"></i></button>
                <button class="btn btn-sm btn-desir-refuser" data-perm-action="refuse" data-perm-id="${p.id}" title="Refuser"><i class="bi bi-x-lg"></i></button>
              </td>
            </tr>`;
        }).join('');
    }

    async function doValidatePermanent(id, statut, commentaire) {
        commentaire = commentaire || '';
        const res = await adminApiPost('admin_validate_permanent', { id, statut, commentaire });
        if (res.success) {
            showToast(res.message, 'success');
            await loadPermanentsPending();
        } else { showToast(res.message || 'Erreur', 'error'); }
    }

    window.initDesirsPage = initDesirsPage;
})();
</script>
