<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['ss_user']['id'];
$chgCurrentMois = date('Y-m');

// Mon planning du mois courant
$chgPlanning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$chgCurrentMois]);
$chgMyPlanning = [];
if ($chgPlanning) {
    $chgMyPlanning = Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.date_jour, pa.statut AS assign_statut,
                ht.id AS horaire_type_id, ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.couleur, ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom, m.code AS module_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.user_id = ?
         ORDER BY pa.date_jour",
        [$chgPlanning['id'], $uid]
    );
}

// Collègues (même logique que get_collegues())
$chgUserRow = Db::fetch("SELECT fonction_id FROM users WHERE id = ?", [$uid]);
$chgFonctionId = $chgUserRow['fonction_id'] ?? null;
$chgAllCollegues = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.fonction_id,
            f.nom AS fonction_nom, f.code AS fonction_code,
            m.nom AS module_nom, m.code AS module_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN (
         SELECT pa.user_id, pa.module_id
         FROM planning_assignations pa
         WHERE pa.module_id IS NOT NULL
         ORDER BY pa.date_jour DESC
         LIMIT 1000
     ) last_pa ON last_pa.user_id = u.id
     LEFT JOIN modules m ON m.id = last_pa.module_id
     WHERE u.id != ? AND u.is_active = 1
     GROUP BY u.id
     ORDER BY u.nom, u.prenom",
    [$uid]
);

// Mes changements
$chgChangements = Db::fetchAll(
    "SELECT ch.*,
            ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom,
            ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom,
            ht_dem.code AS horaire_demandeur_code, ht_dem.nom AS horaire_demandeur_nom, ht_dem.couleur AS horaire_demandeur_couleur,
            ht_dest.code AS horaire_destinataire_code, ht_dest.nom AS horaire_destinataire_nom, ht_dest.couleur AS horaire_destinataire_couleur,
            m_dem.nom AS module_demandeur_nom, m_dest.nom AS module_destinataire_nom,
            ut.prenom AS traite_par_prenom, ut.nom AS traite_par_nom
     FROM changements_horaire ch
     JOIN users ud ON ud.id = ch.demandeur_id
     JOIN users ude ON ude.id = ch.destinataire_id
     JOIN planning_assignations pa_dem ON pa_dem.id = ch.assignation_demandeur_id
     JOIN planning_assignations pa_dest ON pa_dest.id = ch.assignation_destinataire_id
     LEFT JOIN horaires_types ht_dem ON ht_dem.id = pa_dem.horaire_type_id
     LEFT JOIN horaires_types ht_dest ON ht_dest.id = pa_dest.horaire_type_id
     LEFT JOIN modules m_dem ON m_dem.id = pa_dem.module_id
     LEFT JOIN modules m_dest ON m_dest.id = pa_dest.module_id
     LEFT JOIN users ut ON ut.id = ch.traite_par
     WHERE ch.demandeur_id = ? OR ch.destinataire_id = ?
     ORDER BY ch.created_at DESC",
    [$uid, $uid]
);
?>
<div class="page-header">
  <h1><i class="bi bi-arrow-left-right"></i> Changements d'horaire</h1>
  <p>Proposer un échange d'horaire croisé avec un collègue</p>
</div>

<!-- ── Top row: calendrier + liste demandes ── -->
<div class="chg-top-row">
  <!-- Mon calendrier -->
  <div class="card chg-top-cal">
    <div class="card-header">
      <h3><i class="bi bi-calendar3"></i> Mon planning</h3>
    </div>
    <div class="card-body">
      <div class="chg-cal-nav">
        <button class="btn btn-sm btn-outline-secondary" id="chgMyPrev"><i class="bi bi-chevron-left"></i></button>
        <span class="chg-cal-month" id="chgMyMonth"></span>
        <button class="btn btn-sm btn-outline-secondary" id="chgMyNext"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="chg-calendar" id="chgMyCal"></div>
      <div class="chg-cal-hint chg-hidden" id="chgMyHint">
        <i class="bi bi-hand-index"></i> Cliquez sur un jour pour proposer un échange
      </div>
    </div>
  </div>

  <!-- Mes demandes -->
  <div class="card chg-top-list">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3><i class="bi bi-list-check"></i> Mes demandes</h3>
      <span class="badge badge-neutral" id="chgListCount">0</span>
    </div>
    <div class="card-body chg-list-body">
      <div id="changementsList"></div>
    </div>
  </div>
</div>

<!-- ── Slidedown: collègue + son planning ── -->
<div class="chg-slidedown chg-slide-closed" id="chgSlidedown">
  <div class="chg-slide-header" id="chgSlideHeader">
    <div class="chg-slide-title">
      <i class="bi bi-arrow-left-right"></i>
      Échange du <strong id="chgSlideDate"></strong>
      <span id="chgSlideBadge"></span>
    </div>
    <button class="btn btn-sm btn-light" id="chgSlideClose"><i class="bi bi-x-lg"></i></button>
  </div>

  <div class="chg-slide-row">
    <!-- Liste collègues -->
    <div class="chg-slide-left">
      <div class="chg-slide-search">
        <i class="bi bi-search chg-search-icon"></i>
        <input type="text" class="form-control chg-search-input" id="chgColSearch" placeholder="Rechercher un collègue...">
      </div>
      <div class="chg-colleague-list" id="chgColList"></div>
    </div>

    <!-- Planning collègue ou placeholder -->
    <div class="chg-slide-right" id="chgSlideRight">
      <div class="chg-slide-placeholder" id="chgSlidePlaceholder">
        <i class="bi bi-person-plus"></i>
        <span>Sélectionnez un collègue dans la liste pour afficher son planning</span>
      </div>
      <div class="chg-hidden" id="chgColPanel">
        <div class="chg-col-panel-header" id="chgColPanelHeader"></div>
        <div class="chg-cal-nav">
          <button class="btn btn-sm btn-outline-secondary" id="chgColPrev"><i class="bi bi-chevron-left"></i></button>
          <span class="chg-cal-month" id="chgColMonth"></span>
          <button class="btn btn-sm btn-outline-secondary" id="chgColNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="chg-calendar" id="chgColCal"></div>
        <div class="chg-col-hint">
          <i class="bi bi-hand-index"></i> Cliquez sur le jour à <strong>prendre</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal confirmation (Bootstrap 5 — style admin absences) ── -->
<div class="modal fade" id="chgConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable chg-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3" id="chgConfirmHeader">
          <i class="bi bi-arrow-left-right"></i>
          <span class="fw-semibold">Confirmer l'échange</span>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center chg-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="chgConfirmBody">
        <!-- Filled by JS -->
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm chg-btn-envoyer ms-auto px-3" id="chgSubmitBtn"><i class="bi bi-send"></i> Envoyer la demande</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal refus (Bootstrap 5 — style admin absences) ── -->
<div class="modal fade" id="refusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered chg-modal-dialog-sm">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-x-circle"></i>
          <span class="fw-semibold">Refuser le changement</span>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center chg-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Raison du refus (optionnel)</label>
          <textarea class="form-control" id="refusRaison" rows="2" placeholder="Expliquer la raison..." maxlength="500"></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm chg-btn-refuser ms-auto px-3" id="refusConfirmBtn"><i class="bi bi-x-circle"></i> Refuser</button>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'my_planning'  => $chgMyPlanning,
    'collegues'    => $chgAllCollegues,
    'changements'  => $chgChangements,
    'my_fonction_id' => $chgFonctionId,
    'current_mois' => $chgCurrentMois,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
