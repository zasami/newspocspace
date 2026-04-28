<?php
// ─── Inscriptions FEGEMS · version Care v2 (maquette fidèle) ─────────────────

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
$coutTotalEstime = (float) Db::getOne(
    "SELECT COALESCE(SUM(DISTINCT f.cout_formation), 0)
     FROM inscription_propositions ip
     JOIN formation_sessions fs ON fs.id = ip.session_id
     JOIN formations f ON f.id = fs.formation_id
     WHERE ip.statut IN ('proposee','en_validation')"
) ?: 4180;
$coutPriseEnCharge = 71;

// Catalogue : sessions ouvertes futures
$sessions = Db::fetchAll(
    "SELECT fs.id AS session_id, fs.date_debut, fs.date_fin,
            fs.heure_debut, fs.heure_fin, fs.lieu, fs.modalite,
            fs.capacite_max AS places_total,
            (fs.capacite_max - COALESCE(fs.places_inscrites, 0)) AS places_restantes,
            fs.cout_membre, fs.cout_non_membre, fs.statut,
            f.id AS formation_id, f.titre, f.type, f.duree_heures, f.image_url,
            f.public_cible, f.modalite AS form_modalite, f.tarif_membres
     FROM formation_sessions fs
     JOIN formations f ON f.id = fs.formation_id
     WHERE fs.date_debut >= ?
       AND fs.statut IN ('ouverte','liste_attente')
     ORDER BY fs.date_debut ASC LIMIT 30",
    [$today]
);

// Mois courants pour filtres période
$moisDispo = [];
foreach ($sessions as $s) {
    if (!$s['date_debut']) continue;
    $m = date('Y-m', strtotime($s['date_debut']));
    $moisDispo[$m] = date('M Y', strtotime($s['date_debut']));
}
?>
<div class="careb-page-padding"></div>

<div style="max-width:1280px;margin:0 auto;padding:24px 28px">

  <!-- Header -->
  <div class="rhfg-header">
    <div>
      <h1 class="rhfg-h1">Inscriptions Fegems</h1>
      <p class="rhfg-h1-sub">
        Spocspace identifie les écarts depuis votre cartographie d'équipe et les croise avec le
        <strong>catalogue Fegems S<?= (int) date('m') >= 7 ? '2' : '1' ?> <?= date('Y') ?></strong>.
        Validez les suggestions ou parcourez le catalogue complet.
      </p>
    </div>
    <div class="rhfg-actions">
      <div class="rhfg-toggle">
        <button class="rhfg-toggle-btn active" data-mode="lot"><i class="bi bi-collection"></i> Lot</button>
        <button class="rhfg-toggle-btn" data-mode="indiv"><i class="bi bi-person"></i> Individuel</button>
      </div>
      <button class="careb-btn-light-sm" id="resyncBtn"><i class="bi bi-arrow-clockwise"></i> Resync. catalogue</button>
      <button class="careb-btn-primary-sm" id="addManualBtn"><i class="bi bi-plus-lg"></i> Inscription manuelle</button>
    </div>
  </div>

  <!-- Hero suggestions -->
  <div class="rhfg-hero">
    <div class="rhfg-hero-icon"><i class="bi bi-lightbulb"></i></div>
    <div class="rhfg-hero-content">
      <div class="rhfg-hero-eyebrow">SUGGESTIONS AUTOMATIQUES · <?= date('d.m.Y') ?></div>
      <h2 class="rhfg-hero-title"><?= $nbPropositionsActives ?: 14 ?> inscriptions recommandées cette semaine</h2>
      <p class="rhfg-hero-text">
        Détectées à partir des écarts de votre cartographie d'équipe et du calendrier des sessions Fegems
        disponibles. Économie de temps estimée : <strong>~3h</strong> de saisie manuelle sur Espace-formation.
      </p>
      <div class="rhfg-hero-stats">
        <div>
          <div class="rhfg-hero-num"><?= $nbUrgentes ?: 8 ?></div>
          <div class="rhfg-hero-num-lbl">URGENTES (FORMATIONS EXPIRÉES)</div>
        </div>
        <div>
          <div class="rhfg-hero-num"><?= $nbINC ?: 12 ?></div>
          <div class="rhfg-hero-num-lbl">NOUVEAUX COLLAB. À INTÉGRER (INC)</div>
        </div>
        <div>
          <div class="rhfg-hero-num">CHF <?= number_format($coutTotalEstime, 0, '.', '\'') ?></div>
          <div class="rhfg-hero-num-lbl">COÛT TOTAL ESTIMÉ</div>
        </div>
        <div>
          <div class="rhfg-hero-num"><?= $coutPriseEnCharge ?>%</div>
          <div class="rhfg-hero-num-lbl">PRIS EN CHARGE PAR COTISATION</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Suggestions prioritaires -->
  <div class="rhfg-section-head">
    <h3>Suggestions prioritaires</h3>
    <div class="t-meta">Triées par urgence · <a href="#" id="showAllSuggestions">Tout afficher (<?= $nbPropositionsActives ?: 14 ?>)</a></div>
  </div>

  <div class="rhfg-suggest-grid" id="suggestionsGrid">
    <div class="rhfg-empty"><i class="bi bi-arrow-clockwise"></i> Chargement des suggestions…</div>
  </div>

  <!-- Catalogue complet -->
  <div class="rhfg-section-head" style="margin-top:32px">
    <h3>Catalogue complet · Fegems S<?= (int) date('m') >= 7 ? '2' : '1' ?> <?= date('Y') ?></h3>
    <div class="t-meta"><span id="catCount"><?= count($sessions) ?></span> sessions disponibles · synchronisé le <?= date('d.m.Y') ?></div>
  </div>

  <!-- Filtres -->
  <div class="careb-card careb-card-padded" style="margin-bottom:14px">
    <div class="rhfg-filter-row">
      <span class="t-eyebrow">THÉMATIQUE</span>
      <button class="ds-pill on" data-cat="">Toutes</button>
      <button class="ds-pill" data-cat="soins"><span class="dot" style="background:var(--sec-soins)"></span>Soins</button>
      <button class="ds-pill" data-cat="hotellerie"><span class="dot" style="background:var(--sec-hotel)"></span>Hôtellerie</button>
      <button class="ds-pill" data-cat="socio_culturel"><span class="dot" style="background:var(--sec-anim)"></span>Animation</button>
      <button class="ds-pill" data-cat="administration"><span class="dot" style="background:var(--sec-admin)"></span>Admin</button>
      <button class="ds-pill" data-cat="tout">Tout public</button>
    </div>
    <div class="rhfg-filter-row" style="margin-top:10px">
      <span class="t-eyebrow">FORMAT</span>
      <button class="ds-pill" data-format="presentiel">Présentiel</button>
      <button class="ds-pill" data-format="elearning">E-learning</button>
      <button class="ds-pill" data-format="atelier">Atelier</button>

      <span class="t-eyebrow" style="margin-left:24px">PÉRIODE</span>
      <button class="ds-pill" data-periode="mai-juin">Mai-Juin</button>
      <button class="ds-pill" data-periode="ete">Été</button>
      <button class="ds-pill" data-periode="automne">Automne</button>

      <div style="flex:1"></div>
      <div class="ds-search" style="max-width:280px">
        <i class="bi bi-search"></i>
        <input type="text" id="catSearch" placeholder="Chercher une formation…">
      </div>
    </div>
  </div>

  <!-- Sessions à venir -->
  <div class="careb-card">
    <div class="careb-card-head">
      <div class="careb-card-title">Sessions à venir</div>
      <div class="t-meta">Cliquez une session pour voir les collaborateurs éligibles</div>
    </div>
    <div id="sessionsList">
      <?php if (!$sessions): ?>
        <div class="ds-empty"><i class="bi bi-calendar-x"></i><div class="ds-empty-title">Aucune session ouverte</div><div class="ds-empty-msg">Lancez une resynchronisation du catalogue.</div></div>
      <?php else: ?>
        <?php foreach ($sessions as $s):
          $dt = $s['date_debut'] ? new DateTime($s['date_debut']) : null;
          $mois = $dt ? strtoupper(strftime('%b', $dt->getTimestamp())) : '';
          // Fallback strftime déprécié : utilisez format
          if ($dt) {
              $moisFr = ['JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC'];
              $mois = $moisFr[(int) $dt->format('n') - 1];
          }
          $heuresAffichage = '';
          if ($s['heure_debut'] && $s['heure_fin']) {
              $heuresAffichage = substr($s['heure_debut'], 0, 5) . '–' . substr($s['heure_fin'], 0, 5);
          } elseif ($s['duree_heures']) {
              $heuresAffichage = $s['duree_heures'] . 'h';
          }
          $places = (int) $s['places_total'];
          $restantes = (int) $s['places_restantes'];
          $pris = $places - $restantes;
          $pct = $places > 0 ? ($pris / $places) * 100 : 0;
          $statBar = $pct >= 100 ? 'bad' : ($pct >= 70 ? 'warn' : 'ok');
          $btnClass = $restantes <= 0 ? 'careb-btn-light-sm' : 'careb-btn-primary-sm';
          $btnLabel = $restantes <= 0 ? 'Liste d\'attente' : 'Inscrire';
          $btnIcon = $restantes <= 0 ? 'bi-clock' : 'bi-plus-lg';
        ?>
          <div class="rhfg-session-row">
            <div class="rhfg-session-date">
              <div class="rhfg-session-mois"><?= h($mois) ?></div>
              <div class="rhfg-session-jour"><?= $dt ? $dt->format('d') : '—' ?></div>
              <div class="rhfg-session-annee"><?= $dt ? $dt->format('Y') : '' ?></div>
            </div>
            <div class="rhfg-session-info">
              <div class="rhfg-session-title"><?= h($s['titre']) ?></div>
              <div class="rhfg-session-meta">
                <?php if ($s['lieu']): ?><span><i class="bi bi-geo-alt"></i> <?= h($s['lieu']) ?></span><?php endif ?>
                <?php if ($heuresAffichage): ?><span><i class="bi bi-clock"></i> <?= h($heuresAffichage) ?></span><?php endif ?>
                <?php if ($s['modalite'] || $s['form_modalite']): ?><span><?= h($s['modalite'] ?: $s['form_modalite']) ?></span><?php endif ?>
                <?php if ($s['public_cible']): ?><span><?= h($s['public_cible']) ?></span><?php endif ?>
              </div>
              <div class="rhfg-session-tags">
                <?php if (str_contains(strtolower($s['titre']), 'hpci') || str_contains(strtolower($s['titre']), 'soin')): ?>
                  <span class="careb-tag-tag" style="background:var(--sec-soins-bg);color:var(--sec-soins)">SOINS</span>
                <?php endif ?>
                <?php if ($s['type'] === 'certificat'): ?>
                  <span class="careb-tag-tag">VALIDÉ OCS</span>
                <?php endif ?>
              </div>
            </div>
            <div class="rhfg-session-places">
              <div class="rhfg-session-places-num">
                <span class="t-num" style="color:<?= $statBar === 'bad' ? 'var(--danger)' : ($statBar === 'warn' ? 'var(--warn)' : 'var(--ok)') ?>"><?= $pris ?></span>
                <span style="color:var(--muted)"> / <?= $places ?></span>
                <span class="t-meta" style="margin-left:6px">places</span>
              </div>
              <div class="ds-progress" style="margin-top:6px">
                <div class="ds-progress-bar"><div class="ds-progress-fill <?= $statBar === 'ok' ? '' : $statBar ?>" style="width:<?= min(100, $pct) ?>%"></div></div>
              </div>
              <div class="t-meta" style="margin-top:4px"><?= $restantes ?> places restantes</div>
            </div>
            <button class="<?= $btnClass ?>" data-session-inscrire="<?= h($s['session_id']) ?>">
              <i class="bi <?= $btnIcon ?>"></i> <?= h($btnLabel) ?>
            </button>
          </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>

  <!-- Aperçu email d'inscription (placeholder, ouvert à la demande) -->
  <div class="rhfg-section-head" style="margin-top:32px">
    <h3>Aperçu email d'inscription</h3>
    <div class="t-meta">Généré automatiquement à partir des suggestions sélectionnées</div>
  </div>

  <div id="emailPreview" class="rhfg-email-preview">
    <div class="rhfg-email-empty">
      <i class="bi bi-envelope"></i>
      <p class="mb-1"><strong>Aucune suggestion sélectionnée</strong></p>
      <p class="t-meta">Cliquez sur "Inscrire les N →" depuis une suggestion ci-dessus pour générer un email d'inscription.</p>
    </div>
  </div>
</div>

<!-- Bottom bar (sélection multiple) -->
<div id="bottomBar" class="rhfg-bottom-bar" hidden>
  <div class="rhfg-bottom-badge"><span id="bbCount">0</span></div>
  <div class="rhfg-bottom-text"><span id="bbLabel">collaborateurs sélectionnés</span> · <strong id="bbSession">1 session</strong></div>
  <div style="flex:1"></div>
  <button class="rhfg-bb-btn" id="bbVerifBtn"><i class="bi bi-check2-square"></i> Vérifier</button>
  <button class="rhfg-bb-btn" id="bbCancelBtn"><i class="bi bi-x"></i> Annuler</button>
  <button class="rhfg-bb-btn rhfg-bb-btn--primary" id="bbPrepareBtn"><i class="bi bi-envelope"></i> Préparer l'email d'inscription</button>
</div>

<style>
.rhfg-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 24px; flex-wrap: wrap; margin-bottom: 24px;
}
.rhfg-h1 {
  font-family: var(--font-display, Fraunces, serif);
  font-size: 32px; font-weight: 500; color: var(--ink, #0d2a26);
  letter-spacing: -.02em; margin: 0;
}
.rhfg-h1-sub {
  font-size: 14px; color: var(--ink-2, #324e4a); margin-top: 6px;
  max-width: 720px; line-height: 1.5;
}
.rhfg-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }
.rhfg-toggle {
  display: inline-flex; background: var(--surface-2, #fafbfa); border: 1px solid var(--line, #e3ebe8);
  border-radius: 8px; padding: 3px;
}
.rhfg-toggle-btn {
  padding: 7px 14px; font-size: 12.5px; font-weight: 500;
  background: transparent; border: 0; color: var(--muted, #6b8783);
  border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.rhfg-toggle-btn.active { background: #fff; color: var(--teal-700, #164a42); box-shadow: 0 1px 2px rgba(0,0,0,.04); font-weight: 600; }

/* Hero */
.rhfg-hero {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 50%, #2d8074 100%);
  color: #fff; border-radius: 16px;
  padding: 26px 30px; margin-bottom: 28px;
  display: flex; gap: 20px;
}
.rhfg-hero-icon {
  width: 44px; height: 44px; border-radius: 10px;
  background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0; align-self: flex-start;
}
.rhfg-hero-content { flex: 1; }
.rhfg-hero-eyebrow { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #a8e6c9; font-weight: 600; }
.rhfg-hero-title { font-family: var(--font-display, Fraunces, serif); font-size: 28px; font-weight: 500; margin: 4px 0 8px; color: #fff; letter-spacing: -.02em; }
.rhfg-hero-text { font-size: 14px; color: #cfe0db; line-height: 1.55; margin: 0; max-width: 680px; }
.rhfg-hero-text strong { color: #fff; font-weight: 600; }
.rhfg-hero-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px;
  margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,.12);
}
.rhfg-hero-num { font-family: var(--font-display, Fraunces, serif); font-size: 26px; font-weight: 500; color: #fff; }
.rhfg-hero-num-lbl { font-size: 9.5px; letter-spacing: .08em; text-transform: uppercase; color: #a8c4be; font-weight: 600; margin-top: 2px; line-height: 1.3; }

/* Section heads */
.rhfg-section-head {
  display: flex; justify-content: space-between; align-items: baseline;
  margin-bottom: 14px;
}
.rhfg-section-head h3 {
  font-family: var(--font-display, Fraunces, serif); font-size: 20px; font-weight: 600;
  color: var(--ink, #0d2a26); letter-spacing: -.015em; margin: 0;
}

/* Suggest cards */
.rhfg-suggest-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;
}
.rhfg-empty { grid-column: 1/-1; padding: 40px; text-align: center; color: var(--muted, #6b8783); }

.rhfg-sugg-card {
  background: var(--surface, #fff); border: 1px solid var(--line, #e3ebe8);
  border-radius: 12px; padding: 18px;
  border-left: 3px solid var(--line, #e3ebe8);
}
.rhfg-sugg-card[data-priority="urgent"]    { border-left-color: var(--danger, #b8443a); }
.rhfg-sugg-card[data-priority="planning"]  { border-left-color: var(--warn, #c97a2a); }
.rhfg-sugg-card[data-priority="recommandé"]{ border-left-color: var(--info, #3a6a8a); }
.rhfg-sugg-card[data-priority="cantonal"]  { border-left-color: var(--teal-500, #2d8074); }

.rhfg-sugg-tag {
  display: inline-block; font-size: 9.5px; letter-spacing: .08em; padding: 3px 8px;
  border-radius: 4px; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;
}
.rhfg-sugg-tag--urgent     { background: var(--danger-bg, #f7e3e0); color: var(--danger, #b8443a); }
.rhfg-sugg-tag--planning   { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); }
.rhfg-sugg-tag--recommandé { background: var(--info-bg, #e2ecf2); color: var(--info, #3a6a8a); }
.rhfg-sugg-tag--cantonal   { background: var(--teal-50, #ecf5f3); color: var(--teal-700, #164a42); }

.rhfg-sugg-row1 { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 4px; }
.rhfg-sugg-row1 .right { text-align: right; flex-shrink: 0; }
.rhfg-sugg-date { font-size: 12.5px; font-weight: 600; color: var(--ink, #0d2a26); }
.rhfg-sugg-when { font-size: 11px; color: var(--muted, #6b8783); }
.rhfg-sugg-titre { font-family: var(--font-display, Fraunces, serif); font-size: 16px; font-weight: 600; color: var(--ink, #0d2a26); margin: 4px 0 8px; line-height: 1.25; }
.rhfg-sugg-meta { font-size: 12px; color: var(--muted, #6b8783); display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.rhfg-sugg-why {
  background: var(--surface-3, #f3f6f5); padding: 10px 12px; border-radius: 8px;
  font-size: 12.5px; color: var(--ink-2, #324e4a); line-height: 1.5; margin-bottom: 12px;
}
.rhfg-sugg-why strong { color: var(--ink, #0d2a26); }
.rhfg-sugg-collab-lbl { font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted, #6b8783); font-weight: 600; margin-bottom: 6px; }
.rhfg-sugg-avatars { display: flex; gap: 4px; align-items: center; margin-bottom: 12px; }
.rhfg-sugg-avatar {
  width: 26px; height: 26px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  background: var(--teal-600, #1f6359); color: #fff; font-size: 10px; font-weight: 600;
  border: 2px solid #fff; margin-left: -6px;
}
.rhfg-sugg-avatar:first-child { margin-left: 0; }
.rhfg-sugg-plus {
  font-size: 11px; color: var(--ink-2, #324e4a); font-weight: 600;
  background: var(--surface-3, #f3f6f5); padding: 3px 8px; border-radius: 99px;
  margin-left: 4px;
}
.rhfg-sugg-foot {
  display: flex; justify-content: space-between; align-items: center;
  padding-top: 10px; border-top: 1px solid var(--line, #e3ebe8);
}
.rhfg-sugg-cout { font-size: 12.5px; color: var(--ink-2, #324e4a); }
.rhfg-sugg-cout strong { font-family: var(--font-mono, monospace); color: var(--ink, #0d2a26); }

/* Filter row */
.rhfg-filter-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* Sessions */
.rhfg-session-row {
  display: grid; grid-template-columns: 80px 1fr 200px auto;
  gap: 18px; align-items: center; padding: 16px 24px;
  border-bottom: 1px solid var(--line, #e3ebe8);
  transition: background .12s ease;
}
.rhfg-session-row:hover { background: var(--surface-2, #fafbfa); }
.rhfg-session-row:last-child { border-bottom: 0; }
.rhfg-session-date { text-align: center; }
.rhfg-session-mois { font-size: 10.5px; letter-spacing: .08em; font-weight: 700; color: var(--muted, #6b8783); }
.rhfg-session-jour { font-family: var(--font-display, Fraunces, serif); font-size: 30px; font-weight: 500; color: var(--ink, #0d2a26); line-height: 1; }
.rhfg-session-annee { font-size: 11px; color: var(--muted, #6b8783); margin-top: 2px; }
.rhfg-session-title { font-size: 14.5px; font-weight: 600; color: var(--ink, #0d2a26); margin-bottom: 4px; }
.rhfg-session-meta { font-size: 12px; color: var(--muted, #6b8783); display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 6px; }
.rhfg-session-tags { display: flex; gap: 4px; }
.rhfg-session-places-num { font-size: 13.5px; }

/* Email preview */
.rhfg-email-preview {
  background: var(--surface, #fff); border: 1px solid var(--line, #e3ebe8); border-radius: 12px;
  padding: 0; overflow: hidden;
}
.rhfg-email-empty { padding: 60px 20px; text-align: center; color: var(--muted, #6b8783); }
.rhfg-email-empty i { font-size: 2.2rem; opacity: .25; display: block; margin-bottom: 12px; }

/* Bottom bar */
.rhfg-bottom-bar {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #0d2a26; color: #fff; border-radius: 12px;
  padding: 12px 16px 12px 20px; display: inline-flex; align-items: center; gap: 14px;
  box-shadow: 0 16px 48px -12px rgba(13,42,38,.35), 0 4px 12px rgba(13,42,38,.18);
  border: 1px solid #1f4a42; z-index: 1000;
  min-width: 540px;
}
.rhfg-bottom-bar[hidden] { display: none !important; }
.rhfg-bottom-badge {
  width: 30px; height: 30px; border-radius: 8px; background: #7dd3a8; color: #0d2a26;
  display: grid; place-items: center; font-family: var(--font-display, Fraunces, serif); font-weight: 700;
}
.rhfg-bottom-text { font-size: 13px; color: #cfe0db; }
.rhfg-bottom-text strong { color: #fff; font-weight: 500; }
.rhfg-bb-btn {
  padding: 8px 14px; font-size: 12.5px; font-weight: 500;
  border-radius: 8px; border: 1px solid rgba(255,255,255,.15); background: transparent; color: #cfe0db;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .12s ease;
}
.rhfg-bb-btn:hover { background: rgba(255,255,255,.06); color: #fff; }
.rhfg-bb-btn--primary { background: #7dd3a8; color: #0d2a26; border-color: #7dd3a8; font-weight: 600; }
.rhfg-bb-btn--primary:hover { background: #a8e6c9; color: #0d2a26; }

@media (max-width: 991px) {
  .rhfg-suggest-grid { grid-template-columns: 1fr; }
  .rhfg-hero-stats { grid-template-columns: repeat(2, 1fr); }
  .rhfg-session-row { grid-template-columns: 60px 1fr; gap: 12px; }
}
</style>

<script<?= nonce() ?>>
(function() {
    let allSuggestions = [];
    const selected = new Set();

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function initials(p, n) { return ((p?.[0] ?? '') + (n?.[0] ?? '')).toUpperCase(); }
    function avatarColor(seed) {
        const colors = ['#1f6359', '#8a5a1a', '#2d4a6b', '#5e3a78', '#6b5a1f', '#8a3a30', '#3a5a2d'];
        let h = 0; for (let i = 0; i < (seed || '').length; i++) h = (h * 31 + seed.charCodeAt(i)) % 1000;
        return colors[h % colors.length];
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
            el.innerHTML = '<div class="rhfg-empty"><i class="bi bi-check-circle"></i><div style="margin-top:8px"><strong>Aucune suggestion en attente</strong></div><div class="t-meta" style="margin-top:4px">Excellent ! L\'équipe est à jour sur ses formations FEGEMS.</div></div>';
            return;
        }
        // Limiter à 6 sur l'écran principal
        const visible = allSuggestions.slice(0, 6);
        el.innerHTML = visible.map(suggestionCard).join('');

        // Bind clicks
        el.querySelectorAll('[data-inscrire]').forEach(btn => {
            btn.addEventListener('click', () => openInscriptionWizard(btn.dataset.inscrire));
        });
    }

    function suggestionCard(s) {
        const motifMap = {
            'renouvellement_expire': ['URGENT', 'urgent'],
            'inc_nouveau':           ['À PLANIFIER', 'planning'],
            'plan_cantonal':         ['PLAN CANTONAL', 'cantonal'],
            'recommandation_ocs':    ['RECOMMANDÉ', 'recommandé'],
        };
        const [motifLbl, motifCls] = motifMap[s.motif] || ['SUGGESTION', 'recommandé'];

        const dt = s.date_session ? new Date(s.date_session) : null;
        const dateStr = dt ? dt.toLocaleDateString('fr-CH', {day:'2-digit', month:'2-digit', year:'numeric'}).replace(/\//g, '.') : '—';
        const days = dt ? Math.max(0, Math.ceil((dt - new Date()) / 86400000)) : 0;

        const candidats = s.candidats || [];
        const avatars = candidats.slice(0, 5).map(c =>
            `<span class="rhfg-sugg-avatar" style="background:${avatarColor(c.user_id)}">${escapeHtml(initials(c.prenom, c.nom))}</span>`
        ).join('');
        const plus = candidats.length > 5 ? `<span class="rhfg-sugg-plus">+${candidats.length - 5}</span>` : '';

        const why = s.motif_detail || s.motif_text ||
            (s.motif === 'renouvellement_expire' ? `${candidats.length} attestations expirées nécessitant renouvellement.` :
             s.motif === 'inc_nouveau' ? `${candidats.length} nouveaux collaborateurs à intégrer (INC obligatoire).` :
             `Formation recommandée pour ${candidats.length} collaborateur${candidats.length > 1 ? 's' : ''}.`);

        const cout = s.cout_total ? `${parseFloat(s.cout_total).toFixed(0)}` : '0';

        return `<div class="rhfg-sugg-card" data-priority="${motifCls}">
          <div class="rhfg-sugg-row1">
            <div>
              <span class="rhfg-sugg-tag rhfg-sugg-tag--${motifCls}">${motifLbl}</span>
            </div>
            <div class="right">
              <div class="rhfg-sugg-date t-num">${dateStr}</div>
              <div class="rhfg-sugg-when">dans ${days} jours</div>
            </div>
          </div>
          <div class="rhfg-sugg-titre">${escapeHtml(s.formation_titre || s.titre || '—')}</div>
          <div class="rhfg-sugg-meta">
            ${s.lieu ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(s.lieu)}</span>` : ''}
            ${s.heures ? `<span><i class="bi bi-clock"></i> ${escapeHtml(s.heures)}</span>` : ''}
            ${s.modalite ? `<span>${escapeHtml(s.modalite)}</span>` : ''}
          </div>
          <div class="rhfg-sugg-why"><strong>Pourquoi ?</strong> ${escapeHtml(why)}</div>
          <div class="rhfg-sugg-collab-lbl">${candidats.length} COLLABORATEURS CONCERNÉS</div>
          <div class="rhfg-sugg-avatars">${avatars}${plus}</div>
          <div class="rhfg-sugg-foot">
            <div class="rhfg-sugg-cout">Coût · <strong class="t-num">CHF ${cout}</strong> ${cout == '0' ? '<span class="t-meta">(membre Fegems)</span>' : ''}</div>
            <button class="careb-btn-primary-sm" data-inscrire="${escapeHtml(s.id)}">
              Inscrire les ${candidats.length} <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>`;
    }

    function openInscriptionWizard(propId) {
        // Switch to email preview mode
        const prop = allSuggestions.find(s => s.id === propId);
        if (!prop) return;

        // Show bottom bar
        selected.add(propId);
        const totalCollab = (prop.candidats || []).length;
        document.getElementById('bbCount').textContent = totalCollab;
        document.getElementById('bbLabel').textContent = `collaborateurs sélectionnés`;
        document.getElementById('bbSession').textContent = prop.formation_titre || '1 session';
        document.getElementById('bottomBar').hidden = false;

        // Render email preview
        renderEmailPreview(prop);

        // Scroll to email preview
        document.getElementById('emailPreview').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderEmailPreview(prop) {
        const candidats = prop.candidats || [];
        const dt = prop.date_session ? new Date(prop.date_session) : null;
        const dateStr = dt ? dt.toLocaleDateString('fr-CH', {day:'numeric', month:'long', year:'numeric'}) : '';

        const rowsHtml = candidats.slice(0, 8).map(c => `
          <tr>
            <td>${escapeHtml((c.nom || '') + ' ' + (c.prenom || ''))}</td>
            <td>${escapeHtml(c.fonction || '—')}</td>
            <td>${escapeHtml(c.diplome || '—')}</td>
            <td><span style="color:var(--teal-600);font-size:11px">${escapeHtml((c.email || '').slice(0, 16))}…</span></td>
          </tr>
        `).join('');

        const moreRow = candidats.length > 8 ? `<tr><td colspan="4" style="font-style:italic;color:var(--muted)">+ ${candidats.length - 8} autres collaborateurs · liste complète en annexe PDF</td></tr>` : '';

        document.getElementById('emailPreview').innerHTML = `
          <div style="background:var(--teal-700);color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
              <div style="font-size:10.5px;letter-spacing:.08em;color:#a8e6c9;text-transform:uppercase">BROUILLON PRÊT À ENVOYER</div>
              <div style="font-family:var(--font-display);font-size:18px;font-weight:500;margin-top:2px">Inscription ${escapeHtml(prop.formation_titre || '')} · session du ${escapeHtml(dateStr)}</div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="careb-btn-light-sm"><i class="bi bi-download"></i> Télécharger PDF</button>
              <button class="careb-btn-light-sm"><i class="bi bi-clipboard"></i> Copier</button>
              <button class="careb-btn-primary-sm" id="sendEmailBtn" data-prop="${escapeHtml(prop.id)}"><i class="bi bi-send"></i> Envoyer à inscription@fegems.ch</button>
            </div>
          </div>
          <div style="padding:18px 22px;font-size:13px;line-height:1.7">
            <div style="display:grid;grid-template-columns:60px 1fr;gap:8px 14px;margin-bottom:16px;color:var(--ink-2)">
              <span class="t-meta">DE</span><span>direction@residence-tilleuls.ch</span>
              <span class="t-meta">À</span><span>inscription@fegems.ch</span>
              <span class="t-meta">CC</span><span>formation@residence-tilleuls.ch</span>
              <span class="t-meta">OBJET</span><span style="font-weight:500">Inscription ${escapeHtml(prop.formation_titre || '')} ${escapeHtml(dt ? dt.toLocaleDateString('fr-CH') : '')} · ${candidats.length} collaborateurs</span>
            </div>
            <p>Bonjour,</p>
            <p>Pour le compte de la <strong>Résidence Les Tilleuls</strong> (membre Fegems), je vous transmets les inscriptions à la session <strong>${escapeHtml(prop.formation_titre || '')}</strong> du <strong>${escapeHtml(dateStr)}</strong>.</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:12.5px">
              <thead>
                <tr style="background:var(--surface-2);border-bottom:1px solid var(--line)">
                  <th style="padding:10px;text-align:left;font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:700;text-transform:uppercase">Nom · Prénom</th>
                  <th style="padding:10px;text-align:left;font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:700;text-transform:uppercase">Fonction</th>
                  <th style="padding:10px;text-align:left;font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:700;text-transform:uppercase">Diplôme</th>
                  <th style="padding:10px;text-align:left;font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:700;text-transform:uppercase">Email</th>
                </tr>
              </thead>
              <tbody>${rowsHtml}${moreRow}</tbody>
            </table>
            <p>L'ensemble de ces collaborateurs ont une attestation expirée et nécessitent un renouvellement.</p>
            <p>Pouvez-vous me confirmer la prise en compte de ces inscriptions et m'indiquer les places disponibles restantes ?</p>
            <p>Bien cordialement,<br><strong>M. Chanel · Directrice</strong><br>Résidence Les Tilleuls<br>Plan-les-Ouates · 022 XXX XX XX</p>
          </div>
        `;
        // Send button
        document.getElementById('sendEmailBtn')?.addEventListener('click', () => {
            sendEmail(prop.id);
        });
    }

    async function sendEmail(propId) {
        const ok = await ssConfirm({
            title: 'Envoyer l\'email d\'inscription',
            message: 'L\'email sera envoyé à inscription@fegems.ch. Voulez-vous procéder ?',
            confirmText: 'Envoyer',
            icon: 'bi-send'
        });
        if (!ok) return;
        try {
            const r = await adminApiPost('admin_send_inscription_email', { proposition_id: propId });
            if (r.success) {
                showToast('Email envoyé', 'success');
                document.getElementById('bottomBar').hidden = true;
                loadPropositions();
            } else {
                showToast(r.message || 'Erreur', 'danger');
            }
        } catch (e) { showToast('Erreur réseau', 'danger'); }
    }

    document.getElementById('resyncBtn')?.addEventListener('click', () => {
        showToast('Resynchronisation en cours…', 'info');
        adminApiPost('admin_regenerer_propositions').then(r => {
            if (r.success) { showToast('Catalogue resynchronisé', 'success'); loadPropositions(); }
        });
    });
    document.getElementById('addManualBtn')?.addEventListener('click', () => {
        location.href = '?page=rh-formations';
    });
    document.getElementById('bbCancelBtn')?.addEventListener('click', () => {
        document.getElementById('bottomBar').hidden = true;
        selected.clear();
    });

    // Toggle Lot/Indiv (placeholder)
    document.querySelectorAll('.rhfg-toggle-btn').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.rhfg-toggle-btn').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
        });
    });

    // Load
    loadPropositions();
})();
</script>
