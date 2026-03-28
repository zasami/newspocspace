<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
$pvRefModules = Db::fetchAll("SELECT id, nom, code FROM modules ORDER BY ordre, nom");
?>
<!-- PV Page - Split View (List + Detail) -->
<link rel="stylesheet" href="/zerdatime/admin/assets/css/editor.css">
<link rel="stylesheet" href="/zerdatime/admin/assets/css/emoji-picker.css">
<div class="split-view">


  <!-- LEFT: PV List (Inbox style) -->
  <div class="split-view-left">
    <!-- Header with filters -->
    <div class="split-view-header">
      <h4><i class="bi bi-file-earmark-text"></i> Procès-Verbaux</h4>
      <select class="form-select form-select-sm" id="pvModuleFilter">
        <option value="">Tous les modules</option>
      </select>
    </div>

    <!-- PV List -->
    <div id="pvListContainer" class="split-view-list">
      <div class="split-view-loading">
        <span class="spinner-border spinner-border-sm"></span>
      </div>
    </div>

    <!-- Pagination -->
    <div class="split-view-footer">
      <span id="pvCount">—</span> PV
    </div>
  </div>

  <!-- RIGHT: PV Detail Panel -->
  <div class="split-view-right">
    <div id="pvDetailPanel" class="split-view-detail">
      <div class="split-view-empty">
        <i class="bi bi-file-earmark-text"></i>
        <p>Sélectionnez un PV pour voir les détails</p>
      </div>
    </div>
  </div>

</div>

<style>
/* PV list items — use shared split-list-item */
#pvListContainer .pv-item {
  padding: 12px 15px;
  border-bottom: 1px solid var(--zt-border-light);
  cursor: pointer;
  transition: background var(--zt-transition);
}
#pvListContainer .pv-item:hover {
  background: var(--zt-accent-bg);
}
#pvListContainer .pv-item.selected {
  background: var(--zt-accent-bg);
  border-left: 4px solid var(--zt-navy);
  padding-left: 11px;
}
.pv-item-title {
  font-size: 0.95rem;
  font-weight: 500;
  color: #333;
  margin-bottom: 4px;
}
.pv-item-meta {
  font-size: 0.8rem;
  color: #999;
}
.pv-detail-card {
  background: var(--zt-bg-card);
  border-radius: var(--zt-radius-md);
  padding: 25px;
  box-shadow: var(--zt-shadow-md);
  border: 1px solid var(--zt-border);
}
.pv-detail-header {
  border-bottom: 2px solid var(--zt-border-light);
  margin-bottom: 20px;
  padding-bottom: 15px;
}
.pv-detail-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--zt-text);
  margin: 0 0 8px 0;
}
.pv-detail-status {
  display: inline-block;
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 12px;
}
.pv-detail-meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
  margin-bottom: 20px;
  padding: 15px;
  background: var(--zt-bg);
  border-radius: 6px;
  font-size: 0.9rem;
}
.pv-meta-item strong {
  display: block;
  margin-bottom: 3px;
  color: #666;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.pv-meta-item {
  color: #333;
}
.pv-section-title {
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #999;
  margin-top: 20px;
  margin-bottom: 10px;
  letter-spacing: 0.5px;
}
.pv-content-box {
  background: var(--zt-bg-card);
  border: 1px solid var(--zt-border);
  border-radius: var(--zt-radius-md);
  padding: 12px;
  line-height: 1.6;
  color: var(--zt-text);
  white-space: pre-wrap;
  word-wrap: break-word;
  min-height: 100px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.pv-participants {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.pv-participant-badge {
  display: inline-block;
  background: var(--zt-accent-bg);
  color: var(--zt-text);
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.8rem;
  border-left: 3px solid var(--zt-navy);
}

/* Rating & Comments */
.pv-rating-stars {
  display: inline-flex;
  gap: 4px;
  font-size: 1.3rem;
  color: #ddd;
  cursor: pointer;
}
.pv-rating-stars i {
  transition: color 0.2s;
}
.pv-rating-stars i.active,
.pv-rating-stars i:hover,
.pv-rating-stars i:hover ~ i {
  color: #FFC107;
}
.pv-rating-stars:hover i {
  color: #FFC107;
}
.pv-rating-stars i:hover ~ i {
  color: #ddd !important;
}

.pv-comment-box {
  background: #fff;
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.pv-comment-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 0.85rem;
}
.pv-comment-author {
  font-weight: 600;
  color: var(--zt-navy);
}
.pv-comment-date {
  color: #999;
}
.pv-comment-body {
  font-size: 0.95rem;
  color: #444;
  line-height: 1.5;
}
.pv-editor-container {
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #fff;
  overflow: hidden;
}
.pv-editor-container .zs-ed-content {
  min-height: 100px;
  padding: 10px;
}
</style>

<script type="application/json" id="__zt_ssr__"><?= json_encode(['modules' => $pvRefModules], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>

