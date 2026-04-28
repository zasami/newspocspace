<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$userId = $_SESSION['ss_user']['id'];
$today  = date('Y-m-d');

// Stats SSR rapides (synchro avec API)
$nbAVenir = (int) Db::getOne(
    "SELECT COUNT(*) FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut = 'inscrit'
       AND (f.date_debut IS NULL OR f.date_debut >= ?)",
    [$userId, $today]
);
$nbValidees = (int) Db::getOne(
    "SELECT COUNT(*) FROM formation_participants WHERE user_id = ? AND statut = 'valide'",
    [$userId]
);
$heuresAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut IN ('present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())",
    [$userId]
);
$nbSouhaits = (int) Db::getOne(
    "SELECT COUNT(*) FROM formation_souhaits WHERE user_id = ? AND statut = 'en_attente'",
    [$userId]
);
?>
<div class="page-wrap">
  <?= render_page_header('Mes formations', 'bi-mortarboard', null, null) ?>

  <!-- Cartes stats -->
  <div class="row g-3 mb-3">
    <?= render_stat_card('À venir', $nbAVenir, 'bi-calendar-event', 'teal') ?>
    <?= render_stat_card('Validées', $nbValidees, 'bi-patch-check', 'green') ?>
    <?= render_stat_card('Heures '.date('Y'), number_format($heuresAnnee, 0, ',', ' '), 'bi-clock-history', 'orange') ?>
    <?= render_stat_card('Souhaits', $nbSouhaits, 'bi-star', 'purple') ?>
  </div>

  <!-- Onglets -->
  <ul class="nav nav-tabs mb-3" id="formTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAVenir" type="button">
        <i class="bi bi-calendar-event"></i> À venir <span class="badge text-bg-light ms-1" id="badgeAVenir"><?= $nbAVenir ?></span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPassees" type="button">
        <i class="bi bi-clock-history"></i> Mes formations passées
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCatalogue" type="button">
        <i class="bi bi-book"></i> Catalogue
      </button>
    </li>
    <?php if ($nbSouhaits > 0): ?>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSouhaits" type="button">
          <i class="bi bi-star"></i> Mes souhaits <span class="badge text-bg-warning ms-1"><?= $nbSouhaits ?></span>
        </button>
      </li>
    <?php endif ?>
  </ul>

  <div class="tab-content">
    <!-- À VENIR -->
    <div class="tab-pane fade show active" id="tabAVenir">
      <div id="formAVenirList">
        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
      </div>
    </div>

    <!-- PASSÉES -->
    <div class="tab-pane fade" id="tabPassees">
      <div id="formPasseesList">
        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
      </div>
    </div>

    <!-- CATALOGUE -->
    <div class="tab-pane fade" id="tabCatalogue">
      <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle"></i>
        Formations proposées par l'EMS. Si une formation correspond à votre fonction, elle est marquée
        d'un <span class="badge text-bg-success">Pour vous</span>. Cliquez sur "Souhaite participer"
        pour exprimer votre intérêt — votre responsable RH sera notifié.
      </div>
      <div id="formCatalogueList">
        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
      </div>
    </div>

    <!-- SOUHAITS -->
    <?php if ($nbSouhaits > 0): ?>
    <div class="tab-pane fade" id="tabSouhaits">
      <div id="formSouhaitsList">
        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>

<!-- Modal upload certificat -->
<div class="modal fade" id="certifModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Charger mon certificat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="certifFormationTitle" class="fw-bold mb-2"></div>
        <p class="text-muted small mb-3">Formats acceptés : PDF, DOC, DOCX, JPG, PNG, GIF, WebP. Taille max : 8 Mo.</p>
        <input type="hidden" id="certifParticipantId">
        <input type="file" class="form-control" id="certifFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*">
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary btn-sm" id="certifUploadBtn"><i class="bi bi-cloud-upload"></i> Téléverser</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal souhait participation -->
<div class="modal fade" id="souhaitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-star"></i> Souhaite participer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="souhaitFormationTitle" class="fw-bold mb-2"></div>
        <input type="hidden" id="souhaitFormationId">
        <label class="form-label small">Message à votre responsable (optionnel)</label>
        <textarea class="form-control" id="souhaitMessage" rows="3" placeholder="Pourquoi souhaitez-vous suivre cette formation ?"></textarea>
        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-info-circle"></i> Votre responsable formation sera notifié de votre souhait.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary btn-sm" id="souhaitSubmitBtn"><i class="bi bi-send"></i> Envoyer ma demande</button>
      </div>
    </div>
  </div>
</div>

<style>
.fmt-card {
  background: var(--cl-surface, #fff);
  border: 1px solid var(--cl-border-light, #F0EDE8);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  transition: all .18s ease;
}
.fmt-card:hover {
  border-color: var(--cl-teal, #2d4a43);
  box-shadow: 0 4px 12px rgba(0,0,0,.05);
}
.fmt-card-row {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.fmt-thumb {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  flex-shrink: 0;
  background: linear-gradient(135deg, #bcd2cb, #2d4a43);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.6rem;
  overflow: hidden;
}
.fmt-thumb img { width: 100%; height: 100%; object-fit: cover; }
.fmt-info { flex: 1; min-width: 0; }
.fmt-title { font-size: .98rem; font-weight: 700; color: var(--cl-text); margin-bottom: 4px; }
.fmt-meta { font-size: .82rem; color: var(--cl-text-muted); display: flex; gap: 12px; flex-wrap: wrap; }
.fmt-meta span { display: inline-flex; align-items: center; gap: 4px; }
.fmt-actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; align-items: flex-start; }
.fmt-status-pill { font-size: .68rem; padding: 3px 9px; border-radius: 99px; font-weight: 600; }
.fmt-status-inscrit { background: #B8C9D4; color: #3B4F6B; }
.fmt-status-present { background: #D4C4A8; color: #6B5B3E; }
.fmt-status-valide  { background: #bcd2cb; color: #2d4a43; }
.fmt-status-absent  { background: #E2B8AE; color: #7B3B2C; }
.fmt-certif-btn {
  font-size: .78rem;
  padding: 4px 10px;
}
.fmt-certif-btn.has-certif { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.fmt-empty {
  text-align: center;
  padding: 40px 20px;
  color: var(--cl-text-muted);
}
.fmt-empty i { font-size: 2rem; opacity: .3; display: block; margin-bottom: 8px; }
.fmt-match-badge {
  background: #bcd2cb;
  color: #2d4a43;
  font-size: .68rem;
  padding: 2px 8px;
  border-radius: 99px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}
</style>
