<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$today = date('Y-m-d');

// Cards stats
$totalFormations   = (int) Db::getOne("SELECT COUNT(*) FROM formations");
$totalParticipants = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants");
$totalHeures       = (float) Db::getOne(
    "SELECT COALESCE(SUM(f.duree_heures), 0)
     FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide')"
);
$totalValides      = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants WHERE statut = 'valide'");
$tauxValidation    = $totalParticipants > 0 ? round(($totalValides / $totalParticipants) * 100, 1) : 0;

// Répartition par type
$parType = Db::fetchAll(
    "SELECT type, COUNT(*) AS n FROM formations GROUP BY type ORDER BY n DESC"
);

// Répartition par statut
$parStatut = Db::fetchAll(
    "SELECT statut, COUNT(*) AS n FROM formations GROUP BY statut ORDER BY n DESC"
);

// Top 5 formations (par nb participants)
$topFormations = Db::fetchAll(
    "SELECT f.id, f.titre, f.type, f.statut, f.date_debut, f.duree_heures,
            COUNT(p.id) AS nb_part
     FROM formations f
     LEFT JOIN formation_participants p ON p.formation_id = f.id
     GROUP BY f.id
     HAVING nb_part > 0
     ORDER BY nb_part DESC
     LIMIT 5"
);

// Formations à venir (planifiées avec date_debut >= today)
$aVenir = Db::fetchAll(
    "SELECT id, titre, type, date_debut, date_fin, lieu, max_participants,
            (SELECT COUNT(*) FROM formation_participants p WHERE p.formation_id = f.id) AS nb_part
     FROM formations f
     WHERE statut = 'planifiee' AND date_debut >= ?
     ORDER BY date_debut ASC
     LIMIT 8",
    [$today]
);

// Sessions ce mois
$debutMois = date('Y-m-01');
$finMois   = date('Y-m-t');
$sessionsCeMois = (int) Db::getOne(
    "SELECT COUNT(*) FROM formations
     WHERE (date_debut BETWEEN ? AND ?) OR (date_fin BETWEEN ? AND ?)
        OR (date_debut <= ? AND date_fin >= ?)",
    [$debutMois, $finMois, $debutMois, $finMois, $debutMois, $finMois]
);

$TYPE_LABELS = [
    'interne'   => 'Interne',
    'externe'   => 'Externe',
    'e-learning'=> 'E-learning',
    'certificat'=> 'Certificat',
];
$STATUT_LABELS = [
    'planifiee' => 'Planifiée',
    'en_cours'  => 'En cours',
    'terminee'  => 'Terminée',
    'annulee'   => 'Annulée',
];

$maxType   = max(array_map(fn($r) => (int) $r['n'], $parType ?: [['n' => 0]]));
$maxStatut = max(array_map(fn($r) => (int) $r['n'], $parStatut ?: [['n' => 0]]));
?>
<style>
.rhfs-stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.rhfs-bar-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.rhfs-bar-row:last-child { border-bottom: 0; }
.rhfs-bar-label { min-width: 100px; font-size: .82rem; font-weight: 600; color: var(--cl-text); }
.rhfs-bar-track { flex: 1; height: 10px; background: var(--cl-border-light, #F0EDE8); border-radius: 6px; overflow: hidden; }
.rhfs-bar-fill { height: 100%; border-radius: 6px; transition: width .4s ease; }
.rhfs-bar-fill.bg-teal    { background: #bcd2cb; }
.rhfs-bar-fill.bg-blue    { background: #B8C9D4; }
.rhfs-bar-fill.bg-purple  { background: #D0C4D8; }
.rhfs-bar-fill.bg-sand    { background: #D4C4A8; }
.rhfs-bar-fill.bg-rose    { background: #E2B8AE; }
.rhfs-bar-fill.bg-green   { background: #bcd2cb; }
.rhfs-bar-count { min-width: 40px; text-align: right; font-size: .82rem; font-weight: 600; color: var(--cl-text-muted); }
.rhfs-section-title { font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin: 0 0 12px; }
.rhfs-list { list-style: none; padding: 0; margin: 0; }
.rhfs-list li { padding: 10px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); display: flex; gap: 12px; align-items: center; }
.rhfs-list li:last-child { border-bottom: 0; }
.rhfs-rank { width: 26px; height: 26px; border-radius: 50%; background: var(--cl-bg, #F7F5F2); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; color: var(--cl-text-muted); flex-shrink: 0; }
.rhfs-list-title { flex: 1; min-width: 0; font-size: .88rem; font-weight: 600; color: var(--cl-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rhfs-list-meta { font-size: .72rem; color: var(--cl-text-muted); flex-shrink: 0; }
.rhfs-pill { font-size: .68rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; background: var(--cl-bg, #F7F5F2); color: var(--cl-text); flex-shrink: 0; }
.rhfs-empty { text-align: center; padding: 32px 12px; color: var(--cl-text-muted); font-size: .88rem; }
.rhfs-empty i { font-size: 1.8rem; opacity: .25; display: block; margin-bottom: 6px; }
.rhfs-card { background: #fff; border: 1px solid var(--cl-border-light, #F0EDE8); border-radius: 14px; padding: 18px; height: 100%; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-graph-up"></i> Statistiques formations</h4>
  <a href="?page=rh-formations" class="btn btn-sm btn-outline-secondary" data-page-link><i class="bi bi-list-ul"></i> Voir la liste</a>
</div>

<!-- Cartes stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-teal"><i class="bi bi-mortarboard"></i></div>
      <div>
        <div class="stat-value"><?= $totalFormations ?></div>
        <div class="stat-label">Formations</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-green"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value"><?= $totalParticipants ?></div>
        <div class="stat-label">Inscriptions</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-orange"><i class="bi bi-clock-history"></i></div>
      <div>
        <div class="stat-value"><?= number_format($totalHeures, 1, ',', ' ') ?></div>
        <div class="stat-label">Heures réalisées</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-purple"><i class="bi bi-patch-check"></i></div>
      <div>
        <div class="stat-value"><?= $tauxValidation ?>%</div>
        <div class="stat-label">Taux de validation</div>
      </div>
    </div>
  </div>
</div>

<!-- Répartition + sessions du mois -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-tag"></i> Par type</h5>
      <?php if ($parType): ?>
        <?php foreach ($parType as $r):
            $pct = $maxType > 0 ? round(($r['n'] / $maxType) * 100) : 0;
            $color = ['interne'=>'bg-teal','externe'=>'bg-blue','e-learning'=>'bg-purple','certificat'=>'bg-sand'][$r['type']] ?? 'bg-teal';
        ?>
        <div class="rhfs-bar-row">
          <span class="rhfs-bar-label"><?= h($TYPE_LABELS[$r['type']] ?? $r['type']) ?></span>
          <div class="rhfs-bar-track"><div class="rhfs-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
          <span class="rhfs-bar-count"><?= $r['n'] ?></span>
        </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-bar-chart"></i>Aucune donnée</div>
      <?php endif ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-flag"></i> Par statut</h5>
      <?php if ($parStatut): ?>
        <?php foreach ($parStatut as $r):
            $pct = $maxStatut > 0 ? round(($r['n'] / $maxStatut) * 100) : 0;
            $color = ['planifiee'=>'bg-blue','en_cours'=>'bg-sand','terminee'=>'bg-green','annulee'=>'bg-rose'][$r['statut']] ?? 'bg-teal';
        ?>
        <div class="rhfs-bar-row">
          <span class="rhfs-bar-label"><?= h($STATUT_LABELS[$r['statut']] ?? $r['statut']) ?></span>
          <div class="rhfs-bar-track"><div class="rhfs-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
          <span class="rhfs-bar-count"><?= $r['n'] ?></span>
        </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-bar-chart"></i>Aucune donnée</div>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- Top + à venir -->
<div class="row g-3">
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-trophy"></i> Top 5 — Plus d'inscrits</h5>
      <?php if ($topFormations): ?>
        <ul class="rhfs-list">
          <?php foreach ($topFormations as $i => $f): ?>
          <li>
            <span class="rhfs-rank"><?= $i + 1 ?></span>
            <span class="rhfs-list-title" title="<?= h($f['titre']) ?>"><?= h($f['titre']) ?></span>
            <span class="rhfs-pill"><?= h($TYPE_LABELS[$f['type']] ?? $f['type']) ?></span>
            <span class="rhfs-list-meta"><i class="bi bi-people"></i> <?= $f['nb_part'] ?></span>
          </li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-trophy"></i>Aucune inscription pour le moment</div>
      <?php endif ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-calendar-event"></i> Prochaines sessions <span class="rhfs-pill" style="font-weight:600"><?= $sessionsCeMois ?> ce mois</span></h5>
      <?php if ($aVenir): ?>
        <ul class="rhfs-list">
          <?php foreach ($aVenir as $f):
              $dateAffiche = $f['date_debut']
                ? DateTime::createFromFormat('Y-m-d', $f['date_debut'])->format('d/m/Y')
                : '—';
          ?>
          <li>
            <span class="rhfs-list-meta" style="min-width:80px"><i class="bi bi-calendar3"></i> <?= h($dateAffiche) ?></span>
            <span class="rhfs-list-title" title="<?= h($f['titre']) ?>"><?= h($f['titre']) ?></span>
            <?php if ($f['max_participants']): ?>
              <span class="rhfs-list-meta"><?= $f['nb_part'] ?>/<?= $f['max_participants'] ?></span>
            <?php else: ?>
              <span class="rhfs-list-meta"><i class="bi bi-people"></i> <?= $f['nb_part'] ?></span>
            <?php endif ?>
          </li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-calendar-x"></i>Aucune session à venir</div>
      <?php endif ?>
    </div>
  </div>
</div>
