<?php
$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');
$anneeCourante = (int) $today->format('Y');

// Stats
$nbPlanifies = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien >= ?",
    [$todayStr]
);
$nbRealises = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'realise' AND YEAR(date_entretien) = ?",
    [$anneeCourante]
);
$nbEnRetard = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien < ?",
    [$todayStr]
);
$nbAEcheance = (int) Db::getOne(
    "SELECT COUNT(*) FROM users WHERE is_active = 1
     AND prochain_entretien_date IS NOT NULL
     AND prochain_entretien_date <= DATE_ADD(?, INTERVAL 30 DAY)
     AND NOT EXISTS (
         SELECT 1 FROM entretiens_annuels e
         WHERE e.user_id = users.id AND e.statut = 'planifie' AND e.date_entretien >= CURDATE()
     )",
    [$todayStr]
);

// Filtres GET
$filtreStatut = $_GET['statut'] ?? '';
$filtreAnnee  = (int) ($_GET['annee'] ?? $anneeCourante);

$where = ['1=1'];
$binds = [];
if (in_array($filtreStatut, ['planifie', 'realise', 'reporte', 'annule'], true)) {
    $where[] = 'e.statut = ?';
    $binds[] = $filtreStatut;
}
if ($filtreAnnee) {
    $where[] = 'e.annee = ?';
    $binds[] = $filtreAnnee;
}

$entretiens = Db::fetchAll(
    "SELECT e.id, e.user_id, e.annee, e.date_entretien, e.statut, e.signed_at,
            u.prenom, u.nom, fn.nom AS fonction, fn.secteur_fegems,
            ev.prenom AS eval_prenom, ev.nom AS eval_nom
     FROM entretiens_annuels e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     LEFT JOIN users ev ON ev.id = e.evaluator_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY e.date_entretien IS NULL, e.date_entretien DESC, u.nom ASC",
    $binds
);

// Échéances (users avec prochain_entretien_date <= +60j sans entretien planifié)
$echeances = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.prochain_entretien_date,
            fn.nom AS fonction, fn.secteur_fegems,
            DATEDIFF(u.prochain_entretien_date, CURDATE()) AS jours_restants
     FROM users u
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE u.is_active = 1
       AND u.prochain_entretien_date IS NOT NULL
       AND u.prochain_entretien_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
       AND NOT EXISTS (
           SELECT 1 FROM entretiens_annuels e WHERE e.user_id = u.id AND e.statut = 'planifie' AND e.date_entretien >= CURDATE()
       )
     ORDER BY u.prochain_entretien_date ASC
     LIMIT 30"
);

// Liste users actifs pour modal nouveau
$usersActifs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, fn.nom AS fonction
     FROM users u LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE u.is_active = 1
     ORDER BY u.nom, u.prenom"
);
$evaluateurs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom FROM users u
     WHERE u.is_active = 1 AND u.role IN ('responsable','admin','direction')
     ORDER BY u.nom"
);

$STATUT_BADGE = [
    'planifie' => ['Planifié', '#B8C9D4', '#3D5A6B'],
    'realise'  => ['Réalisé',  '#bcd2cb', '#2d4a43'],
    'reporte'  => ['Reporté',  '#E8C9A0', '#7B5B2C'],
    'annule'   => ['Annulé',   '#E2B8AE', '#7B3B2C'],
];
$SECTEUR_LABELS = [
    'soins' => 'Soins', 'socio_culturel' => 'Socio-culturel', 'hotellerie' => 'Hôtellerie',
    'maintenance' => 'Maintenance', 'administration' => 'Administration', 'management' => 'Management',
];

$anneesDispos = Db::fetchAll("SELECT DISTINCT annee FROM entretiens_annuels ORDER BY annee DESC");
?>
<style>
.rhe-row { border-bottom: 1px solid var(--cl-border-light, #F0EDE8); padding: 12px 0; display: flex; gap: 12px; align-items: center; cursor: pointer; transition: background .15s; }
.rhe-row:hover { background: var(--cl-bg, #F7F5F2); }
.rhe-row:last-child { border-bottom: 0; }
.rhe-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--cl-teal, #2d4a43), var(--cl-teal-700, #1f3530)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .82rem; flex-shrink: 0; }
.rhe-name { font-weight: 600; color: var(--cl-text); }
.rhe-meta { font-size: .78rem; color: var(--cl-text-muted); }
.rhe-date { font-size: .82rem; color: var(--cl-text); font-weight: 600; min-width: 100px; }
.rhe-date small { display: block; font-weight: 400; color: var(--cl-text-muted); }
.rhe-badge { font-size: .72rem; padding: 3px 10px; border-radius: 10px; font-weight: 600; flex-shrink: 0; }
.rhe-secteur-tag { font-size: .68rem; padding: 2px 8px; border-radius: 8px; background: var(--cl-bg, #F7F5F2); color: var(--cl-text-muted); flex-shrink: 0; }
.rhe-tab-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.rhe-pill { padding: 5px 12px; border-radius: 16px; background: var(--cl-bg, #F7F5F2); font-size: .82rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: all .15s; color: var(--cl-text); text-decoration: none; }
.rhe-pill:hover { background: #fff; border-color: var(--cl-border-light, #F0EDE8); }
.rhe-pill.active { background: var(--cl-teal, #2d4a43); color: #fff; border-color: var(--cl-teal, #2d4a43); }
.rhe-empty { text-align: center; padding: 40px 12px; color: var(--cl-text-muted); }
.rhe-empty i { font-size: 2.2rem; opacity: .25; display: block; margin-bottom: 8px; }
.rhe-echeance-row { padding: 10px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); display: flex; align-items: center; gap: 10px; }
.rhe-echeance-row:last-child { border-bottom: 0; }
.rhe-jrest { font-size: .72rem; padding: 2px 8px; border-radius: 8px; font-weight: 600; flex-shrink: 0; min-width: 56px; text-align: center; }
.rhe-jrest.urgent { background: #E2B8AE; color: #7B3B2C; }
.rhe-jrest.proche { background: #E8C9A0; color: #7B5B2C; }
.rhe-jrest.ok { background: #bcd2cb; color: #2d4a43; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-chat-square-text"></i> Entretiens annuels</h4>
  <button class="btn btn-sm btn-primary" id="rhe-btn-new"><i class="bi bi-plus-lg"></i> Planifier un entretien</button>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-calendar-event"></i></div>
      <div><div class="stat-value"><?= $nbPlanifies ?></div><div class="stat-label">Planifiés à venir</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="stat-value"><?= $nbRealises ?></div><div class="stat-label">Réalisés <?= $anneeCourante ?></div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-red"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="stat-value"><?= $nbEnRetard ?></div><div class="stat-label">En retard</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-clock"></i></div>
      <div><div class="stat-value"><?= $nbAEcheance ?></div><div class="stat-label">À échéance (30j)</div></div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header bg-white">
        <div class="rhe-tab-pills">
          <a href="?page=rh-entretiens" class="rhe-pill <?= $filtreStatut === '' ? 'active' : '' ?>" data-page-link>Tous</a>
          <a href="?page=rh-entretiens&statut=planifie" class="rhe-pill <?= $filtreStatut === 'planifie' ? 'active' : '' ?>" data-page-link>Planifiés</a>
          <a href="?page=rh-entretiens&statut=realise" class="rhe-pill <?= $filtreStatut === 'realise' ? 'active' : '' ?>" data-page-link>Réalisés</a>
          <a href="?page=rh-entretiens&statut=reporte" class="rhe-pill <?= $filtreStatut === 'reporte' ? 'active' : '' ?>" data-page-link>Reportés</a>
          <span class="ms-auto"></span>
          <select class="form-select form-select-sm" id="rhe-annee-select" style="max-width:120px">
            <?php foreach ($anneesDispos as $a): ?>
              <option value="<?= $a['annee'] ?>" <?= $filtreAnnee == $a['annee'] ? 'selected' : '' ?>><?= $a['annee'] ?></option>
            <?php endforeach ?>
            <?php if (!array_filter($anneesDispos, fn($a) => $a['annee'] == $anneeCourante)): ?>
              <option value="<?= $anneeCourante ?>" selected><?= $anneeCourante ?></option>
            <?php endif ?>
          </select>
        </div>
      </div>
      <div class="card-body">
        <?php if ($entretiens): ?>
          <?php foreach ($entretiens as $e):
            $initials = strtoupper(substr($e['prenom'], 0, 1) . substr($e['nom'], 0, 1));
            [$badgeLabel, $badgeBg, $badgeFg] = $STATUT_BADGE[$e['statut']] ?? ['?', '#ddd', '#333'];
            $dateAffiche = $e['date_entretien']
              ? DateTime::createFromFormat('Y-m-d', $e['date_entretien'])->format('d/m/Y')
              : '—';
            $jourSemaine = $e['date_entretien']
              ? ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'][(int) date('w', strtotime($e['date_entretien']))]
              : '';
          ?>
            <div class="rhe-row" data-link="<?= h(admin_url('rh-entretiens-fiche', $e['id'])) ?>">
              <div class="rhe-avatar"><?= h($initials) ?></div>
              <div style="flex:1;min-width:0">
                <div class="rhe-name"><?= h($e['prenom'] . ' ' . $e['nom']) ?></div>
                <div class="rhe-meta">
                  <?= h($e['fonction'] ?: '—') ?>
                  <?php if ($e['eval_prenom']): ?> · Évalué·e par <?= h($e['eval_prenom'] . ' ' . $e['eval_nom']) ?><?php endif ?>
                </div>
              </div>
              <?php if ($e['secteur_fegems']): ?>
                <span class="rhe-secteur-tag"><?= h($SECTEUR_LABELS[$e['secteur_fegems']] ?? $e['secteur_fegems']) ?></span>
              <?php endif ?>
              <div class="rhe-date">
                <?= h($dateAffiche) ?>
                <small><?= h($jourSemaine) ?> · <?= $e['annee'] ?></small>
              </div>
              <span class="rhe-badge" style="background:<?= $badgeBg ?>;color:<?= $badgeFg ?>"><?= h($badgeLabel) ?></span>
            </div>
          <?php endforeach ?>
        <?php else: ?>
          <div class="rhe-empty">
            <i class="bi bi-clipboard-x"></i>
            <p class="mb-0">Aucun entretien <?= $filtreStatut ? 'au statut "' . h($filtreStatut) . '"' : '' ?> pour <?= $filtreAnnee ?></p>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header bg-white">
        <strong><i class="bi bi-clock"></i> Échéances à venir</strong>
        <span class="text-muted small ms-2"><?= count($echeances) ?> collab</span>
      </div>
      <div class="card-body">
        <?php if ($echeances): ?>
          <?php foreach ($echeances as $ec):
            $jr = (int) $ec['jours_restants'];
            $cls = $jr < 0 ? 'urgent' : ($jr <= 14 ? 'urgent' : ($jr <= 30 ? 'proche' : 'ok'));
            $jrLabel = $jr < 0 ? 'En retard' : ($jr === 0 ? "Aujourd'hui" : "+{$jr}j");
            $dateAffiche = DateTime::createFromFormat('Y-m-d', $ec['prochain_entretien_date'])->format('d/m/Y');
          ?>
            <div class="rhe-echeance-row">
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.86rem"><?= h($ec['prenom'] . ' ' . $ec['nom']) ?></div>
                <div class="rhe-meta"><?= h($ec['fonction'] ?: '—') ?> · <?= h($dateAffiche) ?></div>
              </div>
              <span class="rhe-jrest <?= $cls ?>"><?= h($jrLabel) ?></span>
              <button class="btn btn-sm btn-outline-primary rhe-btn-plan" data-uid="<?= h($ec['id']) ?>" data-name="<?= h($ec['prenom'] . ' ' . $ec['nom']) ?>" data-date="<?= h($ec['prochain_entretien_date']) ?>" title="Planifier">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          <?php endforeach ?>
        <?php else: ?>
          <div class="rhe-empty" style="padding:20px 8px">
            <i class="bi bi-check-circle"></i>
            <p class="mb-0 small">Aucune échéance proche</p>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal nouvel entretien -->
<div class="modal fade" id="rhe-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Planifier un entretien</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="rhe-form">
          <div class="mb-3">
            <label class="form-label small">Collaborateur</label>
            <select class="form-select" name="user_id" required>
              <option value="">— Choisir —</option>
              <?php foreach ($usersActifs as $u): ?>
                <option value="<?= h($u['id']) ?>"><?= h($u['nom'] . ' ' . $u['prenom']) ?> <?= $u['fonction'] ? '· ' . h($u['fonction']) : '' ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Évaluateur·trice</label>
            <select class="form-select" name="evaluator_id">
              <option value="">— Aucun —</option>
              <?php foreach ($evaluateurs as $u): ?>
                <option value="<?= h($u['id']) ?>"><?= h($u['nom'] . ' ' . $u['prenom']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small">Date</label>
              <input type="date" name="date_entretien" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label small">Année</label>
              <input type="number" name="annee" class="form-control" value="<?= $anneeCourante ?>" min="2020" max="2100">
            </div>
          </div>
          <input type="hidden" name="statut" value="planifie">
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary btn-sm" id="rhe-modal-save">Planifier</button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= nonce() ?>">
(function () {
  // Click row → fiche
  document.querySelectorAll('.rhe-row').forEach(row => {
    row.addEventListener('click', () => { location.href = row.dataset.link; });
  });

  // Filter année
  const anneeSelect = document.getElementById('rhe-annee-select');
  anneeSelect?.addEventListener('change', () => {
    const url = new URL(location.href);
    url.searchParams.set('annee', anneeSelect.value);
    location.href = url.toString();
  });

  // Modal new
  const modalEl = document.getElementById('rhe-modal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('rhe-form');

  document.getElementById('rhe-btn-new')?.addEventListener('click', () => {
    form.reset();
    form.querySelector('[name="annee"]').value = <?= $anneeCourante ?>;
    form.querySelector('[name="date_entretien"]').value = '<?= $today->modify('+14 days')->format('Y-m-d') ?>';
    modal.show();
  });

  // Boutons "+" sur échéances : pré-remplir
  document.querySelectorAll('.rhe-btn-plan').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      form.reset();
      form.querySelector('[name="user_id"]').value = btn.dataset.uid;
      form.querySelector('[name="date_entretien"]').value = btn.dataset.date;
      form.querySelector('[name="annee"]').value = <?= $anneeCourante ?>;
      modal.show();
    });
  });

  document.getElementById('rhe-modal-save')?.addEventListener('click', async () => {
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    if (!payload.user_id || !payload.date_entretien) {
      showToast('Collaborateur et date requis', 'danger');
      return;
    }
    try {
      const r = await adminApiPost('save_entretien', payload);
      if (r.success) {
        showToast('Entretien planifié', 'success');
        modal.hide();
        setTimeout(() => location.reload(), 400);
      } else {
        showToast(r.message || 'Erreur', 'danger');
      }
    } catch (err) {
      showToast('Erreur réseau', 'danger');
    }
  });
})();
</script>
