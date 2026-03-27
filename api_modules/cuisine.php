<?php
/**
 * Cuisine API — Menu management, reservations collab/famille, table VIP
 */

function cuisine_get_menus_semaine()
{
    $user = require_permission('page_cuisine');
    global $params;

    $dateRef = $params['date'] ?? date('Y-m-d');
    $dt = new DateTime($dateRef);
    $dow = (int) $dt->format('N');
    $monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
    $sunday = (clone $monday)->modify('+6 days');

    $menus = Db::fetchAll(
        "SELECT m.id, m.date_jour, m.repas, m.entree, m.plat, m.salade, m.accompagnement, m.dessert, m.remarques,
                (SELECT COUNT(*) FROM menu_reservations r WHERE r.menu_id = m.id AND r.statut = 'confirmee') AS nb_reservations,
                (SELECT SUM(r2.nb_personnes) FROM menu_reservations r2 WHERE r2.menu_id = m.id AND r2.statut = 'confirmee') AS total_couverts
         FROM menus m
         WHERE m.date_jour BETWEEN ? AND ?
         ORDER BY m.date_jour ASC, m.repas ASC",
        [$monday->format('Y-m-d'), $sunday->format('Y-m-d')]
    );

    respond([
        'success' => true,
        'menus' => $menus,
        'semaine_debut' => $monday->format('Y-m-d'),
        'semaine_fin' => $sunday->format('Y-m-d'),
    ]);
}

function cuisine_save_menu()
{
    $user = require_permission('cuisine_saisie_menu');
    global $params;

    $dateJour = Sanitize::date($params['date_jour'] ?? '');
    $repas = in_array($params['repas'] ?? '', ['midi', 'soir']) ? $params['repas'] : 'midi';
    $entree = Sanitize::text($params['entree'] ?? '', 500);
    $plat = Sanitize::text($params['plat'] ?? '', 500);
    $salade = Sanitize::text($params['salade'] ?? '', 500);
    $accompagnement = Sanitize::text($params['accompagnement'] ?? '', 500);
    $dessert = Sanitize::text($params['dessert'] ?? '', 500);
    $remarques = Sanitize::text($params['remarques'] ?? '', 2000);

    if (!$dateJour) bad_request('Date requise');
    if (!$plat) bad_request('Plat principal requis');

    $existing = Db::fetch("SELECT id FROM menus WHERE date_jour = ? AND repas = ?", [$dateJour, $repas]);

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
            "INSERT INTO menus (id, date_jour, repas, entree, plat, salade, accompagnement, dessert, remarques, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$menuId, $dateJour, $repas, $entree, $plat, $salade, $accompagnement, $dessert, $remarques, $user['id']]
        );
    }

    respond(['success' => true, 'menu_id' => $menuId, 'message' => 'Menu enregistré']);
}

function cuisine_delete_menu()
{
    require_permission('cuisine_saisie_menu');
    global $params;

    $menuId = Sanitize::text($params['menu_id'] ?? '', 36);
    if (!$menuId) bad_request('ID requis');

    $menu = Db::fetch("SELECT id FROM menus WHERE id = ?", [$menuId]);
    if (!$menu) not_found('Menu non trouvé');

    Db::exec("DELETE FROM menu_reservations WHERE menu_id = ?", [$menuId]);
    Db::exec("DELETE FROM menus WHERE id = ?", [$menuId]);

    respond(['success' => true, 'message' => 'Menu supprimé']);
}

function cuisine_get_reservations_collab()
{
    require_permission('cuisine_reservations_collab');
    global $params;

    $dateJour = Sanitize::date($params['date'] ?? date('Y-m-d'));
    $repas = in_array($params['repas'] ?? '', ['midi', 'soir']) ? $params['repas'] : 'midi';

    $reservations = Db::fetchAll(
        "SELECT r.id, r.choix, r.nb_personnes, r.remarques, r.paiement, r.statut, r.created_at,
                u.prenom, u.nom, f.nom AS fonction_nom, f.code AS fonction_code
         FROM menu_reservations r
         JOIN menus m ON m.id = r.menu_id
         JOIN users u ON u.id = r.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE m.date_jour = ? AND m.repas = ? AND r.statut = 'confirmee'
         ORDER BY u.nom, u.prenom",
        [$dateJour, $repas]
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
        'date' => $dateJour,
        'repas' => $repas,
    ]);
}

function cuisine_add_commande()
{
    require_permission('cuisine_reservations_collab');
    global $params;

    $dateJour = Sanitize::date($params['date_jour'] ?? '');
    $repas = in_array($params['repas'] ?? '', ['midi', 'soir']) ? $params['repas'] : 'midi';
    $userId = $params['user_id'] ?? '';
    $choix = in_array($params['choix'] ?? '', ['menu', 'salade']) ? $params['choix'] : 'menu';
    $nbPersonnes = Sanitize::int($params['nb_personnes'] ?? 1);
    $remarques = Sanitize::text($params['remarques'] ?? '', 500);
    $paiement = in_array($params['paiement'] ?? '', ['salaire', 'caisse', 'carte']) ? $params['paiement'] : 'salaire';

    if (!$dateJour) bad_request('Date requise');
    if (!$userId) bad_request('Collaborateur requis');
    if ($nbPersonnes < 1 || $nbPersonnes > 10) bad_request('Nombre de personnes invalide');

    // Verify user exists
    $user = Db::fetch("SELECT id FROM users WHERE id = ? AND is_active = 1", [$userId]);
    if (!$user) bad_request('Collaborateur introuvable');

    // Find or create menu for that date/repas
    $menu = Db::fetch("SELECT id FROM menus WHERE date_jour = ? AND repas = ?", [$dateJour, $repas]);
    if (!$menu) bad_request('Aucun menu pour cette date/repas. Créez d\'abord le menu.');

    $menuId = $menu['id'];

    // Check if already has a reservation
    $existing = Db::fetch(
        "SELECT id FROM menu_reservations WHERE menu_id = ? AND user_id = ? AND statut = 'confirmee'",
        [$menuId, $userId]
    );

    if ($existing) {
        Db::exec(
            "UPDATE menu_reservations SET choix = ?, nb_personnes = ?, remarques = ?, paiement = ?, updated_at = NOW() WHERE id = ?",
            [$choix, $nbPersonnes, $remarques, $paiement, $existing['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO menu_reservations (id, menu_id, user_id, choix, nb_personnes, remarques, paiement) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $menuId, $userId, $choix, $nbPersonnes, $remarques, $paiement]
        );
    }

    respond(['success' => true, 'message' => 'Commande enregistrée']);
}

function cuisine_delete_commande()
{
    require_permission('cuisine_reservations_collab');
    global $params;

    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE menu_reservations SET statut = 'annulee', updated_at = NOW() WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Commande annulée']);
}

function cuisine_search_users()
{
    require_permission('cuisine_reservations_collab');
    global $params;

    $q = trim($params['q'] ?? '');
    if (mb_strlen($q) < 2) { respond(['success' => true, 'users' => []]); return; }

    $like = "%$q%";
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo, f.nom AS fonction_nom, f.code AS fonction_code
         FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)
         ORDER BY u.nom, u.prenom LIMIT 15",
        [$like, $like, $like]
    );
    respond(['success' => true, 'users' => $users]);
}

function cuisine_get_residents()
{
    require_permission('cuisine_reservations_famille');
    global $params;

    $search = $params['search'] ?? '';
    $sql = "SELECT id, nom, prenom, chambre, etage, is_vip, menu_special FROM residents WHERE is_active = 1";
    $p = [];

    if ($search) {
        $sql .= " AND (nom LIKE ? OR prenom LIKE ?)";
        $like = "%$search%";
        $p = [$like, $like];
    }

    $sql .= " ORDER BY nom, prenom";
    respond(['success' => true, 'residents' => Db::fetchAll($sql, $p)]);
}

function cuisine_search_visiteurs()
{
    require_permission('cuisine_reservations_famille');
    global $params;

    $q = trim($params['q'] ?? '');
    $residentId = $params['resident_id'] ?? '';

    if (mb_strlen($q) < 2 && !$residentId) {
        respond(['success' => true, 'visiteurs' => []]);
        return;
    }

    $sql = "SELECT id, nom, prenom, telephone, resident_id, relation FROM visiteurs WHERE 1=1";
    $p = [];

    if ($residentId) {
        $sql .= " AND resident_id = ?";
        $p[] = $residentId;
    }

    if ($q) {
        $sql .= " AND (nom LIKE ? OR prenom LIKE ?)";
        $like = "%$q%";
        $p[] = $like;
        $p[] = $like;
    }

    $sql .= " ORDER BY nom, prenom LIMIT 20";
    respond(['success' => true, 'visiteurs' => Db::fetchAll($sql, $p)]);
}

function cuisine_save_visiteur()
{
    require_permission('cuisine_reservations_famille');
    global $params;

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $prenom = Sanitize::text($params['prenom'] ?? '', 100);
    $telephone = Sanitize::phone($params['telephone'] ?? '');
    $residentId = $params['resident_id'] ?? null;
    $relation = Sanitize::text($params['relation'] ?? '', 100);

    if (!$nom || !$prenom) bad_request('Nom et prénom requis');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO visiteurs (id, nom, prenom, telephone, resident_id, relation)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $nom, $prenom, $telephone, $residentId ?: null, $relation]
    );

    respond(['success' => true, 'id' => $id, 'message' => 'Visiteur enregistré']);
}

function cuisine_get_reservations_famille()
{
    require_permission('cuisine_reservations_famille');
    global $params;

    $dateJour = Sanitize::date($params['date'] ?? date('Y-m-d'));
    $repas = in_array($params['repas'] ?? '', ['midi', 'soir']) ? $params['repas'] : 'midi';

    $reservations = Db::fetchAll(
        "SELECT rf.*, r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre,
                v.nom AS visiteur_nom_ref, v.prenom AS visiteur_prenom_ref, v.relation,
                u.prenom AS created_prenom, u.nom AS created_nom
         FROM reservations_famille rf
         JOIN residents r ON r.id = rf.resident_id
         LEFT JOIN visiteurs v ON v.id = rf.visiteur_id
         LEFT JOIN users u ON u.id = rf.created_by
         WHERE rf.date_jour = ? AND rf.repas = ? AND rf.statut = 'confirmee'
         ORDER BY r.nom, r.prenom",
        [$dateJour, $repas]
    );

    respond(['success' => true, 'reservations' => $reservations, 'date' => $dateJour, 'repas' => $repas]);
}

function cuisine_save_reservation_famille()
{
    $user = require_permission('cuisine_reservations_famille');
    global $params;

    $id = $params['id'] ?? '';
    $dateJour = Sanitize::date($params['date_jour'] ?? '');
    $repas = in_array($params['repas'] ?? '', ['midi', 'soir']) ? $params['repas'] : 'midi';
    $residentId = $params['resident_id'] ?? '';
    $visiteurId = $params['visiteur_id'] ?? null;
    $visiteurNom = Sanitize::text($params['visiteur_nom'] ?? '', 200);
    $nbPersonnes = Sanitize::int($params['nb_personnes'] ?? 1);
    $remarques = Sanitize::text($params['remarques'] ?? '', 2000);

    if (!$dateJour) bad_request('Date requise');
    if (!$residentId) bad_request('Résident requis');
    if ($nbPersonnes < 1 || $nbPersonnes > 20) bad_request('Nombre de personnes invalide');

    if ($id) {
        // Update
        Db::exec(
            "UPDATE reservations_famille SET date_jour = ?, repas = ?, resident_id = ?, visiteur_id = ?,
             visiteur_nom = ?, nb_personnes = ?, remarques = ?, updated_at = NOW()
             WHERE id = ?",
            [$dateJour, $repas, $residentId, $visiteurId ?: null, $visiteurNom, $nbPersonnes, $remarques, $id]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO reservations_famille (id, date_jour, repas, resident_id, visiteur_id, visiteur_nom, nb_personnes, remarques, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $dateJour, $repas, $residentId, $visiteurId ?: null, $visiteurNom, $nbPersonnes, $remarques, $user['id']]
        );
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Réservation enregistrée']);
}

function cuisine_delete_reservation_famille()
{
    require_permission('cuisine_reservations_famille');
    global $params;

    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE reservations_famille SET statut = 'annulee', updated_at = NOW() WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Réservation annulée']);
}

function cuisine_get_vip()
{
    require_permission('cuisine_table_vip');

    $residents = Db::fetchAll(
        "SELECT id, nom, prenom, chambre, etage, menu_special FROM residents WHERE is_vip = 1 AND is_active = 1 ORDER BY nom, prenom"
    );

    respond(['success' => true, 'residents' => $residents]);
}

function cuisine_save_vip()
{
    require_permission('cuisine_table_vip');
    global $params;

    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('resident_id requis');

    $action = $params['vip_action'] ?? '';

    if ($action === 'set_menu') {
        $menuSpecial = Sanitize::text($params['menu_special'] ?? '', 2000);
        Db::exec("UPDATE residents SET menu_special = ?, updated_at = NOW() WHERE id = ?", [$menuSpecial, $residentId]);
        respond(['success' => true, 'message' => 'Menu spécial mis à jour']);
    } elseif ($action === 'add') {
        Db::exec("UPDATE residents SET is_vip = 1, updated_at = NOW() WHERE id = ?", [$residentId]);
        respond(['success' => true, 'message' => 'Résident ajouté à la table VIP']);
    } elseif ($action === 'remove') {
        Db::exec("UPDATE residents SET is_vip = 0, menu_special = NULL, updated_at = NOW() WHERE id = ?", [$residentId]);
        respond(['success' => true, 'message' => 'Résident retiré de la table VIP']);
    } else {
        bad_request('Action non reconnue');
    }
}

// ═══════════════════════════════════════
// VIP Sessions
// ═══════════════════════════════════════

function cuisine_get_vip_session()
{
    require_permission('cuisine_table_vip');
    global $params;

    $sessionId = $params['session_id'] ?? '';

    // If no session_id, get current/upcoming one
    if (!$sessionId) {
        $session = Db::fetch(
            "SELECT * FROM vip_sessions WHERE date_session >= ? ORDER BY date_session ASC LIMIT 1",
            [date('Y-m-d')]
        );
        if (!$session) {
            // Get most recent past one
            $session = Db::fetch("SELECT * FROM vip_sessions ORDER BY date_session DESC LIMIT 1");
        }
    } else {
        $session = Db::fetch("SELECT * FROM vip_sessions WHERE id = ?", [$sessionId]);
    }

    if (!$session) {
        respond(['success' => true, 'session' => null, 'residents' => [], 'accompagnateurs' => [], 'history' => []]);
        return;
    }

    // Residents
    $residents = Db::fetchAll(
        "SELECT vsr.id AS link_id, vsr.menu_special, r.id, r.nom, r.prenom, r.chambre, r.etage
         FROM vip_session_residents vsr
         JOIN residents r ON r.id = vsr.resident_id
         WHERE vsr.session_id = ?
         ORDER BY r.nom, r.prenom",
        [$session['id']]
    );

    // Accompagnateurs
    $accompagnateurs = Db::fetchAll(
        "SELECT vsa.id AS link_id, u.id AS user_id, u.prenom, u.nom, u.photo, f.nom AS fonction_nom, f.code AS fonction_code
         FROM vip_session_accompagnateurs vsa
         JOIN users u ON u.id = vsa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE vsa.session_id = ?
         ORDER BY u.nom",
        [$session['id']]
    );

    // History (all sessions)
    $history = Db::fetchAll(
        "SELECT vs.id, vs.date_session, vs.menu_plat, vs.statut,
                (SELECT COUNT(*) FROM vip_session_residents vsr2 WHERE vsr2.session_id = vs.id) AS nb_residents
         FROM vip_sessions vs ORDER BY vs.date_session DESC LIMIT 12"
    );

    respond([
        'success' => true,
        'session' => $session,
        'residents' => $residents,
        'accompagnateurs' => $accompagnateurs,
        'history' => $history,
    ]);
}

function cuisine_create_vip_session()
{
    $user = require_permission('cuisine_table_vip');
    global $params;

    $dateSession = Sanitize::date($params['date_session'] ?? '');
    if (!$dateSession) bad_request('Date requise');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO vip_sessions (id, date_session, created_by) VALUES (?, ?, ?)",
        [$id, $dateSession, $user['id']]
    );
    respond(['success' => true, 'id' => $id, 'message' => 'Session VIP créée']);
}

function cuisine_save_vip_session_menu()
{
    require_permission('cuisine_table_vip');
    global $params;

    $sessionId = $params['session_id'] ?? '';
    if (!$sessionId) bad_request('Session requise');

    Db::exec(
        "UPDATE vip_sessions SET menu_entree = ?, menu_plat = ?, menu_accompagnement = ?, menu_dessert = ?, menu_remarques = ?, updated_at = NOW() WHERE id = ?",
        [
            Sanitize::text($params['menu_entree'] ?? '', 500),
            Sanitize::text($params['menu_plat'] ?? '', 500),
            Sanitize::text($params['menu_accompagnement'] ?? '', 500),
            Sanitize::text($params['menu_dessert'] ?? '', 500),
            Sanitize::text($params['menu_remarques'] ?? '', 2000),
            $sessionId,
        ]
    );
    respond(['success' => true, 'message' => 'Menu VIP enregistré']);
}

function cuisine_add_vip_resident()
{
    require_permission('cuisine_table_vip');
    global $params;

    $sessionId = $params['session_id'] ?? '';
    $residentId = $params['resident_id'] ?? '';
    if (!$sessionId || !$residentId) bad_request('Session et résident requis');

    $existing = Db::fetch("SELECT id FROM vip_session_residents WHERE session_id = ? AND resident_id = ?", [$sessionId, $residentId]);
    if ($existing) bad_request('Résident déjà dans cette session');

    Db::exec(
        "INSERT INTO vip_session_residents (id, session_id, resident_id) VALUES (?, ?, ?)",
        [Uuid::v4(), $sessionId, $residentId]
    );
    respond(['success' => true, 'message' => 'Résident ajouté']);
}

function cuisine_remove_vip_resident()
{
    require_permission('cuisine_table_vip');
    global $params;

    $linkId = $params['link_id'] ?? '';
    if (!$linkId) bad_request('ID requis');

    Db::exec("DELETE FROM vip_session_residents WHERE id = ?", [$linkId]);
    respond(['success' => true, 'message' => 'Résident retiré']);
}

function cuisine_add_vip_accompagnateur()
{
    require_permission('cuisine_table_vip');
    global $params;

    $sessionId = $params['session_id'] ?? '';
    $userId = $params['user_id'] ?? '';
    if (!$sessionId || !$userId) bad_request('Session et accompagnateur requis');

    $existing = Db::fetch("SELECT id FROM vip_session_accompagnateurs WHERE session_id = ? AND user_id = ?", [$sessionId, $userId]);
    if ($existing) bad_request('Déjà assigné');

    Db::exec(
        "INSERT INTO vip_session_accompagnateurs (id, session_id, user_id) VALUES (?, ?, ?)",
        [Uuid::v4(), $sessionId, $userId]
    );
    respond(['success' => true, 'message' => 'Accompagnateur ajouté']);
}

function cuisine_remove_vip_accompagnateur()
{
    require_permission('cuisine_table_vip');
    global $params;

    $linkId = $params['link_id'] ?? '';
    if (!$linkId) bad_request('ID requis');

    Db::exec("DELETE FROM vip_session_accompagnateurs WHERE id = ?", [$linkId]);
    respond(['success' => true, 'message' => 'Accompagnateur retiré']);
}

function cuisine_close_vip_session()
{
    require_permission('cuisine_table_vip');
    global $params;

    $sessionId = $params['session_id'] ?? '';
    if (!$sessionId) bad_request('Session requise');

    Db::exec("UPDATE vip_sessions SET statut = 'termine', updated_at = NOW() WHERE id = ?", [$sessionId]);
    respond(['success' => true, 'message' => 'Session terminée']);
}
