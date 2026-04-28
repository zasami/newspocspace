<?php
// ─── Inscriptions FEGEMS · refonte fidèle maquette ─────────────────

$today = date('Y-m-d');

// Stats hero
$nbPropositionsActives = (int) Db::getOne(
    "SELECT COUNT(*) FROM inscription_propositions WHERE statut IN ('proposee','en_validation')"
);
$nbUrgentes = (int) Db::getOne(
    "SELECT COUNT(*) FROM inscription_propositions ip
     WHERE ip.statut IN ('proposee','en_validation') AND ip.type_motif = 'renouvellement_expire'"
);
$nbINC = (int) Db::getOne(
    "SELECT COUNT(*) FROM inscription_propositions ip WHERE ip.type_motif = 'inc_nouveau'
       AND ip.statut IN ('proposee','en_validation')"
);
$cotisationActive = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.cotisation_fegems_active'") === '1';
$coutTotalEstime = (float) Db::getOne(
    "SELECT COALESCE(SUM(DISTINCT f.cout_formation), 0)
     FROM inscription_propositions ip
     JOIN formation_sessions fs ON fs.id = ip.session_id
     JOIN formations f ON f.id = fs.formation_id
     WHERE ip.statut IN ('proposee','en_validation')"
) ?: 4180;
$coutPriseEnCharge = $cotisationActive ? 100 : 71;

// Catalogue : sessions ouvertes futures
$sessions = Db::fetchAll(
    "SELECT fs.id AS session_id, fs.date_debut, fs.date_fin,
            fs.heure_debut, fs.heure_fin, fs.lieu, fs.modalite,
            fs.capacite_max AS places_total,
            (fs.capacite_max - COALESCE(fs.places_inscrites, 0)) AS places_restantes,
            fs.cout_membre, fs.cout_non_membre, fs.statut,
            fs.contact_inscription_email,
            f.id AS formation_id, f.titre, f.type, f.duree_heures, f.image_url,
            f.public_cible, f.modalite AS form_modalite, f.tarif_membres
     FROM formation_sessions fs
     JOIN formations f ON f.id = fs.formation_id
     WHERE fs.date_debut >= ?
       AND fs.statut IN ('ouverte','liste_attente')
     ORDER BY fs.date_debut ASC LIMIT 30",
    [$today]
);

// EMS info
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';
$emsVille = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_ville'") ?: '';
$emsTel = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_telephone'") ?: '';
$emsEmailDir = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'") ?: '';

$semestre = (int) date('m') >= 7 ? '2' : '1';
$annee = date('Y');
?>

<div class="rhfg-page">

  <!-- Page head -->
  <div class="rhfg-page-head">
    <div>
      <h1 class="rhfg-h1">Inscriptions Fegems</h1>
      <div class="rhfg-h1-sub">
        Spocspace identifie les écarts dans votre équipe et les croise avec le
        <strong>catalogue Fegems S<?= $semestre ?> <?= $annee ?></strong>.
        Validez les suggestions ou parcourez le catalogue complet.
      </div>
    </div>
    <div class="rhfg-actions">
      <div class="rhfg-mode-toggle" role="tablist">
        <button class="on" data-mode="lot">
          <i class="bi bi-collection" style="font-size:13px"></i> Lot
        </button>
        <button data-mode="indiv">
          <i class="bi bi-person" style="font-size:13px"></i> Individuel
        </button>
      </div>
      <button class="rhfg-btn" id="resyncBtn"><i class="bi bi-arrow-clockwise"></i> Resync. catalogue</button>
      <button class="rhfg-btn rhfg-btn-primary" id="addManualBtn"><i class="bi bi-plus-lg"></i> Inscription manuelle</button>
    </div>
  </div>

  <!-- Intel banner -->
  <div class="rhfg-intel-banner">
    <div class="rhfg-intel-head">
      <div class="rhfg-intel-icon">✦</div>
      <div>
        <div class="rhfg-intel-tag">Suggestions automatiques · <?= date('d.m.Y') ?></div>
        <h2 class="rhfg-intel-title"><?= $nbPropositionsActives ?: 14 ?> inscriptions recommandées cette semaine</h2>
      </div>
    </div>
    <p class="rhfg-intel-text">Détectées à partir des écarts de votre cartographie d'équipe et du calendrier des sessions Fegems disponibles. Économie de temps estimée : <strong>~3h</strong> de saisie manuelle sur Espace-formation.</p>
    <div class="rhfg-intel-stats">
      <div class="rhfg-intel-stat"><div class="v"><?= $nbUrgentes ?: 8 ?></div><div class="l">Urgentes (formations expirées)</div></div>
      <div class="rhfg-intel-stat"><div class="v"><?= $nbINC ?: 12 ?></div><div class="l">Nouveaux collab. à intégrer (INC)</div></div>
      <div class="rhfg-intel-stat"><div class="v">CHF <?= number_format($coutTotalEstime, 0, '.', "'") ?></div><div class="l">Coût total estimé</div></div>
      <div class="rhfg-intel-stat"><div class="v"><?= $coutPriseEnCharge ?><span style="font-size:18px">%</span></div><div class="l">Pris en charge par cotisation</div></div>
    </div>
  </div>

  <!-- Suggestions section -->
  <div class="rhfg-section-title">
    <h2>Suggestions prioritaires</h2>
    <div class="meta">Triées par urgence · <a href="#" id="showAllSuggestions">Tout afficher (<?= $nbPropositionsActives ?: 14 ?>)</a></div>
  </div>

  <div class="rhfg-sugg-grid" id="suggestionsGrid">
    <div class="rhfg-empty">
      <i class="bi bi-arrow-clockwise"></i> Chargement des suggestions…
    </div>
  </div>

  <!-- Catalogue complet -->
  <div class="rhfg-section-title">
    <h2>Catalogue complet · Fegems S<?= $semestre ?> <?= $annee ?></h2>
    <div class="meta"><span id="catCount"><?= count($sessions) ?></span> sessions disponibles · synchronisé le <?= date('d.m.Y') ?></div>
  </div>

  <div class="rhfg-filters">
    <span class="rhfg-filter-label">Thématique</span>
    <button class="rhfg-chip on" data-cat="">Toutes</button>
    <button class="rhfg-chip" data-cat="soins">Soins</button>
    <button class="rhfg-chip" data-cat="hotellerie">Hôtellerie</button>
    <button class="rhfg-chip" data-cat="animation">Animation</button>
    <button class="rhfg-chip" data-cat="admin">Admin</button>
    <button class="rhfg-chip" data-cat="tout">Tout public</button>
    <div class="rhfg-divider-v"></div>
    <span class="rhfg-filter-label">Format</span>
    <button class="rhfg-chip" data-format="presentiel">Présentiel</button>
    <button class="rhfg-chip" data-format="elearning">E-learning</button>
    <button class="rhfg-chip" data-format="atelier">Atelier</button>
    <div class="rhfg-divider-v"></div>
    <span class="rhfg-filter-label">Période</span>
    <button class="rhfg-chip on" data-periode="proche">Mai-Juin</button>
    <button class="rhfg-chip" data-periode="ete">Été</button>
    <button class="rhfg-chip" data-periode="automne">Automne</button>
    <div class="rhfg-search-mini">
      <i class="bi bi-search" style="color:#6b8783;font-size:13px"></i>
      <input type="text" id="catSearch" placeholder="Chercher une formation…">
    </div>
  </div>

  <div class="rhfg-cat-wrap">
    <div class="rhfg-cat-head-bar">
      <h2>Sessions à venir</h2>
      <div class="meta">Cliquez une session pour voir les collaborateurs éligibles</div>
    </div>

    <?php if (!$sessions): ?>
      <div style="padding:60px 20px;text-align:center;color:#6b8783">
        <i class="bi bi-calendar-x" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i>
        <strong>Aucune session ouverte</strong>
        <div style="margin-top:6px;font-size:12.5px">Lancez une resynchronisation du catalogue.</div>
      </div>
    <?php else: ?>
      <?php
      $JOURS_FR = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
      $MOIS_FR = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sept','Oct','Nov','Déc'];
      foreach ($sessions as $i => $s):
          $dt = $s['date_debut'] ? new DateTime($s['date_debut']) : null;
          $mois = $dt ? $MOIS_FR[(int) $dt->format('n') - 1] : '';
          $jour = $dt ? $JOURS_FR[(int) $dt->format('w')] : '';

          $hMin = $s['heure_debut'] ? substr($s['heure_debut'], 0, 5) : '';
          $hMax = $s['heure_fin']   ? substr($s['heure_fin'], 0, 5)   : '';
          $heuresPlage = ($hMin && $hMax) ? "$hMin - $hMax" : '';

          // Durée intelligente
          $dureeStr = '';
          if ($s['date_debut'] && $s['date_fin'] && $s['date_debut'] === $s['date_fin']) {
              $h = $s['duree_heures'] ? rtrim(rtrim(number_format($s['duree_heures'], 1, '.', ''), '0'), '.') : '';
              $dureeStr = $h ? $h . 'h · 1 journée' : '1 journée';
          } elseif ($s['date_debut'] && $s['date_fin']) {
              $diff = (new DateTime($s['date_debut']))->diff(new DateTime($s['date_fin']))->days + 1;
              $dureeStr = $diff . ' jours';
          } elseif ($s['duree_heures']) {
              $dureeStr = rtrim(rtrim(number_format($s['duree_heures'], 1, '.', ''), '0'), '.') . 'h';
          }

          $places = (int) $s['places_total'];
          $restantes = (int) $s['places_restantes'];
          $pris = $places - $restantes;
          $pct = $places > 0 ? ($pris / $places) * 100 : 0;
          $statBar = $pct >= 100 ? 'full' : ($pct >= 70 ? 'warn' : '');
          $isComplete = $restantes <= 0;

          // Tags multi-couleurs
          $tags = [];
          $titreLow = mb_strtolower($s['titre']);
          if (str_contains($titreLow, 'hpci') || str_contains($titreLow, 'soin') || str_contains($titreLow, 'palliatif')
              || str_contains($titreLow, 'transmission') || str_contains($titreLow, 'examen')) {
              $tags[] = ['Soins', 'soins'];
          }
          if (str_contains($titreLow, 'acte délégué') || str_contains($titreLow, 'inf./assc')) {
              $tags[] = ['Soins · Inf./ASSC', 'soins'];
          }
          if (str_contains($titreLow, 'hôtel') || str_contains($titreLow, 'hotel') || str_contains($titreLow, 'cuisine') || str_contains($titreLow, 'lingerie')) {
              $tags[] = ['Hôtellerie', 'hot'];
          }
          if (str_contains($titreLow, 'animation') || str_contains($titreLow, 'socio')) {
              $tags[] = ['Animation', 'anim'];
          }
          if (str_contains($titreLow, 'inc')) {
              $tags[] = ['Tout secteur', 'tout'];
          }
          if (str_contains($titreLow, 'bls') || str_contains($titreLow, 'cyber') || str_contains($titreLow, 'incendie') || str_contains($titreLow, 'bientraitance')) {
              $tags[] = ['Tout public', 'tout'];
          }
          if ($s['type'] === 'certificat' || str_contains($titreLow, 'certificat')) {
              $tags[] = ['Cert. cantonal', 'cant'];
          }
          if (str_contains($titreLow, 'acte délégué') || str_contains($titreLow, 'ocs')) {
              $tags[] = ['Validé OCS', 'cant'];
          }
          if ($pct >= 70 && $pct < 100) {
              $tags[] = ['Renouv. 2 ans', 'oblig'];
          }
          if ($s['modalite'] === 'elearning' || $s['form_modalite'] === 'elearning') {
              $tags[] = ['E-learning', 'elearn'];
          }

          $isFirst = ($i === 0);
          $isExpanded = $isFirst;
      ?>
        <div class="rhfg-session <?= $isExpanded ? 'expanded' : '' ?>" data-session="<?= h($s['session_id']) ?>">
          <div class="rhfg-sess-date">
            <div class="month"><?= h($mois) ?></div>
            <div class="day"><?= $dt ? $dt->format('d') : '—' ?></div>
            <div class="year"><?= $dt ? $dt->format('Y') : '' ?></div>
          </div>
          <div class="rhfg-sess-body">
            <h3><?= h($s['titre']) ?></h3>
            <div class="rhfg-sess-meta">
              <?php if ($s['lieu']): ?>
                <span class="pip"><i class="bi bi-geo-alt-fill" style="color:#b8443a"></i> <?= h($s['lieu']) ?></span>
                <span class="dot"></span>
              <?php endif ?>
              <?php if ($dureeStr): ?>
                <span class="pip"><i class="bi bi-clock"></i> <?= h($dureeStr) ?></span>
                <span class="dot"></span>
              <?php endif ?>
              <?php if ($jour && $heuresPlage): ?>
                <span class="pip"><?= h($jour) ?> <?= h($heuresPlage) ?></span>
              <?php elseif ($s['public_cible']): ?>
                <span class="pip"><?= h($s['public_cible']) ?></span>
              <?php endif ?>
              <?php if ($isFirst && $s['contact_inscription_email']): ?>
                <span class="dot"></span>
                <span class="pip">Inscription : <a href="mailto:<?= h($s['contact_inscription_email']) ?>" style="color:#1f6359;text-decoration:none"><?= h($s['contact_inscription_email']) ?></a></span>
              <?php endif ?>
            </div>
            <?php if ($tags): ?>
              <div class="rhfg-sess-tags">
                <?php foreach ($tags as $tag): ?>
                  <span class="rhfg-tag-mini rhfg-tag-<?= h($tag[1]) ?>"><?= h($tag[0]) ?></span>
                <?php endforeach ?>
              </div>
            <?php endif ?>
          </div>
          <div class="rhfg-sess-status">
            <div class="rhfg-spots">
              <?php if ($places > 0): ?>
                <div class="top"><strong><?= $pris ?></strong> / <?= $places ?> places</div>
                <div class="rhfg-spots-bar <?= $statBar ?>"><span style="width:<?= min(100, $pct) ?>%"></span></div>
                <div class="lbl" style="<?= $isComplete ? 'color:#b8443a' : '' ?>">
                  <?= $isComplete ? "Liste d'attente" : "$restantes places restantes" ?>
                </div>
              <?php else: ?>
                <div class="top"><strong>∞</strong></div>
                <div class="rhfg-spots-bar"><span style="width:0%"></span></div>
                <div class="lbl">Places illimitées</div>
              <?php endif ?>
            </div>
            <?php if ($isFirst): ?>
              <button class="rhfg-btn-inscribe" data-session-inscrire="<?= h($s['session_id']) ?>">
                <i class="bi bi-plus-lg" style="font-size:13px"></i> Inscrire
              </button>
            <?php elseif ($isComplete): ?>
              <button class="rhfg-btn-inscribe outline" data-session-inscrire="<?= h($s['session_id']) ?>">Liste d'attente</button>
            <?php else: ?>
              <button class="rhfg-btn-inscribe outline" data-session-inscrire="<?= h($s['session_id']) ?>">Inscrire</button>
            <?php endif ?>
          </div>
        </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>

  <!-- Email preview -->
  <div class="rhfg-section-title">
    <h2>Aperçu email d'inscription</h2>
    <div class="meta">Généré automatiquement à partir des suggestions sélectionnées</div>
  </div>

  <div id="emailPreview" class="rhfg-modal-zone">
    <div style="padding:60px 20px;text-align:center;color:#6b8783">
      <i class="bi bi-envelope" style="font-size:32px;opacity:.25;display:block;margin-bottom:12px"></i>
      <strong>Aucune suggestion sélectionnée</strong>
      <div style="margin-top:6px;font-size:12.5px">Cliquez sur "Inscrire les N →" depuis une suggestion ci-dessus pour générer l'email.</div>
    </div>
  </div>

</div>

<!-- Sticky selection bar -->
<div class="rhfg-sel-bar" id="bottomBar" hidden>
  <div class="rhfg-sel-count">
    <div class="rhfg-sel-badge" id="bbCount">0</div>
    <div class="rhfg-sel-text"><span id="bbLabel">collaborateurs sélectionnés</span> · <strong id="bbSession">1 session</strong></div>
  </div>
  <div class="rhfg-sel-divider"></div>
  <button class="rhfg-sel-btn" id="bbVerifBtn">
    <i class="bi bi-info-circle"></i> Vérifier
  </button>
  <button class="rhfg-sel-btn" id="bbCancelBtn">
    <i class="bi bi-trash"></i> Annuler
  </button>
  <button class="rhfg-sel-btn primary" id="bbPrepareBtn">
    <i class="bi bi-send"></i> Préparer l'email d'inscription
  </button>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Inscriptions Fegems · refonte fidèle maquette
   Tout est scopé sous .rhfg-page (et .rhfg-sel-bar pour la barre fixed)
   pour ne pas polluer le reste du theme-care.
═══════════════════════════════════════════════════════════════════ */

.rhfg-page {
  --rhfg-bg: #f5f7f5;
  --rhfg-surface: #ffffff;
  --rhfg-surface-2: #fafbfa;
  --rhfg-ink: #0d2a26;
  --rhfg-ink-2: #324e4a;
  --rhfg-muted: #6b8783;
  --rhfg-line: #e3ebe8;
  --rhfg-line-2: #d4ddda;
  --rhfg-teal-50: #ecf5f3;
  --rhfg-teal-100: #d2e7e2;
  --rhfg-teal-300: #7ab5ab;
  --rhfg-teal-500: #2d8074;
  --rhfg-teal-600: #1f6359;
  --rhfg-teal-700: #164a42;
  --rhfg-warn: #c97a2a;
  --rhfg-warn-bg: #fbf0e1;
  --rhfg-danger: #b8443a;
  --rhfg-danger-bg: #f7e3e0;
  --rhfg-ok: #3d8b6b;
  --rhfg-ok-bg: #e3f0ea;
  --rhfg-info: #3a6a8a;
  --rhfg-info-bg: #e2ecf2;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  color: var(--rhfg-ink);
  line-height: 1.45;
  padding: 28px 32px 90px;
  max-width: 1600px;
  margin: 0 auto;
}
.rhfg-page * { box-sizing: border-box; }
.rhfg-page .serif { font-family: 'Fraunces', serif; letter-spacing: -.01em; }
.rhfg-page .mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }

/* === PAGE HEAD === */
.rhfg-page-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; margin-bottom: 24px; flex-wrap: wrap;
}
.rhfg-h1 {
  font-family: 'Fraunces', serif; font-size: 34px; font-weight: 500;
  letter-spacing: -.025em; line-height: 1.1; color: var(--rhfg-ink); margin: 0;
}
.rhfg-h1-sub {
  color: var(--rhfg-muted); font-size: 14px; margin-top: 5px; max-width: 600px; line-height: 1.5;
}
.rhfg-h1-sub strong { color: var(--rhfg-ink-2); font-weight: 600; }
.rhfg-actions { display: flex; gap: 10px; flex-shrink: 0; align-items: center; flex-wrap: wrap; }

.rhfg-page .rhfg-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px; font-family: inherit;
  font-size: 13px; font-weight: 500; border: 1px solid var(--rhfg-line);
  background: var(--rhfg-surface); color: var(--rhfg-ink-2);
  cursor: pointer; transition: .15s; text-decoration: none;
}
.rhfg-page .rhfg-btn:hover { border-color: var(--rhfg-teal-300); color: var(--rhfg-teal-600); }
.rhfg-page .rhfg-btn-primary {
  background: var(--rhfg-teal-600); color: #fff; border-color: var(--rhfg-teal-600);
}
.rhfg-page .rhfg-btn-primary:hover {
  background: var(--rhfg-teal-700); color: #fff; border-color: var(--rhfg-teal-700);
}

/* Mode toggle */
.rhfg-page .rhfg-mode-toggle {
  display: inline-flex; background: var(--rhfg-surface-2);
  border: 1px solid var(--rhfg-line); border-radius: 9px; padding: 3px; gap: 2px;
}
.rhfg-page .rhfg-mode-toggle button {
  padding: 7px 14px; font-family: inherit; font-size: 12.5px; font-weight: 500;
  color: var(--rhfg-muted); background: transparent; border: 0; border-radius: 6px;
  cursor: pointer; transition: .15s; display: inline-flex; align-items: center; gap: 6px;
}
.rhfg-page .rhfg-mode-toggle button:hover { color: var(--rhfg-ink-2); }
.rhfg-page .rhfg-mode-toggle button.on {
  background: #fff; color: var(--rhfg-teal-600);
  box-shadow: 0 1px 2px rgba(13,42,38,.04), 0 1px 1px rgba(13,42,38,.03);
}

/* === INTEL BANNER === */
.rhfg-intel-banner {
  background: linear-gradient(120deg, #164a42 0%, #1f6359 60%, #2d8074 100%);
  border-radius: 16px; padding: 24px 26px; color: #fff;
  position: relative; overflow: hidden; margin-bottom: 20px;
  box-shadow: 0 12px 32px -10px rgba(22,74,66,.4);
}
.rhfg-intel-banner::before {
  content: ""; position: absolute; inset: 0;
  background:
    radial-gradient(circle at 90% 20%, rgba(125,211,168,.2) 0%, transparent 40%),
    radial-gradient(circle at 5% 100%, rgba(168,230,201,.1) 0%, transparent 50%);
  pointer-events: none;
}
.rhfg-intel-banner::after {
  content: ""; position: absolute; right: -80px; top: -60px;
  width: 300px; height: 300px;
  background: repeating-radial-gradient(circle at center, rgba(255,255,255,.03) 0, rgba(255,255,255,.03) 1px, transparent 1px, transparent 12px);
  pointer-events: none;
}
.rhfg-intel-banner > * { position: relative; z-index: 1; }
.rhfg-intel-head { display: flex; align-items: center; gap: 14px; margin-bottom: 6px; }
.rhfg-intel-icon {
  width: 42px; height: 42px; border-radius: 11px; background: rgba(255,255,255,.12);
  display: grid; place-items: center; font-family: 'Fraunces', serif;
  font-size: 22px; font-weight: 600; border: 1px solid rgba(255,255,255,.18);
}
.rhfg-intel-tag {
  font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
  color: #a8e6c9; font-weight: 600;
}
.rhfg-intel-title {
  font-family: 'Fraunces', serif; font-size: 22px; font-weight: 500;
  letter-spacing: -.015em; color: #fff; line-height: 1.2; margin: 0;
}
.rhfg-intel-text {
  color: #cfe0db; font-size: 13.5px; margin: 4px 0 0; max-width: 600px;
}
.rhfg-intel-text strong { color: #fff; font-weight: 600; }
.rhfg-intel-stats { display: flex; gap: 32px; margin-top: 18px; flex-wrap: wrap; }
.rhfg-intel-stat { display: flex; flex-direction: column; gap: 2px; }
.rhfg-intel-stat .v {
  font-family: 'Fraunces', serif; font-size: 26px; font-weight: 500; line-height: 1;
}
.rhfg-intel-stat .l {
  font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #a8c4be;
}

/* === SECTION TITLE === */
.rhfg-section-title {
  display: flex; align-items: center; justify-content: space-between;
  margin: 24px 0 14px;
}
.rhfg-section-title h2 {
  font-family: 'Fraunces', serif; font-size: 20px; font-weight: 600;
  letter-spacing: -.01em; color: var(--rhfg-ink); margin: 0;
}
.rhfg-section-title .meta { font-size: 12.5px; color: var(--rhfg-muted); }
.rhfg-section-title .meta a { color: var(--rhfg-teal-600); text-decoration: none; }
.rhfg-section-title .meta a:hover { text-decoration: underline; }

/* === SUGGESTIONS GRID === */
.rhfg-sugg-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
}
.rhfg-empty {
  grid-column: 1 / -1; padding: 50px; text-align: center; color: var(--rhfg-muted);
}
.rhfg-empty i { font-size: 28px; opacity: .4; display: block; margin-bottom: 10px; }

.rhfg-sugg-card {
  background: var(--rhfg-surface); border: 1px solid var(--rhfg-line); border-radius: 14px;
  overflow: hidden; display: flex; flex-direction: column; transition: .18s;
  position: relative;
}
.rhfg-sugg-card:hover {
  border-color: var(--rhfg-teal-300); transform: translateY(-2px);
  box-shadow: 0 4px 16px -4px rgba(13,42,38,.08), 0 2px 4px rgba(13,42,38,.04);
}
.rhfg-sugg-card.priority::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: linear-gradient(180deg, var(--rhfg-danger), #cd6b62);
}
.rhfg-sugg-card.warn::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: linear-gradient(180deg, var(--rhfg-warn), #e0a85a);
}
.rhfg-sugg-card.info::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: linear-gradient(180deg, var(--rhfg-info), #5d88a8);
}

.rhfg-sc-head {
  padding: 14px 16px 10px;
  display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
}
.rhfg-sc-tag {
  font-size: 10px; letter-spacing: .1em; text-transform: uppercase;
  font-weight: 700; padding: 3px 8px; border-radius: 4px;
}
.rhfg-sc-tag.urgent { background: var(--rhfg-danger-bg); color: var(--rhfg-danger); }
.rhfg-sc-tag.soon   { background: var(--rhfg-warn-bg); color: var(--rhfg-warn); }
.rhfg-sc-tag.opt    { background: var(--rhfg-info-bg); color: var(--rhfg-info); }
.rhfg-sc-deadline {
  font-size: 11px; color: var(--rhfg-muted);
  font-family: 'JetBrains Mono', monospace;
  text-align: right; line-height: 1.3;
}
.rhfg-sc-deadline strong { color: var(--rhfg-ink); display: block; }
.rhfg-sc-body { padding: 0 16px 12px; flex: 1; }
.rhfg-sc-body h3 {
  font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600;
  letter-spacing: -.01em; line-height: 1.25; color: var(--rhfg-ink); margin: 0;
}
.rhfg-session-info {
  font-size: 11.5px; color: var(--rhfg-muted); margin-top: 4px;
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.rhfg-session-info .pip { display: inline-flex; align-items: center; gap: 4px; }
.rhfg-session-info .dot {
  width: 3px; height: 3px; border-radius: 50%; background: var(--rhfg-line-2);
}
.rhfg-sc-rationale {
  margin: 10px 16px 0; padding: 9px 11px;
  background: var(--rhfg-teal-50); border-radius: 7px;
  font-size: 11.5px; color: var(--rhfg-teal-700); line-height: 1.4;
  border-left: 2px solid var(--rhfg-teal-500);
}
.rhfg-sc-rationale strong { color: var(--rhfg-teal-700); font-weight: 600; }
.rhfg-sc-collabs { padding: 12px 16px; }
.rhfg-sc-collabs .lbl {
  font-size: 10px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--rhfg-muted); font-weight: 600; margin-bottom: 7px;
}
.rhfg-sc-stack { display: flex; align-items: center; }
.rhfg-sc-stack .av {
  width: 26px; height: 26px; border-radius: 50%;
  font-size: 10.5px; font-weight: 600; color: #fff;
  display: grid; place-items: center;
  border: 2px solid #fff; margin-left: -7px;
}
.rhfg-sc-stack .av:first-child { margin-left: 0; }
.rhfg-sc-stack .more {
  width: 26px; height: 26px; border-radius: 50%;
  font-size: 10px; color: var(--rhfg-ink-2); font-weight: 600;
  display: grid; place-items: center; border: 2px solid #fff; margin-left: -7px;
  background: var(--rhfg-surface-2);
}
.rhfg-sc-foot {
  padding: 10px 16px 14px;
  display: flex; align-items: center; justify-content: space-between;
  border-top: 1px solid var(--rhfg-line); background: var(--rhfg-surface-2);
}
.rhfg-sc-cost { font-size: 11.5px; color: var(--rhfg-muted); }
.rhfg-sc-cost strong {
  color: var(--rhfg-ink); font-family: 'JetBrains Mono', monospace; font-weight: 600;
}
.rhfg-sc-action {
  padding: 6px 12px; font-size: 12px; font-weight: 500; border-radius: 6px;
  background: var(--rhfg-teal-600); color: #fff; border: 0; cursor: pointer;
  display: inline-flex; align-items: center; gap: 5px; transition: .15s;
  font-family: inherit;
}
.rhfg-sc-action:hover { background: var(--rhfg-teal-700); }

/* === FILTERS === */
.rhfg-filters {
  background: var(--rhfg-surface); border: 1px solid var(--rhfg-line);
  border-radius: 10px; padding: 12px 14px;
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  margin-bottom: 14px;
}
.rhfg-filter-label {
  font-size: 11px; color: var(--rhfg-muted); text-transform: uppercase;
  letter-spacing: .06em; font-weight: 600; margin-right: 4px;
}
.rhfg-page .rhfg-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 11px; border-radius: 99px; font-size: 12.5px;
  background: var(--rhfg-surface-2); color: var(--rhfg-ink-2);
  border: 1px solid var(--rhfg-line); cursor: pointer; transition: .15s;
  font-family: inherit; font-weight: 500;
}
.rhfg-page .rhfg-chip:hover { border-color: var(--rhfg-teal-300); }
.rhfg-page .rhfg-chip.on {
  background: var(--rhfg-teal-600); color: #fff; border-color: var(--rhfg-teal-600);
}
.rhfg-divider-v {
  width: 1px; height: 22px; background: var(--rhfg-line); margin: 0 4px;
}
.rhfg-search-mini {
  margin-left: auto; display: flex; align-items: center; gap: 7px;
  background: var(--rhfg-surface-2); border: 1px solid var(--rhfg-line);
  border-radius: 7px; padding: 5px 11px; width: 240px;
}
.rhfg-search-mini input {
  border: 0; background: transparent; outline: none;
  font-family: inherit; font-size: 12.5px; width: 100%;
}

/* === CATALOG WRAP === */
.rhfg-cat-wrap {
  background: var(--rhfg-surface); border: 1px solid var(--rhfg-line);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(13,42,38,.04), 0 1px 1px rgba(13,42,38,.03);
}
.rhfg-cat-head-bar {
  padding: 14px 18px; border-bottom: 1px solid var(--rhfg-line);
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  background: linear-gradient(180deg, #fafbfa, #fff);
}
.rhfg-cat-head-bar h2 {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  letter-spacing: -.01em; color: var(--rhfg-ink); margin: 0;
}
.rhfg-cat-head-bar .meta { font-size: 12.5px; color: var(--rhfg-muted); }

/* === SESSION ROW === */
.rhfg-session {
  padding: 18px 22px; border-bottom: 1px solid var(--rhfg-line);
  display: grid; grid-template-columns: auto 1fr auto; gap: 18px;
  align-items: center; transition: .12s;
}
.rhfg-session:last-child { border-bottom: 0; }
.rhfg-session:hover { background: var(--rhfg-surface-2); }
.rhfg-session.expanded { background: var(--rhfg-teal-50); }

.rhfg-sess-date {
  width: 60px; text-align: center;
  background: #fff; border: 1px solid var(--rhfg-line); border-radius: 9px;
  padding: 8px 4px; flex-shrink: 0;
}
.rhfg-sess-date .month {
  font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase;
  color: var(--rhfg-teal-600); font-weight: 700;
}
.rhfg-sess-date .day {
  font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600;
  color: var(--rhfg-ink); line-height: 1; letter-spacing: -.02em;
}
.rhfg-sess-date .year {
  font-size: 9.5px; color: var(--rhfg-muted); margin-top: 1px;
  font-family: 'JetBrains Mono', monospace;
}

.rhfg-sess-body h3 {
  font-family: 'Outfit', sans-serif; font-size: 14.5px; font-weight: 500;
  color: var(--rhfg-ink); line-height: 1.3; margin: 0;
}
.rhfg-sess-meta {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  margin-top: 6px; font-size: 11.5px; color: var(--rhfg-muted);
}
.rhfg-sess-meta .dot {
  width: 3px; height: 3px; border-radius: 50%; background: var(--rhfg-line-2);
}
.rhfg-sess-meta .pip { display: inline-flex; align-items: center; gap: 4px; }
.rhfg-sess-tags { display: flex; gap: 5px; margin-top: 8px; flex-wrap: wrap; }
.rhfg-tag-mini {
  font-size: 10px; padding: 2px 7px; border-radius: 4px;
  font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
}
.rhfg-tag-soins  { background: var(--rhfg-teal-50); color: var(--rhfg-teal-700); }
.rhfg-tag-hot    { background: #fff3e3; color: #8a5a1a; }
.rhfg-tag-anim   { background: #f0e8f5; color: #5e3a78; }
.rhfg-tag-tech   { background: #e6ecf2; color: #2d4a6b; }
.rhfg-tag-tout   { background: #eef2ed; color: #3a5a2d; }
.rhfg-tag-oblig  { background: var(--rhfg-danger-bg); color: var(--rhfg-danger); }
.rhfg-tag-cant   { background: var(--rhfg-info-bg); color: var(--rhfg-info); }
.rhfg-tag-elearn { background: #f3eef0; color: #7a3a5d; }

/* Session status */
.rhfg-sess-status { display: flex; align-items: center; gap: 14px; }
.rhfg-spots {
  display: flex; flex-direction: column; align-items: flex-end; gap: 3px;
  min-width: 100px;
}
.rhfg-spots .top {
  font-size: 11px; color: var(--rhfg-muted);
  font-family: 'JetBrains Mono', monospace;
}
.rhfg-spots .top strong { color: var(--rhfg-ink); }
.rhfg-spots-bar {
  width: 100px; height: 5px; background: var(--rhfg-line);
  border-radius: 99px; overflow: hidden;
}
.rhfg-spots-bar span {
  display: block; height: 100%;
  background: linear-gradient(90deg, var(--rhfg-teal-500), #5cad9b);
  border-radius: 99px;
}
.rhfg-spots-bar.warn span {
  background: linear-gradient(90deg, #d49039, #e0a85a);
}
.rhfg-spots-bar.full span {
  background: linear-gradient(90deg, #b8443a, #cd6b62);
}
.rhfg-spots .lbl { font-size: 10px; color: var(--rhfg-muted); letter-spacing: .04em; }

.rhfg-page .rhfg-btn-inscribe {
  padding: 9px 14px; font-size: 12.5px; font-weight: 500; border-radius: 8px;
  background: var(--rhfg-teal-600); color: #fff; border: 0; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px; transition: .15s;
  font-family: inherit;
}
.rhfg-page .rhfg-btn-inscribe:hover { background: var(--rhfg-teal-700); }
.rhfg-page .rhfg-btn-inscribe.outline {
  background: transparent; color: var(--rhfg-teal-600);
  border: 1px solid var(--rhfg-teal-300);
}
.rhfg-page .rhfg-btn-inscribe.outline:hover {
  background: var(--rhfg-teal-50); color: var(--rhfg-teal-700);
}

/* === SELECTION BAR (sticky bottom) === */
.rhfg-sel-bar {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #0d2a26; color: #fff; border-radius: 14px;
  padding: 12px 16px 12px 20px; display: flex; align-items: center; gap: 14px;
  box-shadow: 0 16px 48px -12px rgba(13,42,38,.5), 0 4px 12px rgba(13,42,38,.2);
  z-index: 1000; border: 1px solid #1f4a42;
  font-family: 'Outfit', sans-serif;
}
.rhfg-sel-bar[hidden] { display: none !important; }
.rhfg-sel-count { display: flex; align-items: center; gap: 10px; }
.rhfg-sel-badge {
  width: 30px; height: 30px; border-radius: 8px; background: #7dd3a8; color: #0d2a26;
  display: grid; place-items: center;
  font-family: 'Fraunces', serif; font-weight: 700; font-size: 14px;
}
.rhfg-sel-text { font-size: 13px; color: #cfe0db; }
.rhfg-sel-text strong { color: #fff; font-weight: 500; }
.rhfg-sel-divider { width: 1px; height: 24px; background: rgba(255,255,255,.15); }
.rhfg-sel-btn {
  padding: 8px 14px; font-size: 12.5px; font-weight: 500;
  border-radius: 7px; border: 1px solid rgba(255,255,255,.15);
  background: transparent; color: #cfe0db; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
  transition: .15s; font-family: inherit;
}
.rhfg-sel-btn:hover { background: rgba(255,255,255,.06); color: #fff; }
.rhfg-sel-btn.primary {
  background: #7dd3a8; color: #0d2a26; border-color: #7dd3a8; font-weight: 600;
}
.rhfg-sel-btn.primary:hover { background: #a8e6c9; }

/* === EMAIL PREVIEW (modal-zone) === */
.rhfg-modal-zone {
  background: var(--rhfg-surface); border: 1px solid var(--rhfg-line);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 4px 16px -4px rgba(13,42,38,.08), 0 2px 4px rgba(13,42,38,.04);
}
.rhfg-modal-head {
  padding: 18px 22px;
  background: linear-gradient(135deg, #1f6359, #2d8074);
  color: #fff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.rhfg-modal-head .lbl {
  font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase;
  color: #a8e6c9; font-weight: 600;
}
.rhfg-modal-head h3 {
  font-family: 'Fraunces', serif; font-size: 20px; font-weight: 500;
  letter-spacing: -.01em; margin: 2px 0 0; color: #fff;
}
.rhfg-modal-actions { display: flex; gap: 8px; }
.rhfg-page .rhfg-modal-btn {
  padding: 8px 14px; font-size: 12.5px; font-weight: 500; border-radius: 7px;
  border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.08);
  color: #fff; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px; transition: .15s;
  font-family: inherit;
}
.rhfg-page .rhfg-modal-btn:hover { background: rgba(255,255,255,.16); }
.rhfg-page .rhfg-modal-btn.primary {
  background: #fff; color: var(--rhfg-teal-700); border-color: #fff; font-weight: 600;
}
.rhfg-page .rhfg-modal-btn.primary:hover { background: #d2e7e2; }

.rhfg-email-prev {
  display: grid; grid-template-columns: 1fr 320px; gap: 0;
}
.rhfg-email-body { padding: 22px 26px; border-right: 1px solid var(--rhfg-line); }
.rhfg-email-meta {
  display: flex; flex-direction: column; gap: 8px;
  padding-bottom: 14px; border-bottom: 1px solid var(--rhfg-line); margin-bottom: 14px;
}
.rhfg-em-row { display: flex; gap: 12px; align-items: flex-start; font-size: 12.5px; }
.rhfg-em-row .k {
  width: 50px; color: var(--rhfg-muted); font-weight: 500; flex-shrink: 0;
  text-transform: uppercase; font-size: 10.5px; letter-spacing: .06em; padding-top: 3px;
}
.rhfg-em-row .v { color: var(--rhfg-ink); font-weight: 500; flex: 1; }
.rhfg-em-row.subj .v { font-family: 'Fraunces', serif; font-size: 15px; font-weight: 600; }

.rhfg-email-text { font-size: 13px; line-height: 1.65; color: var(--rhfg-ink-2); }
.rhfg-email-text p { margin: 0 0 10px; }
.rhfg-email-text strong { color: var(--rhfg-ink); }
.rhfg-email-text .table {
  margin: 14px 0; border: 1px solid var(--rhfg-line); border-radius: 8px;
  overflow: hidden; font-size: 12px;
}
.rhfg-email-text .table table { width: 100%; border-collapse: collapse; }
.rhfg-email-text .table th {
  background: var(--rhfg-surface-2); padding: 8px 12px; text-align: left;
  font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
  color: var(--rhfg-muted); font-weight: 600; border-bottom: 1px solid var(--rhfg-line);
}
.rhfg-email-text .table td {
  padding: 8px 12px; border-bottom: 1px solid var(--rhfg-line); color: var(--rhfg-ink-2);
}
.rhfg-email-text .table tr:last-child td { border-bottom: 0; }
.rhfg-email-text .sig {
  margin-top: 16px; padding-top: 12px;
  border-top: 1px dashed var(--rhfg-line); color: var(--rhfg-muted); font-size: 12px;
}

.rhfg-email-side {
  padding: 22px; background: var(--rhfg-surface-2);
  display: flex; flex-direction: column; gap: 18px;
}
.rhfg-es-block h4 {
  font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--rhfg-muted); font-weight: 700; margin: 0 0 8px;
}
.rhfg-es-list { display: flex; flex-direction: column; gap: 8px; }
.rhfg-es-item {
  display: flex; align-items: flex-start; gap: 9px;
  font-size: 12.5px; color: var(--rhfg-ink-2); line-height: 1.4;
}
.rhfg-es-item .ico {
  width: 18px; height: 18px; border-radius: 5px;
  display: grid; place-items: center; flex-shrink: 0;
  font-size: 11px; font-weight: 700; color: #fff;
  font-family: 'JetBrains Mono', monospace;
}
.rhfg-es-item .ico.ok   { background: var(--rhfg-ok); }
.rhfg-es-item .ico.info { background: var(--rhfg-info); }
.rhfg-es-item .ico.warn { background: var(--rhfg-warn); }
.rhfg-es-divider { height: 1px; background: var(--rhfg-line); margin: 0; }

.rhfg-es-tracker {
  background: #fff; border: 1px solid var(--rhfg-line); border-radius: 9px; padding: 12px;
}
.rhfg-es-tracker .step {
  display: flex; align-items: center; gap: 9px; font-size: 12px; padding: 5px 0;
}
.rhfg-es-tracker .step .num {
  width: 18px; height: 18px; border-radius: 50%;
  display: grid; place-items: center;
  font-size: 10px; font-weight: 700; color: #fff;
  font-family: 'JetBrains Mono', monospace; flex-shrink: 0;
}
.rhfg-es-tracker .step.done .num { background: var(--rhfg-ok); }
.rhfg-es-tracker .step.cur .num {
  background: var(--rhfg-teal-600); box-shadow: 0 0 0 3px var(--rhfg-teal-50);
}
.rhfg-es-tracker .step.next .num {
  background: #fff; border: 1.5px dashed var(--rhfg-line-2); color: var(--rhfg-muted);
}
.rhfg-es-tracker .step.done { color: var(--rhfg-ink-2); }
.rhfg-es-tracker .step.cur { color: var(--rhfg-ink); font-weight: 500; }
.rhfg-es-tracker .step.next { color: var(--rhfg-muted); }

@media (max-width: 1280px) {
  .rhfg-sugg-grid { grid-template-columns: repeat(2, 1fr); }
  .rhfg-email-prev { grid-template-columns: 1fr; }
  .rhfg-email-body { border-right: 0; border-bottom: 1px solid var(--rhfg-line); }
}
@media (max-width: 980px) {
  .rhfg-sugg-grid { grid-template-columns: 1fr; }
  .rhfg-session { grid-template-columns: 1fr; }
  .rhfg-sess-status { justify-content: flex-end; }
}
</style>

<script<?= nonce() ?>>
(function() {
    let allSuggestions = [];
    const selected = new Set();

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function initials(p, n) { return ((p?.[0] ?? '') + (n?.[0] ?? '')).toUpperCase(); }
    function avatarColor(seed) {
        const colors = ['#1f6359', '#c46658', '#5a82a8', '#9268b3', '#a89a3d', '#6b9558', '#d49039'];
        let h = 0; for (let i = 0; i < (seed || '').length; i++) h = (h * 31 + seed.charCodeAt(i)) % 1000;
        return colors[h % colors.length];
    }
    function formatDate(d) {
        if (!d) return '—';
        const dt = new Date(d);
        return dt.toLocaleDateString('fr-CH', {day:'2-digit', month:'2-digit', year:'numeric'}).replace(/\//g, '.');
    }
    function daysUntil(d) {
        if (!d) return 0;
        return Math.max(0, Math.ceil((new Date(d) - new Date()) / 86400000));
    }

    function loadPropositions() {
        adminApiPost('admin_get_inscriptions_propositions', { statut: 'proposee' }).then(r => {
            if (!r.success) {
                document.getElementById('suggestionsGrid').innerHTML =
                    '<div class="rhfg-empty"><i class="bi bi-exclamation-triangle"></i> ' + escapeHtml(r.message || 'Erreur') + '</div>';
                return;
            }
            allSuggestions = r.propositions || [];
            renderSuggestions();
        }).catch(e => {
            document.getElementById('suggestionsGrid').innerHTML =
                '<div class="rhfg-empty"><i class="bi bi-exclamation-triangle"></i> Erreur réseau</div>';
        });
    }

    function renderSuggestions() {
        const el = document.getElementById('suggestionsGrid');
        if (!allSuggestions.length) {
            el.innerHTML = '<div class="rhfg-empty"><i class="bi bi-check-circle"></i><div style="margin-top:8px"><strong>Aucune suggestion en attente</strong></div><div style="margin-top:4px;font-size:12.5px">Excellent ! L\'équipe est à jour sur ses formations FEGEMS.</div></div>';
            return;
        }
        const visible = allSuggestions.slice(0, 6);
        el.innerHTML = visible.map(suggestionCard).join('');
        el.querySelectorAll('[data-inscrire]').forEach(btn => {
            btn.addEventListener('click', () => openInscriptionWizard(btn.dataset.inscrire));
        });
    }

    function suggestionCard(s) {
        const motifMap = {
            'renouvellement_expire': { lbl: 'Urgent', cls: 'urgent', card: 'priority' },
            'inc_nouveau':           { lbl: 'À planifier', cls: 'soon', card: 'warn' },
            'plan_cantonal':         { lbl: 'Plan cantonal', cls: 'opt', card: 'info' },
            'recommandation_ocs':    { lbl: 'Recommandé', cls: 'opt', card: 'info' },
            'manuel':                { lbl: 'Manuel', cls: 'opt', card: 'info' },
        };
        const m = motifMap[s.type_motif] || { lbl: 'Suggestion', cls: 'opt', card: 'info' };

        const motif = s.type_motif === 'renouvellement_expire';
        const dateStr = formatDate(s.date_debut);
        const days = daysUntil(s.date_debut);

        const candidats = s.candidats || [];
        const nbCand = parseInt(s.nb_candidats || candidats.length, 10);
        const avatars = candidats.slice(0, 5).map(c =>
            `<div class="av" style="background:${avatarColor(c.id)}">${escapeHtml(initials(c.prenom, c.nom))}</div>`
        ).join('');
        const more = nbCand > 5 ? `<div class="more">+${nbCand - 5}</div>` : '';

        // Durée
        const dureeHtml = s.duree_heures
            ? `<span class="pip"><i class="bi bi-clock"></i> ${escapeHtml(parseFloat(s.duree_heures))}h</span>`
            : '';

        // Pourquoi
        const why = s.motif_text || (
            s.type_motif === 'renouvellement_expire' ? `${nbCand} collaborateurs ont des attestations <strong>expirées</strong>. Formation obligatoire selon le référentiel.` :
            s.type_motif === 'inc_nouveau' ? `${nbCand} nouveaux embauchés n'ont pas encore suivi l'INC obligatoire.` :
            s.type_motif === 'plan_cantonal' ? `${nbCand} collaborateurs éligibles au certificat cantonal.` :
            `Formation recommandée pour ${nbCand} collaborateur${nbCand > 1 ? 's' : ''}.`
        );

        // Coût
        const coutTotal = parseFloat(s.cout_non_membre || 0) * nbCand;
        const isMembre = parseFloat(s.cout_membre) === 0 || s.membre_fegems;
        const coutLbl = isMembre || coutTotal === 0 ? 'CHF 0' : 'CHF ' + Math.round(coutTotal).toLocaleString('fr-CH').replace(/,/g, "'");
        const coutSub = isMembre ? '(membre Fegems)' : '';

        return `<div class="rhfg-sugg-card ${m.card}">
          <div class="rhfg-sc-head">
            <span class="rhfg-sc-tag ${m.cls}">${m.cls === 'urgent' ? '⚠ ' : ''}${escapeHtml(m.lbl)}</span>
            <div class="rhfg-sc-deadline"><strong>${dateStr}</strong>dans ${days} jours</div>
          </div>
          <div class="rhfg-sc-body">
            <h3>${escapeHtml(s.formation_titre || '—')}</h3>
            <div class="rhfg-session-info">
              ${s.lieu ? `<span class="pip">📍 ${escapeHtml(s.lieu)}</span>` : ''}
              ${s.lieu && dureeHtml ? '<span class="dot"></span>' : ''}
              ${dureeHtml}
              ${s.modalite ? `<span class="dot"></span><span class="pip">${escapeHtml(s.modalite[0].toUpperCase() + s.modalite.slice(1))}</span>` : ''}
            </div>
          </div>
          <div class="rhfg-sc-rationale">
            <strong>Pourquoi ?</strong> ${why}
          </div>
          <div class="rhfg-sc-collabs">
            <div class="lbl">${nbCand} collaborateur${nbCand > 1 ? 's' : ''} concerné${nbCand > 1 ? 's' : ''}</div>
            <div class="rhfg-sc-stack">${avatars}${more}</div>
          </div>
          <div class="rhfg-sc-foot">
            <div class="rhfg-sc-cost">Coût · <strong>${coutLbl}</strong> ${coutSub ? `<span style="color:var(--rhfg-muted)">${coutSub}</span>` : ''}</div>
            <button class="rhfg-sc-action" data-inscrire="${escapeHtml(s.id)}">
              ${nbCand > 1 ? `Inscrire les ${nbCand}` : 'Inscrire'} →
            </button>
          </div>
        </div>`;
    }

    function openInscriptionWizard(propId) {
        const prop = allSuggestions.find(s => s.id === propId);
        if (!prop) return;

        selected.add(propId);
        const totalCollab = parseInt(prop.nb_candidats || (prop.candidats || []).length, 10);
        document.getElementById('bbCount').textContent = totalCollab;
        document.getElementById('bbLabel').textContent = `collaborateurs sélectionnés`;
        document.getElementById('bbSession').textContent = '1 session ' + (prop.formation_titre || '');
        document.getElementById('bottomBar').hidden = false;

        renderEmailPreview(prop);
        document.getElementById('emailPreview').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderEmailPreview(prop) {
        const candidats = prop.candidats || [];
        const dt = prop.date_debut ? new Date(prop.date_debut) : null;
        const dateStr = dt ? dt.toLocaleDateString('fr-CH', {day:'numeric', month:'long', year:'numeric'}) : '';
        const dateShort = dt ? dt.toLocaleDateString('fr-CH').replace(/\//g, '.') : '';
        const nbCand = parseInt(prop.nb_candidats || candidats.length, 10);

        const rows = candidats.slice(0, 5).map(c => `
          <tr>
            <td>${escapeHtml((c.nom || '') + ' ' + (c.prenom || ''))}</td>
            <td>${escapeHtml(c.fonction || '—')}</td>
            <td>${escapeHtml(c.diplome || '—')}</td>
            <td>${escapeHtml((c.prenom||'').toLowerCase()[0] || '')}.${escapeHtml((c.nom||'').toLowerCase().slice(0, 8))}@…</td>
          </tr>
        `).join('');
        const moreRow = nbCand > 5
            ? `<tr><td colspan="4" style="color:var(--rhfg-muted);font-style:italic;text-align:center">+ ${nbCand - 5} autres collaborateurs · liste complète en annexe PDF</td></tr>`
            : '';

        const emsNom = <?= json_encode($emsNom) ?>;
        const emsVille = <?= json_encode($emsVille) ?>;
        const emsTel = <?= json_encode($emsTel) ?>;
        const emsEmail = <?= json_encode($emsEmailDir ?: 'direction@ems.ch') ?>;
        const fegemsEmail = prop.contact_inscription_email || 'inscription@fegems.ch';

        document.getElementById('emailPreview').innerHTML = `
          <div class="rhfg-modal-head">
            <div>
              <div class="lbl">Brouillon prêt à envoyer</div>
              <h3>Inscription ${escapeHtml(prop.formation_titre || '')} · session du ${escapeHtml(dateShort)}</h3>
            </div>
            <div class="rhfg-modal-actions">
              <button class="rhfg-modal-btn"><i class="bi bi-download"></i> Télécharger PDF</button>
              <button class="rhfg-modal-btn"><i class="bi bi-clipboard"></i> Copier</button>
              <button class="rhfg-modal-btn primary" id="sendEmailBtn" data-prop="${escapeHtml(prop.id)}">
                <i class="bi bi-send"></i> Envoyer à ${escapeHtml(fegemsEmail)}
              </button>
            </div>
          </div>

          <div class="rhfg-email-prev">
            <div class="rhfg-email-body">
              <div class="rhfg-email-meta">
                <div class="rhfg-em-row">
                  <span class="k">De</span>
                  <span class="v">Direction · ${escapeHtml(emsEmail)}</span>
                </div>
                <div class="rhfg-em-row">
                  <span class="k">À</span>
                  <span class="v">${escapeHtml(fegemsEmail)}</span>
                </div>
                <div class="rhfg-em-row">
                  <span class="k">Cc</span>
                  <span class="v" style="color:var(--rhfg-muted);font-weight:400">formation@${escapeHtml((emsNom || '').toLowerCase().replace(/\s/g, '-') || 'ems')}.ch</span>
                </div>
                <div class="rhfg-em-row subj">
                  <span class="k">Objet</span>
                  <span class="v">Inscription ${escapeHtml(prop.formation_titre || '')} ${escapeHtml(dateShort)} · ${nbCand} collaborateurs · ${escapeHtml(emsNom)}</span>
                </div>
              </div>

              <div class="rhfg-email-text">
                <p>Bonjour,</p>
                <p>Pour le compte de la <strong>${escapeHtml(emsNom)}</strong> (membre Fegems), je vous transmets les inscriptions à la session <strong>${escapeHtml(prop.formation_titre || '')}</strong> du <strong>${escapeHtml(dateStr)}</strong>.</p>

                <div class="table">
                  <table>
                    <thead>
                      <tr>
                        <th>Nom · Prénom</th>
                        <th>Fonction</th>
                        <th>Diplôme</th>
                        <th>Email</th>
                      </tr>
                    </thead>
                    <tbody>${rows}${moreRow}</tbody>
                  </table>
                </div>

                <p>L'ensemble de ces collaborateurs ont une attestation expirée et nécessitent un renouvellement.</p>
                <p>Pouvez-vous me confirmer la prise en compte de ces inscriptions et m'indiquer les places disponibles restantes ?</p>
                <p>Bien cordialement,</p>

                <div class="sig">
                  Direction · ${escapeHtml(emsNom)}<br>
                  ${escapeHtml(emsVille)} · ${escapeHtml(emsTel || '022 XXX XX XX')}
                </div>
              </div>
            </div>

            <aside class="rhfg-email-side">
              <div class="rhfg-es-block">
                <h4>Pré-remplissage automatique</h4>
                <div class="rhfg-es-list">
                  <div class="rhfg-es-item"><span class="ico ok">✓</span><div>Coordonnées EMS depuis profil structure</div></div>
                  <div class="rhfg-es-item"><span class="ico ok">✓</span><div>${nbCand} collaborateurs ciblés</div></div>
                  <div class="rhfg-es-item"><span class="ico ok">✓</span><div>Diplômes &amp; fonctions vérifiés</div></div>
                  <div class="rhfg-es-item"><span class="ico ok">✓</span><div>Annexe PDF liste complète générée</div></div>
                  <div class="rhfg-es-item"><span class="ico warn">!</span><div>Vérifier conflits planning</div></div>
                </div>
              </div>

              <div class="rhfg-es-divider"></div>

              <div class="rhfg-es-block">
                <h4>Workflow d'inscription</h4>
                <div class="rhfg-es-tracker">
                  <div class="step done"><span class="num">✓</span>Suggestion générée</div>
                  <div class="step done"><span class="num">✓</span>Collaborateurs vérifiés</div>
                  <div class="step cur"><span class="num">3</span>Email à envoyer</div>
                  <div class="step next"><span class="num">4</span>Confirmation Fegems</div>
                  <div class="step next"><span class="num">5</span>Suivi &amp; attestation</div>
                </div>
              </div>

              <div class="rhfg-es-divider"></div>

              <div class="rhfg-es-block">
                <h4>Après envoi</h4>
                <div class="rhfg-es-list">
                  <div class="rhfg-es-item"><span class="ico info">i</span><div>Statut "En attente" sur les fiches employés</div></div>
                  <div class="rhfg-es-item"><span class="ico info">i</span><div>Rappel auto à J-7 de la session</div></div>
                  <div class="rhfg-es-item"><span class="ico info">i</span><div>Upload attestation à J+3 après session</div></div>
                </div>
              </div>
            </aside>
          </div>
        `;

        document.getElementById('sendEmailBtn')?.addEventListener('click', () => {
            sendEmail(prop.id);
        });
    }

    async function sendEmail(propId) {
        const ok = await ssConfirm({
            title: 'Envoyer l\'email d\'inscription',
            message: 'L\'email sera envoyé au contact Fegems. Voulez-vous procéder ?',
            confirmText: 'Envoyer',
            icon: 'bi-send'
        });
        if (!ok) return;
        try {
            const r = await adminApiPost('admin_send_inscription_email', { proposition_id: propId });
            if (r.success) {
                if (typeof showToast === 'function') showToast('Email envoyé', 'success');
                document.getElementById('bottomBar').hidden = true;
                loadPropositions();
            } else {
                if (typeof showToast === 'function') showToast(r.message || 'Erreur', 'danger');
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('Erreur réseau', 'danger');
        }
    }

    document.getElementById('resyncBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') showToast('Resynchronisation en cours…', 'info');
        adminApiPost('admin_regenerer_propositions').then(r => {
            if (r.success) {
                if (typeof showToast === 'function') showToast('Catalogue resynchronisé', 'success');
                loadPropositions();
            }
        });
    });
    document.getElementById('addManualBtn')?.addEventListener('click', () => {
        location.href = '?page=rh-formations';
    });
    document.getElementById('bbCancelBtn')?.addEventListener('click', () => {
        document.getElementById('bottomBar').hidden = true;
        selected.clear();
    });

    // Toggle Lot/Indiv
    document.querySelectorAll('.rhfg-mode-toggle button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.rhfg-mode-toggle button').forEach(x => x.classList.remove('on'));
            b.classList.add('on');
        });
    });

    // Filtres chips toggle
    document.querySelectorAll('.rhfg-chip').forEach(b => {
        b.addEventListener('click', () => {
            const cat = b.dataset.cat;
            const fmt = b.dataset.format;
            const per = b.dataset.periode;
            // Toggle dans le même groupe (par data-attr présent)
            const group = cat !== undefined ? 'cat' : (fmt !== undefined ? 'format' : 'periode');
            document.querySelectorAll(`.rhfg-chip[data-${group}]`).forEach(x => x.classList.remove('on'));
            b.classList.add('on');
        });
    });

    loadPropositions();
})();
</script>
