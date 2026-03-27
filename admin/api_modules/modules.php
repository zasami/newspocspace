<?php

function admin_get_modules()
{
    require_responsable();

    $modules = Db::fetchAll("SELECT * FROM modules ORDER BY ordre");

    // Get etages for each module
    foreach ($modules as &$m) {
        $m['etages'] = Db::fetchAll("SELECT * FROM etages WHERE module_id = ? ORDER BY ordre", [$m['id']]);
        foreach ($m['etages'] as &$e) {
            $e['groupes'] = Db::fetchAll("SELECT * FROM groupes WHERE etage_id = ? ORDER BY ordre", [$e['id']]);
        }
    }

    respond(['success' => true, 'modules' => $modules]);
}

function admin_create_module()
{
    global $params;
    require_admin();

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $code = Sanitize::text($params['code'] ?? '', 20);
    $description = Sanitize::text($params['description'] ?? '', 500);
    $responsableId = $params['responsable_id'] ?? '';
    $etageIds = $params['etage_ids'] ?? [];
    if (!$nom || !$code) bad_request('Nom et code requis');

    $id = Uuid::v4();
    $ordre = (int)Db::getOne("SELECT COALESCE(MAX(ordre), 0) + 1 FROM modules");
    Db::exec(
        "INSERT INTO modules (id, nom, code, description, ordre) VALUES (?, ?, ?, ?, ?)",
        [$id, $nom, strtoupper($code), $description ?: null, $ordre]
    );

    // Assign etages to this module
    if (is_array($etageIds) && !empty($etageIds)) {
        foreach ($etageIds as $etageId) {
            Db::exec("UPDATE etages SET module_id = ? WHERE id = ?", [$id, $etageId]);
        }
    }

    // Assign responsable via ems_config
    if ($responsableId) {
        $existing = Db::getOne(
            "SELECT id FROM ems_config WHERE config_key = ?",
            ['module_' . $id . '_responsable']
        );
        if ($existing) {
            Db::exec(
                "UPDATE ems_config SET config_value = ? WHERE config_key = ?",
                [$responsableId, 'module_' . $id . '_responsable']
            );
        } else {
            Db::exec(
                "INSERT INTO ems_config (id, config_key, config_value) VALUES (?, ?, ?)",
                [Uuid::v4(), 'module_' . $id . '_responsable', $responsableId]
            );
        }
    }

    respond(['success' => true, 'id' => $id]);
}

function admin_update_module()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $code = Sanitize::text($params['code'] ?? '', 20);
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE modules SET nom = ?, code = ? WHERE id = ?", [$nom, strtoupper($code), $id]);
    respond(['success' => true]);
}

function admin_delete_module()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM modules WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_get_etages()
{
    require_responsable();
    $etages = Db::fetchAll(
        "SELECT e.*, m.nom AS module_nom, m.code AS module_code
         FROM etages e JOIN modules m ON m.id = e.module_id
         ORDER BY m.ordre, e.ordre"
    );
    respond(['success' => true, 'etages' => $etages]);
}

function admin_get_groupes()
{
    require_responsable();
    $groupes = Db::fetchAll(
        "SELECT g.*, e.nom AS etage_nom, m.nom AS module_nom
         FROM groupes g
         JOIN etages e ON e.id = g.etage_id
         JOIN modules m ON m.id = e.module_id
         ORDER BY m.ordre, e.ordre, g.ordre"
    );
    respond(['success' => true, 'groupes' => $groupes]);
}

function admin_create_groupe()
{
    global $params;
    require_admin();

    $etageId = $params['etage_id'] ?? '';
    $nom = Sanitize::text($params['nom'] ?? '', 20);
    $code = Sanitize::text($params['code'] ?? '', 10);

    if (!$etageId) bad_request('etage_id requis');
    if (!$nom || !$code) bad_request('Nom et code requis');

    $etage = Db::fetch("SELECT id FROM etages WHERE id = ?", [$etageId]);
    if (!$etage) bad_request('Étage introuvable');

    $id = Uuid::v4();
    $ordre = (int) Db::getOne("SELECT COALESCE(MAX(ordre), 0) + 1 FROM groupes WHERE etage_id = ?", [$etageId]);
    Db::exec(
        "INSERT INTO groupes (id, etage_id, nom, code, ordre) VALUES (?, ?, ?, ?, ?)",
        [$id, $etageId, $nom, strtoupper($code), $ordre]
    );

    respond(['success' => true, 'id' => $id, 'message' => 'Groupe créé']);
}

function admin_update_groupe()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    $nom = Sanitize::text($params['nom'] ?? '', 20);
    $code = Sanitize::text($params['code'] ?? '', 10);

    if (!$id) bad_request('ID requis');
    if (!$nom || !$code) bad_request('Nom et code requis');

    Db::exec("UPDATE groupes SET nom = ?, code = ? WHERE id = ?", [$nom, strtoupper($code), $id]);
    respond(['success' => true, 'message' => 'Groupe mis à jour']);
}

function admin_delete_groupe()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE planning_assignations SET groupe_id = NULL WHERE groupe_id = ?", [$id]);
    Db::exec("DELETE FROM groupes WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Groupe supprimé']);
}
