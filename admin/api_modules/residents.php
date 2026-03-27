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
    $ddn = Sanitize::date($params['date_naissance'] ?? '');
    $chambre = Sanitize::text($params['chambre'] ?? '', 20);
    $etage = Sanitize::text($params['etage'] ?? '', 20);
    $corrNom = Sanitize::text($params['correspondant_nom'] ?? '', 100);
    $corrPrenom = Sanitize::text($params['correspondant_prenom'] ?? '', 100);
    $corrEmail = Sanitize::email($params['correspondant_email'] ?? '');
    $corrTel = Sanitize::phone($params['correspondant_telephone'] ?? '');
    $isVip = (int) ($params['is_vip'] ?? 0);
    $menuSpecial = Sanitize::text($params['menu_special'] ?? '', 2000);

    if (!$nom || !$prenom) bad_request('Nom et prénom requis');

    // Auto-generate code_acces = nom_lowercase + chambre
    $code = strtolower(preg_replace('/[^a-zA-Z]/', '', $nom)) . ($chambre ?: '');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO residents (id, nom, prenom, date_naissance, chambre, etage, correspondant_nom, correspondant_prenom, correspondant_email, correspondant_telephone, code_acces, is_vip, menu_special)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $nom, $prenom, $ddn ?: null, $chambre ?: null, $etage ?: null, $corrNom ?: null, $corrPrenom ?: null, $corrEmail ?: null, $corrTel ?: null, $code, $isVip, $menuSpecial ?: null]
    );

    respond(['success' => true, 'id' => $id, 'code_acces' => $code, 'message' => 'Résident créé']);
}

function admin_update_resident()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $prenom = Sanitize::text($params['prenom'] ?? '', 100);
    $ddn = Sanitize::date($params['date_naissance'] ?? '');
    $chambre = Sanitize::text($params['chambre'] ?? '', 20);
    $etage = Sanitize::text($params['etage'] ?? '', 20);
    $corrNom = Sanitize::text($params['correspondant_nom'] ?? '', 100);
    $corrPrenom = Sanitize::text($params['correspondant_prenom'] ?? '', 100);
    $corrEmail = Sanitize::email($params['correspondant_email'] ?? '');
    $corrTel = Sanitize::phone($params['correspondant_telephone'] ?? '');
    $isVip = (int) ($params['is_vip'] ?? 0);
    $menuSpecial = Sanitize::text($params['menu_special'] ?? '', 2000);

    if (!$nom || !$prenom) bad_request('Nom et prénom requis');

    $code = strtolower(preg_replace('/[^a-zA-Z]/', '', $nom)) . ($chambre ?: '');

    Db::exec(
        "UPDATE residents SET nom = ?, prenom = ?, date_naissance = ?, chambre = ?, etage = ?,
         correspondant_nom = ?, correspondant_prenom = ?, correspondant_email = ?, correspondant_telephone = ?,
         code_acces = ?, is_vip = ?, menu_special = ?, updated_at = NOW()
         WHERE id = ?",
        [$nom, $prenom, $ddn ?: null, $chambre ?: null, $etage ?: null,
         $corrNom ?: null, $corrPrenom ?: null, $corrEmail ?: null, $corrTel ?: null,
         $code, $isVip, $menuSpecial ?: null, $id]
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
