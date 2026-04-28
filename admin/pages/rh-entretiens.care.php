<?php
// ─── Entretiens annuels · version Care (Spocspace DS v1.0) ───────────────────

$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');
$anneeCourante = (int) $today->format('Y');

// Stats
$nbPlanifies = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien >= ?", [$todayStr]
);
$nbRealises = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'realise' AND YEAR(date_entretien) = ?", [$anneeCourante]
);
$nbEnRetard = (int) Db::getOne(
    "SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien < ?", [$todayStr]
);
$nbAEcheance = (int) Db::getOne(
    "SELECT COUNT(*) FROM users WHERE is_active = 1
     AND prochain_entretien_date IS NOT NULL
     AND prochain_entretien_date <= DATE_ADD(?, INTERVAL 30 DAY)
     AND NOT EXISTS (SELECT 1 FROM entretiens_annuels e WHERE e.user_id = users.id AND e.statut = 'planifie' AND e.date_entretien >= CURDATE())",
    [$todayStr]
);

// Filtres
$filtreStatut = $_GET['statut'] ?? '';
$filtreAnnee  = (int) ($_GET['annee'] ?? $anneeCourante);

$where = ['1=1']; $binds = [];
if (in_array($filtreStatut, ['planifie', 'realise', 'reporte', 'annule'], true)) {
    $where[] = 'e.statut = ?'; $binds[] = $filtreStatut;
}
if ($filtreAnnee) { $where[] = 'e.annee = ?'; $binds[] = $filtreAnnee; }

$entretiens = Db::fetchAll(
    "SELECT e.id, e.user_id, e.annee, e.date_entretien, e.statut, e.signed_at,
            u.prenom, u.nom, u.email, u.photo,
            fn.nom AS fonction, fn.secteur_fegems,
            ev.prenom AS eval_prenom, ev.nom AS eval_nom
     FROM entretiens_annuels e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     LEFT JOIN users ev ON ev.id = e.evaluator_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY e.date_entretien IS NULL, e.date_entretien DESC, u.nom ASC",
    $binds
);

$echeances = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.photo, u.prochain_entretien_date,
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
     ORDER BY u.prochain_entretien_date ASC LIMIT 30"
);

$usersActifs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, fn.nom AS fonction
     FROM users u LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE u.is_active = 1 ORDER BY u.nom, u.prenom"
);
$evaluateurs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom FROM users u
     WHERE u.is_active = 1 AND u.role IN ('responsable','admin','direction') ORDER BY u.nom"
);

$STATUT_BADGE = [
    'planifie' => ['Planifié', 'planning'],
    'realise'  => ['Réalisé',  'realisee'],
    'reporte'  => ['Reporté',  'encours'],
    'annule'   => ['Annulé',   'absent'],
];
$SECTEUR_LABELS = [
    'soins' => ['Soins', 's-soins'], 'socio_culturel' => ['Socio-culturel', 's-anim'],
    'hotellerie' => ['Hôtellerie', 's-hot'], 'maintenance' => ['Maintenance', 's-tech'],
    'administration' => ['Administration', 's-admin'], 'management' => ['Management', 's-mgmt'],
];

$anneesDispos = Db::fetchAll("SELECT DISTINCT annee FROM entretiens_annuels ORDER BY annee DESC");
$anneesArr = array_column($anneesDispos, 'annee');
if (!in_array($anneeCourante, $anneesArr)) array_unshift($anneesArr, $anneeCourante);

function avatar_color($seed) {
    $colors = ['#1f6359', '#8a5a1a', '#2d4a6b', '#5e3a78', '#6b5a1f', '#8a3a30', '#3a5a2d'];
    $h = 0; $s = (string) $seed;
    for ($i = 0; $i < strlen($s); $i++) $h = ($h * 31 + ord($s[$i])) % 1000;
    return $colors[$h % count($colors)];
}
?>
<div style="max-width:1280px;margin:0 auto;padding:24px 28px">

  <!-- Header -->
  <div class="rhe-header">
    <div>
      <h1 class="rhe-h1">Entretiens annuels</h1>
      <p class="rhe-h1-sub">
        Suivi des bilans individuels avec vos collaborateurs. Planifiez, réalisez et tracez les objectifs.
      </p>
    </div>
    <div class="rhe-actions">
      <select class="rhe-select" id="rhe-annee-select">
        <?php foreach ($anneesArr as $a): ?>
          <option value="<?= $a ?>" <?= $filtreAnnee == $a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach ?>
      </select>
      <button class="careb-btn-primary-sm" id="rhe-btn-new"><i class="bi bi-plus-lg"></i> Planifier un entretien</button>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="careb-kpi-row" style="margin-bottom:24px">
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">PLANIFIÉS À VENIR</div>
      <div class="careb-kpi-val"><?= $nbPlanifies ?></div>
      <div class="careb-kpi-sub">au-delà d'aujourd'hui</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">RÉALISÉS <?= $anneeCourante ?></div>
      <div class="careb-kpi-val careb-kpi-val--ok"><?= $nbRealises ?></div>
      <div class="careb-kpi-sub">cette année</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">EN RETARD</div>
      <div class="careb-kpi-val careb-kpi-val--danger"><?= $nbEnRetard ?></div>
      <div class="careb-kpi-sub">à reprogrammer</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">À ÉCHÉANCE (30J)</div>
      <div class="careb-kpi-val careb-kpi-val--warn"><?= $nbAEcheance ?></div>
      <div class="careb-kpi-sub">à planifier bientôt</div>
    </div>
  </div>

  <!-- Onglets filtres -->
  <div class="rhe-tabs">
    <a href="?page=rh-entretiens<?= $filtreAnnee != $anneeCourante ? '&annee='.$filtreAnnee : '' ?>"
       class="rhe-tab <?= $filtreStatut === '' ? 'on' : '' ?>" data-page-link>
      Tous
    </a>
    <a href="?page=rh-entretiens&statut=planifie<?= $filtreAnnee != $anneeCourante ? '&annee='.$filtreAnnee : '' ?>"
       class="rhe-tab <?= $filtreStatut === 'planifie' ? 'on' : '' ?>" data-page-link>
      Planifiés
    </a>
    <a href="?page=rh-entretiens&statut=realise<?= $filtreAnnee != $anneeCourante ? '&annee='.$filtreAnnee : '' ?>"
       class="rhe-tab <?= $filtreStatut === 'realise' ? 'on' : '' ?>" data-page-link>
      Réalisés
    </a>
    <a href="?page=rh-entretiens&statut=reporte<?= $filtreAnnee != $anneeCourante ? '&annee='.$filtreAnnee : '' ?>"
       class="rhe-tab <?= $filtreStatut === 'reporte' ? 'on' : '' ?>" data-page-link>
      Reportés
    </a>
  </div>

  <div class="row g-3" style="display:grid;grid-template-columns:2fr 1fr;gap:16px">

    <!-- Liste entretiens -->
    <div>
      <div class="careb-card">
        <div class="careb-card-head">
          <div>
            <div class="careb-card-title">
              <?= $filtreStatut === '' ? 'Tous les entretiens' : ucfirst($filtreStatut) ?>
              <span class="careb-tab-count" style="margin-left:6px"><?= count($entretiens) ?></span>
            </div>
            <div class="careb-card-sub">Cliquez une ligne pour voir la fiche complète</div>
          </div>
        </div>

        <?php if (!$entretiens): ?>
          <div class="ds-empty">
            <i class="bi bi-clipboard-x"></i>
            <div class="ds-empty-title">Aucun entretien <?= $filtreStatut ? '« '.h($filtreStatut).' »' : '' ?> pour <?= $filtreAnnee ?></div>
            <div class="ds-empty-msg">Utilisez le bouton « Planifier un entretien » en haut.</div>
          </div>
        <?php else: ?>
          <?php foreach ($entretiens as $e):
            $initials = strtoupper(mb_substr($e['prenom'], 0, 1) . mb_substr($e['nom'], 0, 1));
            [$badgeLbl, $badgeCls] = $STATUT_BADGE[$e['statut']] ?? [$e['statut'], 'inscrite'];
            $secMeta = $SECTEUR_LABELS[$e['secteur_fegems']] ?? null;
            $dateAffichee = $e['date_entretien']
              ? DateTime::createFromFormat('Y-m-d', $e['date_entretien'])->format('d.m.Y')
              : '—';
            $jourSemaine = $e['date_entretien']
              ? ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'][(int) date('w', strtotime($e['date_entretien']))]
              : '';
          ?>
            <div class="rhe-row" data-link="<?= h(admin_url('rh-entretiens-fiche', $e['id'])) ?>">
              <div class="rhe-avatar" style="background:<?= h(avatar_color($e['user_id'])) ?>">
                <?php if ($e['photo']): ?>
                  <img src="<?= h($e['photo']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                <?php else: ?><?= h($initials) ?><?php endif ?>
              </div>
              <div class="rhe-info">
                <div class="rhe-name"><?= h($e['prenom'] . ' ' . $e['nom']) ?></div>
                <div class="rhe-meta">
                  <?= h($e['fonction'] ?: '—') ?>
                  <?php if ($e['eval_prenom']): ?>
                    · Évalué·e par <?= h($e['eval_prenom'] . ' ' . $e['eval_nom']) ?>
                  <?php endif ?>
                </div>
              </div>
              <?php if ($secMeta): ?>
                <span class="sector-tag <?= h($secMeta[1]) ?>"><span class="bullet"></span><?= h($secMeta[0]) ?></span>
              <?php else: ?>
                <span></span>
              <?php endif ?>
              <div class="rhe-date">
                <div class="rhe-date-num t-num"><?= h($dateAffichee) ?></div>
                <div class="t-meta"><?= h($jourSemaine) ?> · <?= h($e['annee']) ?></div>
              </div>
              <span class="careb-status careb-status--<?= h($badgeCls) ?>"><?= h(strtoupper($badgeLbl)) ?></span>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>

    <!-- Échéances -->
    <div>
      <div class="careb-card">
        <div class="careb-card-head">
          <div>
            <div class="careb-card-title">Échéances à venir</div>
            <div class="careb-card-sub"><?= count($echeances) ?> collaborateur<?= count($echeances) > 1 ? 's' : '' ?></div>
          </div>
        </div>

        <?php if (!$echeances): ?>
          <div class="ds-empty" style="padding:30px 16px">
            <i class="bi bi-check-circle"></i>
            <div class="ds-empty-title">Aucune échéance proche</div>
          </div>
        <?php else: ?>
          <?php foreach ($echeances as $ec):
            $jr = (int) $ec['jours_restants'];
            $cls = $jr < 0 ? 'urgent' : ($jr <= 14 ? 'urgent' : ($jr <= 30 ? 'proche' : 'ok'));
            $jrLabel = $jr < 0 ? 'En retard' : ($jr === 0 ? "Aujourd'hui" : "+{$jr}j");
            $dateAff = DateTime::createFromFormat('Y-m-d', $ec['prochain_entretien_date'])->format('d.m.Y');
            $initEc = strtoupper(mb_substr($ec['prenom'], 0, 1) . mb_substr($ec['nom'], 0, 1));
          ?>
            <div class="rhe-eche-row">
              <div class="rhe-avatar rhe-avatar--sm" style="background:<?= h(avatar_color($ec['id'])) ?>">
                <?= h($initEc) ?>
              </div>
              <div class="rhe-info">
                <div class="rhe-name" style="font-size:13px"><?= h($ec['prenom'] . ' ' . $ec['nom']) ?></div>
                <div class="rhe-meta"><?= h($ec['fonction'] ?: '—') ?> · <span class="t-num"><?= h($dateAff) ?></span></div>
              </div>
              <span class="rhe-jrest rhe-jrest--<?= $cls ?>"><?= h($jrLabel) ?></span>
              <button class="careb-btn-soft rhe-btn-plan" data-uid="<?= h($ec['id']) ?>" data-name="<?= h($ec['prenom'] . ' ' . $ec['nom']) ?>" data-date="<?= h($ec['prochain_entretien_date']) ?>" title="Planifier">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal nouvel entretien -->
<div class="modal fade" id="rhe-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
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

<style>
.rhe-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 24px; flex-wrap: wrap; margin-bottom: 24px;
}
.rhe-h1 { font-family: var(--font-display, Fraunces, serif); font-size: 32px; font-weight: 500; color: var(--ink, #0d2a26); letter-spacing: -.02em; margin: 0; }
.rhe-h1-sub { font-size: 14px; color: var(--ink-2, #324e4a); margin-top: 6px; max-width: 720px; line-height: 1.5; }
.rhe-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }

.rhe-select {
  padding: 7px 12px; border: 1px solid var(--line, #e3ebe8); border-radius: 8px;
  background: var(--surface, #fff); font-size: 13px; color: var(--ink, #0d2a26);
  font-family: var(--font-mono, monospace);
}

.rhe-tabs {
  display: flex; gap: 4px; border-bottom: 1px solid var(--line, #e3ebe8); margin-bottom: 18px;
}
.rhe-tab {
  padding: 10px 18px; font-size: 13px; font-weight: 500; color: var(--muted, #6b8783);
  cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
  text-decoration: none; transition: color .12s ease;
}
.rhe-tab:hover { color: var(--ink-2, #324e4a); }
.rhe-tab.on { color: var(--teal-700, #164a42); border-bottom-color: var(--teal-600, #1f6359); font-weight: 600; }

.rhe-row {
  display: grid; grid-template-columns: 40px 1fr 130px 110px 90px;
  gap: 16px; align-items: center; padding: 14px 22px;
  border-bottom: 1px solid var(--line, #e3ebe8); cursor: pointer; transition: background .12s ease;
}
.rhe-row:hover { background: var(--surface-2, #fafbfa); }
.rhe-row:last-child { border-bottom: 0; }

.rhe-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 600; font-size: 13px;
  flex-shrink: 0; overflow: hidden;
}
.rhe-avatar--sm { width: 32px; height: 32px; font-size: 11px; }

.rhe-name { font-weight: 600; color: var(--ink, #0d2a26); font-size: 14px; }
.rhe-meta { font-size: 12px; color: var(--muted, #6b8783); margin-top: 2px; }

.rhe-date { text-align: right; }
.rhe-date-num { font-size: 13px; font-weight: 600; color: var(--ink, #0d2a26); font-family: var(--font-mono, monospace); }

.rhe-eche-row {
  display: grid; grid-template-columns: 32px 1fr auto auto;
  gap: 10px; align-items: center; padding: 12px 22px;
  border-bottom: 1px solid var(--line, #e3ebe8);
}
.rhe-eche-row:last-child { border-bottom: 0; }

.rhe-jrest {
  font-size: 11px; padding: 3px 9px; border-radius: 99px;
  font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
}
.rhe-jrest--urgent { background: var(--danger-bg, #f7e3e0); color: var(--danger, #b8443a); }
.rhe-jrest--proche { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); }
.rhe-jrest--ok     { background: var(--ok-bg, #e3f0ea); color: var(--ok, #3d8b6b); }

@media (max-width: 991px) {
  .row.g-3 { grid-template-columns: 1fr !important; }
  .rhe-row { grid-template-columns: 40px 1fr 90px; }
  .rhe-row .sector-tag, .rhe-row .rhe-date { display: none; }
}
</style>

<script<?= nonce() ?>>
(function () {
  // Click row → fiche
  document.querySelectorAll('.rhe-row').forEach(row => {
    row.addEventListener('click', () => { location.href = row.dataset.link; });
  });

  // Filter année
  const anneeSel = document.getElementById('rhe-annee-select');
  anneeSel?.addEventListener('change', () => {
    const url = new URL(location.href);
    url.searchParams.set('annee', anneeSel.value);
    location.href = url.toString();
  });

  // Modal new
  const modalEl = document.getElementById('rhe-modal');
  if (!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('rhe-form');

  document.getElementById('rhe-btn-new')?.addEventListener('click', () => {
    form.reset();
    form.querySelector('[name="annee"]').value = <?= $anneeCourante ?>;
    const d = new Date(); d.setDate(d.getDate() + 14);
    form.querySelector('[name="date_entretien"]').value = d.toISOString().slice(0, 10);
    modal.show();
  });

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
      const r = await adminApiPost('admin_save_entretien', payload);
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
