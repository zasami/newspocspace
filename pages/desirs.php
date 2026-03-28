<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['zt_user']['id'];
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
<style>
.desir-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 0.75rem; }
.desir-cal-header { text-align: center; font-size: 0.7rem; font-weight: 700; color: var(--text-muted, #888); padding: 4px 0; text-transform: uppercase; }
.desir-cal-day {
  text-align: center; padding: 8px 2px; border-radius: 8px; cursor: pointer;
  font-size: 0.85rem; font-weight: 500; border: 2px solid transparent;
  transition: all 0.15s; user-select: none; background: var(--card-bg, #fff);
}
.desir-cal-day:hover { background: rgba(26,26,26,0.08); border-color:#cfc5b6; cursor: pointer; }
.desir-cal-day.selected { background: rgba(26,26,26,0.15); border-color: #1a1a1a; color: #1a1a1a; font-weight: 700; }
.desir-cal-day.disabled { opacity: 0.3; pointer-events: none; }
.desir-cal-day.has-desir { background: rgba(26,26,26,0.1); border-color: #1a1a1a; color: #1a1a1a; font-weight: 700; position: relative; }
.desir-cal-day.has-desir::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; background: #1a1a1a; border-radius: 50%; }
.desir-cal-day.has-desir.selected { background: rgba(26,26,26,0.15); border-color: #1a1a1a; color: #1a1a1a; }
.desir-cal-day.weekend { color: var(--text-muted, #999); }
.desir-cal-day.empty { visibility: hidden; }
.desir-count-info { font-size: 0.8rem; }
.mode-toggle { display: flex; gap: 0; border-radius: 10px; overflow: hidden; border: 2px solid #d1cfc5; }
.mode-toggle-btn {
  flex: 1; text-align: center; padding: 8px 14px; cursor: pointer; font-size: 0.82rem;
  font-weight: 600; transition: all 0.25s; border: none; background: transparent; color: #6c757d;
}
.mode-toggle-btn:hover:not(.active) { background: #f5f3ee; color: #1a1a1a; }
.mode-toggle-btn.active { background: #1a1a1a; color: #fff; }
.mode-toggle-btn:first-child { border-right: 1px solid #d1cfc5; }
.horaire-option { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: background 0.1s; }
.horaire-option:hover { background: rgba(0,0,0,0.04); }
.horaire-option.selected { background: rgba(26,26,26,0.08); outline: 2px solid #1a1a1a; }
.horaire-badge { min-width: 36px; text-align: center; padding: 2px 6px; border-radius: 4px; color: #fff; font-weight: 700; font-size: 0.82rem; }
.horaire-label { font-size: 0.8rem; }
.horaire-time { font-size: 0.72rem; color: var(--text-muted, #888); }
.desir-table-row { cursor: pointer; transition: background 0.15s; }
.desir-table-row:hover { background: rgba(26,26,26,0.03); }
/* Permanent table rows hover */
#permanentsTableBody tr { transition: background 0.15s; }
#permanentsTableBody tr:hover { background: rgba(26,26,26,0.03); }
/* Detail panel pulse animation */
@keyframes desirDetailPulse {
  0% { background: #ede8df; }
  100% { background: #f7f5f2; }
}
#desirDetailPanel.desir-detail-pulse {
  animation: desirDetailPulse 0.6s ease-out;
}
</style>

<div class="page-header">
  <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
    <h1 style="margin:0"><i class="bi bi-star"></i> Mes Désirs</h1>
    <span id="desirSoldeBadge" style="background:#f0e9dd;color:#1a1a1a;padding:0.25rem 0.6rem;border:1px solid #d1cfc5;border-radius:999px;font-size:0.86rem;font-weight:600;">0 restants</span>
  </div>
  <p>Soumettez jusqu'à 4 désirs par mois</p>
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
          <label class="form-label d-flex justify-content-between align-items-center">
            <span id="calMonthLabel"></span>
            <span class="desir-count-info" id="calCountInfo"></span>
          </label>
          <div class="desir-calendar" id="desirCalendar"></div>
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
        <div id="horairesList" style="max-height:200px;overflow-y:auto;border:1px solid var(--border-color,#dee2e6);border-radius:8px;padding:4px"></div>
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
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header">
      <h3 id="desirsListTitle">Désirs du mois</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Date</th><th>Type</th><th>Horaire</th><th>Détail</th><th>Statut</th><th></th></tr>
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
            <tr><th>Jour</th><th>Type</th><th>Horaire</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody id="permanentsTableBody">
            <tr><td colspan="5" class="text-center text-muted" style="padding:1.5rem">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer" style="background:#f7f5f2;border-top:1px solid #e8e5e0;">
      <div id="desirDetailPanel" style="display:none; font-size:0.9rem; color:#2c2c2c;"></div>
    </div>
  </div>
</div>
<script type="application/json" id="__zt_ssr__"><?= json_encode([
    'desirs' => $desirsInitData,
    'permanents' => $desirsPermanents,
    'horaires' => $desirsHoraires,
    'max_desirs' => $desirsMaxMois,
    'mois' => $desirsInitMois,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
