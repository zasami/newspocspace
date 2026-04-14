<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="an-emp-wrap">
    <div class="an-emp-header">
        <h2 class="an-emp-title"><i class="bi bi-telephone"></i> Annuaire téléphonique</h2>
        <div class="an-emp-tabs">
            <button class="an-emp-tab active" data-tab="collegues"><i class="bi bi-people-fill"></i> Collègues</button>
            <button class="an-emp-tab" data-tab="urgence"><i class="bi bi-exclamation-triangle-fill"></i> Urgence</button>
            <button class="an-emp-tab" data-tab="interne">Interne</button>
            <button class="an-emp-tab" data-tab="externe">Externe</button>
            <button class="an-emp-tab" data-tab="all">Tous</button>
            <button class="an-emp-tab" data-tab="history"><i class="bi bi-clock-history"></i> Historique</button>
        </div>
    </div>

    <div id="anEmpUrgenceQuick" class="an-emp-urgence-grid"></div>
    <div id="anEmpList" class="an-emp-list"></div>
</div>
