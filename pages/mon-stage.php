<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="mst-wrap">
    <div class="mst-header">
        <h2 class="mst-title"><i class="bi bi-journal-text"></i> Mon stage</h2>
    </div>
    <div id="mstContent"><div class="text-muted">Chargement…</div></div>

    <!-- Modal report -->
    <div class="ss-modal" id="mstReportModal" style="display:none">
        <div class="ss-modal-backdrop"></div>
        <div class="ss-modal-dialog">
            <div class="ss-modal-header">
                <h3 id="mstReportTitle">Nouveau report</h3>
                <button class="ss-modal-close" data-close-report>&times;</button>
            </div>
            <div class="ss-modal-body">
                <input type="hidden" id="mstReportId">
                <label class="ms-lbl">Type</label>
                <select id="mstRType" class="ms-input">
                    <option value="quotidien">Quotidien</option>
                    <option value="hebdo">Hebdomadaire</option>
                </select>
                <label class="ms-lbl">Date</label>
                <input type="date" id="mstRDate" class="ms-input">
                <label class="ms-lbl">Titre (optionnel)</label>
                <input type="text" id="mstRTitre" class="ms-input" placeholder="Ex. Accompagnement toilettes matinales">
                <label class="ms-lbl">Contenu *</label>
                <textarea id="mstRContenu" class="ms-input" rows="10" placeholder="Décris ta journée, ce que tu as appris, les difficultés rencontrées, les questions..."></textarea>
            </div>
            <div class="ss-modal-footer">
                <button class="ss-btn-secondary" data-close-report>Annuler</button>
                <button class="ss-btn-secondary" id="btnSaveDraft">Brouillon</button>
                <button class="ss-btn-primary" id="btnSubmitReport">Soumettre</button>
            </div>
        </div>
    </div>
</div>
