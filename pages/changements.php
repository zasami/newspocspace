<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
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
  <div class="modal-dialog modal-dialog-centered chg-modal-dialog">
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
