<?php
/**
 * Website Admin — Gestion des actualités et activités à venir
 */
require_once __DIR__ . '/../../init.php';

if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    header('Location: /spocspace/login');
    exit;
}

$user = $_SESSION['ss_user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actualités — Admin Site Vitrine</title>
<meta name="csrf-token" content="<?= h($_SESSION['ss_csrf_token'] ?? '') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<script src="/spocspace/website/admin/assets/js/bootstrap-icons-data.js" defer></script>
<style>
/* ── Styles TipTap éditeur ── */
#aaEditorContainer .zs-ed-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  padding: 8px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}
#aaEditorContainer .zs-ed-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #4b5563;
  font-size: .9rem;
  transition: all .15s;
}
#aaEditorContainer .zs-ed-btn:hover { background: #fff; border-color: #e5e7eb; color: #111827; }
#aaEditorContainer .zs-ed-btn.active { background: #e8f5e9; color: #2E7D32; border-color: rgba(46,125,50,.25); }
#aaEditorContainer .zs-ed-sep { width: 1px; background: #e5e7eb; margin: 4px 4px; }
#aaEditorContainer .zs-ed-content {
  padding: 16px 20px;
  min-height: 240px;
  max-height: 500px;
  overflow-y: auto;
}
#aaEditorContainer .zs-ed-content .ProseMirror {
  outline: none;
  min-height: 200px;
  font-size: .95rem;
  line-height: 1.65;
  color: #111827;
}
#aaEditorContainer .zs-ed-content .ProseMirror p { margin: 0 0 10px; }
#aaEditorContainer .zs-ed-content .ProseMirror h2 { font-size: 1.35rem; font-weight: 600; margin: 18px 0 10px; color: #111827; }
#aaEditorContainer .zs-ed-content .ProseMirror h3 { font-size: 1.1rem; font-weight: 600; margin: 14px 0 8px; color: #111827; }
#aaEditorContainer .zs-ed-content .ProseMirror ul,
#aaEditorContainer .zs-ed-content .ProseMirror ol { padding-left: 24px; margin: 0 0 10px; }
#aaEditorContainer .zs-ed-content .ProseMirror blockquote {
  border-left: 3px solid #81C784;
  padding: 4px 14px;
  margin: 12px 0;
  color: #4b5563;
  font-style: italic;
}
#aaEditorContainer .zs-ed-content .ProseMirror a { color: #2E7D32; text-decoration: underline; }
#aaEditorContainer .zs-ed-content .ProseMirror img,
#aaEditorContainer .zs-ed-content .ProseMirror .zs-ed-img {
  max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0;
}
#aaEditorContainer .zs-ed-content .ProseMirror table {
  border-collapse: collapse; margin: 12px 0; width: 100%;
}
#aaEditorContainer .zs-ed-content .ProseMirror th,
#aaEditorContainer .zs-ed-content .ProseMirror td {
  border: 1px solid #e5e7eb; padding: 6px 10px;
}
#aaEditorContainer .zs-ed-content .ProseMirror p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
  float: left;
  height: 0;
}

* { box-sizing: border-box; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: #f5f7fa;
  color: #1f2937;
  margin: 0;
  line-height: 1.5;
}

/* Topbar */
.aa-topbar {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  padding: 14px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
}
.aa-topbar h1 {
  font-size: 1.05rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.aa-topbar h1 i { color: #2E7D32; }
.aa-topbar-actions { display: flex; gap: 10px; align-items: center; }
.aa-btn-ghost {
  padding: 8px 16px;
  background: transparent;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: .85rem;
  color: #4b5563;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all .2s;
}
.aa-btn-ghost:hover { background: #f3f4f6; color: #111827; }

.aa-btn-primary {
  padding: 10px 20px;
  background: #2E7D32;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .2s;
}
.aa-btn-primary:hover { background: #1B5E20; }

/* Main */
.aa-main { max-width: 1200px; margin: 0 auto; padding: 28px; }

/* Tabs */
.aa-tabs {
  display: flex;
  gap: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 20px;
  width: fit-content;
}
.aa-tab {
  padding: 9px 20px;
  background: transparent;
  border: none;
  border-radius: 8px;
  font-size: .87rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 7px;
}
.aa-tab:hover { color: #111827; background: #f9fafb; }
.aa-tab.active { background: #2E7D32; color: #fff; }

.aa-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

/* List */
.aa-list { display: flex; flex-direction: column; gap: 14px; }
.aa-item {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px 22px;
  display: grid;
  grid-template-columns: 120px 1fr auto;
  gap: 20px;
  align-items: center;
  transition: box-shadow .2s;
}
.aa-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,.04); }
.aa-item.aa-pinned { border-color: #E6C220; background: linear-gradient(180deg,#FFFCEB 0%,#fff 60%); }
.aa-item.aa-hidden { opacity: .55; }

.aa-item-thumb {
  width: 120px;
  height: 80px;
  border-radius: 8px;
  background: #f3f4f6;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.aa-item-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.aa-item-thumb-icon {
  font-size: 2rem;
  color: #9ca3af;
}
.aa-item-thumb-video {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.8rem;
}

.aa-item-info { min-width: 0; }
.aa-item-title { font-size: 1.02rem; font-weight: 600; color: #111827; margin: 0 0 4px; }
.aa-item-meta { display: flex; gap: 12px; font-size: .78rem; color: #6b7280; flex-wrap: wrap; align-items: center; }
.aa-item-meta i { margin-right: 3px; }
.aa-type-badge {
  display: inline-flex;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .5px;
  padding: 3px 10px;
  border-radius: 100px;
  font-weight: 600;
  background: rgba(46,125,50,.1);
  color: #2E7D32;
}
.aa-item-extrait {
  font-size: .85rem;
  color: #4b5563;
  margin: 6px 0 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.aa-item-actions { display: flex; gap: 6px; flex-shrink: 0; }
.aa-action-btn {
  width: 36px; height: 36px;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  cursor: pointer;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .15s;
}
.aa-action-btn:hover { background: #f9fafb; color: #111827; }
.aa-action-btn.aa-pin-active { color: #d97706; border-color: rgba(245,158,11,.3); }
.aa-action-btn.aa-sidebar-active { color: #2E7D32; border-color: rgba(46,125,50,.35); background: rgba(46,125,50,.08); }
.aa-action-btn.aa-visible-active { color: #2E7D32; }
.aa-action-btn.aa-danger:hover { background: #fee2e2; color: #dc2626; border-color: rgba(239,68,68,.3); }

/* Empty */
.aa-empty {
  text-align: center;
  padding: 80px 20px;
  background: #fff;
  border: 1px dashed #e5e7eb;
  border-radius: 12px;
  color: #9ca3af;
}
.aa-empty i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: .5; }

/* Loading */
.aa-loading { text-align: center; padding: 60px; color: #9ca3af; }
.aa-loading i { font-size: 2rem; display: block; margin-bottom: 10px; animation: aaSpin 1s linear infinite; }
@keyframes aaSpin { to { transform: rotate(360deg); } }

/* Toast */
.aa-toast {
  position: fixed;
  bottom: 24px; right: 24px;
  background: #111827;
  color: #fff;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: .88rem;
  font-weight: 500;
  box-shadow: 0 10px 30px rgba(0,0,0,.2);
  transform: translateY(80px);
  opacity: 0;
  transition: all .3s;
  z-index: 1100;
}
.aa-toast.show { transform: translateY(0); opacity: 1; }
.aa-toast.aa-toast-error { background: #dc2626; }

/* Modal */
.aa-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(17,24,39,.55);
  display: none;
  align-items: flex-start;
  justify-content: center;
  z-index: 200;
  padding: 40px 20px;
  overflow-y: auto;
}
.aa-modal-overlay.show { display: flex; }
.aa-modal {
  background: #fff;
  border-radius: 14px;
  max-width: 860px;
  width: 100%;
  box-shadow: 0 25px 80px rgba(0,0,0,.3);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 80px);
}
.aa-modal-header {
  padding: 18px 26px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.aa-modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.aa-modal-header h3 i { color: #2E7D32; }
.aa-modal-close { background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #6b7280; line-height: 1; }
.aa-modal-body { padding: 26px; overflow-y: auto; flex: 1; }
.aa-modal-footer {
  padding: 16px 26px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 10px;
  justify-content: space-between;
  flex-shrink: 0;
}

/* Form */
.aa-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-bottom: 18px;
}
.aa-form-grid.aa-full { grid-template-columns: 1fr; }
.aa-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.aa-form-group label { font-size: .82rem; font-weight: 500; color: #4b5563; }
.aa-form-group label .aa-req { color: #dc2626; }
.aa-input, .aa-textarea, .aa-select {
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: .92rem;
  font-family: inherit;
  color: #111827;
  background: #fff;
  transition: border-color .2s;
}
.aa-input:focus, .aa-textarea:focus, .aa-select:focus {
  outline: none;
  border-color: #2E7D32;
  box-shadow: 0 0 0 3px rgba(46,125,50,.1);
}
.aa-textarea { resize: vertical; min-height: 80px; }

.aa-type-picker {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
}
.aa-type-option {
  padding: 14px 8px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  text-align: center;
  transition: all .2s;
  font-size: .78rem;
  font-weight: 500;
  color: #6b7280;
}
.aa-type-option i { display: block; font-size: 1.4rem; margin-bottom: 4px; color: #9ca3af; }
.aa-type-option:hover { border-color: #81C784; color: #2E7D32; }
.aa-type-option.active {
  border-color: #2E7D32;
  background: rgba(46,125,50,.06);
  color: #2E7D32;
}
.aa-type-option.active i { color: #2E7D32; }

.aa-upload-zone {
  border: 2px dashed #e5e7eb;
  border-radius: 10px;
  padding: 22px;
  text-align: center;
  cursor: pointer;
  transition: all .2s;
  color: #6b7280;
  font-size: .88rem;
  position: relative;
}
.aa-upload-zone:hover {
  border-color: #81C784;
  background: rgba(46,125,50,.04);
}
.aa-upload-zone.aa-has-file { border-color: #2E7D32; background: rgba(46,125,50,.06); }
.aa-upload-zone.aa-drag {
  border-color: #2E7D32;
  background: rgba(46,125,50,.10);
  transform: scale(1.01);
}
.aa-upload-zone i { font-size: 1.6rem; display: block; margin-bottom: 6px; color: #9ca3af; }
.aa-upload-zone input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.aa-upload-preview {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: center;
}
.aa-upload-preview img, .aa-upload-preview video {
  max-height: 120px;
  max-width: 220px;
  border-radius: 6px;
  background: #f3f4f6;
}
.aa-upload-clear {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: .78rem;
  cursor: pointer;
  color: #dc2626;
}

/* Gallery uploader */
.aa-gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
  margin-top: 12px;
}
.aa-gallery-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  background: #f3f4f6;
}
.aa-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
.aa-gallery-remove {
  position: absolute;
  top: 6px; right: 6px;
  background: rgba(17,24,39,.75);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 26px; height: 26px;
  cursor: pointer;
  font-size: .9rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.aa-checkbox-row {
  display: flex;
  gap: 22px;
  flex-wrap: wrap;
  padding: 14px 16px;
  background: #f9fafb;
  border-radius: 10px;
  margin-bottom: 16px;
}
.aa-check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: .88rem;
  color: #374151;
  font-weight: 500;
}
.aa-check input[type="checkbox"] {
  width: 18px; height: 18px; accent-color: #2E7D32; cursor: pointer;
}

/* Editor container */
#aaEditorContainer {
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  min-height: 280px;
  overflow: hidden;
}
#aaEditorContainer:focus-within { border-color: #2E7D32; }

/* Section title */
.aa-section-title {
  font-size: .82rem;
  font-weight: 600;
  color: #2E7D32;
  text-transform: uppercase;
  letter-spacing: .8px;
  margin: 22px 0 12px;
  padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
}
.aa-section-title:first-child { margin-top: 0; }

.aa-upload-progress {
  width: 100%;
  margin-top: 10px;
  display: none;
}
.aa-upload-progress.show { display: block; }
.aa-upload-progress-bar {
  height: 5px;
  background: #2E7D32;
  width: 0;
  transition: width .25s ease;
  border-radius: 100px;
}
.aa-upload-progress {
  background: transparent;
}
.aa-upload-progress::before {
  content: '';
  display: block;
  height: 5px;
  background: #e5e7eb;
  border-radius: 100px;
  position: relative;
  margin-bottom: -5px;
}
.aa-upload-progress-label {
  font-size: .78rem;
  color: #2E7D32;
  font-weight: 600;
  text-align: center;
  margin-top: 6px;
}

/* ── Icon picker button ── */
.aa-icon-picker-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  color: #111827;
  cursor: pointer;
  font-family: inherit;
  font-size: .92rem;
  transition: border-color .2s, background .2s;
  text-align: left;
  width: 100%;
}
.aa-icon-picker-btn:hover { border-color: #81C784; background: rgba(46,125,50,.04); }
.aa-icon-picker-btn > i:first-child {
  width: 38px;
  height: 38px;
  background: rgba(46,125,50,.08);
  color: #2E7D32;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}
.aa-icon-picker-btn > span {
  flex: 1;
  color: #4b5563;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: .85rem;
}
.aa-icon-picker-chev {
  color: #9ca3af;
  font-size: .85rem;
}

/* ── Icon picker modal ── */
.aa-ip-search {
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.aa-ip-search i { color: #9ca3af; font-size: 1.1rem; }
.aa-ip-search input {
  flex: 1;
  border: none;
  outline: none;
  font-size: .95rem;
  color: #111827;
  background: transparent;
}
.aa-ip-cats {
  padding: 12px 18px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  max-height: 110px;
  overflow-y: auto;
  flex-shrink: 0;
}
.aa-ip-cat {
  padding: 5px 12px;
  background: #f3f4f6;
  border-radius: 100px;
  font-size: .78rem;
  color: #4b5563;
  cursor: pointer;
  font-weight: 500;
  transition: all .15s;
  user-select: none;
}
.aa-ip-cat:hover { background: #e5e7eb; color: #111827; }
.aa-ip-cat.active { background: #2E7D32; color: #fff; }
.aa-ip-grid {
  padding: 16px 18px;
  overflow-y: auto;
  flex: 1;
  min-height: 280px;
}
.aa-ip-cat-title {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: #6b7280;
  margin: 14px 0 8px;
  padding-left: 4px;
}
.aa-ip-cat-title:first-child { margin-top: 0; }
.aa-ip-cat-title + .aa-ip-icons-wrap {
  display: grid;
}
.aa-ip-grid > .aa-ip-icons-wrap {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: 6px;
  margin-bottom: 14px;
}
.aa-ip-icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 6px;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all .15s;
  min-width: 0;
}
.aa-ip-icon:hover {
  background: rgba(46,125,50,.06);
  border-color: rgba(46,125,50,.2);
}
.aa-ip-icon.selected {
  background: rgba(46,125,50,.12);
  border-color: #2E7D32;
}
.aa-ip-icon i {
  font-size: 1.5rem;
  color: #2E7D32;
}
.aa-ip-name {
  font-size: .65rem;
  color: #6b7280;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: 'Consolas', 'Monaco', monospace;
}
.aa-ip-empty {
  text-align: center;
  padding: 40px;
  color: #9ca3af;
}

@media (max-width: 700px) {
  .aa-form-grid { grid-template-columns: 1fr; }
  .aa-type-picker { grid-template-columns: repeat(3, 1fr); }
  .aa-item { grid-template-columns: 1fr; }
  .aa-item-thumb { width: 100%; height: 180px; }
}
</style>
</head>
<body>

<header class="aa-topbar">
  <h1><i class="bi bi-newspaper"></i> Actualités &amp; activités</h1>
  <div class="aa-topbar-actions">
    <a href="/spocspace/website/actualites.php" target="_blank" class="aa-btn-ghost">
      <i class="bi bi-eye"></i> Voir la page publique
    </a>
    <a href="/spocspace/website/admin/" class="aa-btn-ghost">
      <i class="bi bi-arrow-left"></i> Admin Site
    </a>
  </div>
</header>

<main class="aa-main">

  <div class="aa-tabs">
    <button class="aa-tab active" data-view="actualites">
      <i class="bi bi-newspaper"></i> Actualités
    </button>
    <button class="aa-tab" data-view="activites">
      <i class="bi bi-calendar-event"></i> Activités à venir
    </button>
  </div>

  <!-- ═══ Vue Actualités ═══ -->
  <div id="aaViewActualites">
    <div class="aa-header-bar">
      <div></div>
      <button class="aa-btn-primary" onclick="aaOpenModal()">
        <i class="bi bi-plus-lg"></i> Nouvelle actualité
      </button>
    </div>
    <div id="aaList">
      <div class="aa-loading"><i class="bi bi-arrow-repeat"></i><div>Chargement…</div></div>
    </div>
  </div>

  <!-- ═══ Vue Activités à venir ═══ -->
  <div id="aaViewActivites" style="display:none">
    <div class="aa-header-bar">
      <div></div>
      <button class="aa-btn-primary" onclick="aaOpenActiviteModal()">
        <i class="bi bi-plus-lg"></i> Nouvelle activité
      </button>
    </div>
    <div id="aaActivitesList">
      <div class="aa-loading"><i class="bi bi-arrow-repeat"></i><div>Chargement…</div></div>
    </div>
  </div>

</main>

<!-- ═══ Modal Actualité ═══ -->
<div class="aa-modal-overlay" id="aaModal">
  <div class="aa-modal">
    <div class="aa-modal-header">
      <h3><i class="bi bi-newspaper"></i> <span id="aaModalTitle">Nouvelle actualité</span></h3>
      <button class="aa-modal-close" onclick="aaCloseModal()">&times;</button>
    </div>
    <div class="aa-modal-body">
      <input type="hidden" id="aaFormId">

      <div class="aa-section-title">Type d'actualité</div>
      <div class="aa-type-picker" id="aaTypePicker">
        <div class="aa-type-option active" data-type="texte"><i class="bi bi-file-text"></i>Article</div>
        <div class="aa-type-option" data-type="photo"><i class="bi bi-image"></i>Photo</div>
        <div class="aa-type-option" data-type="video"><i class="bi bi-camera-video"></i>Vidéo</div>
        <div class="aa-type-option" data-type="galerie"><i class="bi bi-images"></i>Galerie</div>
        <div class="aa-type-option" data-type="affiche"><i class="bi bi-megaphone"></i>Affiche</div>
      </div>

      <div class="aa-section-title">Informations</div>
      <div class="aa-form-group">
        <label>Titre <span class="aa-req">*</span></label>
        <input type="text" class="aa-input" id="aaTitre" maxlength="255" placeholder="Ex: Concert du jeudi">
      </div>
      <div class="aa-form-group">
        <label>Extrait (affiché en introduction)</label>
        <textarea class="aa-textarea" id="aaExtrait" maxlength="500" placeholder="Courte description de l'actualité…"></textarea>
      </div>

      <!-- Media: photo/affiche -->
      <div id="aaMediaPhoto" style="display:none">
        <div class="aa-section-title">Image principale</div>
        <div class="aa-upload-zone" data-upload="cover">
          <i class="bi bi-cloud-arrow-up"></i>
          <div><strong>Cliquer ou déposer une image</strong></div>
          <div style="font-size:.78rem;color:#9ca3af;margin-top:4px">JPEG, PNG, WebP · max 15 MB — Plusieurs photos ? Elles passeront automatiquement en <strong>Galerie</strong></div>
          <input type="file" accept="image/*" multiple="multiple">
        </div>
        <div class="aa-upload-preview" id="aaCoverPreview" style="display:none"></div>
        <div class="aa-upload-progress" id="aaCoverProgress"><div class="aa-upload-progress-bar"></div><div class="aa-upload-progress-label"></div></div>
      </div>

      <!-- Media: video -->
      <div id="aaMediaVideo" style="display:none">
        <div class="aa-section-title">Vidéo</div>
        <div class="aa-upload-zone" data-upload="video">
          <i class="bi bi-camera-video"></i>
          Cliquer ou déposer une vidéo (MP4, WebM, MOV · max 200 MB)
          <input type="file" accept="video/*">
        </div>
        <div class="aa-upload-preview" id="aaVideoPreview" style="display:none"></div>
        <div class="aa-upload-progress" id="aaVideoProgress"><div class="aa-upload-progress-bar"></div></div>

        <div class="aa-section-title">Image de couverture vidéo (optionnel)</div>
        <div class="aa-upload-zone" data-upload="poster">
          <i class="bi bi-image"></i>
          Cliquer pour ajouter une vignette
          <input type="file" accept="image/*">
        </div>
        <div class="aa-upload-preview" id="aaPosterPreview" style="display:none"></div>
      </div>

      <!-- Media: galerie -->
      <div id="aaMediaGalerie" style="display:none">
        <div class="aa-section-title">Galerie photos</div>
        <div class="aa-upload-zone" data-upload="gallery">
          <i class="bi bi-images"></i>
          <div><strong>Cliquer pour sélectionner plusieurs photos</strong></div>
          <div style="font-size:.78rem;color:#9ca3af;margin-top:4px">Maintenez <kbd style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:1px 6px;font-size:.72rem">Ctrl</kbd> (ou <kbd style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:1px 6px;font-size:.72rem">⌘</kbd> sur Mac) pour sélectionner plusieurs images, ou glissez-déposez directement</div>
          <input type="file" accept="image/*" multiple="multiple">
        </div>
        <div class="aa-upload-progress" id="aaGalleryProgress">
          <div class="aa-upload-progress-bar"></div>
          <div class="aa-upload-progress-label"></div>
        </div>
        <div class="aa-gallery-grid" id="aaGalleryGrid"></div>
      </div>

      <!-- Contenu TipTap -->
      <div class="aa-section-title">Contenu de l'article</div>
      <div id="aaEditorContainer"></div>

      <div class="aa-section-title">Options de publication</div>
      <div class="aa-checkbox-row">
        <label class="aa-check"><input type="checkbox" id="aaVisible" checked> Visible sur le site</label>
        <label class="aa-check"><input type="checkbox" id="aaPinned"> Épingler en haut</label>
        <label class="aa-check" id="aaSidebarPinWrap" style="display:none"><input type="checkbox" id="aaSidebarPin"> <i class="bi bi-bookmark-star" style="color:#2E7D32"></i> Accrocher sur la sidebar</label>
      </div>
      <div class="aa-form-grid">
        <div class="aa-form-group">
          <label>Date de publication</label>
          <input type="datetime-local" class="aa-input" id="aaPublishedAt">
        </div>
      </div>
    </div>
    <div class="aa-modal-footer">
      <button class="aa-btn-ghost" onclick="aaCloseModal()">Annuler</button>
      <button class="aa-btn-primary" onclick="aaSave()"><i class="bi bi-check-lg"></i> <span id="aaSaveLabel">Enregistrer</span></button>
    </div>
  </div>
</div>

<!-- ═══ Modal Confirmation Suppression ═══ -->
<div class="aa-modal-overlay" id="aaDeleteModal">
  <div class="aa-modal" style="max-width:460px">
    <div class="aa-modal-header">
      <h3><i class="bi bi-exclamation-triangle-fill" style="color:#dc2626"></i> Supprimer cette actualité ?</h3>
      <button class="aa-modal-close" onclick="aaCloseDeleteModal()">&times;</button>
    </div>
    <div class="aa-modal-body">
      <p style="margin:0 0 10px;font-size:.92rem;color:#4b5563">
        Vous êtes sur le point de supprimer l'actualité <strong id="aaDeleteTitle">—</strong>.
      </p>
      <p style="margin:0;font-size:.85rem;color:#6b7280">
        Cette action est <strong>irréversible</strong>. Tous les médias associés (images, vidéos, galeries) seront également supprimés définitivement.
      </p>
    </div>
    <div class="aa-modal-footer">
      <button class="aa-btn-ghost" onclick="aaCloseDeleteModal()">Annuler</button>
      <button class="aa-btn-primary" id="aaDeleteConfirmBtn" style="background:#dc2626">
        <i class="bi bi-trash"></i> Supprimer définitivement
      </button>
    </div>
  </div>
</div>

<!-- ═══ Modal Activité ═══ -->
<div class="aa-modal-overlay" id="aaActModal">
  <div class="aa-modal" style="max-width:640px">
    <div class="aa-modal-header">
      <h3><i class="bi bi-calendar-event"></i> <span id="aaActModalTitle">Nouvelle activité</span></h3>
      <button class="aa-modal-close" onclick="aaCloseActiviteModal()">&times;</button>
    </div>
    <div class="aa-modal-body">
      <input type="hidden" id="aaActFormId">
      <div class="aa-form-group">
        <label>Titre <span class="aa-req">*</span></label>
        <input type="text" class="aa-input" id="aaActTitre" maxlength="255" placeholder="Ex: Atelier chant">
      </div>
      <div class="aa-form-group">
        <label>Description</label>
        <textarea class="aa-textarea" id="aaActDescription" maxlength="1000"></textarea>
      </div>
      <div class="aa-form-grid">
        <div class="aa-form-group">
          <label>Date <span class="aa-req">*</span></label>
          <input type="date" class="aa-input" id="aaActDate">
        </div>
        <div class="aa-form-group">
          <label>Lieu</label>
          <input type="text" class="aa-input" id="aaActLieu" maxlength="255" placeholder="Ex: Salle commune">
        </div>
      </div>
      <div class="aa-form-grid">
        <div class="aa-form-group">
          <label>Heure début</label>
          <input type="time" class="aa-input" id="aaActHeureDebut">
        </div>
        <div class="aa-form-group">
          <label>Heure fin</label>
          <input type="time" class="aa-input" id="aaActHeureFin">
        </div>
      </div>
      <div class="aa-form-group">
        <label>Icône</label>
        <button type="button" class="aa-icon-picker-btn" id="aaActIconeBtn" onclick="aaOpenIconPicker()">
          <i class="bi bi-calendar-event" id="aaActIconePreview"></i>
          <span id="aaActIconeLabel">calendar-event</span>
          <i class="bi bi-chevron-down aa-icon-picker-chev"></i>
        </button>
        <input type="hidden" id="aaActIcone" value="bi-calendar-event">
      </div>
      <div class="aa-checkbox-row">
        <label class="aa-check"><input type="checkbox" id="aaActVisible" checked> Visible sur le site</label>
      </div>
    </div>
    <div class="aa-modal-footer">
      <button class="aa-btn-ghost" onclick="aaCloseActiviteModal()">Annuler</button>
      <button class="aa-btn-primary" onclick="aaSaveActivite()"><i class="bi bi-check-lg"></i> Enregistrer</button>
    </div>
  </div>
</div>

<!-- ═══ Modal Icon Picker ═══ -->
<div class="aa-modal-overlay" id="aaIconModal">
  <div class="aa-modal" style="max-width:780px;max-height:80vh">
    <div class="aa-modal-header">
      <h3><i class="bi bi-grid-3x3-gap"></i> Choisir une icône</h3>
      <button class="aa-modal-close" onclick="aaCloseIconPicker()">&times;</button>
    </div>
    <div class="aa-modal-body" style="padding:0;display:flex;flex-direction:column;overflow:hidden">
      <div class="aa-ip-search">
        <i class="bi bi-search"></i>
        <input type="text" class="aa-input" id="aaIpSearch" placeholder="Rechercher une icône (ex: music, heart, calendar...)" autocomplete="off">
      </div>
      <div class="aa-ip-cats" id="aaIpCats"></div>
      <div class="aa-ip-grid" id="aaIpGrid"></div>
    </div>
  </div>
</div>

<div class="aa-toast" id="aaToast"></div>

<script type="module">
import { createEditor, getHTML, setContent } from '/spocspace/assets/js/rich-editor.js';

const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const API = '/spocspace/website/admin/api/actualites.php';

const TYPE_LABELS = { texte: 'Article', photo: 'Photo', video: 'Vidéo', galerie: 'Galerie', affiche: 'Affiche' };
const TYPE_ICONS = { texte: 'bi-file-text', photo: 'bi-image', video: 'bi-camera-video', galerie: 'bi-images', affiche: 'bi-megaphone' };

let editor = null;
let currentItem = null;
let currentGallery = [];
let currentCover = '';
let currentVideo = '';
let currentPoster = '';
let currentType = 'texte';

// ── Helpers ──
function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtDate(s) {
  if (!s) return '';
  const d = new Date(s.replace(' ', 'T'));
  return d.toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', year: 'numeric' });
}
function toast(msg, isError) {
  const t = document.getElementById('aaToast');
  t.textContent = msg;
  t.classList.toggle('aa-toast-error', !!isError);
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

async function api(action, data = {}) {
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ action, ...data })
  });
  return res.json();
}

async function apiUpload(action, file, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', API);
    xhr.setRequestHeader('X-CSRF-Token', CSRF);
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable && onProgress) onProgress((e.loaded / e.total) * 100);
    });
    xhr.onload = () => {
      try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); }
    };
    xhr.onerror = reject;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('file', file);
    xhr.send(fd);
  });
}

// ── Tabs ──
document.querySelectorAll('.aa-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.aa-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const view = btn.dataset.view;
    document.getElementById('aaViewActualites').style.display = view === 'actualites' ? '' : 'none';
    document.getElementById('aaViewActivites').style.display = view === 'activites' ? '' : 'none';
    if (view === 'actualites') loadList();
    else loadActivites();
  });
});

// ── List actualités ──
async function loadList() {
  const container = document.getElementById('aaList');
  container.innerHTML = '<div class="aa-loading"><i class="bi bi-arrow-repeat"></i></div>';
  const data = await api('list');
  if (!data.success) {
    container.innerHTML = '<div class="aa-empty"><i class="bi bi-exclamation-triangle"></i>Erreur de chargement</div>';
    return;
  }
  if (!data.actualites.length) {
    container.innerHTML = '<div class="aa-empty"><i class="bi bi-newspaper"></i><div>Aucune actualité pour le moment</div></div>';
    return;
  }
  container.innerHTML = '<div class="aa-list">' + data.actualites.map(renderItem).join('') + '</div>';
}

function renderItem(a) {
  const type = a.type || 'texte';
  let thumbHtml;
  if (type === 'video') {
    thumbHtml = a.video_poster || a.cover_url
      ? `<img src="${escHtml(a.video_poster || a.cover_url)}" alt=""><div class="aa-item-thumb-video"><i class="bi bi-play-circle-fill"></i></div>`
      : `<div class="aa-item-thumb-video"><i class="bi bi-camera-video"></i></div>`;
  } else if (a.cover_url) {
    thumbHtml = `<img src="${escHtml(a.cover_url)}" alt="">`;
  } else if (type === 'galerie' && a.images && a.images.length) {
    thumbHtml = `<img src="${escHtml(a.images[0])}" alt="">`;
  } else {
    thumbHtml = `<div class="aa-item-thumb-icon"><i class="bi ${TYPE_ICONS[type]}"></i></div>`;
  }

  return `
    <div class="aa-item ${a.epingle == 1 ? 'aa-pinned' : ''} ${a.is_visible != 1 ? 'aa-hidden' : ''}" data-id="${a.id}">
      <div class="aa-item-thumb">${thumbHtml}</div>
      <div class="aa-item-info">
        <h4 class="aa-item-title">${escHtml(a.titre)}</h4>
        <div class="aa-item-meta">
          <span class="aa-type-badge"><i class="bi ${TYPE_ICONS[type]}"></i>&nbsp;${TYPE_LABELS[type]}</span>
          <span><i class="bi bi-clock"></i>${fmtDate(a.published_at || a.created_at)}</span>
          ${a.is_visible != 1 ? '<span style="color:#dc2626"><i class="bi bi-eye-slash"></i>Masquée</span>' : ''}
        </div>
        ${a.extrait ? `<p class="aa-item-extrait">${escHtml(a.extrait)}</p>` : ''}
      </div>
      <div class="aa-item-actions">
        ${type === 'affiche' ? `<button class="aa-action-btn ${a.sidebar_pin == 1 ? 'aa-sidebar-active' : ''}" title="${a.sidebar_pin == 1 ? 'Décrocher de la sidebar' : 'Accrocher sur la sidebar'}" onclick="aaToggleSidebar('${a.id}')"><i class="bi bi-${a.sidebar_pin == 1 ? 'bookmark-star-fill' : 'bookmark-plus'}"></i></button>` : ''}
        <button class="aa-action-btn ${a.epingle == 1 ? 'aa-pin-active' : ''}" title="Épingler" onclick="aaTogglePin('${a.id}')"><i class="bi bi-pin-angle${a.epingle == 1 ? '-fill' : ''}"></i></button>
        <button class="aa-action-btn ${a.is_visible == 1 ? 'aa-visible-active' : ''}" title="Visibilité" onclick="aaToggleVisible('${a.id}')"><i class="bi bi-eye${a.is_visible == 1 ? '-fill' : '-slash'}"></i></button>
        <button class="aa-action-btn" title="Modifier" onclick="aaEdit('${a.id}')"><i class="bi bi-pencil"></i></button>
        <button class="aa-action-btn aa-danger" title="Supprimer" onclick="aaDelete('${a.id}')"><i class="bi bi-trash"></i></button>
      </div>
    </div>
  `;
}

// ── Type picker ──
document.getElementById('aaTypePicker').addEventListener('click', (e) => {
  const opt = e.target.closest('.aa-type-option');
  if (!opt) return;
  document.querySelectorAll('.aa-type-option').forEach(o => o.classList.remove('active'));
  opt.classList.add('active');
  currentType = opt.dataset.type;
  refreshMediaSections();
});

function refreshMediaSections() {
  document.getElementById('aaMediaPhoto').style.display = (currentType === 'photo' || currentType === 'affiche') ? '' : 'none';
  document.getElementById('aaMediaVideo').style.display = currentType === 'video' ? '' : 'none';
  document.getElementById('aaMediaGalerie').style.display = currentType === 'galerie' ? '' : 'none';
  document.getElementById('aaSidebarPinWrap').style.display = currentType === 'affiche' ? '' : 'none';
  if (currentType !== 'affiche') document.getElementById('aaSidebarPin').checked = false;
}

// ── Modal ──
window.aaOpenModal = async function(item) {
  currentItem = item || null;
  currentGallery = [];
  currentCover = '';
  currentVideo = '';
  currentPoster = '';
  currentType = item?.type || 'texte';

  document.getElementById('aaModalTitle').textContent = item ? 'Modifier l\'actualité' : 'Nouvelle actualité';
  document.getElementById('aaSaveLabel').textContent = item ? 'Enregistrer' : 'Créer';
  document.getElementById('aaFormId').value = item?.id || '';
  document.getElementById('aaTitre').value = item?.titre || '';
  document.getElementById('aaExtrait').value = item?.extrait || '';
  document.getElementById('aaVisible').checked = item ? item.is_visible == 1 : true;
  document.getElementById('aaPinned').checked = item?.epingle == 1;
  document.getElementById('aaSidebarPin').checked = item?.sidebar_pin == 1;

  // datetime-local attend "YYYY-MM-DDTHH:mm", la DB renvoie "YYYY-MM-DD HH:mm:ss"
  // Pas de conversion via Date() pour éviter tout décalage UTC/local.
  const rawDate = item?.published_at || item?.created_at || '';
  document.getElementById('aaPublishedAt').value = rawDate
    ? rawDate.replace(' ', 'T').slice(0, 16)
    : '';

  // Type picker
  document.querySelectorAll('.aa-type-option').forEach(o => {
    o.classList.toggle('active', o.dataset.type === currentType);
  });
  refreshMediaSections();

  // Existing media
  currentCover = item?.cover_url || '';
  currentVideo = item?.video_url || '';
  currentPoster = item?.video_poster || '';
  currentGallery = Array.isArray(item?.images) ? [...item.images] : [];

  updatePreview('aaCoverPreview', currentCover, 'image', 'cover');
  updatePreview('aaVideoPreview', currentVideo, 'video', 'video');
  updatePreview('aaPosterPreview', currentPoster, 'image', 'poster');
  renderGallery();

  // Editor
  document.getElementById('aaModal').classList.add('show');
  if (!editor) {
    editor = await createEditor(document.getElementById('aaEditorContainer'), {
      mode: 'full',
      placeholder: 'Rédigez le contenu de votre article…',
      content: item?.contenu || ''
    });
  } else {
    setContent(editor, item?.contenu || '');
  }
};

window.aaCloseModal = function() {
  document.getElementById('aaModal').classList.remove('show');
};

function updatePreview(containerId, url, kind, slot) {
  const el = document.getElementById(containerId);
  const zone = document.querySelector(`.aa-upload-zone[data-upload="${slot}"]`);
  if (!url) {
    el.style.display = 'none';
    el.innerHTML = '';
    if (zone) zone.classList.remove('aa-has-file');
    return;
  }
  el.style.display = 'flex';
  const media = kind === 'video'
    ? `<video src="${escHtml(url)}" controls></video>`
    : `<img src="${escHtml(url)}" alt="">`;
  el.innerHTML = `${media}<button type="button" class="aa-upload-clear" data-clear="${slot}"><i class="bi bi-x"></i> Retirer</button>`;
  if (zone) zone.classList.add('aa-has-file');
}

function renderGallery() {
  const grid = document.getElementById('aaGalleryGrid');
  grid.innerHTML = currentGallery.map((url, i) => `
    <div class="aa-gallery-item">
      <img src="${escHtml(url)}" alt="">
      <button type="button" class="aa-gallery-remove" data-idx="${i}">&times;</button>
    </div>
  `).join('');
}

// Clear previews
document.addEventListener('click', (e) => {
  const clearBtn = e.target.closest('[data-clear]');
  if (clearBtn) {
    const slot = clearBtn.dataset.clear;
    if (slot === 'cover') { currentCover = ''; updatePreview('aaCoverPreview', '', 'image', 'cover'); }
    if (slot === 'video') { currentVideo = ''; updatePreview('aaVideoPreview', '', 'video', 'video'); }
    if (slot === 'poster') { currentPoster = ''; updatePreview('aaPosterPreview', '', 'image', 'poster'); }
  }
  const rmBtn = e.target.closest('.aa-gallery-remove');
  if (rmBtn) {
    const idx = parseInt(rmBtn.dataset.idx, 10);
    currentGallery.splice(idx, 1);
    renderGallery();
  }
});

// Upload handlers (parallèle + progression agrégée)
document.querySelectorAll('.aa-upload-zone').forEach(zone => {
  const input = zone.querySelector('input[type="file"]');
  const slot = zone.dataset.upload;

  // Drag & drop
  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('aa-drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('aa-drag'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('aa-drag');
    if (!e.dataTransfer?.files?.length) return;
    handleFiles(Array.from(e.dataTransfer.files));
  });

  input.addEventListener('change', async () => {
    const files = Array.from(input.files || []);
    if (!files.length) return;
    try {
      await handleFiles(files);
    } finally {
      input.value = '';
    }
  });

  async function handleFiles(files) {
    // ── Auto-switch vers galerie si plusieurs fichiers sur cover ──
    let effectiveSlot = slot;
    if (slot === 'cover' && files.length > 1) {
      effectiveSlot = 'gallery';
      // Change le type de l'actualité vers "galerie"
      currentType = 'galerie';
      document.querySelectorAll('.aa-type-option').forEach(o => {
        o.classList.toggle('active', o.dataset.type === 'galerie');
      });
      refreshMediaSections();
      toast(`${files.length} photos → mode Galerie activé`);
    }

    const progressId = effectiveSlot === 'cover' ? 'aaCoverProgress'
                    : effectiveSlot === 'video' ? 'aaVideoProgress'
                    : effectiveSlot === 'gallery' ? 'aaGalleryProgress' : null;
    const progressEl = progressId ? document.getElementById(progressId) : null;
    const progressBar = progressEl?.querySelector('.aa-upload-progress-bar');
    const progressLabel = progressEl?.querySelector('.aa-upload-progress-label');

    const isVideo = effectiveSlot === 'video';
    const action = isVideo ? 'upload_video' : 'upload_image';

    // Slots uniques : une seule image à la fois
    if (effectiveSlot !== 'gallery') {
      const file = files[0];
      if (progressEl) { progressEl.classList.add('show'); if (progressBar) progressBar.style.width = '0%'; }
      try {
        const result = await apiUpload(action, file, (p) => { if (progressBar) progressBar.style.width = p + '%'; });
        if (result.success && result.url) {
          if (effectiveSlot === 'cover') { currentCover = result.url; updatePreview('aaCoverPreview', currentCover, 'image', 'cover'); }
          else if (effectiveSlot === 'video') { currentVideo = result.url; updatePreview('aaVideoPreview', currentVideo, 'video', 'video'); }
          else if (effectiveSlot === 'poster') { currentPoster = result.url; updatePreview('aaPosterPreview', currentPoster, 'image', 'poster'); }
        } else {
          toast(result.message || 'Erreur upload', true);
        }
      } catch (e) {
        toast('Erreur upload', true);
      }
      if (progressEl) setTimeout(() => progressEl.classList.remove('show'), 600);
      return;
    }

    // ── Upload groupé pour la galerie (parallèle) ──
    if (progressEl) { progressEl.classList.add('show'); if (progressBar) progressBar.style.width = '0%'; }

    const total = files.length;
    let completed = 0;
    let failed = 0;
    const progressPerFile = new Array(total).fill(0);

    const updateAggregateProgress = () => {
      const avg = progressPerFile.reduce((a, b) => a + b, 0) / total;
      if (progressBar) progressBar.style.width = avg + '%';
      if (progressLabel) progressLabel.textContent = `Envoi ${completed}/${total}…`;
    };

    if (progressLabel) progressLabel.textContent = `Envoi 0/${total}…`;

    // Lance tous les uploads en parallèle, préserve l'ordre des résultats
    const results = await Promise.all(files.map((file, idx) =>
      apiUpload(action, file, (p) => {
        progressPerFile[idx] = p;
        updateAggregateProgress();
      }).then(r => {
        completed++;
        progressPerFile[idx] = 100;
        updateAggregateProgress();
        return r;
      }).catch(() => {
        completed++;
        failed++;
        progressPerFile[idx] = 100;
        updateAggregateProgress();
        return { success: false };
      })
    ));

    // Ajoute dans l'ordre original
    results.forEach(r => {
      if (r && r.success && r.url) currentGallery.push(r.url);
    });
    renderGallery();

    if (failed > 0) toast(`${failed} fichier${failed > 1 ? 's' : ''} en erreur`, true);
    else toast(`${total} photo${total > 1 ? 's' : ''} ajoutée${total > 1 ? 's' : ''}`);

    if (progressLabel) progressLabel.textContent = '';
    if (progressEl) setTimeout(() => progressEl.classList.remove('show'), 800);
  }
});

// ── Save ──
window.aaSave = async function() {
  const titre = document.getElementById('aaTitre').value.trim();
  if (!titre) { toast('Titre requis', true); return; }

  const payload = {
    id: document.getElementById('aaFormId').value,
    titre,
    type: currentType,
    extrait: document.getElementById('aaExtrait').value,
    contenu: editor ? getHTML(editor) : '',
    cover_url: currentCover,
    video_url: currentVideo,
    video_poster: currentPoster,
    images: currentGallery,
    is_visible: document.getElementById('aaVisible').checked ? 1 : 0,
    epingle: document.getElementById('aaPinned').checked ? 1 : 0,
    sidebar_pin: (currentType === 'affiche' && document.getElementById('aaSidebarPin').checked) ? 1 : 0,
    published_at: document.getElementById('aaPublishedAt').value
      ? document.getElementById('aaPublishedAt').value.replace('T', ' ') + ':00'
      : null
  };

  const res = await api('save', payload);
  if (res.success) {
    toast('Enregistré');
    aaCloseModal();
    loadList();
  } else {
    toast(res.message || 'Erreur', true);
  }
};

// ── Actions ──
window.aaEdit = async function(id) {
  const res = await api('get', { id });
  if (!res.success) return toast('Erreur', true);
  aaOpenModal(res.actualite);
};
window.aaTogglePin = async function(id) {
  const res = await api('toggle_pin', { id });
  if (res.success) { toast('Mis à jour'); loadList(); }
};
window.aaToggleSidebar = async function(id) {
  const res = await api('toggle_sidebar', { id });
  if (res.success) {
    toast(res.sidebar_pin == 1 ? 'Affiche accrochée sur la sidebar' : 'Affiche décrochée');
    loadList();
  } else {
    toast(res.message || 'Erreur', true);
  }
};
window.aaToggleVisible = async function(id) {
  const res = await api('toggle_visible', { id });
  if (res.success) { toast('Mis à jour'); loadList(); }
};
let aaPendingDeleteId = null;
window.aaDelete = function(id) {
  aaPendingDeleteId = id;
  // Récupérer le titre depuis la carte affichée pour l'afficher dans la modale
  const row = document.querySelector(`.aa-item[data-id="${id}"] .aa-item-title`);
  document.getElementById('aaDeleteTitle').textContent = row ? row.textContent : 'cette actualité';
  document.getElementById('aaDeleteModal').classList.add('show');
};
window.aaCloseDeleteModal = function() {
  document.getElementById('aaDeleteModal').classList.remove('show');
  aaPendingDeleteId = null;
};
document.getElementById('aaDeleteConfirmBtn').addEventListener('click', async () => {
  if (!aaPendingDeleteId) return;
  const btn = document.getElementById('aaDeleteConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Suppression…';
  const res = await api('delete', { id: aaPendingDeleteId });
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-trash"></i> Supprimer définitivement';
  if (res.success) {
    toast('Actualité supprimée');
    aaCloseDeleteModal();
    loadList();
  } else {
    toast(res.message || 'Erreur', true);
  }
});
// Clic sur overlay ou Escape pour fermer
document.getElementById('aaDeleteModal').addEventListener('click', (e) => {
  if (e.target.id === 'aaDeleteModal') aaCloseDeleteModal();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.getElementById('aaDeleteModal').classList.contains('show')) {
    aaCloseDeleteModal();
  }
});

// ══ ACTIVITÉS À VENIR ══
async function loadActivites() {
  const container = document.getElementById('aaActivitesList');
  container.innerHTML = '<div class="aa-loading"><i class="bi bi-arrow-repeat"></i></div>';
  const data = await api('list_activites');
  if (!data.success) {
    container.innerHTML = '<div class="aa-empty"><i class="bi bi-exclamation-triangle"></i>Erreur</div>';
    return;
  }
  if (!data.activites.length) {
    container.innerHTML = '<div class="aa-empty"><i class="bi bi-calendar2-x"></i><div>Aucune activité planifiée</div></div>';
    return;
  }
  container.innerHTML = '<div class="aa-list">' + data.activites.map(renderActiviteItem).join('') + '</div>';
}

function renderActiviteItem(a) {
  const past = new Date(a.date_activite) < new Date(new Date().toDateString());
  return `
    <div class="aa-item ${a.is_visible != 1 || past ? 'aa-hidden' : ''}" data-id="${a.id}">
      <div class="aa-item-thumb">
        <div class="aa-item-thumb-icon"><i class="bi ${escHtml(a.icone || 'bi-calendar-event')}"></i></div>
      </div>
      <div class="aa-item-info">
        <h4 class="aa-item-title">${escHtml(a.titre)}</h4>
        <div class="aa-item-meta">
          <span><i class="bi bi-calendar3"></i>${fmtDate(a.date_activite)}</span>
          ${a.heure_debut ? `<span><i class="bi bi-clock"></i>${a.heure_debut.substring(0,5)}${a.heure_fin ? ' – ' + a.heure_fin.substring(0,5) : ''}</span>` : ''}
          ${a.lieu ? `<span><i class="bi bi-geo-alt"></i>${escHtml(a.lieu)}</span>` : ''}
          ${past ? '<span style="color:#dc2626">Passée</span>' : ''}
        </div>
        ${a.description ? `<p class="aa-item-extrait">${escHtml(a.description)}</p>` : ''}
      </div>
      <div class="aa-item-actions">
        <button class="aa-action-btn" title="Modifier" onclick='aaEditActivite(${JSON.stringify(a).replace(/'/g, "&#39;")})'><i class="bi bi-pencil"></i></button>
        <button class="aa-action-btn aa-danger" title="Supprimer" onclick="aaDeleteActivite('${a.id}')"><i class="bi bi-trash"></i></button>
      </div>
    </div>
  `;
}

window.aaOpenActiviteModal = function(item) {
  document.getElementById('aaActModalTitle').textContent = item ? 'Modifier l\'activité' : 'Nouvelle activité';
  document.getElementById('aaActFormId').value = item?.id || '';
  document.getElementById('aaActTitre').value = item?.titre || '';
  document.getElementById('aaActDescription').value = item?.description || '';
  document.getElementById('aaActDate').value = item?.date_activite || '';
  document.getElementById('aaActLieu').value = item?.lieu || '';
  document.getElementById('aaActHeureDebut').value = item?.heure_debut ? item.heure_debut.substring(0,5) : '';
  document.getElementById('aaActHeureFin').value = item?.heure_fin ? item.heure_fin.substring(0,5) : '';
  document.getElementById('aaActIcone').value = item?.icone || 'bi-calendar-event';
  document.getElementById('aaActVisible').checked = item ? item.is_visible == 1 : true;
  document.getElementById('aaActModal').classList.add('show');
};
window.aaCloseActiviteModal = function() {
  document.getElementById('aaActModal').classList.remove('show');
};
window.aaEditActivite = function(item) { aaOpenActiviteModal(item); };

window.aaSaveActivite = async function() {
  const titre = document.getElementById('aaActTitre').value.trim();
  const date = document.getElementById('aaActDate').value;
  if (!titre || !date) { toast('Titre et date requis', true); return; }

  const payload = {
    id: document.getElementById('aaActFormId').value,
    titre,
    description: document.getElementById('aaActDescription').value,
    date_activite: date,
    heure_debut: document.getElementById('aaActHeureDebut').value,
    heure_fin: document.getElementById('aaActHeureFin').value,
    lieu: document.getElementById('aaActLieu').value,
    icone: document.getElementById('aaActIcone').value || 'bi-calendar-event',
    is_visible: document.getElementById('aaActVisible').checked ? 1 : 0
  };
  const res = await api('save_activite', payload);
  if (res.success) {
    toast('Enregistrée');
    aaCloseActiviteModal();
    loadActivites();
  } else {
    toast(res.message || 'Erreur', true);
  }
};

window.aaDeleteActivite = async function(id) {
  if (!confirm('Supprimer cette activité ?')) return;
  const res = await api('delete_activite', { id });
  if (res.success) { toast('Supprimée'); loadActivites(); }
};

// Close modals on overlay click
document.getElementById('aaModal').addEventListener('click', (e) => {
  if (e.target.id === 'aaModal') aaCloseModal();
});
document.getElementById('aaActModal').addEventListener('click', (e) => {
  if (e.target.id === 'aaActModal') aaCloseActiviteModal();
});

// ══ ICON PICKER ══
let ipCurrentCat = null;
let ipCurrentIcon = 'bi-calendar-event';

window.aaOpenIconPicker = function() {
  ipCurrentIcon = document.getElementById('aaActIcone').value || 'bi-calendar-event';
  const modal = document.getElementById('aaIconModal');
  modal.classList.add('show');
  document.getElementById('aaIpSearch').value = '';
  ipCurrentCat = null;
  renderIconCats();
  renderIconGrid('');
  setTimeout(() => document.getElementById('aaIpSearch').focus(), 100);
};

window.aaCloseIconPicker = function() {
  document.getElementById('aaIconModal').classList.remove('show');
};

function renderIconCats() {
  const BI = window.__BI_ICONS || {};
  const cats = Object.keys(BI);
  const totalAll = Object.values(BI).flat().length;
  let html = `<div class="aa-ip-cat ${!ipCurrentCat ? 'active' : ''}" data-cat="">Tout (${totalAll})</div>`;
  cats.forEach(cat => {
    const count = BI[cat].length;
    html += `<div class="aa-ip-cat ${ipCurrentCat === cat ? 'active' : ''}" data-cat="${escHtml(cat)}">${escHtml(cat)} (${count})</div>`;
  });
  document.getElementById('aaIpCats').innerHTML = html;
}

function renderIconGrid(search) {
  const BI = window.__BI_ICONS || {};
  const q = (search || '').toLowerCase().trim();
  const grid = document.getElementById('aaIpGrid');
  let html = '';
  let total = 0;

  const catsToShow = ipCurrentCat ? { [ipCurrentCat]: BI[ipCurrentCat] || [] } : BI;

  for (const [cat, icons] of Object.entries(catsToShow)) {
    const filtered = icons.filter(ic => !q || ic.toLowerCase().includes(q));
    if (!filtered.length) continue;

    html += `<div class="aa-ip-cat-title">${escHtml(cat)} (${filtered.length})</div>`;
    html += '<div class="aa-ip-icons-wrap">';
    filtered.forEach(ic => {
      const shortName = ic.replace('bi-', '');
      const isSelected = ic === ipCurrentIcon;
      html += `<div class="aa-ip-icon ${isSelected ? 'selected' : ''}" data-icon="${escHtml(ic)}" title="${escHtml(ic)}">
        <i class="bi ${escHtml(ic)}"></i>
        <span class="aa-ip-name">${escHtml(shortName)}</span>
      </div>`;
      total++;
    });
    html += '</div>';
  }

  if (!total) {
    html = '<div class="aa-ip-empty"><i class="bi bi-search" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>Aucune icône trouvée</div>';
  }

  grid.innerHTML = html;
}

document.getElementById('aaIpCats').addEventListener('click', (e) => {
  const cat = e.target.closest('.aa-ip-cat');
  if (!cat) return;
  ipCurrentCat = cat.dataset.cat || null;
  renderIconCats();
  renderIconGrid(document.getElementById('aaIpSearch').value);
});

document.getElementById('aaIpSearch').addEventListener('input', (e) => {
  renderIconGrid(e.target.value);
});

document.getElementById('aaIpGrid').addEventListener('click', (e) => {
  const el = e.target.closest('.aa-ip-icon');
  if (!el) return;
  const icon = el.dataset.icon;
  ipCurrentIcon = icon;
  document.getElementById('aaActIcone').value = icon;
  document.getElementById('aaActIconePreview').className = 'bi ' + icon;
  document.getElementById('aaActIconeLabel').textContent = icon.replace('bi-', '');
  aaCloseIconPicker();
});

document.getElementById('aaIconModal').addEventListener('click', (e) => {
  if (e.target.id === 'aaIconModal') aaCloseIconPicker();
});

// Synchronise le bouton picker quand on ouvre la modale activité
const _origOpenAct = window.aaOpenActiviteModal;
window.aaOpenActiviteModal = function(item) {
  _origOpenAct(item);
  const icon = (item && item.icone) || 'bi-calendar-event';
  document.getElementById('aaActIconePreview').className = 'bi ' + icon;
  document.getElementById('aaActIconeLabel').textContent = icon.replace('bi-', '');
  document.getElementById('aaActIcone').value = icon;
};

loadList();
</script>
</body>
</html>
