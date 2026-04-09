<?php
/**
 * Wiki / Base de connaissances — API admin
 */

/* ── Helpers ───────────────────────────────────────────── */

function wiki_slugify(string $str): string
{
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[àáâãäå]/u', 'a', $str);
    $str = preg_replace('/[èéêë]/u', 'e', $str);
    $str = preg_replace('/[ìíîï]/u', 'i', $str);
    $str = preg_replace('/[òóôõö]/u', 'o', $str);
    $str = preg_replace('/[ùúûü]/u', 'u', $str);
    $str = preg_replace('/[ç]/u', 'c', $str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

/* ── Categories ────────────────────────────────────────── */

function admin_get_wiki_categories()
{
    require_auth();
    $cats = Db::fetchAll("SELECT * FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
    respond(['success' => true, 'categories' => $cats]);
}

function admin_create_wiki_category()
{
    require_responsable();
    global $params;

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    if (!$nom) bad_request('Nom requis');

    $slug = wiki_slugify($nom);
    $existing = Db::getOne("SELECT id FROM wiki_categories WHERE slug = ?", [$slug]);
    if ($existing) bad_request('Une catégorie avec ce nom existe déjà');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO wiki_categories (id, nom, slug, description, icone, couleur, ordre)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $nom, $slug,
            Sanitize::text($params['description'] ?? '', 500),
            Sanitize::text($params['icone'] ?? 'book', 50),
            Sanitize::text($params['couleur'] ?? '#6c757d', 20),
            (int)($params['ordre'] ?? 0),
        ]
    );

    respond(['success' => true, 'message' => 'Catégorie créée', 'id' => $id]);
}

function admin_update_wiki_category()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    if (!Db::fetch("SELECT id FROM wiki_categories WHERE id = ?", [$id])) not_found('Catégorie introuvable');

    $sets = [];
    $binds = [];

    if (isset($params['nom'])) {
        $nom = Sanitize::text($params['nom'], 100);
        if ($nom) { $sets[] = 'nom = ?'; $binds[] = $nom; $sets[] = 'slug = ?'; $binds[] = wiki_slugify($nom); }
    }
    if (isset($params['description'])) { $sets[] = 'description = ?'; $binds[] = Sanitize::text($params['description'], 500); }
    if (isset($params['icone'])) { $sets[] = 'icone = ?'; $binds[] = Sanitize::text($params['icone'], 50); }
    if (isset($params['couleur'])) { $sets[] = 'couleur = ?'; $binds[] = Sanitize::text($params['couleur'], 20); }
    if (isset($params['ordre'])) { $sets[] = 'ordre = ?'; $binds[] = (int)$params['ordre']; }

    if (empty($sets)) bad_request('Rien à modifier');

    $binds[] = $id;
    Db::exec("UPDATE wiki_categories SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Catégorie mise à jour']);
}

function admin_delete_wiki_category()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $count = (int)Db::getOne("SELECT COUNT(*) FROM wiki_pages WHERE categorie_id = ? AND archived_at IS NULL", [$id]);
    if ($count > 0) bad_request("Impossible : $count page(s) liée(s) à cette catégorie");

    Db::exec("DELETE FROM wiki_categories WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Catégorie supprimée']);
}

/* ── Pages ─────────────────────────────────────────────── */

function admin_get_wiki_pages()
{
    require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $catId = $params['categorie_id'] ?? '';
    $showArchived = !empty($params['show_archived']);

    $where = $showArchived ? ['1=1'] : ['p.archived_at IS NULL'];
    $binds = [];

    if ($catId) {
        $where[] = 'p.categorie_id = ?';
        $binds[] = $catId;
    }

    if ($search) {
        $where[] = '(p.titre LIKE ? OR p.description LIKE ? OR p.contenu LIKE ?)';
        $s = "%$search%";
        $binds[] = $s;
        $binds[] = $s;
        $binds[] = $s;
    }

    $whereSql = implode(' AND ', $where);

    // Tag filter
    $tagId = $params['tag_id'] ?? '';
    if ($tagId) {
        $where[] = 'EXISTS (SELECT 1 FROM wiki_page_tags wpt WHERE wpt.page_id = p.id AND wpt.tag_id = ?)';
        $binds[] = $tagId;
    }

    // Expired verification filter
    if (!empty($params['expired_only'])) {
        $where[] = 'p.verify_next IS NOT NULL AND p.verify_next <= NOW()';
    }

    $whereSql = implode(' AND ', $where);

    $pages = Db::fetchAll(
        "SELECT p.id, p.titre, p.slug, p.description, p.categorie_id, p.version,
                p.visible, p.epingle, p.expert_id, p.verified_at, p.verify_next,
                p.verify_interval_days, p.created_at, p.updated_at,
                c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
                up.prenom AS modif_prenom, up.nom AS modif_nom,
                ex.prenom AS expert_prenom, ex.nom AS expert_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         LEFT JOIN users up ON up.id = p.updated_by
         LEFT JOIN users ex ON ex.id = p.expert_id
         WHERE $whereSql
         ORDER BY p.epingle DESC, p.updated_at DESC",
        $binds
    );

    // Attach tags to each page
    $pageIds = array_column($pages, 'id');
    $tagsByPage = [];
    if ($pageIds) {
        $ph = implode(',', array_fill(0, count($pageIds), '?'));
        $allTags = Db::fetchAll(
            "SELECT wpt.page_id, t.id, t.nom, t.slug, t.couleur
             FROM wiki_page_tags wpt
             JOIN wiki_tags t ON t.id = wpt.tag_id
             WHERE wpt.page_id IN ($ph)",
            $pageIds
        );
        foreach ($allTags as $t) {
            $tagsByPage[$t['page_id']][] = $t;
        }
    }
    foreach ($pages as &$p) {
        $p['tags'] = $tagsByPage[$p['id']] ?? [];
    }
    unset($p);

    respond(['success' => true, 'pages' => $pages]);
}

function admin_get_wiki_page()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $page = Db::fetch(
        "SELECT p.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
                up.prenom AS modif_prenom, up.nom AS modif_nom,
                ex.prenom AS expert_prenom, ex.nom AS expert_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         LEFT JOIN users up ON up.id = p.updated_by
         LEFT JOIN users ex ON ex.id = p.expert_id
         WHERE p.id = ?",
        [$id]
    );

    if (!$page) not_found('Page introuvable');

    // Attach tags
    $page['tags'] = Db::fetchAll(
        "SELECT t.id, t.nom, t.slug, t.couleur FROM wiki_page_tags wpt JOIN wiki_tags t ON t.id = wpt.tag_id WHERE wpt.page_id = ?",
        [$id]
    );

    respond(['success' => true, 'page' => $page]);
}

function admin_create_wiki_page()
{
    $user = require_responsable();
    global $params;

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $slug = wiki_slugify($titre);
    // Ensure unique slug
    $base = $slug;
    $i = 1;
    while (Db::getOne("SELECT id FROM wiki_pages WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . $i++;
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO wiki_pages (id, titre, slug, contenu, description, image_url, categorie_id, version, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)",
        [
            $id, $titre, $slug,
            $params['contenu'] ?? '',
            Sanitize::text($params['description'] ?? '', 500),
            Sanitize::text($params['image_url'] ?? '', 500),
            $params['categorie_id'] ?: null,
            $user['id'],
        ]
    );

    respond(['success' => true, 'message' => 'Page créée', 'id' => $id, 'slug' => $slug]);
}

function admin_update_wiki_page()
{
    $user = require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $existing = Db::fetch("SELECT id, titre, contenu, version FROM wiki_pages WHERE id = ?", [$id]);
    if (!$existing) not_found('Page introuvable');

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    $contenu = $params['contenu'] ?? null;
    $description = isset($params['description']) ? Sanitize::text($params['description'], 500) : null;
    $catId = $params['categorie_id'] ?? null;

    // Save current version in history if content changed
    if ($contenu !== null && $contenu !== $existing['contenu']) {
        Db::exec(
            "INSERT INTO wiki_versions (id, page_id, version, titre, contenu, edited_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(), $id, $existing['version'],
                $existing['titre'], $existing['contenu'],
                $user['id'],
                Sanitize::text($params['version_note'] ?? '', 500),
            ]
        );
    }

    $sets = ['updated_by = ?', 'version = version + 1'];
    $binds = [$user['id']];

    if ($titre) {
        $sets[] = 'titre = ?';
        $binds[] = $titre;
        $newSlug = wiki_slugify($titre);
        $conflicting = Db::getOne("SELECT id FROM wiki_pages WHERE slug = ? AND id != ?", [$newSlug, $id]);
        if (!$conflicting) {
            $sets[] = 'slug = ?';
            $binds[] = $newSlug;
        }
    }
    if ($contenu !== null) { $sets[] = 'contenu = ?'; $binds[] = $contenu; }
    if ($description !== null) { $sets[] = 'description = ?'; $binds[] = $description; }
    if ($catId !== null) { $sets[] = 'categorie_id = ?'; $binds[] = $catId ?: null; }
    if (isset($params['visible'])) { $sets[] = 'visible = ?'; $binds[] = (int)$params['visible']; }
    if (isset($params['epingle'])) { $sets[] = 'epingle = ?'; $binds[] = (int)$params['epingle']; }
    if (isset($params['image_url'])) { $sets[] = 'image_url = ?'; $binds[] = Sanitize::text($params['image_url'], 500); }

    $binds[] = $id;
    Db::exec("UPDATE wiki_pages SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Page mise à jour']);
}

function admin_delete_wiki_page()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $permanent = !empty($params['permanent']);
    if (!$id) bad_request('ID requis');

    $page = Db::fetch("SELECT id FROM wiki_pages WHERE id = ?", [$id]);
    if (!$page) not_found('Page introuvable');

    if ($permanent) {
        Db::exec("DELETE FROM wiki_versions WHERE page_id = ?", [$id]);
        Db::exec("DELETE FROM wiki_pages WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Page supprimée définitivement']);
    } else {
        Db::exec(
            "UPDATE wiki_pages SET archived_at = NOW(), archived_by = ?, visible = 0 WHERE id = ?",
            [$_SESSION['ss_user']['id'], $id]
        );
        respond(['success' => true, 'message' => 'Page archivée']);
    }
}

function admin_restore_wiki_page()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE wiki_pages SET archived_at = NULL, archived_by = NULL, visible = 1 WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Page restaurée']);
}

function admin_get_wiki_versions()
{
    require_auth();
    global $params;

    $pageId = $params['page_id'] ?? '';
    if (!$pageId) bad_request('page_id requis');

    $versions = Db::fetchAll(
        "SELECT v.*, u.prenom, u.nom
         FROM wiki_versions v
         LEFT JOIN users u ON u.id = v.edited_by
         WHERE v.page_id = ?
         ORDER BY v.version DESC",
        [$pageId]
    );

    respond(['success' => true, 'versions' => $versions]);
}

function admin_restore_wiki_version()
{
    $user = require_responsable();
    global $params;

    $versionId = $params['version_id'] ?? '';
    if (!$versionId) bad_request('version_id requis');

    $ver = Db::fetch("SELECT * FROM wiki_versions WHERE id = ?", [$versionId]);
    if (!$ver) not_found('Version introuvable');

    $current = Db::fetch("SELECT id, titre, contenu, version FROM wiki_pages WHERE id = ?", [$ver['page_id']]);
    if (!$current) not_found('Page introuvable');

    // Save current as version before restoring
    Db::exec(
        "INSERT INTO wiki_versions (id, page_id, version, titre, contenu, edited_by, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [Uuid::v4(), $current['id'], $current['version'], $current['titre'], $current['contenu'], $user['id'], 'Avant restauration v' . $ver['version']]
    );

    Db::exec(
        "UPDATE wiki_pages SET titre = ?, contenu = ?, version = version + 1, updated_by = ? WHERE id = ?",
        [$ver['titre'], $ver['contenu'], $user['id'], $ver['page_id']]
    );

    respond(['success' => true, 'message' => 'Version ' . $ver['version'] . ' restaurée']);
}

function admin_toggle_wiki_page()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $current = (int)Db::getOne("SELECT visible FROM wiki_pages WHERE id = ?", [$id]);
    $new = $current ? 0 : 1;
    Db::exec("UPDATE wiki_pages SET visible = ? WHERE id = ?", [$new, $id]);

    respond(['success' => true, 'visible' => $new, 'message' => $new ? 'Page visible' : 'Page masquée']);
}

/* ── Tags ──────────────────────────────────────────────── */

function admin_get_wiki_tags()
{
    require_auth();
    $tags = Db::fetchAll("SELECT * FROM wiki_tags ORDER BY nom");
    respond(['success' => true, 'tags' => $tags]);
}

function admin_create_wiki_tag()
{
    require_responsable();
    global $params;

    $nom = Sanitize::text($params['nom'] ?? '', 80);
    if (!$nom) bad_request('Nom requis');

    $slug = wiki_slugify($nom);
    if (Db::getOne("SELECT id FROM wiki_tags WHERE slug = ?", [$slug])) bad_request('Ce tag existe déjà');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO wiki_tags (id, nom, slug, couleur) VALUES (?, ?, ?, ?)",
        [$id, $nom, $slug, Sanitize::text($params['couleur'] ?? '#6c757d', 20)]
    );
    respond(['success' => true, 'id' => $id, 'message' => 'Tag créé']);
}

function admin_delete_wiki_tag()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM wiki_page_tags WHERE tag_id = ?", [$id]);
    Db::exec("DELETE FROM wiki_tags WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Tag supprimé']);
}

function admin_set_wiki_page_tags()
{
    require_responsable();
    global $params;

    $pageId = $params['page_id'] ?? '';
    $tagIds = $params['tag_ids'] ?? [];
    if (!$pageId) bad_request('page_id requis');
    if (!is_array($tagIds)) $tagIds = [];

    Db::exec("DELETE FROM wiki_page_tags WHERE page_id = ?", [$pageId]);
    foreach ($tagIds as $tid) {
        Db::exec("INSERT IGNORE INTO wiki_page_tags (page_id, tag_id) VALUES (?, ?)", [$pageId, $tid]);
    }
    respond(['success' => true, 'message' => count($tagIds) . ' tag(s) assigné(s)']);
}

/* ── Expert / Vérification ─────────────────────────────── */

function admin_assign_wiki_expert()
{
    require_responsable();
    global $params;

    $pageId = $params['page_id'] ?? '';
    $expertId = $params['expert_id'] ?? '';
    $intervalDays = max(7, min(365, (int)($params['interval_days'] ?? 90)));
    if (!$pageId) bad_request('page_id requis');

    Db::exec(
        "UPDATE wiki_pages SET expert_id = ?, verify_interval_days = ? WHERE id = ?",
        [$expertId ?: null, $intervalDays, $pageId]
    );
    respond(['success' => true, 'message' => $expertId ? 'Expert assigné' : 'Expert retiré']);
}

function admin_verify_wiki_page()
{
    $user = require_responsable();
    global $params;

    $pageId = $params['page_id'] ?? '';
    if (!$pageId) bad_request('page_id requis');

    $page = Db::fetch("SELECT id, verify_interval_days FROM wiki_pages WHERE id = ?", [$pageId]);
    if (!$page) not_found('Page introuvable');

    $interval = (int)($page['verify_interval_days'] ?: 90);
    $nextDate = date('Y-m-d H:i:s', strtotime("+$interval days"));

    Db::exec(
        "UPDATE wiki_pages SET verified_at = NOW(), verified_by = ?, verify_next = ? WHERE id = ?",
        [$user['id'], $nextDate, $pageId]
    );

    respond(['success' => true, 'message' => 'Page vérifiée — prochaine vérification le ' . date('d/m/Y', strtotime($nextDate))]);
}

function admin_toggle_wiki_favori()
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

function admin_get_wiki_expired()
{
    require_responsable();

    $expired = Db::fetchAll(
        "SELECT p.id, p.titre, p.verified_at, p.verify_next, p.verify_interval_days,
                ex.prenom AS expert_prenom, ex.nom AS expert_nom,
                c.nom AS categorie_nom
         FROM wiki_pages p
         LEFT JOIN users ex ON ex.id = p.expert_id
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         WHERE p.archived_at IS NULL AND p.verify_next IS NOT NULL AND p.verify_next <= NOW()
         ORDER BY p.verify_next ASC"
    );

    respond(['success' => true, 'expired' => $expired, 'count' => count($expired)]);
}

/* ── Permissions par rôle ──────────────────────────────── */

function admin_get_wiki_page_permissions()
{
    require_responsable();
    global $params;
    $pageId = $params['page_id'] ?? '';
    if (!$pageId) bad_request('page_id requis');

    $roles = Db::fetchAll("SELECT role FROM wiki_page_permissions WHERE page_id = ?", [$pageId]);
    respond(['success' => true, 'roles' => array_column($roles, 'role')]);
}

function admin_set_wiki_page_permissions()
{
    require_responsable();
    global $params;

    $pageId = $params['page_id'] ?? '';
    $roles = $params['roles'] ?? [];
    if (!$pageId) bad_request('page_id requis');
    if (!is_array($roles)) $roles = [];

    $valid = ['collaborateur', 'responsable', 'direction', 'admin'];
    Db::exec("DELETE FROM wiki_page_permissions WHERE page_id = ?", [$pageId]);

    foreach ($roles as $r) {
        if (in_array($r, $valid)) {
            Db::exec("INSERT INTO wiki_page_permissions (page_id, role) VALUES (?, ?)", [$pageId, $r]);
        }
    }

    $msg = empty($roles) ? 'Visible par tous' : 'Restreint à : ' . implode(', ', $roles);
    respond(['success' => true, 'message' => $msg]);
}

/* ── Suggestions IA ────────────────────────────────────── */

function admin_get_wiki_suggestions()
{
    $user = require_auth();
    global $params;

    $contextPage = Sanitize::text($params['context_page'] ?? '', 50);
    $userRole = $user['role'] ?? 'collaborateur';

    // Get recently viewed/dismissed suggestions to exclude
    $dismissed = Db::fetchAll(
        "SELECT page_id FROM wiki_suggestions_log WHERE user_id = ? AND dismissed = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
        [$user['id']]
    );
    $excludeIds = array_column($dismissed, 'page_id');

    // Build smart suggestion based on context
    $suggestions = [];

    // 1) Pages avec vérification expirée (priorité haute pour responsables)
    if (in_array($userRole, ['admin', 'direction', 'responsable'])) {
        $expired = Db::fetchAll(
            "SELECT p.id, p.titre, p.description, c.icone AS categorie_icone, c.couleur AS categorie_couleur
             FROM wiki_pages p
             LEFT JOIN wiki_categories c ON c.id = p.categorie_id
             WHERE p.archived_at IS NULL AND p.visible = 1
               AND p.verify_next IS NOT NULL AND p.verify_next <= NOW()
             ORDER BY p.verify_next ASC LIMIT 3"
        );
        foreach ($expired as $e) {
            if (!in_array($e['id'], $excludeIds)) {
                $suggestions[] = ['page_id' => $e['id'], 'titre' => $e['titre'], 'reason' => 'À revérifier', 'type' => 'expired',
                    'icone' => $e['categorie_icone'] ?: 'book', 'couleur' => $e['categorie_couleur'] ?: '#6c757d'];
            }
        }
    }

    // 2) Pages populaires (les plus consultées/favorisées) pas encore vues
    $popular = Db::fetchAll(
        "SELECT p.id, p.titre, p.description, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                COUNT(wf.user_id) AS fav_count
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN wiki_favoris wf ON wf.page_id = p.id
         WHERE p.archived_at IS NULL AND p.visible = 1
           AND NOT EXISTS (SELECT 1 FROM wiki_favoris f2 WHERE f2.page_id = p.id AND f2.user_id = ?)
         GROUP BY p.id
         HAVING fav_count > 0
         ORDER BY fav_count DESC, p.updated_at DESC
         LIMIT 3",
        [$user['id']]
    );
    foreach ($popular as $pp) {
        if (!in_array($pp['id'], $excludeIds) && count($suggestions) < 5) {
            $suggestions[] = ['page_id' => $pp['id'], 'titre' => $pp['titre'], 'reason' => 'Populaire (' . $pp['fav_count'] . ' favoris)', 'type' => 'popular',
                'icone' => $pp['categorie_icone'] ?: 'book', 'couleur' => $pp['categorie_couleur'] ?: '#6c757d'];
        }
    }

    // 3) Pages récentes pas encore consultées
    if (count($suggestions) < 5) {
        $recent = Db::fetchAll(
            "SELECT p.id, p.titre, p.description, c.icone AS categorie_icone, c.couleur AS categorie_couleur
             FROM wiki_pages p
             LEFT JOIN wiki_categories c ON c.id = p.categorie_id
             WHERE p.archived_at IS NULL AND p.visible = 1
               AND p.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY p.created_at DESC LIMIT 5",
            []
        );
        foreach ($recent as $r) {
            if (!in_array($r['id'], $excludeIds) && count($suggestions) < 5) {
                $alreadySuggested = array_column($suggestions, 'page_id');
                if (!in_array($r['id'], $alreadySuggested)) {
                    $suggestions[] = ['page_id' => $r['id'], 'titre' => $r['titre'], 'reason' => 'Récemment ajouté', 'type' => 'recent',
                        'icone' => $r['categorie_icone'] ?: 'book', 'couleur' => $r['categorie_couleur'] ?: '#6c757d'];
                }
            }
        }
    }

    respond(['success' => true, 'suggestions' => $suggestions]);
}

function admin_dismiss_wiki_suggestion()
{
    $user = require_auth();
    global $params;

    $pageId = $params['page_id'] ?? '';
    if (!$pageId) bad_request('page_id requis');

    Db::exec(
        "INSERT INTO wiki_suggestions_log (id, user_id, page_id, context_page, dismissed) VALUES (?, ?, ?, ?, 1)",
        [Uuid::v4(), $user['id'], $pageId, Sanitize::text($params['context'] ?? '', 50)]
    );
    respond(['success' => true]);
}

function admin_get_wiki_ai_suggest()
{
    $user = require_auth();
    global $params;

    $query = Sanitize::text($params['query'] ?? '', 500);
    if (mb_strlen($query) < 5) bad_request('Question trop courte');

    // Load AI config
    $cfg = [];
    $cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key IN ('ai_provider','gemini_api_key','gemini_model','anthropic_api_key','anthropic_model')");
    foreach ($cfgRows as $r) $cfg[$r['config_key']] = $r['config_value'];

    $aiProvider = $cfg['ai_provider'] ?? 'gemini';
    $aiApiKey = ($aiProvider === 'gemini') ? ($cfg['gemini_api_key'] ?? '') : ($cfg['anthropic_api_key'] ?? '');
    $aiModel = ($aiProvider === 'gemini') ? ($cfg['gemini_model'] ?? 'gemini-2.5-flash') : ($cfg['anthropic_model'] ?? 'claude-haiku-4-5-20251001');

    if (empty($aiApiKey)) bad_request('IA non configurée');

    // Get all wiki page titles + descriptions for context
    $pages = Db::fetchAll(
        "SELECT id, titre, description FROM wiki_pages WHERE archived_at IS NULL AND visible = 1 ORDER BY titre"
    );
    $catalog = implode("\n", array_map(function($p) {
        return "- [{$p['id']}] {$p['titre']}" . ($p['description'] ? " — {$p['description']}" : '');
    }, $pages));

    $prompt = "Tu es un assistant dans un EMS (établissement médico-social). " .
        "Un collaborateur pose la question suivante : \"$query\"\n\n" .
        "Voici le catalogue des fiches wiki disponibles :\n$catalog\n\n" .
        "Identifie les 1 à 3 fiches les plus pertinentes pour répondre à cette question. " .
        "Retourne UNIQUEMENT un JSON valide, un tableau d'objets avec les champs \"id\" (l'ID entre crochets) et \"raison\" (pourquoi cette fiche est pertinente, en 1 phrase courte).\n" .
        "Si aucune fiche n'est pertinente, retourne un tableau vide [].";

    $result = null;

    if ($aiProvider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1024, 'responseMimeType' => 'application/json'],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $resp = json_decode($raw, true);
            $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $result = json_decode($text, true);
        }
    } else {
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = json_encode([
            'model' => $aiModel, 'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $aiApiKey, 'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $resp = json_decode($raw, true);
            $text = $resp['content'][0]['text'] ?? '';
            $clean = preg_replace('/```json\s*|\s*```/', '', $text);
            $result = json_decode($clean, true);
        }
    }

    if (!is_array($result)) $result = [];

    // Enrich with page data
    $suggestions = [];
    foreach ($result as $item) {
        $pid = $item['id'] ?? '';
        $page = Db::fetch("SELECT id, titre, description FROM wiki_pages WHERE id = ? AND archived_at IS NULL AND visible = 1", [$pid]);
        if ($page) {
            $suggestions[] = [
                'page_id' => $page['id'],
                'titre' => $page['titre'],
                'description' => $page['description'],
                'reason' => $item['raison'] ?? '',
            ];
        }
    }

    respond(['success' => true, 'suggestions' => $suggestions, 'query' => $query]);
}

/* ── Image couverture wiki ─────────────────────────────── */

function admin_upload_wiki_image()
{
    require_responsable();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) bad_request('Image manquante');

    $file = $_FILES['file'];
    if ($file['size'] > 5 * 1024 * 1024) bad_request('Max 5 Mo');
    if (!in_array($file['type'], ['image/jpeg','image/png','image/webp','image/gif'], true)) bad_request('Format non autorisé');

    $storageDir = __DIR__ . '/../../assets/uploads/wiki/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) bad_request('Erreur sauvegarde');

    respond(['success' => true, 'url' => '/spocspace/assets/uploads/wiki/' . $filename]);
}

function admin_save_pixabay_wiki()
{
    require_responsable();
    global $params;

    $imageUrl = $params['image_url'] ?? '';
    if (!$imageUrl) bad_request('URL manquante');

    $parsed = parse_url($imageUrl);
    if (!$parsed || !preg_match('/pixabay\.(com|net)$/', $parsed['host'] ?? '')) bad_request('Source non autorisée');
    if (($parsed['scheme'] ?? '') !== 'https') bad_request('HTTPS requis');

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true]);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$imgData) bad_request('Téléchargement échoué');

    $storageDir = __DIR__ . '/../../assets/uploads/wiki/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $tmpFile = tempnam(sys_get_temp_dir(), 'pxb_');
    file_put_contents($tmpFile, $imgData);

    $mime = mime_content_type($tmpFile);
    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmpFile),
        'image/png'  => imagecreatefrompng($tmpFile),
        'image/webp' => imagecreatefromwebp($tmpFile),
        default => null,
    };
    unlink($tmpFile);
    if (!$img) bad_request('Format non supporté');

    $filename = 'wiki_' . bin2hex(random_bytes(8)) . '.webp';
    imagewebp($img, $storageDir . $filename, 82);
    imagedestroy($img);

    respond(['success' => true, 'url' => '/spocspace/assets/uploads/wiki/' . $filename]);
}
