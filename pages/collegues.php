<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$colleguesAbsences = Db::fetchAll(
    "SELECT a.id, u.prenom, u.nom, a.date_debut, a.date_fin, a.type, a.statut,
            m.nom AS module_nom
     FROM absences a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE a.statut = 'valide'
       AND a.date_fin >= CURDATE()
     ORDER BY a.date_debut",
    []
);
?>
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
<script type="application/json" id="__zt_ssr__"><?= json_encode(['absences' => $colleguesAbsences], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
