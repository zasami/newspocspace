<?php
/**
 * Admin Menus API — Manage daily menus (midi 7j/7) & view reservations
 */

function admin_get_menus()
{
    require_responsable();
    global $params;

    $dateRef = $params['date'] ?? date('Y-m-d');
    $dt = new DateTime($dateRef);
    $dow = (int) $dt->format('N');
    $monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
    $sunday = (clone $monday)->modify('+6 days');

    $menus = Db::fetchAll(
        "SELECT m.*,
                u.prenom AS creator_prenom, u.nom AS creator_nom,
                (SELECT COUNT(*) FROM menu_reservations r WHERE r.menu_id = m.id AND r.statut = 'confirmee') AS nb_reservations,
                (SELECT SUM(r2.nb_personnes) FROM menu_reservations r2 WHERE r2.menu_id = m.id AND r2.statut = 'confirmee') AS total_couverts,
                (SELECT COUNT(*) FROM menu_reservations r3 WHERE r3.menu_id = m.id AND r3.statut = 'confirmee' AND r3.choix = 'menu') AS nb_menu,
                (SELECT COUNT(*) FROM menu_reservations r4 WHERE r4.menu_id = m.id AND r4.statut = 'confirmee' AND r4.choix = 'salade') AS nb_salade
         FROM menus m
         LEFT JOIN users u ON u.id = m.created_by
         WHERE m.date_jour BETWEEN ? AND ?
         ORDER BY m.date_jour ASC",
        [$monday->format('Y-m-d'), $sunday->format('Y-m-d')]
    );

    respond([
        'success' => true,
        'menus' => $menus,
        'semaine_debut' => $monday->format('Y-m-d'),
        'semaine_fin' => $sunday->format('Y-m-d'),
    ]);
}

function admin_save_menu()
{
    $user = require_responsable();
    global $params;

    $dateJour = Sanitize::date($params['date_jour'] ?? '');
    $entree = Sanitize::text($params['entree'] ?? '', 500);
    $plat = Sanitize::text($params['plat'] ?? '', 500);
    $salade = Sanitize::text($params['salade'] ?? '', 500);
    $accompagnement = Sanitize::text($params['accompagnement'] ?? '', 500);
    $dessert = Sanitize::text($params['dessert'] ?? '', 500);
    $remarques = Sanitize::text($params['remarques'] ?? '', 2000);

    if (!$dateJour) bad_request('Date requise');
    if (!$plat) bad_request('Plat principal requis');

    $existing = Db::fetch("SELECT id FROM menus WHERE date_jour = ?", [$dateJour]);

    if ($existing) {
        Db::exec(
            "UPDATE menus SET entree = ?, plat = ?, salade = ?, accompagnement = ?, dessert = ?, remarques = ?, updated_at = NOW()
             WHERE id = ?",
            [$entree, $plat, $salade, $accompagnement, $dessert, $remarques, $existing['id']]
        );
        $menuId = $existing['id'];
    } else {
        $menuId = Uuid::v4();
        Db::exec(
            "INSERT INTO menus (id, date_jour, entree, plat, salade, accompagnement, dessert, remarques, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$menuId, $dateJour, $entree, $plat, $salade, $accompagnement, $dessert, $remarques, $user['id']]
        );
    }

    respond(['success' => true, 'menu_id' => $menuId, 'message' => 'Menu enregistré']);
}

function admin_delete_menu()
{
    require_responsable();
    global $params;

    $menuId = Sanitize::text($params['menu_id'] ?? '', 36);
    if (!$menuId) bad_request('ID requis');

    $menu = Db::fetch("SELECT id FROM menus WHERE id = ?", [$menuId]);
    if (!$menu) not_found('Menu non trouvé');

    Db::exec("DELETE FROM menu_reservations WHERE menu_id = ?", [$menuId]);
    Db::exec("DELETE FROM menus WHERE id = ?", [$menuId]);

    respond(['success' => true, 'message' => 'Menu supprimé']);
}

function admin_get_menu_reservations()
{
    require_responsable();
    global $params;

    $menuId = Sanitize::text($params['menu_id'] ?? '', 36);
    if (!$menuId) bad_request('ID requis');

    $reservations = Db::fetchAll(
        "SELECT r.id, r.choix, r.nb_personnes, r.remarques, r.paiement, r.statut, r.created_at,
                u.prenom, u.nom, u.email, f.nom AS fonction_nom
         FROM menu_reservations r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE r.menu_id = ? AND r.statut = 'confirmee'
         ORDER BY r.choix ASC, r.created_at ASC",
        [$menuId]
    );

    $total = array_sum(array_column($reservations, 'nb_personnes'));
    $nbMenu = count(array_filter($reservations, fn($r) => $r['choix'] === 'menu'));
    $nbSalade = count(array_filter($reservations, fn($r) => $r['choix'] === 'salade'));

    respond([
        'success' => true,
        'reservations' => $reservations,
        'total_couverts' => $total,
        'nb_menu' => $nbMenu,
        'nb_salade' => $nbSalade,
    ]);
}

function admin_get_reservations_jour()
{
    require_responsable();
    global $params;

    $dateJour = Sanitize::date($params['date'] ?? date('Y-m-d'));
    if (!$dateJour) bad_request('Date requise');

    $reservations = Db::fetchAll(
        "SELECT r.id, r.choix, r.nb_personnes, r.remarques, r.paiement, r.statut, r.created_at,
                m.date_jour, m.plat, m.salade,
                u.prenom, u.nom, f.nom AS fonction_nom
         FROM menu_reservations r
         JOIN menus m ON m.id = r.menu_id
         JOIN users u ON u.id = r.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE m.date_jour = ? AND r.statut = 'confirmee'
         ORDER BY r.choix ASC, r.created_at ASC",
        [$dateJour]
    );

    respond(['success' => true, 'reservations' => $reservations, 'date' => $dateJour]);
}
