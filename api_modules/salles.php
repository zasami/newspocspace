<?php
/**
 * Employee API — Réservation de salles
 */

function get_salles_disponibilites()
{
    require_auth();
    global $params;

    $date_debut = Sanitize::date($params['date_debut'] ?? date('Y-m-d'));
    $date_fin   = Sanitize::date($params['date_fin'] ?? date('Y-m-d', strtotime('+6 days')));

    $salles = Db::fetchAll("SELECT id, nom, description, capacite, equipements, couleur FROM salles WHERE is_active = 1 ORDER BY ordre, nom");

    $reservations = Db::fetchAll(
        "SELECT r.id, r.salle_id, r.user_id, r.titre, r.description, r.date_jour,
                r.heure_debut, r.heure_fin, u.prenom, u.nom AS user_nom
         FROM reservations_salles r
         JOIN users u ON u.id = r.user_id
         WHERE r.date_jour BETWEEN ? AND ? AND r.statut = 'confirmee'
         ORDER BY r.date_jour, r.heure_debut",
        [$date_debut, $date_fin]
    );

    respond(['success' => true, 'salles' => $salles, 'reservations' => $reservations]);
}

function create_reservation_salle()
{
    require_auth();
    global $params;

    $salle_id        = $params['salle_id'] ?? '';
    $titre           = Sanitize::text($params['titre'] ?? '', 200);
    $description     = Sanitize::text($params['description'] ?? '', 1000);
    $date_jour       = Sanitize::date($params['date_jour'] ?? '');
    $journee_entiere = !empty($params['journee_entiere']) ? 1 : 0;
    $heure_debut     = $journee_entiere ? '00:00' : Sanitize::time($params['heure_debut'] ?? '');
    $heure_fin       = $journee_entiere ? '23:59' : Sanitize::time($params['heure_fin'] ?? '');
    $user_id         = $_SESSION['ss_user']['id'];

    if (!$salle_id || !$titre || !$date_jour) {
        bad_request('Champs requis manquants');
    }

    if (!$journee_entiere && (!$heure_debut || !$heure_fin)) {
        bad_request('Heures requises');
    }

    if (!$journee_entiere && $heure_fin <= $heure_debut) {
        bad_request('L\'heure de fin doit être après l\'heure de début');
    }

    // Pas de réservation dans le passé
    if ($date_jour < date('Y-m-d')) {
        bad_request('Impossible de réserver dans le passé');
    }

    // Vérifier conflit
    $conflit = Db::fetch(
        "SELECT id, titre FROM reservations_salles
         WHERE salle_id = ? AND date_jour = ? AND statut = 'confirmee'
           AND heure_debut < ? AND heure_fin > ?",
        [$salle_id, $date_jour, $heure_fin, $heure_debut]
    );

    if ($conflit) {
        bad_request('Cette salle est déjà réservée sur ce créneau');
    }

    // Vérifier que la salle existe et est active
    $salle = Db::fetch("SELECT id FROM salles WHERE id = ? AND is_active = 1", [$salle_id]);
    if (!$salle) bad_request('Salle introuvable');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO reservations_salles (id, salle_id, user_id, titre, description, date_jour, heure_debut, heure_fin, journee_entiere)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $salle_id, $user_id, $titre, $description, $date_jour, $heure_debut, $heure_fin, $journee_entiere]
    );

    respond(['success' => true, 'id' => $id]);
}

function annuler_reservation_salle()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $user_id = $_SESSION['ss_user']['id'];

    // Seul le créateur peut annuler sa réservation
    $resa = Db::fetch("SELECT id FROM reservations_salles WHERE id = ? AND user_id = ? AND statut = 'confirmee'", [$id, $user_id]);
    if (!$resa) bad_request('Réservation introuvable ou non autorisée');

    Db::exec("UPDATE reservations_salles SET statut = 'annulee' WHERE id = ?", [$id]);
    respond(['success' => true]);
}
