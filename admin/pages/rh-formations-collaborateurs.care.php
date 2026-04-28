<?php
// ─── Collaborateurs · formation · liste filtrable + actions bulk ─────────────

$annee = (int) date('Y');
$today = date('Y-m-d');

$secteurMeta = [
    'soins'           => ['Soins',           '#1f6359', 'bi-heart-pulse'],
    'socio_culturel'  => ['Animation',       '#9268b3', 'bi-palette'],
    'hotellerie'      => ['Hôtellerie',      '#c97a2a', 'bi-cup-hot'],
    'maintenance'     => ['Maintenance',     '#5a82a8', 'bi-tools'],
    'administration'  => ['Administration',  '#3a6a8a', 'bi-briefcase'],
    'management'      => ['Management',      '#164a42', 'bi-stars'],
];

// ── Liste collaborateurs avec stats formation ───────────────────
$collabs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, u.photo, u.taux,
            f.nom AS fonction_nom, f.code AS fonction_code, f.secteur_fegems,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id) AS nb_them,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'a_jour') AS nb_ok,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'haute') AS nb_haute,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'moyenne') AS nb_moyenne,
            (SELECT MIN(cu.date_expiration) FROM competences_user cu
              WHERE cu.user_id = u.id AND cu.date_expiration IS NOT NULL AND cu.date_expiration >= ?) AS prochaine_exp,
            (SELECT COUNT(*) FROM formation_participants p JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut IN ('present','valide')
                AND YEAR(COALESCE(p.date_realisation, fo.date_debut)) = ?) AS nb_form_realisees,
            (SELECT COUNT(*) FROM formation_participants p JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut = 'inscrit' AND fo.date_debut > CURDATE()) AS nb_form_a_venir,
            (SELECT COUNT(*) FROM formation_participants p
              WHERE p.user_id = u.id AND p.statut IN ('present','valide')
                AND (p.certificat_url IS NULL OR p.certificat_url = '')) AS nb_sans_cert,
            (SELECT COALESCE(SUM(p.heures_realisees), 0) FROM formation_participants p
              JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut IN ('present','valide')
                AND YEAR(COALESCE(p.date_realisation, fo.date_debut)) = ?) AS heures_an
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1
     ORDER BY u.nom, u.prenom",
    [$today, $annee, $annee]
);

// ── Stats globales (cartes filtrables) ──────────────────────────
$nbTotal = count($collabs);
$nbConformes = 0;
$nbEcartsCritiques = 0;
$nbSansCertificat = 0;
$nbExpirentSoon = 0;
$soonDate = date('Y-m-d', strtotime('+60 days'));
$nbInscritsAVenir = 0;

foreach ($collabs as $c) {
    if ($c['nb_them'] > 0 && $c['nb_haute'] === 0 && $c['nb_moyenne'] === 0) $nbConformes++;
    if ($c['nb_haute'] > 0) $nbEcartsCritiques++;
    if ($c['nb_sans_cert'] > 0) $nbSansCertificat++;
    if ($c['prochaine_exp'] && $c['prochaine_exp'] <= $soonDate) $nbExpirentSoon++;
    if ($c['nb_form_a_venir'] > 0) $nbInscritsAVenir++;
}

$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';
$fmtN = fn($n, $d=0) => number_format((float)$n, $d, ',', "'");
?>

<div class="fcl-page">

  <!-- HEAD -->
  <div class="fcl-head">
    <div>
      <h1 class="fcl-h1">Collaborateurs · formation</h1>
      <div class="fcl-sub">
        Vue détaillée par collaborateur · <strong><?= h($emsNom) ?></strong> ·
        <?= $nbTotal ?> personnes actives · année <?= $annee ?>
      </div>
    </div>
    <div class="fcl-actions">
      <button class="fcl-btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      <button class="fcl-btn" id="fclExportCsv"><i class="bi bi-download"></i> Export CSV</button>
      <button class="fcl-btn fcl-btn-primary" id="fclSendReminderBulk" disabled>
        <i class="bi bi-envelope"></i> Envoyer rappel <span id="fclBulkCount"></span>
      </button>
    </div>
  </div>

  <!-- STAT CARDS (cliquables, filtrent la liste) -->
  <div class="fcl-stats">
    <button class="fcl-stat fcl-stat-active" data-filter="all">
      <div class="fcl-stat-icon" style="background:#ecf5f3;color:#1f6359"><i class="bi bi-people"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbTotal ?></div>
        <div class="fcl-stat-lbl">Tous</div>
      </div>
    </button>
    <button class="fcl-stat" data-filter="conformes">
      <div class="fcl-stat-icon" style="background:#e3f0ea;color:#3d8b6b"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbConformes ?></div>
        <div class="fcl-stat-lbl">Conformes</div>
      </div>
    </button>
    <button class="fcl-stat" data-filter="ecarts">
      <div class="fcl-stat-icon" style="background:#f7e3e0;color:#b8443a"><i class="bi bi-exclamation-triangle"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbEcartsCritiques ?></div>
        <div class="fcl-stat-lbl">Écarts critiques</div>
      </div>
    </button>
    <button class="fcl-stat" data-filter="sans-cert">
      <div class="fcl-stat-icon" style="background:#fbf0e1;color:#c97a2a"><i class="bi bi-paperclip"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbSansCertificat ?></div>
        <div class="fcl-stat-lbl">Sans certificat</div>
      </div>
    </button>
    <button class="fcl-stat" data-filter="expire-soon">
      <div class="fcl-stat-icon" style="background:#e2ecf2;color:#3a6a8a"><i class="bi bi-clock-history"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbExpirentSoon ?></div>
        <div class="fcl-stat-lbl">Expire &lt; 60j</div>
      </div>
    </button>
    <button class="fcl-stat" data-filter="inscrits">
      <div class="fcl-stat-icon" style="background:#f3eef0;color:#7a3a5d"><i class="bi bi-calendar-event"></i></div>
      <div>
        <div class="fcl-stat-num"><?= $nbInscritsAVenir ?></div>
        <div class="fcl-stat-lbl">Inscrit·es à venir</div>
      </div>
    </button>
  </div>

  <!-- FILTERS BAR -->
  <div class="fcl-filters">
    <span class="fcl-filter-lbl">Secteur</span>
    <button class="fcl-pill on" data-secteur="">Tous</button>
    <?php foreach ($secteurMeta as $key => $m): ?>
      <button class="fcl-pill" data-secteur="<?= h($key) ?>">
        <span class="fcl-pill-dot" style="background:<?= h($m[1]) ?>"></span><?= h($m[0]) ?>
      </button>
    <?php endforeach ?>
    <div class="fcl-filter-search">
      <i class="bi bi-search"></i>
      <input type="text" id="fclSearch" placeholder="Rechercher un collaborateur, fonction…">
    </div>
  </div>

  <!-- TABLE -->
  <div class="fcl-card">
    <div class="fcl-card-head">
      <h3>Liste collaborateurs <span class="fcl-shown-count" id="fclShownCount"><?= $nbTotal ?></span></h3>
      <div class="fcl-card-actions">
        <label class="fcl-checkall">
          <input type="checkbox" id="fclSelectAll"> <span>Tout sélectionner</span>
        </label>
      </div>
    </div>

    <div class="fcl-table-wrap">
      <table class="fcl-table" id="fclTable">
        <thead>
          <tr>
            <th style="width:36px"></th>
            <th>Collaborateur</th>
            <th>Fonction</th>
            <th>Secteur</th>
            <th class="num">Conformité</th>
            <th class="num">Réalisées <?= $annee ?></th>
            <th class="num">Sans cert.</th>
            <th class="num">Prochaine éché.</th>
            <th class="num">Heures</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($collabs as $c):
            $sec = $c['secteur_fegems'] ?: '';
            [$secLbl, $secColor] = $secteurMeta[$sec] ?? ['—', '#6b8783'];
            $pct = $c['nb_them'] > 0 ? round(($c['nb_ok'] / $c['nb_them']) * 100) : 0;
            $pctCls = $pct >= 80 ? 'ok' : ($pct >= 60 ? 'warn' : 'bad');
            $isConforme = $c['nb_them'] > 0 && $c['nb_haute'] === 0 && $c['nb_moyenne'] === 0;
            $hasEcart = $c['nb_haute'] > 0;
            $hasSansCert = $c['nb_sans_cert'] > 0;
            $expireSoon = $c['prochaine_exp'] && $c['prochaine_exp'] <= $soonDate;
            $isInscrit = $c['nb_form_a_venir'] > 0;

            // Classes filtres
            $filterClasses = [];
            if ($isConforme) $filterClasses[] = 'flt-conformes';
            if ($hasEcart) $filterClasses[] = 'flt-ecarts';
            if ($hasSansCert) $filterClasses[] = 'flt-sans-cert';
            if ($expireSoon) $filterClasses[] = 'flt-expire-soon';
            if ($isInscrit) $filterClasses[] = 'flt-inscrits';

            $searchData = strtolower($c['prenom'] . ' ' . $c['nom'] . ' ' . ($c['fonction_nom'] ?: '') . ' ' . ($c['email'] ?: ''));
          ?>
            <tr class="fcl-row <?= h(implode(' ', $filterClasses)) ?>"
                data-secteur="<?= h($sec) ?>"
                data-search="<?= h($searchData) ?>"
                data-uid="<?= h($c['id']) ?>"
                data-email="<?= h($c['email']) ?>"
                data-name="<?= h($c['prenom'] . ' ' . $c['nom']) ?>">
              <td>
                <input type="checkbox" class="fcl-row-chk" data-uid="<?= h($c['id']) ?>">
              </td>
              <td>
                <div class="fcl-collab">
                  <div class="fcl-avatar" style="background:<?= h($secColor) ?>">
                    <?= h(strtoupper(mb_substr($c['prenom'] ?? '', 0, 1) . mb_substr($c['nom'] ?? '', 0, 1))) ?>
                  </div>
                  <a href="?page=rh-collab-competences&id=<?= h($c['id']) ?>" data-page-link class="fcl-collab-link">
                    <?= h($c['nom'] . ' ' . $c['prenom']) ?>
                    <div class="fcl-collab-email"><?= h($c['email']) ?></div>
                  </a>
                </div>
              </td>
              <td><?= h($c['fonction_nom'] ?: '—') ?></td>
              <td>
                <?php if ($sec): ?>
                  <span class="fcl-sec-pill" style="background:<?= h($secColor) ?>22;color:<?= h($secColor) ?>"><?= h($secLbl) ?></span>
                <?php else: ?><span class="t-meta">—</span><?php endif ?>
              </td>
              <td class="num">
                <div class="fcl-conf">
                  <span class="fcl-conf-val fcl-<?= $pctCls ?>"><?= $pct ?>%</span>
                  <div class="fcl-mini-bar"><span class="fcl-bar-<?= $pctCls ?>" style="width:<?= $pct ?>%"></span></div>
                  <?php if ($hasEcart): ?>
                    <span class="fcl-pill-mini fcl-pill-bad"><?= $c['nb_haute'] ?> haute</span>
                  <?php endif ?>
                </div>
              </td>
              <td class="num t-num"><?= $c['nb_form_realisees'] ?>
                <?php if ($c['nb_form_a_venir'] > 0): ?>
                  <small style="color:#3a6a8a">+ <?= $c['nb_form_a_venir'] ?> à venir</small>
                <?php endif ?>
              </td>
              <td class="num">
                <?php if ($c['nb_sans_cert'] > 0): ?>
                  <span class="fcl-pill-mini fcl-pill-warn"><?= $c['nb_sans_cert'] ?></span>
                <?php else: ?>
                  <span class="t-meta">—</span>
                <?php endif ?>
              </td>
              <td class="num t-num">
                <?php if ($c['prochaine_exp']):
                  $cls = $expireSoon ? 'fcl-bad' : 'fcl-muted';
                ?>
                  <span class="<?= $cls ?>"><?= date('d.m.Y', strtotime($c['prochaine_exp'])) ?></span>
                <?php else: ?>
                  <span class="t-meta">—</span>
                <?php endif ?>
              </td>
              <td class="num t-num"><?= $fmtN($c['heures_an']) ?>h</td>
              <td>
                <a href="?page=rh-collab-competences&id=<?= h($c['id']) ?>" data-page-link class="fcl-btn-mini" title="Historique">
                  <i class="bi bi-clock-history"></i>
                </a>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      <div class="fcl-empty" id="fclEmpty" style="display:none">
        <i class="bi bi-search"></i>
        <strong>Aucun collaborateur ne correspond aux filtres</strong>
      </div>
    </div>
  </div>

</div>

<!-- Modal envoi rappel · pattern Bootstrap SpocSpace standard -->
<div class="modal fade" id="fclModalReminder" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <div class="text-muted small" style="letter-spacing:.08em;text-transform:uppercase;font-weight:600;font-size:10.5px;color:var(--cl-primary,#1f6359)!important">Rappel formation</div>
          <h5 class="modal-title mt-1">Envoyer un rappel</h5>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div id="fclReminderRecipients" class="alert alert-info py-2 px-3 mb-3" style="font-size:13px"></div>

        <div class="mb-3">
          <label class="form-label small text-muted text-uppercase fw-semibold" style="letter-spacing:.04em">Type de rappel</label>
          <select class="form-select" id="fclReminderType">
            <option value="certificat">Upload certificat de formation</option>
            <option value="renouvellement">Renouvellement compétence</option>
            <option value="inscription">Confirmation inscription</option>
            <option value="custom">Message personnalisé</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label small text-muted text-uppercase fw-semibold" style="letter-spacing:.04em">Sujet</label>
          <input type="text" class="form-control" id="fclReminderSubject" value="Rappel : merci d'uploader votre certificat de formation">
        </div>

        <div class="mb-2">
          <label class="form-label small text-muted text-uppercase fw-semibold" style="letter-spacing:.04em">Message</label>
          <textarea class="form-control" id="fclReminderBody" rows="6">Bonjour {prenom},

Tu as suivi récemment une formation FEGEMS dont nous n'avons pas encore reçu l'attestation. Merci de la téléverser depuis ton espace SpocSpace > Formations.

Cordialement,
La direction</textarea>
          <small class="text-muted">Variables disponibles : <code>{prenom}</code>, <code>{nom}</code></small>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="fclModalSend">
          <i class="bi bi-send"></i> Envoyer
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Liste collaborateurs · formation · scopé sous .fcl-page
═══════════════════════════════════════════════════════════════════ */

.fcl-page {
  --fcl-bg: #f5f7f5;
  --fcl-surface: #fff;
  --fcl-surface-2: #fafbfa;
  --fcl-ink: #0d2a26;
  --fcl-ink-2: #324e4a;
  --fcl-muted: #6b8783;
  --fcl-line: #e3ebe8;
  --fcl-line-2: #d4ddda;
  --fcl-teal-50: #ecf5f3;
  --fcl-teal-300: #7ab5ab;
  --fcl-teal-600: #1f6359;
  --fcl-teal-700: #164a42;
  --fcl-warn: #c97a2a;
  --fcl-warn-bg: #fbf0e1;
  --fcl-danger: #b8443a;
  --fcl-danger-bg: #f7e3e0;
  --fcl-ok: #3d8b6b;
  --fcl-ok-bg: #e3f0ea;
  --fcl-info: #3a6a8a;
  --fcl-info-bg: #e2ecf2;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 14px; color: var(--fcl-ink); line-height: 1.45;
  padding: 28px 32px 60px; max-width: 1600px; margin: 0 auto;
}
.fcl-page * { box-sizing: border-box; }

/* HEAD */
.fcl-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; margin-bottom: 24px; flex-wrap: wrap;
}
.fcl-h1 {
  font-family: 'Fraunces', serif; font-size: 32px; font-weight: 500;
  letter-spacing: -.025em; color: var(--fcl-ink); margin: 0;
}
.fcl-sub { color: var(--fcl-muted); font-size: 14px; margin-top: 4px; }
.fcl-sub strong { color: var(--fcl-ink-2); font-weight: 600; }
.fcl-actions { display: flex; gap: 10px; flex-wrap: wrap; }

.fcl-page .fcl-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px; font-family: inherit;
  font-size: 13px; font-weight: 500;
  border: 1px solid var(--fcl-line); background: var(--fcl-surface);
  color: var(--fcl-ink-2); cursor: pointer; transition: .15s; text-decoration: none;
}
.fcl-page .fcl-btn:hover:not(:disabled) { border-color: var(--fcl-teal-300); color: var(--fcl-teal-600); }
.fcl-page .fcl-btn:disabled { opacity: .5; cursor: not-allowed; }
.fcl-page .fcl-btn-primary {
  background: var(--fcl-teal-600); color: #fff; border-color: var(--fcl-teal-600);
}
.fcl-page .fcl-btn-primary:hover:not(:disabled) {
  background: var(--fcl-teal-700); border-color: var(--fcl-teal-700); color: #fff;
}

/* STATS CARDS (cliquables) */
.fcl-stats {
  display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px;
  margin-bottom: 22px;
}
.fcl-page .fcl-stat {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; border-radius: 12px;
  background: var(--fcl-surface); border: 2px solid var(--fcl-line);
  cursor: pointer; transition: .15s; text-align: left;
  font-family: inherit;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fcl-page .fcl-stat:hover { border-color: var(--fcl-teal-300); transform: translateY(-1px); }
.fcl-page .fcl-stat-active { border-color: var(--fcl-teal-600); background: linear-gradient(180deg, #fff, var(--fcl-teal-50)); }
.fcl-stat-icon {
  width: 36px; height: 36px; border-radius: 9px;
  display: grid; place-items: center; font-size: 16px; flex-shrink: 0;
}
.fcl-stat-num {
  font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600;
  letter-spacing: -.02em; line-height: 1; color: var(--fcl-ink);
}
.fcl-stat-lbl {
  font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase;
  color: var(--fcl-muted); font-weight: 600; margin-top: 3px;
}

/* FILTERS BAR */
.fcl-filters {
  background: var(--fcl-surface); border: 1px solid var(--fcl-line);
  border-radius: 10px; padding: 10px 14px;
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  margin-bottom: 14px;
}
.fcl-filter-lbl {
  font-size: 11px; color: var(--fcl-muted); font-weight: 600;
  letter-spacing: .04em; text-transform: uppercase; margin-right: 4px;
}
.fcl-page .fcl-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 11px; border-radius: 99px; font-size: 12.5px;
  background: var(--fcl-surface-2); color: var(--fcl-ink-2);
  border: 1px solid var(--fcl-line); cursor: pointer; transition: .15s;
  font-family: inherit; font-weight: 500;
}
.fcl-page .fcl-pill:hover { border-color: var(--fcl-teal-300); }
.fcl-page .fcl-pill.on { background: var(--fcl-teal-600); color: #fff; border-color: var(--fcl-teal-600); }
.fcl-pill-dot { width: 8px; height: 8px; border-radius: 50%; }

.fcl-filter-search {
  margin-left: auto; display: flex; align-items: center; gap: 7px;
  background: var(--fcl-surface-2); border: 1px solid var(--fcl-line);
  border-radius: 7px; padding: 5px 11px; width: 280px;
}
.fcl-filter-search i { color: var(--fcl-muted); }
.fcl-filter-search input {
  border: 0; background: transparent; outline: none;
  font-family: inherit; font-size: 12.5px; width: 100%;
}

/* CARD + TABLE */
.fcl-card {
  background: var(--fcl-surface); border: 1px solid var(--fcl-line);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fcl-card-head {
  padding: 14px 18px; border-bottom: 1px solid var(--fcl-line);
  display: flex; justify-content: space-between; align-items: center;
}
.fcl-card-head h3 {
  font-family: 'Fraunces', serif; font-size: 17px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fcl-ink); margin: 0;
}
.fcl-shown-count {
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  background: var(--fcl-teal-50); color: var(--fcl-teal-700);
  padding: 1px 7px; border-radius: 99px; font-weight: 600;
  margin-left: 6px;
}
.fcl-checkall {
  font-size: 12px; color: var(--fcl-muted);
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
}
.fcl-checkall input { cursor: pointer; }

.fcl-table-wrap { overflow-x: auto; }
.fcl-page table.fcl-table {
  width: 100%; border-collapse: separate; border-spacing: 0;
  min-width: 980px;
}
.fcl-table thead th {
  font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em;
  color: var(--fcl-muted); font-weight: 700; text-align: left;
  padding: 10px 14px; background: var(--fcl-surface-2);
  border-bottom: 1px solid var(--fcl-line);
  white-space: nowrap;
}
.fcl-table thead th.num { text-align: right; }
.fcl-table tbody td {
  padding: 11px 14px; border-bottom: 1px solid var(--fcl-line);
  vertical-align: middle; font-size: 13px;
}
.fcl-table tbody td.num { text-align: right; font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
.fcl-table tbody tr:last-child td { border-bottom: 0; }
.fcl-table tbody tr:hover { background: var(--fcl-surface-2); }
.fcl-table tbody tr.fcl-row-selected { background: var(--fcl-teal-50); }
.fcl-table tbody tr[hidden] { display: none; }

.fcl-collab { display: flex; align-items: center; gap: 10px; }
.fcl-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  display: grid; place-items: center; color: #fff;
  font-weight: 600; font-size: 11.5px; flex-shrink: 0;
}
.fcl-page .fcl-collab-link {
  color: var(--fcl-ink); font-weight: 500; text-decoration: none;
  line-height: 1.3;
}
.fcl-page .fcl-collab-link:hover { color: var(--fcl-teal-600); }
.fcl-collab-email { font-size: 11px; color: var(--fcl-muted); margin-top: 1px; }

.fcl-sec-pill {
  display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 4px;
  font-weight: 600;
}

.fcl-conf {
  display: flex; flex-direction: column; align-items: flex-end; gap: 3px;
  min-width: 100px;
}
.fcl-conf-val { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; }
.fcl-conf-val.fcl-ok   { color: var(--fcl-ok); }
.fcl-conf-val.fcl-warn { color: var(--fcl-warn); }
.fcl-conf-val.fcl-bad  { color: var(--fcl-danger); }
.fcl-mini-bar {
  height: 3px; width: 80px; background: var(--fcl-line);
  border-radius: 99px; overflow: hidden;
}
.fcl-mini-bar span { display: block; height: 100%; border-radius: 99px; }
.fcl-bar-ok   { background: linear-gradient(90deg, var(--fcl-ok), #5cad8b); }
.fcl-bar-warn { background: linear-gradient(90deg, var(--fcl-warn), #e0a85a); }
.fcl-bar-bad  { background: linear-gradient(90deg, var(--fcl-danger), #cd6b62); }

.fcl-pill-mini {
  display: inline-block; font-size: 9.5px; padding: 1px 6px; border-radius: 3px;
  font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
}
.fcl-pill-ok   { background: var(--fcl-ok-bg); color: var(--fcl-ok); }
.fcl-pill-warn { background: var(--fcl-warn-bg); color: var(--fcl-warn); }
.fcl-pill-bad  { background: var(--fcl-danger-bg); color: var(--fcl-danger); }

.fcl-bad   { color: var(--fcl-danger); font-weight: 600; }
.fcl-muted { color: var(--fcl-muted); }
.t-num { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
.t-meta { font-size: 11px; color: var(--fcl-muted); }

.fcl-page .fcl-btn-mini {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px;
  border: 1px solid var(--fcl-line); background: #fff; color: var(--fcl-muted);
  cursor: pointer; transition: .15s; text-decoration: none;
}
.fcl-page .fcl-btn-mini:hover {
  border-color: var(--fcl-teal-300); color: var(--fcl-teal-600); background: var(--fcl-teal-50);
}

.fcl-empty {
  padding: 60px 20px; text-align: center; color: var(--fcl-muted);
}
.fcl-empty i { font-size: 36px; opacity: .25; display: block; margin-bottom: 12px; }

/* MODAL · pattern Bootstrap SpocSpace, juste styles spécifiques pour les chips destinataires */
#fclReminderRecipients {
  display: flex; flex-wrap: wrap; gap: 4px 6px; align-items: center;
}
#fclReminderRecipients .lbl { font-weight: 600; margin-right: 4px; }
#fclReminderRecipients .chip {
  display: inline-block;
  background: rgba(255,255,255,.6); padding: 2px 7px; border-radius: 4px;
  font-size: 11.5px; color: inherit;
}

@media (max-width: 1280px) {
  .fcl-stats { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 720px) {
  .fcl-stats { grid-template-columns: repeat(2, 1fr); }
  .fcl-filter-search { width: 100%; margin-left: 0; margin-top: 8px; }
}

@media print {
  .fcl-actions, .fcl-stats, .fcl-filters, .fcl-card-actions, .fcl-btn-mini, .fcl-row-chk, thead th:first-child, td:first-child, td:last-child, th:last-child { display: none !important; }
  .fcl-page { padding: 12px; }
}
</style>

<script<?= nonce() ?>>
(function () {
  const tbl = document.getElementById('fclTable');
  const rows = Array.from(tbl.querySelectorAll('tbody tr.fcl-row'));
  const shownCount = document.getElementById('fclShownCount');
  const emptyEl = document.getElementById('fclEmpty');
  const searchInput = document.getElementById('fclSearch');
  const selectAll = document.getElementById('fclSelectAll');
  const sendBtn = document.getElementById('fclSendReminderBulk');
  const bulkCount = document.getElementById('fclBulkCount');

  let curStatFilter = 'all';
  let curSecteur = '';
  let curSearch = '';

  function applyFilters() {
    let visible = 0;
    rows.forEach(r => {
      const matchSec = !curSecteur || r.dataset.secteur === curSecteur;
      const matchSearch = !curSearch || r.dataset.search.includes(curSearch);
      const matchStat = curStatFilter === 'all' || r.classList.contains('flt-' + curStatFilter);
      const ok = matchSec && matchSearch && matchStat;
      r.hidden = !ok;
      if (ok) visible++;
    });
    shownCount.textContent = visible;
    emptyEl.style.display = visible === 0 ? 'block' : 'none';
    syncSelectAll();
    updateBulkButton();
  }

  // Stat cards click → filter
  document.querySelectorAll('.fcl-stat').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.fcl-stat').forEach(c => c.classList.toggle('fcl-stat-active', c === card));
      curStatFilter = card.dataset.filter;
      applyFilters();
    });
  });

  // Secteur pills
  document.querySelectorAll('.fcl-pill[data-secteur]').forEach(p => {
    p.addEventListener('click', () => {
      document.querySelectorAll('.fcl-pill[data-secteur]').forEach(x => x.classList.toggle('on', x === p));
      curSecteur = p.dataset.secteur;
      applyFilters();
    });
  });

  // Search
  function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
  const searchHandler = debounce(() => {
    curSearch = searchInput.value.trim().toLowerCase();
    applyFilters();
  }, 120);
  searchInput.addEventListener('input', searchHandler);

  // Hook to topbar global search if present
  const topSearch = document.querySelector('input[name="global_search"], #global-search-input, .topbar input[type="search"]');
  if (topSearch) {
    topSearch.addEventListener('input', () => {
      searchInput.value = topSearch.value;
      curSearch = topSearch.value.trim().toLowerCase();
      applyFilters();
    });
  }

  // Bulk select
  selectAll.addEventListener('change', () => {
    rows.forEach(r => {
      if (r.hidden) return;
      const chk = r.querySelector('.fcl-row-chk');
      chk.checked = selectAll.checked;
      r.classList.toggle('fcl-row-selected', chk.checked);
    });
    updateBulkButton();
  });

  function syncSelectAll() {
    const visibleRows = rows.filter(r => !r.hidden);
    const checkedVisible = visibleRows.filter(r => r.querySelector('.fcl-row-chk').checked);
    selectAll.checked = visibleRows.length > 0 && checkedVisible.length === visibleRows.length;
    selectAll.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleRows.length;
  }

  function updateBulkButton() {
    const checkedCount = rows.filter(r => !r.hidden && r.querySelector('.fcl-row-chk').checked).length;
    sendBtn.disabled = checkedCount === 0;
    bulkCount.textContent = checkedCount > 0 ? `(${checkedCount})` : '';
  }

  document.querySelectorAll('.fcl-row-chk').forEach(chk => {
    chk.addEventListener('change', e => {
      const tr = e.target.closest('tr');
      tr.classList.toggle('fcl-row-selected', e.target.checked);
      syncSelectAll();
      updateBulkButton();
    });
  });

  // Modal rappel · Bootstrap modal
  const modalEl = document.getElementById('fclModalReminder');
  const modal = new bootstrap.Modal(modalEl);
  const modalRecipients = document.getElementById('fclReminderRecipients');
  const modalSubject = document.getElementById('fclReminderSubject');
  const modalBody = document.getElementById('fclReminderBody');
  const modalType = document.getElementById('fclReminderType');

  const templates = {
    certificat: {
      sujet: "Rappel : merci d'uploader votre certificat de formation",
      body: "Bonjour {prenom},\n\nTu as suivi récemment une formation FEGEMS dont nous n'avons pas encore reçu l'attestation. Merci de la téléverser depuis ton espace SpocSpace > Formations.\n\nCordialement,\nLa direction",
    },
    renouvellement: {
      sujet: "Rappel : renouvellement formation FEGEMS",
      body: "Bonjour {prenom},\n\nUne de tes compétences arrive bientôt à échéance. Merci de t'inscrire à une session de renouvellement (HPCI, BLS-AED, actes délégués…) via ton espace SpocSpace.\n\nCordialement,\nLa direction",
    },
    inscription: {
      sujet: "Confirmation inscription formation",
      body: "Bonjour {prenom},\n\nTu es inscrit·e à une formation FEGEMS. Merci de bien noter la date dans ton planning et de confirmer ta présence.\n\nCordialement,\nLa direction",
    },
    custom: { sujet: "", body: "" },
  };

  modalType.addEventListener('change', () => {
    const t = templates[modalType.value] || templates.custom;
    modalSubject.value = t.sujet;
    modalBody.value = t.body;
  });

  function openReminderModal() {
    const checked = rows.filter(r => !r.hidden && r.querySelector('.fcl-row-chk').checked);
    if (!checked.length) return;
    modalRecipients.innerHTML = '<span class="lbl">Destinataires :</span>'
      + checked.slice(0, 12).map(r => `<span class="chip">${escapeHtml(r.dataset.name)}</span>`).join('')
      + (checked.length > 12 ? `<span class="chip">+ ${checked.length - 12}</span>` : '');
    modal.show();
  }

  sendBtn.addEventListener('click', openReminderModal);

  document.getElementById('fclModalSend').addEventListener('click', async () => {
    const checked = rows.filter(r => !r.hidden && r.querySelector('.fcl-row-chk').checked);
    if (!checked.length) return;

    const sujet = modalSubject.value.trim();
    const bodyTpl = modalBody.value.trim();
    if (!sujet || !bodyTpl) {
      if (typeof showToast === 'function') showToast('Sujet et message requis', 'danger');
      return;
    }

    const sendBtnEl = document.getElementById('fclModalSend');
    sendBtnEl.disabled = true;
    sendBtnEl.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Envoi…';

    let ok = 0, fail = 0;
    for (const row of checked) {
      const uid = row.dataset.uid;
      const name = row.dataset.name;
      const prenom = name.split(' ')[0] || '';
      const personalBody = bodyTpl.replace(/\{prenom\}/g, prenom).replace(/\{nom\}/g, name);

      try {
        const r = await adminApiPost('admin_send_message', {
          to: [uid],
          sujet,
          contenu: personalBody.replace(/\n/g, '<br>'),
        });
        if (r.success) ok++; else fail++;
      } catch (e) { fail++; }
    }

    sendBtnEl.disabled = false;
    sendBtnEl.innerHTML = '<i class="bi bi-send"></i> Envoyer';
    if (typeof showToast === 'function') {
      if (ok > 0) showToast(`${ok} rappel${ok > 1 ? 's' : ''} envoyé${ok > 1 ? 's' : ''}` + (fail > 0 ? ` · ${fail} erreur(s)` : ''), 'success');
      else if (fail > 0) showToast(`${fail} erreur(s) lors de l'envoi`, 'danger');
    }
    if (ok > 0) modal.hide();
  });

  // Export CSV
  document.getElementById('fclExportCsv').addEventListener('click', () => {
    const visible = rows.filter(r => !r.hidden);
    const headers = ['Nom', 'Email', 'Fonction', 'Secteur', 'Conformité %', 'Réalisées', 'À venir', 'Sans certificat', 'Prochaine échéance', 'Heures'];
    const lines = [headers.join(';')];
    visible.forEach(r => {
      const cells = r.querySelectorAll('td');
      const name = r.dataset.name;
      const email = r.dataset.email;
      const fonction = cells[2]?.textContent.trim() || '';
      const secteur = cells[3]?.textContent.trim() || '';
      const conf = cells[4]?.querySelector('.fcl-conf-val')?.textContent.trim() || '';
      const realisees = cells[5]?.firstChild?.textContent.trim() || '';
      const aVenir = cells[5]?.querySelector('small')?.textContent.replace(/[+]\s*|\s*à venir/g, '').trim() || '';
      const sansCert = cells[6]?.textContent.trim() || '';
      const echeance = cells[7]?.textContent.trim() || '';
      const heures = cells[8]?.textContent.trim() || '';
      lines.push([name, email, fonction, secteur, conf, realisees, aVenir, sansCert, echeance, heures].map(v => `"${(v||'').replace(/"/g,'""')}"`).join(';'));
    });
    const csv = lines.join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `collaborateurs-formation-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  });

  function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
})();
</script>
