<?php
// ─── Données serveur (filtre par défaut : confirme_collegue) ──────────────
$initChangements = Db::fetchAll(
    "SELECT ch.*,
            ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom, ud.employee_id AS demandeur_employee_id,
            ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom, ude.employee_id AS destinataire_employee_id,
            fd.code AS demandeur_fonction, fde.code AS destinataire_fonction,
            ht_dem.code AS horaire_demandeur_code, ht_dem.nom AS horaire_demandeur_nom, ht_dem.couleur AS horaire_demandeur_couleur,
            ht_dest.code AS horaire_destinataire_code, ht_dest.nom AS horaire_destinataire_nom, ht_dest.couleur AS horaire_destinataire_couleur,
            m_dem.nom AS module_demandeur_nom, m_dest.nom AS module_destinataire_nom,
            ut.prenom AS traite_par_prenom, ut.nom AS traite_par_nom
     FROM changements_horaire ch
     JOIN users ud ON ud.id = ch.demandeur_id
     JOIN users ude ON ude.id = ch.destinataire_id
     JOIN planning_assignations pa_dem ON pa_dem.id = ch.assignation_demandeur_id
     JOIN planning_assignations pa_dest ON pa_dest.id = ch.assignation_destinataire_id
     LEFT JOIN fonctions fd ON fd.id = ud.fonction_id
     LEFT JOIN fonctions fde ON fde.id = ude.fonction_id
     LEFT JOIN horaires_types ht_dem ON ht_dem.id = pa_dem.horaire_type_id
     LEFT JOIN horaires_types ht_dest ON ht_dest.id = pa_dest.horaire_type_id
     LEFT JOIN modules m_dem ON m_dem.id = pa_dem.module_id
     LEFT JOIN modules m_dest ON m_dest.id = pa_dest.module_id
     LEFT JOIN users ut ON ut.id = ch.traite_par
     WHERE ch.statut = 'confirme_collegue'
     ORDER BY ch.created_at DESC"
);
$initPending = count(array_filter($initChangements, fn($c) => $c['statut'] === 'confirme_collegue'));

// PHP date formatting helpers
$joursFr = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$moisFr = ['jan.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
function fmtChgDate($dateStr, $joursFr, $moisFr) {
    if (!$dateStr) return '—';
    $dt = new DateTime($dateStr);
    return $joursFr[(int)$dt->format('w')] . ' ' . (int)$dt->format('j') . ' ' . $moisFr[(int)$dt->format('n') - 1];
}
?>
<style>
/* Layout utilities */
.chg-w-auto { width: auto; }
.chg-text-center { text-align: center; }

/* Modal detail body */
.chg-modal-body-scroll { max-height: 80vh; overflow-y: auto; }

/* Confirmation modal */
.chg-confirm-content { border-radius: 1.25rem; border: none; overflow: hidden; }
.chg-confirm-title { color: #2d3a34; }
.chg-confirm-footer { border-color: var(--cl-border, #E8E5E0) !important; }
.chg-confirm-ok-btn { background: #bcd2cb; color: #1a2e24; font-weight: 600; }
.chg-confirm-ok-btn:hover { background: #a8c4bb; color: #1a2e24; }

/* Badge variants */
.chg-badge-horaire { color: #fff; }
.chg-badge-horaire-lg { color: #fff; font-size: 0.8rem; }
.chg-badge-statut-sm { font-size: 0.65rem; }

/* Scroll behavior helpers */
.chg-scroll-auto { scroll-behavior: auto; }
.chg-scroll-smooth { scroll-behavior: smooth; }
.chg-snap-none { scroll-snap-type: none; }

/* Animation reset helper */
.chg-anim-reset { animation: none !important; }

/* Two swap date highlighting (cross-day exchange) */
.chg-swap-day-dem { background: rgba(188,210,203,.2) !important; }
.chg-swap-day-dest { background: rgba(178,201,212,.2) !important; }
.chg-swap-dem-header { background: rgba(188,210,203,.35) !important; color: #2d4a43; }
.chg-swap-dest-header { background: rgba(178,201,212,.35) !important; color: #3B4F6B; }

/* Count banner */
.chg-count-banner { font-size: .85rem; color: var(--cl-text-muted); }
.chg-count-pending { color: #856404; font-weight: 600; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2">
    <div class="zs-select chg-w-auto" id="chgStatutFilter" data-placeholder="Tous les statuts"></div>
  </div>
  <div class="chg-count-banner<?= $initPending > 0 ? ' chg-count-pending' : '' ?>" id="chgCount">
    <i class="bi bi-arrow-left-right me-2"></i><strong><?= count($initChangements) ?></strong> demande<?= count($initChangements) > 1 ? 's' : '' ?><?= $initPending > 0 ? ' · <strong>'.$initPending.'</strong> à traiter' : '' ?>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Cède</th>
          <th>Demandeur</th>
          <th>Son horaire</th>
          <th class="chg-text-center"><i class="bi bi-arrow-left-right"></i></th>
          <th>Collègue</th>
          <th>Son horaire</th>
          <th>Prend</th>
          <th>Motif</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody id="changementsTableBody">
        <?php if (empty($initChangements)): ?>
        <tr><td colspan="9" class="text-center py-4 text-muted">Aucune demande à traiter</td></tr>
        <?php else: ?>
        <?php foreach ($initChangements as $ch):
            $statutBadge = [
                'en_attente_collegue' => '<span class="badge bg-secondary">Attente collègue</span>',
                'confirme_collegue'   => '<span class="badge bg-warning text-dark">À traiter</span>',
                'valide'              => '<span class="badge bg-success">Validé</span>',
                'refuse'              => '<span class="badge bg-danger">Refusé'.($ch['refuse_par']==='collegue' ? ' (collègue)' : '').'</span>',
            ][$ch['statut']] ?? h($ch['statut']);
            $dateDem  = $ch['date_demandeur']  ?: $ch['date_jour'];
            $dateDest = $ch['date_destinataire'] ?: $ch['date_jour'];
        ?>
        <tr class="chg-row" data-id="<?= h($ch['id']) ?>" role="button">
          <td><strong><?= h(fmtChgDate($dateDem, $joursFr, $moisFr)) ?></strong></td>
          <td><strong><?= h($ch['demandeur_prenom'].' '.$ch['demandeur_nom']) ?></strong><br><small class="text-muted"><?= h($ch['demandeur_fonction']??'') ?></small></td>
          <td>
            <?php if (!empty($ch['horaire_demandeur_code'])): ?>
              <span class="badge chg-badge-horaire" style="background:<?= h($ch['horaire_demandeur_couleur']??'#6c757d') ?>"><?= h($ch['horaire_demandeur_code']) ?></span>
              <small class="text-muted"><?= h($ch['module_demandeur_nom']??'') ?></small>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="chg-text-center"><i class="bi bi-arrow-left-right text-primary"></i></td>
          <td><strong><?= h($ch['destinataire_prenom'].' '.$ch['destinataire_nom']) ?></strong><br><small class="text-muted"><?= h($ch['destinataire_fonction']??'') ?></small></td>
          <td>
            <?php if (!empty($ch['horaire_destinataire_code'])): ?>
              <span class="badge chg-badge-horaire" style="background:<?= h($ch['horaire_destinataire_couleur']??'#6c757d') ?>"><?= h($ch['horaire_destinataire_code']) ?></span>
              <small class="text-muted"><?= h($ch['module_destinataire_nom']??'') ?></small>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><strong><?= h(fmtChgDate($dateDest, $joursFr, $moisFr)) ?></strong></td>
          <td><?= !empty($ch['motif']) ? '<small>'.h($ch['motif']).'</small>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= $statutBadge ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal détail changement -->
<div class="modal fade" id="changementDetailModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content modal-info">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Détail du changement</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body chg-modal-body-scroll">
        <div class="row mb-3" id="chgDetailSummary"></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <button class="btn btn-sm btn-outline-secondary" id="chgWeekPrev"><i class="bi bi-chevron-left"></i> Semaine préc.</button>
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="chgZoomOut" title="Réduire"><i class="bi bi-zoom-out"></i></button>
            <strong id="chgWeekLabel"></strong>
            <button class="btn btn-sm btn-outline-secondary" id="chgZoomIn" title="Agrandir"><i class="bi bi-zoom-in"></i></button>
          </div>
          <button class="btn btn-sm btn-outline-secondary" id="chgWeekNext">Semaine suiv. <i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="chg-grid-wrapper" id="chgGridWrapper">
          <table class="table table-bordered table-sm mb-0 chg-planning-table">
            <thead id="chgPlanningHead"></thead>
            <tbody id="chgPlanningBody"></tbody>
          </table>
        </div>
        <div class="chg-swipe-hint" id="chgSwipeHint">
          <i class="bi bi-chevron-left"></i>
          <span>Glissez pour naviguer jour par jour</span>
          <i class="bi bi-chevron-right"></i>
        </div>
        <div class="mt-3 d-none" id="chgRefusBlock">
          <label class="form-label fw-semibold">Raison du refus (optionnel)</label>
          <textarea class="form-control" id="chgRefusRaison" rows="2" placeholder="Expliquer la raison..." maxlength="500"></textarea>
        </div>
        <div class="mt-3 d-none" id="chgRefusInfo"></div>
      </div>
      <div class="modal-footer" id="chgDetailFooter">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal confirmation validation -->
<div class="modal fade" id="chgConfirmModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content chg-confirm-content">
      <div class="modal-body text-center py-4 px-4 position-relative">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" id="chgConfirmClose" aria-label="Fermer"></button>
        <div class="chg-confirm-icon mb-3">
          <svg class="chg-check-svg" viewBox="0 0 80 80" width="80" height="80">
            <circle class="chg-check-circle" cx="40" cy="40" r="36" fill="none" stroke="#bcd2cb" stroke-width="3"/>
            <path class="chg-check-mark" d="M24 42 L35 53 L56 28" fill="none" stroke="#bcd2cb" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h6 class="fw-semibold mb-2 chg-confirm-title">Confirmer la validation</h6>
        <p class="text-muted small mb-0">Les horaires des deux collaborateurs seront échangés. Cette action est irréversible.</p>
      </div>
      <div class="modal-footer border-top chg-confirm-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary px-4" id="chgConfirmCancel">Annuler</button>
        <button type="button" class="btn btn-sm px-4 chg-confirm-ok-btn" id="chgConfirmOk">Valider l'échange</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
let chgDetailModalInstance = null;
let chgCurrentId = null;
let chgIsRefusing = false;
// Données initiales injectées côté serveur (filtre: confirme_collegue)
let chgCachedList = <?= json_encode(array_values($initChangements), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

let chgBuffer = { days: [], demandeur: {}, destinataire: {}, changement: null, viewStart: 0 };
const DAY_NAMES_FR = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

function initChangementsPage() {
    zerdaSelect.init(document.getElementById('chgStatutFilter'), [
        {value: '', label: 'Tous les statuts'},
        {value: 'confirme_collegue', label: 'Confirm\u00e9s (\u00e0 traiter)'},
        {value: 'en_attente_collegue', label: 'En attente coll\u00e8gue'},
        {value: 'valide', label: 'Valid\u00e9s'},
        {value: 'refuse', label: 'Refus\u00e9s'}
    ], { value: 'confirme_collegue', onSelect: loadChangements });
    document.getElementById('chgWeekPrev')?.addEventListener('click', () => navigateDays(-7));
    document.getElementById('chgWeekNext')?.addEventListener('click', () => navigateDays(7));
    document.getElementById('chgZoomIn')?.addEventListener('click', () => zoomGrid(1));
    document.getElementById('chgZoomOut')?.addEventListener('click', () => zoomGrid(-1));
    initSwipeGesture();

    // Event delegation on tbody — no need for loadChangements on init
    document.getElementById('changementsTableBody').addEventListener('click', (e) => {
        const row = e.target.closest('.chg-row');
        if (row) openDetail(row.dataset.id);
    });
}

/* ── Zoom ── */
const ZOOM_STEPS = [60, 75, 90, 110, 130, 160];
let chgZoomLevel = 2;
function getColW() { return ZOOM_STEPS[chgZoomLevel]; }
function zoomGrid(dir) {
    const w = document.getElementById('chgGridWrapper');
    const oldW = getColW();
    const centerScroll = w.scrollLeft + (w.clientWidth - 160) / 2;
    const centerDayIdx = centerScroll / oldW;
    chgZoomLevel = Math.max(0, Math.min(ZOOM_STEPS.length - 1, chgZoomLevel + dir));
    const newW = getColW();
    applyZoom();
    w.classList.remove('chg-scroll-smooth');
    w.classList.add('chg-scroll-auto');
    w.scrollLeft = centerDayIdx * newW - (w.clientWidth - 160) / 2;
    updateDateLabel();
}
function applyZoom() {
    const table = document.querySelector('.chg-planning-table');
    if (table) table.style.setProperty('--chg-col-w', getColW() + 'px');
}

let swipeStartX = 0, swipeStartScroll = 0, swiping = false;
let chgAnimating = false;
function initSwipeGesture() {
    const w = document.getElementById('chgGridWrapper');
    if (!w) return;
    w.addEventListener('mousedown',  onSwipeStart);
    w.addEventListener('mousemove',  onSwipeMove);
    w.addEventListener('mouseup',    onSwipeEnd);
    w.addEventListener('mouseleave', onSwipeEnd);
    w.addEventListener('touchstart', onSwipeStart, { passive: true });
    w.addEventListener('touchmove',  onSwipeMove, { passive: false });
    w.addEventListener('touchend',   onSwipeEnd);
    w.addEventListener('scroll', updateDateLabel, { passive: true });
}
function getX(e) { return e.touches ? e.touches[0].clientX : e.clientX; }
function onSwipeStart(e) {
    if (chgAnimating) return;
    const w = document.getElementById('chgGridWrapper');
    swiping = true; swipeStartX = getX(e); swipeStartScroll = w.scrollLeft;
    w.classList.remove('chg-scroll-smooth');
    w.classList.add('chg-scroll-auto', 'chg-snap-none');
}
function onSwipeMove(e) {
    if (!swiping) return;
    const dx = getX(e) - swipeStartX;
    document.getElementById('chgGridWrapper').scrollLeft = swipeStartScroll - dx;
    if (Math.abs(dx) > 8 && e.cancelable) e.preventDefault();
}
function onSwipeEnd() { if (!swiping) return; swiping = false; snapToDay(); }
function snapToDay() {
    const w = document.getElementById('chgGridWrapper');
    const nearestDay = Math.round(w.scrollLeft / getColW()) * getColW();
    w.classList.remove('chg-scroll-auto');
    w.classList.add('chg-scroll-smooth');
    w.scrollLeft = nearestDay;
    setTimeout(() => { w.classList.remove('chg-snap-none'); }, 300);
}
function navigateDays(n) {
    if (chgAnimating) return;
    const w = document.getElementById('chgGridWrapper');
    w.classList.remove('chg-scroll-auto');
    w.classList.add('chg-scroll-smooth');
    w.scrollLeft = Math.max(0, w.scrollLeft + n * getColW());
    setTimeout(() => snapToDay(), 350);
}
function updateDateLabel() {
    const w = document.getElementById('chgGridWrapper');
    if (!w || !chgBuffer.days.length) return;
    const colW = getColW();
    const firstVisIdx = Math.round(w.scrollLeft / colW);
    const visCount = Math.round((w.clientWidth - 160) / colW);
    const cFirst = Math.max(0, Math.min(firstVisIdx, chgBuffer.days.length - 1));
    const cLast  = Math.max(0, Math.min(firstVisIdx + visCount - 1, chgBuffer.days.length - 1));
    const d1 = new Date(chgBuffer.days[cFirst] + 'T00:00:00');
    const d2 = new Date(chgBuffer.days[cLast]  + 'T00:00:00');
    const label = document.getElementById('chgWeekLabel');
    if (label) label.textContent = d1.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' }) + ' \u2014 ' + d2.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function loadChangements() {
    const statut = zerdaSelect.getValue('#chgStatutFilter') || '';
    const res = await adminApiPost('admin_get_changements', { statut });
    const items = res.changements || [];
    chgCachedList = items;

    const tbody = document.getElementById('changementsTableBody');
    const countEl = document.getElementById('chgCount');
    const n = items.length;
    const pending = items.filter(c => c.statut === 'confirme_collegue').length;
    let countHtml = `<i class="bi bi-arrow-left-right me-2"></i><strong>${n}</strong> demande${n > 1 ? 's' : ''}`;
    if (pending > 0) countHtml += ` \u00b7 <strong>${pending}</strong> \u00e0 traiter`;
    countEl.innerHTML = countHtml;
    countEl.className = 'chg-count-banner' + (pending > 0 ? ' chg-count-pending' : '');

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Aucune demande</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(ch => {
        const statutBadge = getStatutBadge(ch);
        const horDem = ch.horaire_demandeur_code
            ? `<span class="badge chg-badge-horaire" style="background:${escapeHtml(ch.horaire_demandeur_couleur || '#6c757d')}">${escapeHtml(ch.horaire_demandeur_code)}</span><small class="text-muted">${escapeHtml(ch.module_demandeur_nom || '')}</small>`
            : '\u2014';
        const horDest = ch.horaire_destinataire_code
            ? `<span class="badge chg-badge-horaire" style="background:${escapeHtml(ch.horaire_destinataire_couleur || '#6c757d')}">${escapeHtml(ch.horaire_destinataire_code)}</span><small class="text-muted">${escapeHtml(ch.module_destinataire_nom || '')}</small>`
            : '\u2014';
        const dateDem = ch.date_demandeur || ch.date_jour;
        const dateDest = ch.date_destinataire || ch.date_jour;
        const dateDemFmt = new Date(dateDem + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'short', day: 'numeric', month: 'short' });
        const dateDestFmt = new Date(dateDest + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'short', day: 'numeric', month: 'short' });
        return `<tr class="chg-row" data-id="${escapeHtml(ch.id)}" role="button">
            <td><strong>${escapeHtml(dateDemFmt)}</strong></td>
            <td><strong>${escapeHtml(ch.demandeur_prenom)} ${escapeHtml(ch.demandeur_nom)}</strong><br><small class="text-muted">${escapeHtml(ch.demandeur_fonction || '')}</small></td>
            <td>${horDem}</td>
            <td class="chg-text-center"><i class="bi bi-arrow-left-right text-primary"></i></td>
            <td><strong>${escapeHtml(ch.destinataire_prenom)} ${escapeHtml(ch.destinataire_nom)}</strong><br><small class="text-muted">${escapeHtml(ch.destinataire_fonction || '')}</small></td>
            <td>${horDest}</td>
            <td><strong>${escapeHtml(dateDestFmt)}</strong></td>
            <td>${ch.motif ? `<small>${escapeHtml(ch.motif)}</small>` : '<span class="text-muted">\u2014</span>'}</td>
            <td>${statutBadge}</td>
        </tr>`;
    }).join('');
}

function getStatutBadge(ch) {
    const map = {
        'en_attente_collegue': '<span class="badge bg-secondary">Attente coll\u00e8gue</span>',
        'confirme_collegue': '<span class="badge bg-warning text-dark">\u00c0 traiter</span>',
        'valide': '<span class="badge bg-success">Valid\u00e9</span>',
        'refuse': `<span class="badge bg-danger">Refus\u00e9${ch.refuse_par === 'collegue' ? ' (coll\u00e8gue)' : ''}</span>`
    };
    return map[ch.statut] || ch.statut;
}

function openDetail(id) {
    chgCurrentId = id;
    chgIsRefusing = false;
    document.getElementById('chgRefusBlock')?.classList.add('d-none');
    document.getElementById('chgRefusRaison').value = '';
    loadDetail(id);
}

async function loadDetail(id) {
    if (!chgDetailModalInstance) {
        chgDetailModalInstance = new bootstrap.Modal(document.getElementById('changementDetailModal'));
    }
    chgDetailModalInstance.show();
    document.getElementById('chgPlanningBody').innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">Chargement...</td></tr>';

    const res = await adminApiPost('admin_get_changement_detail', { id });
    if (!res.success) { showToast(res.message || 'Erreur', 'danger'); return; }

    const ch = res.changement;
    chgBuffer.days = res.days;
    chgBuffer.demandeur = res.planning_demandeur;
    chgBuffer.destinataire = res.planning_destinataire;
    chgBuffer.changement = ch;
    chgBuffer.viewStart = res.view_start || 7;
    chgBuffer.swapDates = res.swap_dates || [ch.date_demandeur || ch.date_jour, ch.date_destinataire || ch.date_jour];

    const summaryEl = document.getElementById('chgDetailSummary');
    const dateDem = ch.date_demandeur || ch.date_jour;
    const dateDest = ch.date_destinataire || ch.date_jour;
    const dateDemFmt = new Date(dateDem + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const dateDestFmt = new Date(dateDest + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    summaryEl.innerHTML = `
      <div class="col-md-5"><div class="card border-0 bg-light p-2">
        <div class="fw-bold"><i class="bi bi-person-fill text-primary me-1"></i>${escapeHtml(ch.demandeur_prenom)} ${escapeHtml(ch.demandeur_nom)}</div>
        <small class="text-muted">${escapeHtml(ch.demandeur_fonction || '')}</small>
        <div class="mt-1"><small class="text-muted">C\u00e8de son horaire du</small> <strong>${escapeHtml(dateDemFmt)}</strong></div>
      </div></div>
      <div class="col-md-2 d-flex align-items-center justify-content-center"><i class="bi bi-arrow-left-right fs-4 text-primary"></i></div>
      <div class="col-md-5"><div class="card border-0 bg-light p-2">
        <div class="fw-bold"><i class="bi bi-person-fill text-info me-1"></i>${escapeHtml(ch.destinataire_prenom)} ${escapeHtml(ch.destinataire_nom)}</div>
        <small class="text-muted">${escapeHtml(ch.destinataire_fonction || '')}</small>
        <div class="mt-1"><small class="text-muted">C\u00e8de son horaire du</small> <strong>${escapeHtml(dateDestFmt)}</strong></div>
      </div></div>
      <div class="col-12 mt-2 text-center">${ch.motif ? `<em class="text-muted">${escapeHtml(ch.motif)}</em> \u00b7 ` : ''}${getStatutBadge(ch)}</div>`;

    renderGrid();

    const footer = document.getElementById('chgDetailFooter');
    const refusInfo = document.getElementById('chgRefusInfo');
    refusInfo.classList.add('d-none');

    if (ch.statut === 'confirme_collegue') {
        footer.innerHTML = `
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn btn-outline-danger" id="chgBtnRefuser"><i class="bi bi-x-circle me-1"></i>Refuser</button>
          <button type="button" class="btn btn-success" id="chgBtnValider"><i class="bi bi-check-circle me-1"></i>Valider l'\u00e9change</button>`;
        footer.querySelector('#chgBtnValider').addEventListener('click', () => doValidate(ch.id));
        footer.querySelector('#chgBtnRefuser').addEventListener('click', () => toggleRefus(ch.id));
    } else {
        footer.innerHTML = `<button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>`;
        if (ch.statut === 'refuse' && ch.raison_refus) {
            refusInfo.classList.remove('d-none');
            refusInfo.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i><strong>Refus\u00e9${ch.refuse_par === 'collegue' ? ' par le coll\u00e8gue' : " par l'admin"} :</strong> ${escapeHtml(ch.raison_refus)}</div>`;
        }
    }
}

function renderGrid() {
    const ch = chgBuffer.changement;
    const allDays = chgBuffer.days;
    const swapDates = chgBuffer.swapDates || [];
    const thead = document.getElementById('chgPlanningHead');
    thead.innerHTML = `<tr><th class="chg-user-col">Collaborateur</th>${allDays.map(d => {
        const dt = new Date(d + 'T00:00:00');
        const isSwapDem = d === swapDates[0], isSwapDest = d === swapDates[1];
        let cls = 'chg-day-col text-center';
        if (isSwapDem) cls += ' chg-swap-day-header chg-swap-dem-header';
        if (isSwapDest) cls += ' chg-swap-day-header chg-swap-dest-header';
        return `<th class="${cls}">${DAY_NAMES_FR[dt.getDay()]}<br><small>${dt.getDate()}</small></th>`;
    }).join('')}</tr>`;
    const tbody = document.getElementById('chgPlanningBody');
    tbody.innerHTML = buildPlanningRow(ch.demandeur_prenom + ' ' + ch.demandeur_nom, chgBuffer.demandeur, allDays, swapDates, ch.assignation_demandeur_id, 'primary')
        + buildPlanningRow(ch.destinataire_prenom + ' ' + ch.destinataire_nom, chgBuffer.destinataire, allDays, swapDates, ch.assignation_destinataire_id, 'info');
    applyZoom();
    scrollToSwapDay();
    updateDateLabel();
}

function scrollToSwapDay() {
    const w = document.getElementById('chgGridWrapper');
    const ch = chgBuffer.changement;
    const allDays = chgBuffer.days;
    if (!w || !ch || !allDays.length) return;
    const colW = getColW();
    const swapDates = chgBuffer.swapDates || [];
    const swapIdx = swapDates.length ? allDays.indexOf(swapDates[0]) : -1;
    const scrollTarget = swapIdx >= 0 ? swapIdx : chgBuffer.viewStart;
    const visibleW = w.clientWidth - 160;
    if (visibleW <= 0) {
        const modalEl = document.getElementById('changementDetailModal');
        const handler = () => { modalEl.removeEventListener('shown.bs.modal', handler); scrollToSwapDay(); };
        modalEl.addEventListener('shown.bs.modal', handler);
        return;
    }
    w.classList.remove('chg-scroll-smooth'); w.classList.add('chg-scroll-auto');
    w.scrollLeft = Math.max(0, scrollTarget * colW - visibleW / 2 + colW / 2);
    updateDateLabel();
}

function buildPlanningRow(name, planningData, days, swapDates, swapAssignId, colorClass) {
    const cells = days.map(d => {
        const cell = planningData[d];
        const isSwapDem = d === swapDates[0], isSwapDest = d === swapDates[1];
        let content = '<span class="text-muted">\u2014</span>';
        let highlight = '';
        if (cell) {
            const bgColor = cell.horaire_couleur || '#6c757d';
            content = `<span class="badge chg-badge-horaire-lg" style="background:${escapeHtml(bgColor)}">${escapeHtml(cell.horaire_code || '?')}</span>`;
            if (cell.module_code) content += `<br><small class="text-muted">${escapeHtml(cell.module_code)}</small>`;
            if (cell.statut && cell.statut !== 'present') content += `<br><span class="badge bg-secondary chg-badge-statut-sm">${escapeHtml(cell.statut)}</span>`;
            if (cell.id === swapAssignId) highlight = ' chg-swap-cell';
        }
        let cls = 'chg-day-col text-center';
        if (isSwapDem) cls += ' chg-swap-day chg-swap-day-dem';
        if (isSwapDest) cls += ' chg-swap-day chg-swap-day-dest';
        cls += highlight;
        return `<td class="${cls}">${content}</td>`;
    }).join('');
    return `<tr><td class="chg-user-col"><strong class="text-${colorClass}"><i class="bi bi-person-fill me-1"></i>${escapeHtml(name)}</strong></td>${cells}</tr>`;
}

function toggleRefus(id) {
    const block = document.getElementById('chgRefusBlock');
    if (chgIsRefusing) { doRefuse(id); }
    else {
        chgIsRefusing = true;
        block.classList.remove('d-none');
        document.getElementById('chgRefusRaison').focus();
        const btn = document.getElementById('chgBtnRefuser');
        btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Confirmer le refus';
        btn.classList.replace('btn-outline-danger', 'btn-danger');
    }
}

async function doValidate(id) {
    const confirmed = await showConfirmValidation();
    if (!confirmed) return;
    const res = await adminApiPost('admin_valider_changement', { id, decision: 'valide' });
    if (res.success) {
        showToast(res.message, 'success');
        if (chgDetailModalInstance) chgDetailModalInstance.hide();
        location.reload();
    } else { showToast(res.message || 'Erreur', 'danger'); }
}

function showConfirmValidation() {
    return new Promise(resolve => {
        const modalEl = document.getElementById('chgConfirmModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const circleEl = modalEl.querySelector('.chg-check-circle');
        const markEl = modalEl.querySelector('.chg-check-mark');
        circleEl.classList.add('chg-anim-reset'); markEl.classList.add('chg-anim-reset');
        void modalEl.offsetWidth;
        circleEl.classList.remove('chg-anim-reset'); markEl.classList.remove('chg-anim-reset');
        const cleanup = (result) => {
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            closeBtn.removeEventListener('click', onCancel);
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            resolve(result);
        };
        const onOk = () => { modal.hide(); cleanup(true); };
        const onCancel = () => { modal.hide(); cleanup(false); };
        const onHidden = () => { cleanup(false); };
        const okBtn = document.getElementById('chgConfirmOk');
        const cancelBtn = document.getElementById('chgConfirmCancel');
        const closeBtn = document.getElementById('chgConfirmClose');
        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        closeBtn.addEventListener('click', onCancel);
        modalEl.addEventListener('hidden.bs.modal', onHidden);
        modal.show();
    });
}

async function doRefuse(id) {
    const raison = document.getElementById('chgRefusRaison')?.value || '';
    const res = await adminApiPost('admin_valider_changement', { id, decision: 'refuse', raison });
    if (res.success) {
        showToast(res.message, 'success');
        if (chgDetailModalInstance) chgDetailModalInstance.hide();
        location.reload();
    } else { showToast(res.message || 'Erreur', 'danger'); }
}

window.initChangementsPage = initChangementsPage;
</script>
