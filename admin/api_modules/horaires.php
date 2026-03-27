<?php

function admin_get_horaires()
{
    require_responsable();

    $horaires = Db::fetchAll("SELECT * FROM horaires_types ORDER BY code");
    respond(['success' => true, 'horaires' => $horaires]);
}

function admin_create_horaire()
{
    global $params;
    require_admin();

    $code = strtoupper(Sanitize::text($params['code'] ?? '', 10));
    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $debut = Sanitize::time($params['heure_debut'] ?? '');
    $fin = Sanitize::time($params['heure_fin'] ?? '');
    $pp = Sanitize::int($params['pauses_payees'] ?? 0);
    $pnp = Sanitize::int($params['pauses_non_payees'] ?? 0);
    $couleur = $params['couleur'] ?? '#2D9CDB';

    if (!$code || !$nom || !$debut || !$fin) bad_request('Champs requis manquants');

    // Calculate effective duration
    $d1 = new DateTime($debut);
    $d2 = new DateTime($fin);
    $diff = $d1->diff($d2);
    $hours = $diff->h + ($diff->i / 60);
    $effective = round($hours - ($pnp * 0.5), 2);

    $isActive = isset($params['is_active']) ? Sanitize::int($params['is_active']) : 1;

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO horaires_types (id, code, nom, heure_debut, heure_fin, pauses_payees, pauses_non_payees, duree_effective, couleur, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $code, $nom, $debut, $fin, $pp, $pnp, $effective, $couleur, $isActive]
    );

    respond(['success' => true, 'id' => $id]);
}

function admin_update_horaire()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $code = strtoupper(Sanitize::text($params['code'] ?? '', 10));
    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $debut = Sanitize::time($params['heure_debut'] ?? '');
    $fin = Sanitize::time($params['heure_fin'] ?? '');
    $pp = Sanitize::int($params['pauses_payees'] ?? 0);
    $pnp = Sanitize::int($params['pauses_non_payees'] ?? 0);
    $couleur = $params['couleur'] ?? '#2D9CDB';

    $d1 = new DateTime($debut);
    $d2 = new DateTime($fin);
    $diff = $d1->diff($d2);
    $hours = $diff->h + ($diff->i / 60);
    $effective = round($hours - ($pnp * 0.5), 2);

    $isActive = isset($params['is_active']) ? Sanitize::int($params['is_active']) : 1;

    Db::exec(
        "UPDATE horaires_types SET code=?, nom=?, heure_debut=?, heure_fin=?,
                pauses_payees=?, pauses_non_payees=?, duree_effective=?, couleur=?, is_active=?
         WHERE id = ?",
        [$code, $nom, $debut, $fin, $pp, $pnp, $effective, $couleur, $isActive, $id]
    );

    respond(['success' => true]);
}

function admin_toggle_horaire()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $isActive = Sanitize::int($params['is_active'] ?? 0);
    Db::exec("UPDATE horaires_types SET is_active = ? WHERE id = ?", [$isActive, $id]);

    respond(['success' => true]);
}

function admin_delete_horaire()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE horaires_types SET is_active = 0 WHERE id = ?", [$id]);
    respond(['success' => true]);
}
