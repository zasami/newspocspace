<?php
// ─── Dashboard · version Care (Spocspace DS v1.0) ────────────────────────────
$totalUsers    = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
$pendingAbs    = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$pendingDesirs = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
$currentUserId = $_SESSION['ss_user']['id'] ?? '';
$unreadMsgs    = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$currentUserId]);

// Conformité globale (compétences à jour / total)
$totalComp  = (int) Db::getOne("SELECT COUNT(*) FROM competences_user");
$ajourComp  = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite = 'a_jour'");
$conformite = $totalComp > 0 ? round(($ajourComp / $totalComp) * 100) : 0;

// Gaps prioritaires (priorité haute)
$gapsHaute = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite = 'haute'");

// Budget formation
$budgetTotal = (float) (Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.annuel_total_chf'") ?: 0);
$coutReel = (float) Db::getOne(
    "SELECT COALESCE(SUM(f.cout_formation), 0) FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide') AND YEAR(f.date_debut) = YEAR(CURDATE())"
);
$pctConsomme = $budgetTotal > 0 ? round(($coutReel / $budgetTotal) * 100) : 0;

$recentAbsences = Db::fetchAll(
    "SELECT a.*, u.prenom, u.nom, u.photo, fn.secteur_fegems
     FROM absences a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     ORDER BY a.created_at DESC
     LIMIT 8"
);

$absStatusBadge = [
    'valide'     => ['Validé',   'ok'],
    'refuse'     => ['Refusé',   'danger'],
    'en_attente' => ['En attente','warn'],
];
$secteurClass = [
    'soins'         => 's-soins',
    'socio_culturel'=> 's-anim',
    'hotellerie'    => 's-hot',
    'maintenance'   => 's-tech',
    'administration'=> 's-admin',
    'management'    => 's-mgmt',
];
$secteurLabel = [
    'soins'         => 'Soins',
    'socio_culturel'=> 'Socio-culturel',
    'hotellerie'    => 'Hôtellerie',
    'maintenance'   => 'Maintenance',
    'administration'=> 'Administration',
    'management'    => 'Management',
];
?>
<div class="care-page">

  <!-- Hero -->
  <header class="ds-hero">
    <div class="t-eyebrow" style="color:#a8e6c9">Tableau de bord</div>
    <h1>Bonjour <?= h($_SESSION['ss_user']['prenom'] ?? '') ?></h1>
    <div class="ds-hero-sub">Vue d'ensemble de l'activité <?= date('Y') ?> · établissement</div>

    <div class="ds-hero-stats">
      <div class="ds-hero-stat">
        <strong><?= $totalUsers ?></strong>
        <span>Collaborateurs actifs</span>
      </div>
      <div class="ds-hero-stat">
        <strong class="t-num"><?= $conformite ?>%</strong>
        <span>Conformité globale</span>
      </div>
      <div class="ds-hero-stat">
        <strong><?= $gapsHaute ?></strong>
        <span>Gaps prioritaires</span>
      </div>
      <?php if ($budgetTotal > 0): ?>
      <div class="ds-hero-stat">
        <strong class="t-num"><?= $pctConsomme ?>%</strong>
        <span>Budget consommé</span>
      </div>
      <?php endif ?>
    </div>
  </header>

  <!-- KPI cards -->
  <section class="care-section" style="margin-top:0">
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="kpi feature">
          <div class="kpi-lbl">Conformité globale</div>
          <div class="kpi-val t-num"><?= $conformite ?><small>%</small></div>
          <div class="kpi-delta"><?= $ajourComp ?>/<?= $totalComp ?> compétences à jour</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="kpi">
          <div class="kpi-lbl">Collaborateurs</div>
          <div class="kpi-val t-num"><?= $totalUsers ?></div>
          <div class="kpi-delta">actifs aujourd'hui</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="kpi">
          <div class="kpi-lbl">Gaps prioritaires</div>
          <div class="kpi-val t-num" style="color:var(--danger)"><?= $gapsHaute ?></div>
          <div class="kpi-delta bad">priorité haute</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="kpi">
          <div class="kpi-lbl">À traiter</div>
          <div class="kpi-val t-num"><?= $pendingAbs + $pendingDesirs ?></div>
          <div class="kpi-delta"><?= $pendingAbs ?> absences · <?= $pendingDesirs ?> désirs</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Demandes en attente -->
  <section class="care-section">
    <div class="care-section-title">
      <h2 class="serif">Dernières demandes d'absence</h2>
      <a href="<?= admin_url('absences') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> Voir tout
      </a>
    </div>

    <?php if (empty($recentAbsences)): ?>
      <div class="ds-card ds-card-padded">
        <div class="ds-empty">
          <i class="bi bi-inbox"></i>
          <div class="ds-empty-title">Aucune demande</div>
          <div class="ds-empty-msg">Les nouvelles demandes apparaîtront ici.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="ds-tbl-wrap">
        <table class="ds-tbl">
          <thead>
            <tr>
              <th>Collaborateur</th>
              <th>Secteur</th>
              <th>Type</th>
              <th>Période</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="dashRecentAbsences">
            <?php foreach ($recentAbsences as $a):
              $initials = mb_strtoupper(mb_substr($a['prenom'] ?? '', 0, 1) . mb_substr($a['nom'] ?? '', 0, 1));
              [$sLbl, $sCls] = $absStatusBadge[$a['statut']] ?? [$a['statut'], 'neutral'];
              $secCls = $secteurClass[$a['secteur_fegems']] ?? '';
              $secLbl = $secteurLabel[$a['secteur_fegems']] ?? '—';
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <?php if (!empty($a['photo'])): ?>
                      <img src="<?= h($a['photo']) ?>" alt="" class="ds-avatar" style="object-fit:cover">
                    <?php else: ?>
                      <div class="ds-avatar" style="background:var(--teal-600)"><?= h($initials) ?></div>
                    <?php endif ?>
                    <div>
                      <div style="font-weight:500;color:var(--ink)"><?= h($a['prenom'] . ' ' . $a['nom']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($secCls): ?>
                    <span class="sector-tag <?= $secCls ?>"><span class="bullet"></span><?= h($secLbl) ?></span>
                  <?php else: ?>
                    <span class="t-meta">—</span>
                  <?php endif ?>
                </td>
                <td><?= h(ucfirst($a['type'])) ?></td>
                <td class="t-num" style="font-size:12.5px;color:var(--ink-2)">
                  <?= h($a['date_debut']) ?> → <?= h($a['date_fin']) ?>
                </td>
                <td>
                  <?php if ($sCls === 'ok'): ?>
                    <span class="badge text-bg-success"><?= h($sLbl) ?></span>
                  <?php elseif ($sCls === 'danger'): ?>
                    <span class="badge text-bg-danger"><?= h($sLbl) ?></span>
                  <?php elseif ($sCls === 'warn'): ?>
                    <span class="badge text-bg-warning"><?= h($sLbl) ?></span>
                  <?php endif ?>
                </td>
                <td style="text-align:right">
                  <?php if ($a['statut'] === 'en_attente'): ?>
                    <button class="btn btn-outline-primary btn-sm" data-quick-valid="<?= h($a['id']) ?>" title="Valider">
                      <i class="bi bi-check-lg"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" data-quick-refuse="<?= h($a['id']) ?>" title="Refuser">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  <?php else: ?>
                    <span class="t-meta">—</span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>

  <!-- Raccourcis modules -->
  <section class="care-section">
    <div class="care-section-title">
      <h2 class="serif">Modules clés</h2>
    </div>

    <div class="row g-3">
      <div class="col-md-6 col-lg-3">
        <a href="<?= admin_url('rh-formations-cartographie') ?>" class="ds-card ds-card-padded ds-card-hover" style="display:block;text-decoration:none;color:inherit">
          <div class="t-eyebrow"><i class="bi bi-bullseye"></i> Compétences</div>
          <div class="h-2 mt-2">Cartographie d'équipe</div>
          <div class="t-small mt-1" style="color:var(--muted)">Vue radar de l'équipe par secteur FEGEMS</div>
        </a>
      </div>
      <div class="col-md-6 col-lg-3">
        <a href="<?= admin_url('rh-formations') ?>" class="ds-card ds-card-padded ds-card-hover" style="display:block;text-decoration:none;color:inherit">
          <div class="t-eyebrow"><i class="bi bi-mortarboard"></i> Formations</div>
          <div class="h-2 mt-2">Catalogue & sessions</div>
          <div class="t-small mt-1" style="color:var(--muted)">Sessions FEGEMS et inscriptions</div>
        </a>
      </div>
      <div class="col-md-6 col-lg-3">
        <a href="<?= admin_url('rh-entretiens') ?>" class="ds-card ds-card-padded ds-card-hover" style="display:block;text-decoration:none;color:inherit">
          <div class="t-eyebrow"><i class="bi bi-chat-square-text"></i> Entretiens</div>
          <div class="h-2 mt-2">Entretiens annuels</div>
          <div class="t-small mt-1" style="color:var(--muted)">Planification et suivi des bilans</div>
        </a>
      </div>
      <div class="col-md-6 col-lg-3">
        <a href="<?= admin_url('planning') ?>" class="ds-card ds-card-padded ds-card-hover" style="display:block;text-decoration:none;color:inherit">
          <div class="t-eyebrow"><i class="bi bi-calendar3"></i> Planning</div>
          <div class="h-2 mt-2">Gestion planning</div>
          <div class="t-small mt-1" style="color:var(--muted)">Couverture, désirs, absences</div>
        </a>
      </div>
    </div>
  </section>

</div>

<script<?= nonce() ?>>
const dashTbody = document.getElementById('dashRecentAbsences');
if (dashTbody) {
  dashTbody.addEventListener('click', async (e) => {
    const vBtn = e.target.closest('[data-quick-valid]');
    const rBtn = e.target.closest('[data-quick-refuse]');
    if (!vBtn && !rBtn) return;
    const btn = vBtn || rBtn;
    const id = btn.dataset[vBtn ? 'quickValid' : 'quickRefuse'];
    const statut = vBtn ? 'valide' : 'refuse';
    btn.disabled = true;
    const res = await adminApiPost('admin_validate_absence', { id, statut });
    if (res.success) { showToast(res.message, 'success'); location.reload(); }
    else { showToast(res.message || 'Erreur', 'error'); btn.disabled = false; }
  });
}
</script>
