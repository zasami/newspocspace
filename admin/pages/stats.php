<?php
// ─── Données serveur (SSR) ───────────────────────────────────────────────────
$statTotalUsers = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
?>
<style>
/* ── Stat cards (large, top) ── */
.st-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.st-stat-card {
    flex:1; min-width:140px; text-align:center; padding:20px 14px;
    border-radius:16px; border:1.5px solid var(--cl-border-light,#F0EDE8);
    background:var(--cl-surface,#fff); transition:box-shadow .15s;
}
.st-stat-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.04); }
.st-stat-icon { width:44px;height:44px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:10px; }
.st-stat-val { font-size:1.8rem;font-weight:700;line-height:1.1; }
.st-stat-lbl { font-size:.72rem;color:var(--cl-text-muted);margin-top:4px; }

/* ── Period nav ── */
.st-period-nav { display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;padding:10px 16px;border-radius:14px;background:var(--cl-surface,#fff);border:1.5px solid var(--cl-border-light,#F0EDE8); }
.st-period-tabs { display:flex;gap:4px;background:var(--cl-bg,#F7F5F2);border-radius:20px;padding:3px; }
.st-period-tab { border:none;background:none;padding:5px 16px;border-radius:18px;font-size:.78rem;font-weight:600;color:var(--cl-text-muted);cursor:pointer;transition:all .15s; }
.st-period-tab.active { background:var(--cl-surface,#fff);color:var(--cl-text,#2d2d2d);box-shadow:0 1px 4px rgba(0,0,0,.08); }
.st-date-nav { display:flex;align-items:center;gap:8px;margin-left:auto; }
.st-date-nav button { border:none;background:var(--cl-bg,#F7F5F2);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--cl-text-muted);transition:all .12s; }
.st-date-nav button:hover { background:var(--cl-border-light);color:var(--cl-text); }
.st-date-label { font-size:.88rem;font-weight:600;min-width:140px;text-align:center; }

/* ── Charts ── */
.st-chart-card { border-radius:14px;border:1.5px solid var(--cl-border-light,#F0EDE8);background:var(--cl-surface,#fff);padding:20px; }
.st-compare-btn { border:1.5px solid #D0C4D8;background:none;color:#5B4B6B;padding:5px 14px;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:5px; }
.st-compare-btn:hover { background:#D0C4D8;color:#fff; }
.st-cmp-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px; }
.st-cmp-card { border-radius:14px;border:1.5px solid var(--cl-border-light,#F0EDE8);padding:16px;background:var(--cl-surface,#fff); }
.st-cmp-card-title { font-size:.78rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
.st-cmp-card-title .year { font-size:.68rem;padding:2px 8px;border-radius:10px;font-weight:600; }
.st-cmp-stats { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px; }
.st-cmp-stat { flex:1;min-width:70px;text-align:center;padding:8px 6px;border-radius:10px;background:var(--cl-bg,#F7F5F2); }
.st-cmp-stat .val { font-size:1.1rem;font-weight:700; }
.st-cmp-stat .lbl { font-size:.62rem;color:var(--cl-text-muted);margin-top:1px; }
.st-cmp-chart { height:200px;position:relative; }
.st-cmp-diff { border-radius:14px;padding:16px;border:1.5px solid var(--cl-border-light);background:var(--cl-surface,#fff); }
.st-cmp-diff-title { font-size:.82rem;font-weight:700;margin-bottom:10px; }
.st-cmp-diff-row { display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--cl-border-light);font-size:.85rem; }
.st-cmp-diff-row:last-child { border:none; }
.st-cmp-arrow { font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:3px; }
.st-cmp-up { color:#7B3B2C; }
.st-cmp-down { color:#2d4a43; }
.st-cmp-equal { color:var(--cl-text-muted); }
@media(max-width:768px) { .st-cmp-grid { grid-template-columns:1fr; } }
.st-chart-title { font-size:.82rem;font-weight:700;margin-bottom:12px; }
.st-chart-toggle { display:flex;gap:2px;background:var(--cl-bg,#F7F5F2);border-radius:8px;padding:2px; }
.st-ct-btn { border:none;background:none;width:30px;height:28px;border-radius:6px;cursor:pointer;color:var(--cl-text-muted);font-size:.82rem;display:flex;align-items:center;justify-content:center;transition:all .15s; }
.st-ct-btn:hover { color:var(--cl-text); }
.st-ct-btn.active { background:var(--cl-surface,#fff);color:#2d4a43;box-shadow:0 1px 3px rgba(0,0,0,.08); }
.st-chart-wrap { position:relative;height:220px; }

/* ── Detail button ── */
.st-detail-btn {
    display:inline-flex;align-items:center;gap:8px;padding:10px 24px;border-radius:12px;
    font-weight:600;font-size:.88rem;border:2px solid #2d4a43;background:#bcd2cb;color:#2d4a43;
    cursor:pointer;transition:all .15s;
}
.st-detail-btn:hover { background:#2d4a43;color:#fff; }
.st-detail-btn .bi-chevron-down { transition:transform .2s; }
.st-detail-btn.open .bi-chevron-down { transform:rotate(180deg); }

/* ── Table ── */
.st-table-wrap { border:1.5px solid var(--cl-border-light,#F0EDE8);border-radius:14px;overflow:hidden;background:var(--cl-surface,#fff); }
.st-table { width:100%;border-collapse:collapse; }
.st-table th { font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--cl-text-muted);padding:12px 14px;border-bottom:1.5px solid var(--cl-border-light,#F0EDE8);text-align:left;background:var(--cl-bg,#F7F5F2); }
.st-table th:first-child { border-top-left-radius:14px; }
.st-table th:last-child { border-top-right-radius:14px; }
.st-table td { padding:11px 14px;border-bottom:1px solid var(--cl-border-light,#F0EDE8);vertical-align:middle;font-size:.85rem; }
.st-table tr:last-child td { border-bottom:none; }
.st-table .st-row-click { cursor:pointer;transition:background .12s; }
.st-table .st-row-click:hover td { background:var(--cl-bg,#FAFAF7); }

/* ── Slidedown zone ── */
.st-slidedown { max-height:0; overflow:hidden; transition:max-height .4s ease; }
.st-slidedown.open { max-height:3000px; }

/* ── Badges ── */
.st-badge { font-size:.72rem;padding:3px 10px;border-radius:20px;font-weight:600;display:inline-block; }
.st-badge-vacances { background:#bcd2cb;color:#2d4a43; }
.st-badge-maladie { background:#E2B8AE;color:#7B3B2C; }
.st-badge-accident { background:#D4C4A8;color:#6B5B3E; }
.st-badge-formation { background:#B8C9D4;color:#3B4F6B; }
.st-badge-conge_special { background:#D0C4D8;color:#5B4B6B; }
.st-badge-militaire,.st-badge-autre { background:#E8E4DE;color:#6c757d; }
.st-badge-oui { background:#bcd2cb;color:#2d4a43; }
.st-badge-non { background:#E2B8AE;color:#7B3B2C; }

/* ── Avatar ── */
.st-avatar { width:34px;height:34px;border-radius:50%;object-fit:cover; }
.st-avatar-init { width:34px;height:34px;border-radius:50%;background:#bcd2cb;color:#2d4a43;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700; }

/* ── Modal detail ── */
.st-modal-stats { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px; }
.st-modal-stat { flex:1;min-width:90px;text-align:center;padding:12px 8px;border-radius:12px;border:1px solid var(--cl-border-light); }
.st-modal-stat .val { font-size:1.2rem;font-weight:700; }
.st-modal-stat .lbl { font-size:.66rem;color:var(--cl-text-muted);margin-top:2px; }
.st-vac-alert { background:#FEF3C7;border:1px solid #F59E0B;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;color:#92400E; }
.st-mini-bar { display:flex;gap:3px;margin-top:10px; }
.st-mini-bar-month { width:20px;height:18px;border-radius:4px;background:var(--cl-border-light,#E8E4DE);display:flex;align-items:center;justify-content:center; }
.st-mini-bar-month.active { background:#bcd2cb; }
.st-mini-bar-month span { font-size:.5rem;color:var(--cl-text-muted); }
.st-detail-table { width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:10px; }
.st-detail-table th { padding:8px 10px;background:var(--cl-bg);border-bottom:1px solid var(--cl-border-light);font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cl-text-muted); }
.st-detail-table td { padding:8px 10px;border-bottom:1px solid var(--cl-border-light); }

@media print { .st-no-print { display:none!important; } }
</style>

<h5 class="mb-3"><i class="bi bi-bar-chart-line"></i> Statistiques</h5>

<!-- ═══ Section 1: Stat cards (absence stats - dynamiques) ═══ -->
<div class="st-stats" id="stTopStats">
  <div class="st-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="st-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-people-fill"></i></div>
    <div class="st-stat-val" style="color:#2d4a43" id="stStatAbsents">—</div>
    <div class="st-stat-lbl">Collaborateurs absents</div>
  </div>
  <div class="st-stat-card" style="background:#f0f4f8;border-color:#d4dfe8">
    <div class="st-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-calendar-x"></i></div>
    <div class="st-stat-val" style="color:#3B4F6B" id="stStatJours">—</div>
    <div class="st-stat-lbl">Jours d'absence</div>
  </div>
  <div class="st-stat-card" style="background:#f8f4ed;border-color:#e8dece">
    <div class="st-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-clock"></i></div>
    <div class="st-stat-val" style="color:#6B5B3E" id="stStatHeures">—</div>
    <div class="st-stat-lbl">Heures d'absence</div>
  </div>
  <div class="st-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="st-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check-circle"></i></div>
    <div class="st-stat-val" style="color:#2d4a43" id="stStatJust">—</div>
    <div class="st-stat-lbl">Justifiées</div>
  </div>
  <div class="st-stat-card" style="background:#f8f0ee;border-color:#e8d4ce">
    <div class="st-stat-icon" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-exclamation-circle"></i></div>
    <div class="st-stat-val" style="color:#7B3B2C" id="stStatNonJust">—</div>
    <div class="st-stat-lbl">Non justifiées</div>
  </div>
</div>

<!-- ═══ Section 2: Graphiques avec navigation ═══ -->
<div class="st-period-nav st-no-print" id="stPeriodNav">
  <div class="st-period-tabs">
    <button class="st-period-tab" data-period="week">Semaine</button>
    <button class="st-period-tab active" data-period="month">Mois</button>
    <button class="st-period-tab" data-period="year">Année</button>
  </div>
  <div class="st-date-nav">
    <button id="stPrev" title="Précédent"><i class="bi bi-chevron-left"></i></button>
    <span class="st-date-label" id="stDateLabel">—</span>
    <button id="stNext" title="Suivant"><i class="bi bi-chevron-right"></i></button>
  </div>
  <button class="st-compare-btn" id="stCompareBtn" title="Comparer avec l'année précédente"><i class="bi bi-arrow-left-right"></i> Comparer</button>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="st-chart-card">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="st-chart-title mb-0">Jours d'absence par sous-période</div>
        <div class="st-chart-toggle">
          <button class="st-ct-btn active" data-chart="bar" title="Barres"><i class="bi bi-bar-chart-fill"></i></button>
          <button class="st-ct-btn" data-chart="line" title="Courbe"><i class="bi bi-graph-up"></i></button>
          <button class="st-ct-btn" data-chart="polarArea" title="Polaire"><i class="bi bi-pie-chart-fill"></i></button>
        </div>
      </div>
      <div class="st-chart-wrap"><canvas id="stChartBar"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="st-chart-card"><div class="st-chart-title">Répartition par type</div><div class="st-chart-wrap"><canvas id="stChartDoughnut"></canvas></div></div>
  </div>
</div>

<!-- ═══ Section 3: Bouton détail + slidedown ═══ -->
<div class="text-center my-3 st-no-print">
  <button class="st-detail-btn" id="stToggleDetail"><i class="bi bi-list-ul"></i> Voir les absences détaillées <i class="bi bi-chevron-down"></i></button>
</div>

<div class="st-slidedown" id="stSlidedown">
  <div class="d-flex justify-content-end mb-2 st-no-print">
    <button class="btn btn-sm btn-outline-secondary" id="stPrintBtn"><i class="bi bi-printer"></i> Imprimer</button>
  </div>
  <div id="stDetailBody">
    <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
  </div>
</div>

<!-- ═══ Modal: Détail collaborateur ═══ -->
<div class="modal fade" id="stUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stUserModalTitle">Détail collaborateur</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="stUserModalBody">
        <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Comparaison ═══ -->
<div class="modal fade" id="stCompareModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stCompareTitle"><i class="bi bi-arrow-left-right me-2"></i>Comparaison</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="stCompareBody">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
          <button class="btn btn-sm btn-outline-secondary" id="stCmpPrev" title="Mois précédent"><i class="bi bi-chevron-left"></i></button>
          <span class="fw-bold" id="stCmpLabel" style="min-width:150px;text-align:center;font-size:.95rem"></span>
          <button class="btn btn-sm btn-outline-secondary" id="stCmpNext" title="Mois suivant"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div id="stCompareContent">
          <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?> src="/zerdatime/assets/js/vendor/chart.umd.min.js"></script>
<script<?= nonce() ?>>
(function() {
    let currentPeriod = 'month';
    let currentDate = new Date();
    let stData = null;
    let chartBar = null, chartDoughnut = null;
    let chartType = 'bar';

    const TYPE_COLORS = { vacances:'#bcd2cb', maladie:'#E2B8AE', accident:'#D4C4A8', formation:'#B8C9D4', conge_special:'#D0C4D8', militaire:'#C8C4BE', autre:'#E8E4DE' };
    const TYPE_LABELS = { vacances:'Vacances', maladie:'Maladie', accident:'Accident', formation:'Formation', conge_special:'Congé spécial', militaire:'Militaire', autre:'Autre' };
    const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const MONTHS_SHORT = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

    function formatDateLabel() {
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        if (currentPeriod === 'year') return '' + y;
        if (currentPeriod === 'month') return MONTHS_FR[m] + ' ' + y;
        const d = new Date(currentDate); d.setHours(0,0,0,0); d.setDate(d.getDate()+3-(d.getDay()+6)%7);
        const w = Math.ceil((((d-new Date(d.getFullYear(),0,4))/864e5)+1)/7);
        return 'Sem. ' + w + ' — ' + y;
    }
    function toApiDate() { return currentDate.toISOString().slice(0,10); }
    function navigate(dir) {
        if (currentPeriod === 'week') currentDate.setDate(currentDate.getDate() + dir * 7);
        else if (currentPeriod === 'month') currentDate.setMonth(currentDate.getMonth() + dir);
        else currentDate.setFullYear(currentDate.getFullYear() + dir);
        loadData();
    }

    function loadData() {
        document.getElementById('stDateLabel').textContent = formatDateLabel();
        adminApiPost('admin_get_absence_stats', { period: currentPeriod, date: toApiDate() }).then(r => {
            if (!r.success) return;
            stData = r;
            renderTopStats(r.stats);
            renderCharts(r);
            renderDetailTable(r);
        });
    }

    function renderTopStats(s) {
        document.getElementById('stStatAbsents').textContent = s.total_absents;
        document.getElementById('stStatJours').textContent = s.total_jours;
        document.getElementById('stStatHeures').textContent = s.total_heures;
        document.getElementById('stStatJust').textContent = s.justifiees;
        document.getElementById('stStatNonJust').textContent = s.non_justifiees;
    }

    function renderCharts(data) {
        const labels = (data.chart_data||[]).map(d => d.label);
        const values = (data.chart_data||[]).map(d => d.jours);
        if (chartBar) chartBar.destroy();
        const ctxBar = document.getElementById('stChartBar');
        if (ctxBar) {
            const colors = values.map((_,i) => {
                const palette = ['#bcd2cb','#B8C9D4','#D4C4A8','#D0C4D8','#E2B8AE','#C8C4BE'];
                return palette[i % palette.length];
            });
            const cfg = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } } };
            let dsOpts = {};
            if (chartType === 'bar') {
                dsOpts = { backgroundColor:'rgba(188,210,203,.6)', borderColor:'#2d4a43', borderWidth:1, borderRadius:6, maxBarThickness:40 };
                cfg.scales = { y:{ beginAtZero:true, ticks:{ stepSize:1 } } };
            } else if (chartType === 'line') {
                dsOpts = { borderColor:'#2d4a43', backgroundColor:'rgba(188,210,203,.15)', fill:true, tension:.3, pointRadius:5, pointBackgroundColor:'#2d4a43' };
                cfg.scales = { y:{ beginAtZero:true, ticks:{ stepSize:1 } } };
            } else {
                dsOpts = { backgroundColor:colors, borderWidth:2, borderColor:'#fff' };
                cfg.scales = {};
            }
            chartBar = new Chart(ctxBar, {
                type: chartType,
                data: { labels, datasets: [{ label:'Jours', data:values, ...dsOpts }] },
                options: cfg
            });
        }
        if (chartDoughnut) chartDoughnut.destroy();
        const pt = data.stats.par_type || {};
        const dL=[], dV=[], dC=[];
        for (const [k,v] of Object.entries(pt)) { if (v>0) { dL.push(TYPE_LABELS[k]||k); dV.push(v); dC.push(TYPE_COLORS[k]||'#6c757d'); } }
        const ctxD = document.getElementById('stChartDoughnut');
        if (ctxD) {
            chartDoughnut = new Chart(ctxD, {
                type: 'doughnut',
                data: { labels:dL, datasets:[{ data:dV, backgroundColor:dC, borderWidth:2, borderColor:'#fff' }] },
                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8, font:{ size:11 } } } } }
            });
        }
    }

    function renderDetailTable(data) {
        const el = document.getElementById('stDetailBody');
        const collabs = (data.collaborateurs||[]).sort((a,b) => b.nb_jours_periode - a.nb_jours_periode);
        if (!collabs.length) { el.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-check-circle" style="font-size:2rem;opacity:.2"></i><br>Aucune absence sur cette période</div>'; return; }

        let h = '<div class="st-table-wrap"><table class="st-table"><thead><tr>';
        h += '<th>Collaborateur</th><th>Fonction</th><th>Jours</th><th>Cumulé année</th><th>Justifié</th><th>Type</th>';
        h += '</tr></thead><tbody>';
        collabs.forEach(c => {
            const mainType = getMainType(c.absences);
            const justified = (c.absences||[]).filter(a => a.has_justificatif).length;
            const total = (c.absences||[]).length;
            const initials = ((c.prenom||'')[0]+(c.nom||'')[0]).toUpperCase();
            const avatar = c.photo ? '<img src="'+esc(c.photo)+'" class="st-avatar">' : '<span class="st-avatar-init">'+esc(initials)+'</span>';
            h += '<tr class="st-row-click" data-uid="'+esc(c.id)+'">';
            h += '<td><div class="d-flex align-items-center gap-2">'+avatar+' <strong>'+esc(c.prenom)+' '+esc(c.nom)+'</strong></div></td>';
            h += '<td>'+esc(c.fonction_nom||'-')+'</td>';
            h += '<td><strong>'+c.nb_jours_periode+'</strong></td>';
            h += '<td>'+c.nb_jours_annee+'</td>';
            h += '<td>'+(justified===total ? '<span class="st-badge st-badge-oui">'+justified+'/'+total+'</span>' : '<span class="st-badge st-badge-non">'+justified+'/'+total+'</span>')+'</td>';
            h += '<td>'+typeBadge(mainType)+'</td>';
            h += '</tr>';
        });
        h += '</tbody></table></div>';
        el.innerHTML = h;

        // Clic sur ligne → ouvre modal
        el.querySelectorAll('.st-row-click').forEach(row => {
            row.addEventListener('click', () => {
                const uid = row.dataset.uid;
                const c = collabs.find(x => x.id === uid);
                if (c) openUserModal(c, data);
            });
        });
    }

    function openUserModal(c, data) {
        document.getElementById('stUserModalTitle').textContent = (c.prenom||'') + ' ' + (c.nom||'') + ' — Absences';
        const totalWorkDays = businessDays(data.date_debut, data.date_fin);
        const tauxAbs = totalWorkDays > 0 ? Math.round((c.nb_jours_periode / totalWorkDays) * 1000) / 10 : 0;
        const totalHours = Math.round(c.nb_jours_periode * 7.6 * 10) / 10;

        let h = '';
        // Stats
        h += '<div class="st-modal-stats">';
        h += modalStat(c.nb_jours_periode, 'Jours (période)', '#f0f6f4', '#2d4a43');
        h += modalStat(c.nb_jours_annee, 'Jours (année)', '#f0f4f8', '#3B4F6B');
        h += modalStat(totalHours+'h', 'Heures cumulées', '#f8f4ed', '#6B5B3E');
        h += modalStat(tauxAbs+'%', "Taux d'absence", '#f8f0ee', '#7B3B2C');
        h += '</div>';

        // Vacances adjacentes
        if (c.vacances_adjacentes?.length) {
            c.vacances_adjacentes.forEach(va => {
                h += '<div class="st-vac-alert"><i class="bi bi-exclamation-triangle-fill"></i> Absence détectée <strong>'+(va.position==='avant'?'avant':'après')+'</strong> des vacances ('+esc(va.vacance_dates)+')</div>';
            });
        }

        // Absences table
        h += '<table class="st-detail-table"><thead><tr><th>Début</th><th>Fin</th><th>Durée</th><th>Type</th><th>Justifié</th><th>Motif</th></tr></thead><tbody>';
        (c.absences||[]).forEach(a => {
            h += '<tr><td>'+fmtDate(a.date_debut)+'</td><td>'+fmtDate(a.date_fin)+'</td><td>'+a.duree_jours+'j</td>';
            h += '<td>'+typeBadge(a.type)+'</td>';
            h += '<td>'+(a.has_justificatif?'<span class="st-badge st-badge-oui">✓</span>':'<span class="st-badge st-badge-non">✗</span>')+'</td>';
            h += '<td>'+esc(a.motif||'-')+'</td></tr>';
        });
        h += '</tbody></table>';

        // Mini bar annuel
        h += '<div class="small text-muted fw-bold mb-1">Historique annuel</div><div class="st-mini-bar">';
        const absMonths = new Set();
        (c.absences||[]).forEach(a => absMonths.add(parseInt(a.date_debut.slice(5,7))));
        for (let m=1;m<=12;m++) h += '<div class="st-mini-bar-month'+(absMonths.has(m)?' active':'')+'"><span>'+MONTHS_SHORT[m-1]+'</span></div>';
        h += '</div>';

        document.getElementById('stUserModalBody').innerHTML = h;
        new bootstrap.Modal(document.getElementById('stUserModal')).show();
    }

    // Period tabs
    document.querySelectorAll('.st-period-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.st-period-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentPeriod = btn.dataset.period;
            currentDate = new Date();
            loadData();
        });
    });
    document.getElementById('stPrev')?.addEventListener('click', () => navigate(-1));
    document.getElementById('stNext')?.addEventListener('click', () => navigate(1));

    // Chart type toggle
    document.querySelectorAll('.st-ct-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.st-ct-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            chartType = btn.dataset.chart;
            if (stData) renderCharts(stData);
        });
    });

    // ═══ Compare feature ═══
    let cmpDate = new Date();
    let cmpChartCurrent = null, cmpChartPrev = null;

    document.getElementById('stCompareBtn')?.addEventListener('click', () => {
        cmpDate = new Date(currentDate);
        const modal = new bootstrap.Modal(document.getElementById('stCompareModal'));
        modal.show();
        loadCompare();
    });

    document.getElementById('stCmpPrev')?.addEventListener('click', () => {
        cmpDate.setMonth(cmpDate.getMonth() - 1);
        loadCompare();
    });
    document.getElementById('stCmpNext')?.addEventListener('click', () => {
        cmpDate.setMonth(cmpDate.getMonth() + 1);
        loadCompare();
    });

    async function loadCompare() {
        const m = cmpDate.getMonth(), y = cmpDate.getFullYear();
        document.getElementById('stCmpLabel').textContent = MONTHS_FR[m] + ' ' + y;
        document.getElementById('stCompareTitle').textContent = 'Comparaison — ' + MONTHS_FR[m];
        document.getElementById('stCompareContent').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const dateCurrent = y + '-' + String(m+1).padStart(2,'0') + '-15';
        const datePrev = (y-1) + '-' + String(m+1).padStart(2,'0') + '-15';

        const [resCur, resPrev] = await Promise.all([
            adminApiPost('admin_get_absence_stats', { period:'month', date:dateCurrent }),
            adminApiPost('admin_get_absence_stats', { period:'month', date:datePrev }),
        ]);

        if (!resCur.success) { document.getElementById('stCompareContent').innerHTML = '<p class="text-danger">Erreur</p>'; return; }

        const sCur = resCur.stats || {};
        const sPrev = resPrev.success ? (resPrev.stats || {}) : { total_absents:0, total_jours:0, total_heures:0, justifiees:0, non_justifiees:0, par_type:{} };

        let h = '<div class="st-cmp-grid">';

        // Current year card
        h += '<div class="st-cmp-card">';
        h += '<div class="st-cmp-card-title"><i class="bi bi-calendar3"></i> ' + MONTHS_FR[m] + ' <span class="year" style="background:#bcd2cb;color:#2d4a43">' + y + '</span></div>';
        h += cmpStats(sCur);
        h += '<div class="st-cmp-chart"><canvas id="stCmpChartCur"></canvas></div>';
        h += '</div>';

        // Previous year card
        h += '<div class="st-cmp-card">';
        h += '<div class="st-cmp-card-title"><i class="bi bi-calendar3"></i> ' + MONTHS_FR[m] + ' <span class="year" style="background:#D0C4D8;color:#5B4B6B">' + (y-1) + '</span></div>';
        h += cmpStats(sPrev);
        h += '<div class="st-cmp-chart"><canvas id="stCmpChartPrev"></canvas></div>';
        h += '</div>';

        h += '</div>';

        // Diff recap
        h += '<div class="st-cmp-diff">';
        h += '<div class="st-cmp-diff-title"><i class="bi bi-arrow-left-right"></i> Évolution ' + (y-1) + ' → ' + y + '</div>';
        h += diffRow('Collaborateurs absents', sPrev.total_absents, sCur.total_absents);
        h += diffRow('Jours d\'absence', sPrev.total_jours, sCur.total_jours);
        h += diffRow('Heures d\'absence', sPrev.total_heures, sCur.total_heures);
        h += diffRow('Justifiées', sPrev.justifiees, sCur.justifiees);
        h += diffRow('Non justifiées', sPrev.non_justifiees, sCur.non_justifiees);
        h += '</div>';

        document.getElementById('stCompareContent').innerHTML = h;

        // Render mini charts
        if (cmpChartCurrent) cmpChartCurrent.destroy();
        if (cmpChartPrev) cmpChartPrev.destroy();
        const chartOpts = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } };

        const curCtx = document.getElementById('stCmpChartCur');
        if (curCtx) {
            const d = resCur.chart_data || [];
            cmpChartCurrent = new Chart(curCtx, {
                type:'bar', data:{ labels:d.map(x=>x.label), datasets:[{ data:d.map(x=>x.jours), backgroundColor:'rgba(188,210,203,.6)', borderColor:'#2d4a43', borderWidth:1, borderRadius:4, maxBarThickness:30 }] }, options:chartOpts
            });
        }
        const prevCtx = document.getElementById('stCmpChartPrev');
        if (prevCtx && resPrev.success) {
            const d = resPrev.chart_data || [];
            cmpChartPrev = new Chart(prevCtx, {
                type:'bar', data:{ labels:d.map(x=>x.label), datasets:[{ data:d.map(x=>x.jours), backgroundColor:'rgba(208,196,216,.6)', borderColor:'#5B4B6B', borderWidth:1, borderRadius:4, maxBarThickness:30 }] }, options:chartOpts
            });
        }
    }

    function cmpStats(s) {
        let h = '<div class="st-cmp-stats">';
        h += '<div class="st-cmp-stat"><div class="val">' + (s.total_absents||0) + '</div><div class="lbl">Absents</div></div>';
        h += '<div class="st-cmp-stat"><div class="val">' + (s.total_jours||0) + '</div><div class="lbl">Jours</div></div>';
        h += '<div class="st-cmp-stat"><div class="val">' + (s.total_heures||0) + '</div><div class="lbl">Heures</div></div>';
        h += '<div class="st-cmp-stat"><div class="val">' + (s.justifiees||0) + '</div><div class="lbl">Justif.</div></div>';
        h += '<div class="st-cmp-stat"><div class="val">' + (s.non_justifiees||0) + '</div><div class="lbl">Non justif.</div></div>';
        return h + '</div>';
    }

    function diffRow(label, prev, cur) {
        prev = parseFloat(prev) || 0;
        cur = parseFloat(cur) || 0;
        const diff = cur - prev;
        let arrow, cls;
        if (diff > 0) { arrow = '▲ +' + diff; cls = 'st-cmp-up'; }
        else if (diff < 0) { arrow = '▼ ' + diff; cls = 'st-cmp-down'; }
        else { arrow = '= 0'; cls = 'st-cmp-equal'; }
        const pct = prev > 0 ? Math.round((diff / prev) * 100) : (cur > 0 ? '+100' : '0');
        return '<div class="st-cmp-diff-row"><span>' + label + '</span><span><strong>' + prev + '</strong> → <strong>' + cur + '</strong> <span class="st-cmp-arrow ' + cls + '">' + arrow + ' (' + (diff >= 0 ? '+' : '') + pct + '%)</span></span></div>';
    }

    // Slidedown toggle
    document.getElementById('stToggleDetail')?.addEventListener('click', function() {
        const sd = document.getElementById('stSlidedown');
        this.classList.toggle('open');
        sd.classList.toggle('open');
    });

    // Print
    document.getElementById('stPrintBtn')?.addEventListener('click', () => {
        if (!stData) return;
        const w = window.open('','_blank');
        if (!w) return;
        const s = stData.stats;
        const collabs = (stData.collaborateurs||[]).sort((a,b) => b.nb_jours_periode - a.nb_jours_periode);
        let p = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Suivi absences</title>';
        p += '<style>body{font-family:system-ui,sans-serif;padding:30px;font-size:13px;color:#333}';
        p += 'h1{font-size:18px;margin-bottom:4px}h2{font-size:14px;margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:4px}';
        p += '.stats{display:flex;gap:12px;margin:12px 0}.stat{flex:1;text-align:center;padding:10px;border:1px solid #ddd;border-radius:8px}';
        p += '.stat .v{font-size:20px;font-weight:700}.stat .l{font-size:10px;color:#888}';
        p += 'table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:12px}';
        p += 'th{background:#f5f5f5;font-weight:600}.alert{background:#FEF3C7;border:1px solid #F59E0B;border-radius:6px;padding:8px;margin:6px 0;font-size:12px}';
        p += '</style></head><body>';
        p += '<h1>Suivi des absences — '+esc(formatDateLabel())+'</h1>';
        p += '<p style="color:#888;font-size:11px">Généré le '+new Date().toLocaleDateString('fr-CH')+'</p>';
        p += '<div class="stats">';
        p += '<div class="stat"><div class="v">'+s.total_absents+'</div><div class="l">Absents</div></div>';
        p += '<div class="stat"><div class="v">'+s.total_jours+'</div><div class="l">Jours</div></div>';
        p += '<div class="stat"><div class="v">'+s.total_heures+'</div><div class="l">Heures</div></div>';
        p += '<div class="stat"><div class="v">'+s.justifiees+'</div><div class="l">Justifiées</div></div>';
        p += '<div class="stat"><div class="v">'+s.non_justifiees+'</div><div class="l">Non justifiées</div></div>';
        p += '</div>';
        collabs.forEach(c => {
            p += '<h2>'+esc(c.prenom+' '+c.nom)+' — '+esc(c.fonction_nom||'')+'</h2>';
            p += '<p><strong>'+c.nb_jours_periode+'</strong> jours (période) | <strong>'+c.nb_jours_annee+'</strong> jours (année)</p>';
            if (c.vacances_adjacentes?.length) c.vacances_adjacentes.forEach(va => { p += '<div class="alert">⚠ Absence '+va.position+' vacances ('+esc(va.vacance_dates)+')</div>'; });
            p += '<table><tr><th>Début</th><th>Fin</th><th>Durée</th><th>Type</th><th>Justifié</th><th>Motif</th></tr>';
            (c.absences||[]).forEach(a => { p += '<tr><td>'+fmtDate(a.date_debut)+'</td><td>'+fmtDate(a.date_fin)+'</td><td>'+a.duree_jours+'j</td><td>'+esc(TYPE_LABELS[a.type]||a.type)+'</td><td>'+(a.has_justificatif?'Oui':'Non')+'</td><td>'+esc(a.motif||'-')+'</td></tr>'; });
            p += '</table>';
        });
        p += '</body></html>';
        w.document.write(p); w.document.close(); w.onload = () => w.print();
    });

    // Helpers
    function esc(s) { if(!s)return''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function fmtDate(d) { if(!d)return'-'; try{return new Date(d+'T00:00:00').toLocaleDateString('fr-CH',{day:'2-digit',month:'2-digit',year:'numeric'});}catch(e){return d;} }
    function typeBadge(t) { return '<span class="st-badge st-badge-'+(t||'autre')+'">'+esc(TYPE_LABELS[t]||t||'-')+'</span>'; }
    function modalStat(val,lbl,bg,fg) { return '<div class="st-modal-stat" style="background:'+bg+'"><div class="val" style="color:'+fg+'">'+val+'</div><div class="lbl">'+lbl+'</div></div>'; }
    function getMainType(abs) { if(!abs?.length)return'autre'; const c={}; abs.forEach(a=>{c[a.type]=(c[a.type]||0)+a.duree_jours;}); return Object.entries(c).sort((a,b)=>b[1]-a[1])[0][0]; }
    function businessDays(s,e) { let n=0; const d=new Date(s+'T00:00:00'),l=new Date(e+'T00:00:00'); while(d<=l){const w=d.getDay();if(w!==0&&w!==6)n++;d.setDate(d.getDate()+1);}return n; }

    // Init
    function initStatsPage() { loadData(); }
    window.initStatsPage = initStatsPage;
    initStatsPage();
})();
</script>
