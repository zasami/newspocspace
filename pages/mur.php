<link rel="stylesheet" href="/spocspace/admin/assets/css/emoji-picker.css">
<div id="mur-page">

    <!-- Hero banner -->
    <div class="mur-hero" id="murHero">
        <div class="mur-hero-cover" id="murHeroCover"></div>
        <div class="mur-hero-overlay"></div>
        <div class="mur-hero-inner">
            <div class="mur-hero-avatar" id="murHeroAvatar">
                <img src="/spocspace/logo.png" alt="" id="murHeroLogo">
            </div>
            <div class="mur-hero-info">
                <h1 id="murHeroTitle">Mur social</h1>
                <p id="murHeroSubtitle">Votre réseau interne</p>
            </div>
            <div class="mur-hero-stats">
                <div class="mur-hero-stat"><strong id="murStatPosts">-</strong><span>Posts</span></div>
                <div class="mur-hero-stat"><strong id="murStatComments">-</strong><span>Commentaires</span></div>
                <div class="mur-hero-stat"><strong id="murStatContributors">-</strong><span>Membres</span></div>
            </div>
        </div>
    </div>

    <!-- 3-column layout -->
    <div class="mur-3col">

        <!-- LEFT SIDEBAR -->
        <aside class="mur-sidebar-left">
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title">Catégories</div>
                <nav class="mur-cat-nav" id="murCatNav"></nav>
            </div>
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-shield-check"></i> Règles</div>
                <div class="mur-sidebar-rules" id="murRules"></div>
            </div>
        </aside>

        <!-- CENTER -->
        <div class="mur-center">
            <!-- Composer -->
            <div class="mur-composer" id="murComposer">
                <div class="mur-composer-row">
                    <div class="mur-composer-avatar" id="composerAvatar"></div>
                    <div class="mur-composer-wrap" id="composerWrap">
                        <div id="composerEditor" class="mur-composer-editor"></div>
                        <div class="mur-composer-bar">
                            <div class="mur-composer-toolbar" id="composerToolbar">
                                <button type="button" class="mur-tb-btn" data-action="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="mur-tb-btn" data-action="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="mur-tb-btn" data-action="highlight" title="Surligner"><i class="bi bi-brush"></i></button>
                                <span class="mur-tb-sep"></span>
                                <button type="button" class="mur-tb-btn" data-action="bulletList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                                <button type="button" class="mur-tb-btn" data-action="orderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                                <span class="mur-tb-sep"></span>
                                <button type="button" class="mur-tb-btn mur-tb-emoji" data-action="emoji" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                                <select id="composerCategory" class="mur-composer-cat"></select>
                            </div>
                            <div class="mur-composer-icons">
                                <label class="mur-composer-icon-btn" title="Photo"><i class="bi bi-camera"></i><input type="file" id="composerFiles" multiple accept="image/*" class="d-none"></label>
                                <label class="mur-composer-icon-btn" title="Vidéo"><i class="bi bi-camera-video"></i></label>
                                <label class="mur-composer-icon-btn" title="Pièce jointe"><i class="bi bi-paperclip"></i></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mur-composer-preview" style="display:none" id="composerPreview"></div>
                <div class="mur-composer-bottom">
                    <label class="mur-composer-anon" style="display:none" id="composerAnonWrap">
                        <input type="checkbox" id="composerAnon"> Anonyme
                    </label>
                    <button class="mur-btn-post" id="btnPost"><i class="bi bi-send-fill"></i> Publier</button>
                </div>
            </div>

            <!-- Feed -->
            <div id="murFeed"><div class="mur-loading"><span class="spinner"></span></div></div>
            <div id="murLoadMore" class="mur-load-more" style="display:none">
                <button class="mur-btn-load-more" id="btnLoadMore">Voir plus</button>
            </div>
        </div>

        <!-- RIGHT SIDEBAR -->
        <aside class="mur-sidebar-right">
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-images"></i> Galerie média</div>
                <div class="mur-gallery-grid" id="murGallery">
                    <div class="mur-widget-empty">Aucune photo</div>
                </div>
            </div>
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-people"></i> Contributeurs actifs</div>
                <div class="mur-widget-list" id="murTopContributors">
                    <div class="mur-widget-empty">—</div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Lightbox -->
<div class="mur-lightbox" style="display:none" id="murLightbox">
    <div class="mur-lightbox-overlay"></div>
    <button class="mur-lightbox-close" id="murLightboxClose"><i class="bi bi-x-lg"></i></button>
    <button class="mur-lightbox-prev" id="murLightboxPrev"><i class="bi bi-chevron-left"></i></button>
    <button class="mur-lightbox-next" id="murLightboxNext"><i class="bi bi-chevron-right"></i></button>
    <img class="mur-lightbox-img" id="murLightboxImg" src="" alt="">
</div>
