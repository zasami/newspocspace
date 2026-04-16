<?php
/**
 * Admin API — Gestion des salles & réservations
 */

// ─── Salles (CRUD) ──────────────────────────────────────────

function admin_get_salles()
{
    require_responsable();
    $salles = Db::fetchAll("SELECT * FROM salles ORDER BY ordre, nom");
    respond(['success' => true, 'salles' => $salles]);
}

function admin_create_salle()
{
    global $params;
    require_admin();

    $nom         = Sanitize::text($params['nom'] ?? '', 100);
    $description = Sanitize::text($params['description'] ?? '', 500);
    $capacite    = Sanitize::int($params['capacite'] ?? 0);
    $equipements = Sanitize::text($params['equipements'] ?? '', 500);
    $couleur     = Sanitize::text($params['couleur'] ?? '#2D9CDB', 7);

    if (!$nom) bad_request('Nom requis');

    $id = Uuid::v4();
    $ordre = (int) Db::getOne("SELECT IFNULL(MAX(ordre),0)+1 FROM salles");

    Db::exec(
        "INSERT INTO salles (id, nom, description, capacite, equipements, couleur, ordre)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $nom, $description, $capacite, $equipements, $couleur, $ordre]
    );

    respond(['success' => true, 'id' => $id]);
}

function admin_update_salle()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $nom         = Sanitize::text($params['nom'] ?? '', 100);
    $description = Sanitize::text($params['description'] ?? '', 500);
    $capacite    = Sanitize::int($params['capacite'] ?? 0);
    $equipements = Sanitize::text($params['equipements'] ?? '', 500);
    $couleur     = Sanitize::text($params['couleur'] ?? '#2D9CDB', 7);

    if (!$nom) bad_request('Nom requis');

    Db::exec(
        "UPDATE salles SET nom=?, description=?, capacite=?, equipements=?, couleur=? WHERE id=?",
        [$nom, $description, $capacite, $equipements, $couleur, $id]
    );

    respond(['success' => true]);
}

function admin_toggle_salle()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE salles SET is_active = NOT is_active WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_delete_salle()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    // Soft delete (désactiver)
    Db::exec("UPDATE salles SET is_active = 0 WHERE id = ?", [$id]);
    respond(['success' => true]);
}

// ─── Réservations ────────────────────────────────────────────

function admin_get_reservations_salles()
{
    global $params;
    require_responsable();

    $date_debut = Sanitize::date($params['date_debut'] ?? date('Y-m-d'));
    $date_fin   = Sanitize::date($params['date_fin'] ?? date('Y-m-d', strtotime('+6 days')));
    $salle_id   = $params['salle_id'] ?? '';

    $sql = "SELECT r.*, s.nom AS salle_nom, s.couleur AS salle_couleur,
                   u.prenom, u.nom AS user_nom, f.nom AS fonction_nom
            FROM reservations_salles r
            JOIN salles s ON s.id = r.salle_id
            JOIN users u ON u.id = r.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            WHERE r.date_jour BETWEEN ? AND ? AND r.statut = 'confirmee'";
    $bind = [$date_debut, $date_fin];

    if ($salle_id) {
        $sql .= " AND r.salle_id = ?";
        $bind[] = $salle_id;
    }

    $sql .= " ORDER BY r.date_jour, r.heure_debut";

    $reservations = Db::fetchAll($sql, $bind);
    $salles = Db::fetchAll("SELECT * FROM salles WHERE is_active = 1 ORDER BY ordre, nom");

    respond(['success' => true, 'reservations' => $reservations, 'salles' => $salles]);
}

function admin_create_reservation_salle()
{
    global $params;
    require_responsable();

    $salle_id    = $params['salle_id'] ?? '';
    $titre       = Sanitize::text($params['titre'] ?? '', 200);
    $description = Sanitize::text($params['description'] ?? '', 1000);
    $date_jour   = Sanitize::date($params['date_jour'] ?? '');
    $heure_debut = Sanitize::time($params['heure_debut'] ?? '');
    $heure_fin   = Sanitize::time($params['heure_fin'] ?? '');
    $user_id     = $params['user_id'] ?? $_SESSION['ss_user']['id'];

    if (!$salle_id || !$titre || !$date_jour || !$heure_debut || !$heure_fin) {
        bad_request('Champs requis manquants');
    }

    if ($heure_fin <= $heure_debut) {
        bad_request('L\'heure de fin doit être après l\'heure de début');
    }

    // Vérifier conflit
    $conflit = Db::fetch(
        "SELECT id, titre FROM reservations_salles
         WHERE salle_id = ? AND date_jour = ? AND statut = 'confirmee'
           AND heure_debut < ? AND heure_fin > ?",
        [$salle_id, $date_jour, $heure_fin, $heure_debut]
    );

    if ($conflit) {
        bad_request('Conflit : cette salle est déjà réservée sur ce créneau (« ' . $conflit['titre'] . ' »)');
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO reservations_salles (id, salle_id, user_id, titre, description, date_jour, heure_debut, heure_fin)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $salle_id, $user_id, $titre, $description, $date_jour, $heure_debut, $heure_fin]
    );

    respond(['success' => true, 'id' => $id]);
}

function admin_update_reservation_salle()
{
    global $params;
    require_responsable();

    $id          = $params['id'] ?? '';
    $titre       = Sanitize::text($params['titre'] ?? '', 200);
    $description = Sanitize::text($params['description'] ?? '', 1000);
    $date_jour   = Sanitize::date($params['date_jour'] ?? '');
    $heure_debut = Sanitize::time($params['heure_debut'] ?? '');
    $heure_fin   = Sanitize::time($params['heure_fin'] ?? '');
    $salle_id    = $params['salle_id'] ?? '';

    if (!$id || !$titre || !$date_jour || !$heure_debut || !$heure_fin || !$salle_id) {
        bad_request('Champs requis manquants');
    }

    if ($heure_fin <= $heure_debut) {
        bad_request('L\'heure de fin doit être après l\'heure de début');
    }

    // Vérifier conflit (exclure la réservation courante)
    $conflit = Db::fetch(
        "SELECT id, titre FROM reservations_salles
         WHERE salle_id = ? AND date_jour = ? AND statut = 'confirmee'
           AND heure_debut < ? AND heure_fin > ? AND id != ?",
        [$salle_id, $date_jour, $heure_fin, $heure_debut, $id]
    );

    if ($conflit) {
        bad_request('Conflit : cette salle est déjà réservée sur ce créneau (« ' . $conflit['titre'] . ' »)');
    }

    Db::exec(
        "UPDATE reservations_salles SET salle_id=?, titre=?, description=?, date_jour=?, heure_debut=?, heure_fin=?
         WHERE id=?",
        [$salle_id, $titre, $description, $date_jour, $heure_debut, $heure_fin, $id]
    );

    respond(['success' => true]);
}

function admin_delete_reservation_salle()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE reservations_salles SET statut = 'annulee' WHERE id = ?", [$id]);
    respond(['success' => true]);
}
