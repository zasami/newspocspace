<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="ms-wrap">
    <div class="ms-header">
        <h2 class="ms-title"><i class="bi bi-mortarboard-fill"></i> Mes stagiaires</h2>
        <p class="ms-sub text-muted small mb-0">Liste des stagiaires dont vous êtes formateur — validez leurs reports et complétez les évaluations.</p>
    </div>

    <div id="msActifs"></div>

    <div class="ms-history-section">
        <h5 class="mt-4"><i class="bi bi-clock-history"></i> Historique</h5>
        <div id="msHistory"></div>
    </div>
</div>

<!-- Modal détail stagiaire -->
<div class="modal fade" id="msDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="msDetailTitle">Stagiaire</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body" id="msDetailBody"></div>
    </div>
  </div>
</div>

<!-- Modal évaluation -->
<div class="modal fade" id="msEvalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Évaluation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="msEvalId">
        <input type="hidden" id="msEvalStagId">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" id="msEvalDate" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Période</label>
            <select id="msEvalPeriode" class="form-select form-select-sm">
              <option value="journaliere">Journalière</option>
              <option value="hebdo">Hebdomadaire</option>
              <option value="mi_stage">Mi-stage</option>
              <option value="finale">Finale</option>
            </select>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Initiative</label><input type="number" min="1" max="5" id="msNInit" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Communication</label><input type="number" min="1" max="5" id="msNComm" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Connaissances</label><input type="number" min="1" max="5" id="msNConn" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Autonomie</label><input type="number" min="1" max="5" id="msNAuto" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Savoir-être</label><input type="number" min="1" max="5" id="msNSav" class="form-control form-control-sm"></div>
          <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Ponctualité</label><input type="number" min="1" max="5" id="msNPonc" class="form-control form-control-sm"></div>
        </div>
        <label class="form-label small fw-semibold mt-2">Points forts</label>
        <textarea id="msPFortes" class="form-control form-control-sm" rows="2"></textarea>
        <label class="form-label small fw-semibold mt-2">Points à améliorer</label>
        <textarea id="msPAmelio" class="form-control form-control-sm" rows="2"></textarea>
        <label class="form-label small fw-semibold mt-2">Commentaire général</label>
        <textarea id="msComGen" class="form-control form-control-sm" rows="3"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnSaveEval">Enregistrer</button>
      </div>
    </div>
  </div>
</div>
