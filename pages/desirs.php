<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['ss_user']['id'];
$desirsInitMois = date('Y-m');
$desirsInitData = Db::fetchAll(
    "SELECT d.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
            ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur
     FROM desirs d
     LEFT JOIN users u2 ON u2.id = d.valide_par
     LEFT JOIN horaires_types ht ON ht.id = d.horaire_type_id
     WHERE d.user_id = ? AND d.mois_cible = ?
     ORDER BY d.date_souhaitee DESC",
    [$uid, $desirsInitMois]
);
$desirsHoraires = Db::fetchAll(
    "SELECT id, code, nom, heure_debut, heure_fin, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code"
);
$desirsMaxMois = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: 4);
$_desirsPermanentsRaw = Db::fetchAll(
    "SELECT dp.*, ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
            dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
            dp2.horaire_type_id AS ancien_horaire_type_id, dp2.detail AS ancien_detail,
            ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
     FROM desirs_permanents dp
     LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
     LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
     LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
     WHERE dp.user_id = ?
     ORDER BY dp.jour_semaine",
    [$uid]
);
$_desirsPendingIds = array_column(
    Db::fetchAll("SELECT replaces_id FROM desirs_permanents WHERE user_id = ? AND replaces_id IS NOT NULL AND statut = 'en_attente'", [$uid]),
    'replaces_id'
);
$desirsPermanents = array_map(function($p) use ($_desirsPendingIds) {
    $p['has_pending_modification'] = in_array($p['id'], $_desirsPendingIds) ? 1 : 0;
    return $p;
}, $_desirsPermanentsRaw);
?>


<div class="page-header">
  <h1 style="margin:0"><i class="bi bi-star"></i> Mes Désirs</h1>
  <p>Soumettez jusqu'à 4 désirs par mois</p>
</div>

<!-- Stats cards -->
<div class="desir-stats-row">
  <div class="desir-stat-card">
    <div class="desir-stat-icon" style="background:#e6efe9;color:#2d4a43"><i class="bi bi-calendar-check"></i></div>
    <div class="desir-stat-body">
      <div class="desir-stat-label">Désirs restants</div>
      <div class="desir-stat-value" id="statRestants">—</div>
      <div class="desir-stat-sub" id="statRestantsSub">ce mois</div>
    </div>
  </div>
  <div class="desir-stat-card">
    <div class="desir-stat-icon" style="background:#f4ecdd;color:#6B5B3E"><i class="bi bi-hourglass-split"></i></div>
    <div class="desir-stat-body">
      <div class="desir-stat-label">En attente</div>
      <div class="desir-stat-value" id="statEnAttente">—</div>
      <div class="desir-stat-sub">de validation</div>
    </div>
  </div>
  <div class="desir-stat-card">
    <div class="desir-stat-icon" style="background:#e8edf5;color:#3B4F6B"><i class="bi bi-check-circle"></i></div>
    <div class="desir-stat-body">
      <div class="desir-stat-label">Validés</div>
      <div class="desir-stat-value" id="statValides">—</div>
      <div class="desir-stat-sub">ce mois</div>
    </div>
  </div>
  <div class="desir-stat-card">
    <div class="desir-stat-icon" style="background:#f0ebf5;color:#5B4B6B"><i class="bi bi-pin-angle-fill"></i></div>
    <div class="desir-stat-body">
      <div class="desir-stat-label">Permanents</div>
      <div class="desir-stat-value" id="statPermanents">—</div>
      <div class="desir-stat-sub" id="statPermanentsSub">actifs</div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 flex-wrap">
  <!-- Formulaire -->
  <div class="card" style="flex:0 0 380px;">
    <div class="card-header">
      <h3>Nouveau désir</h3>
    </div>
    <div class="card-body">
      <!-- Mode toggle -->
      <div class="form-group">
        <div class="mode-toggle">
          <button type="button" class="mode-toggle-btn active" data-mode="ponctuel">
            <i class="bi bi-calendar-event"></i> Ce mois
          </button>
          <button type="button" class="mode-toggle-btn" data-mode="permanent">
            <i class="bi bi-pin-angle"></i> Permanent
          </button>
        </div>
      </div>

      <!-- PONCTUEL: Calendar picker -->
      <div id="ponctuelFields">
        <div class="form-group">
          <label class="form-label d-flex justify-content-between align-items-center" style="gap:8px">
            <button type="button" id="calPrevBtn" class="desir-cal-nav" title="Mois précédent"><i class="bi bi-chevron-left"></i></button>
            <span id="calMonthLabel" style="flex:1;text-align:center"></span>
            <button type="button" id="calNextBtn" class="desir-cal-nav" title="Mois suivant"><i class="bi bi-chevron-right"></i></button>
            <span class="desir-count-info" id="calCountInfo"></span>
          </label>
          <div class="desir-calendar" id="desirCalendar"></div>
        </div>
        <div class="alert alert-info" id="desirReadOnlyBanner" style="display:none;margin:0.75rem 0 0;padding:0.6rem 0.8rem;font-size:0.8rem;border-radius:8px;background:#fff8e1;border:1px solid #f4d03f;color:#7d5d0e">
          <i class="bi bi-eye"></i> <strong>Consultation historique</strong> — les désirs ne peuvent être créés que pour le mois courant + 1
        </div>
        <div class="alert alert-info" style="margin:0.75rem 0 0;padding:0.6rem 0.8rem;font-size:0.8rem;border-radius:8px;background:rgba(26,26,26,0.04);border:1px solid #d1cfc5;color:#1a1a1a">
          <i class="bi bi-info-circle"></i> <strong>Badge solde</strong> en haut affiche les jours restants pour ce mois
        </div>
      </div>

      <!-- PERMANENT: Jour semaine -->
      <div id="permanentFields" style="display:none">
        <div class="form-group">
          <label class="form-label">Jour de la semaine</label>
          <select class="form-control" id="desirJourSemaine">
            <option value="1">Lundi</option>
            <option value="2">Mardi</option>
            <option value="3">Mercredi</option>
            <option value="4">Jeudi</option>
            <option value="5">Vendredi</option>
            <option value="6">Samedi</option>
            <option value="0">Dimanche</option>
          </select>
        </div>
        <div class="alert alert-warning" style="font-size:0.8rem;">
          <i class="bi bi-pin-angle-fill"></i> Sera appliqué <strong>chaque mois</strong> automatiquement une fois validé par votre responsable.
        </div>
      </div>

      <!-- Type -->
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-control" id="desirType">
          <option value="jour_off">Jour off</option>
          <option value="horaire_special">Horaire spécial</option>
        </select>
      </div>

      <!-- Horaire visual picker -->
      <div class="form-group" id="desirHoraireGroup" style="display:none">
        <label class="form-label">Horaire souhaité</label>
        <div id="horairesList" class="horaires-grid"></div>
        <input type="hidden" id="desirHoraireId">
      </div>

      <!-- Detail -->
      <div class="form-group" id="desirDetailGroup" style="display:none">
        <label class="form-label">Commentaire (optionnel)</label>
        <textarea class="form-control" id="desirDetail" placeholder="Ex: rendez-vous médical..." rows="2"></textarea>
      </div>

      <button type="button" class="btn btn-dark" id="desirSubmitBtn" disabled style="border-radius:10px;padding:10px 20px">
        <i class="bi bi-send"></i> Soumettre
      </button>
    </div>
  </div>

  <!-- Right panel: liste du mois + permanents -->
  <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:0;">
    <div class="card">
    <div class="card-header">
      <h3 id="desirsListTitle">Désirs du mois</h3>
    </div>
    <!-- Détail du désir sélectionné -->
    <div id="desirDetailPanel" style="display:none;padding:.85rem 1.1rem;font-size:.9rem;color:#2c2c2c;background:#faf7f0;border-bottom:1px solid var(--ss-border-light,#e8e4da);border-left:3px solid var(--ss-teal,#2a9d8f);">
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="desir-col-when">Date</th>
              <th class="desir-col-type">Type</th>
              <th class="desir-col-horaire">Horaire</th>
              <th class="desir-col-detail">Détail</th>
              <th class="desir-col-statut">Statut</th>
              <th class="desir-col-actions">Actions</th>
            </tr>
          </thead>
          <tbody id="desirsTableBody">
            <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-header" style="border-top:1px solid var(--border-color,#e1e5eb)">
      <h3><i class="bi bi-pin-angle"></i> Mes désirs permanents</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="desir-col-when">Jour</th>
              <th class="desir-col-type">Type</th>
              <th class="desir-col-horaire">Horaire</th>
              <th class="desir-col-detail">Détail</th>
              <th class="desir-col-statut">Statut</th>
              <th class="desir-col-actions">Actions</th>
            </tr>
          </thead>
          <tbody id="permanentsTableBody">
            <tr><td colspan="6" class="text-center text-muted" style="padding:1.5rem">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    </div><!-- /.card -->
  </div><!-- /flex wrapper -->
</div>

<!-- ═══ Modal confirmation suppression ═══ -->
<div class="desir-confirm-overlay" id="desirConfirmModal" style="display:none">
  <div class="desir-confirm-box">
    <div class="desir-confirm-header">
      <div class="desir-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <h3 id="desirConfirmTitle">Supprimer ce désir ?</h3>
    </div>
    <p id="desirConfirmMessage" class="desir-confirm-msg">Cette action est irréversible.</p>
    <div class="desir-confirm-actions">
      <button type="button" class="btn-desir-cancel" id="desirConfirmCancel">Annuler</button>
      <button type="button" class="btn-desir-confirm" id="desirConfirmOk"><i class="bi bi-trash"></i> Supprimer</button>
    </div>
  </div>
</div>

<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'desirs' => $desirsInitData,
    'permanents' => $desirsPermanents,
    'horaires' => $desirsHoraires,
    'max_desirs' => $desirsMaxMois,
    'mois' => $desirsInitMois,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
