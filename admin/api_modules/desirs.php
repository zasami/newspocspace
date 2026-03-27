<?php

function admin_get_desirs()
{
    require_responsable();
    global $params;
    $mois = $params['mois'] ?? '';
    $statut = $params['statut'] ?? '';

    $sql = "SELECT d.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
                   ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                   ht.heure_debut AS horaire_debut, ht.heure_fin AS horaire_fin,
                   ht.duree_effective AS horaire_duree,
                   dp.jour_semaine AS permanent_jour_semaine
            FROM desirs d
            JOIN users u ON u.id = d.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            LEFT JOIN horaires_types ht ON ht.id = d.horaire_type_id
            LEFT JOIN desirs_permanents dp ON dp.id = d.permanent_id
            WHERE 1=1";
    $p = [];

    $userId = $params['user_id'] ?? '';

    if ($userId) { $sql .= " AND d.user_id = ?"; $p[] = $userId; }
    if ($mois) { $sql .= " AND d.mois_cible = ?"; $p[] = $mois; }
    if ($statut) { $sql .= " AND d.statut = ?"; $p[] = $statut; }

    $sql .= " ORDER BY d.date_souhaitee ASC, u.nom ASC";

    respond(['success' => true, 'desirs' => Db::fetchAll($sql, $p)]);
}

function admin_validate_desir()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 500);

    if (!$id || !in_array($statut, ['valide', 'refuse'])) {
        bad_request('ID et statut requis');
    }

    Db::exec(
        "UPDATE desirs SET statut = ?, commentaire_chef = ?, valide_par = ?, valide_at = NOW() WHERE id = ?",
        [$statut, $commentaire, $_SESSION['zt_user']['id'], $id]
    );

    respond(['success' => true, 'message' => 'Désir ' . ($statut === 'valide' ? 'validé' : 'refusé')]);
}

function admin_get_user_permanents()
{
    require_responsable();
    global $params;
    $userId = $params['user_id'] ?? '';
    if (!$userId) bad_request('user_id requis');

    $permanents = Db::fetchAll(
        "SELECT dp.*, ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
                ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
         FROM desirs_permanents dp
         LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
         LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
         LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
         WHERE dp.user_id = ?
         ORDER BY dp.jour_semaine",
        [$userId]
    );

    respond(['success' => true, 'permanents' => $permanents]);
}

function admin_get_permanents_pending()
{
    require_responsable();
    $sql = "SELECT dp.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
                   ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                   dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
                   ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
            FROM desirs_permanents dp
            JOIN users u ON u.id = dp.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
            LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
            LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
            WHERE dp.statut = 'en_attente'
            ORDER BY dp.created_at ASC";

    respond(['success' => true, 'permanents' => Db::fetchAll($sql)]);
}

function admin_validate_permanent()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 500);

    if (!$id || !in_array($statut, ['valide', 'refuse'])) {
        bad_request('ID et statut requis');
    }

    $perm = Db::fetch("SELECT * FROM desirs_permanents WHERE id = ? AND statut = 'en_attente'", [$id]);
    if (!$perm) bad_request('Désir permanent non trouvé ou déjà traité');

    if ($statut === 'valide') {
        // Activate the new permanent
        Db::exec(
            "UPDATE desirs_permanents SET statut = 'valide', is_active = 1, valide_par = ?, valide_at = NOW(), commentaire_chef = ? WHERE id = ?",
            [$_SESSION['zt_user']['id'], $commentaire ?: null, $id]
        );

        // If this is a modification, deactivate the old permanent
        if ($perm['replaces_id']) {
            Db::exec(
                "UPDATE desirs_permanents SET is_active = 0 WHERE id = ?",
                [$perm['replaces_id']]
            );
        }

        $message = $perm['replaces_id'] ? 'Modification validée — ancien désir désactivé' : 'Désir permanent validé';
    } else {
        // Refuse: delete the proposal, old one stays as-is
        Db::exec(
            "UPDATE desirs_permanents SET statut = 'refuse', valide_par = ?, valide_at = NOW(), commentaire_chef = ? WHERE id = ?",
            [$_SESSION['zt_user']['id'], $commentaire ?: null, $id]
        );

        $message = $perm['replaces_id'] ? 'Modification refusée — ancien désir maintenu' : 'Désir permanent refusé';
    }

    respond(['success' => true, 'message' => $message]);
}
