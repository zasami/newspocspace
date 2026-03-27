<?php
/**
 * Employee-side Repartition API — View weekly staffing by module
 */

function get_repartition()
{
    $user = require_auth();
    global $params;

    // Determine week
    $weekStart = null;
    if (!empty($params['date'])) {
        $d = Sanitize::date($params['date']);
        if ($d) {
            $dto = new DateTime($d);
            $dow = (int)$dto->format('N');
            $dto->modify('-' . ($dow - 1) . ' days');
            $weekStart = $dto->format('Y-m-d');
        }
    }
    if (!$weekStart) {
        $dto = new DateTime();
        $dow = (int)$dto->format('N');
        $dto->modify('-' . ($dow - 1) . ' days');
        $weekStart = $dto->format('Y-m-d');
    }

    $dtoStart = new DateTime($weekStart);
    $dtoEnd = clone $dtoStart;
    $dtoEnd->modify('+6 days');
    $weekEnd = $dtoEnd->format('Y-m-d');
    $weekNum = (int)$dtoStart->format('W');
    $year = (int)$dtoStart->format('o');

    // Modules
    $modules = Db::fetchAll("SELECT id, nom, code, ordre FROM modules ORDER BY ordre");

    // Horaires
    $horaires = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");

    // Fonctions
    $fonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");

    // Users with home module + fonction
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                hm.id AS home_module_id, hm.code AS home_module_code, hm.nom AS home_module_nom
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules hm ON hm.id = um.module_id
         WHERE u.is_active = 1
         ORDER BY f.ordre, u.nom"
    );

    // Plannings for the week
    $moisStart = $dtoStart->format('Y-m');
    $moisEnd = $dtoEnd->format('Y-m');
    $moisList = [$moisStart];
    if ($moisEnd !== $moisStart) $moisList[] = $moisEnd;
    $ph = implode(',', array_fill(0, count($moisList), '?'));
    $plannings = Db::fetchAll("SELECT id, mois_annee, statut FROM plannings WHERE mois_annee IN ($ph)", $moisList);
    $planningIds = array_column($plannings, 'id');

    $assignments = [];
    if ($planningIds) {
        $phPlan = implode(',', array_fill(0, count($planningIds), '?'));
        $assignments = Db::fetchAll(
            "SELECT pa.date_jour, pa.user_id, pa.statut,
                    ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                    ht.heure_debut, ht.heure_fin,
                    m.code AS module_code, m.id AS module_id
             FROM planning_assignations pa
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             LEFT JOIN modules m ON m.id = pa.module_id
             WHERE pa.planning_id IN ($phPlan)
               AND pa.date_jour BETWEEN ? AND ?
             ORDER BY pa.date_jour, m.ordre",
            array_merge($planningIds, [$weekStart, $weekEnd])
        );
    }

    // Day labels
    $frDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $d = clone $dtoStart;
        $d->modify("+$i days");
        $days[] = [
            'date' => $d->format('Y-m-d'),
            'label' => $frDays[$i] . ' ' . $d->format('d'),
            'short' => $frDays[$i],
            'is_weekend' => in_array($d->format('N'), ['6', '7']),
        ];
    }

    respond([
        'success' => true,
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'week_num' => $weekNum,
        'year' => $year,
        'days' => $days,
        'modules' => $modules,
        'fonctions' => $fonctions,
        'horaires' => $horaires,
        'assignments' => $assignments,
        'users' => $users,
    ]);
}
