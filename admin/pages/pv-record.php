<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$pvRecordId = $_GET['id'] ?? null;
$pvRecordData = null;
if ($pvRecordId) {
    $pvRecordData = Db::fetch(
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
        [$pvRecordId]
    );
    if ($pvRecordData) {
        $pvRecordData['participants'] = !empty($pvRecordData['participants']) ? json_decode($pvRecordData['participants'], true) : [];
        $pvRecordData['tags'] = !empty($pvRecordData['tags']) ? json_decode($pvRecordData['tags'], true) : [];
    }
}
$pvRecordCfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key IN ('ollama_model','transcription_engine','pv_external_mode','pv_transcription_cloud','pv_structuration_cloud','deepgram_api_key','pv_structure_options') ORDER BY config_key");
$pvRecordCfg = [];
foreach ($pvRecordCfgRows as $r) { $pvRecordCfg[$r['config_key']] = $r['config_value']; }
?>
<!-- PV Recording Page -->
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css">
<link rel="stylesheet" href="/spocspace/admin/assets/css/emoji-picker.css">
<style>
/* ── Server status: compact indicator dot ── */
.srv-indicator {
  position: relative;
  display: inline-block;
  cursor: pointer;
  user-select: none;
}
.srv-indicator-dot {
  width: 12px; height: 12px;
  border-radius: 50%;
  background: #bbb8b2;
  transition: background .4s, box-shadow .4s;
  position: relative;
}
/* Halo animation */
.srv-indicator-dot::after {
  content: '';
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  opacity: 0;
  transition: opacity .4s;
}
/* Green = all connected */
.srv-indicator[data-global="ok"] .srv-indicator-dot {
  background: #4caf50;
  box-shadow: 0 0 6px rgba(76,175,80,.4);
}
.srv-indicator[data-global="ok"] .srv-indicator-dot::after {
  background: rgba(76,175,80,.15);
  animation: srv-halo 2.5s ease-in-out infinite;
}
/* Orange = partial */
.srv-indicator[data-global="partial"] .srv-indicator-dot {
  background: #ff9800;
  box-shadow: 0 0 6px rgba(255,152,0,.4);
}
.srv-indicator[data-global="partial"] .srv-indicator-dot::after {
  background: rgba(255,152,0,.15);
  animation: srv-halo 2s ease-in-out infinite;
}
/* Red = nothing connected */
.srv-indicator[data-global="error"] .srv-indicator-dot {
  background: #ef5350;
  box-shadow: 0 0 6px rgba(239,83,80,.4);
}
.srv-indicator[data-global="error"] .srv-indicator-dot::after {
  background: rgba(239,83,80,.15);
  animation: srv-halo 1.5s ease-in-out infinite;
}
/* Checking */
.srv-indicator[data-global="checking"] .srv-indicator-dot {
  background: #bbb8b2;
  animation: srv-pulse-check 1.2s ease-in-out infinite;
}
@keyframes srv-halo {
  0%, 100% { opacity: 0; transform: scale(.8); }
  50% { opacity: 1; transform: scale(1.1); }
}
@keyframes srv-pulse-check {
  0%, 100% { opacity: .4; transform: scale(.85); }
  50% { opacity: 1; transform: scale(1.1); }
}

/* ── Dropdown detail panel ── */
.srv-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: var(--cl-bg-card, #fff);
  border: 1px solid var(--cl-border, #e5e7eb);
  border-radius: 10px;
  padding: 10px 14px;
  min-width: 180px;
  box-shadow: 0 4px 16px rgba(0,0,0,.1);
  z-index: 100;
  font-size: .78rem;
}
.srv-dropdown.open { display: block; animation: srv-drop-in .2s ease-out; }
@keyframes srv-drop-in {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}
.srv-dropdown-row {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 0;
  color: var(--cl-text, #1a1a1a);
}
.srv-dropdown-row + .srv-dropdown-row { border-top: 1px solid var(--cl-border, #f0f0f0); padding-top: 6px; margin-top: 2px; }
.srv-dd-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.srv-dd-dot.on { background: #4caf50; }
.srv-dd-dot.off { background: #ef5350; }
.srv-dd-dot.wait { background: #bbb; }
.srv-dd-name { font-weight: 500; flex: 1; }
.srv-dd-status { font-size: .7rem; opacity: .6; }

/* Legacy compat — hide old badges */
.srv-badge { display: none !important; }

/* ─── Blur on editor during AI structuration ─── */
.pv-editor-blur .zs-ed-content {
  filter: blur(6px);
  pointer-events: none;
  transition: filter 0.4s ease;
}

/* Cancel button in processing bar */
.pv-btn-cancel {
  background: transparent; border: 1px solid #c0392b; color: #c0392b;
  border-radius: 6px; padding: 2px 10px; font-size: .78rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.pv-btn-cancel:hover { background: #c0392b; color: #fff; }

/* Toggle original button */
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

/* ── Reusable utility classes ── */
.pv-hidden { display: none !important; }
.pv-d-none { display: none; }
.pv-d-block { display: block; }
.pv-d-inline-block { display: inline-block; }
.pv-d-inline-flex { display: inline-flex; }
.pv-d-flex { display: flex; }
.pv-opacity-0 { opacity: 0; }
.pv-opacity-1 { opacity: 1; }

/* ── Button variant classes ── */
.pv-btn-transcribe {
  background: #bcd2cb; color: #2d4a43; font-weight: 500;
}
.pv-btn-whisper {
  background: #5B4B6B; color: #fff; font-weight: 500;
}
.pv-btn-structure {
  background: #D0C4D8; color: #5B4B6B; font-weight: 500;
}
.pv-btn-settings {
  background: #f3f2ee; color: var(--cl-text-secondary, #6B6B6B); font-weight: 500;
}
.pv-whisper-small { opacity: .7; font-size: .75em; }

/* ── Recording status area ── */
.pv-recording-status {
  background-color: #FFF8F4; border-color: #E8E5E0;
  border-left: 4px solid #D97757; padding: 12px; transition: all 0.3s;
}
.pv-recording-status.paused {
  background-color: var(--cl-sidebar-hover, #F0EDE8);
  border-color: var(--cl-border, #E8E5E0);
}
.pv-recording-status.recording {
  background-color: #FFF8F4;
  border-color: var(--cl-border, #E8E5E0);
}

/* ── Audio visualizer canvas ── */
.pv-audio-canvas {
  display: block; border-radius: 4px; margin-top: 4px; background: #F7F5F2;
}

/* ── Processing status area ── */
.pv-processing-status {
  background-color: #e8e6dc; border: 1px solid #dbd9d1;
}
.pv-processing-timer { font-size: .8rem; }
.pv-processing-bar-wrap { height: 6px; border-radius: 3px; background: #d5d3cb; }
.pv-processing-bar {
  width: 0%; background: var(--cl-accent, #191918);
  border-radius: 3px; transition: width .5s linear;
}
.pv-processing-meta { font-size: .72rem; color: #8a8680; }

/* ── Editor container ── */
.pv-editor-container { min-height: 300px; position: relative; }

.pv-back-link { text-decoration: none; }

/* ── Drop zone ── */
.pv-drop-zone {
  border: 2px dashed var(--cl-border, #E8E5E0); border-radius: 12px;
  padding: 28px 20px; cursor: pointer; transition: all .25s; background: #FAFAF8;
  text-align: center;
}
.pv-drop-zone:hover,
.pv-drop-zone.dragover {
  border-color: var(--cl-accent, #191918); background: #F0EDE8;
}
.pv-drop-zone:hover .bi-soundwave { color: var(--cl-accent, #191918) !important; }
.pv-drop-zone-icon { font-size: 2.2rem; color: var(--cl-text-muted, #9B9B9B); display: block; margin-bottom: 6px; }
.pv-drop-zone-title { color: var(--cl-text, #1A1A1A); }
.pv-drop-zone-link { color: var(--cl-accent, #191918); font-weight: 600; text-decoration: underline; }
.pv-file-input-hidden { display: none; }

/* ── Audio file preview ── */
.pv-audio-preview { display: none; background: #F5F0E8; border: 1px solid #D4C9B5; border-radius: 10px; padding: 12px 16px; margin-top: 10px; }
.pv-audio-preview.pv-visible { display: block; }
.pv-audio-preview-icon { font-size: 1.4rem; color: #5B4B6B; }

/* ── Audio playback container ── */
.pv-audio-playback-container { display: none; }
.pv-audio-playback-container.pv-visible { display: block; }
.pv-audio-playback-wrap { background: #F5F0E8; border: 1px solid #D4C9B5; border-radius: 10px; padding: 12px 16px; }
.pv-audio-player { border-radius: 8px; }
.pv-btn-retranscribe { font-size: .78rem; padding: 2px 10px; border-radius: 6px; }

/* ── Upload button ── */
.pv-btn-upload-audio { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: 8px; }

/* ── Validation options ── */
.pv-validation-options { display: none; }
.pv-validation-options.pv-visible { display: block; }

/* ── Modal headers (shared) ── */
.pv-modal-header { background: #F5F0E8; border-bottom: 2px solid #D4C9B5; }
.pv-modal-close { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.pv-modal-close-icon { font-size: .85rem; }

/* ── Original text content ── */
.pv-original-text-content {
  background: #FAFAF8; border: 1px solid #E8E5E0; border-radius: 8px;
  padding: 16px; max-height: 400px; overflow-y: auto;
  font-size: .9rem; line-height: 1.6; white-space: pre-wrap; user-select: text;
}

/* ── Accent buttons (shared) ── */
.pv-btn-accent { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: var(--cl-radius-sm, 8px); }
.pv-btn-email-original { background: #D4C4A8; color: #5B4B3B; font-weight: 600; border-radius: 8px; }

/* ── Email modal ── */
.pv-email-list-bg { background: #FAFAF8; }
.pv-email-search-wrap { background: #fff; }
.pv-email-search-icon { left: 10px; top: 50%; transform: translateY(-50%); font-size: .78rem; color: #999; }
.pv-email-search-input { padding-left: 30px; padding-right: 28px; font-size: .82rem; border-radius: 6px; }
.pv-email-search-clear {
  right: 4px; top: 50%; transform: translateY(-50%);
  width: 20px; height: 20px; padding: 0; border: none; background: transparent; display: none;
}
.pv-email-search-clear.pv-visible { display: flex; }
.pv-email-search-clear-icon { font-size: .68rem; color: #999; }
.pv-email-fonction-filter { width: auto; min-width: 100px; font-size: .82rem; border-radius: 6px; }
.pv-email-recipients-list { max-height: 200px; overflow-y: auto; }
.pv-email-content { font-size: .85rem; }
.pv-custom-instructions { font-size: .85rem; }
.pv-recipients-badge { background: var(--cl-accent, #191918); font-size: .7rem; min-width: 22px; display: none; }
.pv-recipients-badge.pv-visible { display: inline-block; }

/* ── Format preview inline styles → classes ── */
.pv-fmt-title-preview { font-size: 1.05rem; font-weight: 700; }
.pv-fmt-subtitle-preview { font-size: 0.88rem; font-weight: 600; }
.pv-fmt-bold-preview { font-weight: 800; }
.pv-fmt-italic-preview { font-style: italic; }
.pv-fmt-underline-preview { text-decoration: underline; font-weight: 500; }
.pv-fmt-list-preview { align-items: flex-start; padding-left: 8px; }
.pv-fmt-list-item { font-size: .78rem; }
.pv-line-50 { width: 50%; }
.pv-line-60 { width: 60%; }
.pv-line-45 { width: 45%; }
.pv-line-40 { width: 40%; }
.pv-line-55 { width: 55%; }
.pv-line-35 { width: 35%; }
.pv-line-70 { width: 70%; }
.pv-rc-check-icon { font-size: .85rem; }

/* ── Whisper alert modal (JS-created) — CSS classes ── */
.pv-whisper-overlay {
  position: fixed; inset: 0; z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,.45);
}
.pv-whisper-dialog {
  background: #fff; border-radius: 12px; max-width: 520px; width: 90%;
  box-shadow: 0 8px 32px rgba(0,0,0,.2); overflow: hidden;
}
.pv-whisper-header {
  padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
  display: flex; align-items: center; gap: 12px;
}
.pv-whisper-icon-wrap {
  width: 40px; height: 40px; border-radius: 50%; background: #E2B8AE;
  display: flex; align-items: center; justify-content: center;
}
.pv-whisper-icon { color: #7B3B2C; font-size: 1.1rem; }
.pv-whisper-title { margin: 0; font-size: 1.05rem; }
.pv-whisper-body { padding: 24px; }
.pv-whisper-body-text { margin: 0 0 16px; color: #374151; font-size: .9rem; }
.pv-whisper-instructions {
  background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
  padding: 16px; margin-bottom: 16px;
}
.pv-whisper-instructions-title { margin: 0 0 8px; font-weight: 600; font-size: .85rem; color: #1a1a1a; }
.pv-whisper-steps { margin: 0; padding-left: 20px; font-size: .84rem; color: #4b5563; }
.pv-whisper-steps li { margin-bottom: 6px; }
.pv-whisper-note {
  background: #FFF8F4; border: 1px solid #E8E5E0; border-radius: 8px;
  padding: 12px; font-size: .82rem; color: #6B5B3E;
}
.pv-whisper-footer {
  padding: 16px 24px; border-top: 1px solid #e5e7eb;
  display: flex; justify-content: flex-end; gap: 8px;
}
.pv-whisper-retry { background: #bcd2cb; color: #2d4a43; font-weight: 500; }

/* ── External badge (JS-created) ── */
.pv-ext-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px 5px 10px; border-radius: 20px;
  font-size: .75rem; font-weight: 600;
  background: #e8f5e9; color: #2e7d32;
}
.pv-ext-dot { width: 8px; height: 8px; border-radius: 50%; background: #4CAF50; }

/* ── Recording status state classes ── */
.pv-recording-paused { background-color: var(--cl-sidebar-hover, #F0EDE8) !important; border-color: var(--cl-border, #E8E5E0) !important; }
.pv-recording-active { background-color: #FFF8F4 !important; border-color: var(--cl-border, #E8E5E0) !important; }

/* ── Whisper done state ── */
.pv-btn-whisper-done { background: #bcd2cb; color: #2d4a43; }
.pv-btn-whisper-reset { background: #5B4B6B; color: #fff; }

/* ── Transcribe done state ── */
.pv-btn-transcribe-done { background: #bcd2cb; }

/* ── Status text opacity transition ── */
.pv-status-fade { opacity: 0; transition: opacity .3s; }
.pv-status-show { opacity: 1; }
</style>
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-mic-fill"></i> <span id="pvRecordTitle">Chargement...</span>
    </h1>
    <div class="d-flex gap-2 align-items-center">
      <button class="btn shadow-sm pv-btn-record" id="btnStartRecord">
        <i class="bi bi-mic-fill"></i> DÉMARRER LA DICTÉE
      </button>
      <button class="btn shadow-sm pv-btn-pause pv-d-none" id="btnPauseRecord">
        <i class="bi bi-pause-fill"></i> PAUSE
      </button>
      <button class="btn shadow-sm pv-btn-stop pv-d-none" id="btnStopRecord">
        <i class="bi bi-stop-circle-fill"></i> ARRÊTER
      </button>
      <button class="btn shadow-sm pv-btn-transcribe pv-d-none" id="btnTranscribe">
        <i class="bi bi-body-text"></i> TRANSCRIRE L'AUDIO
      </button>
      <button class="btn shadow-sm pv-btn-whisper pv-d-none" id="btnWhisperRetranscribe" title="Re-transcrire l'audio complet avec Whisper (plus précis, plus lent)">
        <i class="bi bi-stars"></i> WHISPER <small class="pv-whisper-small">(précis)</small>
      </button>
      <button class="btn shadow-sm pv-btn-structure pv-d-none" id="btnStructure">
        <i class="bi bi-magic"></i> STRUCTURER LE PV
      </button>
      <button class="btn shadow-sm pv-btn-settings" id="btnPvSettings" title="Paramètres IA" data-bs-toggle="modal" data-bs-target="#modalPvSettings">
        <i class="bi bi-gear"></i> Paramètres
      </button>
      <a href="<?= admin_url('pv') ?>" class="btn pv-btn-pause pv-back-link">
        <i class="bi bi-arrow-left"></i> Retour
      </a>
    </div>
  </div>

  <!-- Recording Controls -->
  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title d-flex justify-content-between align-items-center">
            <span>Enregistrement & Transcription en Direct</span>
            <div class="srv-indicator" id="srvIndicator" data-global="checking" title="Statut des serveurs">
              <div class="srv-indicator-dot"></div>
              <div class="srv-dropdown" id="srvDropdown">
                <div class="srv-dropdown-row" id="rowVosk">
                  <span class="srv-dd-dot wait"></span>
                  <span class="srv-dd-name">Vosk</span>
                  <span class="srv-dd-status">…</span>
                </div>
                <div class="srv-dropdown-row pv-d-none" id="rowWhisper">
                  <span class="srv-dd-dot wait"></span>
                  <span class="srv-dd-name">Whisper</span>
                  <span class="srv-dd-status">…</span>
                </div>
                <div class="srv-dropdown-row" id="rowOllama">
                  <span class="srv-dd-dot wait"></span>
                  <span class="srv-dd-name">Ollama</span>
                  <span class="srv-dd-status">…</span>
                </div>
              </div>
            </div>
          </h5>
          
          <!-- Recording Status -->
          <div id="recordingStatus" class="alert mb-3 pv-recording-status pv-d-none">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span id="recordingIndicator"></span>
                <strong id="recordingStatusText">Écoute en cours... Parlez distinctement.</strong>
                <div class="small mt-1 text-muted" id="recordingSubtext">Le texte s'affiche en temps réel grâce à Vosk (IA locale).</div>
              </div>
              <div class="text-end">
                <span id="recordingTime" class="fw-bold fs-5 font-monospace d-block">00:00</span>
                <canvas id="audioVisualizer" width="120" height="28" class="pv-audio-canvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Processing Status with progress -->
          <div id="processingStatus" class="alert mb-3 py-2 small pv-processing-status pv-d-none">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span id="processingLabel">
                <span class="spinner-border spinner-border-sm me-2"></span>
                L'IA traduit votre voix en texte...
              </span>
              <div class="d-flex align-items-center gap-2">
                <span id="processingTimer" class="text-muted fw-semibold pv-processing-timer"></span>
                <button class="pv-btn-cancel pv-d-none" id="btnCancelProcessing" title="Annuler l'opération">
                  <i class="bi bi-x-circle me-1"></i>Annuler
                </button>
              </div>
            </div>
            <div class="progress pv-processing-bar-wrap" id="processingProgressWrap">
              <div class="progress-bar pv-processing-bar" id="processingProgress" role="progressbar"></div>
            </div>
            <div class="d-flex justify-content-between mt-1 pv-processing-meta">
              <span id="processingElapsed">0:00</span>
              <span id="processingEstimate"></span>
            </div>
          </div>

          <!-- Transcript Area -->
          <div class="form-group mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label mb-0"><strong>Contenu du PV</strong></label>
              <button class="pv-btn-toggle-original pv-d-none" id="btnShowOriginal" title="Voir la transcription originale avant structuration IA">
                <i class="bi bi-file-earmark-text me-1"></i>Original
              </button>
            </div>
            <div id="pvEditorContainer" class="zs-editor-wrap form-control p-0 pv-editor-container">
                <!-- Tiptap Editor will be mounted here -->
            </div>
            <textarea id="pvTranscript" class="pv-d-none"></textarea>
          </div>

          <!-- Audio Upload Alternative -->
          <div class="mb-3 border-top pt-3 mt-4">
            <label class="form-label small mb-2"><strong>Ou importer un fichier audio externe</strong></label>

            <!-- Drop zone -->
            <div id="audioDropZone" class="pv-drop-zone">
              <i class="bi bi-soundwave pv-drop-zone-icon"></i>
              <p class="mb-1 small fw-semibold pv-drop-zone-title">Glissez un fichier audio ici</p>
              <p class="mb-0 small text-muted">ou <span class="pv-drop-zone-link">cliquez pour parcourir</span></p>
              <input type="file" id="audioFileInput" accept="audio/*" class="pv-file-input-hidden">
            </div>

            <!-- File selected preview -->
            <div id="audioFilePreview" class="pv-audio-preview">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-file-earmark-music pv-audio-preview-icon"></i>
                  <div>
                    <div class="small fw-semibold" id="audioFileName">—</div>
                    <div class="small text-muted" id="audioFileSize">—</div>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm pv-btn-upload-audio" id="btnUploadAudio">
                    <i class="bi bi-cloud-upload me-1"></i>Transcrire
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" id="btnRemoveAudio" title="Supprimer">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Audio playback -->
            <div id="audioPlaybackContainer" class="mt-3 pv-audio-playback-container">
              <div class="pv-audio-playback-wrap">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="small fw-semibold mb-0"><i class="bi bi-play-circle me-1"></i> Audio du PV</label>
                  <button class="btn btn-sm btn-outline-secondary pv-btn-retranscribe" id="btnRetranscribeAudio">
                    <i class="bi bi-arrow-repeat me-1"></i>Retranscrire
                  </button>
                </div>
                <audio id="audioPlayback" controls class="w-100 pv-audio-player"></audio>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Side Panel -->
    <div class="col-lg-4">
      <!-- PV Info -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Infos du PV</h6>
        </div>
        <div class="card-body small">
          <div class="mb-2">
            <strong>Titre:</strong><br>
            <span id="pvRecordTitleInfo">—</span>
          </div>
          <div class="mb-2">
            <strong>Créateur:</strong><br>
            <span id="pvRecordCreator">—</span>
          </div>
          <div class="mb-2">
            <strong>Module:</strong><br>
            <span id="pvRecordModule">—</span>
          </div>
          <div class="mb-2">
            <strong>Participants:</strong><br>
            <span id="pvRecordParticipants">—</span>
          </div>
          <div class="mb-3">
            <strong>Statut:</strong><br>
            <span class="badge pv-badge-brouillon" id="pvRecordStatus">brouillon</span>
          </div>
          
          <div class="border-top pt-3">
            <div class="d-flex align-items-center justify-content-between">
              <label class="small fw-bold mb-0" for="pvAllowComments">Autoriser les commentaires et la note</label>
              <div class="form-check form-switch form-switch-sm mb-0">
                <input class="form-check-input" type="checkbox" id="pvAllowComments" checked>
              </div>
            </div>
            <small class="text-muted d-block mt-1">Si actif, les employés pourront noter ce PV (5 étoiles) et ajouter des commentaires via l'éditeur.</small>
          </div>
        </div>
      </div>

      <!-- Validation -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-shield-check me-1"></i> Validation</h6>
        </div>
        <div class="card-body small">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="small fw-bold mb-0" for="pvValidationRequired">Validation requise</label>
            <div class="form-check form-switch form-switch-sm mb-0">
              <input class="form-check-input" type="checkbox" id="pvValidationRequired">
            </div>
          </div>
          <div id="pvValidationOptions" class="pv-validation-options">
            <label class="small text-muted mb-1 d-block">Qui doit valider ce PV ?</label>
            <div class="zs-select" id="pvValidationRole" data-placeholder="Rôle"></div>
            <small class="text-muted d-block mt-1">Le validateur recevra une notification quand le PV sera soumis.</small>
          </div>
        </div>
      </div>

      <!-- Save PV -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Finaliser</h6>
        </div>
        <div class="card-body">
          <button class="btn w-100 btn-sm pv-btn-primary" id="btnSavePv">
            <i class="bi bi-check-lg"></i> Enregistrer et finaliser le PV
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Theme buttons */
.pv-btn-primary {
  background-color: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 600;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-primary:hover { background-color: var(--cl-accent-hover, #000); color: #fff; }

.pv-btn-record {
  background-color: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 500;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-record:hover { background-color: var(--cl-accent-hover, #000); color: #fff; }

.pv-btn-pause {
  background-color: var(--cl-sidebar-hover, #F0EDE8); border: 1px solid var(--cl-border, #E8E5E0);
  color: var(--cl-text, #1A1A1A); font-weight: 500;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-pause:hover { background-color: var(--cl-sidebar-active, #EDE8E0); color: var(--cl-text, #1A1A1A); }

.pv-btn-stop {
  background-color: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 500;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-stop:hover { background-color: var(--cl-accent-hover, #000); color: #fff; }

.pv-btn-resume {
  background-color: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 500;
  border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s;
}
.pv-btn-resume:hover { background-color: var(--cl-accent-hover, #000); color: #fff; }

/* Badges */
.pv-badge-brouillon { background-color: #F0EDE8; color: var(--cl-text-secondary, #6B6B6B); font-weight: 600; }
.pv-badge-enregistrement { background-color: #E8E5E0; color: var(--cl-text, #1A1A1A); font-weight: 600; }
.pv-badge-en_validation { background-color: #FFF3CD; color: #856404; font-weight: 600; }
.pv-badge-finalise { background-color: #bcd2cb !important; color: #2d4a43 !important; }

/* Recording dot animation */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}
#recordingIndicator {
  display: inline-block;
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--cl-accent, #191918);
  animation: pulse 1s infinite;
  flex-shrink: 0; vertical-align: middle;
}
#recordingStatus.paused #recordingIndicator {
  background: var(--cl-text-muted, #9B9B9B);
  animation: none;
}
/* Timer mono font */
#recordingTime {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 1.4rem; font-weight: 700; letter-spacing: 1px;
}
/* Ensure the editor content area expands */
.zs-ed-content { min-height: 250px; padding: 1rem; }
.zs-ed-content .ProseMirror { min-height: 250px; outline: none; }

</style>

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
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg pv-modal-close-icon"></i></button>
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
                <span class="pv-fmt-title-preview">Titre</span>
                <span class="pv-line pv-line-50"></span>
              </div>
              <div class="pv-fmt-label">Titres</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptSousTitres">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-60"></span>
                <span class="pv-fmt-subtitle-preview">Sous-titre</span>
                <span class="pv-line pv-line-45"></span>
              </div>
              <div class="pv-fmt-label">Sous-titres</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptGras">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-40"></span>
                <span class="pv-fmt-bold-preview">Texte gras</span>
                <span class="pv-line pv-line-55"></span>
              </div>
              <div class="pv-fmt-label">Gras</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptItalique">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-50"></span>
                <em class="pv-fmt-italic-preview">Question ?</em>
                <span class="pv-line pv-line-35"></span>
              </div>
              <div class="pv-fmt-label">Italique</div>
            </div>
            <div class="pv-fmt-card" data-opt="pvOptSouligne">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview">
                <span class="pv-line pv-line-45"></span>
                <span class="pv-fmt-underline-preview">M. Dupont</span>
                <span class="pv-line pv-line-55"></span>
              </div>
              <div class="pv-fmt-label">Souligné</div>
            </div>
            <div class="pv-fmt-card active" data-opt="pvOptListes">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview pv-fmt-list-preview">
                <span class="pv-fmt-list-item">&#8226; Premier point</span>
                <span class="pv-fmt-list-item">&#8226; Deuxième point</span>
                <span class="pv-fmt-list-item">&#8226; Troisième point</span>
              </div>
              <div class="pv-fmt-label">Listes à puces</div>
            </div>
            <div class="pv-fmt-card" data-opt="pvOptNumeros">
              <span class="pv-fmt-check">✓</span>
              <div class="pv-fmt-preview pv-fmt-list-preview">
                <span class="pv-fmt-list-item">1. Premier point</span>
                <span class="pv-fmt-list-item">2. Deuxième point</span>
                <span class="pv-fmt-list-item">3. Troisième point</span>
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

<!-- Modal: Transcription Originale -->
<div class="modal fade" id="modalOriginalText" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header pv-modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Transcription originale</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg pv-modal-close-icon"></i></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2">Texte brut avant structuration par l'IA. Sélectionnez du texte pour copier une partie, ou utilisez les boutons ci-dessous.</p>
        <div id="originalTextContent" class="pv-original-text-content"></div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnCopyOriginal"><i class="bi bi-clipboard me-1"></i>Copier tout</button>
          <button class="btn btn-sm btn-outline-secondary" id="btnCopySelection"><i class="bi bi-cursor-text me-1"></i>Copier la sélection</button>
        </div>
        <button class="btn btn-sm pv-btn-email-original" id="btnEmailOriginal">
          <i class="bi bi-envelope me-1"></i>Envoyer par email
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Envoyer par email -->
<div class="modal fade" id="modalSendEmail" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-chat-dots me-2"></i>Envoyer par message interne</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center pv-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg pv-modal-close-icon"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold d-flex align-items-center justify-content-between">
            <span>Destinataires *</span>
            <span id="emailRecipientsCount" class="badge rounded-pill pv-recipients-badge">0</span>
          </label>
          <div class="border rounded overflow-hidden pv-email-list-bg">
            <div class="d-flex gap-2 p-2 border-bottom pv-email-search-wrap">
              <div class="position-relative flex-grow-1">
                <i class="bi bi-search position-absolute pv-email-search-icon"></i>
                <input type="text" id="emailSearchInput" class="form-control form-control-sm pv-email-search-input" placeholder="Rechercher…">
                <button type="button" id="emailSearchClear" class="btn btn-sm position-absolute align-items-center justify-content-center pv-email-search-clear"><i class="bi bi-x-lg pv-email-search-clear-icon"></i></button>
              </div>
              <div class="zs-select pv-email-fonction-filter" id="emailFonctionFilter" data-placeholder="Tous"></div>
            </div>
            <div id="emailRecipientsList" class="p-2 d-flex flex-column gap-1 pv-email-recipients-list"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Sujet</label>
          <input type="text" class="form-control form-control-sm" id="emailSubject" value="">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Contenu</label>
          <textarea class="form-control form-control-sm pv-email-content" id="emailContent" rows="6"></textarea>
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

<script<?= nonce() ?>>
(function() {
let editorModule = null;

let isRecording = false;
let isPaused = false;
let totalRecordingTime = 0;
let recordingInterval = null;
let pvId = null;
let pvData = null;
const ssrPvData = <?= $pvRecordData ? json_encode($pvRecordData, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;
const ssrCfg = <?= json_encode($pvRecordCfg, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
let audioStream = null;

// Enregistreur principal (pour sauvegarder le fichier final complet)
let mainRecorder = null;
let recordedChunks = [];

// Enregistreur en direct (pour la transcription temps réel)
let liveRecorder = null;
let liveInterval = null;
let transcribingQueue = Promise.resolve();

// Variables globales pour l'IA (Python Whisper local server)
const WHISPER_URL = 'http://localhost:5876';
let isAiReady = false;
let ollamaModel = 'gemma3:4b'; // loaded from config
let transcriptionEngine = 'vosk'; // vosk (léger) ou whisper (précis) — loaded from config
let whisperAvailable = false; // true si faster-whisper installé sur le poste
let externalMode = false; // legacy compat
let transcriptionCloud = false; // true = Deepgram cloud transcription
let structurationCloud = false; // true = Claude/Gemini cloud structuration
let deepgramApiKey = ''; // loaded from config
let deepgramSocket = null; // WebSocket for live streaming

// ── Paramètres de structuration IA ──
const pvSettingsDefaults = {
    titres: true, sousTitres: true, gras: true, italique: true,
    souligne: false, listes: true, numeros: false,
    secPresents: true, secOdj: true, secPoints: true,
    secDecisions: true, secActions: true, secProchaine: false,
    detailLevel: '80', ortho: true, customInstructions: ''
};
let pvSettings = { ...pvSettingsDefaults };

// Abort controller pour annuler les opérations IA en cours
let _currentAbortController = null;

// Transcription originale avant structuration
let _originalTranscription = null;
let _showingOriginal = false;

function loadPvSettingsToUI() {
    document.querySelectorAll('.pv-fmt-card[data-opt], .pv-sec-chip[data-opt]').forEach(el => {
        const optId = el.dataset.opt;
        const key = optId.replace('pvOpt', '').replace(/^(.)/, (m,c) => c.toLowerCase());
        el.classList.toggle('active', !!pvSettings[key]);
    });
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
    } catch (e) {
        toast('Erreur: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer';
    bootstrap.Modal.getInstance(document.getElementById('modalPvSettings'))?.hide();
}

function buildStructurePrompt(rawText) {
    const s = pvSettings;
    let rules = [];

    // Formatage
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

    // Sections
    let sections = [];
    if (s.secPresents) sections.push('Présents / Absents');
    if (s.secOdj) sections.push('Ordre du jour');
    if (s.secPoints) sections.push('Points discutés');
    if (s.secDecisions) sections.push('Décisions prises');
    if (s.secActions) sections.push('Actions à mener');
    if (s.secProchaine) sections.push('Prochaine séance');
    if (sections.length > 0) rules.push('Sections attendues : ' + sections.join(', '));

    // Niveau de détail
    const pct = parseInt(s.detailLevel) || 80;
    if (pct >= 70) {
        rules.push(`NE RÉSUME PAS : conserve au minimum ${pct}% du contenu original. Chaque point discuté doit être développé, pas juste mentionné`);
    } else if (pct >= 50) {
        rules.push(`Conserve environ ${pct}% du contenu. Résume les points secondaires mais développe les décisions et actions`);
    } else {
        rules.push(`Fais un résumé concis (environ ${pct}% du contenu). Garde uniquement l'essentiel : décisions, actions, points clés`);
    }

    // Italique pour questions
    if (s.italique) rules.push('Les questions doivent être en <em>italique</em>, suivies immédiatement de la réponse en texte normal dessous');

    // Orthographe
    if (s.ortho) rules.push("Corrige les fautes d'orthographe et de grammaire");

    rules.push('Garde le sens exact et les détails, ne modifie pas le fond');
    rules.push('Ne mets PAS de balises <html>, <head>, <body> — juste le contenu HTML direct');
    rules.push('Réponds UNIQUEMENT avec le HTML structuré, sans commentaire ni explication');

    // Instructions personnalisées
    let customBlock = '';
    if (s.customInstructions) {
        customBlock = `\n\nInstructions supplémentaires de l'utilisateur :\n${s.customInstructions}`;
    }

    return `Tu es un assistant pour un EMS (établissement médico-social) à Genève.
Voici la transcription brute d'un procès-verbal de réunion. Structure ce texte en un PV professionnel en HTML.

Règles IMPORTANTES :
${rules.map(r => '- ' + r).join('\n')}${customBlock}

Transcription brute :
${rawText}`;
}

// Variables pour le visualiseur audio
let visAudioContext = null;
let visAnalyser = null;
let visSource = null; // IMPORTANT: garder la référence pour éviter le garbage collection
let visDataArray = null;
let visAnimationId = null;
let visRunning = false;

// Instance de l'éditeur Tiptap
let editorInstance = null;

// Initialize
async function initPvrecordPage() {
  console.log('[PV-INIT] START');

  // Attacher les contrôles d'enregistrement EN PREMIER (avant tout await)
  setupRecordingControls();
  setupFileUpload();
  document.getElementById('btnSavePv')?.addEventListener('click', savePv);
  document.getElementById('btnSavePvSettings')?.addEventListener('click', savePvSettings);

  // Init validation role zerdaSelect
  const validationRoleOpts = [
    { value: 'responsable', label: 'Responsable' },
    { value: 'admin', label: 'Admin' },
    { value: 'direction', label: 'Direction' },
  ];
  zerdaSelect.init(document.getElementById('pvValidationRole'), validationRoleOpts, {
    value: 'responsable', search: false
  });

  // Card/chip toggle click handlers
  document.querySelectorAll('.pv-fmt-card[data-opt], .pv-sec-chip[data-opt]').forEach(el => {
      el.addEventListener('click', () => el.classList.toggle('active'));
  });

  console.log('[PV-INIT] Controls attached');

  // Config injected by PHP
  if (ssrCfg.ollama_model) ollamaModel = ssrCfg.ollama_model;
  if (ssrCfg.transcription_engine) transcriptionEngine = ssrCfg.transcription_engine;
  // New separate modes (fallback to legacy pv_external_mode)
  if (ssrCfg.pv_transcription_cloud === '1' || (ssrCfg.pv_external_mode === '1' && ssrCfg.pv_transcription_cloud === undefined)) transcriptionCloud = true;
  if (ssrCfg.pv_structuration_cloud === '1' || (ssrCfg.pv_external_mode === '1' && ssrCfg.pv_structuration_cloud === undefined)) structurationCloud = true;
  externalMode = transcriptionCloud; // legacy compat for recording flow
  if (ssrCfg.deepgram_api_key) deepgramApiKey = ssrCfg.deepgram_api_key;
  if (ssrCfg.pv_structure_options) {
    try { pvSettings = { ...pvSettingsDefaults, ...JSON.parse(ssrCfg.pv_structure_options) }; } catch {}
  }
  loadPvSettingsToUI();
  // Mode UI — cloud services mark as ready
  if (transcriptionCloud) {
    isAiReady = true;
  }

  // Check serveurs locaux (fire-and-forget)
  checkServers();
  setInterval(checkServers, 8000);

  try {
    editorModule = await import('/spocspace/assets/js/rich-editor.js');
    console.log('[PV-INIT] Editor module loaded');
  } catch (e) {
    console.error('[PV-INIT] Editor import error:', e);
  }

  pvId = AdminURL.currentId();

  if (!ssrPvData) {
    toast('PV non trouvé', 'error');
    window.history.back();
    return;
  }
  pvData = ssrPvData;
  updatePvInfo();
  console.log('[PV-INIT] PV data loaded (SSR)');

  // Initialize Tiptap editor
  if (editorModule) {
    try {
      // Si contenu sauvegardé dans localStorage (rechargement pendant blur), le restaurer
      const lsSaved = localStorage.getItem('ss_pv_blur_saved');
      const editorContent = lsSaved || pvData.contenu || pvData.transcription_brute || '';
      if (lsSaved) localStorage.removeItem('ss_pv_blur_saved');
      editorInstance = await editorModule.createEditor(document.getElementById('pvEditorContainer'), {
          placeholder: "Le texte transcrit s'affichera ici en temps réel. Vous pouvez corriger les éventuelles erreurs manuellement...",
          content: editorContent,
          mode: 'full'
      });
      console.log('[PV-INIT] Editor created');

      // Si le PV a une transcription brute, afficher le bouton original
      if (pvData.transcription_brute) {
          _originalTranscription = pvData.transcription_brute;
          document.getElementById('btnShowOriginal').classList.remove('pv-d-none');
      }

      // Afficher le bouton "Structurer avec l'IA" si du texte est présent
      showStructureBtn();

      // Charger les paramètres de validation
      if (pvData.validation_required) {
          document.getElementById('pvValidationRequired').checked = true;
          document.getElementById('pvValidationOptions').classList.add('pv-visible');
      }
      if (pvData.validation_role) {
          zerdaSelect.setValue(document.getElementById('pvValidationRole'), pvData.validation_role);
      }
    } catch (e) {
      console.error('[PV-INIT] Error initializing editor:', e);
      toast('Erreur lors du chargement', 'error');
    }
  }

  initLocalAI();
  console.log('[PV-INIT] DONE');
}
window.initPvrecordPage = initPvrecordPage;

function updatePvInfo() {
  document.getElementById('pvRecordTitle').textContent = pvData.titre;
  document.getElementById('pvRecordTitleInfo').textContent = pvData.titre;
  document.getElementById('pvRecordCreator').textContent = (pvData.creator_prenom || '') + ' ' + (pvData.creator_nom || '');
  document.getElementById('pvRecordModule').textContent = pvData.module_nom || '—';
  document.getElementById('pvAllowComments').checked = pvData.allow_comments != 0;
  
  const participants = pvData.participants || [];
  document.getElementById('pvRecordParticipants').textContent = 
    participants.length > 0 ? participants.map(p => p.prenom + ' ' + p.nom).join(', ') : '—';
    
  if (pvData.audio_path) {
      showAudioPlayback('/spocspace/admin/api.php?action=admin_serve_pv_audio&id=' + pvData.id);
  }
}

function showAudioPlayback(url) {
    const container = document.getElementById('audioPlaybackContainer');
    const player = document.getElementById('audioPlayback');
    player.src = url + '&t=' + new Date().getTime();
    container.classList.add('pv-visible');
}

// ════════════════════════════════════════════════════
// ANCIENNE VERSION — IA LOCALE (Transformers.js / WebAssembly)
// Conservée comme référence. Le système utilise maintenant
// un serveur Python + Whisper local (voir whisper-local/).
// ════════════════════════════════════════════════════
/*
async function initLocalAI_WASM() {
    try {
        const { pipeline, env } = await import('/spocspace/assets/ai/js/transformers.min.js');

        env.allowLocalModels = true;
        env.allowRemoteModels = false;
        env.localModelPath = '/spocspace/assets/ai/models/';
        env.backends.onnx.wasm.wasmPaths = '/spocspace/assets/ai/js/';

        console.log("Chargement du modèle Whisper en mémoire...");

        transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-tiny');
        isAiReady = true;

        console.log("Modèle IA prêt !");
    } catch (e) {
        console.error("Erreur d'initialisation de l'IA locale:", e);
        toast("L'IA locale n'a pas pu démarrer. Vérifiez les fichiers.", "error");
    }
}
*/

// ════════════════════════════════════════════════════
// NOUVELLE VERSION — Python + Whisper (serveur local)
// Le serveur écoute sur http://localhost:9876
// ════════════════════════════════════════════════════
async function initLocalAI() {
    if (externalMode) return; // pas de serveur local en mode externe
    try {
        const res = await fetch(WHISPER_URL + '/health', { signal: AbortSignal.timeout(2000) });
        if (res.ok) {
            const data = await res.json();
            const engines = data.engines || ['vosk'];
            whisperAvailable = engines.includes('whisper');
            isAiReady = true;

            // Show/hide Whisper badge
            const whisperBadge = document.getElementById('badgeWhisper');
            if (whisperBadge) {
                whisperBadge.classList.toggle('pv-d-none', !whisperAvailable);
                whisperBadge.classList.toggle('pv-d-inline-flex', whisperAvailable);
            }

            console.log(`[Transcription] Serveur connecté. Disponibles: ${engines.join(', ')}`);
        } else {
            throw new Error('Serveur inaccessible');
        }
    } catch (e) {
        console.warn("[Transcription] Serveur local non détecté:", e.message);
        isAiReady = false;
    }
}

/*
// ── ANCIENNE VERSION (WebAssembly) ──
async function liveTranscribeBlob_WASM(blob) {
    if (!isAiReady || !transcriber) return;
    document.getElementById('processingStatus').style.display = 'block';
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        const float32Array = audioBuffer.getChannelData(0);
        if (float32Array.length < 8000) return;
        const output = await transcriber(float32Array, {
            language: 'french', task: 'transcribe', return_timestamps: false
        });
        if (output.text && output.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
                const el = editorInstance.view.dom;
                el.scrollTop = el.scrollHeight;
            }
        }
    } catch (e) {
        console.error("Erreur de transcription en direct:", e);
    } finally {
        if (!isRecording || isPaused) document.getElementById('processingStatus').style.display = 'none';
    }
}
*/

// ── NOUVELLE VERSION (Python serveur local) ──
// Le live transcription utilise TOUJOURS Vosk (temps réel, rapide)
// Whisper est réservé à la re-transcription complète (bouton dédié)
async function liveTranscribeBlob(blob) {
    if (!isAiReady || externalMode) return; // pas de live transcription en mode externe

    const ps = document.getElementById('processingStatus');
    ps.classList.remove('pv-d-none');
    document.getElementById('processingProgressWrap').classList.add('pv-d-none');
    document.getElementById('processingLabel').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>L\'IA traduit votre voix en texte...';
    document.getElementById('processingTimer').textContent = '';
    document.getElementById('processingElapsed').textContent = '';
    document.getElementById('processingEstimate').textContent = '';

    try {
        const res = await fetch(WHISPER_URL + '/transcribe?engine=vosk', {
            method: 'POST',
            headers: { 'Content-Type': blob.type || 'audio/webm' },
            body: blob,
        });

        const data = await res.json();

        if (data.success && data.text && data.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(data.text.trim() + ' ');
                const el = editorInstance.view.dom;
                el.scrollTop = el.scrollHeight;
                showStructureBtn();
            }
        }
    } catch (e) {
        console.error(`[${transcriptionEngine}] Erreur de transcription en direct:`, e);
    } finally {
        if (!isRecording || isPaused) ps.classList.add('pv-d-none');
    }
}

/*
// ── ANCIENNE VERSION (WebAssembly) ──
async function transcribeFullAudioBlob_WASM(blob) {
    if (!isAiReady || !transcriber) return;
    document.getElementById('processingStatus').style.display = 'block';
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        const float32Array = audioBuffer.getChannelData(0);
        const output = await transcriber(float32Array, {
            chunk_length_s: 30, stride_length_s: 5,
            language: 'french', task: 'transcribe', return_timestamps: false
        });
        if (output.text && output.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
            }
        }
        toast("Transcription complète terminée !");
    } catch (e) {
        console.error("Erreur de transcription:", e);
        toast("Erreur lors de la transcription complète.", "error");
    } finally {
        document.getElementById('processingStatus').style.display = 'none';
    }
}
*/

// ── Transcription complète (local ou externe) ──
async function transcribeFullAudioBlob(blob) {
    if (!isAiReady && !externalMode) {
        showWhisperAlert();
        return;
    }

    _currentAbortController = new AbortController();
    const signal = _currentAbortController.signal;

    // ── Mode externe : envoyer au serveur via API backend ──
    if (externalMode) {
        startProgress('Transcription cloud (Deepgram)…', 15, '#B8C9D4');
        try {
            const formData = new FormData();
            formData.append('action', 'admin_transcribe_external');
            formData.append('audio', blob, 'audio.webm');

            const res = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: formData,
                signal,
            });
            const data = await res.json();

            if (data.success && data.text && data.text.trim().length > 0) {
                if (editorInstance) {
                    editorInstance.commands.insertContent(data.text.trim() + ' ');
                    showStructureBtn();
                }
                toast('Transcription cloud terminée !');
            } else if (data.message) {
                toast(data.message, 'error');
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error('[External] Erreur transcription:', e);
            toast('Erreur de transcription cloud: ' + e.message, 'error');
        } finally {
            stopProgress();
        }
        return;
    }

    // ── Mode local : Vosk ou Whisper ──
    const useWhisper = transcriptionEngine === 'whisper' && whisperAvailable;
    const engine = useWhisper ? 'whisper' : 'vosk';

    if (useWhisper) {
        const estSec = Math.max(10, Math.ceil(totalRecordingTime * 4));
        startProgress('Transcription Whisper en cours…', estSec, '#5B4B6B');
    } else {
        const estSec = Math.max(5, Math.ceil(totalRecordingTime * 0.5));
        startProgress('Transcription Vosk en cours…', estSec, '#bcd2cb');
    }

    try {
        const res = await fetch(WHISPER_URL + '/transcribe?engine=' + engine, {
            method: 'POST',
            headers: { 'Content-Type': blob.type || 'audio/webm' },
            body: blob,
            signal,
        });

        const data = await res.json();

        if (data.success && data.text && data.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(data.text.trim() + ' ');
                showStructureBtn();
            }
        }
        toast("Transcription " + (useWhisper ? 'Whisper' : 'Vosk') + " terminée !");
    } catch (e) {
        if (e.name === 'AbortError') return;
        console.error("[" + engine + "] Erreur de transcription complète:", e);
        toast("Erreur lors de la transcription. Vérifiez que le serveur est démarré.", "error");
    } finally {
        stopProgress();
    }
}

// ── Alerte serveur Whisper non détecté ──
function showWhisperAlert() {
    // Supprimer une alerte précédente si elle existe
    document.getElementById('whisperAlertModal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'whisperAlertModal';
    modal.className = 'pv-whisper-overlay';
    modal.innerHTML = `
      <div class="pv-whisper-dialog">
        <div class="pv-whisper-header">
          <div class="pv-whisper-icon-wrap">
            <i class="bi bi-exclamation-triangle-fill pv-whisper-icon"></i>
          </div>
          <h5 class="pv-whisper-title">Serveur de transcription non détecté</h5>
        </div>
        <div class="pv-whisper-body">
          <p class="pv-whisper-body-text">
            Le serveur Whisper local n'est pas démarré sur votre ordinateur.
            Veuillez le lancer avant de commencer l'enregistrement.
          </p>
          <div class="pv-whisper-instructions">
            <p class="pv-whisper-instructions-title">
              <i class="bi bi-terminal"></i> Comment démarrer :
            </p>
            <ol class="pv-whisper-steps">
              <li>Double-cliquez sur le raccourci <strong>« SpocSpace Whisper »</strong> sur votre Bureau</li>
              <li>Attendez que la fenêtre affiche <code>Serveur démarré</code></li>
              <li>Revenez ici et cliquez à nouveau sur <strong>Démarrer la dictée</strong></li>
            </ol>
          </div>
          <div class="pv-whisper-note">
            <i class="bi bi-info-circle"></i>
            <strong>Première utilisation ?</strong> Demandez à votre administrateur le fichier d'installation
            ou téléchargez <code>install-whisper.ps1</code> depuis l'intranet.
          </div>
        </div>
        <div class="pv-whisper-footer">
          <button id="whisperAlertRetry" class="btn btn-sm pv-whisper-retry">
            <i class="bi bi-arrow-clockwise"></i> Réessayer
          </button>
          <button id="whisperAlertClose" class="btn btn-sm btn-light">Fermer</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    document.getElementById('whisperAlertClose').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    document.getElementById('whisperAlertRetry').addEventListener('click', async () => {
        const btn = document.getElementById('whisperAlertRetry');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Connexion...';
        try {
            const check = await fetch(WHISPER_URL + '/health', { signal: AbortSignal.timeout(3000) });
            if (check.ok) {
                isAiReady = true;
                modal.remove();
                toast('Serveur Whisper connecté !', 'success');
            } else {
                throw new Error('Non OK');
            }
        } catch (e) {
            toast('Serveur toujours inaccessible', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Réessayer';
        }
    });
}

// ----------------------------------------------------
// ENREGISTREMENT ET UPLOAD
// ----------------------------------------------------
function setupRecordingControls() {
    const btnStart = document.getElementById('btnStartRecord');
    const btnStop = document.getElementById('btnStopRecord');
    const btnPause = document.getElementById('btnPauseRecord');
    const btnTranscribe = document.getElementById('btnTranscribe');

    console.log('[PV] setupRecordingControls — btnStart found:', !!btnStart);
    if (btnStart) btnStart.addEventListener('click', startRecording);
    if (btnStop) btnStop.addEventListener('click', stopRecording);
    if (btnPause) btnPause.addEventListener('click', togglePauseRecording);
    if (btnTranscribe) btnTranscribe.addEventListener('click', transcribeRecordedAudio);

    const btnWhisper = document.getElementById('btnWhisperRetranscribe');
    if (btnWhisper) btnWhisper.addEventListener('click', whisperRetranscribe);

    const btnStructure = document.getElementById('btnStructure');
    if (btnStructure) btnStructure.addEventListener('click', structurePv);

    const btnCancel = document.getElementById('btnCancelProcessing');
    if (btnCancel) btnCancel.addEventListener('click', cancelCurrentOperation);

    // Modal transcription originale
    document.getElementById('btnShowOriginal')?.addEventListener('click', showOriginalModal);
    document.getElementById('btnCopyOriginal')?.addEventListener('click', copyOriginalText);
    document.getElementById('btnCopySelection')?.addEventListener('click', copySelectionText);
    document.getElementById('btnEmailOriginal')?.addEventListener('click', openSendEmailModal);
    document.getElementById('btnSendEmail')?.addEventListener('click', sendPvEmail);

    // Validation toggle
    const valToggle = document.getElementById('pvValidationRequired');
    if (valToggle) {
        valToggle.addEventListener('change', () => {
            document.getElementById('pvValidationOptions').classList.toggle('pv-visible', valToggle.checked);
        });
    }
}

// Variable pour stocker le blob audio après l'enregistrement
let lastRecordedBlob = null;

const OLLAMA_URL = 'http://localhost:11434';

// ── Progress bar helpers ──
let _progressInterval = null;
let _progressStart = 0;

function fmtTime(sec) {
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return m + ':' + String(s).padStart(2, '0');
}

/**
 * Start progress tracking
 * @param {string} label - Text to display
 * @param {number} estimatedSec - Estimated total seconds
 * @param {string} color - Progress bar color
 */
function startProgress(label, estimatedSec, color) {
    const wrap = document.getElementById('processingStatus');
    const labelEl = document.getElementById('processingLabel');
    const bar = document.getElementById('processingProgress');
    const elapsedEl = document.getElementById('processingElapsed');
    const estimateEl = document.getElementById('processingEstimate');
    const timerEl = document.getElementById('processingTimer');
    const cancelBtn = document.getElementById('btnCancelProcessing');

    wrap.classList.remove('pv-d-none');
    document.getElementById('processingProgressWrap').classList.remove('pv-d-none');
    labelEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + label;
    bar.style.width = '0%';
    bar.style.background = color || 'var(--cl-accent,#191918)';
    elapsedEl.textContent = '0:00';
    estimateEl.textContent = estimatedSec > 0 ? '~' + fmtTime(estimatedSec) + ' estimé' : '';
    timerEl.textContent = '';

    // Show cancel button if we have an abort controller
    if (cancelBtn) cancelBtn.classList.toggle('pv-d-none', !_currentAbortController);

    _progressStart = Date.now();
    clearInterval(_progressInterval);
    _progressInterval = setInterval(() => {
        const elapsed = (Date.now() - _progressStart) / 1000;
        elapsedEl.textContent = fmtTime(elapsed);
        if (estimatedSec > 0) {
            const pct = Math.min(95, (elapsed / estimatedSec) * 100);
            bar.style.width = pct + '%';
            const remaining = Math.max(0, estimatedSec - elapsed);
            if (remaining > 0) {
                timerEl.textContent = '~' + fmtTime(remaining) + ' restant';
            } else {
                timerEl.textContent = 'presque terminé…';
                bar.style.width = '95%';
            }
        } else {
            const pct = Math.min(90, elapsed * 2);
            bar.style.width = pct + '%';
            timerEl.textContent = fmtTime(elapsed);
        }
    }, 500);
}

function stopProgress() {
    clearInterval(_progressInterval);
    _currentAbortController = null;
    const bar = document.getElementById('processingProgress');
    const timerEl = document.getElementById('processingTimer');
    const cancelBtn = document.getElementById('btnCancelProcessing');
    if (bar) bar.style.width = '100%';
    if (timerEl) timerEl.textContent = '';
    if (cancelBtn) cancelBtn.classList.add('pv-d-none');
    setTimeout(() => {
        const wrap = document.getElementById('processingStatus');
        if (wrap) wrap.classList.add('pv-d-none');
        if (bar) bar.style.width = '0%';
    }, 600);
}

function showStructureBtn() {
    const btn = document.getElementById('btnStructure');
    if (!btn || !editorInstance) return;
    const text = editorInstance.getText().trim();
    btn.classList.toggle('pv-d-none', text.length <= 30);
}

// ── Blur + écriture virtuelle dans TipTap pendant la structuration IA ──
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

function startEditorBlur() {
    const container = document.getElementById('pvEditorContainer');
    if (!container || !editorInstance) return;

    // Sauvegarder le contenu dans localStorage et vider l'éditeur
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

function stopEditorBlur() {
    clearInterval(_twTimer);
    _twTimer = null;
    _twIdx = 0;
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

// ── Bouton Annuler ──
function cancelCurrentOperation() {
    if (_currentAbortController) {
        _currentAbortController.abort();
        _currentAbortController = null;
    }
    stopProgress();
    // Restaurer le contenu sauvegardé avant le blur
    _restoreSavedContent();
    stopEditorBlur();
    // Remettre les boutons à leur état normal
    const btnStructure = document.getElementById('btnStructure');
    if (btnStructure) { btnStructure.disabled = false; btnStructure.innerHTML = '<i class="bi bi-magic"></i> STRUCTURER LE PV'; }
    toast('Opération annulée', 'info');
}

// ── Modal transcription originale ──
function showOriginalModal() {
    if (!_originalTranscription) return;
    // Convertir le HTML en texte lisible
    const div = document.createElement('div');
    div.innerHTML = _originalTranscription;
    const container = document.getElementById('originalTextContent');
    container.innerHTML = _originalTranscription;
    const modal = new bootstrap.Modal(document.getElementById('modalOriginalText'));
    modal.show();
}

function copyOriginalText() {
    const container = document.getElementById('originalTextContent');
    const text = container.innerText || container.textContent;
    navigator.clipboard.writeText(text).then(() => toast('Texte copié !')).catch(() => toast('Erreur de copie', 'error'));
}

function copySelectionText() {
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed) { toast('Sélectionnez du texte d\'abord', 'error'); return; }
    navigator.clipboard.writeText(sel.toString()).then(() => toast('Sélection copiée !')).catch(() => toast('Erreur de copie', 'error'));
}

let _emailUsers = [];
let _emailFonctions = [];
let _emailSelectedIds = new Set();

function openSendEmailModal() {
    const container = document.getElementById('originalTextContent');
    const text = container.innerText || container.textContent;
    const titre = pvData?.titre || 'PV';
    document.getElementById('emailSubject').value = 'Transcription originale — ' + titre;
    document.getElementById('emailContent').value = text;

    // Reset filters
    document.getElementById('emailSearchInput').value = '';
    document.getElementById('emailSearchClear').classList.remove('pv-visible');
    const emailFonctionEl = document.getElementById('emailFonctionFilter');
    if (emailFonctionEl._zsInit) zerdaSelect.setValue(emailFonctionEl, '');

    if (_emailUsers.length === 0) {
        adminApiPost('admin_get_pv_refs').then(r => {
            if (r.success) {
                _emailUsers = r.users || [];
                _emailFonctions = r.fonctions || [];
                // Pre-select participants
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

let _emailFiltersBound = false;
function bindEmailFilterEvents() {
    if (_emailFiltersBound) return;
    _emailFiltersBound = true;

    const searchInput = document.getElementById('emailSearchInput');
    const clearBtn = document.getElementById('emailSearchClear');

    searchInput.addEventListener('input', () => {
        clearBtn.classList.toggle('pv-visible', searchInput.value.length > 0);
        renderEmailRecipients();
    });

    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearBtn.classList.remove('pv-visible');
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
            <div class="pv-rc-check"><i class="bi bi-check-lg pv-rc-check-icon"></i></div>
        </div>`;
    }).join('') || '<div class="text-muted small text-center py-3">Aucun résultat</div>';

    list.querySelectorAll('.pv-recipient-card').forEach(card => {
        card.addEventListener('click', () => {
            const uid = card.dataset.uid;
            if (_emailSelectedIds.has(uid)) {
                _emailSelectedIds.delete(uid);
                card.classList.remove('selected');
            } else {
                _emailSelectedIds.add(uid);
                card.classList.add('selected');
            }
            updateRecipientsCount();
        });
    });
    updateRecipientsCount();
}

function updateRecipientsCount() {
    const count = _emailSelectedIds.size;
    const badge = document.getElementById('emailRecipientsCount');
    badge.textContent = count;
    badge.classList.toggle('pv-visible', count > 0);
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

let _structuredContent = null;

async function setupVisualizer(stream) {
    try {
        // Créer un nouveau stream dédié au visualiseur pour ne pas interférer avec MediaRecorder
        const visStream = stream.clone();

        // Débloquer l'audio Firefox : jouer le stream dans un élément audio muet
        const unlockAudio = document.createElement('audio');
        unlockAudio.srcObject = visStream;
        unlockAudio.muted = true;
        unlockAudio.volume = 0;
        await unlockAudio.play().catch(() => {});

        visAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        await visAudioContext.resume();
        console.log('[PV] AudioContext state after unlock:', visAudioContext.state);

        visAnalyser = visAudioContext.createAnalyser();
        visAnalyser.fftSize = 256;
        visAnalyser.smoothingTimeConstant = 0.5;
        visAnalyser.minDecibels = -90;
        visAnalyser.maxDecibels = -10;

        // IMPORTANT: stocker la référence source pour empêcher le garbage collection
        visSource = visAudioContext.createMediaStreamSource(visStream);
        visSource.connect(visAnalyser);
        visDataArray = new Uint8Array(visAnalyser.frequencyBinCount);

        // Nettoyer l'élément audio de déverrouillage (ne pas toucher au visStream)
        unlockAudio.srcObject = null;
        unlockAudio.remove();

        const track = visStream.getAudioTracks()[0];
        console.log('[PV] Visualizer setup OK — track enabled:', track.enabled, 'muted:', track.muted, 'readyState:', track.readyState);
    } catch (e) {
        console.warn('[PV] Visualizer setup failed:', e);
    }
}

function startVisualizer() {
    const canvas = document.getElementById('audioVisualizer');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const W = canvas.width;
    const H = canvas.height;
    const barCount = 24;
    const barGap = 1;
    const barWidth = (W - (barCount - 1) * barGap) / barCount;
    const smoothBars = new Float32Array(barCount);

    visRunning = true;
    let logCount = 0;

    function draw() {
        if (!visRunning) return;
        visAnimationId = requestAnimationFrame(draw);

        if (visAnalyser && visDataArray) {
            visAnalyser.getByteTimeDomainData(visDataArray);

            // Log les premières frames pour debug
            if (logCount < 5) {
                let maxDev = 0;
                for (let i = 0; i < visDataArray.length; i++) {
                    maxDev = Math.max(maxDev, Math.abs(visDataArray[i] - 128));
                }
                console.log('[PV] Visualizer frame', logCount, '— max deviation from silence:', maxDev, '/ bufferLen:', visDataArray.length);
                logCount++;
            }

            const segLen = Math.floor(visDataArray.length / barCount);
            for (let b = 0; b < barCount; b++) {
                let sum = 0;
                const offset = b * segLen;
                for (let j = 0; j < segLen; j++) {
                    const sample = (visDataArray[offset + j] - 128) / 128;
                    sum += sample * sample;
                }
                const rms = Math.sqrt(sum / segLen);
                const target = isPaused ? 0 : Math.min(1, rms * 4);
                smoothBars[b] += (target - smoothBars[b]) * 0.3;
            }
        }

        // Clear
        ctx.fillStyle = isPaused ? '#F7F5F2' : '#FFF8F4';
        ctx.fillRect(0, 0, W, H);

        // Draw bars
        let x = 0;
        for (let i = 0; i < barCount; i++) {
            const v = smoothBars[i];
            const barH = Math.max(2, v * H);
            const alpha = 0.4 + v * 0.6;
            ctx.fillStyle = `rgba(217, 119, 87, ${alpha})`;
            const radius = Math.min(barWidth / 2, 2);
            const y = H - barH;
            ctx.beginPath();
            ctx.moveTo(x, H);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.lineTo(x + barWidth - radius, y);
            ctx.quadraticCurveTo(x + barWidth, y, x + barWidth, y + radius);
            ctx.lineTo(x + barWidth, H);
            ctx.closePath();
            ctx.fill();
            x += barWidth + barGap;
        }
    }
    draw();
}

function stopVisualizer() {
    visRunning = false;
    if (visAnimationId) {
        cancelAnimationFrame(visAnimationId);
        visAnimationId = null;
    }
    // Déconnecter et nettoyer la source
    if (visSource) {
        visSource.disconnect();
        // Arrêter les tracks du stream cloné
        if (visSource.mediaStream) {
            visSource.mediaStream.getAudioTracks().forEach(t => t.stop());
        }
        visSource = null;
    }
    visAnalyser = null;
    visDataArray = null;
    // Fermer le contexte audio pour libérer les ressources
    if (visAudioContext) {
        visAudioContext.close().catch(() => {});
        visAudioContext = null;
    }
    const canvas = document.getElementById('audioVisualizer');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#F7F5F2';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
}

async function startRecording() {
    if (isRecording) return; // éviter double-clic
    console.log('[PV] startRecording() called, externalMode:', externalMode, 'isAiReady:', isAiReady);
    // En mode local, tenter de détecter le serveur (mais ne pas bloquer l'enregistrement)
    if (!externalMode && !isAiReady) {
        try {
            const check = await fetch(WHISPER_URL + '/health', { signal: AbortSignal.timeout(2000) });
            if (check.ok) {
                isAiReady = true;
            }
        } catch (e) { /* serveur pas dispo */ }

        if (!isAiReady) {
            // Avertir mais ne pas bloquer — l'enregistrement fonctionne, la transcription viendra après
            toast('Serveur de transcription non détecté — l\'enregistrement fonctionne, la transcription sera disponible quand le serveur sera lancé.', 'error');
        }
    }

    try {
        console.log('[PV] Requesting microphone...');
        audioStream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
        });
        const audioTrack = audioStream.getAudioTracks()[0];
        console.log('[PV] Microphone OK — stream active:', audioStream.active,
            '| track enabled:', audioTrack.enabled,
            '| track muted:', audioTrack.muted,
            '| track readyState:', audioTrack.readyState,
            '| track label:', audioTrack.label,
            '| track settings:', JSON.stringify(audioTrack.getSettings()));
        recordedChunks = [];
        totalRecordingTime = 0;
        isPaused = false;

        // Setup Visualizer — lancer en parallèle, await plus tard avant startVisualizer
        const visReady = setupVisualizer(audioStream);

        console.log('[PV] Creating MediaRecorder...');
        mainRecorder = new MediaRecorder(audioStream);
        mainRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recordedChunks.push(e.data);
        };
        mainRecorder.onstop = async () => {
            if (recordedChunks.length > 0) {
                lastRecordedBlob = new Blob(recordedChunks, { type: 'audio/webm' });
                await uploadAudioBlob(lastRecordedBlob, 'recorded_pv.webm');
                // Afficher le bouton Transcrire
                document.getElementById('btnTranscribe').classList.remove('pv-d-none');
                if (externalMode) {
                    document.getElementById('btnTranscribe').textContent = 'TRANSCRIRE (Cloud)';
                }
                // Afficher le bouton Whisper si disponible (mode local uniquement)
                if (!externalMode && whisperAvailable) {
                    document.getElementById('btnWhisperRetranscribe').classList.remove('pv-d-none');
                }
            }
        };
        mainRecorder.start();
        console.log('[PV] MediaRecorder started, state:', mainRecorder.state);

        // Transcription en direct
        if (externalMode && deepgramApiKey) {
            console.log('[PV] Starting Deepgram live transcriber...');
            startLiveTranscriberExternal();
        } else if (!externalMode) {
            startLiveTranscriber();
        }

        isRecording = true;
        lastRecordedBlob = null;

        // Afficher l'UI AVANT le visualizer (le canvas doit être visible)
        document.getElementById('btnStartRecord').classList.add('pv-d-none');
        document.getElementById('btnPauseRecord').classList.remove('pv-d-none');
        document.getElementById('btnStopRecord').classList.remove('pv-d-none');
        document.getElementById('btnTranscribe').classList.add('pv-d-none');
        document.getElementById('btnWhisperRetranscribe').classList.add('pv-d-none');
        document.getElementById('recordingStatus').classList.remove('pv-d-none');

        // Attendre que le visualizer soit prêt, puis démarrer l'animation
        await visReady;
        requestAnimationFrame(() => startVisualizer());
        console.log('[PV] Recording UI activated');
        if (externalMode) {
            document.getElementById('recordingSubtext').textContent = 'Le texte s\'affiche en temps réel via Deepgram (cloud).';
        }

        recordingInterval = setInterval(() => {
            if (isRecording && !isPaused) {
                totalRecordingTime++;
                const m = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
                const s = String(totalRecordingTime % 60).padStart(2, '0');
                document.getElementById('recordingTime').textContent = `${m}:${s}`;
            }
        }, 1000);

    } catch (err) {
        console.error('[PV] startRecording ERROR:', err);
        toast('Erreur d\'accès au microphone : ' + err.message, 'error');
    }
}

function startLiveTranscriber() {
    liveRecorder = new MediaRecorder(audioStream);
    liveRecorder.ondataavailable = (e) => {
        if (isPaused) return; // Ignorer le chunk si en pause
        if (e.data.size > 0) {
            const blob = new Blob([e.data], { type: 'audio/webm' });
            transcribingQueue = transcribingQueue.then(() => liveTranscribeBlob(blob));
        }
    };
    liveRecorder.start();

    liveInterval = setInterval(() => {
        if (isRecording && !isPaused && liveRecorder.state === 'recording') {
            liveRecorder.stop();
            liveRecorder.start();
        }
    }, 5000);
}

// ── Transcription temps réel via Deepgram WebSocket ──
function startLiveTranscriberExternal() {
    if (!deepgramApiKey || !audioStream) return;

    const url = 'wss://api.deepgram.com/v1/listen?language=fr&model=nova-2&punctuate=true&smart_format=true';
    deepgramSocket = new WebSocket(url, ['token', deepgramApiKey]);

    deepgramSocket.onopen = () => {
        console.log('[Deepgram] WebSocket connecté — transcription temps réel active');

        // Cloner le stream pour éviter conflit avec mainRecorder (Firefox)
        const clonedStream = audioStream.clone();
        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
        const opts = mimeType ? { mimeType } : {};
        liveRecorder = new MediaRecorder(clonedStream, opts);
        console.log('[Deepgram] LiveRecorder mimeType:', liveRecorder.mimeType);
        liveRecorder.ondataavailable = (e) => {
            if (isPaused || !deepgramSocket || deepgramSocket.readyState !== WebSocket.OPEN) return;
            if (e.data.size > 0) {
                deepgramSocket.send(e.data);
            }
        };
        liveRecorder.start(250); // envoyer un chunk toutes les 250ms
        console.log('[Deepgram] LiveRecorder started, state:', liveRecorder.state);

        // Afficher l'indicateur de transcription
        const ps = document.getElementById('processingStatus');
        ps.classList.remove('pv-d-none');
        document.getElementById('processingProgressWrap').classList.add('pv-d-none');
        document.getElementById('processingLabel').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deepgram transcrit en temps réel…';
        document.getElementById('processingTimer').textContent = '';
        document.getElementById('processingElapsed').textContent = '';
        document.getElementById('processingEstimate').textContent = '';
    };

    deepgramSocket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            const transcript = data.channel?.alternatives?.[0]?.transcript;
            if (transcript && transcript.trim() && data.is_final) {
                if (editorInstance) {
                    editorInstance.commands.insertContent(transcript.trim() + ' ');
                    const el = editorInstance.view.dom;
                    el.scrollTop = el.scrollHeight;
                    showStructureBtn();
                }
            }
        } catch (e) {
            console.error('[Deepgram] Erreur parsing:', e);
        }
    };

    deepgramSocket.onerror = (e) => {
        console.error('[Deepgram] WebSocket erreur:', e);
        toast('Erreur connexion Deepgram', 'error');
    };

    deepgramSocket.onclose = (e) => {
        console.log('[Deepgram] WebSocket fermé:', e.code, e.reason);
        if (isRecording) {
            document.getElementById('processingStatus').classList.add('pv-d-none');
        }
    };
}

function stopLiveTranscriberExternal() {
    if (liveRecorder) {
        if (liveRecorder.state !== 'inactive') liveRecorder.stop();
        // Arrêter les tracks du stream cloné
        if (liveRecorder.stream) {
            liveRecorder.stream.getTracks().forEach(t => t.stop());
        }
    }
    if (deepgramSocket && deepgramSocket.readyState === WebSocket.OPEN) {
        // Envoyer un message vide pour signaler la fin
        deepgramSocket.send(new Uint8Array(0));
        setTimeout(() => {
            if (deepgramSocket) deepgramSocket.close();
            deepgramSocket = null;
        }, 500);
    }
}

function togglePauseRecording() {
    if (!isRecording) return;
    
    isPaused = !isPaused;
    const btnPause = document.getElementById('btnPauseRecord');
    const recordingStatus = document.getElementById('recordingStatus');
    const indicator = document.getElementById('recordingIndicator');
    const statusText = document.getElementById('recordingStatusText');
    
    if (isPaused) {
        if (mainRecorder && mainRecorder.state === 'recording') mainRecorder.pause();
        if (liveRecorder && liveRecorder.state === 'recording') liveRecorder.pause();

        btnPause.innerHTML = '<i class="bi bi-play-fill"></i> REPRENDRE';
        btnPause.className = 'btn shadow-sm pv-btn-resume';

        recordingStatus.classList.add('paused', 'pv-recording-paused');
        recordingStatus.classList.remove('pv-recording-active');
        statusText.textContent = 'Dictée en pause...';
    } else {
        if (mainRecorder && mainRecorder.state === 'paused') mainRecorder.resume();
        if (liveRecorder && liveRecorder.state === 'paused') liveRecorder.resume();

        btnPause.innerHTML = '<i class="bi bi-pause-fill"></i> PAUSE';
        btnPause.className = 'btn shadow-sm pv-btn-pause';

        recordingStatus.classList.remove('paused', 'pv-recording-paused');
        recordingStatus.classList.add('pv-recording-active');
        statusText.textContent = 'Écoute en cours... Parlez distinctement.';
    }
}

function stopRecording() {
    if (!isRecording) return;

    if (mainRecorder && mainRecorder.state !== 'inactive') mainRecorder.stop();

    clearInterval(liveInterval);
    if (externalMode) {
        stopLiveTranscriberExternal();
    } else {
        if (liveRecorder && liveRecorder.state !== 'inactive') liveRecorder.stop();
    }

    if (audioStream) {
        audioStream.getTracks().forEach(track => track.stop());
    }

    stopVisualizer();

    clearInterval(recordingInterval);
    isRecording = false;

    document.getElementById('btnStartRecord').classList.remove('pv-d-none');
    document.getElementById('btnPauseRecord').classList.add('pv-d-none');
    document.getElementById('btnStopRecord').classList.add('pv-d-none');
    document.getElementById('recordingStatus').classList.add('pv-d-none');

    // Afficher le temps total enregistré et garder le chrono visible
    const totalMin = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
    const totalSec = String(totalRecordingTime % 60).padStart(2, '0');
    document.getElementById('recordingTime').textContent = `${totalMin}:${totalSec}`;

    document.getElementById('processingStatus').classList.add('pv-d-none');
    showStructureBtn();
}

// ── Transcrire l'audio enregistré (post-enregistrement) ──
async function transcribeRecordedAudio() {
    if (!lastRecordedBlob) {
        toast('Aucun enregistrement disponible', 'error');
        return;
    }

    if (!isAiReady && !externalMode) {
        showWhisperAlert();
        return;
    }

    const btn = document.getElementById('btnTranscribe');
    const totalMin = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
    const totalSec = String(totalRecordingTime % 60).padStart(2, '0');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Transcription en cours (' + totalMin + ':' + totalSec + ')...';

    document.getElementById('processingStatus').classList.remove('pv-d-none');

    try {
        await transcribeFullAudioBlob(lastRecordedBlob);
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Transcription terminée';
        btn.classList.add('pv-btn-transcribe-done');
        setTimeout(() => {
            btn.classList.add('pv-d-none');
            btn.classList.remove('pv-btn-transcribe-done');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-body-text"></i> TRANSCRIRE L\'AUDIO';
        }, 3000);
    } catch (e) {
        toast('Erreur de transcription: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-body-text"></i> TRANSCRIRE L\'AUDIO';
    }
}

// ── Re-transcrire avec Whisper (haute précision, post-enregistrement) ──
async function whisperRetranscribe() {
    if (!lastRecordedBlob) {
        toast('Aucun enregistrement disponible', 'error');
        return;
    }
    if (!isAiReady) {
        showWhisperAlert();
        return;
    }

    const btn = document.getElementById('btnWhisperRetranscribe');
    const totalMin = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
    const totalSec = String(totalRecordingTime % 60).padStart(2, '0');
    const estimatedMin = Math.max(1, Math.ceil(totalRecordingTime / 60 * 0.25)); // ~25% du temps réel

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Whisper en cours (~' + estimatedMin + ' min)...';

    // Whisper: ~25% real-time speed on CPU (4x slower than real-time)
    const estSec = Math.max(10, Math.ceil(totalRecordingTime * 0.25));
    startProgress('Whisper analyse l\'audio complet (' + totalMin + ':' + totalSec + ')…', estSec, '#5B4B6B');

    try {
        const res = await fetch(WHISPER_URL + '/transcribe?engine=whisper', {
            method: 'POST',
            headers: { 'Content-Type': lastRecordedBlob.type || 'audio/webm' },
            body: lastRecordedBlob,
        });

        const data = await res.json();

        if (data.success && data.text && data.text.trim().length > 0) {
            if (editorInstance) {
                // Remplacer tout le contenu par la transcription Whisper
                editorInstance.commands.setContent('<p>' + data.text.trim() + '</p>');
                showStructureBtn();
            }
            toast('Transcription Whisper terminée — texte remplacé !');
        } else if (data.error) {
            throw new Error(data.error);
        } else {
            throw new Error('Réponse vide');
        }

        btn.innerHTML = '<i class="bi bi-check-lg"></i> Whisper terminé';
        btn.classList.remove('pv-btn-whisper');
        btn.classList.add('pv-btn-whisper-done');
        setTimeout(() => {
            btn.disabled = false;
            btn.classList.remove('pv-btn-whisper-done');
            btn.classList.add('pv-btn-whisper');
            btn.innerHTML = '<i class="bi bi-stars"></i> WHISPER <small class="pv-whisper-small">(précis)</small>';
        }, 3000);
    } catch (e) {
        console.error('[Whisper] Erreur:', e);
        toast('Erreur Whisper: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-stars"></i> WHISPER <small class="pv-whisper-small">(précis)</small>';
    } finally {
        stopProgress();
    }
}

function setupFileUpload() {
    const fileInput = document.getElementById('audioFileInput');
    const btnUpload = document.getElementById('btnUploadAudio');
    const dropZone = document.getElementById('audioDropZone');
    const preview = document.getElementById('audioFilePreview');
    const btnRemove = document.getElementById('btnRemoveAudio');
    const btnRetranscribe = document.getElementById('btnRetranscribeAudio');

    // Drop zone click → open file dialog
    if (dropZone) dropZone.addEventListener('click', () => fileInput.click());

    // Drag & drop
    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showFilePreview(e.dataTransfer.files[0]);
            }
        });
    }

    // File selected
    if (fileInput) fileInput.addEventListener('change', () => {
        if (fileInput.files.length) showFilePreview(fileInput.files[0]);
    });

    function showFilePreview(file) {
        document.getElementById('audioFileName').textContent = file.name;
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        document.getElementById('audioFileSize').textContent = sizeMB + ' Mo';
        dropZone.classList.add('pv-d-none');
        preview.classList.add('pv-visible');
    }

    // Remove file
    if (btnRemove) btnRemove.addEventListener('click', () => {
        fileInput.value = '';
        preview.classList.remove('pv-visible');
        dropZone.classList.remove('pv-d-none');
    });

    // Upload & transcribe
    if (btnUpload) btnUpload.addEventListener('click', async () => {
        if (!fileInput.files.length) {
            toast('Veuillez sélectionner un fichier', 'error');
            return;
        }
        const file = fileInput.files[0];
        btnUpload.disabled = true;
        btnUpload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Transcription...';

        const uploadSuccess = await uploadAudioBlob(file, file.name);

        if (uploadSuccess) {
            await transcribeFullAudioBlob(file);
        }

        btnUpload.disabled = false;
        btnUpload.innerHTML = '<i class="bi bi-cloud-upload me-1"></i>Transcrire';
        fileInput.value = '';
        preview.classList.remove('pv-visible');
        dropZone.classList.remove('pv-d-none');
    });

    // Retranscribe existing audio
    if (btnRetranscribe) btnRetranscribe.addEventListener('click', async () => {
        const audioPlayer = document.getElementById('audioPlayback');
        if (!audioPlayer || !audioPlayer.src) { toast('Aucun audio disponible', 'error'); return; }

        btnRetranscribe.disabled = true;
        btnRetranscribe.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Transcription...';

        try {
            if (editorInstance) editorInstance.commands.setContent('');
            const res = await fetch(audioPlayer.src);
            const blob = await res.blob();
            await transcribeFullAudioBlob(blob);
        } catch (e) {
            toast('Erreur lors de la retranscription: ' + e.message, 'error');
        }

        btnRetranscribe.disabled = false;
        btnRetranscribe.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Retranscrire';
    });
}

async function uploadAudioBlob(blob, filename) {
    const formData = new FormData();
    formData.append('action', 'admin_upload_pv_audio');
    formData.append('id', pvId);
    formData.append('audio', blob, filename);

    try {
        const headers = {};
        if (window.__SS_ADMIN__?.csrfToken) {
            headers['X-CSRF-Token'] = window.__SS_ADMIN__.csrfToken;
        }

        const res = await fetch('/spocspace/admin/api.php', {
            method: 'POST',
            headers: headers,
            body: formData
        });

        const json = await res.json();
        if (json.csrf && window.__SS_ADMIN__) window.__SS_ADMIN__.csrfToken = json.csrf;

        if (json.success) {
            if (json.audio_path) showAudioPlayback('/spocspace/admin/api.php?action=admin_serve_pv_audio&id=' + pvId);
            return true;
        } else {
            toast('Erreur: ' + (json.message || 'Échec de la sauvegarde audio'), 'error');
            return false;
        }
    } catch (e) {
        toast('Erreur réseau lors de la sauvegarde audio', 'error');
        return false;
    }
}

// Global expose for save button
async function savePv() {
  const transcript = editorInstance && editorModule ? editorModule.getHTML(editorInstance) : '';
  const allowComments = document.getElementById('pvAllowComments').checked ? 1 : 0;
  
  if (!transcript || transcript === '<p></p>') {
      toast('Veuillez dicter ou écrire du contenu', 'error');
      return;
  }

  const btn = document.getElementById('btnSavePv');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

  // Sauvegarder les paramètres de validation
  const validationRequired = document.getElementById('pvValidationRequired').checked ? 1 : 0;
  const validationRole = validationRequired ? zerdaSelect.getValue('#pvValidationRole') : null;

  try {
    const r = await adminApiPost('admin_update_pv', {
      id: pvId,
      contenu: transcript,
      allow_comments: allowComments,
      validation_required: validationRequired,
      validation_role: validationRole,
    });

    if (r.success) {
      const f = await adminApiPost('admin_finalize_pv', { id: pvId });
      if (f.success) {
        const msg = f.statut === 'en_validation'
          ? 'PV enregistré et soumis pour validation !'
          : 'PV enregistré et finalisé !';
        toast(msg);
        setTimeout(() => {
          window.location.href = AdminURL.page('pv');
        }, 1500);
      } else {
        toast('Erreur lors de la finalisation', 'error');
        resetSaveBtn(btn);
      }
    } else {
      toast('Erreur lors de la sauvegarde du contenu', 'error');
      resetSaveBtn(btn);
    }
  } catch (e) {
    toast('Erreur: ' + e.message, 'error');
    resetSaveBtn(btn);
  }
};

// ── Structurer le PV via Ollama (local) ou Claude/Gemini (externe) ──
async function structurePv() {
    if (!editorInstance) return;

    const rawText = editorInstance.getText().trim();
    if (rawText.length < 30) {
        toast('Pas assez de texte à structurer', 'error');
        return;
    }

    const btn = document.getElementById('btnStructure');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Structuration en cours...';

    // Sauvegarder la transcription originale avant structuration
    const originalHtml = editorModule.getHTML(editorInstance);
    _originalTranscription = originalHtml;
    _showingOriginal = false;
    _structuredContent = null;

    // Sauvegarder en DB
    if (pvId) {
        adminApiPost('admin_update_pv', { id: pvId, transcription_brute: originalHtml }).catch(() => {});
    }

    const prompt = buildStructurePrompt(rawText);

    // AbortController pour permettre l'annulation
    _currentAbortController = new AbortController();
    const signal = _currentAbortController.signal;

    // Blur + typewriter
    startEditorBlur();

    // ── Mode cloud structuration : Claude/Gemini via backend API ──
    if (structurationCloud) {
        const estSec = Math.max(8, Math.ceil(rawText.length / 1000 * 5));
        startProgress('Structuration IA cloud…', estSec, '#D0C4D8');

        try {
            const res = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '',
                },
                body: JSON.stringify({ action: 'admin_structure_pv_external', text: rawText, prompt }),
                signal,
            });
            const data = await res.json();
            if (data.csrf) window.__SS_ADMIN__.csrfToken = data.csrf;

            if (data.success && data.html && data.html.length > 20) {
                editorInstance.commands.setContent(data.html);
                _structuredContent = data.html;
                const providerLabel = (data.provider === 'claude' ? 'Claude' : 'Gemini');
                toast('PV structuré via ' + providerLabel + ' !');
                document.getElementById('btnShowOriginal').classList.remove('pv-d-none');
            } else {
                _restoreSavedContent();
                toast('La structuration n\'a pas produit de résultat exploitable', 'error');
            }
        } catch (e) {
            if (e.name === 'AbortError') return; // annulé par l'utilisateur
            _restoreSavedContent();
            console.error('[External] Erreur structuration:', e);
            toast('Erreur de structuration: ' + (e.message || 'erreur réseau'), 'error');
        } finally {
            stopProgress();
            stopEditorBlur();
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-magic"></i> STRUCTURER LE PV';
        }
        return;
    }

    // ── Mode local : Ollama ──
    let ollamaOk = false;
    try {
        const check = await fetch(OLLAMA_URL + '/api/tags', { signal: AbortSignal.timeout(3000) });
        ollamaOk = check.ok;
    } catch (e) {}

    if (!ollamaOk) {
        toast('Ollama hors ligne — lancez le raccourci « SpocSpace IA » sur votre Bureau.', 'error');
        _restoreSavedContent();
        stopEditorBlur();
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-magic"></i> STRUCTURER LE PV';
        _currentAbortController = null;
        return;
    }

    const estSec = Math.max(10, Math.ceil(rawText.length / 1000 * 15));
    startProgress('Structuration IA en cours…', estSec, '#D0C4D8');

    try {
        const res = await fetch(OLLAMA_URL + '/api/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: ollamaModel,
                prompt: prompt,
                stream: false
            }),
            signal,
        });

        if (!res.ok) throw new Error('Erreur Ollama: ' + res.status);

        const data = await res.json();
        let html = (data.response || '').trim();

        html = html.replace(/^```html?\s*/i, '').replace(/```\s*$/, '').trim();

        if (html.length > 20) {
            editorInstance.commands.setContent(html);
            _structuredContent = html;
            toast('PV structuré avec succès !');
            document.getElementById('btnShowOriginal').classList.remove('pv-d-none');
        } else {
            _restoreSavedContent();
            toast('La structuration n\'a pas produit de résultat exploitable', 'error');
        }
    } catch (e) {
        if (e.name === 'AbortError') return; // annulé par l'utilisateur
        _restoreSavedContent();
        console.error('[Ollama] Erreur:', e);
        toast('Erreur de structuration: ' + e.message, 'error');
    } finally {
        stopProgress();
        stopEditorBlur();
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-magic"></i> STRUCTURER LE PV';
    }
}

function resetSaveBtn(btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer et finaliser le PV';
}

// ── Server status monitoring (compact indicator) ──
const _srvState = { vosk: 'checking', whisper: 'checking', ollama: 'checking' };

// Toggle dropdown on click
document.getElementById('srvIndicator')?.addEventListener('click', (e) => {
    e.stopPropagation();
    document.getElementById('srvDropdown')?.classList.toggle('open');
});
document.addEventListener('click', () => {
    document.getElementById('srvDropdown')?.classList.remove('open');
});

function updateBadge(id, status) {
    // Map old badge IDs to new row IDs
    const map = { badgeVosk: 'vosk', badgeWhisper: 'whisper', badgeOllama: 'ollama' };
    const key = map[id];
    if (!key) return;

    const prev = _srvState[key];
    if (prev === status) return;
    _srvState[key] = status;

    // Update dropdown row
    const rowId = 'row' + key.charAt(0).toUpperCase() + key.slice(1);
    const row = document.getElementById(rowId);
    if (row) {
        const dot = row.querySelector('.srv-dd-dot');
        const statusEl = row.querySelector('.srv-dd-status');
        if (dot) { dot.className = 'srv-dd-dot ' + (status === 'online' ? 'on' : status === 'offline' ? 'off' : 'wait'); }
        if (statusEl) { statusEl.textContent = status === 'online' ? 'Connecté' : status === 'offline' ? 'Hors ligne' : '…'; }
    }

    // Update global indicator color
    updateGlobalIndicator();
}

function updateGlobalIndicator() {
    const indicator = document.getElementById('srvIndicator');
    if (!indicator) return;

    // Collect relevant states (skip cloud services)
    const states = [];
    if (!transcriptionCloud) states.push(_srvState.vosk);
    if (!structurationCloud) states.push(_srvState.ollama);

    // If everything is cloud, show green
    if (states.length === 0) { indicator.dataset.global = 'ok'; return; }

    const online = states.filter(s => s === 'online').length;
    const checking = states.filter(s => s === 'checking').length;

    if (checking > 0 && online === 0) indicator.dataset.global = 'checking';
    else if (online === states.length) indicator.dataset.global = 'ok';
    else if (online > 0) indicator.dataset.global = 'partial';
    else indicator.dataset.global = 'error';
}

async function checkServers() {
    // Check local transcription server (skip if cloud transcription)
    if (!transcriptionCloud) {
        try {
            const r = await fetch(WHISPER_URL + '/health', { signal: AbortSignal.timeout(3000) });
            if (r.ok) {
                const data = await r.json();
                const engines = data.engines || ['vosk'];
                whisperAvailable = engines.includes('whisper');
                const rowW = document.getElementById('rowWhisper');
                if (rowW) rowW.classList.toggle('pv-d-none', !whisperAvailable);
            }
            updateBadge('badgeVosk', r.ok ? 'online' : 'offline');
            if (whisperAvailable) updateBadge('badgeWhisper', r.ok ? 'online' : 'offline');
        } catch {
            updateBadge('badgeVosk', 'offline');
            updateBadge('badgeWhisper', 'offline');
            whisperAvailable = false;
        }
    } else {
        // Cloud transcription — mark as online, hide local rows
        document.getElementById('rowVosk')?.classList.add('pv-d-none');
        document.getElementById('rowWhisper')?.classList.add('pv-d-none');
        // Show cloud row
        let rowCloud = document.getElementById('rowDeepgram');
        if (!rowCloud) {
            const dd = document.getElementById('srvDropdown');
            if (dd) {
                const r = document.createElement('div');
                r.className = 'srv-dropdown-row';
                r.id = 'rowDeepgram';
                r.innerHTML = '<span class="srv-dd-dot on"></span><span class="srv-dd-name">Deepgram</span><span class="srv-dd-status">cloud</span>';
                dd.prepend(r);
            }
        }
    }

    // Check Ollama (skip if cloud structuration)
    if (!structurationCloud) {
        try {
            const r = await fetch(OLLAMA_URL + '/api/tags', { signal: AbortSignal.timeout(3000) });
            updateBadge('badgeOllama', r.ok ? 'online' : 'offline');
        } catch {
            updateBadge('badgeOllama', 'offline');
        }
    } else {
        document.getElementById('rowOllama')?.classList.add('pv-d-none');
        let rowIA = document.getElementById('rowIACloud');
        if (!rowIA) {
            const dd = document.getElementById('srvDropdown');
            if (dd) {
                const r = document.createElement('div');
                r.className = 'srv-dropdown-row';
                r.id = 'rowIACloud';
                r.innerHTML = '<span class="srv-dd-dot on"></span><span class="srv-dd-name">IA Cloud</span><span class="srv-dd-status">actif</span>';
                dd.append(r);
            }
        }
        updateGlobalIndicator();
    }
}

})();
</script>