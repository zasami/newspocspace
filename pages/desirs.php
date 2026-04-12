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
<style>
/* Halo pulsé sur les jours sélectionnés (non encore validés) */
.desir-cal-day.selected {
  position: relative;
  --halo-color: 26, 26, 26;
  animation: desirDayPulse 1.8s ease-in-out infinite;
  z-index: 1;
}
@keyframes desirDayPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(var(--halo-color), .5); }
  50%      { box-shadow: 0 0 0 8px rgba(var(--halo-color), 0); }
}

.desir-cal-nav {
  background: transparent;
  border: 1px solid #d1cfc5;
  color: #1a1a1a;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background .15s, border-color .15s;
  font-size: .8rem;
  padding: 0;
}
.desir-cal-nav:hover:not(:disabled) { background: rgba(26,26,26,0.08); border-color: #1a1a1a; }
.desir-cal-nav:disabled { opacity: .3; cursor: not-allowed; }
.desir-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 0.75rem; }
.desir-calendar.desir-cal-readonly .desir-cal-day:not(.empty) { cursor: default; }
.desir-calendar.desir-cal-readonly .desir-cal-day:not(.has-desir):hover { background: transparent; border-color: transparent; }
.desir-cal-header { text-align: center; font-size: 0.7rem; font-weight: 700; color: var(--text-muted, #888); padding: 4px 0; text-transform: uppercase; }
.desir-cal-day {
  text-align: center; padding: 8px 2px; border-radius: 8px; cursor: pointer;
  font-size: 0.85rem; font-weight: 500; border: 2px solid transparent;
  transition: all 0.15s; user-select: none; background: var(--card-bg, #fff);
}
.desir-cal-day:hover { background: rgba(26,26,26,0.08); border-color:#cfc5b6; cursor: pointer; }
.desir-cal-day.selected { background: rgba(26,26,26,0.08); border-color: #cfc5b6; color: #1a1a1a; font-weight: 700; position: relative; }
.desir-cal-day-chip {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 20px;
  height: 16px;
  padding: 0 5px;
  border-radius: 10px;
  background: #1a1a1a;
  color: #fff;
  font-size: .6rem;
  font-weight: 700;
  line-height: 16px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0,0,0,.25);
  pointer-events: none;
  text-transform: uppercase;
  letter-spacing: .3px;
}
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
/* Ancien sélecteur (conservé pour compat) */
.horaire-option { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: background 0.1s; }
.horaire-option:hover { background: rgba(0,0,0,0.04); }
.horaire-option.selected { background: rgba(26,26,26,0.08); outline: 2px solid #1a1a1a; }

/* Nouveau sélecteur compact en grille */
.horaires-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.horaire-chip {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  border: 2px solid var(--border-color, #dee2e6);
  background: #fff;
  cursor: pointer;
  font-family: inherit;
  transition: transform .12s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
  text-align: left;
  min-height: 48px;
}
.horaire-chip:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  border-color: #e0d5c2;
  background: #faf7f0;
}
.horaire-chip.selected {
  border-color: #1a1a1a;
  background: #faf7f0;
  box-shadow: 0 0 0 2px rgba(26,26,26,.08);
}
.horaire-chip.selected::before {
  content: '\F633';
  font-family: 'bootstrap-icons';
  position: absolute;
  top: -8px;
  right: -8px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #1a1a1a;
  color: #fff;
  font-size: .7rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,.2);
  opacity: 1;
  z-index: 2;
}
.horaire-chip-code {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border-radius: 8px;
  color: #fff;
  font-weight: 700;
  font-size: .8rem;
  letter-spacing: .5px;
}
.horaire-chip-time {
  font-size: .65rem;
  line-height: 1.15;
  color: var(--text-muted, #888);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}
/* Tooltip custom via data-tooltip */
.horaire-chip::after {
  content: attr(data-tooltip);
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%) scale(.9);
  background: #1a1a1a;
  color: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: .72rem;
  font-weight: 500;
  white-space: nowrap;
  letter-spacing: normal;
  opacity: 0;
  pointer-events: none;
  transition: opacity .15s ease, transform .15s ease;
  z-index: 100;
  box-shadow: 0 4px 14px rgba(0,0,0,.2);
}
.horaire-chip::before {
  content: '';
  position: absolute;
  bottom: calc(100% + 2px);
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1a1a1a;
  opacity: 0;
  pointer-events: none;
  transition: opacity .15s ease;
  z-index: 100;
}
.horaire-chip:hover::after,
.horaire-chip:hover::before {
  opacity: 1;
}
.horaire-chip:hover::after {
  transform: translateX(-50%) scale(1);
}
.horaire-badge { min-width: 36px; text-align: center; padding: 2px 6px; border-radius: 4px; color: #fff; font-weight: 700; font-size: 0.82rem; }
.horaire-label { font-size: 0.8rem; }
.horaire-time { font-size: 0.72rem; color: var(--text-muted, #888); }
.desir-table-row { cursor: pointer; transition: background 0.15s; }
.desir-table-row:hover,
.desir-table-row:hover td,
#desirsTableBody tr:hover,
#desirsTableBody tr:hover td {
  background: #faf7f0 !important;
}

/* Colonnes largeur fixe pour aligner les deux tableaux (désirs + permanents) */
.desir-col-when { width: 12%; }
.desir-col-type { width: 14%; }
.desir-col-horaire { width: 14%; }
.desir-col-detail { width: 30%; }
.desir-col-statut { width: 18%; }
.desir-col-actions { width: 110px; text-align: right; }

/* Boutons actions (Modifier / Supprimer) — style identique admin */
.btn-desir-edit {
  background: #f7f5f2;
  border: 1px solid #e8e4da;
  color: #8a8578;
  border-radius: 8px;
  padding: 4px 8px;
  transition: all .15s ease;
  font-size: .85rem;
  line-height: 1;
}
.btn-desir-edit:hover {
  background: #bcd2cb;
  color: #2d4a43;
  border-color: #bcd2cb;
}
.btn-desir-delete {
  background: #f7f5f2;
  border: 1px solid #e8e4da;
  color: #8a8578;
  border-radius: 8px;
  padding: 4px 8px;
  transition: all .15s ease;
  font-size: .85rem;
  line-height: 1;
}
.btn-desir-delete:hover {
  background: #E2B8AE;
  color: #7B3B2C;
  border-color: #E2B8AE;
}

/* Modal confirmation suppression */
.desir-confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  backdrop-filter: blur(3px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: desirConfirmFadeIn .2s ease-out;
}
@keyframes desirConfirmFadeIn { from { opacity: 0; } to { opacity: 1; } }
.desir-confirm-box {
  background: #fff;
  border-radius: 16px;
  padding: 28px 30px 22px;
  max-width: 440px;
  width: 92%;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
  animation: desirConfirmSlide .25s cubic-bezier(.22,1,.36,1);
}
@keyframes desirConfirmSlide {
  from { transform: translateY(10px) scale(.97); opacity: 0; }
  to { transform: none; opacity: 1; }
}
.desir-confirm-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 12px;
}
.desir-confirm-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #f8dfda;
  color: #7B3B2C;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  flex-shrink: 0;
}
.desir-confirm-box h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a1a1a;
}
.desir-confirm-msg {
  font-size: .88rem;
  color: #6b7280;
  margin: 0 0 22px;
  line-height: 1.5;
}
.desir-confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn-desir-cancel {
  background: none;
  border: 1px solid #e5e7eb;
  color: #6b7280;
  padding: 8px 18px;
  border-radius: 10px;
  cursor: pointer;
  font-size: .88rem;
  font-family: inherit;
  transition: background .15s ease;
}
.btn-desir-cancel:hover { background: #f9fafb; }
.btn-desir-confirm {
  background: #E2B8AE;
  border: 1px solid #E2B8AE;
  color: #7B3B2C;
  padding: 8px 18px;
  border-radius: 10px;
  cursor: pointer;
  font-size: .88rem;
  font-weight: 600;
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s ease, transform .12s ease;
}
.btn-desir-confirm:hover { background: #d9a59a; transform: translateY(-1px); }
/* Permanent table rows hover */
#permanentsTableBody tr { transition: background 0.15s; cursor: pointer; }
#permanentsTableBody tr:hover,
#permanentsTableBody tr:hover td {
  background: #faf7f0 !important;
}

/* Stats cards en haut de page */
.desir-stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 1.25rem;
}
@media (max-width: 900px) {
  .desir-stats-row { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 540px) {
  .desir-stats-row { grid-template-columns: 1fr; }
}
.desir-stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: #fff;
  border: 1px solid #e8e4da;
  border-radius: 14px;
  transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.desir-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,.06);
  border-color: #d1cfc5;
}
.desir-stat-icon {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}
.desir-stat-body { min-width: 0; flex: 1; }
.desir-stat-label {
  font-size: .72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  color: #8a8578;
  margin-bottom: 2px;
}
.desir-stat-value {
  font-size: 1.3rem;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 1.1;
}
.desir-stat-sub {
  font-size: .72rem;
  color: #8a8578;
  margin-top: 2px;
}
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
