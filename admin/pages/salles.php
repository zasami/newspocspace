<?php
/**
 * Admin — Réservation de salles
 * Vue semaine + gestion des salles
 */
$salles = Db::fetchAll("SELECT * FROM salles WHERE is_active = 1 ORDER BY ordre, nom");
$allSalles = Db::fetchAll("SELECT * FROM salles ORDER BY ordre, nom");
$users = Db::fetchAll(
    "SELECT id, prenom, nom FROM users WHERE is_active = 1 ORDER BY nom, prenom"
);
?>
<style>
/* ── Layout ── */
.sl-tabs { display: flex; gap: 2px; background: var(--cl-bg, #F7F5F2); border-radius: 10px; padding: 3px; margin-bottom: 20px; width: fit-content; }
.sl-tab { padding: 7px 18px; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--cl-text-muted, #888); transition: all .15s; }
.sl-tab.active { background: var(--cl-surface, #fff); color: var(--cl-text, #1a1a1a); box-shadow: 0 1px 3px rgba(0,0,0,.06); }

/* ── Toolbar ── */
.sl-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.sl-nav-btn { background: none; border: 1.5px solid var(--cl-border, #e0dcd7); cursor: pointer; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: .9rem; }
.sl-nav-btn:hover { border-color: #2d4a43; color: #2d4a43; }
.sl-week-label { font-weight: 700; font-size: 1rem; min-width: 200px; }
.sl-today-btn { padding: 5px 14px; border-radius: 8px; font-size: .78rem; font-weight: 600; cursor: pointer; border: 1.5px solid var(--cl-border); background: var(--cl-surface, #fff); color: var(--cl-text); }
.sl-today-btn:hover { border-color: #2d4a43; color: #2d4a43; }
.sl-filter { padding: 5px 12px; border-radius: 8px; font-size: .82rem; border: 1.5px solid var(--cl-border); background: var(--cl-surface, #fff); }

/* ── Timeline grid ── */
.sl-grid-wrap { overflow-x: auto; background: var(--cl-surface, #fff); border-radius: 12px; border: 1.5px solid var(--cl-border-light, #F0EDE8); }
.sl-grid { display: grid; min-width: 800px; }
.sl-grid-header { display: contents; }
.sl-grid-header > div { padding: 10px 8px; font-weight: 700; font-size: .78rem; text-align: center; background: var(--cl-bg, #F7F5F2); border-bottom: 1.5px solid var(--cl-border-light); position: sticky; top: 0; z-index: 2; }
.sl-grid-header > div:first-child { text-align: left; padding-left: 14px; }
.sl-time-col { padding: 0 10px; font-size: .72rem; color: var(--cl-text-muted); text-align: right; border-right: 1.5px solid var(--cl-border-light); min-width: 60px; position: sticky; left: 0; background: var(--cl-surface, #fff); z-index: 1; }
.sl-day-col { position: relative; min-height: 50px; border-right: 1px solid var(--cl-border-light, #F0EDE8); border-bottom: 1px solid var(--cl-border-light, #F0EDE8); cursor: pointer; }
.sl-day-col:hover { background: rgba(45,74,67,.03); }
.sl-day-col.is-today { background: rgba(45,74,67,.05); }

/* ── Reservation blocks ── */
.sl-block { position: absolute; left: 3px; right: 3px; border-radius: 6px; padding: 4px 7px; font-size: .72rem; overflow: hidden; cursor: pointer; z-index: 3; color: #fff; line-height: 1.3; box-shadow: 0 1px 3px rgba(0,0,0,.12); transition: transform .1s; }
.sl-block:hover { transform: scale(1.02); box-shadow: 0 2px 8px rgba(0,0,0,.18); }
.sl-block.sl-block-journee { background-image: repeating-linear-gradient(135deg, transparent, transparent 4px, rgba(255,255,255,.18) 4px, rgba(255,255,255,.18) 8px) !important; border: 2px solid rgba(255,255,255,.35); }
.sl-block-title { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sl-block-time { opacity: .85; font-size: .65rem; }
.sl-block-user { opacity: .75; font-size: .65rem; }

/* ── Room cards ── */
.sl-room-card { background: var(--cl-surface, #fff); border: 1.5px solid var(--cl-border-light, #F0EDE8); border-radius: 12px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; transition: border-color .15s; }
.sl-room-card:hover { border-color: var(--cl-border, #d0ccc7); }
.sl-room-dot { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; margin-top: 3px; }
.sl-room-name { font-weight: 700; font-size: .95rem; }
.sl-room-meta { font-size: .78rem; color: var(--cl-text-muted); margin-top: 2px; }
.sl-room-equip { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.sl-room-tag { background: var(--cl-bg, #F7F5F2); padding: 2px 8px; border-radius: 6px; font-size: .7rem; color: var(--cl-text-muted); }
.sl-room-actions { margin-left: auto; display: flex; gap: 6px; }
.sl-room-actions button { border: none; background: none; cursor: pointer; padding: 4px 8px; border-radius: 6px; font-size: .82rem; color: var(--cl-text-muted); }
.sl-room-actions button:hover { background: var(--cl-bg); color: var(--cl-text); }
.sl-room-inactive { opacity: .45; }

/* ── Legend ── */
.sl-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.sl-legend-item { display: flex; align-items: center; gap: 6px; font-size: .78rem; color: var(--cl-text-muted); }
.sl-legend-dot { width: 12px; height: 12px; border-radius: 3px; }

/* ── Modal ── */
.sl-modal-field { margin-bottom: 14px; }
.sl-modal-field label { font-weight: 600; font-size: .82rem; margin-bottom: 4px; display: block; }

@media (max-width: 768px) {
    .sl-toolbar { gap: 6px; }
    .sl-week-label { font-size: .85rem; min-width: auto; }
    .sl-grid { min-width: 600px; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-door-open"></i> Réservation de salles</h4>
  <button class="btn btn-sm btn-dark" id="slNewResaBtn"><i class="bi bi-plus-lg"></i> Nouvelle réservation</button>
</div>

<!-- Tabs -->
<div class="sl-tabs">
  <button class="sl-tab active" data-tab="planning">Planning</button>
  <button class="sl-tab" data-tab="salles">Gérer les salles</button>
</div>

<!-- ═══ TAB: Planning ═══ -->
<div id="slTabPlanning">
  <!-- Legend -->
  <div class="sl-legend" id="slLegend"></div>

  <!-- Toolbar -->
  <div class="sl-toolbar">
    <button class="sl-nav-btn" id="slPrev" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
    <button class="sl-nav-btn" id="slNext" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
    <span class="sl-week-label" id="slWeekLabel"></span>
    <button class="sl-today-btn" id="slToday">Aujourd'hui</button>
    <select class="sl-filter" id="slSalleFilter">
      <option value="">Toutes les salles</option>
    </select>
  </div>

  <!-- Timeline -->
  <div class="sl-grid-wrap" id="slGridWrap" style="max-height: calc(100vh - 260px); overflow-y: auto;">
    <div class="sl-grid" id="slGrid"></div>
  </div>
</div>

<!-- ═══ TAB: Gérer les salles ═══ -->
<div id="slTabSalles" style="display:none">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted" style="font-size:.85rem"><?= count($allSalles) ?> salle(s) configurée(s)</span>
    <button class="btn btn-sm btn-outline-dark" id="slAddRoomBtn"><i class="bi bi-plus-lg"></i> Ajouter une salle</button>
  </div>
  <div class="d-flex flex-column gap-3" id="slRoomsList">
    <?php foreach ($allSalles as $s): ?>
    <div class="sl-room-card <?= $s['is_active'] ? '' : 'sl-room-inactive' ?>" data-room-id="<?= h($s['id']) ?>">
      <div class="sl-room-dot" style="background:<?= h($s['couleur']) ?>"></div>
      <div class="flex-grow-1">
        <div class="sl-room-name"><?= h($s['nom']) ?><?php if (!$s['is_active']): ?> <span class="badge bg-secondary" style="font-size:.65rem">Inactive</span><?php endif ?></div>
        <div class="sl-room-meta">
          <?php if ($s['capacite']): ?><i class="bi bi-people"></i> <?= (int)$s['capacite'] ?> pers. &nbsp;<?php endif ?>
          <?= h($s['description'] ?: '') ?>
        </div>
        <?php if ($s['equipements']): ?>
        <div class="sl-room-equip">
          <?php foreach (explode(',', $s['equipements']) as $eq): ?>
          <span class="sl-room-tag"><?= h(trim($eq)) ?></span>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>
      <div class="sl-room-actions">
        <button class="sl-edit-room" data-id="<?= h($s['id']) ?>" title="Modifier"><i class="bi bi-pencil"></i></button>
        <button class="sl-toggle-room" data-id="<?= h($s['id']) ?>" data-active="<?= $s['is_active'] ?>" title="<?= $s['is_active'] ? 'Désactiver' : 'Activer' ?>">
          <i class="bi bi-<?= $s['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
        </button>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- ═══ Modal Réservation ═══ -->
<div class="modal fade" id="slResaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="slResaModalTitle">Nouvelle réservation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="slResaId">
        <div class="sl-modal-field">
          <label>Salle *</label>
          <select class="form-select form-select-sm" id="slResaSalle">
            <?php foreach ($salles as $s): ?>
            <option value="<?= h($s['id']) ?>"><?= h($s['nom']) ?> (<?= (int)$s['capacite'] ?> pers.)</option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="sl-modal-field">
          <label>Titre / Objet *</label>
          <input type="text" class="form-control form-control-sm" id="slResaTitre" placeholder="Ex: Réunion d'équipe">
        </div>
        <div class="sl-modal-field">
          <label>Description</label>
          <textarea class="form-control form-control-sm" id="slResaDesc" rows="2" placeholder="Détails (optionnel)"></textarea>
        </div>
        <div class="sl-modal-field">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="slResaJournee">
            <label class="form-check-label fw-semibold" for="slResaJournee" style="font-size:.82rem">Journée entière</label>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-4 sl-modal-field">
            <label>Date *</label>
            <input type="date" class="form-control form-control-sm" id="slResaDate">
          </div>
          <div class="col-sm-4 sl-modal-field" id="slResaDebutWrap">
            <label>Début *</label>
            <input type="time" class="form-control form-control-sm" id="slResaDebut" value="08:00" step="900">
          </div>
          <div class="col-sm-4 sl-modal-field" id="slResaFinWrap">
            <label>Fin *</label>
            <input type="time" class="form-control form-control-sm" id="slResaFin" value="09:00" step="900">
          </div>
        </div>
        <div class="sl-modal-field">
          <label>Réservé pour</label>
          <select class="form-select form-select-sm" id="slResaUser">
            <?php foreach ($users as $u): ?>
            <option value="<?= h($u['id']) ?>" <?= $u['id'] === ($_SESSION['ss_user']['id'] ?? '') ? 'selected' : '' ?>><?= h($u['nom'] . ' ' . $u['prenom']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark" id="slResaSaveBtn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal Salle ═══ -->
<div class="modal fade" id="slRoomModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="slRoomModalTitle">Nouvelle salle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="slRoomId">
        <div class="sl-modal-field">
          <label>Nom *</label>
          <input type="text" class="form-control form-control-sm" id="slRoomNom" placeholder="Ex: Salle de conférence">
        </div>
        <div class="sl-modal-field">
          <label>Description</label>
          <input type="text" class="form-control form-control-sm" id="slRoomDesc" placeholder="Emplacement ou détails">
        </div>
        <div class="row g-2">
          <div class="col-sm-6 sl-modal-field">
            <label>Capacité</label>
            <input type="number" class="form-control form-control-sm" id="slRoomCapacite" min="0" value="10">
          </div>
          <div class="col-sm-6 sl-modal-field">
            <label>Couleur</label>
            <input type="color" class="form-control form-control-sm" id="slRoomCouleur" value="#2D9CDB" style="height:34px">
          </div>
        </div>
        <div class="sl-modal-field">
          <label>Équipements <span style="font-weight:400;color:var(--cl-text-muted)">(séparés par des virgules)</span></label>
          <input type="text" class="form-control form-control-sm" id="slRoomEquip" placeholder="Projecteur, Tableau blanc, TV">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark" id="slRoomSaveBtn">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal Détail ═══ -->
<div class="modal fade" id="slDetailModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="slDetailTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="slDetailBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-danger" id="slDetailDeleteBtn"><i class="bi bi-trash"></i> Annuler réservation</button>
        <button type="button" class="btn btn-sm btn-outline-dark" id="slDetailEditBtn"><i class="bi bi-pencil"></i> Modifier</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const salles = <?= json_encode(array_values($salles), JSON_HEX_TAG) ?>;
    const HOURS_START = 7;
    const HOURS_END = 20;
    const HOUR_H = 50; // px per hour

    let currentMonday = getMonday(new Date());
    let reservations = [];
    let filterSalle = '';
    let resaModal, roomModal, detailModal;
    let currentDetailId = null;

    // ── Init ──
    resaModal = new bootstrap.Modal(document.getElementById('slResaModal'));
    roomModal = new bootstrap.Modal(document.getElementById('slRoomModal'));
    detailModal = new bootstrap.Modal(document.getElementById('slDetailModal'));

    // Tabs
    document.querySelectorAll('.sl-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.sl-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const t = tab.dataset.tab;
            document.getElementById('slTabPlanning').style.display = t === 'planning' ? '' : 'none';
            document.getElementById('slTabSalles').style.display = t === 'salles' ? '' : 'none';
        });
    });

    // Legend
    renderLegend();

    // Filter
    const filterEl = document.getElementById('slSalleFilter');
    salles.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.nom;
        filterEl.appendChild(opt);
    });
    filterEl.addEventListener('change', () => { filterSalle = filterEl.value; renderGrid(); });

    // Nav
    document.getElementById('slPrev').addEventListener('click', () => { currentMonday.setDate(currentMonday.getDate() - 7); loadWeek(); });
    document.getElementById('slNext').addEventListener('click', () => { currentMonday.setDate(currentMonday.getDate() + 7); loadWeek(); });
    document.getElementById('slToday').addEventListener('click', () => { currentMonday = getMonday(new Date()); loadWeek(); });

    // New resa
    document.getElementById('slNewResaBtn').addEventListener('click', () => openResaModal());

    // Save resa
    document.getElementById('slResaSaveBtn').addEventListener('click', saveResa);

    // Room actions
    document.getElementById('slAddRoomBtn').addEventListener('click', () => openRoomModal());
    document.getElementById('slRoomSaveBtn').addEventListener('click', saveRoom);
    document.getElementById('slRoomsList').addEventListener('click', handleRoomAction);

    // Detail actions
    document.getElementById('slDetailDeleteBtn').addEventListener('click', deleteResa);
    document.getElementById('slDetailEditBtn').addEventListener('click', editFromDetail);

    // Initial load
    loadWeek();

    // ── Functions ──

    function getMonday(d) {
        const dt = new Date(d);
        const day = dt.getDay() || 7;
        dt.setDate(dt.getDate() - day + 1);
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    function fmtDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function fmtDateFr(d) {
        const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        const mois = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jul', 'aoû', 'sep', 'oct', 'nov', 'déc'];
        return jours[d.getDay()] + ' ' + d.getDate() + ' ' + mois[d.getMonth()];
    }

    function getWeekDates() {
        const dates = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(currentMonday);
            d.setDate(d.getDate() + i);
            dates.push(d);
        }
        return dates;
    }

    function renderLegend() {
        const el = document.getElementById('slLegend');
        el.innerHTML = salles.map(s =>
            '<div class="sl-legend-item"><div class="sl-legend-dot" style="background:' + escapeHtml(s.couleur) + '"></div>' + escapeHtml(s.nom) + '</div>'
        ).join('');
    }

    async function loadWeek() {
        const dates = getWeekDates();
        const moisFr = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        const d0 = dates[0], d6 = dates[6];
        let label = d0.getDate() + ' ' + moisFr[d0.getMonth()];
        if (d0.getMonth() !== d6.getMonth()) label += ' — ' + d6.getDate() + ' ' + moisFr[d6.getMonth()];
        else label += ' — ' + d6.getDate();
        label += ' ' + d6.getFullYear();
        document.getElementById('slWeekLabel').textContent = label;

        const res = await adminApiPost('admin_get_reservations_salles', {
            date_debut: fmtDate(d0),
            date_fin: fmtDate(d6)
        });
        if (res.success) {
            reservations = res.reservations || [];
            renderGrid();
        }
    }

    function renderGrid() {
        const dates = getWeekDates();
        const today = fmtDate(new Date());
        const totalHours = HOURS_END - HOURS_START;
        const filteredSalles = filterSalle ? salles.filter(s => s.id === filterSalle) : salles;
        const cols = dates.length;

        const grid = document.getElementById('slGrid');
        grid.style.gridTemplateColumns = '60px repeat(' + cols + ', 1fr)';

        let html = '<div class="sl-grid-header"><div>Heure</div>';
        dates.forEach(d => {
            const isToday = fmtDate(d) === today;
            html += '<div' + (isToday ? ' style="color:#2d4a43;background:#e8f0ed"' : '') + '>' + fmtDateFr(d) + '</div>';
        });
        html += '</div>';

        // Time rows
        for (let h = HOURS_START; h < HOURS_END; h++) {
            html += '<div class="sl-time-col" style="grid-row:' + (h - HOURS_START + 2) + ';grid-column:1;height:' + HOUR_H + 'px;display:flex;align-items:flex-start;padding-top:2px">' + String(h).padStart(2, '0') + ':00</div>';
            dates.forEach((d, di) => {
                const isToday = fmtDate(d) === today;
                html += '<div class="sl-day-col' + (isToday ? ' is-today' : '') + '" style="grid-row:' + (h - HOURS_START + 2) + ';grid-column:' + (di + 2) + ';height:' + HOUR_H + 'px" data-date="' + fmtDate(d) + '" data-hour="' + h + '"></div>';
            });
        }

        grid.innerHTML = html;
        grid.style.gridTemplateRows = 'auto repeat(' + totalHours + ', ' + HOUR_H + 'px)';

        // Place reservation blocks
        const dayResaMap = {};
        reservations.forEach(r => {
            if (filterSalle && r.salle_id !== filterSalle) return;
            const key = r.date_jour;
            if (!dayResaMap[key]) dayResaMap[key] = [];
            dayResaMap[key].push(r);
        });

        dates.forEach((d, di) => {
            const dateStr = fmtDate(d);
            const resas = dayResaMap[dateStr] || [];

            resas.forEach(r => {
                const salle = salles.find(s => s.id === r.salle_id);
                const color = salle ? salle.couleur : '#888';

                const [hd, md] = r.heure_debut.split(':').map(Number);
                const [hf, mf] = r.heure_fin.split(':').map(Number);
                const startMin = (hd - HOURS_START) * 60 + md;
                const endMin = (hf - HOURS_START) * 60 + mf;
                const topPx = startMin * HOUR_H / 60;
                const heightPx = Math.max((endMin - startMin) * HOUR_H / 60, 18);

                // Find the parent cell (first hour row for this day column)
                const colIdx = di + 2;
                const block = document.createElement('div');
                const isJEBlock = parseInt(r.journee_entiere);
                block.className = 'sl-block' + (isJEBlock ? ' sl-block-journee' : '');
                block.style.cssText = 'background:' + color + ';top:' + topPx + 'px;height:' + heightPx + 'px';
                block.dataset.id = r.id;
                const isJE = parseInt(r.journee_entiere);
                const timeLabel = isJE ? 'Journée entière' : r.heure_debut.substring(0,5) + ' — ' + r.heure_fin.substring(0,5);
                block.innerHTML = '<div class="sl-block-title">' + escapeHtml(r.titre) + '</div>'
                    + (heightPx > 30 ? '<div class="sl-block-time">' + timeLabel + '</div>' : '')
                    + (heightPx > 45 ? '<div class="sl-block-user">' + escapeHtml(r.prenom + ' ' + r.user_nom) + (filteredSalles.length > 1 ? ' · ' + escapeHtml(r.salle_nom) : '') + '</div>' : '');
                block.addEventListener('click', () => showDetail(r));

                // Attach to the day column — use a container approach
                // We'll position blocks inside the first cell of each day column
                const firstCell = grid.querySelector('.sl-day-col[data-date="' + dateStr + '"][data-hour="' + HOURS_START + '"]');
                if (firstCell) {
                    firstCell.style.position = 'relative';
                    firstCell.style.overflow = 'visible';
                    firstCell.appendChild(block);
                }
            });
        });

        // Click on empty cell → new reservation
        grid.querySelectorAll('.sl-day-col').forEach(cell => {
            cell.addEventListener('click', (e) => {
                if (e.target.closest('.sl-block')) return;
                const date = cell.dataset.date;
                const hour = parseInt(cell.dataset.hour);
                openResaModal(null, date, String(hour).padStart(2,'0') + ':00', String(hour + 1).padStart(2,'0') + ':00');
            });
        });
    }

    // ── Reservation modal ──

    // Toggle journée entière
    const slJourneeCheck = document.getElementById('slResaJournee');
    slJourneeCheck.addEventListener('change', () => {
        const hide = slJourneeCheck.checked;
        document.getElementById('slResaDebutWrap').style.display = hide ? 'none' : '';
        document.getElementById('slResaFinWrap').style.display = hide ? 'none' : '';
    });

    function openResaModal(resa, date, debut, fin) {
        document.getElementById('slResaId').value = resa ? resa.id : '';
        document.getElementById('slResaModalTitle').textContent = resa ? 'Modifier la réservation' : 'Nouvelle réservation';
        document.getElementById('slResaSalle').value = resa ? resa.salle_id : (filterSalle || (salles[0]?.id || ''));
        document.getElementById('slResaTitre').value = resa ? resa.titre : '';
        document.getElementById('slResaDesc').value = resa ? (resa.description || '') : '';
        document.getElementById('slResaDate').value = resa ? resa.date_jour : (date || fmtDate(new Date()));
        const isJournee = resa ? !!parseInt(resa.journee_entiere) : false;
        slJourneeCheck.checked = isJournee;
        document.getElementById('slResaDebutWrap').style.display = isJournee ? 'none' : '';
        document.getElementById('slResaFinWrap').style.display = isJournee ? 'none' : '';
        document.getElementById('slResaDebut').value = resa && !isJournee ? resa.heure_debut.substring(0,5) : (debut || '08:00');
        document.getElementById('slResaFin').value = resa && !isJournee ? resa.heure_fin.substring(0,5) : (fin || '09:00');
        if (resa) document.getElementById('slResaUser').value = resa.user_id;
        resaModal.show();
    }

    async function saveResa() {
        const id = document.getElementById('slResaId').value;
        const isJournee = slJourneeCheck.checked;
        const data = {
            salle_id: document.getElementById('slResaSalle').value,
            titre: document.getElementById('slResaTitre').value.trim(),
            description: document.getElementById('slResaDesc').value.trim(),
            date_jour: document.getElementById('slResaDate').value,
            journee_entiere: isJournee ? 1 : 0,
            heure_debut: isJournee ? '00:00' : document.getElementById('slResaDebut').value,
            heure_fin: isJournee ? '23:59' : document.getElementById('slResaFin').value,
            user_id: document.getElementById('slResaUser').value,
        };

        if (!data.titre) { showToast('Titre requis', 'error'); return; }
        if (!data.date_jour) { showToast('Date requise', 'error'); return; }

        const act = id ? 'admin_update_reservation_salle' : 'admin_create_reservation_salle';
        if (id) data.id = id;

        const res = await adminApiPost(act, data);
        if (res.success) {
            resaModal.hide();
            showToast(id ? 'Réservation modifiée' : 'Réservation créée', 'success');
            loadWeek();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    // ── Detail modal ──

    function showDetail(r) {
        currentDetailId = r.id;
        const salle = salles.find(s => s.id === r.salle_id);
        document.getElementById('slDetailTitle').textContent = r.titre;

        const dateFr = new Date(r.date_jour + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        let html = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">'
            + '<div style="width:12px;height:12px;border-radius:3px;background:' + escapeHtml(salle?.couleur || '#888') + '"></div>'
            + '<strong>' + escapeHtml(salle?.nom || '?') + '</strong></div>'
            + '<p style="margin:0 0 6px"><i class="bi bi-calendar3"></i> ' + escapeHtml(dateFr) + '</p>'
            + '<p style="margin:0 0 6px"><i class="bi bi-clock"></i> ' + (parseInt(r.journee_entiere) ? 'Journée entière' : r.heure_debut.substring(0,5) + ' — ' + r.heure_fin.substring(0,5)) + '</p>'
            + '<p style="margin:0 0 6px"><i class="bi bi-person"></i> ' + escapeHtml(r.prenom + ' ' + r.user_nom) + (r.fonction_nom ? ' <span style="color:var(--cl-text-muted)">(' + escapeHtml(r.fonction_nom) + ')</span>' : '') + '</p>';
        if (r.description) html += '<p style="margin:10px 0 0;font-size:.85rem;color:var(--cl-text-muted)">' + escapeHtml(r.description) + '</p>';

        document.getElementById('slDetailBody').innerHTML = html;

        // Store full resa for edit
        document.getElementById('slDetailEditBtn').dataset.resa = JSON.stringify(r);
        detailModal.show();
    }

    async function deleteResa() {
        if (!currentDetailId) return;
        if (!confirm('Annuler cette réservation ?')) return;
        const res = await adminApiPost('admin_delete_reservation_salle', { id: currentDetailId });
        if (res.success) {
            detailModal.hide();
            showToast('Réservation annulée', 'success');
            loadWeek();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    function editFromDetail() {
        const resa = JSON.parse(document.getElementById('slDetailEditBtn').dataset.resa || '{}');
        detailModal.hide();
        setTimeout(() => openResaModal(resa), 300);
    }

    // ── Room modal ──

    function openRoomModal(room) {
        document.getElementById('slRoomId').value = room ? room.id : '';
        document.getElementById('slRoomModalTitle').textContent = room ? 'Modifier la salle' : 'Nouvelle salle';
        document.getElementById('slRoomNom').value = room ? room.nom : '';
        document.getElementById('slRoomDesc').value = room ? (room.description || '') : '';
        document.getElementById('slRoomCapacite').value = room ? room.capacite : 10;
        document.getElementById('slRoomCouleur').value = room ? room.couleur : '#2D9CDB';
        document.getElementById('slRoomEquip').value = room ? (room.equipements || '') : '';
        roomModal.show();
    }

    async function saveRoom() {
        const id = document.getElementById('slRoomId').value;
        const data = {
            nom: document.getElementById('slRoomNom').value.trim(),
            description: document.getElementById('slRoomDesc').value.trim(),
            capacite: parseInt(document.getElementById('slRoomCapacite').value) || 0,
            couleur: document.getElementById('slRoomCouleur').value,
            equipements: document.getElementById('slRoomEquip').value.trim(),
        };

        if (!data.nom) { showToast('Nom requis', 'error'); return; }

        const act = id ? 'admin_update_salle' : 'admin_create_salle';
        if (id) data.id = id;

        const res = await adminApiPost(act, data);
        if (res.success) {
            roomModal.hide();
            showToast(id ? 'Salle modifiée' : 'Salle créée', 'success');
            location.reload();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    function handleRoomAction(e) {
        const editBtn = e.target.closest('.sl-edit-room');
        const toggleBtn = e.target.closest('.sl-toggle-room');

        if (editBtn) {
            const id = editBtn.dataset.id;
            // Load room data
            adminApiPost('admin_get_salles').then(res => {
                if (res.success) {
                    const room = res.salles.find(s => s.id === id);
                    if (room) openRoomModal(room);
                }
            });
        }

        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            const isActive = toggleBtn.dataset.active === '1';
            if (isActive && !confirm('Désactiver cette salle ?')) return;
            adminApiPost('admin_toggle_salle', { id }).then(res => {
                if (res.success) {
                    showToast(isActive ? 'Salle désactivée' : 'Salle réactivée', 'success');
                    location.reload();
                }
            });
        }
    }

    // ── Helpers ──
    function fmtDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }
})();
</script>
