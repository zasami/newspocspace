<div id="mur-page">

    <!-- Hero banner -->
    <div class="mur-hero">
        <div class="mur-hero-bg"></div>
        <div class="mur-hero-inner">
            <div class="mur-hero-icon"><i class="bi bi-chat-square-heart"></i></div>
            <div class="mur-hero-text">
                <h1>Mur social</h1>
                <p>Partagez avec vos collègues</p>
            </div>
            <div class="mur-hero-stats">
                <div class="mur-hero-stat">
                    <strong id="murStatPosts">-</strong>
                    <span>Publications</span>
                </div>
                <div class="mur-hero-stat">
                    <strong id="murStatToday">-</strong>
                    <span>Aujourd'hui</span>
                </div>
                <div class="mur-hero-stat">
                    <strong id="murStatContributors">-</strong>
                    <span>Contributeurs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3-column layout -->
    <div class="mur-3col">

        <!-- LEFT SIDEBAR: Categories + info -->
        <aside class="mur-sidebar-left" id="murSidebarLeft">
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title">Catégories</div>
                <nav class="mur-cat-nav" id="murCatNav">
                    <!-- filled by JS -->
                </nav>
            </div>
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-shield-check"></i> Règles du mur</div>
                <div class="mur-sidebar-rules" id="murRules">
                    <!-- filled by JS -->
                </div>
            </div>
        </aside>

        <!-- CENTER: Composer + Feed -->
        <div class="mur-center">

            <!-- Composer -->
            <div class="mur-composer" id="murComposer">
                <div class="mur-composer-header">
                    <div class="mur-composer-avatar" id="composerAvatar"></div>
                    <textarea id="composerBody" class="mur-composer-input" placeholder="Quoi de neuf ?" rows="1"></textarea>
                </div>
                <div class="mur-composer-footer">
                    <div class="mur-composer-options">
                        <select id="composerCategory" class="mur-select">
                            <!-- filled by JS -->
                        </select>
                        <label class="mur-composer-anon" id="composerAnonWrap" style="display:none">
                            <input type="checkbox" id="composerAnon"> Anonyme
                        </label>
                    </div>
                    <button class="mur-btn-post" id="btnPost">
                        <i class="bi bi-send-fill"></i> Publier
                    </button>
                </div>
            </div>

            <!-- Feed list -->
            <div id="murFeed">
                <div class="mur-loading"><span class="spinner"></span></div>
            </div>

            <!-- Load more -->
            <div id="murLoadMore" class="mur-load-more" style="display:none">
                <button class="mur-btn-load-more" id="btnLoadMore">Voir plus</button>
            </div>
        </div>

        <!-- RIGHT SIDEBAR: Widgets -->
        <aside class="mur-sidebar-right" id="murSidebarRight">
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-fire"></i> Posts populaires</div>
                <div class="mur-widget-list" id="murPopularPosts">
                    <div class="mur-widget-empty">Aucun post pour le moment</div>
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
