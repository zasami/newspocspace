<?php
/**
 * Employee-side Menus & Reservations API
 * Menu du jour = midi uniquement, 7j/7, choix menu ou salade
 */

function get_menus_semaine()
{
    $user = require_auth();
    global $params;

    $dateRef = $params['date'] ?? date('Y-m-d');
    $dt = new DateTime($dateRef);
    $dow = (int) $dt->format('N');
    $monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
    $sunday = (clone $monday)->modify('+6 days');

    $menus = Db::fetchAll(
        "SELECT m.id, m.date_jour, m.entree, m.plat, m.salade, m.accompagnement, m.dessert, m.remarques,
                (SELECT COUNT(*) FROM menu_reservations r WHERE r.menu_id = m.id AND r.statut = 'confirmee') AS nb_reservations
         FROM menus m
         WHERE m.date_jour BETWEEN ? AND ?
         ORDER BY m.date_jour ASC",
        [$monday->format('Y-m-d'), $sunday->format('Y-m-d')]
    );

    // User's reservations for this week
    $menuIds = array_column($menus, 'id');
    $myReservations = [];
    if ($menuIds) {
        $ph = implode(',', array_fill(0, count($menuIds), '?'));
        $rows = Db::fetchAll(
            "SELECT id, menu_id, choix, nb_personnes, remarques, paiement, statut
             FROM menu_reservations
             WHERE user_id = ? AND menu_id IN ($ph) AND statut = 'confirmee'",
            array_merge([$user['id']], $menuIds)
        );
        foreach ($rows as $r) {
            $myReservations[$r['menu_id']] = $r;
        }
    }

    respond([
        'success' => true,
        'menus' => $menus,
        'my_reservations' => $myReservations,
        'semaine_debut' => $monday->format('Y-m-d'),
        'semaine_fin' => $sunday->format('Y-m-d'),
    ]);
}

function reserver_menu()
{
    $user = require_auth();
    global $params;

    $menuId = Sanitize::text($params['menu_id'] ?? '', 36);
    $choix = in_array($params['choix'] ?? '', ['menu', 'salade']) ? $params['choix'] : 'menu';
    $nbPersonnes = Sanitize::int($params['nb_personnes'] ?? 1);
    $remarques = Sanitize::text($params['remarques'] ?? '', 500);
    $paiement = in_array($params['paiement'] ?? '', ['salaire', 'caisse', 'carte']) ? $params['paiement'] : 'salaire';

    if (!$menuId) bad_request('Menu requis');
    if ($nbPersonnes < 1 || $nbPersonnes > 10) bad_request('Nombre de personnes invalide (1-10)');

    $menu = Db::fetch("SELECT id, date_jour FROM menus WHERE id = ?", [$menuId]);
    if (!$menu) not_found('Menu non trouvé');

    if ($menu['date_jour'] < date('Y-m-d')) {
        bad_request('Impossible de réserver pour une date passée');
    }

    $maxDate = (new DateTime())->modify('+7 days')->format('Y-m-d');
    if ($menu['date_jour'] > $maxDate) {
        bad_request('Réservation possible jusqu\'à 7 jours à l\'avance');
    }

    $existing = Db::fetch(
        "SELECT id FROM menu_reservations WHERE menu_id = ? AND user_id = ? AND statut = 'confirmee'",
        [$menuId, $user['id']]
    );

    if ($existing) {
        Db::exec(
            "UPDATE menu_reservations SET choix = ?, nb_personnes = ?, remarques = ?, paiement = ?, updated_at = NOW() WHERE id = ?",
            [$choix, $nbPersonnes, $remarques, $paiement, $existing['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO menu_reservations (id, menu_id, user_id, choix, nb_personnes, remarques, paiement) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $menuId, $user['id'], $choix, $nbPersonnes, $remarques, $paiement]
        );
    }

    respond(['success' => true, 'message' => 'Commande confirmée']);
}

function annuler_reservation_menu()
{
    $user = require_auth();
    global $params;

    $reservationId = Sanitize::text($params['reservation_id'] ?? '', 36);
    if (!$reservationId) bad_request('ID requis');

    $reservation = Db::fetch(
        "SELECT r.id, m.date_jour FROM menu_reservations r JOIN menus m ON m.id = r.menu_id WHERE r.id = ? AND r.user_id = ?",
        [$reservationId, $user['id']]
    );
    if (!$reservation) not_found('Réservation non trouvée');

    if ($reservation['date_jour'] < date('Y-m-d')) {
        bad_request('Impossible d\'annuler une réservation passée');
    }

    Db::exec(
        "UPDATE menu_reservations SET statut = 'annulee', updated_at = NOW() WHERE id = ?",
        [$reservationId]
    );

    respond(['success' => true, 'message' => 'Commande annulée']);
}
