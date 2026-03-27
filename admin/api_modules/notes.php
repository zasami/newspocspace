<?php

function admin_get_notes()
{
    global $params;
    require_responsable();

    $where = ['1=1'];
    $bind  = [];

    $cat = $params['categorie'] ?? '';
    if ($cat) { $where[] = 'n.categorie = ?'; $bind[] = $cat; }

    $search = trim($params['search'] ?? '');
    if ($search) { $where[] = '(n.titre LIKE ? OR n.contenu LIKE ?)'; $bind[] = "%$search%"; $bind[] = "%$search%"; }

    $pinned = $params['pinned'] ?? '';
    if ($pinned !== '') { $where[] = 'n.is_pinned = ?'; $bind[] = (int)$pinned; }

    $sql = "SELECT n.*, u.prenom AS creator_prenom, u.nom AS creator_nom
            FROM admin_notes n
            LEFT JOIN users u ON u.id = n.created_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.is_pinned DESC, n.updated_at DESC";

    $notes = Db::fetchAll($sql, $bind);

    respond(['success' => true, 'notes' => $notes]);
}

function admin_create_note()
{
    global $params;
    require_responsable();

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $categories = ['idee','probleme','decision','rappel','observation','autre'];
    $categorie = in_array($params['categorie'] ?? '', $categories) ? $params['categorie'] : 'autre';

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO admin_notes (id, titre, contenu, categorie, couleur, created_by)
         VALUES (?, ?, ?, ?, ?, ?)",
        [
            $id,
            $titre,
            $params['contenu'] ?? '',
            $categorie,
            $params['couleur'] ?? '#F7F5F2',
            $_SESSION['admin']['id'],
        ]
    );

    respond(['success' => true, 'id' => $id]);
}

function admin_update_note()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fields = [];
    $bind = [];

    if (isset($params['titre'])) {
        $fields[] = 'titre = ?';
        $bind[] = Sanitize::text($params['titre'], 255);
    }
    if (isset($params['contenu'])) {
        $fields[] = 'contenu = ?';
        $bind[] = $params['contenu'];
    }
    if (isset($params['categorie'])) {
        $categories = ['idee','probleme','decision','rappel','observation','autre'];
        if (in_array($params['categorie'], $categories)) {
            $fields[] = 'categorie = ?';
            $bind[] = $params['categorie'];
        }
    }
    if (isset($params['couleur'])) {
        $fields[] = 'couleur = ?';
        $bind[] = $params['couleur'];
    }
    if (isset($params['is_pinned'])) {
        $fields[] = 'is_pinned = ?';
        $bind[] = (int)$params['is_pinned'];
    }

    if (empty($fields)) bad_request('Rien à mettre à jour');

    $bind[] = $id;
    Db::exec("UPDATE admin_notes SET " . implode(', ', $fields) . " WHERE id = ?", $bind);

    respond(['success' => true]);
}

function admin_delete_note()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM admin_notes WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_toggle_pin_note()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE admin_notes SET is_pinned = NOT is_pinned WHERE id = ?", [$id]);
    respond(['success' => true]);
}
