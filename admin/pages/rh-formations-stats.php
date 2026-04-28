<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$anneeCourante = (int) date('Y');

// Cards stats globales
$totalFormations   = (int) Db::getOne("SELECT COUNT(*) FROM formations");
$totalParticipants = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants");
$totalHeures       = (float) Db::getOne(
    "SELECT COALESCE(SUM(f.duree_heures), 0)
     FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide')"
);
$totalValides   = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants WHERE statut = 'valide'");
$tauxValidation = $totalParticipants > 0 ? round(($totalValides / $totalParticipants) * 100, 1) : 0;

// Heures et collaborateurs formés par secteur FEGEMS
$parSecteur = Db::fetchAll(
    "SELECT COALESCE(fn.secteur_fegems, 'sans_secteur') AS secteur,
            COUNT(DISTINCT p.user_id) AS nb_collab,
            COUNT(p.id) AS nb_inscriptions,
            COALESCE(SUM(CASE WHEN p.statut IN ('present','valide') THEN f.duree_heures END), 0) AS heures
     FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     JOIN users u ON u.id = p.user_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     GROUP BY secteur
     ORDER BY heures DESC"
);

// Top 8 thématiques par participants
$topThematiques = Db::fetchAll(
    "SELECT t.id, t.code, t.nom, t.couleur,
            COUNT(DISTINCT p.id) AS nb_part,
            COALESCE(SUM(CASE WHEN p.statut IN ('present','valide') THEN f.duree_heures END), 0) AS heures
     FROM competences_thematiques t
     JOIN formation_thematiques ft ON ft.thematique_id = t.id
     JOIN formations f ON f.id = ft.formation_id
     LEFT JOIN formation_participants p ON p.formation_id = f.id
     WHERE t.actif = 1
     GROUP BY t.id
     HAVING nb_part > 0
     ORDER BY nb_part DESC
     LIMIT 8"
);

// Budget annuel courant
$budgetTotal     = (float) (Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.annuel_total_chf'") ?: 0);
$seuilAlertePct  = (int) (Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.seuil_alerte_pct'") ?: 80);
$devise          = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.devise'") ?: 'CHF';

$budgetAlloue = (float) Db::getOne(
    "SELECT COALESCE(SUM(budget_alloue), 0) FROM formations WHERE YEAR(date_debut) = ?",
    [$anneeCourante]
);
$coutReel = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.cout_individuel), 0) FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide') AND YEAR(f.date_debut) = ?",
    [$anneeCourante]
);
if ($coutReel == 0) {
    $coutReel = (float) Db::getOne(
        "SELECT COALESCE(SUM(f.cout_formation), 0) FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.statut IN ('present','valide') AND YEAR(f.date_debut) = ?",
        [$anneeCourante]
    );
}
$pctConsomme = $budgetTotal > 0 ? round(($coutReel / $budgetTotal) * 100, 1) : 0;

// Évolution mensuelle (12 derniers mois)
$evolution = Db::fetchAll(
    "SELECT DATE_FORMAT(f.date_debut, '%Y-%m') AS mois,
            COUNT(DISTINCT f.id) AS nb_formations,
            COALESCE(SUM(CASE WHEN p.statut IN ('present','valide') THEN f.duree_heures END), 0) AS heures
     FROM formations f
     LEFT JOIN formation_participants p ON p.formation_id = f.id
     WHERE f.date_debut >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY mois
     ORDER BY mois ASC"
);

// Répartition par type / statut (existant)
$parType   = Db::fetchAll("SELECT type, COUNT(*) AS n FROM formations GROUP BY type ORDER BY n DESC");
$parStatut = Db::fetchAll("SELECT statut, COUNT(*) AS n FROM formations GROUP BY statut ORDER BY n DESC");

// Top 5 formations
$topFormations = Db::fetchAll(
    "SELECT f.id, f.titre, f.type, f.statut, f.date_debut, f.duree_heures,
            COUNT(p.id) AS nb_part
     FROM formations f
     LEFT JOIN formation_participants p ON p.formation_id = f.id
     GROUP BY f.id HAVING nb_part > 0
     ORDER BY nb_part DESC LIMIT 5"
);

$aVenir = Db::fetchAll(
    "SELECT id, titre, type, date_debut, max_participants,
            (SELECT COUNT(*) FROM formation_participants p WHERE p.formation_id = f.id) AS nb_part
     FROM formations f
     WHERE statut = 'planifiee' AND date_debut >= ?
     ORDER BY date_debut ASC LIMIT 6",
    [$today]
);

$TYPE_LABELS = [
    'interne' => 'Interne', 'externe' => 'Externe', 'e-learning' => 'E-learning',
    'certificat' => 'Certificat', 'continue_catalogue' => 'Continue', 'superieur' => 'Supérieur',
    'vae' => 'VAE', 'autodidacte' => 'Autodidacte', 'tutorat' => 'Tutorat', 'fpp' => 'FPP', 'autre' => 'Autre',
];
$STATUT_LABELS = [
    'planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée',
];
$SECTEUR_LABELS = [
    'soins' => 'Soins', 'socio_culturel' => 'Socio-culturel', 'hotellerie' => 'Hôtellerie',
    'maintenance' => 'Maintenance', 'administration' => 'Administration', 'management' => 'Management',
    'sans_secteur' => 'Sans secteur',
];
$SECTEUR_COLORS = [
    'soins' => 'teal', 'socio_culturel' => 'purple', 'hotellerie' => 'sand',
    'maintenance' => 'blue', 'administration' => 'green', 'management' => 'orange',
    'sans_secteur' => 'rose',
];

$maxType    = max(array_map(fn($r) => (int) $r['n'], $parType ?: [['n' => 0]]));
$maxStatut  = max(array_map(fn($r) => (int) $r['n'], $parStatut ?: [['n' => 0]]));
$maxSecteur = max(array_map(fn($r) => (int) $r['heures'], $parSecteur ?: [['heures' => 0]]));
$maxThem    = max(array_map(fn($r) => (int) $r['nb_part'], $topThematiques ?: [['nb_part' => 0]]));
$maxEvol    = max(array_map(fn($r) => (int) $r['heures'], $evolution ?: [['heures' => 0]]));

function fmt_chf(float $v, string $devise = 'CHF'): string
{
    return number_format($v, 0, '.', "'") . ' ' . $devise;
}
?>
<style>
.rhfs-stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.rhfs-bar-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.rhfs-bar-row:last-child { border-bottom: 0; }
.rhfs-bar-label { min-width: 110px; font-size: .82rem; font-weight: 600; color: var(--cl-text); }
.rhfs-bar-track { flex: 1; height: 10px; background: var(--cl-border-light, #F0EDE8); border-radius: 6px; overflow: hidden; }
.rhfs-bar-fill { height: 100%; border-radius: 6px; transition: width .4s ease; }
.rhfs-bar-fill.bg-teal    { background: var(--cl-primary-bg); }
.rhfs-bar-fill.bg-blue    { background: #B8C9D4; }
.rhfs-bar-fill.bg-purple  { background: #D0C4D8; }
.rhfs-bar-fill.bg-sand    { background: #D4C4A8; }
.rhfs-bar-fill.bg-rose    { background: #E2B8AE; }
.rhfs-bar-fill.bg-green   { background: var(--cl-primary-bg); }
.rhfs-bar-fill.bg-orange  { background: #E8C9A0; }
.rhfs-bar-count { min-width: 50px; text-align: right; font-size: .82rem; font-weight: 600; color: var(--cl-text-muted); }
.rhfs-section-title { font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin: 0 0 12px; display: flex; align-items: center; gap: 6px; }
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

/* Budget gauge */
.rhfs-budget-gauge { background: var(--cl-bg, #F7F5F2); border-radius: 14px; padding: 18px; }
.rhfs-budget-bar { height: 18px; background: #F0EDE8; border-radius: 10px; overflow: hidden; position: relative; margin: 14px 0 10px; }
.rhfs-budget-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--cl-primary-bg), var(--cl-primary)); transition: width .6s ease; }
.rhfs-budget-fill.warn { background: linear-gradient(90deg, #E8C9A0, #C8924B); }
.rhfs-budget-fill.danger { background: linear-gradient(90deg, #E2B8AE, #B45F4E); }
.rhfs-budget-marker { position: absolute; top: -6px; bottom: -6px; width: 2px; background: #B45F4E; }
.rhfs-budget-row { display: flex; justify-content: space-between; font-size: .82rem; color: var(--cl-text-muted); }
.rhfs-budget-row strong { color: var(--cl-text); }
.rhfs-budget-num { font-size: 1.6rem; font-weight: 700; color: var(--cl-text); line-height: 1.1; }
.rhfs-budget-sub { font-size: .78rem; color: var(--cl-text-muted); }

/* Mini evolution chart */
.rhfs-evol { display: flex; align-items: flex-end; gap: 4px; height: 80px; padding: 6px 0; }
.rhfs-evol-bar { flex: 1; background: linear-gradient(180deg, var(--cl-primary-bg), #8aa9a0); border-radius: 4px 4px 0 0; min-height: 2px; cursor: pointer; transition: opacity .2s; position: relative; }
.rhfs-evol-bar:hover { opacity: .75; }
.rhfs-evol-bar:hover::after { content: attr(data-tooltip); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: var(--cl-primary); color: #fff; font-size: .7rem; padding: 4px 8px; border-radius: 6px; white-space: nowrap; z-index: 10; margin-bottom: 4px; }
.rhfs-evol-labels { display: flex; gap: 4px; font-size: .64rem; color: var(--cl-text-muted); margin-top: 4px; }
.rhfs-evol-labels span { flex: 1; text-align: center; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-graph-up"></i> Statistiques formations</h4>
  <a href="?page=rh-formations" class="btn btn-sm btn-outline-secondary" data-page-link><i class="bi bi-list-ul"></i> Voir la liste</a>
</div>

<!-- Cartes stats globales -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-teal"><i class="bi bi-mortarboard"></i></div>
      <div><div class="stat-value"><?= $totalFormations ?></div><div class="stat-label">Formations</div></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-green"><i class="bi bi-people"></i></div>
      <div><div class="stat-value"><?= $totalParticipants ?></div><div class="stat-label">Inscriptions</div></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-orange"><i class="bi bi-clock-history"></i></div>
      <div><div class="stat-value"><?= number_format($totalHeures, 1, ',', ' ') ?></div><div class="stat-label">Heures réalisées</div></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-purple"><i class="bi bi-patch-check"></i></div>
      <div><div class="stat-value"><?= $tauxValidation ?>%</div><div class="stat-label">Taux de validation</div></div>
    </div>
  </div>
</div>

<!-- Budget tracker + évolution -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title">
        <i class="bi bi-cash-coin"></i> Budget formation <?= $anneeCourante ?>
        <a href="?page=rh-formations-parametres" class="ms-auto" data-page-link
           style="font-size:.72rem;color:var(--cl-text-muted);text-decoration:none">
          <i class="bi bi-gear"></i> Configurer
        </a>
      </h5>

      <?php if ($budgetTotal > 0): ?>
        <?php $cls = $pctConsomme >= 100 ? 'danger' : ($pctConsomme >= $seuilAlertePct ? 'warn' : ''); ?>
        <div class="d-flex justify-content-between align-items-baseline">
          <div>
            <div class="rhfs-budget-num"><?= h(fmt_chf($coutReel, $devise)) ?></div>
            <div class="rhfs-budget-sub">consommé sur <?= h(fmt_chf($budgetTotal, $devise)) ?></div>
          </div>
          <div class="text-end">
            <div class="rhfs-budget-num"><?= $pctConsomme ?>%</div>
            <div class="rhfs-budget-sub">
              <?php if ($pctConsomme >= 100): ?>
                <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Dépassé</span>
              <?php elseif ($pctConsomme >= $seuilAlertePct): ?>
                <span class="text-warning"><i class="bi bi-exclamation-circle"></i> Seuil <?= $seuilAlertePct ?>%</span>
              <?php else: ?>
                <span class="text-success"><i class="bi bi-check-circle"></i> Sain</span>
              <?php endif ?>
            </div>
          </div>
        </div>
        <div class="rhfs-budget-bar">
          <div class="rhfs-budget-fill <?= $cls ?>" style="width:<?= min(100, $pctConsomme) ?>%"></div>
          <div class="rhfs-budget-marker" style="left:<?= min(99, $seuilAlertePct) ?>%"
               title="Seuil d'alerte <?= $seuilAlertePct ?>%"></div>
        </div>
        <div class="rhfs-budget-row">
          <span>Reste : <strong><?= h(fmt_chf(max(0, $budgetTotal - $coutReel), $devise)) ?></strong></span>
          <span>Alloué (planifié) : <strong><?= h(fmt_chf($budgetAlloue, $devise)) ?></strong></span>
        </div>
      <?php else: ?>
        <div class="rhfs-empty" style="padding:18px 12px">
          <i class="bi bi-piggy-bank"></i>
          Aucun budget annuel défini.
          <div class="mt-2"><a href="?page=rh-formations-parametres" data-page-link class="small">Définir le budget</a></div>
        </div>
      <?php endif ?>
    </div>
  </div>

  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-bar-chart-steps"></i> Évolution heures · 12 derniers mois</h5>
      <?php if ($evolution): ?>
        <div class="rhfs-evol">
          <?php foreach ($evolution as $e):
            $h = $maxEvol > 0 ? max(2, round(($e['heures'] / $maxEvol) * 80)) : 2;
            $moisLabel = DateTime::createFromFormat('Y-m', $e['mois'])->format('M Y');
          ?>
            <div class="rhfs-evol-bar" style="height:<?= $h ?>px"
                 data-tooltip="<?= h($moisLabel) ?> · <?= number_format($e['heures'], 0, ',', ' ') ?>h"></div>
          <?php endforeach ?>
        </div>
        <div class="rhfs-evol-labels">
          <?php $first = true; foreach ($evolution as $e):
            $label = DateTime::createFromFormat('Y-m', $e['mois'])->format('M');
          ?>
            <span><?= $first || strpos($e['mois'], '-01') !== false ? h($label) : '' ?></span>
          <?php $first = false; endforeach ?>
        </div>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-graph-up"></i>Aucune formation sur les 12 derniers mois</div>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- Heures par secteur FEGEMS + Top thématiques -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-diagram-3"></i> Heures par secteur FEGEMS</h5>
      <?php if ($parSecteur): ?>
        <?php foreach ($parSecteur as $r):
          $pct = $maxSecteur > 0 ? round(($r['heures'] / $maxSecteur) * 100) : 0;
          $color = 'bg-' . ($SECTEUR_COLORS[$r['secteur']] ?? 'teal');
        ?>
          <div class="rhfs-bar-row">
            <span class="rhfs-bar-label"><?= h($SECTEUR_LABELS[$r['secteur']] ?? $r['secteur']) ?></span>
            <div class="rhfs-bar-track"><div class="rhfs-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
            <span class="rhfs-bar-count" title="<?= $r['nb_collab'] ?> collab · <?= $r['nb_inscriptions'] ?> inscriptions">
              <?= number_format($r['heures'], 0, ',', ' ') ?>h
            </span>
          </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-bar-chart"></i>Aucune donnée</div>
      <?php endif ?>
    </div>
  </div>

  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-tags"></i> Top thématiques formées</h5>
      <?php if ($topThematiques): ?>
        <?php foreach ($topThematiques as $r):
          $pct = $maxThem > 0 ? round(($r['nb_part'] / $maxThem) * 100) : 0;
          $color = 'bg-' . ($r['couleur'] ?? 'teal');
        ?>
          <div class="rhfs-bar-row">
            <span class="rhfs-bar-label" title="<?= h($r['code']) ?>"><?= h($r['nom']) ?></span>
            <div class="rhfs-bar-track"><div class="rhfs-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
            <span class="rhfs-bar-count" title="<?= number_format($r['heures'], 0, ',', ' ') ?>h"><?= $r['nb_part'] ?></span>
          </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="rhfs-empty"><i class="bi bi-tag"></i>Aucune thématique liée</div>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- Type / Statut -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-tag"></i> Par type</h5>
      <?php if ($parType): ?>
        <?php foreach ($parType as $r):
          $pct = $maxType > 0 ? round(($r['n'] / $maxType) * 100) : 0;
          $color = ['interne' => 'bg-teal', 'externe' => 'bg-blue', 'e-learning' => 'bg-purple', 'certificat' => 'bg-sand'][$r['type']] ?? 'bg-teal';
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
          $color = ['planifiee' => 'bg-blue', 'en_cours' => 'bg-sand', 'terminee' => 'bg-green', 'annulee' => 'bg-rose'][$r['statut']] ?? 'bg-teal';
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

<!-- Top 5 + à venir -->
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
        <div class="rhfs-empty"><i class="bi bi-trophy"></i>Aucune inscription</div>
      <?php endif ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="rhfs-card">
      <h5 class="rhfs-section-title"><i class="bi bi-calendar-event"></i> Prochaines sessions</h5>
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
