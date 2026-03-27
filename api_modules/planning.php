<?php
/**
 * Planning API actions
 */

function get_planning_hebdo()
{
    global $params;
    require_auth();

    $date = Sanitize::date($params['date'] ?? date('Y-m-d'));
    if (!$date) $date = date('Y-m-d');

    // Get Monday of the week
    $dt = new DateTime($date);
    $dayOfWeek = (int) $dt->format('N'); // 1=Mon, 7=Sun
    $dt->modify('-' . ($dayOfWeek - 1) . ' days');
    $lundi = $dt->format('Y-m-d');
    $dt->modify('+6 days');
    $dimanche = $dt->format('Y-m-d');

    // Find the planning for this month
    $moisAnnee = (new DateTime($lundi))->format('Y-m');
    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$moisAnnee]);

    $assignations = [];
    if ($planning) {
        $assignations = Db::fetchAll(
            "SELECT pa.*, u.nom, u.prenom, u.employee_id, u.taux,
                    ht.code AS horaire_code, ht.heure_debut, ht.heure_fin, ht.couleur, ht.duree_effective,
                    COALESCE(m.nom, mp.nom) AS module_nom,
                    COALESCE(m.code, mp.code) AS module_code,
                    g.nom AS groupe_nom, g.code AS groupe_code,
                    f.nom AS fonction_nom, f.code AS fonction_code
             FROM planning_assignations pa
             JOIN users u ON u.id = pa.user_id
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             LEFT JOIN modules m ON m.id = pa.module_id
             LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
             LEFT JOIN modules mp ON mp.id = um.module_id
             LEFT JOIN groupes g ON g.id = pa.groupe_id
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             WHERE pa.planning_id = ?
               AND pa.date_jour BETWEEN ? AND ?
             ORDER BY COALESCE(m.ordre, mp.ordre), f.ordre, u.nom",
            [$planning['id'], $lundi, $dimanche]
        );
    }

    respond([
        'success' => true,
        'lundi' => $lundi,
        'dimanche' => $dimanche,
        'assignations' => $assignations,
    ]);
}

function get_planning_mois()
{
    global $params;
    require_auth();

    $mois = $params['mois'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
        $mois = date('Y-m');
    }

    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);

    respond([
        'success' => true,
        'planning' => $planning,
    ]);
}

function get_mon_planning()
{
    global $params;
    $user = require_auth();

    $mois = $params['mois'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
        $mois = date('Y-m');
    }

    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);

    $assignations = [];
    if ($planning) {
        $assignations = Db::fetchAll(
            "SELECT pa.*, ht.code AS horaire_code, ht.heure_debut, ht.heure_fin, ht.couleur,
                    m.nom AS module_nom, g.nom AS groupe_nom
             FROM planning_assignations pa
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             LEFT JOIN modules m ON m.id = pa.module_id
             LEFT JOIN groupes g ON g.id = pa.groupe_id
             WHERE pa.planning_id = ? AND pa.user_id = ?
             ORDER BY pa.date_jour",
            [$planning['id'], $user['id']]
        );
    }

    respond([
        'success' => true,
        'mois' => $mois,
        'assignations' => $assignations,
    ]);
}

function get_modules_list()
{
    require_auth();
    $modules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
    respond(['success' => true, 'modules' => $modules]);
}
