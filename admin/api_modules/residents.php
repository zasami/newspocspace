<?php
/**
 * Admin Residents API — CRUD for EMS residents
 */

function admin_get_residents()
{
    require_responsable();
    global $params;

    $search = $params['search'] ?? '';
    $showInactive = (int) ($params['show_inactive'] ?? 0);

    $sql = "SELECT * FROM residents WHERE 1=1";
    $p = [];

    if (!$showInactive) {
        $sql .= " AND is_active = 1";
    }

    if ($search) {
        $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR chambre LIKE ?)";
        $like = "%$search%";
        $p = array_merge($p, [$like, $like, $like]);
    }

    $sql .= " ORDER BY nom, prenom";

    respond(['success' => true, 'residents' => Db::fetchAll($sql, $p)]);
}

function admin_create_resident()
{
    require_admin();
    global $params;

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $prenom = Sanitize::text($params['prenom'] ?? '', 100);
    $chambre = Sanitize::text($params['chambre'] ?? '', 20);
    $etage = Sanitize::text($params['etage'] ?? '', 20);
    $isVip = (int) ($params['is_vip'] ?? 0);
    $menuSpecial = Sanitize::text($params['menu_special'] ?? '', 2000);

    if (!$nom || !$prenom) bad_request('Nom et prénom requis');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO residents (id, nom, prenom, chambre, etage, is_vip, menu_special)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $nom, $prenom, $chambre ?: null, $etage ?: null, $isVip, $menuSpecial ?: null]
    );

    respond(['success' => true, 'id' => $id, 'message' => 'Résident créé']);
}

function admin_update_resident()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $prenom = Sanitize::text($params['prenom'] ?? '', 100);
    $chambre = Sanitize::text($params['chambre'] ?? '', 20);
    $etage = Sanitize::text($params['etage'] ?? '', 20);
    $isVip = (int) ($params['is_vip'] ?? 0);
    $menuSpecial = Sanitize::text($params['menu_special'] ?? '', 2000);

    if (!$nom || !$prenom) bad_request('Nom et prénom requis');

    Db::exec(
        "UPDATE residents SET nom = ?, prenom = ?, chambre = ?, etage = ?, is_vip = ?, menu_special = ?, updated_at = NOW()
         WHERE id = ?",
        [$nom, $prenom, $chambre ?: null, $etage ?: null, $isVip, $menuSpecial ?: null, $id]
    );

    respond(['success' => true, 'message' => 'Résident mis à jour']);
}

function admin_toggle_resident()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE residents SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Statut modifié']);
}
