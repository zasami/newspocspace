<div id="mur-page">

    <!-- Stats band -->
    <div class="mur-band" id="murBand">
        <div class="mur-band-stat">
            <strong id="murStatPosts">-</strong>
            <span>Publications</span>
        </div>
        <div class="mur-band-stat">
            <strong id="murStatToday">-</strong>
            <span>Aujourd'hui</span>
        </div>
        <div class="mur-band-stat">
            <strong id="murStatContributors">-</strong>
            <span>Contributeurs</span>
        </div>
    </div>

    <div class="mur-layout">
        <!-- Main feed -->
        <div class="mur-center">

            <!-- Composer -->
            <div class="mur-composer" id="murComposer">
                <div class="mur-composer-header">
                    <div class="mur-composer-avatar" id="composerAvatar"></div>
                    <textarea id="composerBody" class="mur-composer-input" placeholder="Quoi de neuf ?" rows="2"></textarea>
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
                        <i class="bi bi-send"></i> Publier
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

        <!-- Sidebar -->
        <aside class="mur-sidebar" id="murSidebar">
            <div class="mur-sidebar-card">
                <div class="mur-sidebar-title"><i class="bi bi-info-circle"></i> Règles du mur</div>
                <div class="mur-sidebar-rules" id="murRules">
                    <!-- filled by JS -->
                </div>
            </div>
        </aside>
    </div>
</div>
