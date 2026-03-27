<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1><i class="bi bi-people"></i> Congés des Collègues</h1>
  <p>Absences validées de vos collègues (données anonymisées RGPD — pas de motif médical)</p>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Collaborateur</th>
            <th>Type</th>
            <th>Du</th>
            <th>Au</th>
            <th>Module</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody id="colleguesTableBody">
          <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Chargement...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
