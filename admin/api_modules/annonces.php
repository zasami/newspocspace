<?php
/**
 * Annonces officielles — API admin
 * Communication descendante direction → personnel
 */

function _annonce_slugify(string $str): string
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

function admin_get_annonces()
{
    require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $categorie = $params['categorie'] ?? '';
    $showArchived = !empty($params['show_archived']);

    $where = $showArchived ? ['1=1'] : ['a.archived_at IS NULL'];
    $binds = [];

    if ($categorie) {
        $where[] = 'a.categorie = ?';
        $binds[] = $categorie;
    }
    if ($search) {
        $where[] = '(a.titre LIKE ? OR a.description LIKE ? OR a.contenu LIKE ?)';
        $s = "%$search%";
        $binds[] = $s;
        $binds[] = $s;
        $binds[] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $annonces = Db::fetchAll(
        "SELECT a.id, a.titre, a.slug, a.description, a.image_url, a.categorie,
                a.epingle, a.visible, a.published_at, a.created_at, a.updated_at,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE $whereSql
         ORDER BY a.epingle DESC, a.published_at DESC, a.created_at DESC",
        $binds
    );

    respond(['success' => true, 'annonces' => $annonces]);
}

function admin_get_annonce()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $a = Db::fetch(
        "SELECT a.*, cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE a.id = ?",
        [$id]
    );

    if (!$a) not_found('Annonce introuvable');
    respond(['success' => true, 'annonce' => $a]);
}

function admin_create_annonce()
{
    $user = require_responsable();
    global $params;

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $slug = _annonce_slugify($titre);
    $base = $slug;
    $i = 1;
    while (Db::getOne("SELECT id FROM annonces WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . $i++;
    }

    $categorie = $params['categorie'] ?? 'direction';
    $validCats = ['direction', 'rh', 'vie_sociale', 'cuisine', 'protocoles', 'securite', 'divers'];
    if (!in_array($categorie, $validCats)) $categorie = 'direction';

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO annonces (id, titre, slug, contenu, description, image_url, categorie, published_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
        [
            $id, $titre, $slug,
            $params['contenu'] ?? '',
            Sanitize::text($params['description'] ?? '', 500),
            Sanitize::text($params['image_url'] ?? '', 500),
            $categorie,
            $user['id'],
        ]
    );

    respond(['success' => true, 'message' => 'Annonce publiée', 'id' => $id]);
}

function admin_update_annonce()
{
    $user = require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    if (!Db::fetch("SELECT id FROM annonces WHERE id = ?", [$id])) not_found('Annonce introuvable');

    $sets = ['updated_by = ?'];
    $binds = [$user['id']];

    if (isset($params['titre'])) {
        $titre = Sanitize::text($params['titre'], 255);
        if ($titre) { $sets[] = 'titre = ?'; $binds[] = $titre; }
    }
    if (isset($params['contenu'])) { $sets[] = 'contenu = ?'; $binds[] = $params['contenu']; }
    if (isset($params['description'])) { $sets[] = 'description = ?'; $binds[] = Sanitize::text($params['description'], 500); }
    if (isset($params['image_url'])) { $sets[] = 'image_url = ?'; $binds[] = Sanitize::text($params['image_url'], 500); }
    if (isset($params['categorie'])) { $sets[] = 'categorie = ?'; $binds[] = $params['categorie']; }
    if (isset($params['visible'])) { $sets[] = 'visible = ?'; $binds[] = (int)$params['visible']; }
    if (isset($params['epingle'])) { $sets[] = 'epingle = ?'; $binds[] = (int)$params['epingle']; }

    $binds[] = $id;
    Db::exec("UPDATE annonces SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Annonce mise à jour']);
}

function admin_delete_annonce()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $permanent = !empty($params['permanent']);
    if (!$id) bad_request('ID requis');

    if ($permanent) {
        Db::exec("DELETE FROM annonces WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Annonce supprimée définitivement']);
    } else {
        Db::exec(
            "UPDATE annonces SET archived_at = NOW(), visible = 0 WHERE id = ?",
            [$id]
        );
        respond(['success' => true, 'message' => 'Annonce archivée']);
    }
}

function admin_upload_annonce_image()
{
    require_responsable();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Image manquante');
    }

    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) bad_request('Image trop volumineuse (max 5 Mo)');

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed, true)) bad_request('Type de fichier non autorisé');

    $storageDir = __DIR__ . '/../../storage/annonces/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde');
    }

    $url = '/spocspace/storage/annonces/' . $filename;
    respond(['success' => true, 'url' => $url]);
}
