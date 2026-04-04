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
            "SELECT pa.id AS assignation_id, pa.planning_id, pa.date_jour, pa.user_id,
                    pa.horaire_type_id, pa.statut, pa.notes, pa.updated_at,
                    u.prenom AS user_prenom, u.nom AS user_nom,
                    f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                    ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                    ht.heure_debut, ht.heure_fin,
                    m.code AS module_code, m.id AS module_id,
                    g.code AS groupe_code, g.id AS groupe_id,
                    e.code AS etage_code, e.id AS etage_id
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

    // IDs of assignations that have been modified from répartition
    $modifiedIds = [];
    if ($assignments) {
        $aIds = array_column($assignments, 'assignation_id');
        $phA = implode(',', array_fill(0, count($aIds), '?'));
        $modRows = Db::fetchAll(
            "SELECT DISTINCT planning_assignation_id FROM planning_modifications WHERE planning_assignation_id IN ($phA)",
            $aIds
        );
        $modifiedIds = array_column($modRows, 'planning_assignation_id');
    }

    // Absences for the week
    $absences = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin, type, motif
         FROM absences
         WHERE statut = 'valide' AND date_debut <= ? AND date_fin >= ?
         ORDER BY date_debut",
        [$weekEnd, $weekStart]
    );

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
        'modified_ids' => $modifiedIds,
        'absences' => $absences,
    ]);
}

// ─── Save répartition cell ──────────────────────────────────────────────────
function admin_save_repartition_cell()
{
    global $params;
    require_responsable();

    $assignationId = $params['assignation_id'] ?? '';
    $planningId    = $params['planning_id'] ?? '';
    $userId        = $params['user_id'] ?? '';
    $dateJour      = Sanitize::date($params['date_jour'] ?? '');
    $horaireTypeId = $params['horaire_type_id'] ?? null;
    $moduleId      = $params['module_id'] ?? null;
    $groupeId      = $params['groupe_id'] ?? null;
    $etageId       = $params['etage_id'] ?? null;
    $statut        = $params['statut'] ?? 'present';
    $notes         = Sanitize::text($params['notes'] ?? '', 500);

    if (!$userId || !$dateJour) {
        bad_request('user_id et date_jour requis');
    }

    $adminId = $_SESSION['ss_user']['id'];

    // If editing existing assignation
    if ($assignationId) {
        $existing = Db::fetch(
            "SELECT * FROM planning_assignations WHERE id = ?",
            [$assignationId]
        );
        if (!$existing) not_found('Assignation introuvable');

        // Optimistic locking
        $expectedUpdatedAt = $params['expected_updated_at'] ?? null;
        if ($expectedUpdatedAt && $existing['updated_at'] && $expectedUpdatedAt !== $existing['updated_at']) {
            respond([
                'success' => false,
                'conflict' => true,
                'message' => 'Cette cellule a été modifiée par un autre utilisateur.',
            ]);
            return;
        }

        // Log modifications
        $fields = [
            'horaire_type_id' => $horaireTypeId,
            'module_id'       => $moduleId,
            'groupe_id'       => $groupeId,
            'statut'          => $statut,
            'notes'           => $notes,
        ];
        foreach ($fields as $champ => $newVal) {
            $oldVal = $existing[$champ] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                Db::exec(
                    "INSERT INTO planning_modifications (id, planning_assignation_id, user_id_modified_by, champ, ancienne_valeur, nouvelle_valeur, source)
                     VALUES (?, ?, ?, ?, ?, ?, 'repartition')",
                    [Uuid::v4(), $assignationId, $adminId, $champ, $oldVal, $newVal]
                );
            }
        }

        Db::exec(
            "UPDATE planning_assignations
             SET horaire_type_id = ?, module_id = ?, groupe_id = ?, etage_id = ?, statut = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [$horaireTypeId, $moduleId, $groupeId, $etageId, $statut, $notes, $assignationId]
        );

        respond(['success' => true, 'id' => $assignationId, 'message' => 'Cellule mise à jour']);

    } else {
        // Create new assignation — need planning_id
        if (!$planningId) {
            // Find planning for this date
            $mois = substr($dateJour, 0, 7);
            $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
            if (!$planning) bad_request('Aucun planning trouvé pour ' . $mois);
            $planningId = $planning['id'];
        }

        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, groupe_id, etage_id, statut, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $planningId, $userId, $dateJour, $horaireTypeId, $moduleId, $groupeId, $etageId, $statut, $notes]
        );

        respond(['success' => true, 'id' => $id, 'message' => 'Assignation créée']);
    }
}

// ─── Mark absent from répartition ───────────────────────────────────────────
function admin_mark_absent_repartition()
{
    global $params;
    require_responsable();

    $assignationId = $params['assignation_id'] ?? '';
    $absenceType   = Sanitize::text($params['absence_type'] ?? 'maladie', 50);
    $motif         = Sanitize::text($params['motif'] ?? '', 500);
    $dateDebut     = Sanitize::date($params['date_debut'] ?? '');
    $dateFin       = Sanitize::date($params['date_fin'] ?? '');

    if (!$assignationId) bad_request('assignation_id requis');

    $existing = Db::fetch("SELECT * FROM planning_assignations WHERE id = ?", [$assignationId]);
    if (!$existing) not_found('Assignation introuvable');

    $adminId = $_SESSION['ss_user']['id'];
    $userId  = $existing['user_id'];

    // Log the status change
    if ($existing['statut'] !== 'absent') {
        Db::exec(
            "INSERT INTO planning_modifications (id, planning_assignation_id, user_id_modified_by, champ, ancienne_valeur, nouvelle_valeur, source)
             VALUES (?, ?, ?, 'statut', ?, 'absent', 'repartition')",
            [Uuid::v4(), $assignationId, $adminId, $existing['statut']]
        );
    }

    // Update the assignation status
    Db::exec(
        "UPDATE planning_assignations SET statut = 'absent', updated_at = NOW() WHERE id = ?",
        [$assignationId]
    );

    // If multi-day absence, create in absences table
    if ($dateDebut && $dateFin && $dateDebut !== $dateFin) {
        $absId = Uuid::v4();
        Db::exec(
            "INSERT INTO absences (id, user_id, type, date_debut, date_fin, motif, statut, valide_par, valide_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'valide', ?, NOW(), NOW())",
            [$absId, $userId, $absenceType, $dateDebut, $dateFin, $motif, $adminId]
        );

        // Also update other assignations in the date range
        $planningId = $existing['planning_id'];
        Db::exec(
            "UPDATE planning_assignations
             SET statut = 'absent', updated_at = NOW()
             WHERE planning_id = ? AND user_id = ? AND date_jour BETWEEN ? AND ? AND id != ?",
            [$planningId, $userId, $dateDebut, $dateFin, $assignationId]
        );
    }

    respond(['success' => true, 'message' => 'Absence enregistrée']);
}

// ─── Delete assignation from répartition ─────────────────────────────────────
function admin_delete_repartition_cell()
{
    global $params;
    require_responsable();

    $assignationId = $params['assignation_id'] ?? '';
    if (!$assignationId) bad_request('assignation_id requis');

    $existing = Db::fetch("SELECT * FROM planning_assignations WHERE id = ?", [$assignationId]);
    if (!$existing) not_found('Assignation introuvable');

    $adminId = $_SESSION['ss_user']['id'];

    // Log deletion
    Db::exec(
        "INSERT INTO planning_modifications (id, planning_assignation_id, user_id_modified_by, champ, ancienne_valeur, nouvelle_valeur, source)
         VALUES (?, ?, ?, 'deleted', 'exists', 'deleted', 'repartition')",
        [Uuid::v4(), $assignationId, $adminId]
    );

    Db::exec("DELETE FROM planning_assignations WHERE id = ?", [$assignationId]);

    respond(['success' => true, 'message' => 'Assignation supprimée']);
}

// ─── Get modification history ────────────────────────────────────────────────
function admin_get_repartition_modifications()
{
    global $params;
    require_responsable();

    $assignationId = $params['assignation_id'] ?? '';
    if (!$assignationId) bad_request('assignation_id requis');

    $mods = Db::fetchAll(
        "SELECT pm.*, u.prenom, u.nom
         FROM planning_modifications pm
         JOIN users u ON u.id = pm.user_id_modified_by
         WHERE pm.planning_assignation_id = ?
         ORDER BY pm.created_at DESC",
        [$assignationId]
    );

    respond(['success' => true, 'modifications' => $mods]);
}
