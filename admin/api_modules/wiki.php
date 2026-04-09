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
        "INSERT INTO wiki_pages (id, titre, slug, contenu, description, categorie_id, version, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)",
        [
            $id, $titre, $slug,
            $params['contenu'] ?? '',
            Sanitize::text($params['description'] ?? '', 500),
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
