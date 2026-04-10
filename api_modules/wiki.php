<?php
/**
 * Wiki / Base de connaissances — API employé (lecture + favoris)
 */

function get_wiki_categories()
{
    require_auth();
    $cats = Db::fetchAll("SELECT id, nom, slug, icone, couleur FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
    respond(['success' => true, 'categories' => $cats]);
}

function get_wiki_tags()
{
    require_auth();
    $tags = Db::fetchAll("SELECT id, nom, slug, couleur FROM wiki_tags ORDER BY nom");
    respond(['success' => true, 'tags' => $tags]);
}

function get_wiki_pages()
{
    $user = require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $catId = $params['categorie_id'] ?? '';
    $tagId = $params['tag_id'] ?? '';
    $favOnly = !empty($params['favoris_only']);

    $where = ['p.archived_at IS NULL', 'p.visible = 1', "p.status = 'publie'"];
    $binds = [];

    // Permission filter: only show pages accessible to user's role
    $userRole = $user['role'] ?? 'collaborateur';
    $where[] = '(NOT EXISTS (SELECT 1 FROM wiki_page_permissions wpp WHERE wpp.page_id = p.id) OR EXISTS (SELECT 1 FROM wiki_page_permissions wpp2 WHERE wpp2.page_id = p.id AND wpp2.role = ?))';
    $binds[] = $userRole;

    if ($catId) { $where[] = 'p.categorie_id = ?'; $binds[] = $catId; }
    if ($tagId) {
        $where[] = 'EXISTS (SELECT 1 FROM wiki_page_tags wpt WHERE wpt.page_id = p.id AND wpt.tag_id = ?)';
        $binds[] = $tagId;
    }
    if ($favOnly) {
        $where[] = 'EXISTS (SELECT 1 FROM wiki_favoris wf WHERE wf.page_id = p.id AND wf.user_id = ?)';
        $binds[] = $user['id'];
    }

    // Use FULLTEXT if available, fallback to LIKE
    if ($search) {
        $where[] = 'MATCH(p.titre, p.description, p.contenu) AGAINST(? IN BOOLEAN MODE)';
        $binds[] = $search . '*';
    }

    $pages = Db::fetchAll(
        "SELECT p.id, p.titre, p.slug, p.description, p.categorie_id, p.version,
                p.epingle, p.expert_id, p.verified_at, p.verify_next,
                p.created_at, p.updated_at,
                c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
                ex.prenom AS expert_prenom, ex.nom AS expert_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         LEFT JOIN users ex ON ex.id = p.expert_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY p.epingle DESC, p.updated_at DESC",
        $binds
    );

    // Attach tags + favoris status
    $pageIds = array_column($pages, 'id');
    $tagsByPage = [];
    $favSet = [];
    if ($pageIds) {
        $ph = implode(',', array_fill(0, count($pageIds), '?'));
        $allTags = Db::fetchAll(
            "SELECT wpt.page_id, t.id, t.nom, t.slug, t.couleur FROM wiki_page_tags wpt JOIN wiki_tags t ON t.id = wpt.tag_id WHERE wpt.page_id IN ($ph)",
            $pageIds
        );
        foreach ($allTags as $t) $tagsByPage[$t['page_id']][] = $t;

        $favRows = Db::fetchAll(
            "SELECT page_id FROM wiki_favoris WHERE user_id = ? AND page_id IN ($ph)",
            array_merge([$user['id']], $pageIds)
        );
        foreach ($favRows as $f) $favSet[$f['page_id']] = true;
    }
    foreach ($pages as &$p) {
        $p['tags'] = $tagsByPage[$p['id']] ?? [];
        $p['is_favori'] = isset($favSet[$p['id']]);
    }
    unset($p);

    respond(['success' => true, 'pages' => $pages]);
}

function get_wiki_page()
{
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $page = Db::fetch(
        "SELECT p.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
                ex.prenom AS expert_prenom, ex.nom AS expert_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         LEFT JOIN users ex ON ex.id = p.expert_id
         WHERE p.id = ? AND p.visible = 1 AND p.archived_at IS NULL AND p.status = 'publie'
           AND (NOT EXISTS (SELECT 1 FROM wiki_page_permissions wpp WHERE wpp.page_id = p.id)
                OR EXISTS (SELECT 1 FROM wiki_page_permissions wpp2 WHERE wpp2.page_id = p.id AND wpp2.role = ?))",
        [$id, $user['role'] ?? 'collaborateur']
    );

    if (!$page) not_found('Page introuvable');

    // Log view
    try {
        Db::exec("INSERT INTO wiki_page_views (id, page_id, user_id) VALUES (?, ?, ?)", [Uuid::v4(), $id, $user['id']]);
    } catch (\Throwable $e) {}

    $page['tags'] = Db::fetchAll(
        "SELECT t.id, t.nom, t.slug, t.couleur FROM wiki_page_tags wpt JOIN wiki_tags t ON t.id = wpt.tag_id WHERE wpt.page_id = ?",
        [$id]
    );
    $page['is_favori'] = (bool)Db::getOne("SELECT 1 FROM wiki_favoris WHERE user_id = ? AND page_id = ?", [$user['id'], $id]);

    respond(['success' => true, 'page' => $page]);
}

function log_wiki_search()
{
    $user = require_auth();
    global $params;
    $q = trim(Sanitize::text($params['q'] ?? '', 200));
    if (mb_strlen($q) < 2) respond(['success' => true]);
    Db::exec(
        "INSERT INTO wiki_search_log (id, user_id, q, results_count) VALUES (?, ?, ?, ?)",
        [Uuid::v4(), $user['id'], $q, (int)($params['results_count'] ?? 0)]
    );
    respond(['success' => true]);
}

/* ── Favoris ───────────────────────────────────────────── */

function toggle_wiki_favori()
{
    $user = require_auth();
    global $params;

    $pageId = $params['page_id'] ?? '';
    if (!$pageId) bad_request('page_id requis');

    $exists = Db::getOne("SELECT 1 FROM wiki_favoris WHERE user_id = ? AND page_id = ?", [$user['id'], $pageId]);
    if ($exists) {
        Db::exec("DELETE FROM wiki_favoris WHERE user_id = ? AND page_id = ?", [$user['id'], $pageId]);
        respond(['success' => true, 'is_favori' => false, 'message' => 'Retiré des favoris']);
    } else {
        Db::exec("INSERT INTO wiki_favoris (user_id, page_id) VALUES (?, ?)", [$user['id'], $pageId]);
        respond(['success' => true, 'is_favori' => true, 'message' => 'Ajouté aux favoris']);
    }
}

/* ── Annonces ──────────────────────────────────────────── */

function get_annonces_list()
{
    require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $categorie = $params['categorie'] ?? '';

    $where = ['a.archived_at IS NULL', 'a.visible = 1'];
    $binds = [];

    if ($categorie) { $where[] = 'a.categorie = ?'; $binds[] = $categorie; }
    if ($search) {
        $where[] = '(a.titre LIKE ? OR a.description LIKE ? OR a.contenu LIKE ?)';
        $s = "%$search%";
        $binds[] = $s; $binds[] = $s; $binds[] = $s;
    }

    $annonces = Db::fetchAll(
        "SELECT a.id, a.titre, a.slug, a.description, a.image_url, a.categorie,
                a.epingle, a.published_at, a.created_at,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.epingle DESC, a.published_at DESC",
        $binds
    );

    respond(['success' => true, 'annonces' => $annonces]);
}

function get_annonce_detail()
{
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $a = Db::fetch(
        "SELECT a.*, cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE a.id = ? AND a.visible = 1 AND a.archived_at IS NULL",
        [$id]
    );

    if (!$a) not_found('Annonce introuvable');

    // Log view + ack status
    try {
        Db::exec("INSERT INTO annonce_views (id, annonce_id, user_id) VALUES (?, ?, ?)", [Uuid::v4(), $id, $user['id']]);
    } catch (\Throwable $e) {}
    if (!empty($a['requires_ack'])) {
        $a['user_acked'] = (bool)Db::getOne("SELECT 1 FROM annonce_acks WHERE annonce_id = ? AND user_id = ?", [$id, $user['id']]);
    }

    respond(['success' => true, 'annonce' => $a]);
}

function ack_annonce()
{
    $user = require_auth();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    $a = Db::fetch("SELECT requires_ack FROM annonces WHERE id = ?", [$id]);
    if (!$a || empty($a['requires_ack'])) bad_request('Annonce sans accusé requis');
    Db::exec("INSERT IGNORE INTO annonce_acks (annonce_id, user_id) VALUES (?, ?)", [$id, $user['id']]);
    respond(['success' => true, 'message' => 'Lecture confirmée']);
}
