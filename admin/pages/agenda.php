<style>
/* ══ AGENDA ══ */
.ag-shell { display: flex; gap: 0; height: calc(100vh - 60px); margin: -20px -24px; overflow: hidden; }

/* ── Sidebar ── */
.ag-side { width: 280px; border-right: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden; }
.ag-side-header { padding: 16px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.ag-mini-cal { padding: 8px 16px; }
.ag-mini-cal table { width: 100%; border-collapse: collapse; font-size: .72rem; text-align: center; }
.ag-mini-cal th { color: var(--cl-text-muted); font-weight: 600; padding: 4px 0; }
.ag-mini-cal td { padding: 3px 0; cursor: pointer; border-radius: 6px; }
.ag-mini-cal td:hover { background: var(--cl-bg); }
.ag-mini-cal td.today { background: #bcd2cb; color: #2d4a43; font-weight: 700; }
.ag-mini-cal td.selected { background: #2d4a43; color: #fff; font-weight: 700; }
.ag-mini-cal td.other-month { opacity: .3; }
.ag-mini-cal td.has-event::after { content: ''; display: block; width: 4px; height: 4px; border-radius: 50%; background: #2d4a43; margin: 1px auto 0; }
.ag-mini-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.ag-mini-nav button { background: none; border: none; cursor: pointer; color: var(--cl-text-muted); font-size: .9rem; padding: 4px 8px; border-radius: 6px; }
.ag-mini-nav button:hover { background: var(--cl-bg); color: var(--cl-text); }
.ag-mini-nav span { font-weight: 700; font-size: .82rem; }

/* Categories */
.ag-cats { padding: 12px 16px; border-top: 1px solid var(--cl-border-light, #F0EDE8); }
.ag-cat-item { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: .82rem; cursor: pointer; }
.ag-cat-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.ag-cat-item.muted { opacity: .35; }

/* Upcoming */
.ag-upcoming { flex: 1; overflow-y: auto; padding: 12px 16px; border-top: 1px solid var(--cl-border-light, #F0EDE8); }
.ag-upcoming-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin-bottom: 8px; }
.ag-up-item { display: flex; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); cursor: pointer; }
.ag-up-item:last-child { border: none; }
.ag-up-dot { width: 4px; border-radius: 2px; flex-shrink: 0; margin-top: 2px; }
.ag-up-time { font-size: .7rem; color: var(--cl-text-muted); white-space: nowrap; }
.ag-up-title { font-size: .82rem; font-weight: 600; }

/* ── Main calendar ── */
.ag-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--cl-bg, #F7F5F2); }
.ag-toolbar { display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: var(--cl-surface, #fff); border-bottom: 1.5px solid var(--cl-border-light, #F0EDE8); flex-shrink: 0; }
.ag-toolbar-title { font-weight: 700; font-size: 1.1rem; min-width: 200px; }
.ag-view-tabs { display: flex; gap: 2px; background: var(--cl-bg); border-radius: 10px; padding: 3px; }
.ag-view-tab { padding: 5px 14px; border-radius: 8px; font-size: .78rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--cl-text-muted); transition: all .15s; }
.ag-view-tab.active { background: var(--cl-surface, #fff); color: var(--cl-text); box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.ag-nav-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--cl-text-muted); transition: all .12s; font-size: .95rem; }
.ag-nav-btn:hover { background: var(--cl-surface); color: var(--cl-text); }
.ag-today-btn { padding: 5px 14px; border-radius: 8px; font-size: .78rem; font-weight: 600; cursor: pointer; border: 1.5px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text); }
.ag-today-btn:hover { border-color: #2d4a43; color: #2d4a43; }

/* ── Search ── */
.ag-search { position: relative; margin-left: auto; }
.ag-search input { width: 200px; padding: 6px 12px 6px 32px; border: 1.5px solid var(--cl-border-light, #F0EDE8); border-radius: 10px; font-size: .82rem; background: var(--cl-bg); transition: all .2s; }
.ag-search input:focus { width: 280px; border-color: #bcd2cb; outline: none; background: var(--cl-surface); }
.ag-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .82rem; color: var(--cl-text-muted); }

/* ── Month grid ── */
.ag-month { flex: 1; display: grid; grid-template-columns: repeat(7, 1fr); grid-template-rows: auto; overflow-y: auto; }
.ag-month-head { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 8px; text-align: center; background: var(--cl-surface); border-bottom: 1px solid var(--cl-border-light, #F0EDE8); position: sticky; top: 0; z-index: 1; }
.ag-day {
    min-height: 100px; padding: 4px 6px; border-right: 1px solid var(--cl-border-light, #F0EDE8);
    border-bottom: 1px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff);
    cursor: pointer; transition: background .1s; position: relative; overflow: hidden;
}
.ag-day:nth-child(7n) { border-right: none; }
.ag-day:hover { background: rgba(188,210,203,.08); }
.ag-day.other { background: var(--cl-bg, #F7F5F2); opacity: .5; }
.ag-day.today { background: rgba(188,210,203,.12); }
.ag-day.today .ag-day-num { background: #2d4a43; color: #fff; }
.ag-day-num { font-size: .78rem; font-weight: 600; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin-bottom: 2px; }
.ag-day-events { display: flex; flex-direction: column; gap: 2px; }

.ag-ev {
    padding: 2px 6px; border-radius: 4px; font-size: .68rem; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer;
    transition: transform .1s, box-shadow .1s; line-height: 1.4;
}
.ag-ev:hover { transform: scale(1.02); box-shadow: 0 2px 6px rgba(0,0,0,.12); z-index: 1; position: relative; }
.ag-ev-more { font-size: .65rem; color: var(--cl-text-muted); padding: 1px 6px; cursor: pointer; font-weight: 600; }
.ag-ev-more:hover { color: var(--cl-text); }

/* ── Week view ── */
.ag-week { flex: 1; display: grid; grid-template-columns: 50px repeat(7, 1fr); overflow-y: auto; }
.ag-week-head { font-size: .7rem; font-weight: 700; text-align: center; padding: 8px 4px; background: var(--cl-surface); border-bottom: 1px solid var(--cl-border-light); position: sticky; top: 0; z-index: 1; }
.ag-week-head.today { color: #2d4a43; }
.ag-week-head-num { font-size: 1.1rem; font-weight: 700; }
.ag-week-hour { font-size: .65rem; color: var(--cl-text-muted); text-align: right; padding: 0 6px; height: 48px; border-right: 1px solid var(--cl-border-light); position: relative; }
.ag-week-cell { height: 48px; border-right: 1px solid var(--cl-border-light, #F0EDE8); border-bottom: 1px solid rgba(0,0,0,.03); position: relative; cursor: pointer; }
.ag-week-cell:hover { background: rgba(188,210,203,.06); }
.ag-week-ev {
    position: absolute; left: 2px; right: 2px; border-radius: 4px; padding: 2px 5px;
    font-size: .68rem; font-weight: 600; overflow: hidden; cursor: pointer; z-index: 2;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}

/* ── Day view ── */
.ag-day-view { flex: 1; display: grid; grid-template-columns: 60px 1fr; overflow-y: auto; }
.ag-dv-hour { font-size: .72rem; color: var(--cl-text-muted); text-align: right; padding: 0 8px; height: 60px; border-right: 1.5px solid var(--cl-border-light); }
.ag-dv-cell { height: 60px; border-bottom: 1px solid rgba(0,0,0,.03); position: relative; cursor: pointer; }
.ag-dv-cell:hover { background: rgba(188,210,203,.06); }
.ag-dv-ev {
    position: absolute; left: 4px; right: 4px; border-radius: 6px; padding: 6px 10px;
    font-size: .82rem; font-weight: 600; cursor: pointer; z-index: 2;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}

/* ── Event modal ── */
.ag-color-pick { display: flex; gap: 6px; flex-wrap: wrap; }
.ag-color-opt { width: 28px; height: 28px; border-radius: 50%; cursor: pointer; border: 2.5px solid transparent; transition: all .15s; }
.ag-color-opt:hover { transform: scale(1.15); }
.ag-color-opt.active { border-color: var(--cl-text); box-shadow: 0 0 0 2px var(--cl-surface), 0 0 0 4px var(--cl-text); }
.ag-participant-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--cl-border-light); }
.ag-participant-row:last-child { border: none; }
.ag-participant-del { background: none; border: none; color: var(--cl-text-muted); cursor: pointer; }
.ag-participant-del:hover { color: #C53030; }

/* ── Responsive ── */
@media (max-width: 992px) {
    .ag-side { display: none; }
}
@media (max-width: 576px) {
    .ag-shell { margin: -12px -12px; }
    .ag-day { min-height: 60px; }
    .ag-toolbar { flex-wrap: wrap; padding: 8px 12px; }
}
</style>

<div class="ag-shell">
  <!-- Sidebar -->
  <div class="ag-side">
    <div class="ag-side-header">
      <button class="btn btn-primary btn-sm w-100" id="agNewBtn"><i class="bi bi-plus-lg"></i> Nouveau</button>
    </div>
    <div class="ag-mini-cal" id="agMiniCal"></div>
    <div class="ag-cats" id="agCats"></div>
    <div class="ag-upcoming">
      <div class="ag-upcoming-title">Prochains événements</div>
      <div id="agUpcoming"></div>
    </div>
  </div>

  <!-- Main -->
  <div class="ag-main">
    <div class="ag-toolbar">
      <button class="ag-today-btn" id="agTodayBtn">Aujourd'hui</button>
      <button class="ag-nav-btn" id="agPrevBtn"><i class="bi bi-chevron-left"></i></button>
      <button class="ag-nav-btn" id="agNextBtn"><i class="bi bi-chevron-right"></i></button>
      <div class="ag-toolbar-title" id="agTitle"></div>
      <div class="ag-view-tabs">
        <button class="ag-view-tab" data-view="day">Jour</button>
        <button class="ag-view-tab active" data-view="month">Mois</button>
        <button class="ag-view-tab" data-view="week">Semaine</button>
      </div>
      <div class="ag-search">
        <i class="bi bi-search"></i>
        <input type="text" id="agSearch" placeholder="Rechercher un événement...">
      </div>
    </div>
    <div id="agCalBody"></div>
  </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="agEventModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="agModalTitle"><i class="bi bi-calendar-plus me-2"></i>Nouvel événement</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <input type="hidden" id="agEvId">
        <div class="mb-3"><label class="form-label small fw-bold">Titre *</label><input class="form-control form-control-sm" id="agEvTitle" maxlength="255" placeholder="Réunion d'équipe..."></div>
        <div class="row mb-3">
          <div class="col"><label class="form-label small fw-bold">Catégorie</label>
            <select class="form-select form-select-sm" id="agEvCat">
              <option value="rdv">Rendez-vous</option>
              <option value="reunion">Réunion</option>
              <option value="rappel">Rappel</option>
              <option value="personnel">Personnel</option>
              <option value="medical">Médical</option>
              <option value="formation">Formation</option>
              <option value="autre">Autre</option>
            </select>
          </div>
          <div class="col"><label class="form-label small fw-bold">Couleur</label>
            <div class="ag-color-pick" id="agEvColorPick">
              <div class="ag-color-opt active" data-color="#2d4a43" style="background:#2d4a43"></div>
              <div class="ag-color-opt" data-color="#3B4F6B" style="background:#3B4F6B"></div>
              <div class="ag-color-opt" data-color="#7B3B2C" style="background:#7B3B2C"></div>
              <div class="ag-color-opt" data-color="#6B5B3E" style="background:#6B5B3E"></div>
              <div class="ag-color-opt" data-color="#5B4B6B" style="background:#5B4B6B"></div>
              <div class="ag-color-opt" data-color="#1565C0" style="background:#1565C0"></div>
              <div class="ag-color-opt" data-color="#C53030" style="background:#C53030"></div>
              <div class="ag-color-opt" data-color="#2E7D32" style="background:#2E7D32"></div>
            </div>
          </div>
        </div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="agEvAllDay"><label class="form-check-label small" for="agEvAllDay">Journée entière</label></div>
        <div class="row mb-3">
          <div class="col"><label class="form-label small fw-bold">Début *</label><input type="datetime-local" class="form-control form-control-sm" id="agEvStart"></div>
          <div class="col"><label class="form-label small fw-bold">Fin</label><input type="datetime-local" class="form-control form-control-sm" id="agEvEnd"></div>
        </div>
        <div class="mb-3"><label class="form-label small fw-bold">Lieu</label><input class="form-control form-control-sm" id="agEvLocation" placeholder="Salle de réunion, bureau..."></div>
        <div class="mb-3"><label class="form-label small fw-bold">Description</label><textarea class="form-control form-control-sm" id="agEvDesc" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label small fw-bold">Notes privées</label><textarea class="form-control form-control-sm" id="agEvNotes" rows="2" placeholder="Notes personnelles..."></textarea></div>
        <div class="row mb-3">
          <div class="col"><label class="form-label small fw-bold">Récurrence</label>
            <select class="form-select form-select-sm" id="agEvRecurrence">
              <option value="none">Aucune</option><option value="daily">Quotidien</option><option value="weekly">Hebdomadaire</option><option value="biweekly">Bi-hebdomadaire</option><option value="monthly">Mensuel</option><option value="yearly">Annuel</option>
            </select>
          </div>
          <div class="col"><label class="form-label small fw-bold">Rappel</label>
            <select class="form-select form-select-sm" id="agEvReminder">
              <option value="0">Aucun</option><option value="5">5 min</option><option value="10">10 min</option><option value="15" selected>15 min</option><option value="30">30 min</option><option value="60">1 heure</option><option value="1440">1 jour</option>
            </select>
          </div>
        </div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="agEvPrivate"><label class="form-check-label small" for="agEvPrivate">Événement privé</label></div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Participants</label>
          <div class="d-flex gap-2 mb-2">
            <div class="zs-select flex-grow-1" id="agEvParticipantSelect" data-placeholder="Ajouter un collaborateur..."></div>
            <button class="btn btn-sm btn-outline-secondary" id="agAddParticipant"><i class="bi bi-plus"></i></button>
          </div>
          <div class="d-flex gap-2 mb-2">
            <input class="form-control form-control-sm" id="agExtName" placeholder="Nom externe" style="flex:1">
            <input class="form-control form-control-sm" id="agExtEmail" placeholder="Email" style="flex:1">
            <button class="btn btn-sm btn-outline-secondary" id="agAddExtParticipant"><i class="bi bi-plus"></i></button>
          </div>
          <div id="agParticipantsList"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-danger btn-sm d-none" id="agEvDeleteBtn"><i class="bi bi-trash3"></i></button>
        <button class="btn btn-primary" id="agEvSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const JOURS = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    const JOURS_FULL = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const CAT_COLORS = { rdv:'#2d4a43', reunion:'#3B4F6B', rappel:'#6B5B3E', personnel:'#5B4B6B', medical:'#7B3B2C', formation:'#1565C0', autre:'#999' };
    const CAT_LABELS = { rdv:'Rendez-vous', reunion:'Réunion', rappel:'Rappel', personnel:'Personnel', medical:'Médical', formation:'Formation', autre:'Autre' };

    let currentDate = new Date();
    let currentView = 'month';
    let allEvents = [];
    let allContacts = [];
    let participants = [];
    let selectedColor = '#2d4a43';
    let hiddenCats = new Set();

    // ── Load contacts ──
    async function loadContacts() {
        const r = await adminApiPost('admin_get_agenda_contacts');
        if (r.success) allContacts = r.contacts || [];
        const opts = allContacts.map(c => ({ value: c.id, label: c.prenom + ' ' + c.nom + (c.fonction_nom ? ' — ' + c.fonction_nom : '') }));
        zerdaSelect.init(document.getElementById('agEvParticipantSelect'), opts, { search: true });
    }
    loadContacts();

    // ── Load events ──
    async function loadEvents() {
        const range = getViewRange();
        const r = await adminApiPost('admin_get_agenda_events', { start: fmt(range.start), end: fmt(range.end) });
        if (r.success) allEvents = r.events || [];
        render();
        renderMiniCal();
        renderUpcoming();
    }

    function getViewRange() {
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        if (currentView === 'month') {
            const start = new Date(y, m, 1); start.setDate(start.getDate() - start.getDay());
            const end = new Date(y, m + 1, 0); end.setDate(end.getDate() + (6 - end.getDay()));
            return { start, end };
        }
        if (currentView === 'week') {
            const start = new Date(currentDate); start.setDate(start.getDate() - start.getDay());
            const end = new Date(start); end.setDate(end.getDate() + 6);
            return { start, end };
        }
        return { start: new Date(currentDate), end: new Date(currentDate) };
    }

    function fmt(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
    function isToday(d) { const t = new Date(); return d.getFullYear()===t.getFullYear() && d.getMonth()===t.getMonth() && d.getDate()===t.getDate(); }

    // ── Render ──
    function render() {
        updateTitle();
        if (currentView === 'month') renderMonth();
        else if (currentView === 'week') renderWeek();
        else renderDay();
        renderCats();
    }

    function updateTitle() {
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        if (currentView === 'month') document.getElementById('agTitle').textContent = MOIS[m] + ' ' + y;
        else if (currentView === 'week') {
            const start = new Date(currentDate); start.setDate(start.getDate() - start.getDay());
            const end = new Date(start); end.setDate(end.getDate() + 6);
            document.getElementById('agTitle').textContent = start.getDate() + ' ' + MOIS[start.getMonth()].slice(0,3) + ' — ' + end.getDate() + ' ' + MOIS[end.getMonth()].slice(0,3) + ' ' + y;
        } else {
            document.getElementById('agTitle').textContent = JOURS_FULL[currentDate.getDay()] + ' ' + currentDate.getDate() + ' ' + MOIS[m] + ' ' + y;
        }
    }

    function visibleEvents() { return allEvents.filter(e => !hiddenCats.has(e.category)); }

    function eventsForDate(dateStr) {
        return visibleEvents().filter(e => {
            const s = e.start_at.slice(0,10);
            const en = e.end_at ? e.end_at.slice(0,10) : s;
            return dateStr >= s && dateStr <= en;
        });
    }

    function evColor(e) { return e.color || CAT_COLORS[e.category] || '#2d4a43'; }

    function renderEventChip(e) {
        const c = evColor(e);
        const time = e.all_day == 1 ? '' : new Date(e.start_at).toLocaleTimeString('fr-CH',{hour:'2-digit',minute:'2-digit'}) + ' ';
        return '<div class="ag-ev" style="background:' + c + '20;color:' + c + ';border-left:3px solid ' + c + '" data-ev-id="' + e.id + '">' + time + escapeHtml(e.title) + '</div>';
    }

    // ── Month ──
    function renderMonth() {
        const body = document.getElementById('agCalBody');
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        const firstDay = new Date(y, m, 1);
        const startDate = new Date(firstDay); startDate.setDate(startDate.getDate() - startDate.getDay());

        let h = '<div class="ag-month">';
        JOURS.forEach(j => h += '<div class="ag-month-head">' + j + '</div>');

        for (let i = 0; i < 42; i++) {
            const d = new Date(startDate); d.setDate(d.getDate() + i);
            const ds = fmt(d);
            const isOther = d.getMonth() !== m;
            const cls = (isOther ? ' other' : '') + (isToday(d) ? ' today' : '');
            const dayEvents = eventsForDate(ds);
            h += '<div class="ag-day' + cls + '" data-date="' + ds + '"><div class="ag-day-num">' + d.getDate() + '</div><div class="ag-day-events">';
            dayEvents.slice(0, 3).forEach(e => h += renderEventChip(e));
            if (dayEvents.length > 3) h += '<div class="ag-ev-more">+' + (dayEvents.length - 3) + ' autres</div>';
            h += '</div></div>';
        }
        h += '</div>';
        body.innerHTML = h;

        body.querySelectorAll('[data-ev-id]').forEach(el => el.addEventListener('click', e => { e.stopPropagation(); openEvent(el.dataset.evId); }));
        body.querySelectorAll('.ag-day').forEach(el => el.addEventListener('click', () => { if (el.dataset.date) openNew(el.dataset.date); }));
    }

    // ── Week ──
    function renderWeek() {
        const body = document.getElementById('agCalBody');
        const start = new Date(currentDate); start.setDate(start.getDate() - start.getDay());
        let h = '<div class="ag-week"><div class="ag-week-head"></div>';
        for (let d = 0; d < 7; d++) {
            const day = new Date(start); day.setDate(day.getDate() + d);
            h += '<div class="ag-week-head' + (isToday(day)?' today':'') + '"><div>' + JOURS[d] + '</div><div class="ag-week-head-num">' + day.getDate() + '</div></div>';
        }
        for (let hr = 0; hr < 24; hr++) {
            h += '<div class="ag-week-hour"><span style="position:absolute;top:-6px;right:6px">' + String(hr).padStart(2,'0') + ':00</span></div>';
            for (let d = 0; d < 7; d++) {
                const day = new Date(start); day.setDate(day.getDate() + d);
                const ds = fmt(day);
                h += '<div class="ag-week-cell" data-date="' + ds + '" data-hour="' + hr + '"></div>';
            }
        }
        h += '</div>';
        body.innerHTML = h;

        // Place events
        visibleEvents().forEach(e => {
            if (e.all_day == 1) return;
            const eStart = new Date(e.start_at);
            const eEnd = e.end_at ? new Date(e.end_at) : new Date(eStart.getTime() + 3600000);
            const ds = fmt(eStart);
            const hr = eStart.getHours();
            const cell = body.querySelector('.ag-week-cell[data-date="' + ds + '"][data-hour="' + hr + '"]');
            if (!cell) return;
            const duration = Math.max(1, (eEnd - eStart) / 3600000);
            const top = (eStart.getMinutes() / 60) * 48;
            const height = Math.max(20, duration * 48 - 2);
            const c = evColor(e);
            const el = document.createElement('div');
            el.className = 'ag-week-ev';
            el.style.cssText = 'top:' + top + 'px;height:' + height + 'px;background:' + c + '20;color:' + c + ';border-left:3px solid ' + c;
            el.dataset.evId = e.id;
            el.textContent = e.title;
            el.addEventListener('click', ev => { ev.stopPropagation(); openEvent(e.id); });
            cell.appendChild(el);
        });

        body.querySelectorAll('.ag-week-cell').forEach(el => el.addEventListener('click', () => {
            if (el.dataset.date) openNew(el.dataset.date, el.dataset.hour);
        }));
    }

    // ── Day ──
    function renderDay() {
        const body = document.getElementById('agCalBody');
        const ds = fmt(currentDate);
        let h = '<div class="ag-day-view">';
        for (let hr = 0; hr < 24; hr++) {
            h += '<div class="ag-dv-hour"><span>' + String(hr).padStart(2,'0') + ':00</span></div>';
            h += '<div class="ag-dv-cell" data-date="' + ds + '" data-hour="' + hr + '"></div>';
        }
        h += '</div>';
        body.innerHTML = h;

        visibleEvents().filter(e => eventsForDate(ds).includes(e)).forEach(e => {
            if (e.all_day == 1) return;
            const eStart = new Date(e.start_at);
            const eEnd = e.end_at ? new Date(e.end_at) : new Date(eStart.getTime() + 3600000);
            const hr = eStart.getHours();
            const cell = body.querySelector('.ag-dv-cell[data-date="' + ds + '"][data-hour="' + hr + '"]');
            if (!cell) return;
            const duration = Math.max(1, (eEnd - eStart) / 3600000);
            const top = (eStart.getMinutes() / 60) * 60;
            const height = Math.max(24, duration * 60 - 2);
            const c = evColor(e);
            const el = document.createElement('div');
            el.className = 'ag-dv-ev';
            el.style.cssText = 'top:' + top + 'px;height:' + height + 'px;background:' + c + '20;color:' + c + ';border-left:4px solid ' + c;
            el.dataset.evId = e.id;
            el.innerHTML = '<strong>' + escapeHtml(e.title) + '</strong><br><small>' + eStart.toLocaleTimeString('fr-CH',{hour:'2-digit',minute:'2-digit'}) + (e.location ? ' · ' + escapeHtml(e.location) : '') + '</small>';
            el.addEventListener('click', ev => { ev.stopPropagation(); openEvent(e.id); });
            cell.appendChild(el);
        });

        body.querySelectorAll('.ag-dv-cell').forEach(el => el.addEventListener('click', () => openNew(el.dataset.date, el.dataset.hour)));
    }

    // ── Mini calendar ──
    function renderMiniCal() {
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        const firstDay = new Date(y, m, 1);
        const startDate = new Date(firstDay); startDate.setDate(startDate.getDate() - startDate.getDay());
        const todayStr = fmt(new Date());
        const selStr = fmt(currentDate);

        let h = '<div class="ag-mini-nav"><button id="agMiniPrev"><i class="bi bi-chevron-left"></i></button><span>' + MOIS[m].slice(0,3) + ' ' + y + '</span><button id="agMiniNext"><i class="bi bi-chevron-right"></i></button></div>';
        h += '<table><tr>';
        ['D','L','M','M','J','V','S'].forEach(j => h += '<th>' + j + '</th>');
        h += '</tr>';
        for (let i = 0; i < 42; i++) {
            if (i % 7 === 0) h += '<tr>';
            const d = new Date(startDate); d.setDate(d.getDate() + i);
            const ds = fmt(d);
            const hasEv = allEvents.some(e => e.start_at.slice(0,10) === ds);
            let cls = '';
            if (d.getMonth() !== m) cls += ' other-month';
            if (ds === todayStr) cls += ' today';
            if (ds === selStr) cls += ' selected';
            if (hasEv) cls += ' has-event';
            h += '<td class="' + cls.trim() + '" data-mini-date="' + ds + '">' + d.getDate() + '</td>';
            if (i % 7 === 6) h += '</tr>';
        }
        h += '</table>';
        document.getElementById('agMiniCal').innerHTML = h;

        document.querySelectorAll('[data-mini-date]').forEach(el => el.addEventListener('click', () => {
            currentDate = new Date(el.dataset.miniDate + 'T12:00:00');
            currentView = 'day';
            updateViewTabs();
            loadEvents();
        }));
    }

    // ── Categories ──
    function renderCats() {
        const el = document.getElementById('agCats');
        el.innerHTML = Object.entries(CAT_LABELS).map(([k,v]) =>
            '<div class="ag-cat-item' + (hiddenCats.has(k) ? ' muted' : '') + '" data-cat="' + k + '"><div class="ag-cat-dot" style="background:' + CAT_COLORS[k] + '"></div>' + v + '</div>'
        ).join('');
        el.querySelectorAll('[data-cat]').forEach(c => c.addEventListener('click', () => {
            if (hiddenCats.has(c.dataset.cat)) hiddenCats.delete(c.dataset.cat); else hiddenCats.add(c.dataset.cat);
            render();
        }));
    }

    // ── Upcoming ──
    function renderUpcoming() {
        const now = new Date();
        const upcoming = allEvents.filter(e => new Date(e.start_at) >= now).slice(0, 8);
        const el = document.getElementById('agUpcoming');
        if (!upcoming.length) { el.innerHTML = '<p class="text-muted small text-center py-2">Aucun événement à venir</p>'; return; }
        el.innerHTML = upcoming.map(e => {
            const d = new Date(e.start_at);
            const time = e.all_day == 1 ? 'Journée' : d.toLocaleTimeString('fr-CH',{hour:'2-digit',minute:'2-digit'});
            const date = d.toLocaleDateString('fr-CH',{day:'numeric',month:'short'});
            return '<div class="ag-up-item" data-ev-id="' + e.id + '"><div class="ag-up-dot" style="background:' + evColor(e) + ';height:100%"></div><div><div class="ag-up-title">' + escapeHtml(e.title) + '</div><div class="ag-up-time">' + date + ' · ' + time + '</div></div></div>';
        }).join('');
        el.querySelectorAll('[data-ev-id]').forEach(el => el.addEventListener('click', () => openEvent(el.dataset.evId)));
    }

    // ── Toolbar ──
    document.querySelectorAll('.ag-view-tab').forEach(t => t.addEventListener('click', () => {
        currentView = t.dataset.view;
        updateViewTabs();
        loadEvents();
    }));
    function updateViewTabs() {
        document.querySelectorAll('.ag-view-tab').forEach(t => t.classList.toggle('active', t.dataset.view === currentView));
    }

    document.getElementById('agTodayBtn').addEventListener('click', () => { currentDate = new Date(); loadEvents(); });
    document.getElementById('agPrevBtn').addEventListener('click', () => { navigate(-1); });
    document.getElementById('agNextBtn').addEventListener('click', () => { navigate(1); });

    function navigate(dir) {
        if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() + dir);
        else if (currentView === 'week') currentDate.setDate(currentDate.getDate() + dir * 7);
        else currentDate.setDate(currentDate.getDate() + dir);
        loadEvents();
    }

    // ── Search ──
    let searchTO;
    document.getElementById('agSearch').addEventListener('input', function() {
        clearTimeout(searchTO);
        searchTO = setTimeout(async () => {
            if (this.value.length < 2) { loadEvents(); return; }
            const r = await adminApiPost('admin_search_agenda', { q: this.value });
            if (r.success) { allEvents = r.results || []; render(); }
        }, 300);
    });

    // ── Event modal ──
    function openNew(date, hour) {
        document.getElementById('agEvId').value = '';
        document.getElementById('agEvTitle').value = '';
        document.getElementById('agEvCat').value = 'rdv';
        document.getElementById('agEvAllDay').checked = false;
        document.getElementById('agEvStart').value = date + 'T' + String(hour || 9).padStart(2,'0') + ':00';
        document.getElementById('agEvEnd').value = date + 'T' + String((parseInt(hour||9)+1) % 24).padStart(2,'0') + ':00';
        document.getElementById('agEvLocation').value = '';
        document.getElementById('agEvDesc').value = '';
        document.getElementById('agEvNotes').value = '';
        document.getElementById('agEvRecurrence').value = 'none';
        document.getElementById('agEvReminder').value = '15';
        document.getElementById('agEvPrivate').checked = false;
        selectedColor = '#2d4a43';
        updateColorPick();
        participants = [];
        renderParticipants();
        document.getElementById('agModalTitle').innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Nouvel événement';
        document.getElementById('agEvDeleteBtn').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('agEventModal')).show();
    }

    function openEvent(id) {
        const e = allEvents.find(x => x.id === id);
        if (!e) return;
        document.getElementById('agEvId').value = e.id;
        document.getElementById('agEvTitle').value = e.title;
        document.getElementById('agEvCat').value = e.category;
        document.getElementById('agEvAllDay').checked = e.all_day == 1;
        document.getElementById('agEvStart').value = e.start_at.replace(' ', 'T').slice(0,16);
        document.getElementById('agEvEnd').value = e.end_at ? e.end_at.replace(' ', 'T').slice(0,16) : '';
        document.getElementById('agEvLocation').value = e.location || '';
        document.getElementById('agEvDesc').value = e.description || '';
        document.getElementById('agEvNotes').value = e.notes || '';
        document.getElementById('agEvRecurrence').value = e.recurrence || 'none';
        document.getElementById('agEvReminder').value = String(e.reminder_minutes || 15);
        document.getElementById('agEvPrivate').checked = e.is_private == 1;
        selectedColor = e.color || '#2d4a43';
        updateColorPick();
        participants = (e.participants || []).map(p => ({
            user_id: p.user_id, external_name: p.external_name, external_email: p.external_email,
            label: p.user_id ? (p.prenom + ' ' + p.nom) : (p.external_name + ' (' + p.external_email + ')'), status: p.status
        }));
        renderParticipants();
        document.getElementById('agModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier l\'événement';
        document.getElementById('agEvDeleteBtn').classList.remove('d-none');
        new bootstrap.Modal(document.getElementById('agEventModal')).show();
    }

    // Color pick
    document.getElementById('agEvColorPick').addEventListener('click', e => {
        const opt = e.target.closest('.ag-color-opt');
        if (!opt) return;
        selectedColor = opt.dataset.color;
        updateColorPick();
    });
    function updateColorPick() {
        document.querySelectorAll('.ag-color-opt').forEach(o => o.classList.toggle('active', o.dataset.color === selectedColor));
    }

    // Participants
    document.getElementById('agAddParticipant').addEventListener('click', () => {
        const uid = zerdaSelect.getValue('#agEvParticipantSelect');
        if (!uid) return;
        if (participants.some(p => p.user_id === uid)) return;
        const c = allContacts.find(x => x.id === uid);
        if (c) participants.push({ user_id: uid, label: c.prenom + ' ' + c.nom });
        zerdaSelect.setValue('#agEvParticipantSelect', '');
        renderParticipants();
    });
    document.getElementById('agAddExtParticipant').addEventListener('click', () => {
        const name = document.getElementById('agExtName').value.trim();
        const email = document.getElementById('agExtEmail').value.trim();
        if (!name) return;
        participants.push({ external_name: name, external_email: email, label: name + (email ? ' (' + email + ')' : '') });
        document.getElementById('agExtName').value = '';
        document.getElementById('agExtEmail').value = '';
        renderParticipants();
    });
    function renderParticipants() {
        document.getElementById('agParticipantsList').innerHTML = participants.map((p, i) =>
            '<div class="ag-participant-row"><i class="bi bi-' + (p.user_id ? 'person' : 'person-add') + '"></i><span class="small flex-grow-1">' + escapeHtml(p.label) + '</span><button class="ag-participant-del" data-rm="' + i + '"><i class="bi bi-x"></i></button></div>'
        ).join('');
        document.querySelectorAll('[data-rm]').forEach(b => b.addEventListener('click', () => { participants.splice(parseInt(b.dataset.rm), 1); renderParticipants(); }));
    }

    // Save
    document.getElementById('agEvSaveBtn').addEventListener('click', async () => {
        const title = document.getElementById('agEvTitle').value.trim();
        if (!title) { showToast('Titre requis', 'error'); return; }
        const data = {
            title, category: document.getElementById('agEvCat').value, color: selectedColor,
            all_day: document.getElementById('agEvAllDay').checked ? 1 : 0,
            start_at: document.getElementById('agEvStart').value.replace('T', ' '),
            end_at: document.getElementById('agEvEnd').value ? document.getElementById('agEvEnd').value.replace('T', ' ') : null,
            location: document.getElementById('agEvLocation').value, description: document.getElementById('agEvDesc').value,
            notes: document.getElementById('agEvNotes').value, recurrence: document.getElementById('agEvRecurrence').value,
            reminder_minutes: parseInt(document.getElementById('agEvReminder').value),
            is_private: document.getElementById('agEvPrivate').checked ? 1 : 0,
            participants
        };
        const id = document.getElementById('agEvId').value;
        const r = id
            ? await adminApiPost('admin_update_agenda_event', { id, ...data })
            : await adminApiPost('admin_create_agenda_event', data);
        if (r.success) {
            showToast(r.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('agEventModal'))?.hide();
            loadEvents();
        } else showToast(r.message || 'Erreur', 'error');
    });

    // Delete
    document.getElementById('agEvDeleteBtn').addEventListener('click', async () => {
        const id = document.getElementById('agEvId').value;
        if (!id) return;
        if (!await adminConfirm({ title: 'Supprimer', text: 'Supprimer cet événement ?', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer' })) return;
        const r = await adminApiPost('admin_delete_agenda_event', { id });
        if (r.success) {
            showToast('Supprimé', 'success');
            bootstrap.Modal.getInstance(document.getElementById('agEventModal'))?.hide();
            loadEvents();
        }
    });

    // ── Keyboard shortcuts ──
    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (e.key === 't' || e.key === 'T') { currentDate = new Date(); loadEvents(); }
        if (e.key === 'n' || e.key === 'N') { openNew(fmt(currentDate), 9); }
        if (e.key === 'm' || e.key === 'M') { currentView = 'month'; updateViewTabs(); loadEvents(); }
        if (e.key === 'w' || e.key === 'W') { currentView = 'week'; updateViewTabs(); loadEvents(); }
        if (e.key === 'd' || e.key === 'D') { currentView = 'day'; updateViewTabs(); loadEvents(); }
        if (e.key === 'ArrowLeft') navigate(-1);
        if (e.key === 'ArrowRight') navigate(1);
    });

    // ── Init ──
    loadEvents();
})();
</script>
