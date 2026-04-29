<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$config = Db::fetchAll("SELECT config_key, config_value FROM mur_config");
$cfg = [];
foreach ($config as $r) $cfg[$r['config_key']] = $r['config_value'];

$pendingCount = (int) Db::getOne("SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'pending'");
?>
<style<?= nonce() ?>>
/* ── Tabs ── */
.mur-tabs { display: flex; gap: 4px; background: var(--cl-bg); border-radius: 12px; padding: 4px; width: fit-content; margin-bottom: 20px; }
.mur-tab { padding: 8px 18px; border-radius: 10px; font-size: .82rem; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--cl-text-muted); transition: var(--cl-transition); position: relative; }
.mur-tab.active { background: var(--cl-surface); color: var(--cl-text); box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.mur-tab .mur-tab-badge { position: absolute; top: 2px; right: 2px; background: #E2B8AE; color: #7B3B2C; font-size: .6rem; padding: 1px 5px; border-radius: 8px; font-weight: 700; }

/* ── Stats ── */
.mur-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.mur-stat-card { background: var(--cl-surface); border: 1.5px solid var(--cl-border-light); border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 12px; min-width: 160px; }
.mur-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.mur-stat-icon.bg-teal { background: #bcd2cb; color: #2d4a43; }
.mur-stat-icon.bg-orange { background: #D4C4A8; color: #6B5B3E; }
.mur-stat-icon.bg-red { background: #E2B8AE; color: #7B3B2C; }
.mur-stat-icon.bg-blue { background: #B8C9D4; color: #3B4F6B; }
.mur-stat-icon.bg-purple { background: #D0C4D8; color: #5B4B6B; }
.mur-stat-value { font-size: 1.3rem; font-weight: 700; line-height: 1; }
.mur-stat-label { font-size: .72rem; color: var(--cl-text-muted); text-transform: uppercase; letter-spacing: .3px; }

/* ── Config panel ── */
.mur-config-card { background: var(--cl-surface); border: 1.5px solid var(--cl-border-light); border-radius: 14px; padding: 24px; }
.mur-config-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--cl-border-light); }
.mur-config-row:last-child { border-bottom: none; }
.mur-config-label { font-weight: 600; font-size: .88rem; }
.mur-config-hint { font-size: .75rem; color: var(--cl-text-muted); margin-top: 2px; }

/* ── Toggle switch ── */
.mur-toggle { position: relative; width: 44px; height: 24px; }
.mur-toggle input { opacity: 0; width: 0; height: 0; }
.mur-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: .3s; }
.mur-toggle-slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; }
.mur-toggle input:checked + .mur-toggle-slider { background: #bcd2cb; }
.mur-toggle input:checked + .mur-toggle-slider::before { transform: translateX(20px); }

/* ── Post list (moderation) ── */
.mur-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light); background: var(--cl-surface); }
.mur-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.mur-table th { background: var(--cl-bg); font-size: .72rem; text-transform: uppercase; letter-spacing: .3px; padding: 10px 14px; font-weight: 600; color: var(--cl-text-muted); }
.mur-table th:first-child { border-top-left-radius: 14px; }
.mur-table th:last-child { border-top-right-radius: 14px; }
.mur-table td { padding: 12px 14px; border-top: 1px solid var(--cl-border-light); vertical-align: middle; }
.mur-table tr:hover { background: rgba(188,210,203,.06); }

.mur-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.mur-avatar-initials { width: 32px; height: 32px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 600; }
.mur-post-body { max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.mur-badge { font-size: .72rem; padding: 3px 10px; border-radius: 8px; font-weight: 600; }
.mur-badge-pending { background: #D4C4A8; color: #6B5B3E; }
.mur-badge-approved { background: #bcd2cb; color: #2d4a43; }
.mur-badge-rejected { background: #E2B8AE; color: #7B3B2C; }

.mur-row-btn { background: none; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: var(--cl-transition); }
.mur-row-btn:hover { background: var(--cl-bg); }
.mur-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }
.mur-row-btn.success:hover { background: #bcd2cb; color: #2d4a43; }

.mur-empty { text-align: center; padding: 40px; color: var(--cl-text-muted); }
.mur-empty i { font-size: 2rem; opacity: .4; display: block; margin-bottom: 8px; }

/* ── Filter pills ── */
.mur-filters { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.mur-filter-pill { padding: 6px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; border: 1.5px solid var(--cl-border-light); background: var(--cl-surface); cursor: pointer; transition: var(--cl-transition); }
.mur-filter-pill.active { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.mur-filter-pill:hover:not(.active) { border-color: var(--cl-border-hover); }

/* ── Categories input ── */
.mur-categories-input { font-size: .85rem; }

/* ── Hero live preview ── */
.mur-hero-preview { position: relative; border-radius: 14px; overflow: hidden; min-height: 160px; cursor: pointer; transition: all .2s; background-size: cover; background-position: center; }
.mur-hero-preview-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.5) 100%); pointer-events: none; }
.mur-hero-preview-content { position: relative; z-index: 1; display: flex; align-items: flex-end; gap: 16px; padding: 60px 24px 20px; pointer-events: none; }
.mur-hero-preview-avatar { width: 60px; height: 60px; border-radius: 50%; border: 3px solid #fff; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.2); overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.mur-hero-preview-avatar img { width: 100%; height: 100%; object-fit: contain; }
.mur-hero-preview-text { flex: 1; min-width: 0; }
.mur-hero-preview-text h3 { color: #fff; font-size: 1.1rem; font-weight: 700; margin: 0 0 2px; text-shadow: 0 1px 3px rgba(0,0,0,.3); }
.mur-hero-preview-text p { color: rgba(255,255,255,.8); font-size: .78rem; margin: 0; text-shadow: 0 1px 2px rgba(0,0,0,.2); }
.mur-hero-preview-stats { display: flex; gap: 18px; flex-shrink: 0; }
.mur-hero-preview-stats > div { text-align: center; }
.mur-hero-preview-stats strong { display: block; font-size: 1rem; font-weight: 700; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.3); }
.mur-hero-preview-stats span { font-size: .6rem; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: .3px; }
/* Drop overlay (plus icon on hover) */
.mur-hero-preview-drop { position: absolute; inset: 0; z-index: 2; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .2s; background: rgba(0,0,0,.3); backdrop-filter: blur(2px); }
.mur-hero-preview:hover .mur-hero-preview-drop { opacity: 1; }
.mur-hero-preview-drop i { font-size: 2.5rem; color: #fff; width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; }
.mur-hero-preview.drag-over .mur-hero-preview-drop { opacity: 1; background: rgba(0,0,0,.45); }
/* Edit/delete buttons */
.mur-hero-preview-actions { position: absolute; top: 10px; right: 10px; z-index: 3; display: flex; gap: 6px; opacity: 0; transition: opacity .2s; }
.mur-hero-preview:hover .mur-hero-preview-actions { opacity: 1; }
.mur-hero-drop-btn { width: 34px; height: 34px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: .85rem; background: rgba(255,255,255,.9); color: #333; backdrop-filter: blur(4px); transition: all .2s; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
.mur-hero-drop-btn:hover { background: #fff; transform: scale(1.1); }
.mur-hero-drop-btn-danger:hover { color: #7B3B2C; background: #ffeae6; }
@media (max-width: 768px) {
    .mur-hero-preview-actions { opacity: 1; }
    .mur-hero-preview-content { flex-direction: column; align-items: center; text-align: center; padding: 40px 16px 16px; gap: 8px; }
    .mur-hero-preview-stats { justify-content: center; }
}

/* ── Image picker modal ── */
.cm-tab-btn { border-radius: 10px 10px 0 0 !important; font-size: .85rem; font-weight: 600; padding: 8px 16px; }
.cm-tab-btn.active { background: var(--cl-surface, #fff) !important; border-color: #e5e7eb #e5e7eb #fff !important; }
.cm-upload-zone { border: 2px dashed #e5e7eb; border-radius: 14px; min-height: 200px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s; position: relative; overflow: hidden; }
.cm-upload-zone:hover { border-color: #bcd2cb; background: rgba(188,210,203,.05); }
.cm-upload-zone.dragover { border-color: #2d4a43; background: rgba(188,210,203,.12); }
.cm-upload-placeholder { text-align: center; color: #999; pointer-events: none; }
.cm-upload-placeholder i { font-size: 2.5rem; color: #ccc; display: block; margin-bottom: 8px; }
.cm-upload-placeholder p { font-size: .9rem; font-weight: 600; margin: 0 0 4px; color: #666; }
.cm-upload-placeholder span { font-size: .78rem; }
.cm-upload-preview-wrap { position: absolute; inset: 0; }
.cm-upload-preview-wrap img { width: 100%; height: 100%; object-fit: cover; }
.cm-pixabay-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; max-height: 360px; overflow-y: auto; }
.cm-pixabay-item { aspect-ratio: 16/10; border-radius: 10px; overflow: hidden; cursor: pointer; position: relative; transition: transform .2s, box-shadow .2s; }
.cm-pixabay-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
.cm-pixabay-item img { width: 100%; height: 100%; object-fit: cover; }
.cm-pixabay-item-overlay { position: absolute; bottom: 0; left: 0; right: 0; padding: 4px 8px; background: linear-gradient(transparent, rgba(0,0,0,.6)); font-size: .65rem; color: #fff; }
.cm-pixabay-empty { grid-column: 1 / -1; text-align: center; padding: 40px; color: #999; }
.cm-pixabay-empty i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .4; }
.cm-pixabay-empty p { margin: 0; font-size: .85rem; }

@media (max-width: 768px) {
    .mur-stats { flex-direction: column; }
    .mur-config-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    .cm-pixabay-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>

<!-- Stats -->
<div class="mur-stats" id="murStats">
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-teal"><i class="bi bi-chat-square-text"></i></div>
        <div><div class="mur-stat-value" id="statTotal">-</div><div class="mur-stat-label">Posts total</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-orange"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="mur-stat-value" id="statPending">-</div><div class="mur-stat-label">En attente</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-blue"><i class="bi bi-calendar-event"></i></div>
        <div><div class="mur-stat-value" id="statToday">-</div><div class="mur-stat-label">Aujourd'hui</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-purple"><i class="bi bi-chat-dots"></i></div>
        <div><div class="mur-stat-value" id="statComments">-</div><div class="mur-stat-label">Commentaires</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-red"><i class="bi bi-heart"></i></div>
        <div><div class="mur-stat-value" id="statLikes">-</div><div class="mur-stat-label">Likes</div></div>
    </div>
</div>

<!-- Tabs -->
<div class="mur-tabs">
    <button class="mur-tab active" data-tab="moderation">
        Modération
        <?php if ($pendingCount > 0): ?><span class="mur-tab-badge" id="pendingBadge"><?= $pendingCount ?></span><?php endif; ?>
    </button>
    <button class="mur-tab" data-tab="config">Paramètres</button>
</div>

<!-- TAB: Moderation -->
<div id="tab-moderation">
    <div class="mur-filters">
        <button class="mur-filter-pill active" data-filter="all">Tous</button>
        <button class="mur-filter-pill" data-filter="pending">En attente</button>
        <button class="mur-filter-pill" data-filter="approved">Approuvés</button>
        <button class="mur-filter-pill" data-filter="rejected">Rejetés</button>
    </div>

    <div class="mur-table-wrap">
        <table class="mur-table">
            <thead>
                <tr>
                    <th style="width:5%"></th>
                    <th style="width:20%">Auteur</th>
                    <th style="width:35%">Contenu</th>
                    <th style="width:10%">Catégorie</th>
                    <th style="width:10%">Statut</th>
                    <th style="width:10%">Date</th>
                    <th style="width:10%; text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="murPostsBody">
                <tr><td colspan="7" class="mur-empty"><span class="spinner"></span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3 gap-2" id="murPagination"></div>
</div>

<!-- TAB: Config -->
<div id="tab-config" style="display:none">
    <div class="mur-config-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2"></i>Règles du mur</h6>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Modération avant publication</div>
                <div class="mur-config-hint">Les posts doivent être approuvés par un admin avant d'apparaître sur le mur</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgModeration" <?= ($cfg['moderation_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Commentaires anonymes autorisés</div>
                <div class="mur-config-hint">Les utilisateurs peuvent commenter sans que leur nom soit visible</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgAnonymousComments" <?= ($cfg['allow_anonymous_comments'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Posts personnels tolérés</div>
                <div class="mur-config-hint">Autoriser les posts non-professionnels (anniversaires, sorties, etc.)</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgPrivatePosts" <?= ($cfg['allow_private_posts'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Activer les commentaires</div>
                <div class="mur-config-hint">Les utilisateurs peuvent commenter les posts</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgComments" <?= ($cfg['allow_comments'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Activer les likes</div>
                <div class="mur-config-hint">Les utilisateurs peuvent liker les posts et commentaires</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgLikes" <?= ($cfg['allow_likes'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Limite de posts par jour</div>
                <div class="mur-config-hint">Nombre maximum de posts qu'un utilisateur peut publier par jour</div>
            </div>
            <input type="number" id="cfgMaxPosts" class="form-control form-control-sm" style="width:80px" min="1" max="50" value="<?= h($cfg['max_posts_per_day'] ?? '10') ?>">
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Catégories disponibles</div>
                <div class="mur-config-hint">Liste séparée par des virgules (ex: general, info, evenement, social)</div>
            </div>
            <input type="text" id="cfgCategories" class="form-control form-control-sm mur-categories-input" style="width:300px" value="<?= h($cfg['post_categories'] ?? 'general,info,evenement,social') ?>">
        </div>

        <div class="mt-3 text-end">
            <button class="btn btn-primary btn-sm" id="btnSaveConfig"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
        </div>
    </div>

    <!-- Hero settings — Live preview -->
    <div class="mur-config-card mt-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Personnalisation du Hero</h6>

        <?php
            $emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '/newspocspace/ss-logo.png';
            $heroColor = $cfg['hero_color'] ?? '#2d4a43';
            $accentColor = $cfg['accent_color'] ?? '#bcd2cb';
            $heroImage = $cfg['hero_image'] ?? '';
            $heroBg = $heroImage
                ? "background-image:url('" . h($heroImage) . "');background-size:cover;background-position:center"
                : "background:linear-gradient(135deg,{$heroColor} 0%,{$accentColor} 100%)";
        ?>

        <!-- Live hero preview -->
        <div class="mur-hero-preview" id="heroPreview" style="<?= $heroBg ?>">
            <div class="mur-hero-preview-overlay"></div>
            <div class="mur-hero-preview-content">
                <div class="mur-hero-preview-avatar"><img src="<?= h($emsLogo) ?>" alt=""></div>
                <div class="mur-hero-preview-text">
                    <h3 id="heroPreviewTitle"><?= h($cfg['hero_title'] ?? 'Mur social') ?></h3>
                    <p id="heroPreviewSubtitle"><?= h($cfg['hero_subtitle'] ?? 'Votre réseau interne') ?></p>
                </div>
                <div class="mur-hero-preview-stats">
                    <div><strong>12</strong><span>Posts</span></div>
                    <div><strong>8</strong><span>Commentaires</span></div>
                    <div><strong>5</strong><span>Membres</span></div>
                </div>
            </div>
            <div class="mur-hero-preview-drop" id="heroDropOverlay"><i class="bi bi-camera-fill"></i><span>Changer l'image</span></div>
            <div class="mur-hero-preview-actions">
                <button class="mur-hero-drop-btn" id="heroChangeBtn" title="Modifier"><i class="bi bi-pencil"></i></button>
                <button class="mur-hero-drop-btn mur-hero-drop-btn-danger" id="heroDeleteBtn" title="Supprimer"><i class="bi bi-trash"></i></button>
            </div>
        </div>

        <!-- Image picker modal -->
        <div class="modal fade" id="heroImageModal" tabindex="-1">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius:18px;overflow:hidden">
              <div class="modal-header" style="border-bottom:1px solid #eee;padding:16px 24px">
                <h6 class="modal-title fw-bold"><i class="bi bi-image me-2"></i>Choisir une image de couverture</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-0">
                <!-- Tabs -->
                <ul class="nav nav-tabs px-3 pt-3" style="border-bottom:none;gap:4px">
                  <li class="nav-item"><button class="nav-link active cm-tab-btn" data-cm-tab="upload"><i class="bi bi-cloud-arrow-up me-1"></i>Uploader</button></li>
                  <li class="nav-item"><button class="nav-link cm-tab-btn" data-cm-tab="pixabay"><i class="bi bi-search me-1"></i>Pixabay</button></li>
                </ul>

                <!-- Upload tab -->
                <div class="cm-panel p-3" id="cmPanelUpload">
                  <div class="cm-upload-zone" id="cmUploadZone">
                    <div class="cm-upload-placeholder" id="cmUploadPlaceholder">
                      <i class="bi bi-cloud-arrow-up"></i>
                      <p>Glissez une image ou cliquez pour charger</p>
                      <span>JPG, PNG, WebP — max 5 Mo</span>
                    </div>
                    <div class="cm-upload-preview-wrap d-none" id="cmUploadPreviewWrap">
                      <img src="" alt="" id="cmUploadPreviewImg">
                      <button class="mur-hero-drop-btn mur-hero-drop-btn-danger" id="cmUploadRemove" style="position:absolute;top:8px;right:8px"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <input type="file" id="cmUploadInput" accept="image/jpeg,image/png,image/webp" style="display:none">
                  </div>
                  <button class="btn btn-primary w-100 mt-2" id="cmUploadBtn" disabled><i class="bi bi-check-lg me-1"></i>Utiliser cette image</button>
                </div>

                <!-- Pixabay tab -->
                <div class="cm-panel p-3 d-none" id="cmPanelPixabay">
                  <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="cmPixabayInput" placeholder="Rechercher des photos...">
                    <select class="form-select" id="cmPixabayCat" style="max-width:140px">
                      <option value="">Toutes</option>
                      <option value="backgrounds">Fonds</option>
                      <option value="nature">Nature</option>
                      <option value="business">Business</option>
                      <option value="people">Personnes</option>
                      <option value="places">Lieux</option>
                      <option value="food">Cuisine</option>
                      <option value="buildings">Bâtiments</option>
                      <option value="travel">Voyage</option>
                    </select>
                    <button class="btn btn-primary" id="cmPixabaySearchBtn"><i class="bi bi-search"></i></button>
                  </div>
                  <div class="cm-pixabay-grid" id="cmPixabayGrid">
                    <div class="cm-pixabay-empty"><i class="bi bi-images"></i><p>Recherchez des photos libres de droits</p></div>
                  </div>
                  <div class="text-center mt-2 d-none" id="cmPixabayLoading"><span class="spinner-border spinner-border-sm"></span> Recherche...</div>
                  <button class="btn btn-outline-secondary w-100 mt-2 d-none" id="cmPixabayMore"><i class="bi bi-plus-circle me-1"></i>Voir plus</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-3 g-3">
            <div class="col-md-6">
                <label class="mur-config-label mb-1">Titre</label>
                <input type="text" id="cfgHeroTitle" class="form-control form-control-sm" value="<?= h($cfg['hero_title'] ?? 'Mur social') ?>">
            </div>
            <div class="col-md-6">
                <label class="mur-config-label mb-1">Sous-titre</label>
                <input type="text" id="cfgHeroSubtitle" class="form-control form-control-sm" value="<?= h($cfg['hero_subtitle'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="mur-config-label mb-1">Couleur hero</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" id="cfgHeroColor" value="<?= h($heroColor) ?>" style="width:40px;height:32px;padding:1px;border:1px solid #ddd;border-radius:6px;cursor:pointer">
                    <span class="mur-config-hint" style="margin:0">Fond si pas d'image</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="mur-config-label mb-1">Couleur accent</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" id="cfgAccentColor" value="<?= h($accentColor) ?>" style="width:40px;height:32px;padding:1px;border:1px solid #ddd;border-radius:6px;cursor:pointer">
                    <span class="mur-config-hint" style="margin:0">Boutons actifs</span>
                </div>
            </div>
        </div>

        <div class="mt-3 text-end">
            <button class="btn btn-primary btn-sm" id="btnSaveHero"><i class="bi bi-check-lg me-1"></i>Enregistrer hero</button>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    const $ = s => document.querySelector(s);
    const $$ = s => document.querySelectorAll(s);
    let currentFilter = 'all';
    let currentPage = 1;

    // ── Tabs ──
    $$('.mur-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            $$('.mur-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const tabId = tab.dataset.tab;
            document.getElementById('tab-moderation').style.display = tabId === 'moderation' ? '' : 'none';
            document.getElementById('tab-config').style.display = tabId === 'config' ? '' : 'none';
        });
    });

    // ── Stats ──
    async function loadStats() {
        const res = await adminApiPost('admin_get_mur_stats');
        if (!res.success) return;
        $('#statTotal').textContent = res.total_posts;
        $('#statPending').textContent = res.pending_posts;
        $('#statToday').textContent = res.posts_today;
        $('#statComments').textContent = res.total_comments;
        $('#statLikes').textContent = res.total_likes;
    }

    // ── Filters ──
    $$('.mur-filter-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            $$('.mur-filter-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentFilter = pill.dataset.filter;
            currentPage = 1;
            loadPosts();
        });
    });

    // ── Posts ──
    async function loadPosts() {
        const body = $('#murPostsBody');
        body.innerHTML = '<tr><td colspan="7" class="mur-empty"><span class="spinner"></span></td></tr>';

        const res = await adminApiPost('admin_get_mur_posts', { status: currentFilter, page: currentPage, limit: 20 });
        if (!res.success) { body.innerHTML = '<tr><td colspan="7" class="mur-empty">Erreur de chargement</td></tr>'; return; }

        if (!res.posts.length) {
            body.innerHTML = '<tr><td colspan="7" class="mur-empty"><i class="bi bi-chat-square"></i>Aucun post</td></tr>';
            $('#murPagination').innerHTML = '';
            return;
        }

        body.innerHTML = res.posts.map(p => {
            const initials = (p.prenom?.[0] || '') + (p.nom?.[0] || '');
            const avatarHtml = p.avatar_url
                ? `<img src="${escapeHtml(p.avatar_url)}" class="mur-avatar">`
                : `<div class="mur-avatar-initials">${escapeHtml(initials)}</div>`;
            const statusClass = p.status === 'pending' ? 'mur-badge-pending' : p.status === 'approved' ? 'mur-badge-approved' : 'mur-badge-rejected';
            const date = new Date(p.created_at).toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            const pinIcon = p.is_pinned == 1 ? 'bi-pin-fill text-warning' : 'bi-pin';

            return `<tr>
                <td>${p.is_pinned == 1 ? '<i class="bi bi-pin-fill text-warning" title="Épinglé"></i>' : ''}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        ${avatarHtml}
                        <div>
                            <div class="fw-semibold" style="font-size:.85rem">${escapeHtml(p.prenom + ' ' + p.nom)}</div>
                            <div style="font-size:.72rem; color:var(--cl-text-muted)">${escapeHtml(p.fonction_nom || '')}</div>
                        </div>
                    </div>
                </td>
                <td><div class="mur-post-body">${escapeHtml(p.body.replace(/<[^>]+>/g, ''))}</div></td>
                <td><span class="mur-badge" style="background:#f0f0f0">${escapeHtml(p.category)}</span></td>
                <td><span class="mur-badge ${statusClass}">${p.status === 'pending' ? 'En attente' : p.status === 'approved' ? 'Approuvé' : 'Rejeté'}</span></td>
                <td style="font-size:.8rem">${date}</td>
                <td style="text-align:center">
                    ${p.status === 'pending' ? `
                        <button class="mur-row-btn success" title="Approuver" data-action="approve" data-id="${p.id}"><i class="bi bi-check-lg"></i></button>
                        <button class="mur-row-btn danger" title="Rejeter" data-action="reject" data-id="${p.id}"><i class="bi bi-x-lg"></i></button>
                    ` : ''}
                    <button class="mur-row-btn" title="${p.is_pinned == 1 ? 'Désépingler' : 'Épingler'}" data-action="pin" data-id="${p.id}"><i class="bi ${pinIcon}"></i></button>
                    <button class="mur-row-btn danger" title="Supprimer" data-action="delete" data-id="${p.id}"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }).join('');

        // Pagination
        let pagHtml = '';
        if (res.total_pages > 1) {
            for (let i = 1; i <= res.total_pages; i++) {
                pagHtml += `<button class="btn btn-sm ${i === res.page ? 'btn-primary' : 'btn-light'}" data-page="${i}">${i}</button>`;
            }
        }
        $('#murPagination').innerHTML = pagHtml;

        // Action handlers
        body.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const id = btn.dataset.id;
                let res2;

                if (action === 'approve' || action === 'reject') {
                    res2 = await adminApiPost('admin_moderate_mur_post', { id, moderation: action });
                } else if (action === 'pin') {
                    res2 = await adminApiPost('admin_pin_mur_post', { id });
                } else if (action === 'delete') {
                    if (!confirm('Supprimer ce post ?')) return;
                    res2 = await adminApiPost('admin_delete_mur_post', { id });
                }

                if (res2?.success) {
                    showToast(res2.message || 'OK', 'success');
                    loadPosts();
                    loadStats();
                } else {
                    showToast(res2?.message || 'Erreur', 'error');
                }
            });
        });

        // Pagination handlers
        $$('#murPagination button').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page);
                loadPosts();
            });
        });
    }

    // ── Save Config ──
    $('#btnSaveConfig')?.addEventListener('click', async () => {
        const config = {
            moderation_enabled: $('#cfgModeration').checked ? '1' : '0',
            allow_anonymous_comments: $('#cfgAnonymousComments').checked ? '1' : '0',
            allow_private_posts: $('#cfgPrivatePosts').checked ? '1' : '0',
            allow_comments: $('#cfgComments').checked ? '1' : '0',
            allow_likes: $('#cfgLikes').checked ? '1' : '0',
            max_posts_per_day: $('#cfgMaxPosts').value || '10',
            post_categories: $('#cfgCategories').value || 'general,info,evenement,social',
        };

        const res = await adminApiPost('admin_save_mur_config', { config });
        if (res.success) {
            showToast('Configuration sauvegardée', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ── Hero live preview ──
    const heroPreview = $('#heroPreview');
    let hasHeroImage = <?= !empty($cfg['hero_image']) ? 'true' : 'false' ?>;
    const heroModal = new bootstrap.Modal($('#heroImageModal'));

    function updatePreviewBg() {
        if (hasHeroImage) return;
        const c1 = $('#cfgHeroColor').value || '#2d4a43';
        const c2 = $('#cfgAccentColor').value || '#bcd2cb';
        heroPreview.style.backgroundImage = 'none';
        heroPreview.style.background = `linear-gradient(135deg, ${c1} 0%, ${c2} 100%)`;
    }

    function setHeroImage(url) {
        heroPreview.style.background = `url('${url}') center/cover no-repeat`;
        hasHeroImage = true;
    }

    // Live text + color updates
    $('#cfgHeroTitle')?.addEventListener('input', () => { $('#heroPreviewTitle').textContent = $('#cfgHeroTitle').value || 'Mur social'; });
    $('#cfgHeroSubtitle')?.addEventListener('input', () => { $('#heroPreviewSubtitle').textContent = $('#cfgHeroSubtitle').value || ''; });
    $('#cfgHeroColor')?.addEventListener('input', updatePreviewBg);
    $('#cfgAccentColor')?.addEventListener('input', updatePreviewBg);

    // Open modal on click/change
    heroPreview?.addEventListener('click', (e) => { if (!e.target.closest('.mur-hero-drop-btn')) heroModal.show(); });
    $('#heroChangeBtn')?.addEventListener('click', (e) => { e.stopPropagation(); heroModal.show(); });

    // Delete hero image
    $('#heroDeleteBtn')?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const res = await adminApiPost('admin_save_mur_config', { config: { hero_image: '' } });
        if (res.success) { hasHeroImage = false; updatePreviewBg(); showToast('Image supprimée', 'success'); }
    });

    // ── Modal tabs ──
    $$('.cm-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            $$('.cm-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            $$('.cm-panel').forEach(p => p.classList.add('d-none'));
            const tab = btn.dataset.cmTab;
            $(`#cmPanel${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.remove('d-none');
        });
    });

    // ── Upload tab ──
    let uploadedFile = null;
    const uploadZone = $('#cmUploadZone');
    const uploadInput = $('#cmUploadInput');

    uploadZone?.addEventListener('click', (e) => {
        if (e.target.closest('#cmUploadRemove')) return;
        if ($('#cmUploadPreviewWrap').classList.contains('d-none')) uploadInput.click();
    });
    ['dragenter','dragover'].forEach(ev => uploadZone?.addEventListener(ev, (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => uploadZone?.addEventListener(ev, () => uploadZone.classList.remove('dragover')));
    uploadZone?.addEventListener('drop', (e) => { e.preventDefault(); if (e.dataTransfer?.files?.[0]) previewUpload(e.dataTransfer.files[0]); });
    uploadInput?.addEventListener('change', () => { if (uploadInput.files[0]) previewUpload(uploadInput.files[0]); });

    function previewUpload(file) {
        if (!file.type.startsWith('image/')) { showToast('Image requise', 'error'); return; }
        if (file.size > 5 * 1024 * 1024) { showToast('Max 5 Mo', 'error'); return; }
        uploadedFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            $('#cmUploadPreviewImg').src = e.target.result;
            $('#cmUploadPreviewWrap').classList.remove('d-none');
            $('#cmUploadPlaceholder').classList.add('d-none');
            $('#cmUploadBtn').disabled = false;
        };
        reader.readAsDataURL(file);
    }

    $('#cmUploadRemove')?.addEventListener('click', (e) => {
        e.stopPropagation();
        uploadedFile = null;
        $('#cmUploadPreviewWrap').classList.add('d-none');
        $('#cmUploadPlaceholder').classList.remove('d-none');
        $('#cmUploadBtn').disabled = true;
        uploadInput.value = '';
    });

    $('#cmUploadBtn')?.addEventListener('click', async () => {
        if (!uploadedFile) return;
        $('#cmUploadBtn').disabled = true;
        $('#cmUploadBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Upload...';

        const fd = new FormData();
        fd.append('action', 'admin_upload_mur_hero');
        fd.append('hero_image', uploadedFile);
        const res = await fetch('/newspocspace/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
            body: fd,
        }).then(r => r.json());

        $('#cmUploadBtn').disabled = false;
        $('#cmUploadBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Utiliser cette image';

        if (res.success) {
            setHeroImage(res.url);
            heroModal.hide();
            showToast('Image hero mise à jour', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ── Pixabay tab ──
    let pxPage = 1;
    let pxTotal = 0;

    async function searchPx(append) {
        const query = $('#cmPixabayInput').value.trim();
        const cat = $('#cmPixabayCat').value;
        if (!query && !cat) { showToast('Entrez un terme de recherche', 'warning'); return; }

        if (!append) { pxPage = 1; $('#cmPixabayGrid').innerHTML = ''; }
        else pxPage++;

        $('#cmPixabayLoading').classList.remove('d-none');
        $('#cmPixabayMore').classList.add('d-none');

        const res = await adminApiPost('admin_search_pixabay', { query: query || cat, category: cat, page: pxPage });
        $('#cmPixabayLoading').classList.add('d-none');

        if (!res.success) { showToast(res.message || 'Erreur', 'error'); return; }
        pxTotal = res.total || 0;

        if (!res.hits?.length && !append) {
            $('#cmPixabayGrid').innerHTML = '<div class="cm-pixabay-empty"><i class="bi bi-emoji-frown"></i><p>Aucun résultat</p></div>';
            return;
        }

        const grid = $('#cmPixabayGrid');
        res.hits.forEach(hit => {
            const item = document.createElement('div');
            item.className = 'cm-pixabay-item';
            item.innerHTML = `<img src="${hit.webformatURL}" alt="${escapeHtml(hit.tags || '')}" loading="lazy"><div class="cm-pixabay-item-overlay">${escapeHtml(hit.user)}</div>`;
            item.addEventListener('click', () => selectPxImage(hit.largeImageURL));
            grid.appendChild(item);
        });

        if (grid.querySelectorAll('.cm-pixabay-item').length < pxTotal) {
            $('#cmPixabayMore').classList.remove('d-none');
        }
    }

    async function selectPxImage(url) {
        $('#cmPixabayLoading').classList.remove('d-none');
        const res = await adminApiPost('admin_save_pixabay_image', { image_url: url });
        $('#cmPixabayLoading').classList.add('d-none');

        if (res.success) {
            setHeroImage(res.url);
            heroModal.hide();
            showToast('Image Pixabay appliquée', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    $('#cmPixabaySearchBtn')?.addEventListener('click', () => searchPx(false));
    $('#cmPixabayInput')?.addEventListener('keypress', (e) => { if (e.key === 'Enter') searchPx(false); });
    $('#cmPixabayMore')?.addEventListener('click', () => searchPx(true));

    // ── Save Hero (text + colors) ──
    $('#btnSaveHero')?.addEventListener('click', async () => {
        const config = {
            hero_title: $('#cfgHeroTitle').value || 'Mur social',
            hero_subtitle: $('#cfgHeroSubtitle').value || '',
            hero_color: $('#cfgHeroColor').value || '#2d4a43',
            accent_color: $('#cfgAccentColor').value || '#bcd2cb',
        };
        const res = await adminApiPost('admin_save_mur_config', { config });
        if (res.success) showToast('Hero mis à jour', 'success');
        else showToast(res.message || 'Erreur', 'error');
    });

    // ── Init ──
    loadStats();
    loadPosts();
})();
</script>
