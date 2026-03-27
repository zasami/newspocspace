<?php

function admin_get_repartition()
{
    global $params;
    require_responsable();

    // Determine week from "semaine" (e.g. "2026-W12") or "date" or default to current week
    $weekStart = null;
    if (!empty($params['semaine']) && preg_match('/^(\d{4})-W(\d{2})$/', $params['semaine'], $m)) {
        $dto = new DateTime();
        $dto->setISODate((int)$m[1], (int)$m[2], 1); // Monday
        $weekStart = $dto->format('Y-m-d');
    } elseif (!empty($params['date'])) {
        $d = Sanitize::date($params['date']);
        if ($d) {
            $dto = new DateTime($d);
            $dow = (int)$dto->format('N'); // 1=Mon
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

    // French month names
    $frMonths = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    $startDay = (int)$dtoStart->format('j');
    $endDay = (int)$dtoEnd->format('j');
    $startMonth = $frMonths[(int)$dtoStart->format('n')];
    $endMonth = $frMonths[(int)$dtoEnd->format('n')];

    if ($dtoStart->format('n') === $dtoEnd->format('n')) {
        $weekLabel = "Semaine $weekNum — $startDay au $endDay $endMonth $year";
    } else {
        $weekLabel = "Semaine $weekNum — $startDay $startMonth au $endDay $endMonth $year";
    }

    // Modules with étages and groupes
    $modules = Db::fetchAll("SELECT id, nom, code, ordre FROM modules ORDER BY ordre");
    foreach ($modules as &$mod) {
        $etages = Db::fetchAll(
            "SELECT id, nom, code, ordre FROM etages WHERE module_id = ? ORDER BY ordre",
            [$mod['id']]
        );
        foreach ($etages as &$etage) {
            $etage['groupes'] = Db::fetchAll(
                "SELECT id, nom, code, ordre FROM groupes WHERE etage_id = ? ORDER BY ordre",
                [$etage['id']]
            );
        }
        unset($etage);
        $mod['etages'] = $etages;
    }
    unset($mod);

    // Horaires types
    $horaires = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");

    // Fonctions
    $fonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");

    // All active users with their home (principal) module and fonction
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.employee_id,
                f.id AS fonction_id, f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                hm.id AS home_module_id, hm.code AS home_module_code, hm.nom AS home_module_nom, hm.ordre AS home_module_ordre
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules hm ON hm.id = um.module_id
         WHERE u.is_active = 1
         ORDER BY hm.ordre, f.ordre, u.nom"
    );

    // Find planning(s) covering this week — could span 2 months
    $moisStart = $dtoStart->format('Y-m');
    $moisEnd = $dtoEnd->format('Y-m');
    $moisList = [$moisStart];
    if ($moisEnd !== $moisStart) {
        $moisList[] = $moisEnd;
    }
    $placeholders = implode(',', array_fill(0, count($moisList), '?'));
    $plannings = Db::fetchAll(
        "SELECT id, mois_annee, statut FROM plannings WHERE mois_annee IN ($placeholders)",
        $moisList
    );
    $planningIds = array_column($plannings, 'id');

    $assignments = [];
    if ($planningIds) {
        $phPlan = implode(',', array_fill(0, count($planningIds), '?'));
        $qParams = array_merge($planningIds, [$weekStart, $weekEnd]);
        $assignments = Db::fetchAll(
            "SELECT pa.date_jour, pa.user_id, pa.statut, pa.notes,
                    u.prenom AS user_prenom, u.nom AS user_nom,
                    f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                    ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                    ht.heure_debut, ht.heure_fin,
                    m.code AS module_code, m.id AS module_id,
                    g.code AS groupe_code, g.id AS groupe_id,
                    e.code AS etage_code
             FROM planning_assignations pa
             JOIN users u ON u.id = pa.user_id
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             LEFT JOIN modules m ON m.id = pa.module_id
             LEFT JOIN groupes g ON g.id = pa.groupe_id
             LEFT JOIN etages e ON e.id = g.etage_id
             WHERE pa.planning_id IN ($phPlan)
               AND pa.date_jour BETWEEN ? AND ?
             ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
            $qParams
        );
    }

    // Build day labels
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
        'week_label' => $weekLabel,
        'week_iso' => "$year-W" . str_pad($weekNum, 2, '0', STR_PAD_LEFT),
        'days' => $days,
        'modules' => $modules,
        'fonctions' => $fonctions,
        'horaires' => $horaires,
        'plannings' => $plannings,
        'assignments' => $assignments,
        'users' => $users,
    ]);
}
