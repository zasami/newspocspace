<?php
/**
 * Wiki / Base de connaissances — API employé (lecture seule)
 */

function get_wiki_categories()
{
    require_auth();
    $cats = Db::fetchAll("SELECT id, nom, slug, icone, couleur FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
    respond(['success' => true, 'categories' => $cats]);
}

function get_wiki_pages()
{
    require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $catId = $params['categorie_id'] ?? '';

    $where = ['p.archived_at IS NULL', 'p.visible = 1'];
    $binds = [];

    if ($catId) { $where[] = 'p.categorie_id = ?'; $binds[] = $catId; }
    if ($search) {
        $where[] = '(p.titre LIKE ? OR p.description LIKE ? OR p.contenu LIKE ?)';
        $s = "%$search%";
        $binds[] = $s; $binds[] = $s; $binds[] = $s;
    }

    $pages = Db::fetchAll(
        "SELECT p.id, p.titre, p.slug, p.description, p.categorie_id, p.version,
                p.epingle, p.created_at, p.updated_at,
                c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY p.epingle DESC, p.updated_at DESC",
        $binds
    );

    respond(['success' => true, 'pages' => $pages]);
}

function get_wiki_page()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $page = Db::fetch(
        "SELECT p.*, c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         LEFT JOIN users cr ON cr.id = p.created_by
         WHERE p.id = ? AND p.visible = 1 AND p.archived_at IS NULL",
        [$id]
    );

    if (!$page) not_found('Page introuvable');
    respond(['success' => true, 'page' => $page]);
}

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
    require_auth();
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
    respond(['success' => true, 'annonce' => $a]);
}
