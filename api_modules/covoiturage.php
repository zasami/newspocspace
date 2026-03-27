<?php
/**
 * Covoiturage — find colleagues with matching shifts
 */

function get_covoiturage_matches()
{
    global $params;
    $user = require_auth();

    $date = Sanitize::date($params['date'] ?? '');
    if (!$date) bad_request('Date requise');

    $mois = substr($date, 0, 7);

    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) {
        respond(['success' => true, 'matches' => [], 'mon_horaire' => null]);
        return;
    }

    // Get my assignation for this date
    $monAssign = Db::fetch(
        "SELECT pa.*, ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.heure_debut, ht.heure_fin, ht.couleur,
                m.nom AS module_nom, m.code AS module_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.user_id = ? AND pa.date_jour = ?",
        [$planning['id'], $user['id'], $date]
    );

    if (!$monAssign || !$monAssign['horaire_type_id']) {
        respond(['success' => true, 'matches' => [], 'mon_horaire' => null]);
        return;
    }

    // Get user's buddy list
    $buddyIds = Db::fetchAll(
        "SELECT buddy_id FROM covoiturage_buddies WHERE user_id = ?",
        [$user['id']]
    );
    $buddyIds = array_column($buddyIds, 'buddy_id');

    // If no buddies, return empty (user needs to add buddies first)
    if (empty($buddyIds)) {
        respond([
            'success' => true,
            'mon_horaire' => [
                'code' => $monAssign['horaire_code'],
                'nom' => $monAssign['horaire_nom'],
                'debut' => $monAssign['heure_debut'],
                'fin' => $monAssign['heure_fin'],
                'couleur' => $monAssign['couleur'],
                'module' => $monAssign['module_nom'],
            ],
            'same_shift' => [],
            'other_shift' => [],
            'no_buddies' => true,
        ]);
        return;
    }

    // Find buddies with shifts on same date
    $placeholders = implode(',', array_fill(0, count($buddyIds), '?'));
    $matches = Db::fetchAll(
        "SELECT pa.user_id, pa.statut,
                u.prenom, u.nom, u.photo,
                f.code AS fonction_code, f.nom AS fonction_nom,
                ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.heure_debut, ht.heure_fin, ht.couleur,
                m.nom AS module_nom
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id AND u.is_active = 1
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.date_jour = ?
           AND pa.user_id IN ($placeholders)
           AND pa.horaire_type_id IS NOT NULL
           AND pa.statut IN ('present','entraide')
         ORDER BY ht.heure_debut, u.nom",
        array_merge([$planning['id'], $date], $buddyIds)
    );

    // Group: same horaire = perfect match, overlapping = partial
    $sameShift = [];
    $otherShift = [];

    foreach ($matches as $m) {
        if ($m['horaire_code'] === $monAssign['horaire_code']) {
            $m['match_type'] = 'exact';
            $sameShift[] = $m;
        } else {
            // Check overlap
            if ($m['heure_debut'] <= $monAssign['heure_fin'] && $m['heure_fin'] >= $monAssign['heure_debut']) {
                $m['match_type'] = 'overlap';
                $otherShift[] = $m;
            }
        }
    }

    respond([
        'success' => true,
        'mon_horaire' => [
            'code' => $monAssign['horaire_code'],
            'nom' => $monAssign['horaire_nom'],
            'debut' => $monAssign['heure_debut'],
            'fin' => $monAssign['heure_fin'],
            'couleur' => $monAssign['couleur'],
            'module' => $monAssign['module_nom'],
        ],
        'same_shift' => $sameShift,
        'other_shift' => $otherShift,
    ]);
}

function get_covoiturage_semaine()
{
    global $params;
    $user = require_auth();

    $date = Sanitize::date($params['date'] ?? '');
    if (!$date) bad_request('Date requise');

    // Get the week (Monday to Sunday)
    $d = new DateTime($date);
    $dow = (int)$d->format('N');
    $monday = (clone $d)->modify('-' . ($dow - 1) . ' days');

    $weekDays = [];
    for ($i = 0; $i < 7; $i++) {
        $weekDays[] = (clone $monday)->modify("+$i days")->format('Y-m-d');
    }

    // Get all my assignations for the week
    $moisSet = [];
    foreach ($weekDays as $wd) {
        $moisSet[substr($wd, 0, 7)] = true;
    }

    $results = [];
    foreach (array_keys($moisSet) as $mois) {
        $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
        if (!$planning) continue;

        $myAssigns = Db::fetchAll(
            "SELECT pa.date_jour, pa.horaire_type_id, ht.code AS horaire_code, ht.heure_debut, ht.heure_fin
             FROM planning_assignations pa
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             WHERE pa.planning_id = ? AND pa.user_id = ? AND pa.date_jour IN (" . implode(',', array_fill(0, count($weekDays), '?')) . ")
               AND pa.horaire_type_id IS NOT NULL",
            array_merge([$planning['id'], $user['id']], $weekDays)
        );

        // Get user's buddy list for filtering
        $buddyIds = Db::fetchAll(
            "SELECT buddy_id FROM covoiturage_buddies WHERE user_id = ?",
            [$user['id']]
        );
        $buddyIds = array_column($buddyIds, 'buddy_id');

        foreach ($myAssigns as $ma) {
            $count = 0;
            if (!empty($buddyIds)) {
                $ph = implode(',', array_fill(0, count($buddyIds), '?'));
                $count = (int)Db::getOne(
                    "SELECT COUNT(*) FROM planning_assignations
                     WHERE planning_id = ? AND date_jour = ? AND user_id IN ($ph)
                       AND horaire_type_id = ? AND statut IN ('present','entraide')",
                    array_merge([$planning['id'], $ma['date_jour']], $buddyIds, [$ma['horaire_type_id']])
                );
            }
            $results[$ma['date_jour']] = [
                'horaire' => $ma['horaire_code'],
                'debut' => $ma['heure_debut'],
                'fin' => $ma['heure_fin'],
                'same_shift_count' => $count,
            ];
        }
    }

    respond([
        'success' => true,
        'week_start' => $weekDays[0],
        'days' => $results,
        'has_buddies' => !empty($buddyIds),
    ]);
}

// ── Buddy management ──

function get_covoiturage_buddies()
{
    $user = require_auth();

    $buddies = Db::fetchAll(
        "SELECT cb.id AS buddy_entry_id, cb.buddy_id,
                u.prenom, u.nom, u.photo, u.email,
                f.nom AS fonction_nom, m.nom AS module_nom
         FROM covoiturage_buddies cb
         JOIN users u ON u.id = cb.buddy_id AND u.is_active = 1
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE cb.user_id = ?
         ORDER BY u.nom, u.prenom",
        [$user['id']]
    );

    respond(['success' => true, 'buddies' => $buddies]);
}

function add_covoiturage_buddy()
{
    global $params;
    $user = require_auth();

    $buddyId = Sanitize::text($params['buddy_id'] ?? '');
    if (!$buddyId) bad_request('Collègue requis');

    if ($buddyId === $user['id']) bad_request('Vous ne pouvez pas vous ajouter vous-même');

    // Check buddy exists
    $buddy = Db::fetch("SELECT id FROM users WHERE id = ? AND is_active = 1", [$buddyId]);
    if (!$buddy) not_found('Utilisateur introuvable');

    // Check not already added
    $exists = Db::fetch(
        "SELECT id FROM covoiturage_buddies WHERE user_id = ? AND buddy_id = ?",
        [$user['id'], $buddyId]
    );
    if ($exists) {
        respond(['success' => true, 'message' => 'Déjà dans votre liste']);
        return;
    }

    Db::exec(
        "INSERT INTO covoiturage_buddies (id, user_id, buddy_id) VALUES (?, ?, ?)",
        [Uuid::v4(), $user['id'], $buddyId]
    );

    respond(['success' => true, 'message' => 'Collègue ajouté']);
}

function remove_covoiturage_buddy()
{
    global $params;
    $user = require_auth();

    $buddyId = Sanitize::text($params['buddy_id'] ?? '');
    if (!$buddyId) bad_request('Collègue requis');

    Db::exec(
        "DELETE FROM covoiturage_buddies WHERE user_id = ? AND buddy_id = ?",
        [$user['id'], $buddyId]
    );

    respond(['success' => true, 'message' => 'Collègue retiré']);
}

function search_covoiturage_users()
{
    global $params;
    $user = require_auth();

    $q = Sanitize::text($params['q'] ?? '');
    if (strlen($q) < 2) bad_request('Recherche trop courte');

    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo,
                f.nom AS fonction_nom
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND u.id != ?
           AND (u.prenom LIKE ? OR u.nom LIKE ? OR CONCAT(u.prenom, ' ', u.nom) LIKE ?)
         ORDER BY u.nom, u.prenom
         LIMIT 20",
        [$user['id'], "%$q%", "%$q%", "%$q%"]
    );

    // Mark which ones are already buddies
    $buddyIds = Db::fetchAll(
        "SELECT buddy_id FROM covoiturage_buddies WHERE user_id = ?",
        [$user['id']]
    );
    $buddySet = array_flip(array_column($buddyIds, 'buddy_id'));

    foreach ($users as &$u) {
        $u['is_buddy'] = isset($buddySet[$u['id']]);
    }

    respond(['success' => true, 'users' => $users]);
}
