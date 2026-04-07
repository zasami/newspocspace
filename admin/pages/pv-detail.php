<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$pvDetailId = $_GET['id'] ?? null;
$pvDetailData = null;
if ($pvDetailId) {
    $pvDetailData = Db::fetch(
        "SELECT pv.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                f.code AS fonction_code, f.nom AS fonction_nom,
                m.code AS module_code, m.nom AS module_nom,
                e.code AS etage_code, e.nom AS etage_nom
         FROM pv
         LEFT JOIN users u ON u.id = pv.created_by
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN modules m ON m.id = pv.module_id
         LEFT JOIN etages e ON e.id = pv.etage_id
         WHERE pv.id = ?",
        [$pvDetailId]
    );
    if ($pvDetailData) {
        $pvDetailData['participants'] = !empty($pvDetailData['participants']) ? json_decode($pvDetailData['participants'], true) : [];
        $pvDetailData['tags'] = !empty($pvDetailData['tags']) ? json_decode($pvDetailData['tags'], true) : [];
    }
}
?>
<!-- PV Detail Page -->
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">

<style>
.pv-editor-card { display: flex; flex-direction: column; overflow: hidden; }
.pv-editor-card .card-body { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0 !important; }
.pv-editor-card .card-body #pvEditorContainer { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
#pvEditorContainer .zs-editor-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
#pvEditorContainer .zs-ed-content { flex: 1; overflow-y: auto; max-height: none; padding: 1rem; }
#pvEditorContainer .zs-ed-content .ProseMirror { min-height: 100%; outline: none; }

/* Theme buttons */
.pv-btn-primary {
  background-color: var(--cl-accent, #191918);
  border: none; color: #fff; font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px);
  transition: all 0.2s;
}
.pv-btn-primary:hover { background-color: var(--cl-accent-hover, #000); color: #fff; }

.pv-btn-secondary {
  background-color: var(--cl-sidebar-hover, #F0EDE8);
  border: 1px solid var(--cl-border, #E8E5E0);
  color: var(--cl-text, #1A1A1A); font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px);
  transition: all 0.2s;
}
.pv-btn-secondary:hover { background-color: var(--cl-sidebar-active, #EDE8E0); color: var(--cl-text, #1A1A1A); }

.pv-btn-delete {
  background: transparent; color: var(--cl-text-secondary, #6B6B6B);
  border: 1px solid var(--cl-border, #E8E5E0); font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px);
  transition: all 0.2s;
}
.pv-btn-delete:hover { background: #F5E6E0; color: #9B2C2C; border-color: #E2B8AE; }

/* Badges */
.pv-badge-finalise { background-color: #bcd2cb !important; color: #2d4a43 !important; }
.pv-badge-enregistrement { background-color: #E8E5E0; color: var(--cl-text, #1A1A1A); font-weight: 600; }
.pv-badge-brouillon { background-color: #F0EDE8; color: var(--cl-text-secondary, #6B6B6B); }
.pv-badge-en_validation { background-color: #FFF3CD; color: #856404; font-weight: 600; }

/* Confirm modal */
.pv-confirm-icon {
  width: 48px; height: 48px; border-radius: 50%;
  background: #F5E6E0; color: #9B2C2C;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem; font-size: 1.3rem;
}
.pv-btn-confirm-del {
  background: #9B2C2C; border: none; color: #fff; font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-confirm-del:hover { background: #7B1F1F; color: #fff; }

/* ─── Blur on editor during AI structuration ─── */
.pv-editor-blur .zs-ed-content { filter: blur(6px); pointer-events: none; transition: filter 0.4s ease; }

/* Cancel & toggle buttons */
.pv-btn-cancel {
  background: transparent; border: 1px solid #c0392b; color: #c0392b;
  border-radius: 6px; padding: 2px 10px; font-size: .78rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.pv-btn-cancel:hover { background: #c0392b; color: #fff; }
.pv-btn-toggle-original {
  font-size: .78rem; padding: 3px 10px; border-radius: 6px;
  background: #E8E5DC; color: #5B4B3B; border: 1px solid #D4C4A8;
  cursor: pointer; transition: all 0.2s; font-weight: 500;
}
.pv-btn-toggle-original:hover { background: #D4C4A8; }
.pv-btn-toggle-original.active { background: #5B4B6B; color: #fff; border-color: #5B4B6B; }

/* Email recipient cards */
.pv-recipient-card {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px; border-radius: 8px; border: 2px solid transparent;
  cursor: pointer; transition: all 0.15s ease; background: #fff;
  user-select: none;
}
.pv-recipient-card:hover { background: #F5F3EE; border-color: #D4C4A8; }
.pv-recipient-card.selected { border-color: #191918; background: #FAFAF8; }
.pv-recipient-card .pv-rc-avatar {
  width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.pv-recipient-card .pv-rc-avatar-placeholder {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  background: #E8E5DC; display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .8rem; color: #5B4B3B; text-transform: uppercase;
}
.pv-recipient-card .pv-rc-info { flex: 1; min-width: 0; }
.pv-recipient-card .pv-rc-name { font-size: .85rem; font-weight: 600; color: #191918; }
.pv-recipient-card .pv-rc-fonction { font-size: .75rem; color: #999; font-weight: 400; }
.pv-recipient-card .pv-rc-check {
  width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s ease;
  opacity: 0; background: transparent; border: none;
}
.pv-recipient-card.selected .pv-rc-check {
  opacity: 1; background: #191918; color: #fff; border-radius: 50%;
}

/* Server status badge */
.srv-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px 5px 10px;
  border-radius: 20px;
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .02em;
  transition: all .4s cubic-bezier(.4,0,.2,1);
  cursor: default;
  user-select: none;
  position: relative;
  overflow: hidden;
}
.srv-badge::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  opacity: 0;
  transition: opacity .4s;
}
.srv-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  transition: all .4s cubic-bezier(.4,0,.2,1);
}
.srv-label { opacity: .7; }
.srv-status { transition: opacity .3s; }
.srv-badge[data-status="checking"] { background: #f0eeea; color: #8a8680; }
.srv-badge[data-status="checking"] .srv-dot { background: #bbb8b2; animation: srv-pulse-check 1.2s ease-in-out infinite; }
@keyframes srv-pulse-check { 0%,100%{opacity:.4;transform:scale(.85)} 50%{opacity:1;transform:scale(1.1)} }
.srv-badge[data-status="online"] { background: #e8f5e9; color: #2e7d32; }
.srv-badge[data-status="online"] .srv-dot { background: #4caf50; box-shadow: 0 0 0 3px rgba(76,175,80,.12); animation: srv-glow-on .6s ease-out forwards; }
@keyframes srv-glow-on { 0%{box-shadow:0 0 0 0 rgba(76,175,80,.5);transform:scale(.6)} 50%{box-shadow:0 0 0 6px rgba(76,175,80,.15);transform:scale(1.2)} 100%{box-shadow:0 0 0 3px rgba(76,175,80,.12);transform:scale(1)} }
.srv-badge[data-status="online"]::before { background:linear-gradient(90deg,transparent,rgba(76,175,80,.08),transparent); animation:srv-shine .8s ease-out; }
@keyframes srv-shine { 0%{opacity:1;transform:translateX(-100%)} 100%{opacity:0;transform:translateX(100%)} }
.srv-badge[data-status="offline"] { background: #ffeaea; color: #c62828; }
.srv-badge[data-status="offline"] .srv-dot { background: #ef5350; animation: srv-pulse-off 2s ease-in-out infinite; }
@keyframes srv-pulse-off { 0%,100%{opacity:.6} 50%{opacity:1} }
.srv-badge.srv-flash { animation: srv-status-change .5s ease-out; }
@keyframes srv-status-change { 0%{transform:scale(.95)} 40%{transform:scale(1.05)} 100%{transform:scale(1)} }

/* AI button */
.pv-btn-ia {
  background: #D0C4D8; color: #5B4B6B; border: none;
  font-weight: 600; border-radius: var(--cl-radius-sm, 8px);
  transition: all .2s;
}
.pv-btn-ia:hover { background: #C4B5D0; color: #4A3B5B; }
.pv-btn-ia:disabled { opacity: .6; cursor: not-allowed; }

/* ─── Utility: hidden ─── */
.pv-hidden { display: none !important; }
.pv-visible-inline { display: inline-block !important; }
.pv-visible-block { display: block !important; }
.pv-visible-flex { display: flex !important; }

/* ─── Reusable: settings button ─── */
.pv-btn-settings {
  background: #f3f2ee; color: var(--cl-text-secondary, #6B6B6B); font-weight: 500;
}

/* ─── Reusable: accent button ─── */
.pv-btn-accent {
  background: var(--cl-accent, #191918); color: #fff;
  font-weight: 600; border-radius: var(--cl-radius-sm, 8px);
}

/* ─── Reusable: validate button ─── */
.pv-btn-validate {
  background: #bcd2cb; color: #2d4a43; font-weight: 600; border-radius: 8px;
}

/* ─── Reusable: reject confirm button ─── */
.pv-btn-reject-confirm {
  background: #856404; color: #fff; font-weight: 600; border-radius: 8px;
}

/* ─── Reusable: modal header themed ─── */
.pv-modal-header {
  background: #F5F0E8; border-bottom: 2px solid #D4C9B5;
}

/* ─── Reusable: modal close button ─── */
.pv-modal-close {
  width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb;
}
.pv-modal-close .bi { font-size: .85rem; }

/* ─── Reusable: modal content styled ─── */
.pv-modal-content-styled {
  border-radius: var(--cl-radius, 16px); border: none;
  box-shadow: var(--cl-shadow-md);
}

/* ─── Reject icon ─── */
.pv-reject-icon {
  width: 48px; height: 48px; border-radius: 50%;
  background: #FFF3CD; color: #856404;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem; font-size: 1.3rem;
}

/* ─── Original text content ─── */
.pv-original-text {
  background: #FAFAF8; border: 1px solid #E8E5E0; border-radius: 8px;
  padding: 16px; max-height: 400px; overflow-y: auto;
  font-size: .9rem; line-height: 1.6; white-space: pre-wrap; user-select: text;
}

/* ─── Progress bar ─── */
.pv-progress-track { height: 6px; border-radius: 3px; background: #e8e5e0; }
.pv-progress-bar { width: 0%; background: #D0C4D8; border-radius: 3px; transition: width .5s linear; }
.pv-progress-footer { font-size: .72rem; color: #8a8680; }

/* ─── Editor container ─── */
.pv-editor-container { position: relative; }

/* ─── Status select ─── */
.pv-status-select { width: auto; min-width: 150px; font-size: .82rem; border-radius: 8px; }

/* ─── Audio player ─── */
.pv-audio-player { width: 100%; border-radius: 8px; }

/* ─── Email modal ─── */
.pv-email-recipients-badge {
  background: var(--cl-accent, #191918); font-size: .7rem; min-width: 22px;
}
.pv-email-search-wrap { background: #FAFAF8; }
.pv-email-search-bar { background: #fff; }
.pv-email-search-icon {
  left: 10px; top: 50%; transform: translateY(-50%);
  font-size: .78rem; color: #999;
}
.pv-email-search-input { padding-left: 30px; padding-right: 28px; font-size: .82rem; border-radius: 6px; }
.pv-email-search-clear {
  right: 4px; top: 50%; transform: translateY(-50%);
  width: 20px; height: 20px; padding: 0; border: none; background: transparent;
}
.pv-email-search-clear .bi { font-size: .68rem; color: #999; }
.pv-email-filter-select { width: auto; min-width: 100px; font-size: .82rem; border-radius: 6px; }
.pv-email-list { max-height: 200px; overflow-y: auto; }
.pv-email-textarea { font-size: .85rem; }
.pv-rc-check .bi { font-size: .85rem; }
.pv-custom-instructions { font-size: .85rem; }

/* ─── Format preview styles ─── */
.pv-fmt-preview-title { font-size: 1.05rem; font-weight: 700; }
.pv-fmt-preview-subtitle { font-size: 0.88rem; font-weight: 600; }
.pv-fmt-preview-bold { font-weight: 800; }
.pv-fmt-preview-italic { font-style: italic; }
.pv-fmt-preview-underline { text-decoration: underline; font-weight: 500; }
.pv-fmt-preview-list { align-items: flex-start; padding-left: 8px; }
.pv-fmt-preview-item { font-size: .78rem; }

/* ─── Opacity transition for srv-status ─── */
.pv-opacity-0 { opacity: 0; }
.pv-opacity-1 { opacity: 1; }

/* ─── Line placeholder widths ─── */
.pv-line-w35 { width: 35%; }
.pv-line-w40 { width: 40%; }
.pv-line-w45 { width: 45%; }
.pv-line-w50 { width: 50%; }
.pv-line-w55 { width: 55%; }
.pv-line-w60 { width: 60%; }
.pv-line-w70 { width: 70%; }
</style>

<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-file-earmark-text"></i> <span id="detailTitle">Chargement...</span>
    </h1>
    <div class="d-flex gap-2">
      <button class="btn btn-sm pv-btn-settings" id="btnPvSettingsDetail" title="Paramètres IA" data-bs-toggle="modal" data-bs-target="#modalPvSettings">
        <i class="bi bi-gear"></i> Paramètres
      </button>
      <a href="<?= admin_url('pv') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Liste
      </a>
    </div>
  </div>

  <div class="row pv-detail-row">
    <div class="col-lg-8">
      <!-- Content Editor -->
      <div class="card mb-4 w-100 pv-editor-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0">Contenu du PV</h6>
            <span class="srv-badge" id="badgeOllama" data-status="checking">
              <span class="srv-dot"></span>
              <span class="srv-label">Ollama</span>
              <span class="srv-status">…</span>
            </span>
          </div>
          <div class="d-flex gap-2">
            <button class="pv-btn-toggle-original pv-hidden" id="btnShowOriginal" title="Voir la transcription originale">
              <i class="bi bi-file-earmark-text me-1"></i>Original
            </button>
            <button class="btn btn-sm pv-btn-ia" id="btnStructureIA" disabled>
              <i class="bi bi-magic"></i> Restructurer avec l'IA
            </button>
            <button class="btn btn-sm pv-btn-primary" id="btnSaveContent">
              <i class="bi bi-check-lg"></i> Sauvegarder
            </button>
          </div>
        </div>
        <!-- Progress bar for IA structuring -->
        <div id="iaProgressWrap" class="px-3 pt-2 pb-1 pv-hidden">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span id="iaProgressLabel" class="small"><span class="spinner-border spinner-border-sm me-1"></span> Restructuration IA…</span>
            <div class="d-flex align-items-center gap-2">
              <span id="iaProgressTimer" class="text-muted small fw-semibold"></span>
              <button class="pv-btn-cancel pv-hidden" id="btnCancelIA" title="Annuler">
                <i class="bi bi-x-circle me-1"></i>Annuler
              </button>
            </div>
          </div>
          <div class="progress pv-progress-track">
            <div class="progress-bar pv-progress-bar" id="iaProgressBar"></div>
          </div>
          <div class="d-flex justify-content-between mt-1 pv-progress-footer">
            <span id="iaProgressElapsed">0:00</span>
            <span id="iaProgressEstimate"></span>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="pvEditorContainer" class="pv-editor-container"></div>
        </div>
      </div>
    </div>

    <!-- Side Info -->
    <div class="col-lg-4" id="pvSidebarCol">
      <!-- Info Card -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Informations</h6>
        </div>
        <div class="card-body small">
          <div class="mb-3">
            <label class="form-label fw-bold">Titre</label>
            <input type="text" class="form-control form-control-sm" id="detailTitleInput">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Description</label>
            <textarea class="form-control form-control-sm" id="detailDescription" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Statut</label>
            <div class="d-flex align-items-center gap-2">
              <span class="badge" id="detailStatus">—</span>
              <div class="zs-select pv-status-select" id="detailStatusSelect" data-placeholder="Statut"></div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Créateur</label>
            <div id="detailCreator">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Module</label>
            <div id="detailModule">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Créé le</label>
            <div id="detailDate">—</div>
          </div>
          <button class="btn btn-sm w-100 pv-btn-primary" id="btnUpdateInfo">
            <i class="bi bi-check-lg"></i> Mettre à jour les infos
          </button>
        </div>
      </div>

      <!-- Participants -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Participants</h6>
        </div>
        <div class="card-body small" id="detailParticipants">
          —
        </div>
      </div>

      <!-- Audio original -->
      <div class="card mb-4 pv-hidden" id="cardAudioOriginal">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-soundwave"></i> Audio original</h6>
        </div>
        <div class="card-body">
          <audio id="pvAudioPlayer" controls preload="metadata" class="pv-audio-player"></audio>
        </div>
      </div>

      <!-- Actions -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Actions</h6>
        </div>
        <div class="card-body">
          <button class="btn btn-sm w-100 mb-2 pv-btn-secondary" id="btnPrint">
            <i class="bi bi-printer"></i> Imprimer
          </button>
          <button class="btn btn-sm w-100 mb-2 pv-btn-secondary" id="btnExportWord">
            <i class="bi bi-file-earmark-word"></i> Exporter Word
          </button>
          <button class="btn btn-sm w-100 mb-2 pv-btn-secondary" id="btnExportPdf">
            <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
          </button>
          <hr class="my-2">
          <button class="btn btn-sm w-100 mb-2 pv-btn-secondary" id="btnReRecord">
            <i class="bi bi-arrow-repeat"></i> Re-enregistrer
          </button>
          <button class="btn btn-sm w-100 pv-btn-delete" id="btnDeletePv">
            <i class="bi bi-trash3"></i> Supprimer
          </button>
          <!-- Validation buttons (shown only for en_validation status) -->
          <div id="validationActions" class="mt-3 pt-3 border-top pv-hidden">
            <p class="small text-muted mb-2"><i class="bi bi-shield-check me-1"></i> Ce PV est en attente de validation</p>
            <button class="btn btn-sm w-100 mb-2 pv-btn-validate" id="btnValidatePv">
              <i class="bi bi-check-circle me-1"></i> Valider le PV
            </button>
            <button class="btn btn-sm w-100 btn-outline-secondary" id="btnRejectPv">
              <i class="bi bi-x-circle me-1"></i> Refuser
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Transcription Originale -->
<div class="modal fade" id="modalOriginalText" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header pv-modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Transcription originale</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2">Texte brut avant structuration par l'IA.</p>
        <div id="originalTextContent" class="pv-original-text"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" id="btnCopyOriginal"><i class="bi bi-clipboard me-1"></i>Copier tout</button>
        <button class="btn btn-sm btn-outline-secondary" id="btnCopySelection"><i class="bi bi-cursor-text me-1"></i>Copier la sélection</button>
        <button class="btn btn-sm pv-btn-accent" id="btnOpenSendEmail"><i class="bi bi-envelope me-1"></i>Envoyer par email</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Envoyer par email -->
<div class="modal fade" id="modalSendEmail" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Envoyer par email interne</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold d-flex align-items-center justify-content-between">
            <span>Destinataires *</span>
            <span id="emailRecipientsCount" class="badge rounded-pill pv-email-recipients-badge pv-hidden">0</span>
          </label>
          <div class="border rounded overflow-hidden pv-email-search-wrap">
            <div class="d-flex gap-2 p-2 border-bottom pv-email-search-bar">
              <div class="position-relative flex-grow-1">
                <i class="bi bi-search position-absolute pv-email-search-icon"></i>
                <input type="text" id="emailSearchInput" class="form-control form-control-sm pv-email-search-input" placeholder="Rechercher…">
                <button type="button" id="emailSearchClear" class="btn btn-sm position-absolute align-items-center justify-content-center pv-email-search-clear pv-hidden"><i class="bi bi-x-lg"></i></button>
              </div>
              <div class="zs-select pv-email-filter-select" id="emailFonctionFilter" data-placeholder="Tous"></div>
            </div>
            <div id="emailRecipientsList" class="p-2 d-flex flex-column gap-1 pv-email-list"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Sujet</label>
          <input type="text" class="form-control form-control-sm" id="emailSubject" value="">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Contenu</label>
          <textarea class="form-control form-control-sm pv-email-textarea" id="emailContent" rows="6"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm pv-btn-accent" id="btnSendEmail">
          <i class="bi bi-send me-1"></i>Envoyer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Refuser le PV -->
<div class="modal fade" id="modalRejectPv" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content pv-modal-content-styled">
      <div class="modal-body text-center py-4 px-4">
        <div class="pv-reject-icon">
          <i class="bi bi-x-circle"></i>
        </div>
        <h6 class="fw-bold mb-2">Refuser ce PV ?</h6>
        <textarea class="form-control form-control-sm mb-2" id="rejectMotif" rows="3" placeholder="Motif du refus (optionnel)..."></textarea>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm px-4 pv-btn-reject-confirm" id="btnConfirmReject">Refuser</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Delete -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content pv-modal-content-styled">
      <div class="modal-body text-center py-4 px-4">
        <div class="pv-confirm-icon">
          <i class="bi bi-trash3"></i>
        </div>
        <h6 class="fw-bold mb-2">Supprimer ce PV ?</h6>
        <p class="text-muted small mb-0">Cette action est irréversible. Le procès-verbal et toutes ses données seront définitivement supprimés.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm px-4 pv-btn-confirm-del" id="btnConfirmDeletePv">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Paramètres structuration IA -->
<style>
/* ── PV Settings Modal — Format Cards ── */
.pv-fmt-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
@media (max-width: 576px) { .pv-fmt-grid { grid-template-columns: repeat(2, 1fr); } }
.pv-fmt-card {
  border: 2px solid var(--cl-border, #E8E5E0);
  border-radius: 12px;
  padding: 12px 10px;
  cursor: pointer;
  text-align: center;
  transition: all 0.2s;
  position: relative;
  background: #fff;
  user-select: none;
}
.pv-fmt-card:hover { border-color: #bbb; }
.pv-fmt-card.active {
  border-color: var(--cl-accent, #191918);
  background: #fff;
  color: inherit;
}
.pv-fmt-card .pv-fmt-check {
  position: absolute; top: 6px; right: 6px;
  width: 18px; height: 18px; border-radius: 50%;
  background: var(--cl-accent, #191918); color: #fff;
  display: none; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700;
}
.pv-fmt-card.active .pv-fmt-check { display: flex; }
.pv-fmt-card .pv-fmt-preview {
  font-size: 0.82rem; line-height: 1.45;
  min-height: 42px; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 2px;
  pointer-events: none;
}
.pv-fmt-card .pv-fmt-label {
  font-size: 0.7rem; margin-top: 6px; opacity: 0.65; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.03em;
}
.pv-fmt-card.active .pv-fmt-label { opacity: 0.85; }
.pv-fmt-card .pv-fmt-preview .pv-line { background: #d0d0d0; height: 3px; border-radius: 2px; width: 70%; }

/* Section chips */
.pv-sec-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.pv-sec-chip {
  border: 2px solid var(--cl-border, #E8E5E0);
  border-radius: 20px;
  padding: 6px 14px;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 500;
  transition: all 0.2s;
  user-select: none;
  background: #fff;
  display: flex; align-items: center; gap: 6px;
}
.pv-sec-chip:hover { border-color: #bbb; }
.pv-sec-chip.active {
  border-color: #5B4B6B;
  background: #D0C4D8;
  color: #5B4B6B;
}
.pv-sec-chip .pv-chip-check {
  width: 14px; height: 14px; border-radius: 50%;
  border: 1.5px solid currentColor; display: flex;
  align-items: center; justify-content: center;
  font-size: 9px; transition: all 0.2s;
}
.pv-sec-chip.active .pv-chip-check {
  background: #5B4B6B; color: #fff; border-color: #5B4B6B;
}
</style>

<div class="modal fade" id="modalPvSettings" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header pv-modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-gear me-2"></i>Paramètres de structuration IA</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Choisissez comment l'IA doit structurer vos procès-verbaux.</p>

        <!-- Formatage — Visual cards -->
        <div class="mb-4">
          <label class="form-label small fw-semibold mb-2"><i class="bi bi-type-bold me-1"></i> Formatage du texte</label>
          <div class="pv-fmt-grid">
            <div class="pv-fmt-card active" data-opt="pvOptTitres">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-fmt-preview-title">Titre</span>
                <span class="pv-line pv-line-w50"></span>
              </div>
              <div class="pv-fmt-label">Titres</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptSousTitres">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-w60"></span>
                <span class="pv-fmt-preview-subtitle">Sous-titre</span>
                <span class="pv-line pv-line-w45"></span>
              </div>
              <div class="pv-fmt-label">Sous-titres</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptGras">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-w40"></span>
                <span class="pv-fmt-preview-bold">Texte gras</span>
                <span class="pv-line pv-line-w55"></span>
              </div>
              <div class="pv-fmt-label">Gras</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptItalique">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-w50"></span>
                <em class="pv-fmt-preview-italic">Question ?</em>
                <span class="pv-line pv-line-w35"></span>
              </div>
              <div class="pv-fmt-label">Italique</div>
            </div>
            <div class="pv-fmt-card" data-opt="pvOptSouligne">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-w45"></span>
                <span class="pv-fmt-preview-underline">M. Dupont</span>
                <span class="pv-line pv-line-w55"></span>
              </div>
              <div class="pv-fmt-label">Souligné</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptListes">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview pv-fmt-preview-list">
                <span class="pv-fmt-preview-item">&#8226; Premier point</span>
                <span class="pv-fmt-preview-item">&#8226; Deuxième point</span>
                <span class="pv-fmt-preview-item">&#8226; Troisième point</span>
              </div>
              <div class="pv-fmt-label">Listes à puces</div>
            </div>
            <div class="pv-fmt-card" data-opt="pvOptNumeros">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview pv-fmt-preview-list">
                <span class="pv-fmt-preview-item">1. Premier point</span>
                <span class="pv-fmt-preview-item">2. Deuxième point</span>
                <span class="pv-fmt-preview-item">3. Troisième point</span>
              </div>
              <div class="pv-fmt-label">Numérotées</div>
            </div>
          </div>
        </div>

        <!-- Sections — Chips -->
        <div class="mb-4">
          <label class="form-label small fw-semibold mb-2"><i class="bi bi-layout-text-sidebar me-1"></i> Sections du PV</label>
          <div class="pv-sec-grid">
            <div class="pv-sec-chip active" data-opt="pvOptSecPresents"><span class="pv-chip-check">✓</span> Présents / Absents</div>
            <div class="pv-sec-chip active" data-opt="pvOptSecOdj"><span class="pv-chip-check">✓</span> Ordre du jour</div>
            <div class="pv-sec-chip active" data-opt="pvOptSecPoints"><span class="pv-chip-check">✓</span> Points discutés</div>
            <div class="pv-sec-chip active" data-opt="pvOptSecDecisions"><span class="pv-chip-check">✓</span> Décisions prises</div>
            <div class="pv-sec-chip active" data-opt="pvOptSecActions"><span class="pv-chip-check">✓</span> Actions à mener</div>
            <div class="pv-sec-chip" data-opt="pvOptSecProchaine"><span class="pv-chip-check">✓</span> Prochaine séance</div>
          </div>
        </div>

        <!-- Niveau de détail -->
        <div class="mb-4">
          <label class="form-label small fw-semibold mb-2"><i class="bi bi-sliders me-1"></i> Niveau de détail</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="pvDetailLevel" value="80" id="pvDetail80" checked>
            <label class="btn btn-outline-secondary btn-sm flex-fill" for="pvDetail80">Détaillé (80%+)</label>
            <input type="radio" class="btn-check" name="pvDetailLevel" value="60" id="pvDetail60">
            <label class="btn btn-outline-secondary btn-sm flex-fill" for="pvDetail60">Modéré (60%)</label>
            <input type="radio" class="btn-check" name="pvDetailLevel" value="40" id="pvDetail40">
            <label class="btn btn-outline-secondary btn-sm flex-fill" for="pvDetail40">Résumé (40%)</label>
          </div>
        </div>

        <!-- Orthographe -->
        <div class="mb-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="pvOptOrtho" checked>
            <label class="form-check-label small" for="pvOptOrtho"><i class="bi bi-spellcheck me-1"></i> Corriger les fautes d'orthographe et de grammaire</label>
          </div>
        </div>

        <!-- Instructions personnalisées -->
        <div class="mb-0">
          <label class="form-label small fw-semibold mb-1"><i class="bi bi-chat-text me-1"></i> Instructions personnalisées</label>
          <textarea class="form-control pv-custom-instructions" id="pvCustomInstructions" rows="3" placeholder="Ex: Mettre les noms des participants en gras, ajouter les horaires en début de chaque point, utiliser un ton formel..."></textarea>
          <div class="form-text">Ces instructions seront ajoutées au prompt envoyé à l'IA.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm pv-btn-accent" id="btnSavePvSettings">
          <i class="bi bi-check-lg me-1"></i>Enregistrer
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
let editorModule = null;
let editorInstance = null;
let pvId = null;
let pvData = null;
const ssrPvData = <?= $pvDetailData ? json_encode($pvDetailData, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;

async function initPvdetailPage() {
  editorModule = await import('/spocspace/assets/js/rich-editor.js');

  pvId = AdminURL.currentId();
  if (!pvId) {
    toast('PV non trouvé');
    window.history.back();
    return;
  }

  if (!ssrPvData) {
    toast('PV non trouvé');
    window.history.back();
    return;
  }
  pvData = ssrPvData;

  // Si contenu sauvegardé dans localStorage (rechargement pendant blur), le restaurer
  const lsSaved = localStorage.getItem('ss_pv_blur_saved');
  const editorContent = lsSaved || pvData.contenu || pvData.transcription_brute || '';
  if (lsSaved) localStorage.removeItem('ss_pv_blur_saved');
  editorInstance = await editorModule.createEditor(document.getElementById('pvEditorContainer'), {
    placeholder: 'Contenu du PV...',
    content: editorContent,
    mode: 'full'
  });

  // Si le PV a une transcription brute, afficher le bouton toggle
  if (pvData.transcription_brute) {
      _originalTranscription = pvData.transcription_brute;
      document.getElementById('btnShowOriginal').classList.remove('pv-hidden');
  }

  // Init status zerdaSelect
  const statusOpts = [
    { value: 'brouillon', label: 'Brouillon' },
    { value: 'enregistrement', label: 'Enregistrement' },
    { value: 'en_validation', label: 'En validation' },
    { value: 'finalisé', label: 'Finalisé' },
  ];
  zerdaSelect.init(document.getElementById('detailStatusSelect'), statusOpts, {
    onSelect: (val) => changeStatus(val),
    value: pvData.statut || 'brouillon',
    search: false
  });

  loadPvDetail();

  // Show audio player if audio exists
  if (pvData.audio_path) {
    var audioCard = document.getElementById('cardAudioOriginal');
    var audioPlayer = document.getElementById('pvAudioPlayer');
    audioCard.classList.remove('pv-hidden');
    audioPlayer.src = '/spocspace/admin/api.php?action=admin_serve_pv_audio&id=' + pvId + '&t=' + Date.now();
  }

  // Button handlers
  document.getElementById('btnSaveContent').addEventListener('click', saveContent);
  document.getElementById('btnUpdateInfo').addEventListener('click', updateInfo);
  document.getElementById('btnReRecord').addEventListener('click', function() {
    window.location.href = AdminURL.page('pv-record', pvId);
  });
  document.getElementById('btnDeletePv').addEventListener('click', showDeleteConfirm);

  // Synchroniser la hauteur de l'éditeur avec la sidebar droite
  requestAnimationFrame(syncEditorHeight);
  window.addEventListener('resize', syncEditorHeight);
}

function syncEditorHeight() {
  const sidebar = document.getElementById('pvSidebarCol');
  const editorCard = document.querySelector('.pv-editor-card');
  if (!sidebar || !editorCard) return;
  // Calculer la hauteur totale des cards dans la sidebar
  let totalH = 0;
  sidebar.querySelectorAll(':scope > .card, :scope > .card.mb-4').forEach(card => {
    const rect = card.getBoundingClientRect();
    const style = getComputedStyle(card);
    totalH += rect.height + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
  });
  if (totalH > 200) {
    editorCard.style.height = totalH + 'px';
    editorCard.style.maxHeight = totalH + 'px';
  }
}

function loadPvDetail() {
  document.getElementById('detailTitle').textContent = pvData.titre;
  document.getElementById('detailTitleInput').value = pvData.titre;
  document.getElementById('detailDescription').value = pvData.description || '';

  var statusEl = document.getElementById('detailStatus');
  updateStatusBadge(pvData.statut);
  zerdaSelect.setValue(document.getElementById('detailStatusSelect'), pvData.statut);

  // Show validation actions if PV is awaiting validation
  if (pvData.statut === 'en_validation') {
      document.getElementById('validationActions').classList.remove('pv-hidden');
  }

  document.getElementById('detailCreator').textContent = (pvData.creator_prenom || '?') + ' ' + (pvData.creator_nom || '');
  document.getElementById('detailModule').textContent = pvData.module_nom ? pvData.module_nom + ' (' + pvData.module_code + ')' : '—';
  document.getElementById('detailDate').textContent = new Date(pvData.created_at).toLocaleString('fr-FR');

  // Participants
  var participants = pvData.participants || [];
  if (participants.length > 0) {
    document.getElementById('detailParticipants').innerHTML =
      participants.map(function(p) { return '<div class="mb-2"><i class="bi bi-person-fill"></i> ' + escapeHtml(p.prenom) + ' ' + escapeHtml(p.nom) + '</div>'; }).join('');
  }
}

async function saveContent() {
  var content = editorInstance && editorModule ? editorModule.getHTML(editorInstance) : '';
  var btn = document.getElementById('btnSaveContent');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

  try {
    var r = await adminApiPost('admin_update_pv', {
      id: pvId,
      contenu: content,
    });

    if (r.success) {
      toast('Contenu sauvegardé');
      pvData.contenu = content;
    } else {
      toast(r.message || 'Erreur');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Sauvegarder';
  }
}

function updateStatusBadge(statut) {
    const statusEl = document.getElementById('detailStatus');
    const labels = { brouillon: 'Brouillon', enregistrement: 'Enregistrement', en_validation: 'En validation', 'finalisé': 'Finalisé' };
    statusEl.textContent = labels[statut] || statut;
    if (statut === 'finalisé') statusEl.className = 'badge pv-badge-finalise';
    else if (statut === 'en_validation') statusEl.className = 'badge pv-badge-en_validation';
    else if (statut === 'enregistrement') statusEl.className = 'badge pv-badge-enregistrement';
    else statusEl.className = 'badge pv-badge-brouillon';

    document.getElementById('validationActions').classList.toggle('pv-hidden', statut !== 'en_validation');
}

async function changeStatus(newStatut) {
    const r = await adminApiPost('admin_update_pv', { id: pvId, statut: newStatut });
    if (r.success) {
        pvData.statut = newStatut;
        updateStatusBadge(newStatut);
        toast('Statut mis à jour');
    } else {
        toast(r.message || 'Erreur', 'error');
        zerdaSelect.setValue(document.getElementById('detailStatusSelect'), pvData.statut);
    }
}

async function updateInfo() {
  var btn = document.getElementById('btnUpdateInfo');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

  try {
    var r = await adminApiPost('admin_update_pv', {
      id: pvId,
      titre: document.getElementById('detailTitleInput').value,
      description: document.getElementById('detailDescription').value,
    });

    if (r.success) {
      toast('Infos mises à jour');
      pvData.titre = document.getElementById('detailTitleInput').value;
      pvData.description = document.getElementById('detailDescription').value;
      loadPvDetail();
    } else {
      toast(r.message || 'Erreur');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Mettre à jour les infos';
  }
}

// Delete confirm modal
var modalConfirmDelete = null;

function showDeleteConfirm() {
  if (!modalConfirmDelete) {
    modalConfirmDelete = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));
    document.getElementById('btnConfirmDeletePv').addEventListener('click', doDeletePv);
  }
  modalConfirmDelete.show();
}

async function doDeletePv() {
  var btn = document.getElementById('btnConfirmDeletePv');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';

  var r = await adminApiPost('admin_delete_pv', { id: pvId });
  if (r.success) {
    toast('PV supprimé');
    modalConfirmDelete.hide();
    window.location.href = AdminURL.page('pv');
  } else {
    toast(r.message || 'Erreur');
    btn.disabled = false;
    btn.textContent = 'Supprimer';
  }
}

// ── Ollama monitoring + IA restructure ──
const OLLAMA_URL = 'http://localhost:11434';
let ollamaOnline = false;
let ollamaModel = 'gemma3:4b'; // loaded from config

function updateBadge(id, status) {
    const badge = document.getElementById(id);
    if (!badge) return;
    const prev = badge.dataset.status;
    if (prev === status) return;
    badge.dataset.status = status;
    const statusEl = badge.querySelector('.srv-status');
    if (statusEl) {
        statusEl.classList.add('pv-opacity-0');
        statusEl.classList.remove('pv-opacity-1');
        setTimeout(() => {
            statusEl.textContent = status === 'online' ? 'Connecté' : status === 'offline' ? 'Hors ligne' : '…';
            statusEl.classList.remove('pv-opacity-0');
            statusEl.classList.add('pv-opacity-1');
        }, 150);
    }
    if (prev && prev !== 'checking') {
        badge.classList.remove('srv-flash');
        void badge.offsetWidth;
        badge.classList.add('srv-flash');
    }
}

async function checkOllama() {
    try {
        const r = await fetch(OLLAMA_URL + '/api/tags', { signal: AbortSignal.timeout(3000) });
        ollamaOnline = r.ok;
        updateBadge('badgeOllama', r.ok ? 'online' : 'offline');
    } catch {
        ollamaOnline = false;
        updateBadge('badgeOllama', 'offline');
    }
    const btn = document.getElementById('btnStructureIA');
    if (btn) btn.disabled = !ollamaOnline;
}

// ── Paramètres de structuration IA ──
const pvSettingsDefaults = {
    titres: true, sousTitres: true, gras: true, italique: true,
    souligne: false, listes: true, numeros: false,
    secPresents: true, secOdj: true, secPoints: true,
    secDecisions: true, secActions: true, secProchaine: false,
    detailLevel: '80', ortho: true, customInstructions: ''
};
let pvSettings = { ...pvSettingsDefaults };

function loadPvSettingsToUI() {
    // Format cards + section chips: toggle .active based on settings
    document.querySelectorAll('.pv-fmt-card[data-opt], .pv-sec-chip[data-opt]').forEach(el => {
        const optId = el.dataset.opt;
        // Map data-opt to settings key: pvOptTitres -> titres, pvOptSecPresents -> secPresents
        const key = optId.replace('pvOpt', '').replace(/^(.)/, (m,c) => c.toLowerCase());
        el.classList.toggle('active', !!pvSettings[key]);
    });
    // Ortho checkbox
    const orthoEl = document.getElementById('pvOptOrtho');
    if (orthoEl) orthoEl.checked = !!pvSettings.ortho;
    document.getElementById('pvCustomInstructions').value = pvSettings.customInstructions || '';
    const radio = document.getElementById('pvDetail' + pvSettings.detailLevel);
    if (radio) radio.checked = true;
}

function readPvSettingsFromUI() {
    document.querySelectorAll('.pv-fmt-card[data-opt], .pv-sec-chip[data-opt]').forEach(el => {
        const optId = el.dataset.opt;
        const key = optId.replace('pvOpt', '').replace(/^(.)/, (m,c) => c.toLowerCase());
        pvSettings[key] = el.classList.contains('active');
    });
    const orthoEl = document.getElementById('pvOptOrtho');
    if (orthoEl) pvSettings.ortho = orthoEl.checked;
    pvSettings.customInstructions = document.getElementById('pvCustomInstructions').value.trim();
    pvSettings.detailLevel = document.querySelector('input[name="pvDetailLevel"]:checked')?.value || '80';
}

async function savePvSettings() {
    readPvSettingsFromUI();
    const btn = document.getElementById('btnSavePvSettings');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const values = { pv_structure_options: JSON.stringify(pvSettings) };
        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) toast('Paramètres IA enregistrés');
        else toast(res.message || 'Erreur', 'error');
    } catch (e) { toast('Erreur: ' + e.message, 'error'); }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer';
    bootstrap.Modal.getInstance(document.getElementById('modalPvSettings'))?.hide();
}

function buildStructurePrompt(rawText) {
    const s = pvSettings;
    let rules = [];
    let tags = [];
    if (s.titres) tags.push('<h2> pour le titre principal');
    if (s.sousTitres) tags.push('<h3> pour les sous-sections');
    tags.push('<p> pour les paragraphes');
    if (s.gras) tags.push('<strong> pour les points importants');
    if (s.italique) tags.push('<em> pour les questions ou éléments à distinguer');
    if (s.souligne) tags.push('<u> pour les noms propres et les dates clés');
    if (s.listes) tags.push('<ul><li> pour les listes');
    if (s.numeros) tags.push('<ol><li> pour les listes numérotées');
    rules.push('Utilise ces balises HTML : ' + tags.join(', '));

    let sections = [];
    if (s.secPresents) sections.push('Présents / Absents');
    if (s.secOdj) sections.push('Ordre du jour');
    if (s.secPoints) sections.push('Points discutés');
    if (s.secDecisions) sections.push('Décisions prises');
    if (s.secActions) sections.push('Actions à mener');
    if (s.secProchaine) sections.push('Prochaine séance');
    if (sections.length > 0) rules.push('Sections attendues : ' + sections.join(', '));

    const pct = parseInt(s.detailLevel) || 80;
    if (pct >= 70) rules.push(`NE RÉSUME PAS : conserve au minimum ${pct}% du contenu original. Chaque point discuté doit être développé, pas juste mentionné`);
    else if (pct >= 50) rules.push(`Conserve environ ${pct}% du contenu. Résume les points secondaires mais développe les décisions et actions`);
    else rules.push(`Fais un résumé concis (environ ${pct}% du contenu). Garde uniquement l'essentiel : décisions, actions, points clés`);

    if (s.italique) rules.push('Les questions doivent être en <em>italique</em>, suivies immédiatement de la réponse en texte normal dessous');
    if (s.ortho) rules.push("Corrige les fautes d'orthographe et de grammaire");
    rules.push('Garde le sens exact et les détails, ne modifie pas le fond');
    rules.push('Ne mets PAS de balises <html>, <head>, <body> — juste le contenu HTML direct');
    rules.push('Réponds UNIQUEMENT avec le HTML structuré, sans commentaire ni explication');

    let customBlock = '';
    if (s.customInstructions) customBlock = `\n\nInstructions supplémentaires de l'utilisateur :\n${s.customInstructions}`;

    return `Tu es un assistant pour un EMS (établissement médico-social) à Genève.
Voici le contenu d'un procès-verbal. Restructure ce texte en un PV professionnel en HTML.

Règles IMPORTANTES :
${rules.map(r => '- ' + r).join('\n')}${customBlock}

Texte du PV :
${rawText}`;
}

// ── Progress helpers ──
let _iaProgressInterval = null;
let _iaProgressStart = 0;

function _fmtTime(sec) {
    return Math.floor(sec / 60) + ':' + String(Math.floor(sec % 60)).padStart(2, '0');
}

// ── Variables pour annulation et transcription originale ──
let _iaAbortController = null;
let _originalTranscription = null;
let _structuredContent = null;
let _showingOriginal = false;

const _twNodes = [
    '<h2>Procès-verbal — Réunion d\'équipe</h2>',
    '<p><strong>Date :</strong> <em>26 mars 2026</em> — <strong>Participants :</strong> Direction, Responsables</p>',
    '<h3>1. Ordre du jour</h3>',
    '<ul><li>Revue des <strong>objectifs trimestriels</strong></li><li>Suivi des <em>indicateurs qualité</em></li><li>Points divers et planification</li></ul>',
    '<h3>2. Discussions et décisions</h3>',
    '<p>La direction a présenté les <strong>résultats du trimestre</strong> en soulignant une <em>amélioration significative</em> de la couverture des soins sur l\'ensemble des unités.</p>',
    '<p>Il a été <strong>décidé</strong> de reconduire le dispositif de <em>remplacement mutualisé</em> entre les différents étages pour le prochain mois.</p>',
    '<h3>3. Plan d\'action</h3>',
    '<ul><li><strong>Responsable RH</strong> : finaliser le <em>planning prévisionnel</em></li><li><strong>Infirmière chef</strong> : organiser la formation continue</li><li>Prochaine réunion fixée au <em>15 avril 2026</em></li></ul>',
    '<h3>4. Remarques</h3>',
    '<p>L\'équipe a souligné l\'importance de maintenir une <strong>communication régulière</strong> et de <em>documenter chaque décision</em> pour assurer le suivi.</p>',
];
let _twTimer = null;
let _twIdx = 0;
const _LS_KEY = 'ss_pv_blur_saved';

function _startEditorBlur() {
    const container = document.getElementById('pvEditorContainer');
    if (!container || !editorInstance) return;

    localStorage.setItem(_LS_KEY, editorInstance.getHTML());
    editorInstance.commands.setContent('');

    container.classList.add('pv-editor-blur');
    _twIdx = 0;

    function addNextNode() {
        if (!editorInstance) return;
        if (_twIdx >= _twNodes.length) _twIdx = 0;
        editorInstance.commands.insertContent(_twNodes[_twIdx]);
        _twIdx++;
        const edContent = container.querySelector('.zs-ed-content');
        if (edContent) edContent.scrollTop = edContent.scrollHeight;
    }

    addNextNode();
    _twTimer = setInterval(addNextNode, 1500);
}

function _stopEditorBlur() {
    clearInterval(_twTimer); _twTimer = null; _twIdx = 0;
    const container = document.getElementById('pvEditorContainer');
    if (!container) return;
    container.classList.remove('pv-editor-blur');
    localStorage.removeItem(_LS_KEY);
}

function _restoreSavedContent() {
    const saved = localStorage.getItem(_LS_KEY);
    if (saved && editorInstance) editorInstance.commands.setContent(saved);
    localStorage.removeItem(_LS_KEY);
}

function startIaProgress(label, estimatedSec) {
    const wrap = document.getElementById('iaProgressWrap');
    const bar = document.getElementById('iaProgressBar');
    const elapsedEl = document.getElementById('iaProgressElapsed');
    const estimateEl = document.getElementById('iaProgressEstimate');
    const timerEl = document.getElementById('iaProgressTimer');
    const labelEl = document.getElementById('iaProgressLabel');
    const cancelBtn = document.getElementById('btnCancelIA');

    wrap.classList.remove('pv-hidden');
    labelEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + label;
    bar.style.width = '0%';
    elapsedEl.textContent = '0:00';
    estimateEl.textContent = estimatedSec > 0 ? '~' + _fmtTime(estimatedSec) + ' estimé' : '';
    timerEl.textContent = '';
    if (cancelBtn) cancelBtn.classList.toggle('pv-hidden', !_iaAbortController);

    _iaProgressStart = Date.now();
    clearInterval(_iaProgressInterval);
    _iaProgressInterval = setInterval(() => {
        const elapsed = (Date.now() - _iaProgressStart) / 1000;
        elapsedEl.textContent = _fmtTime(elapsed);
        if (estimatedSec > 0) {
            const pct = Math.min(95, (elapsed / estimatedSec) * 100);
            bar.style.width = pct + '%';
            const remaining = Math.max(0, estimatedSec - elapsed);
            timerEl.textContent = remaining > 0 ? '~' + _fmtTime(remaining) + ' restant' : 'presque terminé…';
        } else {
            bar.style.width = Math.min(90, elapsed * 2) + '%';
            timerEl.textContent = _fmtTime(elapsed);
        }
    }, 500);
}

function stopIaProgress() {
    clearInterval(_iaProgressInterval);
    _iaAbortController = null;
    const bar = document.getElementById('iaProgressBar');
    const cancelBtn = document.getElementById('btnCancelIA');
    if (bar) bar.style.width = '100%';
    if (cancelBtn) cancelBtn.classList.add('pv-hidden');
    setTimeout(() => {
        const wrap = document.getElementById('iaProgressWrap');
        if (wrap) wrap.classList.add('pv-hidden');
        if (bar) bar.style.width = '0%';
    }, 600);
}

async function structureWithIA() {
    if (!editorInstance || !editorModule) return;
    const rawText = editorModule.getHTML(editorInstance).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();

    if (rawText.length < 30) {
        toast('Pas assez de texte à restructurer.', 'error');
        return;
    }

    if (!ollamaOnline) {
        toast('Ollama hors ligne — lancez Ollama sur votre poste.', 'error');
        return;
    }

    const btn = document.getElementById('btnStructureIA');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Restructuration...';

    // Sauvegarder la transcription originale
    const originalHtml = editorModule.getHTML(editorInstance);
    _originalTranscription = originalHtml;
    _showingOriginal = false;
    _structuredContent = null;

    // Sauvegarder en DB
    if (pvId) {
        adminApiPost('admin_update_pv', { id: pvId, transcription_brute: originalHtml }).catch(() => {});
    }

    // AbortController
    _iaAbortController = new AbortController();
    const signal = _iaAbortController.signal;

    const estSec = Math.max(10, Math.ceil(rawText.length / 1000 * 15));
    startIaProgress('Restructuration IA en cours…', estSec);
    _startEditorBlur();

    const prompt = buildStructurePrompt(rawText);

    try {
        const res = await fetch(OLLAMA_URL + '/api/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ model: ollamaModel, prompt, stream: false }),
            signal,
        });

        if (!res.ok) throw new Error('Erreur Ollama: ' + res.status);
        const data = await res.json();

        if (data.response) {
            let html = data.response.trim();
            html = html.replace(/^```html?\s*/i, '').replace(/\s*```$/i, '');
            editorInstance.commands.setContent(html);
            _structuredContent = html;
            toast('PV restructuré avec succès !');
            document.getElementById('btnShowOriginal').classList.remove('pv-hidden');
        } else {
            throw new Error('Réponse vide');
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        _restoreSavedContent();
        console.error('[Ollama]', e);
        toast('Erreur de restructuration: ' + e.message, 'error');
    } finally {
        stopIaProgress();
        _stopEditorBlur();
        btn.disabled = !ollamaOnline;
        btn.innerHTML = '<i class="bi bi-magic"></i> Restructurer avec l\'IA';
    }
}

function _cancelIA() {
    if (_iaAbortController) { _iaAbortController.abort(); _iaAbortController = null; }
    stopIaProgress();
    _restoreSavedContent();
    _stopEditorBlur();
    const btn = document.getElementById('btnStructureIA');
    if (btn) { btn.disabled = !ollamaOnline; btn.innerHTML = '<i class="bi bi-magic"></i> Restructurer avec l\'IA'; }
    toast('Opération annulée', 'info');
}

// _toggleOriginal replaced by modal approach (btnShowOriginal → modalOriginalText)

// Init monitoring after page load
const origInit = initPvdetailPage;
initPvdetailPage = async function() {
    // Load config (Ollama model + PV settings)
    try {
        const cfgRes = await adminApiPost('admin_get_config');
        if (cfgRes.config?.ollama_model) ollamaModel = cfgRes.config.ollama_model;
        if (cfgRes.config?.pv_structure_options) {
            try { pvSettings = { ...pvSettingsDefaults, ...JSON.parse(cfgRes.config.pv_structure_options) }; } catch {}
        }
        loadPvSettingsToUI();
    } catch {}

    document.getElementById('btnSavePvSettings')?.addEventListener('click', savePvSettings);

    // Card/chip toggle click handlers
    document.querySelectorAll('.pv-fmt-card[data-opt], .pv-sec-chip[data-opt]').forEach(el => {
        el.addEventListener('click', () => el.classList.toggle('active'));
    });

    await origInit();
    document.getElementById('btnStructureIA')?.addEventListener('click', structureWithIA);
    document.getElementById('btnCancelIA')?.addEventListener('click', _cancelIA);

    // Modal transcription originale
    document.getElementById('btnShowOriginal')?.addEventListener('click', () => {
        if (!_originalTranscription) return;
        document.getElementById('originalTextContent').innerHTML = _originalTranscription;
        new bootstrap.Modal(document.getElementById('modalOriginalText')).show();
    });
    document.getElementById('btnCopyOriginal')?.addEventListener('click', () => {
        const text = document.getElementById('originalTextContent').innerText;
        navigator.clipboard.writeText(text).then(() => toast('Texte copié !')).catch(() => toast('Erreur', 'error'));
    });
    document.getElementById('btnCopySelection')?.addEventListener('click', () => {
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed) { toast('Sélectionnez du texte', 'error'); return; }
        navigator.clipboard.writeText(sel.toString()).then(() => toast('Sélection copiée !'));
    });

    // Email
    document.getElementById('btnOpenSendEmail')?.addEventListener('click', openSendEmailModal);
    document.getElementById('btnSendEmail')?.addEventListener('click', sendPvEmail);

    // Validation
    document.getElementById('btnValidatePv')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnValidatePv');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Validation...';
        const r = await adminApiPost('admin_validate_pv', { id: pvId });
        if (r.success) {
            toast('PV validé !');
            document.getElementById('detailStatus').textContent = 'finalisé';
            document.getElementById('detailStatus').className = 'badge pv-badge-finalise';
            document.getElementById('validationActions').classList.add('pv-hidden');
        } else toast(r.message || 'Erreur', 'error');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Valider le PV';
    });
    document.getElementById('btnRejectPv')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('modalRejectPv')).show();
    });
    document.getElementById('btnConfirmReject')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnConfirmReject');
        btn.disabled = true;
        const motif = document.getElementById('rejectMotif').value.trim();
        const r = await adminApiPost('admin_reject_pv', { id: pvId, motif });
        if (r.success) {
            toast('PV refusé');
            document.getElementById('detailStatus').textContent = 'brouillon';
            document.getElementById('detailStatus').className = 'badge pv-badge-brouillon';
            document.getElementById('validationActions').classList.add('pv-hidden');
            bootstrap.Modal.getInstance(document.getElementById('modalRejectPv'))?.hide();
        } else toast(r.message || 'Erreur', 'error');
        btn.disabled = false;
    });

    checkOllama();
    setInterval(checkOllama, 8000);
};

// ── Email functions ──
let _emailUsers = [];
let _emailFonctions = [];
let _emailSelectedIds = new Set();
let _emailFiltersBound = false;

function openSendEmailModal() {
    const container = document.getElementById('originalTextContent');
    const text = container ? (container.innerText || container.textContent) : '';
    const titre = pvData?.titre || 'PV';
    document.getElementById('emailSubject').value = 'Transcription originale — ' + titre;
    document.getElementById('emailContent').value = text;

    document.getElementById('emailSearchInput').value = '';
    document.getElementById('emailSearchClear').classList.add('pv-hidden');
    const emailFonctionEl = document.getElementById('emailFonctionFilter');
    if (emailFonctionEl._zsInit) zerdaSelect.setValue(emailFonctionEl, '');

    if (_emailUsers.length === 0) {
        adminApiPost('admin_get_pv_refs').then(r => {
            if (r.success) {
                _emailUsers = r.users || [];
                _emailFonctions = r.fonctions || [];
                const participants = pvData?.participants || [];
                participants.forEach(p => _emailSelectedIds.add(typeof p === 'string' ? p : p.id));
                populateFonctionFilter();
                renderEmailRecipients();
                bindEmailFilterEvents();
            }
        });
    } else {
        renderEmailRecipients();
    }

    bootstrap.Modal.getInstance(document.getElementById('modalOriginalText'))?.hide();
    setTimeout(() => new bootstrap.Modal(document.getElementById('modalSendEmail')).show(), 300);
}

function populateFonctionFilter() {
    const sel = document.getElementById('emailFonctionFilter');
    const opts = [{ value: '', label: 'Tous' }].concat(_emailFonctions.map(f => ({ value: f.nom, label: f.nom })));
    zerdaSelect.init(sel, opts, { onSelect: () => renderEmailRecipients(), value: '', search: false, width: 'auto' });
    sel._zsInit = true;
}

function bindEmailFilterEvents() {
    if (_emailFiltersBound) return;
    _emailFiltersBound = true;
    const searchInput = document.getElementById('emailSearchInput');
    const clearBtn = document.getElementById('emailSearchClear');

    searchInput.addEventListener('input', () => {
        clearBtn.classList.toggle('pv-hidden', searchInput.value.length === 0);
        renderEmailRecipients();
    });
    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearBtn.classList.add('pv-hidden');
        renderEmailRecipients();
        searchInput.focus();
    });
    // fonctionSel change is handled by zerdaSelect onSelect in populateFonctionFilter()
}

function renderEmailRecipients() {
    const list = document.getElementById('emailRecipientsList');
    const query = (document.getElementById('emailSearchInput').value || '').toLowerCase().trim();
    const fonctionFilter = zerdaSelect.getValue('#emailFonctionFilter');

    const filtered = _emailUsers.filter(u => {
        if (fonctionFilter && u.fonction_nom !== fonctionFilter) return false;
        if (query) {
            const full = `${u.prenom} ${u.nom} ${u.fonction_nom || ''}`.toLowerCase();
            if (!full.includes(query)) return false;
        }
        return true;
    });

    list.innerHTML = filtered.map(u => {
        const sel = _emailSelectedIds.has(u.id);
        const initials = ((u.prenom||'').charAt(0) + (u.nom||'').charAt(0)).toUpperCase();
        const avatarHtml = u.photo
            ? `<img src="${escapeHtml(u.photo)}" alt="" class="pv-rc-avatar">`
            : `<div class="pv-rc-avatar-placeholder">${initials}</div>`;
        return `<div class="pv-recipient-card${sel ? ' selected' : ''}" data-uid="${u.id}">
            ${avatarHtml}
            <div class="pv-rc-info">
                <div class="pv-rc-name">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</div>
                ${u.fonction_nom ? `<div class="pv-rc-fonction">${escapeHtml(u.fonction_nom)}</div>` : ''}
            </div>
            <div class="pv-rc-check"><i class="bi bi-check-lg"></i></div>
        </div>`;
    }).join('') || '<div class="text-muted small text-center py-3">Aucun résultat</div>';

    list.querySelectorAll('.pv-recipient-card').forEach(card => {
        card.addEventListener('click', () => {
            const uid = card.dataset.uid;
            if (_emailSelectedIds.has(uid)) { _emailSelectedIds.delete(uid); card.classList.remove('selected'); }
            else { _emailSelectedIds.add(uid); card.classList.add('selected'); }
            updateRecipientsCount();
        });
    });
    updateRecipientsCount();
}

function updateRecipientsCount() {
    const count = _emailSelectedIds.size;
    const badge = document.getElementById('emailRecipientsCount');
    badge.textContent = count;
    badge.classList.toggle('pv-hidden', count === 0);
}

async function sendPvEmail() {
    const to = [..._emailSelectedIds];
    if (!to.length) { toast('Sélectionnez au moins un destinataire', 'error'); return; }

    const btn = document.getElementById('btnSendEmail');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';

    const r = await adminApiPost('admin_send_pv_email', {
        pv_id: pvId,
        to,
        sujet: document.getElementById('emailSubject').value,
        contenu: document.getElementById('emailContent').value,
    });

    if (r.success) {
        toast('Email envoyé !');
        bootstrap.Modal.getInstance(document.getElementById('modalSendEmail'))?.hide();
    } else {
        toast(r.message || 'Erreur d\'envoi', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-1"></i>Envoyer';
}

window.initPvdetailPage = initPvdetailPage;
})();
</script>
