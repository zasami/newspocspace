<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="sd-wrap container-fluid">
  <!-- Breadcrumb back link -->
  <button class="btn btn-sm btn-link re-back-link mb-1 px-0" data-link="mes-stagiaires">
    <i class="bi bi-arrow-left"></i> Mes stagiaires
  </button>

  <!-- Header : titre à gauche, actions à droite -->
  <div class="sd-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="sd-title mb-0"><i class="bi bi-person-badge"></i> <span id="sdTitle">Profil stagiaire</span></h2>
    <div class="d-flex gap-2" id="sdTopActions"></div>
  </div>

  <!-- Stats cards -->
  <div class="row g-3 mb-4" id="sdStats"></div>

  <!-- Tabs navigation -->
  <ul class="nav nav-tabs mb-3" id="sdTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#sdTabInfos">Infos</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabReports">Reports</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabEvals">Évaluations</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabObjectifs">Objectifs</a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="sdTabInfos">
      <div class="card">
        <div class="card-body" id="sdInfosBody"><div class="text-muted">Chargement…</div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="sdTabReports">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">Reports rédigés par le stagiaire — valide ou demande une correction.</small>
        <div>
          <select class="form-select form-select-sm" id="sdReportFilter" style="max-width:200px">
            <option value="">Tous les statuts</option>
            <option value="soumis">À valider</option>
            <option value="valide">Validés</option>
            <option value="a_refaire">À refaire</option>
            <option value="brouillon">Brouillons</option>
          </select>
        </div>
      </div>
      <div id="sdReportsBody"></div>
    </div>

    <div class="tab-pane fade" id="sdTabEvals">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">Grille d'évaluation remplie par les formateurs.</small>
        <button class="btn btn-sm btn-primary" id="sdBtnNewEval" disabled><i class="bi bi-plus-lg"></i> Nouvelle évaluation</button>
      </div>
      <div id="sdEvalsBody"></div>
    </div>

    <div class="tab-pane fade" id="sdTabObjectifs">
      <small class="text-muted d-block mb-2">Objectifs définis par la RUV.</small>
      <div id="sdObjectifsBody"></div>
    </div>
  </div>
</div>

<!-- Modal évaluation — pattern SpocCare -->
<div class="modal fade" id="sdEvalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:640px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Nouvelle évaluation</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal" aria-label="Fermer">
          <i class="bi bi-x-lg" style="font-size:0.85rem"></i>
        </button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <input type="hidden" id="sdEvalId">
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small fw-semibold">Date</label><input type="date" id="sdEvalDate" class="form-control form-control-sm"></div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Période</label>
            <select id="sdEvalPeriode" class="form-select form-select-sm">
              <option value="journaliere">Journalière</option>
              <option value="hebdo">Hebdomadaire</option>
              <option value="mi_stage">Mi-stage</option>
              <option value="finale">Finale</option>
            </select>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Initiative</label><input type="number" min="1" max="5" id="sdNInit" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Communication</label><input type="number" min="1" max="5" id="sdNComm" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Connaissances</label><input type="number" min="1" max="5" id="sdNConn" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Autonomie</label><input type="number" min="1" max="5" id="sdNAuto" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Savoir-être</label><input type="number" min="1" max="5" id="sdNSav" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Ponctualité</label><input type="number" min="1" max="5" id="sdNPonc" class="form-control form-control-sm"></div>
        </div>
        <label class="form-label small fw-semibold mt-2">Points forts</label>
        <textarea id="sdPFortes" class="form-control form-control-sm" rows="2"></textarea>
        <label class="form-label small fw-semibold mt-2">Points à améliorer</label>
        <textarea id="sdPAmelio" class="form-control form-control-sm" rows="2"></textarea>
        <label class="form-label small fw-semibold mt-2">Commentaire général</label>
        <textarea id="sdComGen" class="form-control form-control-sm" rows="3"></textarea>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="sdBtnSaveEval">
          <i class="bi bi-check-lg"></i> Enregistrer
        </button>
      </div>
    </div>
  </div>
</div>
