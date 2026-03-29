<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$statTotalUsers    = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
$statPendingAbs    = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$statPendingDesirs = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
$statUnreadMsgs    = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE lu = 0 AND deleted = 0");

$absParType = Db::fetchAll(
    "SELECT type, COUNT(*) as total FROM absences WHERE statut = 'valide' AND date_fin >= CURDATE() GROUP BY type"
);

$recentAbsences = Db::fetchAll(
    "SELECT a.*, u.prenom, u.nom, u.photo
     FROM absences a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 10"
);

$year = date('Y');
$absParMois = Db::fetchAll(
    "SELECT MONTH(date_debut) as mois, COUNT(*) as total
     FROM absences
     WHERE YEAR(date_debut) = ?
     GROUP BY MONTH(date_debut)
     ORDER BY mois",
    [$year]
);
?>
<style>
/* ── Stats page classes ── */
.stats-section-title { letter-spacing: .5px; }
.stat-icon {
    width: 48px; height: 48px; flex-shrink: 0;
}
.stat-icon--users     { background: #bcd2cb; }
.stat-icon--users i   { color: #2d4a43; }
.stat-icon--desirs    { background: #D4C4A8; }
.stat-icon--desirs i  { color: #6B5B3E; }
.stat-icon--messages  { background: #B8C9D4; }
.stat-icon--messages i{ color: #3B4F6B; }
.stat-icon--vacances  { background: #E2B8AE; }
.stat-icon--vacances i{ color: #7B3B2C; }
.stat-icon--absences  { background: #D0C4D8; }
.stat-icon--absences i{ color: #5B4B6B; }

.stats-vac-alert--hidden { display: none !important; }
.stats-vac-alert--visible { display: flex !important; }

.chart-container { position: relative; height: 220px; max-height: 220px; }

/* Absence bar chart (JS template) */
.abs-bar-label { width: 110px; }
.abs-bar-track { height: 22px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
.abs-bar-fill  { height: 100%; border-radius: 4px; transition: width .5s; }
.abs-bar-badge { min-width: 32px; }
</style>

<h5 class="mb-3"><i class="bi bi-bar-chart-line"></i> Statistiques</h5>

<!-- Alerte vacances non validées -->
<div class="vac-notice-bar <?= $statPendingAbs > 0 ? 'stats-vac-alert--visible' : 'stats-vac-alert--hidden' ?>" id="statsVacAlert" style="background:#e8e6dc;border-color:#dbd9d1;">
  <span><strong id="statsVacAlertCount"><?= $statPendingAbs ?></strong> demande(s) d'absence en attente de validation.</span>
  <a href="<?= admin_url('absences') ?>" class="vac-notice-link">Voir les demandes &rarr;</a>
</div>

<!-- ═══ Effectifs ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 stats-section-title"><i class="bi bi-people"></i> Effectifs</h6>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center stat-icon stat-icon--users">
          <i class="bi bi-people-fill fs-5"></i>
        </div>
        <div>
          <div class="fs-3 fw-bold"><?= $statTotalUsers ?></div>
          <div class="text-muted small">Collaborateurs actifs</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center stat-icon stat-icon--desirs">
          <i class="bi bi-star-fill fs-5"></i>
        </div>
        <div>
          <div class="fs-3 fw-bold"><?= $statPendingDesirs ?></div>
          <div class="text-muted small">Désirs en attente</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center stat-icon stat-icon--messages">
          <i class="bi bi-envelope-fill fs-5"></i>
        </div>
        <div>
          <div class="fs-3 fw-bold"><?= $statUnreadMsgs ?></div>
          <div class="text-muted small">Messages non lus</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center stat-icon stat-icon--vacances">
          <i class="bi bi-sun-fill fs-5"></i>
        </div>
        <div>
          <div class="fs-3 fw-bold"><?= $statPendingAbs ?></div>
          <div class="text-muted small">Absences en attente</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Absences ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 stats-section-title"><i class="bi bi-calendar-x"></i> Absences</h6>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center stat-icon stat-icon--absences">
          <i class="bi bi-hourglass-split fs-5"></i>
        </div>
        <div>
          <div class="fs-3 fw-bold"><?= $statPendingAbs ?></div>
          <div class="text-muted small">Absences en attente</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-9">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold small mb-3">Absences actives par type</h6>
        <div id="statsAbsencesByType">
          <?php if (empty($absParType)): ?>
            <p class="text-muted small mb-0">Aucune absence active</p>
          <?php else:
            $maxVal = max(array_column($absParType, 'total'));
            $colors = ['vacances'=>'#bcd2cb','maladie'=>'#E2B8AE','accident'=>'#D4C4A8','formation'=>'#B8C9D4','conge_special'=>'#D0C4D8','militaire'=>'#C8C4BE'];
            foreach ($absParType as $ab):
              $pct = $maxVal > 0 ? round(($ab['total'] / $maxVal) * 100) : 0;
              $color = $colors[$ab['type']] ?? '#6c757d';
          ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="fw-500 small abs-bar-label"><?= h($ab['type']) ?></span>
              <div class="flex-fill abs-bar-track">
                <div class="abs-bar-fill" style="width:<?= $pct ?>%;background:<?= h($color) ?>"></div>
              </div>
              <span class="badge abs-bar-badge" style="background:<?= h($color) ?>"><?= (int)$ab['total'] ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Graphiques ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 stats-section-title"><i class="bi bi-graph-up"></i> Graphiques</h6>
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold small mb-3">Absences par mois (année en cours)</h6>
        <div class="chart-container">
          <canvas id="chartAbsencesMois"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="fw-bold small mb-3">Répartition par type</h6>
        <div class="chart-container">
          <canvas id="chartAbsencesType"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Planning ═══ -->
<h6 class="text-muted text-uppercase small fw-bold mb-2 stats-section-title"><i class="bi bi-calendar3"></i> Récentes demandes</h6>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold small mb-3">Dernières demandes d'absence</h6>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th>Collaborateur</th>
                <th>Type</th>
                <th>Du</th>
                <th>Au</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentAbsences)): ?>
                <tr><td colspan="5" class="text-center py-3 text-muted">Aucune demande</td></tr>
              <?php else: foreach (array_slice($recentAbsences, 0, 8) as $a):
                $sc = $a['statut'] === 'valide' ? 'success' : ($a['statut'] === 'refuse' ? 'danger' : 'warning');
                $tc = $a['type'] === 'vacances' ? 'info' : ($a['type'] === 'maladie' ? 'danger' : 'secondary');
              ?>
                <tr>
                  <td><strong><?= h($a['prenom'] . ' ' . $a['nom']) ?></strong></td>
                  <td><span class="badge bg-<?= $tc ?>"><?= h($a['type']) ?></span></td>
                  <td><?= h($a['date_debut']) ?></td>
                  <td><?= h($a['date_fin']) ?></td>
                  <td><span class="badge bg-<?= $sc ?>"><?= h($a['statut']) ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?> src="/zerdatime/assets/js/vendor/chart.umd.min.js"></script>
<script<?= nonce() ?>>
function initStatsPage() {
    const absParType = <?= json_encode(array_values($absParType), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const absParMois = <?= json_encode(array_values($absParMois), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    if (typeof Chart !== 'undefined') {
        const moLabels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];

        // Line chart: absences par mois
        const moisData = new Array(12).fill(0);
        absParMois.forEach(m => { moisData[parseInt(m.mois) - 1] = parseInt(m.total); });

        const ctxLine = document.getElementById('chartAbsencesMois');
        if (ctxLine) {
            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: moLabels,
                    datasets: [{
                        label: 'Absences',
                        data: moisData,
                        borderColor: '#8B7355',
                        backgroundColor: 'rgba(139,115,85,.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#8B7355'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // Doughnut chart: absences par type
        const typeColors = {
            'vacances': '#bcd2cb', 'maladie': '#E2B8AE', 'accident': '#D4C4A8',
            'formation': '#B8C9D4', 'conge_special': '#D0C4D8', 'militaire': '#C8C4BE'
        };
        const ctxDoughnut = document.getElementById('chartAbsencesType');
        if (ctxDoughnut && absParType.length) {
            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: absParType.map(a => a.type),
                    datasets: [{
                        data: absParType.map(a => parseInt(a.total)),
                        backgroundColor: absParType.map(a => typeColors[a.type] || '#6c757d'),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } }
                    }
                }
            });
        }
    }
}
window.initStatsPage = initStatsPage;
</script>
