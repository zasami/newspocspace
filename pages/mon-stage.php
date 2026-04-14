<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="mst-wrap">
    <div class="mst-header">
        <h2 class="mst-title"><i class="bi bi-journal-text"></i> Mon stage</h2>
    </div>
    <div id="mstContent"><div class="text-muted">Chargement…</div></div>
</div>
