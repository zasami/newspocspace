<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="mst-wrap">
    <div class="mst-header">
        <h2 class="mst-title"><i class="bi bi-journal-text"></i> Mon stage</h2>
    </div>
    <div id="mstContent"><div class="text-muted">Chargement…</div></div>

    <!-- Modal report -->
    <div class="ss-modal" id="mstReportModal" style="display:none">
        <div class="ss-modal-backdrop"></div>
        <div class="ss-modal-dialog ss-modal-lg">
            <div class="ss-modal-header">
                <h3 id="mstReportTitle">Nouveau report</h3>
                <button class="ss-modal-close" data-close-report>&times;</button>
            </div>
            <div class="ss-modal-body">
                <input type="hidden" id="mstReportId">
                <div class="mst-form-row">
                    <div class="flex-1">
                        <label class="ms-lbl">Type</label>
                        <select id="mstRType" class="ms-input">
                            <option value="quotidien">Quotidien</option>
                            <option value="hebdo">Hebdomadaire</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="ms-lbl">Date</label>
                        <input type="date" id="mstRDate" class="ms-input">
                    </div>
                </div>
                <label class="ms-lbl">Titre (optionnel)</label>
                <input type="text" id="mstRTitre" class="ms-input" placeholder="Ex. Accompagnement toilettes matinales">

                <div class="mst-section-label">
                    <i class="bi bi-check2-square"></i> Tâches réalisées aujourd'hui
                    <span class="text-muted small">(coche tout ce que tu as fait)</span>
                </div>
                <div id="mstTachesList" class="mst-taches-list"><div class="text-muted small">Chargement du catalogue…</div></div>

                <label class="ms-lbl mt-3">Contenu du rapport *</label>
                <div id="mstREditor" class="mst-editor-wrap"></div>
            </div>
            <div class="ss-modal-footer">
                <button class="ss-btn-secondary" data-close-report>Annuler</button>
                <button class="ss-btn-secondary" id="btnSaveDraft">Brouillon</button>
                <button class="ss-btn-primary" id="btnSubmitReport">Soumettre</button>
            </div>
        </div>
    </div>
</div>
