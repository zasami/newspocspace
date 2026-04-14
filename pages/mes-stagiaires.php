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

    <!-- Modal détail stagiaire (vue formateur) -->
    <div class="ss-modal" id="msDetailModal" style="display:none">
        <div class="ss-modal-backdrop"></div>
        <div class="ss-modal-dialog ss-modal-lg">
            <div class="ss-modal-header">
                <h3 id="msDetailTitle">Stagiaire</h3>
                <button class="ss-modal-close" data-close-ms>&times;</button>
            </div>
            <div class="ss-modal-body" id="msDetailBody"></div>
        </div>
    </div>

    <!-- Modal évaluation -->
    <div class="ss-modal" id="msEvalModal" style="display:none">
        <div class="ss-modal-backdrop"></div>
        <div class="ss-modal-dialog">
            <div class="ss-modal-header">
                <h3>Évaluation</h3>
                <button class="ss-modal-close" data-close-eval>&times;</button>
            </div>
            <div class="ss-modal-body">
                <input type="hidden" id="msEvalId">
                <input type="hidden" id="msEvalStagId">
                <div class="ms-eval-form">
                    <label class="ms-lbl">Date</label>
                    <input type="date" id="msEvalDate" class="ms-input">
                    <label class="ms-lbl">Période</label>
                    <select id="msEvalPeriode" class="ms-input">
                        <option value="journaliere">Journalière</option>
                        <option value="hebdo">Hebdomadaire</option>
                        <option value="mi_stage">Mi-stage</option>
                        <option value="finale">Finale</option>
                    </select>
                    <div class="ms-notes-grid">
                        <div><label class="ms-lbl">Initiative</label><input type="number" min="1" max="5" id="msNInit" class="ms-input"></div>
                        <div><label class="ms-lbl">Communication</label><input type="number" min="1" max="5" id="msNComm" class="ms-input"></div>
                        <div><label class="ms-lbl">Connaissances</label><input type="number" min="1" max="5" id="msNConn" class="ms-input"></div>
                        <div><label class="ms-lbl">Autonomie</label><input type="number" min="1" max="5" id="msNAuto" class="ms-input"></div>
                        <div><label class="ms-lbl">Savoir-être</label><input type="number" min="1" max="5" id="msNSav" class="ms-input"></div>
                        <div><label class="ms-lbl">Ponctualité</label><input type="number" min="1" max="5" id="msNPonc" class="ms-input"></div>
                    </div>
                    <label class="ms-lbl">Points forts</label>
                    <textarea id="msPFortes" class="ms-input" rows="2"></textarea>
                    <label class="ms-lbl">Points à améliorer</label>
                    <textarea id="msPAmelio" class="ms-input" rows="2"></textarea>
                    <label class="ms-lbl">Commentaire général</label>
                    <textarea id="msComGen" class="ms-input" rows="3"></textarea>
                </div>
            </div>
            <div class="ss-modal-footer">
                <button class="ss-btn-secondary" data-close-eval>Annuler</button>
                <button class="ss-btn-primary" id="btnSaveEval">Enregistrer</button>
            </div>
        </div>
    </div>
</div>
