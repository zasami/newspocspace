<?php
require_once __DIR__ . "/../init.php";
if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }

$ssrCategories = Db::fetchAll("SELECT id, nom, slug, icone, couleur FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
$ssrData = ['categories' => $ssrCategories];
?>
<div class="page-header">
  <h1><i class="bi bi-book"></i> Base de connaissances</h1>
  <p>Protocoles, procédures et documentation interne</p>
</div>

<!-- Category filters -->
<div id="wikiCatFilters" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;"></div>

<!-- Pages grid -->
<div id="wikiGrid"></div>
<div id="wikiEmpty" class="page-empty" style="display:none">
  <i class="bi bi-book" style="font-size:2.5rem;display:block;margin-bottom:8px;opacity:.3"></i>
  Aucune page dans cette catégorie
</div>

<!-- Read view -->
<div id="wikiReadView" style="display:none">
  <div style="margin-bottom:12px">
    <button class="btn btn-light btn-sm" id="wikiBackBtn"><i class="bi bi-arrow-left"></i> Retour</button>
  </div>
  <div id="wikiReadPanel" class="wiki-read-panel"></div>
</div>

<style>
.wiki-cat-btn { border:1px solid #dee2e6; background:#fff; border-radius:20px; padding:5px 14px; font-size:.8rem; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:5px; }
.wiki-cat-btn:hover, .wiki-cat-btn.active { color:#fff; border-color:transparent; }

.wiki-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px; }
.wiki-card { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:14px; cursor:pointer; transition:all .2s; position:relative; }
.wiki-card:hover { border-color:#2d4a43; box-shadow:0 2px 8px rgba(0,0,0,.06); transform:translateY(-1px); }
.wiki-card.pinned { border-left:3px solid #2d4a43; }
.wiki-card-title { font-weight:600; font-size:.92rem; margin-bottom:4px; }
.wiki-card-desc { font-size:.78rem; color:#6c757d; margin-bottom:8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.wiki-card-meta { font-size:.7rem; color:#adb5bd; display:flex; gap:8px; align-items:center; }
.wiki-card-cat { font-size:.68rem; padding:2px 8px; border-radius:10px; color:#fff; display:inline-flex; align-items:center; gap:3px; }
.wiki-card-pin { position:absolute; top:8px; right:10px; color:#2d4a43; }

.wiki-read-panel { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:24px; }
.wiki-read-panel h1 { font-size:1.3rem; font-weight:700; margin-bottom:4px; }
.wiki-read-meta { font-size:.78rem; color:#6c757d; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }
.wiki-read-content { font-size:.9rem; line-height:1.7; }
.wiki-read-content h2 { font-size:1.1rem; font-weight:700; margin-top:20px; margin-bottom:8px; color:#2d4a43; }
.wiki-read-content h3 { font-size:.95rem; font-weight:600; margin-top:16px; }
.wiki-read-content img { max-width:100%; border-radius:6px; margin:8px 0; }
.wiki-read-content blockquote { border-left:3px solid #2d4a43; padding-left:12px; color:#6c757d; margin:12px 0; }
.wiki-read-content ul, .wiki-read-content ol { padding-left:20px; }
</style>

<script type="application/json" id="__ss_ssr__"><?= json_encode($ssrData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?></script>
