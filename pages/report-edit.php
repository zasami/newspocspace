<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="re-wrap container-fluid">
  <div class="re-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-secondary" data-link="mon-stage" title="Retour">
        <i class="bi bi-arrow-left"></i> Retour
      </button>
      <h2 class="re-title mb-0"><i class="bi bi-journal-plus"></i> <span id="reTitle">Nouveau report</span></h2>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" id="reBtnDraft">
        <i class="bi bi-save"></i> Enregistrer brouillon
      </button>
      <button class="btn btn-sm btn-primary" id="reBtnSubmit">
        <i class="bi bi-send"></i> Soumettre au formateur
      </button>
    </div>
  </div>

  <div class="row g-3 re-columns">
    <!-- Colonne principale : meta + éditeur -->
    <div class="col-lg-7">
      <div class="card re-card">
        <div class="card-body">
          <input type="hidden" id="reReportId">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Type</label>
              <select id="reType" class="form-select form-select-sm">
                <option value="quotidien">Quotidien</option>
                <option value="hebdo">Hebdomadaire</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Date</label>
              <input type="date" id="reDate" class="form-control form-control-sm">
            </div>
          </div>
          <label class="form-label small fw-semibold">Titre (optionnel)</label>
          <input type="text" id="reTitre" class="form-control form-control-sm mb-3" placeholder="Ex. Accompagnement toilettes matinales">
          <label class="form-label small fw-semibold">Contenu du rapport *</label>
          <div id="reEditor" class="re-editor-wrap"></div>
          <div class="form-text small mt-2 mb-0">
            <i class="bi bi-info-circle"></i>
            Décris ta journée, ce que tu as appris, les difficultés rencontrées, les questions que tu veux poser à ton formateur.
          </div>
        </div>
      </div>
    </div>

    <!-- Colonne checklist tâches -->
    <div class="col-lg-5">
      <div class="card re-card re-taches-card">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-check2-square"></i>
          <strong>Tâches réalisées aujourd'hui</strong>
          <span class="stg-type-badge ms-auto" id="reCountBadge">0</span>
        </div>
        <div class="card-body re-taches-body p-0">
          <div class="re-taches-search p-2 border-bottom">
            <input type="text" id="reTacheSearch" class="form-control form-control-sm" placeholder="Rechercher une tâche…">
          </div>
          <div id="reTachesList" class="re-taches-scroll"><div class="text-muted small p-2">Chargement du catalogue…</div></div>
        </div>
        <div class="card-footer re-taches-footer">
          <i class="bi bi-lightbulb"></i>
          <span id="reTachesFooter">
            <strong id="reTachesFooterCount">0</strong> tâche(s) cochée(s).
            Ton formateur pourra valider chacune (<span class="re-niv-dot re-niv-acquis"></span> acquis,
            <span class="re-niv-dot re-niv-en_cours"></span> en cours,
            <span class="re-niv-dot re-niv-non_acquis"></span> non acquis).
          </span>
        </div>
      </div>
    </div>
  </div>
</div>
