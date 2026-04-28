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

<!-- Modal upload certificat · zone drag & drop -->
<div class="modal fade" id="certifModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Charger mon certificat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="certifFormationTitle" class="fw-bold mb-3"></div>
        <input type="hidden" id="certifParticipantId">

        <!-- Zone drag & drop -->
        <label class="cert-drop" id="certDrop">
          <input type="file" id="certifFile" hidden
                 accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*">
          <div class="cert-drop-empty">
            <div class="cert-drop-icons">
              <span class="cert-mini cert-mini-pdf"><i class="bi bi-file-earmark-pdf-fill"></i></span>
              <span class="cert-mini cert-mini-doc"><i class="bi bi-file-earmark-word-fill"></i></span>
              <span class="cert-mini cert-mini-img"><i class="bi bi-file-earmark-image-fill"></i></span>
            </div>
            <div class="cert-drop-title">Glissez votre fichier ici</div>
            <div class="cert-drop-sub">ou <strong>cliquez pour parcourir</strong></div>
            <div class="cert-drop-hint">PDF · DOC · DOCX · JPG · PNG · GIF · WebP · max 8 Mo</div>
          </div>
          <div class="cert-drop-filled" hidden>
            <div class="cert-file-card" id="certFilePreview">
              <div class="cert-file-ico" id="certFileIco"><i class="bi bi-file-earmark"></i></div>
              <div class="cert-file-info">
                <div class="cert-file-name" id="certFileName">—</div>
                <div class="cert-file-size" id="certFileSize">—</div>
              </div>
              <button type="button" class="cert-file-remove" id="certFileRemove" title="Retirer">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
        </label>
        <div class="cert-error text-danger small mt-2" id="certError" hidden></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary btn-sm" id="certifUploadBtn" disabled><i class="bi bi-cloud-upload"></i> Téléverser</button>
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
/* ─── Zone drop certificat ─── */
.cert-drop {
  display: block; position: relative; cursor: pointer;
  border: 2px dashed var(--cl-border-hover, #d4d0ca);
  border-radius: 14px;
  background: var(--cl-bg, #fafaf8);
  transition: all .15s ease;
  padding: 28px 20px;
  margin: 0;
}
.cert-drop:hover, .cert-drop.dragover {
  border-color: var(--cl-primary, #1f6359);
  background: var(--cl-primary-bg, #ecf5f3);
}
.cert-drop.has-file { padding: 14px; cursor: default; border-style: solid; background: var(--cl-surface, #fff); }
.cert-drop-empty { text-align: center; }
.cert-drop-icons {
  display: flex; gap: 10px; justify-content: center; margin-bottom: 12px;
}
.cert-mini {
  width: 48px; height: 56px; border-radius: 8px;
  display: grid; place-items: center; font-size: 22px; color: #fff;
  position: relative; box-shadow: 0 2px 6px rgba(0,0,0,.08);
  transition: transform .2s ease;
}
.cert-drop:hover .cert-mini { transform: translateY(-2px); }
.cert-mini-pdf { background: linear-gradient(135deg, #b8443a, #8e342c); }
.cert-mini-doc { background: linear-gradient(135deg, #2b5797, #1d3f6d); }
.cert-mini-img { background: linear-gradient(135deg, #3d8b6b, #2a6b51); }
.cert-mini::after {
  content: ""; position: absolute; top: 0; right: 0;
  border-top: 10px solid transparent; border-left: 10px solid rgba(255,255,255,.25);
  border-radius: 0 8px 0 0;
}
.cert-drop-title {
  font-weight: 600; font-size: 15px; color: var(--cl-text, #1a1a1a); margin-bottom: 3px;
}
.cert-drop-sub { font-size: 13px; color: var(--cl-text-secondary, #6b6b6b); }
.cert-drop-sub strong { color: var(--cl-primary, #1f6359); font-weight: 600; }
.cert-drop-hint {
  font-size: 11px; color: var(--cl-text-muted, #9b9b9b);
  margin-top: 8px; letter-spacing: .02em;
}

/* Card fichier sélectionné */
.cert-file-card {
  display: flex; gap: 12px; align-items: center;
  padding: 12px 14px;
  background: var(--cl-surface, #fff);
  border: 1px solid var(--cl-border, #e8e5e0);
  border-radius: 10px;
}
.cert-file-ico {
  width: 48px; height: 56px; border-radius: 8px;
  display: grid; place-items: center; font-size: 22px; color: #fff;
  flex-shrink: 0; position: relative;
  box-shadow: 0 2px 6px rgba(0,0,0,.08);
}
.cert-file-ico.t-pdf { background: linear-gradient(135deg, #b8443a, #8e342c); }
.cert-file-ico.t-doc { background: linear-gradient(135deg, #2b5797, #1d3f6d); }
.cert-file-ico.t-img { background: linear-gradient(135deg, #3d8b6b, #2a6b51); }
.cert-file-ico.t-other { background: linear-gradient(135deg, #6b6b6b, #4a4a4a); }
.cert-file-ico::after {
  content: ""; position: absolute; top: 0; right: 0;
  border-top: 10px solid transparent; border-left: 10px solid rgba(255,255,255,.25);
  border-radius: 0 8px 0 0;
}
.cert-file-info { flex: 1; min-width: 0; }
.cert-file-name {
  font-weight: 600; font-size: 13.5px; color: var(--cl-text, #1a1a1a);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cert-file-size {
  font-size: 11.5px; color: var(--cl-text-muted, #9b9b9b);
  font-family: 'JetBrains Mono', monospace; margin-top: 2px;
}
.cert-file-remove {
  width: 28px; height: 28px; border-radius: 50%;
  background: rgba(0,0,0,.05); border: 0; color: #6b6b6b;
  cursor: pointer; transition: .12s; flex-shrink: 0;
  display: grid; place-items: center;
}
.cert-file-remove:hover {
  background: var(--cl-danger, #b8443a); color: #fff;
}

/* ─── Card certificat dans liste formations (au lieu du bouton "voir") ─── */
.fmt-cert-card {
  display: inline-flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 10px 14px 8px;
  background: var(--cl-bg, #fafaf8);
  border: 1px solid var(--cl-border-light, #F0EDE8);
  border-radius: 10px;
  text-decoration: none; color: var(--cl-text, #1a1a1a);
  transition: .15s ease;
  min-width: 90px; max-width: 140px;
}
.fmt-cert-card:hover {
  border-color: var(--cl-primary, #1f6359);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  color: var(--cl-text, #1a1a1a);
}
.fmt-cert-card-ico {
  width: 44px; height: 52px; border-radius: 7px;
  display: grid; place-items: center; font-size: 20px; color: #fff;
  position: relative;
  box-shadow: 0 2px 4px rgba(0,0,0,.1);
}
.fmt-cert-card-ico.t-pdf { background: linear-gradient(135deg, #b8443a, #8e342c); }
.fmt-cert-card-ico.t-doc { background: linear-gradient(135deg, #2b5797, #1d3f6d); }
.fmt-cert-card-ico.t-img { background: linear-gradient(135deg, #3d8b6b, #2a6b51); }
.fmt-cert-card-ico.t-other { background: linear-gradient(135deg, #6b6b6b, #4a4a4a); }
.fmt-cert-card-ico::after {
  content: ""; position: absolute; top: 0; right: 0;
  border-top: 8px solid transparent; border-left: 8px solid rgba(255,255,255,.25);
  border-radius: 0 7px 0 0;
}
.fmt-cert-card-name {
  font-size: 11px; font-weight: 600; color: var(--cl-text-secondary, #6b6b6b);
  text-align: center; line-height: 1.2;
  word-break: break-word;
  max-width: 110px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

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
