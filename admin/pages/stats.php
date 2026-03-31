<?php
// ─── Données serveur (SSR) ───────────────────────────────────────────────────
$statTotalUsers    = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
$statPendingAbs    = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$statPendingDesirs = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
$statUnreadMsgs    = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE lu = 0 AND deleted = 0");
?>
<style>
/* ── Stat cards ── */
.st-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.st-stat-card { flex:1; min-width:110px; text-align:center; padding:14px 10px; border-radius:14px; border:1px solid var(--cl-border-light,#F0EDE8); background:var(--cl-surface,#fff); }
.st-stat-icon { width:38px;height:38px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:8px; }
.st-stat-val { font-size:1.5rem;font-weight:700;line-height:1.2; }
.st-stat-lbl { font-size:.7rem;color:var(--cl-text-muted);margin-top:3px; }
.st-section-title { letter-spacing:.5px; }

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
.st-chart-title { font-size:.82rem;font-weight:700;margin-bottom:12px; }
.st-chart-wrap { position:relative;height:220px; }

/* ── Summary cards below charts ── */
.st-sum-cards { display:flex;gap:10px;flex-wrap:wrap;margin-top:16px; }
.st-sum-card { flex:1;min-width:100px;padding:12px 10px;border-radius:12px;text-align:center; }
.st-sum-val { font-size:1.3rem;font-weight:700;line-height:1.2; }
.st-sum-lbl { font-size:.68rem;margin-top:2px;opacity:.85; }

/* ── Detail button ── */
.st-detail-btn { display:inline-flex;align-items:center;gap:8px;padding:10px 24px;border-radius:12px;font-weight:600;font-size:.88rem;border:2px solid #2d4a43;background:#bcd2cb;color:#2d4a43;cursor:pointer;transition:all .15s; }
.st-detail-btn:hover { background:#2d4a43;color:#fff; }

/* ── Modal table ── */
.st-table-wrap { border:1.5px solid var(--cl-border-light,#F0EDE8);border-radius:14px;overflow:hidden;background:var(--cl-surface,#fff); }
.st-table { width:100%;border-collapse:collapse; }
.st-table th { font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--cl-text-muted);padding:12px 14px;border-bottom:1.5px solid var(--cl-border-light,#F0EDE8);text-align:left;background:var(--cl-bg,#F7F5F2); }
.st-table th:first-child { border-top-left-radius:14px; }
.st-table th:last-child { border-top-right-radius:14px; }
.st-table td { padding:11px 14px;border-bottom:1px solid var(--cl-border-light,#F0EDE8);vertical-align:middle;font-size:.85rem; }
.st-table tr:last-child td { border-bottom:none; }
.st-table .st-row-click { cursor:pointer;transition:background .12s; }
.st-table .st-row-click:hover td { background:var(--cl-bg,#FAFAF7); }

/* ── Slidedown detail ── */
.st-expand-td { padding:0!important;border-bottom:1px solid var(--cl-border-light)!important; }
.st-expand-inner { padding:16px 20px;border-left:4px solid #bcd2cb;margin:0 10px 10px;border-radius:0 10px 10px 0;background:var(--cl-bg,#F7F5F2); }
.st-expand-stats { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px; }
.st-expand-stat { flex:1;min-width:90px;text-align:center;padding:10px 8px;border-radius:10px;background:var(--cl-surface,#fff);border:1px solid var(--cl-border-light); }
.st-expand-stat .val { font-size:1.1rem;font-weight:700; }
.st-expand-stat .lbl { font-size:.66rem;color:var(--cl-text-muted);margin-top:2px; }
.st-vac-alert { background:#FEF3C7;border:1px solid #F59E0B;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;color:#92400E; }
.st-mini-bar { display:flex;gap:3px;margin-top:10px; }
.st-mini-bar-month { width:20px;height:18px;border-radius:4px;background:var(--cl-border-light,#E8E4DE);display:flex;align-items:center;justify-content:center; }
.st-mini-bar-month.active { background:#bcd2cb; }
.st-mini-bar-month span { font-size:.5rem;color:var(--cl-text-muted); }

/* ── Badges ── */
.st-badge { font-size:.72rem;padding:3px 10px;border-radius:20px;font-weight:600;display:inline-block; }
.st-badge-vacances { background:#bcd2cb;color:#2d4a43; }
.st-badge-maladie { background:#E2B8AE;color:#7B3B2C; }
.st-badge-accident { background:#D4C4A8;color:#6B5B3E; }
.st-badge-formation { background:#B8C9D4;color:#3B4F6B; }
.st-badge-conge_special { background:#D0C4D8;color:#5B4B6B; }
.st-badge-autre { background:#E8E4DE;color:#6c757d; }
.st-badge-oui { background:#bcd2cb;color:#2d4a43; }
.st-badge-non { background:#E2B8AE;color:#7B3B2C; }

/* ── Avatar ── */
.st-avatar { width:34px;height:34px;border-radius:50%;object-fit:cover; }
.st-avatar-init { width:34px;height:34px;border-radius:50%;background:#bcd2cb;color:#2d4a43;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700; }

/* ── Print ── */
@media print { .st-no-print { display:none!important; } }
</style>

<h5 class="mb-3"><i class="bi bi-bar-chart-line"></i> Statistiques</h5>

<!-- ═══ Section 1: Effectifs (SSR) ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 st-section-title"><i class="bi bi-people"></i> Effectifs</h6>
<div class="st-stats">
  <div class="st-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="st-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-people-fill"></i></div>
    <div class="st-stat-val" style="color:#2d4a43"><?= $statTotalUsers ?></div>
    <div class="st-stat-lbl">Collaborateurs actifs</div>
  </div>
  <div class="st-stat-card" style="background:#f8f4ed;border-color:#e8dece">
    <div class="st-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-star-fill"></i></div>
    <div class="st-stat-val" style="color:#6B5B3E"><?= $statPendingDesirs ?></div>
    <div class="st-stat-lbl">Desirs en attente</div>
  </div>
  <div class="st-stat-card" style="background:#f0f4f8;border-color:#d4dfe8">
    <div class="st-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-envelope-fill"></i></div>
    <div class="st-stat-val" style="color:#3B4F6B"><?= $statUnreadMsgs ?></div>
    <div class="st-stat-lbl">Messages non lus</div>
  </div>
  <div class="st-stat-card" style="background:#f8f0ee;border-color:#e8d4ce">
    <div class="st-stat-icon" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-calendar-x-fill"></i></div>
    <div class="st-stat-val" style="color:#7B3B2C"><?= $statPendingAbs ?></div>
    <div class="st-stat-lbl">Absences en attente</div>
  </div>
</div>

<!-- ═══ Section 2: Graphiques with navigation ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 st-section-title"><i class="bi bi-graph-up"></i> Absences</h6>

<div class="st-period-nav" id="stPeriodNav">
  <div class="st-period-tabs">
    <button class="st-period-tab" data-period="week">Semaine</button>
    <button class="st-period-tab active" data-period="month">Mois</button>
    <button class="st-period-tab" data-period="year">Annee</button>
  </div>
  <div class="st-date-nav">
    <button id="stPrev" title="Precedent"><i class="bi bi-chevron-left"></i></button>
    <span class="st-date-label" id="stDateLabel">—</span>
    <button id="stNext" title="Suivant"><i class="bi bi-chevron-right"></i></button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="st-chart-card">
      <div class="st-chart-title">Jours d'absence par sous-periode</div>
      <div class="st-chart-wrap"><canvas id="stChartBar"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="st-chart-card">
      <div class="st-chart-title">Repartition par type</div>
      <div class="st-chart-wrap"><canvas id="stChartDoughnut"></canvas></div>
    </div>
  </div>
</div>

<!-- Summary cards -->
<div class="st-sum-cards" id="stSumCards">
  <div class="st-sum-card" style="background:#bcd2cb;color:#2d4a43"><div class="st-sum-val" id="stSumAbsents">-</div><div class="st-sum-lbl">Total absents</div></div>
  <div class="st-sum-card" style="background:#B8C9D4;color:#3B4F6B"><div class="st-sum-val" id="stSumJours">-</div><div class="st-sum-lbl">Total jours</div></div>
  <div class="st-sum-card" style="background:#D4C4A8;color:#6B5B3E"><div class="st-sum-val" id="stSumHeures">-</div><div class="st-sum-lbl">Total heures</div></div>
  <div class="st-sum-card" style="background:#bcd2cb;color:#2d4a43"><div class="st-sum-val" id="stSumJust">-</div><div class="st-sum-lbl">Justifiees</div></div>
  <div class="st-sum-card" style="background:#E2B8AE;color:#7B3B2C"><div class="st-sum-val" id="stSumNonJust">-</div><div class="st-sum-lbl">Non justifiees</div></div>
</div>

<!-- ═══ Section 3: Detail button ═══ -->
<div class="text-center my-4">
  <button class="st-detail-btn" id="stOpenModal"><i class="bi bi-list-ul"></i> Voir les absences detaillees</button>
</div>

<!-- ═══ Modal: Suivi des absences ═══ -->
<div class="modal fade" id="stAbsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-fullscreen-md-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stModalTitle">Suivi des absences</h5>
        <div class="d-flex gap-2 ms-auto align-items-center">
          <button class="btn btn-sm btn-outline-secondary" id="stPrintBtn"><i class="bi bi-printer"></i> Imprimer</button>
          <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
      <div class="modal-body" id="stModalBody">
        <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?> src="/zerdatime/assets/js/vendor/chart.umd.min.js"></script>
<script<?= nonce() ?>>
(function() {
    /* ── State ── */
    let currentPeriod = 'month';
    let currentDate = new Date();
    let stData = null;
    let chartBar = null, chartDoughnut = null;
    let expandedUserId = null;

    const TYPE_COLORS = { vacances:'#bcd2cb', maladie:'#E2B8AE', accident:'#D4C4A8', formation:'#B8C9D4', conge_special:'#D0C4D8', autre:'#C8C4BE' };
    const TYPE_LABELS = { vacances:'Vacances', maladie:'Maladie', accident:'Accident', formation:'Formation', conge_special:'Conge special', autre:'Autre' };
    const MONTHS_FR = ['Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];
    const MONTHS_SHORT = ['Jan','Fev','Mar','Avr','Mai','Jun','Jul','Aou','Sep','Oct','Nov','Dec'];

    /* ── Date label formatting ── */
    function formatDateLabel() {
        const y = currentDate.getFullYear(), m = currentDate.getMonth();
        if (currentPeriod === 'year') return '' + y;
        if (currentPeriod === 'month') return MONTHS_FR[m] + ' ' + y;
        // week: get ISO week number
        const d = new Date(currentDate);
        d.setHours(0,0,0,0); d.setDate(d.getDate()+3-(d.getDay()+6)%7);
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

    /* ── API call ── */
    function loadData() {
        document.getElementById('stDateLabel').textContent = formatDateLabel();
        adminApiPost('admin_get_absence_stats', { period: currentPeriod, date: toApiDate() }).then(r => {
            if (!r.success) return;
            stData = r;
            renderCharts(r);
            renderSummary(r.stats);
        });
    }

    /* ── Charts ── */
    function renderCharts(data) {
        const labels = data.chart_data.map(d => d.label);
        const values = data.chart_data.map(d => d.jours);

        // Bar chart
        if (chartBar) chartBar.destroy();
        const ctxBar = document.getElementById('stChartBar');
        if (ctxBar) {
            chartBar = new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels, datasets: [{
                        label: 'Jours', data: values,
                        backgroundColor: 'rgba(188,210,203,.6)', borderColor: '#2d4a43',
                        borderWidth: 1, borderRadius: 6, maxBarThickness: 40
                    }]
                },
                options: { responsive:true, maintainAspectRatio:false,
                    plugins:{ legend:{ display:false } },
                    scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
                }
            });
        }

        // Doughnut
        if (chartDoughnut) chartDoughnut.destroy();
        const pt = data.stats.par_type || {};
        const dLabels = [], dValues = [], dColors = [];
        for (const [k,v] of Object.entries(pt)) {
            if (v > 0) { dLabels.push(TYPE_LABELS[k]||k); dValues.push(v); dColors.push(TYPE_COLORS[k]||'#6c757d'); }
        }
        const ctxD = document.getElementById('stChartDoughnut');
        if (ctxD) {
            chartDoughnut = new Chart(ctxD, {
                type: 'doughnut',
                data: { labels:dLabels, datasets:[{ data:dValues, backgroundColor:dColors, borderWidth:2, borderColor:'#fff' }] },
                options: { responsive:true, maintainAspectRatio:false,
                    plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8, font:{ size:11 } } } }
                }
            });
        }
    }

    /* ── Summary cards ── */
    function renderSummary(s) {
        document.getElementById('stSumAbsents').textContent = s.total_absents;
        document.getElementById('stSumJours').textContent = s.total_jours;
        document.getElementById('stSumHeures').textContent = s.total_heures;
        document.getElementById('stSumJust').textContent = s.justifiees;
        document.getElementById('stSumNonJust').textContent = s.non_justifiees;
    }

    /* ── Period tab clicks ── */
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

    /* ── Open modal ── */
    document.getElementById('stOpenModal')?.addEventListener('click', () => {
        if (!stData) return;
        renderModal(stData);
        new bootstrap.Modal(document.getElementById('stAbsModal')).show();
    });

    /* ── Render modal content ── */
    function renderModal(data) {
        const s = data.stats;
        const periodLabel = formatDateLabel();
        document.getElementById('stModalTitle').textContent = 'Suivi des absences — ' + periodLabel;
        expandedUserId = null;

        let html = '';
        // Stats row
        html += '<div class="st-sum-cards mb-3">';
        html += sumCard('#bcd2cb','#2d4a43', s.total_absents, 'Total absents');
        html += sumCard('#B8C9D4','#3B4F6B', s.total_jours, 'Total jours');
        html += sumCard('#D4C4A8','#6B5B3E', s.total_heures, 'Total heures');
        html += sumCard('#bcd2cb','#2d4a43', s.justifiees, 'Justifiees');
        html += sumCard('#E2B8AE','#7B3B2C', s.non_justifiees, 'Non justifiees');
        html += '</div>';

        // Collaborateurs table
        const collabs = (data.collaborateurs || []).sort((a,b) => b.nb_jours_periode - a.nb_jours_periode);
        if (!collabs.length) {
            html += '<div class="text-center text-muted py-4"><i class="bi bi-check-circle" style="font-size:2rem;opacity:.2"></i><br>Aucune absence sur cette periode</div>';
        } else {
            html += '<div class="st-table-wrap"><table class="st-table" id="stModalTable"><thead><tr>';
            html += '<th>Collaborateur</th><th>Fonction</th><th>Jours (periode)</th><th>Jours (annee)</th><th>Justifie</th><th>Type principal</th><th style="width:40px"></th>';
            html += '</tr></thead><tbody>';
            collabs.forEach(c => {
                const mainType = getMainType(c.absences);
                const justified = c.absences.filter(a => a.has_justificatif).length;
                const total = c.absences.length;
                const avatar = c.photo
                    ? '<img src="/zerdatime/uploads/photos/' + esc(c.photo) + '" class="st-avatar" alt="">'
                    : '<span class="st-avatar-init">' + esc((c.prenom||'')[0]+(c.nom||'')[0]) + '</span>';

                html += '<tr class="st-row-click" data-uid="' + esc(c.id) + '">';
                html += '<td><div class="d-flex align-items-center gap-2">' + avatar + ' <strong>' + esc(c.prenom) + ' ' + esc(c.nom) + '</strong></div></td>';
                html += '<td>' + esc(c.fonction_nom||'-') + '</td>';
                html += '<td><strong>' + c.nb_jours_periode + '</strong></td>';
                html += '<td>' + c.nb_jours_annee + '</td>';
                html += '<td>' + justified + '/' + total + '</td>';
                html += '<td>' + typeBadge(mainType) + '</td>';
                html += '<td><i class="bi bi-chevron-down text-muted"></i></td>';
                html += '</tr>';
                // Expand row (hidden)
                html += '<tr class="st-expand-row" data-expand="' + esc(c.id) + '" style="display:none"><td colspan="7" class="st-expand-td">';
                html += buildExpandContent(c, data);
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        document.getElementById('stModalBody').innerHTML = html;

        // Bind row clicks
        document.getElementById('stModalTable')?.addEventListener('click', e => {
            const row = e.target.closest('.st-row-click');
            if (!row) return;
            const uid = row.dataset.uid;
            const expandRow = document.querySelector('.st-expand-row[data-expand="'+uid+'"]');
            if (!expandRow) return;
            if (expandedUserId === uid) {
                expandRow.style.display = 'none';
                expandedUserId = null;
                row.querySelector('.bi')?.classList.replace('bi-chevron-up','bi-chevron-down');
            } else {
                // Close previous
                if (expandedUserId) {
                    const prev = document.querySelector('.st-expand-row[data-expand="'+expandedUserId+'"]');
                    if (prev) prev.style.display = 'none';
                    const prevRow = document.querySelector('.st-row-click[data-uid="'+expandedUserId+'"]');
                    prevRow?.querySelector('.bi')?.classList.replace('bi-chevron-up','bi-chevron-down');
                }
                expandRow.style.display = '';
                expandedUserId = uid;
                row.querySelector('.bi')?.classList.replace('bi-chevron-down','bi-chevron-up');
            }
        });
    }

    /* ── Expand content for a user ── */
    function buildExpandContent(c, data) {
        const totalWorkDays = businessDaysBetween(data.date_debut, data.date_fin);
        const tauxAbs = totalWorkDays > 0 ? Math.round((c.nb_jours_periode / totalWorkDays) * 1000) / 10 : 0;
        const totalHours = Math.round(c.nb_jours_periode * 7.6 * 10) / 10;

        let h = '<div class="st-expand-inner">';
        // User stats
        h += '<div class="st-expand-stats">';
        h += expandStat(c.nb_jours_periode, 'Jours (periode)');
        h += expandStat(c.nb_jours_annee, 'Jours (annee)');
        h += expandStat(totalHours, 'Heures cumulees');
        h += expandStat(tauxAbs + '%', "Taux d'absence");
        h += '</div>';

        // Vacances adjacentes alert
        if (c.vacances_adjacentes && c.vacances_adjacentes.length) {
            c.vacances_adjacentes.forEach(va => {
                const pos = va.position === 'avant' ? 'avant' : 'apres';
                h += '<div class="st-vac-alert"><i class="bi bi-exclamation-triangle-fill"></i> Absence detectee <strong>' + pos + '</strong> des vacances (du ' + esc(va.vacance_dates) + ')</div>';
            });
        }

        // Absences table
        h += '<table class="table table-sm mb-2" style="font-size:.82rem"><thead><tr><th>Debut</th><th>Fin</th><th>Duree</th><th>Type</th><th>Justifie</th><th>Motif</th></tr></thead><tbody>';
        (c.absences || []).forEach(a => {
            h += '<tr>';
            h += '<td>' + formatFR(a.date_debut) + '</td>';
            h += '<td>' + formatFR(a.date_fin) + '</td>';
            h += '<td>' + a.duree_jours + 'j</td>';
            h += '<td>' + typeBadge(a.type) + '</td>';
            h += '<td>' + (a.has_justificatif ? '<span class="st-badge st-badge-oui">&#10003;</span>' : '<span class="st-badge st-badge-non">&#10007;</span>') + '</td>';
            h += '<td>' + esc(a.motif || '-') + '</td>';
            h += '</tr>';
        });
        h += '</tbody></table>';

        // Monthly mini bar
        h += '<div class="small text-muted fw-bold mb-1">Historique annuel</div>';
        h += '<div class="st-mini-bar">';
        const absMonths = new Set();
        (c.absences || []).forEach(a => {
            const m = parseInt(a.date_debut.slice(5,7));
            absMonths.add(m);
        });
        for (let m = 1; m <= 12; m++) {
            h += '<div class="st-mini-bar-month' + (absMonths.has(m) ? ' active' : '') + '"><span>' + MONTHS_SHORT[m-1] + '</span></div>';
        }
        h += '</div></div>';
        return h;
    }

    /* ── Print ── */
    document.getElementById('stPrintBtn')?.addEventListener('click', () => {
        if (!stData) return;
        const w = window.open('', '_blank');
        if (!w) return;
        const s = stData.stats;
        const collabs = (stData.collaborateurs || []).sort((a,b) => b.nb_jours_periode - a.nb_jours_periode);
        let h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Suivi absences</title>';
        h += '<style>body{font-family:system-ui,sans-serif;padding:30px;font-size:13px;color:#333}';
        h += 'h1{font-size:18px;margin-bottom:4px}h2{font-size:14px;margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:4px}';
        h += '.stats{display:flex;gap:12px;margin:12px 0}.stat{flex:1;text-align:center;padding:10px;border:1px solid #ddd;border-radius:8px}';
        h += '.stat .v{font-size:20px;font-weight:700}.stat .l{font-size:10px;color:#888}';
        h += 'table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:12px}';
        h += 'th{background:#f5f5f5;font-weight:600}.badge{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}';
        h += '.alert{background:#FEF3C7;border:1px solid #F59E0B;border-radius:6px;padding:8px;margin:6px 0;font-size:12px}';
        h += '</style></head><body>';
        h += '<h1>Suivi des absences — ' + esc(formatDateLabel()) + '</h1>';
        h += '<p style="color:#888;font-size:11px">Genere le ' + new Date().toLocaleDateString('fr-CH') + '</p>';

        h += '<div class="stats">';
        h += '<div class="stat"><div class="v">' + s.total_absents + '</div><div class="l">Absents</div></div>';
        h += '<div class="stat"><div class="v">' + s.total_jours + '</div><div class="l">Jours</div></div>';
        h += '<div class="stat"><div class="v">' + s.total_heures + '</div><div class="l">Heures</div></div>';
        h += '<div class="stat"><div class="v">' + s.justifiees + '</div><div class="l">Justifiees</div></div>';
        h += '<div class="stat"><div class="v">' + s.non_justifiees + '</div><div class="l">Non justifiees</div></div>';
        h += '</div>';

        collabs.forEach(c => {
            h += '<h2>' + esc(c.prenom + ' ' + c.nom) + ' — ' + esc(c.fonction_nom||'') + '</h2>';
            h += '<p><strong>' + c.nb_jours_periode + '</strong> jours (periode) | <strong>' + c.nb_jours_annee + '</strong> jours (annee)</p>';
            if (c.vacances_adjacentes?.length) {
                c.vacances_adjacentes.forEach(va => {
                    h += '<div class="alert">Absence detectee ' + va.position + ' des vacances (' + esc(va.vacance_dates) + ')</div>';
                });
            }
            h += '<table><tr><th>Debut</th><th>Fin</th><th>Duree</th><th>Type</th><th>Justifie</th><th>Motif</th></tr>';
            (c.absences||[]).forEach(a => {
                h += '<tr><td>' + formatFR(a.date_debut) + '</td><td>' + formatFR(a.date_fin) + '</td>';
                h += '<td>' + a.duree_jours + 'j</td><td>' + esc(TYPE_LABELS[a.type]||a.type) + '</td>';
                h += '<td>' + (a.has_justificatif ? 'Oui' : 'Non') + '</td><td>' + esc(a.motif||'-') + '</td></tr>';
            });
            h += '</table>';
        });

        h += '</body></html>';
        w.document.write(h);
        w.document.close();
        w.onload = () => w.print();
    });

    /* ── Helpers ── */
    function esc(s) { if (!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function formatFR(d) { if(!d)return'-'; try{return new Date(d+'T00:00:00').toLocaleDateString('fr-CH',{day:'2-digit',month:'2-digit',year:'numeric'});}catch(e){return d;} }
    function typeBadge(t) { return '<span class="st-badge st-badge-'+(t||'autre')+'">'+ esc(TYPE_LABELS[t]||t||'-') +'</span>'; }
    function sumCard(bg,fg,val,lbl) { return '<div class="st-sum-card" style="background:'+bg+';color:'+fg+'"><div class="st-sum-val">'+val+'</div><div class="st-sum-lbl">'+lbl+'</div></div>'; }
    function expandStat(val,lbl) { return '<div class="st-expand-stat"><div class="val">'+val+'</div><div class="lbl">'+lbl+'</div></div>'; }
    function getMainType(absences) {
        if (!absences?.length) return 'autre';
        const counts = {};
        absences.forEach(a => { counts[a.type] = (counts[a.type]||0) + a.duree_jours; });
        return Object.entries(counts).sort((a,b) => b[1]-a[1])[0][0];
    }
    function businessDaysBetween(start, end) {
        let count = 0;
        const d = new Date(start+'T00:00:00');
        const last = new Date(end+'T00:00:00');
        while (d <= last) { const dow = d.getDay(); if (dow !== 0 && dow !== 6) count++; d.setDate(d.getDate()+1); }
        return count;
    }

    /* ── Init ── */
    function initStatsPage() { loadData(); }
    window.initStatsPage = initStatsPage;
    initStatsPage();
})();
</script>
