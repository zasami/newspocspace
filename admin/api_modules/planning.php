<?php
require_once __DIR__ . '/../../core/Notification.php';

function admin_get_planning()
{
    require_responsable();
    global $params;
    $mois = $params['mois'] ?? date('Y-m');

    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);

    $assignations = [];
    if ($planning) {
        $assignations = Db::fetchAll(
            "SELECT pa.*, u.nom, u.prenom, u.employee_id,
                    ht.code AS horaire_code, ht.heure_debut, ht.heure_fin, ht.couleur,
                    m.nom AS module_nom, m.code AS module_code,
                    g.nom AS groupe_nom, g.code AS groupe_code,
                    f.nom AS fonction_nom, f.code AS fonction_code
             FROM planning_assignations pa
             JOIN users u ON u.id = pa.user_id
             LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             LEFT JOIN modules m ON m.id = pa.module_id
             LEFT JOIN groupes g ON g.id = pa.groupe_id
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             WHERE pa.planning_id = ?
             ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
            [$planning['id']]
        );
    }

    // Load validated absences for this month
    $moisStart = $mois . '-01';
    $moisEnd = date('Y-m-t', strtotime($moisStart));
    $absences = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin, type, motif
         FROM absences
         WHERE statut = 'valide' AND date_debut <= ? AND date_fin >= ?
         ORDER BY date_debut",
        [$moisEnd, $moisStart]
    );

    // Load formations chevauchant le mois (sessions inscrites/présentes)
    // Une formation bloque les dates [date_debut..date_fin] pour ses participants
    $formations = Db::fetchAll(
        "SELECT p.user_id,
                COALESCE(fs.date_debut, f.date_debut) AS date_debut,
                COALESCE(fs.date_fin,   f.date_fin,   fs.date_debut, f.date_debut) AS date_fin,
                f.titre, f.duree_heures,
                fs.heure_debut, fs.heure_fin, fs.lieu, fs.modalite,
                f.id AS formation_id
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         LEFT JOIN formation_sessions fs ON fs.id = p.session_id
         WHERE p.statut IN ('inscrit','present','valide')
           AND COALESCE(fs.date_debut, f.date_debut) <= ?
           AND COALESCE(fs.date_fin, f.date_fin, fs.date_debut, f.date_debut) >= ?
         ORDER BY COALESCE(fs.date_debut, f.date_debut)",
        [$moisEnd, $moisStart]
    );

    respond([
        'success' => true,
        'planning' => $planning,
        'assignations' => $assignations,
        'absences' => $absences,
        'formations' => $formations,
    ]);
}

function admin_create_planning()
{
    global $params;
    require_admin();

    $mois = $params['mois'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) bad_request('Mois invalide');

    $existing = Db::getOne("SELECT COUNT(*) FROM plannings WHERE mois_annee = ?", [$mois]);
    if ($existing > 0) bad_request('Un planning existe déjà pour ce mois');

    $id = Uuid::v4();
    $userId = $_SESSION['ss_user']['id'];
    Db::exec(
        "INSERT INTO plannings (id, mois_annee, statut, genere_par, genere_at) VALUES (?, ?, 'brouillon', ?, NOW())",
        [$id, $mois, $userId]
    );

    respond(['success' => true, 'message' => 'Planning créé', 'id' => $id]);
}

function admin_save_assignation()
{
    global $params;
    require_responsable();

    $planningId = $params['planning_id'] ?? '';
    $userId = $params['user_id'] ?? '';
    $dateJour = Sanitize::date($params['date_jour'] ?? '');
    $horaireTypeId = $params['horaire_type_id'] ?? null;
    $moduleId = $params['module_id'] ?? null;
    $groupeId = $params['groupe_id'] ?? null;
    $statut = $params['statut'] ?? 'present';
    $notes = Sanitize::text($params['notes'] ?? '', 500);

    if (!$planningId || !$userId || !$dateJour) {
        bad_request('planning_id, user_id et date_jour requis');
    }

    // Optimistic locking: client sends expected_updated_at to detect conflicts
    $expectedUpdatedAt = $params['expected_updated_at'] ?? null;

    // Upsert
    $existing = Db::fetch(
        "SELECT id, updated_at FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
        [$planningId, $userId, $dateJour]
    );

    if ($existing) {
        // Conflict detection: if client sent an expected timestamp, verify it matches
        if ($expectedUpdatedAt && $existing['updated_at'] && $expectedUpdatedAt !== $existing['updated_at']) {
            respond([
                'success' => false,
                'conflict' => true,
                'message' => 'Cette cellule a été modifiée par un autre utilisateur. Rechargez le planning.',
                'server_updated_at' => $existing['updated_at'],
            ]);
            return;
        }

        Db::exec(
            "UPDATE planning_assignations
             SET horaire_type_id = ?, module_id = ?, groupe_id = ?, statut = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [$horaireTypeId, $moduleId, $groupeId, $statut, $notes, $existing['id']]
        );
        $id = $existing['id'];
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, groupe_id, statut, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $planningId, $userId, $dateJour, $horaireTypeId, $moduleId, $groupeId, $statut, $notes]
        );
    }

    respond(['success' => true, 'id' => $id]);
}

function admin_delete_assignation()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $expectedUpdatedAt = $params['expected_updated_at'] ?? null;
    if ($expectedUpdatedAt) {
        $existing = Db::fetch("SELECT updated_at FROM planning_assignations WHERE id = ?", [$id]);
        if ($existing && $existing['updated_at'] && $expectedUpdatedAt !== $existing['updated_at']) {
            respond([
                'success' => false,
                'conflict' => true,
                'message' => 'Cette cellule a été modifiée par un autre utilisateur. Rechargez le planning.',
            ]);
            return;
        }
    }

    Db::exec("DELETE FROM planning_assignations WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_finalize_planning()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? 'final';

    if (!in_array($statut, ['provisoire', 'final'])) bad_request('Statut invalide');

    $planning = Db::fetch("SELECT * FROM plannings WHERE id = ?", [$id]);
    if (!$planning) bad_request('Planning introuvable');

    Db::exec(
        "UPDATE plannings SET statut = ?, valide_par = ?, valide_at = NOW() WHERE id = ?",
        [$statut, $_SESSION['ss_user']['id'], $id]
    );

    // Notify all active users when planning is published (provisoire or final)
    $label = $statut === 'final' ? 'définitif' : 'provisoire';
    $mois = $planning['mois_annee'] ?? '';
    $activeUsers = Db::fetchAll("SELECT id FROM users WHERE is_active = 1");
    foreach ($activeUsers as $u) {
        Notification::create(
            $u['id'],
            'planning_publie',
            'Planning publié',
            "Le planning de $mois est maintenant $label.",
            'planning'
        );
    }

    respond(['success' => true, 'message' => 'Planning mis à jour']);
}

/**
 * Get reference data for planning UI (users, horaires, modules)
 */
function admin_get_planning_refs()
{
    require_responsable();

    $users = Db::fetchAll(
        "SELECT u.id, u.nom, u.prenom, u.taux, u.role, u.type_contrat,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                GROUP_CONCAT(m.id ORDER BY um.is_principal DESC) AS module_ids,
                GROUP_CONCAT(m.code ORDER BY um.is_principal DESC) AS module_codes
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE u.is_active = 1
         GROUP BY u.id
         ORDER BY f.ordre, u.nom, u.prenom"
    );

    $horaires = Db::fetchAll(
        "SELECT id, code, nom, heure_debut, heure_fin, duree_effective, couleur
         FROM horaires_types WHERE is_active = 1 ORDER BY code"
    );

    $modules = Db::fetchAll(
        "SELECT id, code, nom, ordre FROM modules ORDER BY ordre"
    );

    $fonctions = Db::fetchAll(
        "SELECT id, code, nom, ordre FROM fonctions ORDER BY ordre"
    );

    respond([
        'success' => true,
        'users' => $users,
        'horaires' => $horaires,
        'modules' => $modules,
        'fonctions' => $fonctions,
    ]);
}

/**
 * Get planning stats: hours per user, coverage gaps, conflicts
 */
function admin_get_planning_stats()
{
    global $params;
    require_responsable();

    $mois = $params['mois'] ?? date('Y-m');
    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) respond(['success' => true, 'stats' => null]);

    $planningId = $planning['id'];

    // Hours per user
    $heuresParUser = Db::fetchAll(
        "SELECT pa.user_id, u.nom, u.prenom, u.taux,
                f.code AS fonction_code,
                COUNT(*) AS nb_jours,
                SUM(COALESCE(ht.duree_effective, 0)) AS total_heures,
                SUM(CASE WHEN pa.statut = 'present' THEN 1 ELSE 0 END) AS jours_presents,
                SUM(CASE WHEN pa.statut = 'absent' THEN 1 ELSE 0 END) AS jours_absents,
                SUM(CASE WHEN pa.statut = 'repos' THEN 1 ELSE 0 END) AS jours_repos
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE pa.planning_id = ?
         GROUP BY pa.user_id
         ORDER BY f.code, u.nom",
        [$planningId]
    );

    // Target hours per user (based on taux)
    $firstDay = $mois . '-01';
    $daysInMonth = (int) date('t', strtotime($firstDay));
    // Working days (Mon-Fri)
    $workDays = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dow = (int) date('N', strtotime("$mois-" . str_pad($d, 2, '0', STR_PAD_LEFT)));
        if ($dow <= 5) $workDays++;
    }

    foreach ($heuresParUser as &$row) {
        $targetHours = round($workDays * 8.4 * ($row['taux'] / 100), 1);
        $row['heures_cibles'] = $targetHours;
        $row['ecart'] = round($row['total_heures'] - $targetHours, 1);
    }
    unset($row);

    // Coverage per module per day
    $couverture = Db::fetchAll(
        "SELECT pa.date_jour, pa.module_id, m.code AS module_code,
                f.id AS fonction_id, f.code AS fonction_code,
                COUNT(*) AS nb_present
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE pa.planning_id = ? AND pa.statut = 'present'
         GROUP BY pa.date_jour, pa.module_id, f.id
         ORDER BY pa.date_jour, m.ordre",
        [$planningId]
    );

    // Besoins de couverture
    $besoins = Db::fetchAll(
        "SELECT bc.module_id, m.code AS module_code,
                bc.jour_semaine, bc.fonction_id, f.code AS fonction_code,
                bc.nb_requis
         FROM besoins_couverture bc
         JOIN modules m ON m.id = bc.module_id
         JOIN fonctions f ON f.id = bc.fonction_id"
    );

    // Find gaps: compare actual vs needed
    $gaps = [];
    $besoinsIndex = [];
    foreach ($besoins as $b) {
        $key = $b['module_id'] . '_' . $b['jour_semaine'] . '_' . $b['fonction_id'];
        $besoinsIndex[$key] = $b;
    }

    $couvertureIndex = [];
    foreach ($couverture as $c) {
        $key = $c['date_jour'] . '_' . ($c['module_id'] ?? '') . '_' . ($c['fonction_id'] ?? '');
        $couvertureIndex[$key] = $c['nb_present'];
    }

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = $mois . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        $dow = (int) date('N', strtotime($date));

        foreach ($besoins as $b) {
            if ((int)$b['jour_semaine'] !== $dow) continue;
            $actual = $couvertureIndex[$date . '_' . $b['module_id'] . '_' . $b['fonction_id']] ?? 0;
            $needed = (int) $b['nb_requis'];
            if ($actual < $needed) {
                $gaps[] = [
                    'date' => $date,
                    'module_code' => $b['module_code'],
                    'fonction_code' => $b['fonction_code'],
                    'requis' => $needed,
                    'present' => $actual,
                    'manque' => $needed - $actual,
                ];
            }
        }
    }

    // Summary totals
    $totals = Db::fetch(
        "SELECT COUNT(DISTINCT pa.user_id) AS nb_employes,
                COUNT(*) AS nb_assignations,
                SUM(COALESCE(ht.duree_effective, 0)) AS total_heures
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         WHERE pa.planning_id = ?",
        [$planningId]
    );

    respond([
        'success' => true,
        'stats' => [
            'totals' => $totals,
            'heures_par_user' => $heuresParUser,
            'gaps' => $gaps,
            'nb_gaps' => count($gaps),
            'jours_mois' => $daysInMonth,
            'jours_ouvrables' => $workDays,
        ],
    ]);
}

/**
 * Auto-generate planning based on besoins, taux, desirs, absences
 */
function admin_generate_planning()
{
    global $params;
    require_admin();
    $startTime = microtime(true);

    $mois = $params['mois'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) bad_request('Mois invalide');

    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) bad_request('Créez d\'abord le planning pour ce mois');
    if ($planning['statut'] === 'final') bad_request('Ce planning est déjà finalisé. Repassez-le en brouillon pour régénérer.');

    $planningId = $planning['id'];
    $moduleFilter = $params['module_id'] ?? null;
    $mode = $params['mode'] ?? 'local'; // local | hybrid | ai
    if (!in_array($mode, ['local', 'hybrid', 'ai'])) $mode = 'local';

    // Seed random for reproducible results (same month + same data = same planning)
    $seed = crc32($mois . $planningId);
    mt_srand($seed);
    srand($seed);

    // Load all config (IA + API keys)
    $cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
    $cfg = [];
    foreach ($cfgRows as $r) $cfg[$r['config_key']] = $r['config_value'];

    // Validate IA mode has API key configured
    if ($mode !== 'local') {
        $aiProvider = $cfg['ai_provider'] ?? 'gemini';
        $aiApiKey = ($aiProvider === 'gemini') ? ($cfg['gemini_api_key'] ?? '') : ($cfg['anthropic_api_key'] ?? '');
        $aiModel = ($aiProvider === 'gemini') ? ($cfg['gemini_model'] ?? 'gemini-2.5-flash') : ($cfg['anthropic_model'] ?? 'claude-haiku-4-5-20251001');
        if (empty($aiApiKey)) bad_request("Clé API non configurée pour $aiProvider. Allez dans Config IA > Clés API.");
    }

    $iaJoursOuvres      = (float) ($cfg['ia_jours_ouvres'] ?? 21.7);
    $iaHeuresJour       = (float) ($cfg['ia_heures_jour'] ?? 8.4);
    $iaConsecutifMax     = (int)   ($cfg['ia_consecutif_max'] ?? 5);
    $iaConsecutifMaxBes  = (int)   ($cfg['ia_consecutif_max_besoins'] ?? 6);
    $iaDirWeekendOff     = ($cfg['ia_direction_weekend_off'] ?? '1') === '1';
    $iaBonusPrincipal    = (int)   ($cfg['ia_bonus_principal'] ?? 10);
    $iaRandomMax         = (int)   ($cfg['ia_random_max'] ?? 3);
    $iaWeekendSkipProb   = (int)   ($cfg['ia_weekend_skip_prob'] ?? 66);
    $iaAdminShiftCode    = $cfg['ia_admin_shift_code'] ?? 'A6';
    $iaConsecutifMaxAS   = (int)   ($cfg['ia_consecutif_max_as'] ?? 3); // AS: max 3 jours consécutifs
    $iaConsecutifMaxNuit = (int)   ($cfg['ia_consecutif_max_nuit'] ?? 5); // Nuit: max 5 (LTr Suisse art. 17a)
    // ── Plafonds légaux LTr (Loi fédérale sur le travail) — JAMAIS dépassés ──
    // Art. 9 al. 1 let. b LTr : 50h/sem pour le personnel soignant/services.
    // Art. 21 LTr : jour de repos hebdomadaire → max 6 jours consécutifs.
    // Art. 17a LTr : 5 nuits consécutives max (déjà $iaConsecutifMaxNuit).
    // Art. 31 al. 2 LTr : 9h/jour pour travailleurs de nuit (non appliqué ici — via shifts).
    $iaLegalMaxHoursWeek     = (int)   ($cfg['ia_legal_max_hours_week']     ?? 50); // LTr art. 9 al. 1 let. b
    $iaLegalMaxConsecDays    = (int)   ($cfg['ia_legal_max_consec_days']    ?? 6);  // LTr art. 21
    $iaLegalMaxHoursDay      = (float) ($cfg['ia_legal_max_hours_day']      ?? 12); // OLT 2 art. 7 (santé)
    $iaLegalReposQuotidienH  = (float) ($cfg['ia_legal_repos_quotidien_h']  ?? 11); // LTr art. 15a al. 1
    // Limite contractuelle : taux + tolérance, plafonnée par le LTr.
    // 5h est un bon compromis : évite les heures sup abusives (conforme migration 080).
    $iaMaxOverTauxHoursWeek = (int) ($cfg['ia_max_over_taux_hours_week'] ?? 5);

    // Load reference data
    $users = Db::fetchAll(
        "SELECT u.id, u.nom, u.prenom, u.taux, u.role,
                f.code AS fonction_code, f.id AS fonction_id,
                um.module_id AS principal_module_id, um.is_principal
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         WHERE u.is_active = 1
         ORDER BY f.code, u.nom"
    );

    // All user-module assignments
    $userModules = Db::fetchAll("SELECT user_id, module_id FROM user_modules");
    $userModuleMap = [];
    foreach ($userModules as $um) {
        $userModuleMap[$um['user_id']][] = $um['module_id'];
    }

    $horaires = Db::fetchAll(
        "SELECT id, code, heure_debut, heure_fin, duree_effective, couleur
         FROM horaires_types WHERE is_active = 1 ORDER BY code"
    );

    $besoins = Db::fetchAll(
        "SELECT module_id, jour_semaine, fonction_id, nb_requis
         FROM besoins_couverture"
    );

    // Approved desirs for this month
    $desirs = Db::fetchAll(
        "SELECT user_id, date_souhaitee, type, horaire_type_id
         FROM desirs
         WHERE mois_cible = ? AND statut = 'valide'",
        [$mois]
    );
    $desirMap = []; // [userId][date] = ['type' => ..., 'horaire_type_id' => ...]
    foreach ($desirs as $d) {
        $desirMap[$d['user_id']][$d['date_souhaitee']] = [
            'type' => $d['type'],
            'horaire_type_id' => $d['horaire_type_id'],
        ];
    }

    // Approved absences overlapping this month
    $firstDay = $mois . '-01';
    $lastDay = date('Y-m-t', strtotime($firstDay));
    $absences = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin
         FROM absences
         WHERE statut = 'valide' AND date_debut <= ? AND date_fin >= ?",
        [$lastDay, $firstDay]
    );
    $absenceMap = [];
    foreach ($absences as $a) {
        $start = max(strtotime($firstDay), strtotime($a['date_debut']));
        $end = min(strtotime($lastDay), strtotime($a['date_fin']));
        for ($t = $start; $t <= $end; $t += 86400) {
            $absenceMap[$a['user_id']][date('Y-m-d', $t)] = true;
        }
    }

    // ── Formations chevauchant le mois ─────────────────────────
    // Les jours de formation bloquent la génération et seront pré-assignés
    // avec statut='formation'. Heures de formation comptent comme heures travaillées.
    $formationsMois = Db::fetchAll(
        "SELECT p.user_id,
                COALESCE(fs.date_debut, f.date_debut) AS date_debut,
                COALESCE(fs.date_fin,   f.date_fin,   fs.date_debut, f.date_debut) AS date_fin,
                f.titre, f.duree_heures,
                f.id AS formation_id
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         LEFT JOIN formation_sessions fs ON fs.id = p.session_id
         WHERE p.statut IN ('inscrit','present','valide')
           AND COALESCE(fs.date_debut, f.date_debut) <= ?
           AND COALESCE(fs.date_fin, f.date_fin, fs.date_debut, f.date_debut) >= ?",
        [$lastDay, $firstDay]
    );
    $formationMap = []; // [user_id][date] = ['titre'=>..., 'heures'=>..., 'formation_id'=>...]
    foreach ($formationsMois as $f) {
        $start = max(strtotime($firstDay), strtotime($f['date_debut']));
        $end = min(strtotime($lastDay), strtotime($f['date_fin']));
        $nbDays = max(1, (int) round(($end - $start) / 86400) + 1);
        $heuresParJour = $f['duree_heures'] > 0 ? round($f['duree_heures'] / $nbDays, 2) : 7;
        for ($t = $start; $t <= $end; $t += 86400) {
            $formationMap[$f['user_id']][date('Y-m-d', $t)] = [
                'titre' => $f['titre'],
                'heures' => $heuresParJour,
                'formation_id' => $f['formation_id'],
            ];
        }
    }

    // ── Index horaires by code for easy lookup ──
    $horaireByCode = [];
    $horaireById = [];
    foreach ($horaires as $h) {
        $horaireByCode[$h['code']] = $h;
        $horaireById[$h['id']] = $h;
    }

    // ── EMS planning config (universal) ──
    $coverageStart   = $cfg['planning_coverage_start'] ?? '07:00';   // début couverture
    $coverageEnd     = $cfg['planning_coverage_end']   ?? '20:30';   // fin couverture
    $asPerEtage      = (int) ($cfg['planning_as_per_etage'] ?? 2);   // nb AS par étage
    $shiftPairing    = ($cfg['planning_shift_pairing'] ?? '1') === '1'; // paires matin/soir
    $nightThreshold  = (int) ($cfg['planning_night_threshold'] ?? 20);  // heure début nuit
    $eveningThreshold = (int) ($cfg['planning_evening_threshold'] ?? 12); // heure début soir
    $fullDayMinHours = (float) ($cfg['planning_fullday_min_hours'] ?? 10); // durée min journée complète
    $excludeShiftCodes = array_filter(array_map('trim', explode(',', $cfg['planning_exclude_shifts'] ?? 'PIQUET'))); // codes à exclure

    $covStartH = (int) substr($coverageStart, 0, 2);
    $covStartM = (int) substr($coverageStart, 3, 2);
    $covEndH   = (int) substr($coverageEnd, 0, 2);
    $covEndM   = (int) substr($coverageEnd, 3, 2);
    $covStartMin = $covStartH * 60 + $covStartM;
    $covEndMin   = $covEndH * 60 + $covEndM;

    // ── Auto-classify horaires based on actual times (no hardcoded shift codes) ──
    $fullDayShifts = [];   // covers most of coverage range (≥ fullDayMinHours)
    $morningShifts = [];   // starts early, ends before evening
    $eveningShifts = [];   // starts afternoon/evening
    $nightShifts = [];     // starts after nightThreshold
    $adminShift = null;

    foreach ($horaires as $h) {
        if (in_array($h['code'], $excludeShiftCodes)) continue;
        if ($h['code'] === $iaAdminShiftCode) { $adminShift = $h; continue; }

        $deb = (int) substr($h['heure_debut'], 0, 2);
        $eff = (float) $h['duree_effective'];

        if ($deb >= $nightThreshold) { $nightShifts[] = $h; continue; }
        if ($deb <= ($covStartH + 1) && $eff >= $fullDayMinHours) { $fullDayShifts[] = $h; continue; }
        if ($deb >= $eveningThreshold) { $eveningShifts[] = $h; continue; }
        $morningShifts[] = $h;
    }
    if (!$adminShift && !empty($morningShifts)) $adminShift = $morningShifts[0];

    // ── AS-specific shifts: auto-classified from coverage range (no hardcoded codes) ──
    $asMorningShifts = []; // starts early, ends before coverage end
    $asFullDayShifts = []; // covers entire coverage range alone
    $asEveningShifts = []; // starts afternoon, ends at coverage end
    foreach ($horaires as $h) {
        if (in_array($h['code'], $excludeShiftCodes)) continue;
        if ($h['code'] === $iaAdminShiftCode) continue;
        $deb = (int) substr($h['heure_debut'], 0, 2);
        $debM = (int) substr($h['heure_debut'], 3, 2);
        $fin = (int) substr($h['heure_fin'], 0, 2);
        $finM = (int) substr($h['heure_fin'], 3, 2);
        $debMin = $deb * 60 + $debM;
        $finMin = $fin * 60 + $finM;
        $eff = (float) $h['duree_effective'];

        if ($deb >= $nightThreshold) continue; // night shifts excluded from AS day coverage

        // Full-day: starts near coverage start, ends near coverage end, long duration
        if ($debMin <= $covStartMin + 60 && $finMin >= $covEndMin - 30 && $eff >= $fullDayMinHours) {
            $asFullDayShifts[] = $h;
        }
        // Morning: starts early, ends before mid-afternoon
        elseif ($debMin <= $covStartMin + 60 && $finMin < $covEndMin - 60) {
            $asMorningShifts[] = $h;
        }
        // Evening: starts afternoon, ends near coverage end
        elseif ($debMin >= $covStartMin + 300 && $finMin >= $covEndMin - 60) {
            $asEveningShifts[] = $h;
        }
    }

    // ── Load étages per module ──
    $etagesPerModule = [];
    $etagesRows = Db::fetchAll("SELECT module_id, COUNT(*) as nb FROM etages GROUP BY module_id");
    foreach ($etagesRows as $e) $etagesPerModule[$e['module_id']] = (int) $e['nb'];

    // ── Load full étages + groupes per module (for slot→groupe_id mapping) ──
    $allGroupesDB = Db::fetchAll("SELECT id, etage_id, code, nom, ordre FROM groupes ORDER BY etage_id, ordre");
    $groupesByEtageDB = [];
    foreach ($allGroupesDB as $g) $groupesByEtageDB[$g['etage_id']][] = $g;
    $allEtagesDB = Db::fetchAll("SELECT id, module_id, code, nom, ordre FROM etages ORDER BY module_id, ordre");
    $etagesByModuleDB = [];
    foreach ($allEtagesDB as $e) {
        $e['groupes'] = $groupesByEtageDB[$e['id']] ?? [];
        $etagesByModuleDB[$e['module_id']][] = $e;
    }
    // AS slot map: module_id → [ slot_index → groupe_id (or null) ]
    // 2 slots per étage: one per groupe if groupes exist, otherwise null
    $moduleSlotsAS = [];
    foreach ($etagesByModuleDB as $modId => $etages) {
        $slots = [];
        foreach ($etages as $etage) {
            $grps = $etage['groupes'];
            if (count($grps) >= 2) {
                $slots[] = $grps[0]['id'];
                $slots[] = $grps[1]['id'];
            } elseif (count($grps) === 1) {
                $slots[] = $grps[0]['id'];
                $slots[] = null;
            } else {
                $slots[] = null;
                $slots[] = null;
            }
        }
        if (empty($slots)) {
            $slots = [null, null];
        }
        $moduleSlotsAS[$modId] = $slots;
    }
    // Slot counter per module per date (tracks next slot index for AS in Pass 1.5 and Pass 2)
    $moduleASSlotIdx = [];

    // ── Fonction IDs by code ──
    $fonctionByCode = [];
    $fonctionRows = Db::fetchAll("SELECT id, code FROM fonctions");
    foreach ($fonctionRows as $f) $fonctionByCode[$f['code']] = $f['id'];

    // Build besoins index: [module_id][dow][fonction_id] = nb
    $besoinsIdx = [];
    foreach ($besoins as $b) {
        $besoinsIdx[$b['module_id']][(int)$b['jour_semaine']][$b['fonction_id']] = (int) $b['nb_requis'];
    }

    // ── Ensure minimum besoins (fallback if besoins_couverture is empty) ──
    $fonctionAS   = $fonctionByCode['AS']   ?? null;
    $fonctionINF  = $fonctionByCode['INF']  ?? null;
    $fonctionASSC = $fonctionByCode['ASSC'] ?? null;

    $dayModules = Db::fetchAll("SELECT id, code FROM modules WHERE code NOT IN ('NUIT','POOL') ORDER BY ordre");
    $hasBesoins = !empty($besoins);

    foreach ($dayModules as $mod) {
        $nbEtages = $etagesPerModule[$mod['id']] ?? 1;
        for ($dow = 1; $dow <= 7; $dow++) {
            // Use DB besoins if they exist, otherwise apply defaults
            if (!$hasBesoins || empty($besoinsIdx[$mod['id']][$dow])) {
                if ($fonctionAS)   $besoinsIdx[$mod['id']][$dow][$fonctionAS]   = max($besoinsIdx[$mod['id']][$dow][$fonctionAS] ?? 0, $asPerEtage * $nbEtages);
                if ($fonctionINF)  $besoinsIdx[$mod['id']][$dow][$fonctionINF]  = max($besoinsIdx[$mod['id']][$dow][$fonctionINF] ?? 0, 1);
                if ($fonctionASSC) $besoinsIdx[$mod['id']][$dow][$fonctionASSC] = max($besoinsIdx[$mod['id']][$dow][$fonctionASSC] ?? 0, 1);
            }
        }
    }

    // Module for night staff
    $nightModuleId = Db::getOne("SELECT id FROM modules WHERE code = 'NUIT'");
    $poolModuleId = Db::getOne("SELECT id FROM modules WHERE code = 'POOL'");

    // All modules (for auto-assignment)
    $allModules = Db::fetchAll("SELECT id FROM modules WHERE code NOT IN ('NUIT','POOL') ORDER BY ordre");
    $allModuleIds = array_column($allModules, 'id');

    // ── Auto-assign users without module ──
    if (!empty($allModuleIds)) {
        $idx = 0;
        foreach ($users as &$u) {
            if (empty($userModuleMap[$u['id']])) {
                $assignedMod = $allModuleIds[$idx % count($allModuleIds)];
                $userModuleMap[$u['id']][] = $assignedMod;
                $u['principal_module_id'] = $assignedMod;
                $idx++;
            } elseif (!$u['principal_module_id']) {
                $u['principal_module_id'] = $userModuleMap[$u['id']][0];
            }
        }
        unset($u);
    }

    // ── Load structured rules for local enforcement ──
    $structuredRules = Db::fetchAll(
        "SELECT id, rule_type, rule_params, target_mode, target_fonction_code, importance
         FROM ia_human_rules
         WHERE actif = 1 AND rule_type IS NOT NULL"
    );
    $ruleUserRows = Db::fetchAll("SELECT rule_id, user_id FROM ia_rule_users");
    $ruleUserMap = [];
    foreach ($ruleUserRows as $ru) {
        $ruleUserMap[$ru['rule_id']][$ru['user_id']] = true;
    }
    foreach ($structuredRules as &$sr) {
        $sr['params'] = json_decode($sr['rule_params'], true) ?: [];
    }
    unset($sr);

    // ── Delete existing assignations (regenerate) ──
    if ($moduleFilter) {
        Db::exec("DELETE FROM planning_assignations WHERE planning_id = ? AND module_id = ?", [$planningId, $moduleFilter]);
    } else {
        Db::exec("DELETE FROM planning_assignations WHERE planning_id = ?", [$planningId]);
    }

    $daysInMonth = (int) date('t', strtotime($firstDay));
    $pdo = Db::connect();
    $stmtInsert = $pdo->prepare(
        "INSERT IGNORE INTO planning_assignations
            (id, planning_id, user_id, date_jour, horaire_type_id, module_id, groupe_id, statut, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    // ─────────────────────────────────────────────────────────────
    // PRECOMPUTE: date metadata (massively reduces strtotime/date calls)
    // $dateCache[$d] = ['date' => 'Y-m-d', 'dow' => N(1..7), 'wk' => ISO week]
    // $dateByStr[$dateStr] = index (1..daysInMonth) for reverse lookup
    // ─────────────────────────────────────────────────────────────
    $dateCache = [];
    $dateByStr = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $ds  = $mois . '-' . ($d < 10 ? "0$d" : $d);
        $ts  = strtotime($ds);
        $dateCache[$d] = [
            'date' => $ds,
            'dow'  => (int) date('N', $ts),
            'wk'   => (int) date('W', $ts),
        ];
        $dateByStr[$ds] = $d;
    }

    // Helper: resolve groupe_id for an AS slot in a module
    $getGroupeId = function ($fonctionCode, $modId, $slotIndex) use (&$moduleSlotsAS) {
        if ($fonctionCode !== 'AS') return null;
        if (empty($moduleSlotsAS[$modId])) return null;
        $slots = $moduleSlotsAS[$modId];
        return $slots[$slotIndex % count($slots)];
    };

    // Track hours assigned per user this month
    $userHours = [];
    $userDays = [];     // [userId][date] = true
    $userShifts = [];   // [userId][date] = shift code (for rotation tracking)
    $userShiftTimes = []; // [userId][date] = ['start_min'=>int, 'end_min_abs'=>int] (en min depuis minuit du jour ; end_min_abs peut dépasser 1440 si shift traverse minuit)
    $userWeekHours = []; // [userId][weekNum] = hours worked that week (for AS weekly cap)
    $assigned = 0;
    $conflicts = [];

    // ── Pré-assignations FORMATION (priorité absolue) ──────────
    // Pour chaque user inscrit à une formation chevauchant le mois, on
    // crée une assignation statut='formation' qui bloque la cellule et
    // dont les heures comptent comme heures travaillées.
    $userById = [];
    foreach ($users as $u) $userById[$u['id']] = $u;

    foreach ($formationMap as $userId => $byDate) {
        if (!isset($userById[$userId])) continue;
        $u = $userById[$userId];
        // Filtrage par module si moduleFilter actif : on ne pré-assigne
        // les formations que si l'user appartient au module concerné
        if ($moduleFilter && empty(array_intersect([$moduleFilter], $userModuleMap[$userId] ?? []))) continue;

        $modPrincipal = $u['principal_module_id'] ?? ($userModuleMap[$userId][0] ?? null);
        foreach ($byDate as $date => $info) {
            if (!isset($dateByStr[$date])) continue;
            $note = 'Formation : ' . mb_substr($info['titre'], 0, 80);
            $stmtInsert->execute([
                Uuid::v4(), $planningId, $userId, $date,
                null, $modPrincipal, null, 'formation', $note
            ]);
            $userDays[$userId][$date] = true;
            $userHours[$userId] = ($userHours[$userId] ?? 0) + (float) $info['heures'];
            $wk = $dateCache[$dateByStr[$date]]['wk'];
            $userWeekHours[$userId][$wk] = ($userWeekHours[$userId][$wk] ?? 0) + (float) $info['heures'];
            $assigned++;
        }
    }

    // ── Helper: parse heure_debut/heure_fin d'un shift en minutes absolues ──
    // Si shift traverse minuit (fin<debut), end_min_abs > 1440.
    $parseShiftTimes = function ($shift) {
        $startMin = (int) substr($shift['heure_debut'], 0, 2) * 60 + (int) substr($shift['heure_debut'], 3, 2);
        $endMin   = (int) substr($shift['heure_fin'], 0, 2) * 60 + (int) substr($shift['heure_fin'], 3, 2);
        if ($endMin <= $startMin) $endMin += 1440; // shift nuit passant minuit
        return ['start_min' => $startMin, 'end_min_abs' => $endMin];
    };

    // ── Helper: check LTr art. 15a — 11h de repos entre deux journées ──
    // Retourne true si le shift proposé respecte le repos minimum après le shift de J-1.
    $checkRestBetween = function ($userId, $date, $shift) use (&$userShiftTimes, $mois, $iaLegalReposQuotidienH, $parseShiftTimes) {
        $dNum = (int) substr($date, -2);
        if ($dNum <= 1) return true; // premier jour du mois : pas de J-1 connu dans ce planning
        $prevDate = $mois . '-' . ($dNum - 1 < 10 ? '0' . ($dNum - 1) : ($dNum - 1));
        $prev = $userShiftTimes[$userId][$prevDate] ?? null;
        if (!$prev) return true; // pas de shift la veille
        $t = $parseShiftTimes($shift);
        // fin shift J-1 (en minutes depuis minuit J-1) ; début shift J (en minutes depuis minuit J)
        // gap = (24*60 - prev['end_min_abs']) + t['start_min'] si end_min_abs <= 1440
        //     = t['start_min'] - (prev['end_min_abs'] - 1440) si end_min_abs > 1440 (shift nuit)
        if ($prev['end_min_abs'] <= 1440) {
            $gap = (1440 - $prev['end_min_abs']) + $t['start_min'];
        } else {
            $gap = $t['start_min'] - ($prev['end_min_abs'] - 1440);
        }
        return $gap >= $iaLegalReposQuotidienH * 60;
    };

    // ── Helper: get ISO week number for a date (cached) ──
    $getWeekNum = function ($date) use (&$dateByStr, &$dateCache) {
        if (isset($dateByStr[$date])) return $dateCache[$dateByStr[$date]]['wk'];
        return (int) date('W', strtotime($date));
    };

    // ── PRECOMPUTE: per-user monthly/weekly targets (avoid recomputing in inner loops) ──
    $nbWeeksInMonth = $daysInMonth / 7;
    $userTargetMonthHours = [];
    $userWeeklyTargetHours = [];
    $userWeeklyDaysTarget = [];
    // Plafond HEBDO absolu (conformité LTr) : min(taux×heures + tolérance contractuelle, plafond légal)
    $userWeeklyHardCap = [];
    foreach ($users as $u) {
        $tauxRatio = $u['taux'] / 100;
        $monthH = round($iaJoursOuvres * $iaHeuresJour * $tauxRatio);
        $weeklyTarget = round($monthH / $nbWeeksInMonth, 1);
        $userTargetMonthHours[$u['id']] = $monthH;
        $userWeeklyTargetHours[$u['id']] = $weeklyTarget;
        $userWeeklyDaysTarget[$u['id']] = round(round($iaJoursOuvres * $tauxRatio) / $nbWeeksInMonth, 1);
        // Contractuel + tolérance, plafonné par LTr (50h).
        // Pour un 100% (~42h cible), contractuel+tol = 52, bornée à 50 → 50h.
        // Pour un 50% (~21h cible), contractuel+tol = 31, bornée à 50 → 31h (la tolérance contractuelle prime).
        $userWeeklyHardCap[$u['id']] = min(
            $weeklyTarget + $iaMaxOverTauxHoursWeek,
            (float) $iaLegalMaxHoursWeek
        );
    }
    $getWeeklyTarget = function ($user) use (&$userWeeklyTargetHours) {
        return $userWeeklyTargetHours[$user['id']] ?? 0;
    };

    // ── PRECOMPUTE: which structured rules can apply to each user (static filters) ──
    // Module rules are kept and still filtered at runtime by the current module context.
    $userApplicableRules = [];
    foreach ($users as $u) {
        $fCode = $u['fonction_code'] ?? '';
        $uid = $u['id'];
        $applicable = [];
        foreach ($structuredRules as $rule) {
            $tm = $rule['target_mode'];
            if ($tm === 'all'
                || ($tm === 'fonction' && $fCode === $rule['target_fonction_code'])
                || ($tm === 'users' && isset($ruleUserMap[$rule['id']][$uid]))
                || $tm === 'module'
            ) {
                $applicable[] = $rule;
            }
        }
        $userApplicableRules[$uid] = $applicable;
    }

    // ── Coverage shift patterns ──
    // For 2 positions per day, we alternate patterns to ensure 7h-20h30 coverage:
    // Pattern A: 1×D3 (7h-20h30) + 1×morning (A1/A2 7h-15/16h) → overlap morning, 1 covers evening
    // Pattern B: 1×morning (A1/A2) + 1×evening (S3/C2 12/13h-20h30) → split, overlap midday
    // Pattern C: 2×D3 (both full day) → max coverage but expensive in hours
    // We rotate patterns to balance hours across employees

    /**
     * Pick a shift for coverage-aware EMS scheduling.
     * $slotIndex: 0-based index of this person in the day's slots for this function
     * $nbTotal: total people needed for this function today
     * $dayIndex: day of month (for rotation)
     * $fonctionCode: AS, INF, ASSC, etc.
     */
    $pickShift = function ($modId, $slotIndex, $nbTotal, $dayIndex, $userObj, $fonctionCode = null)
        use ($nightModuleId, $fullDayShifts, $morningShifts, $eveningShifts, $nightShifts, $adminShift, $iaDirWeekendOff,
             $asMorningShifts, $asFullDayShifts, $asEveningShifts)
    {
        // Night module → always night shift
        if ($modId === $nightModuleId) {
            return !empty($nightShifts) ? $nightShifts[array_rand($nightShifts)] : null;
        }

        // Direction/Responsable → admin shift
        if (in_array($userObj['role'], ['direction', 'responsable']) && $adminShift) {
            return $adminShift;
        }

        // ── AS: couverture 7h-20h30 par paire d'étage ──
        // Slots are paired per étage: (0,1) = étage 1, (2,3) = étage 2, etc.
        // Each pair must cover 7h-20h30: one morning + one evening, or D3 full day
        if ($fonctionCode === 'AS') {
            $isFirstInPair = ($slotIndex % 2 === 0);
            $pairIndex = intdiv($slotIndex, 2);
            $pattern = ($dayIndex + $pairIndex) % 3; // rotate pattern per pair per day

            if ($isFirstInPair) {
                // First AS in pair: morning or full-day shift
                if ($pattern === 0 && !empty($asFullDayShifts)) {
                    // Pattern A: D3 full day (7h-20h30, 12h)
                    return $asFullDayShifts[array_rand($asFullDayShifts)];
                } elseif ($pattern === 1 && !empty($asMorningShifts)) {
                    // Pattern B: D1 (7h-15h30) — needs evening partner
                    return $asMorningShifts[0]; // D1
                } else {
                    // Pattern C: D4 (7h-19h) — needs evening partner
                    $d4 = array_filter($asMorningShifts, fn($h) => $h['code'] === 'D4');
                    return !empty($d4) ? array_values($d4)[0] : ($asMorningShifts[0] ?? $asFullDayShifts[0] ?? null);
                }
            } else {
                // Second AS in pair: complement to cover 7h-20h30
                if ($pattern === 0) {
                    // First got D3 (full day) → second can be morning reinforcement (D1 or D4)
                    return !empty($asMorningShifts) ? $asMorningShifts[array_rand($asMorningShifts)] : $asFullDayShifts[0];
                } else {
                    // First got D1 or D4 → second must be evening (S3 or S4)
                    if ($pattern === 1 && !empty($asEveningShifts)) {
                        // Prefer S3 (13h-20h30) for better overlap with D1 (ends 15h30)
                        $s3 = array_filter($asEveningShifts, fn($h) => $h['code'] === 'S3');
                        return !empty($s3) ? array_values($s3)[0] : $asEveningShifts[0];
                    } else {
                        // S4 (14h-20h30) pairs well with D4 (ends 19h — good overlap)
                        $s4 = array_filter($asEveningShifts, fn($h) => $h['code'] === 'S4');
                        return !empty($s4) ? array_values($s4)[0] : $asEveningShifts[0];
                    }
                }
            }
        }

        // ── INF / ASSC: coverage logic ──
        if ($nbTotal >= 2) {
            $pattern = $dayIndex % 3;
            if ($pattern === 0) {
                return ($slotIndex === 0 && !empty($fullDayShifts))
                    ? $fullDayShifts[array_rand($fullDayShifts)]
                    : (!empty($morningShifts) ? $morningShifts[array_rand($morningShifts)] : $fullDayShifts[0]);
            } elseif ($pattern === 1) {
                return ($slotIndex === 0)
                    ? (!empty($morningShifts) ? $morningShifts[array_rand($morningShifts)] : $fullDayShifts[0])
                    : (!empty($eveningShifts) ? $eveningShifts[array_rand($eveningShifts)] : $fullDayShifts[0]);
            } else {
                return ($slotIndex === 0 && !empty($fullDayShifts))
                    ? $fullDayShifts[array_rand($fullDayShifts)]
                    : (!empty($eveningShifts) ? $eveningShifts[array_rand($eveningShifts)] : $fullDayShifts[0]);
            }
        }

        // Single person (INF or ASSC seul par module) → full day for max coverage
        return !empty($fullDayShifts) ? $fullDayShifts[array_rand($fullDayShifts)] : $morningShifts[0];
    };

    // ── Helper: check consecutive days (cached, no strtotime) ──
    // Days before the 1st of month aren't tracked in $userDays anyway, so stop at day 1.
    $getConsecutive = function ($userId, $date) use (&$userDays, $mois) {
        $dNum = (int) substr($date, -2);
        $count = 0;
        for ($back = 1; $back <= 7; $back++) {
            $prev = $dNum - $back;
            if ($prev < 1) break;
            $prevDate = $mois . '-' . ($prev < 10 ? "0$prev" : $prev);
            if (isset($userDays[$userId][$prevDate])) $count++;
            else break;
        }
        return $count;
    };

    // ── Helper: check if user is eligible ──
    // Bloque aussi les jours de formation (les heures sont déjà pré-comptabilisées)
    $isEligible = function ($u, $date) use (&$absenceMap, &$formationMap, &$desirMap, &$userDays) {
        if (isset($absenceMap[$u['id']][$date])) return false;
        if (isset($formationMap[$u['id']][$date])) return false;
        if (isset($desirMap[$u['id']][$date]) && $desirMap[$u['id']][$date]['type'] === 'jour_off') return false;
        if (isset($userDays[$u['id']][$date])) return false;
        return true;
    };

    // ── Helper: get desired shift for a user on a date (from horaire_special desirs) ──
    $getDesiredShift = function ($u, $date) use (&$desirMap, &$horaireById) {
        if (!isset($desirMap[$u['id']][$date])) return null;
        $d = $desirMap[$u['id']][$date];
        if ($d['type'] !== 'horaire_special' || empty($d['horaire_type_id'])) return null;
        return $horaireById[$d['horaire_type_id']] ?? null;
    };

    // ── Helper: get forced shift codes for a user (from shift_only rules) ──
    $getForcedShifts = function ($u, $modId = null) use (&$structuredRules, &$ruleUserMap) {
        foreach ($structuredRules as $rule) {
            if ($rule['rule_type'] !== 'shift_only' && $rule['rule_type'] !== 'user_schedule') continue;
            if ($rule['target_mode'] === 'all') { /* applies */ }
            elseif ($rule['target_mode'] === 'module') {
                $targetModIds = $rule['params']['target_module_ids'] ?? [];
                if (empty($targetModIds) || ($modId && !in_array($modId, $targetModIds))) continue;
            }
            elseif ($rule['target_mode'] === 'fonction' && ($u['fonction_code'] ?? '') === $rule['target_fonction_code']) { /* applies */ }
            elseif ($rule['target_mode'] === 'users' && isset($ruleUserMap[$rule['id']][$u['id']])) { /* applies */ }
            else continue;
            return $rule['params']['shift_codes'] ?? [];
        }
        return [];
    };

    // ── Helper: resolve shift for user respecting shift_only rules ──
    $resolveShift = function ($u, $shift, $modId, $date) use (&$horaireByCode, $getForcedShifts, $nightModuleId, &$nightShifts) {
        $forced = $getForcedShifts($u, $modId);
        if (empty($forced)) return $shift; // no constraint
        if ($shift && in_array($shift['code'], $forced)) return $shift; // already ok

        // Safety: never assign night shifts to non-night module employees
        $isNightModule = ($modId === $nightModuleId);
        $nightCodes = array_map(fn($h) => $h['code'], $nightShifts);

        // Shift was rejected — use the forced shift instead
        foreach ($forced as $code) {
            // Block night shifts for non-night employees
            if (!$isNightModule && in_array($code, $nightCodes)) continue;
            if (isset($horaireByCode[$code])) return $horaireByCode[$code];
        }
        return null; // no valid forced shift found
    };

    // ── Helper: check structured rules ──
    // $shiftCode can be null for early module-only checks (before shift is picked)
    // Uses $userApplicableRules for static pre-filter (rules by 'all'/'fonction'/'users' already matched).
    // Module-scoped rules are still checked at runtime.
    $nightCodes = array_map(fn($h) => $h['code'], $nightShifts);
    $checkRules = function ($u, $shiftCode, $modId, $date) use (&$userApplicableRules, &$userDays, $nightModuleId, &$nightCodes, &$dateByStr, &$dateCache) {
        $isNightModule = ($modId === $nightModuleId);
        $dow = isset($dateByStr[$date]) ? $dateCache[$dateByStr[$date]]['dow'] : (int) date('N', strtotime($date));
        $rules = $userApplicableRules[$u['id']] ?? [];

        foreach ($rules as $rule) {
            // Only module rules still need runtime filtering (module-specific targeting)
            if ($rule['target_mode'] === 'module') {
                $targetModIds = $rule['params']['target_module_ids'] ?? [];
                if (empty($targetModIds) || !in_array($modId, $targetModIds)) continue;
            }

            $p = $rule['params'];

            switch ($rule['rule_type']) {
                case 'user_schedule':
                    // Days restriction
                    if (!empty($p['days']) && !in_array($dow, $p['days'])) {
                        return false;
                    }
                    // Shift allowed (if specified)
                    if ($shiftCode !== null && !empty($p['shift_codes'])) {
                        if (!$isNightModule) {
                            $onlyNight = !array_diff($p['shift_codes'], $nightCodes);
                            if ($onlyNight) break;
                        }
                        if (!in_array($shiftCode, $p['shift_codes'])) return false;
                    }
                    // Shift excluded
                    if ($shiftCode !== null && !empty($p['exclude_shift_codes'])) {
                        if (in_array($shiftCode, $p['exclude_shift_codes'])) return false;
                    }
                    break;
                case 'shift_only':
                    if ($shiftCode !== null && !empty($p['shift_codes'])) {
                        // If rule only contains night shifts but employee is not in night module, skip this rule
                        if (!$isNightModule) {
                            $onlyNight = !array_diff($p['shift_codes'], $nightCodes);
                            if ($onlyNight) break; // ignore this rule for day modules
                        }
                        if (!in_array($shiftCode, $p['shift_codes'])) return false;
                    }
                    break;
                case 'shift_exclude':
                    if ($shiftCode !== null && !empty($p['shift_codes']) && in_array($shiftCode, $p['shift_codes'])) {
                        return false;
                    }
                    break;
                case 'module_only':
                    if (!empty($p['module_ids']) && !in_array($modId, $p['module_ids'])) {
                        return false;
                    }
                    break;
                case 'module_exclude':
                    if (!empty($p['module_ids']) && in_array($modId, $p['module_ids'])) {
                        return false;
                    }
                    break;
                case 'days_only':
                    if (!empty($p['days']) && !in_array($dow, $p['days'])) {
                        return false;
                    }
                    break;
                case 'no_weekend':
                    if ($dow >= 6) return false;
                    break;
                case 'max_days_week':
                    $maxDays = (int) ($p['max_days'] ?? 5);
                    // Count days assigned this ISO week
                    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                    $count = 0;
                    for ($i = 0; $i < 7; $i++) {
                        $wd = date('Y-m-d', strtotime("$weekStart +$i days"));
                        if (isset($userDays[$u['id']][$wd])) $count++;
                    }
                    if ($count >= $maxDays) return false;
                    break;
            }
        }
        return true;
    };

    // ── Target days per week for each user (use precomputed cache) ──
    $getUserWeeklyDaysTarget = function ($u) use (&$userWeeklyDaysTarget) {
        return $userWeeklyDaysTarget[$u['id']] ?? 0;
    };
    $userWeekDays = []; // [userId][weekNum] = count of days worked

    // ── Begin transaction: massive speedup for batched INSERTs ──
    $inTx = false;
    if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $inTx = true; }

    // ── PASS 1: Fill besoins_couverture (required positions) ──
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dm = $dateCache[$d];
        $date = $dm['date'];
        $dow = $dm['dow']; // 1=Mon 7=Sun
        $wk = $dm['wk'];

        // Shuffle module order each day to avoid first-module bias
        $moduleKeys = array_keys($besoinsIdx);
        shuffle($moduleKeys);
        $besoinsShuffled = [];
        foreach ($moduleKeys as $mk) $besoinsShuffled[$mk] = $besoinsIdx[$mk];

        foreach ($besoinsShuffled as $modId => $dows) {
            if ($moduleFilter && $modId !== $moduleFilter) continue;
            if (!isset($dows[$dow])) continue;

            foreach ($dows[$dow] as $fonctionId => $nbReqd) {
                // Find eligible users for this module + fonction
                $candidates = [];
                foreach ($users as $u) {
                    if ($u['fonction_id'] !== $fonctionId) continue;
                    $uMods = $userModuleMap[$u['id']] ?? [];
                    if (!in_array($modId, $uMods) && !in_array($poolModuleId, $uMods)) continue;
                    if (!$isEligible($u, $date)) continue;
                    if (!$checkRules($u, null, $modId, $date)) continue; // early module/weekend/max_days check

                    // Skip if already over monthly target (+10% tolerance for coverage)
                    $targetMonthHours = $userTargetMonthHours[$u['id']];
                    $currentHours = $userHours[$u['id']] ?? 0;
                    if ($currentHours >= $targetMonthHours * 1.1) continue;

                    // Score: hours deficit + module preference + randomness
                    $deficit = $targetMonthHours - $currentHours;
                    $isPrincipal = ($u['principal_module_id'] === $modId) ? $iaBonusPrincipal : 0;

                    // Part-time users get a bonus to ensure they get assigned regularly
                    $tauxBonus = ($u['taux'] < 100) ? 5 : 0;

                    // AS: boost score based on weekly hours deficit (ensures weekly balance)
                    $weeklyBonus = 0;
                    if ($u['fonction_code'] === 'AS') {
                        $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                        $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                        $weeklyBonus = round($weeklyTarget - $currentWeekHours);
                    }

                    $candidates[] = [
                        'user' => $u,
                        'score' => $deficit + $isPrincipal + $tauxBonus + $weeklyBonus + rand(0, $iaRandomMax),
                    ];
                }

                usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

                $assignedForSlot = 0;
                foreach ($candidates as $c) {
                    if ($assignedForSlot >= $nbReqd) break;
                    $u = $c['user'];

                    // Consecutive days check: AS max 3, Nuit max 5 (LTr 17a), autres max iaConsecutifMaxBes.
                    // Plafond absolu LTr art. 21 : jamais > 6 jours consécutifs (tous profils confondus).
                    $isNightModule = ($modId === $nightModuleId);
                    $maxConsec = ($u['fonction_code'] === 'AS') ? $iaConsecutifMaxAS : ($isNightModule ? $iaConsecutifMaxNuit : $iaConsecutifMaxBes);
                    $maxConsec = min($maxConsec, $iaLegalMaxConsecDays);
                    if ($getConsecutive($u['id'], $date) >= $maxConsec) continue;

                    // Even weekly distribution: limit days/week based on taux
                    // (relaxed: +2 days tolerance for coverage pass)
                    $weeklyDaysTarget = $userWeeklyDaysTarget[$u['id']];
                    $currentWeekDays = $userWeekDays[$u['id']][$wk] ?? 0;
                    if ($currentWeekDays >= $weeklyDaysTarget + 2) continue;

                    // AS weekly hours cap: don't exceed weekly target (+5h tolerance)
                    if ($u['fonction_code'] === 'AS') {
                        $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                        $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                        if ($currentWeekHours >= $weeklyTarget + 5) continue;
                    }

                    // Pick shift: approved desir = hard constraint (always respected)
                    $desiredShift = $getDesiredShift($u, $date);
                    $isDesir = (bool) $desiredShift;
                    if ($desiredShift) {
                        $shift = $desiredShift;
                    } else {
                        $shift = $pickShift($modId, $assignedForSlot, $nbReqd, $d, $u, $u['fonction_code']);
                        if (!$shift) continue;
                        // If shift is rejected by rules, try to resolve with forced shifts
                        if (!$checkRules($u, $shift['code'], $modId, $date)) {
                            $shift = $resolveShift($u, $shift, $modId, $date);
                            if (!$shift || !$checkRules($u, $shift['code'], $modId, $date)) continue;
                        }
                    }

                    // AS weekly hours: check if this shift would exceed weekly target
                    // Skip this check if it's an approved desir
                    if (!$isDesir && $u['fonction_code'] === 'AS') {
                        $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                        $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                        if ($currentWeekHours + (float) $shift['duree_effective'] > $weeklyTarget + 5) continue;
                    }

                    // ── LTr HARD CAP : plafond hebdo absolu (applicable MÊME aux désirs) ──
                    // Le respect de la loi prime sur toute tolérance ou désir validé.
                    $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                    if ($currentWeekHours + (float) $shift['duree_effective'] > $userWeeklyHardCap[$u['id']]) continue;

                    // ── LTr OLT 2 art. 7 : max 12h effectif par jour ──
                    if ((float) $shift['duree_effective'] > $iaLegalMaxHoursDay) continue;

                    // ── LTr art. 15a : 11h de repos entre deux journées ──
                    if (!$checkRestBetween($u['id'], $date, $shift)) continue;

                    // Part-time: prefer shorter shifts — but NEVER override an approved desir
                    if (!$isDesir && $u['taux'] < 80 && $assignedForSlot > 0 && $u['fonction_code'] !== 'AS') {
                        if (!empty($morningShifts)) $shift = $morningShifts[array_rand($morningShifts)];
                    }

                    $groupeId = $getGroupeId($u['fonction_code'], $modId, $assignedForSlot);
                    if ($u['fonction_code'] === 'AS') {
                        if (!isset($moduleASSlotIdx[$modId][$date])) $moduleASSlotIdx[$modId][$date] = 0;
                        $moduleASSlotIdx[$modId][$date]++;
                    }

                    $desirNote = isset($desirMap[$u['id']][$date]) ? 'desir' : null;
                    $stmtInsert->execute([
                        Uuid::v4(), $planningId, $u['id'], $date,
                        $shift['id'], $modId, $groupeId, 'present', $desirNote
                    ]);

                    $userWeekHours[$u['id']][$wk] = ($userWeekHours[$u['id']][$wk] ?? 0) + (float) $shift['duree_effective'];
                    $userHours[$u['id']] = ($userHours[$u['id']] ?? 0) + (float) $shift['duree_effective'];
                    $userDays[$u['id']][$date] = true;
                    $userShifts[$u['id']][$date] = $shift['code'];
                    $userShiftTimes[$u['id']][$date] = $parseShiftTimes($shift);
                    $userWeekDays[$u['id']][$wk] = ($userWeekDays[$u['id']][$wk] ?? 0) + 1;
                    $assigned++;
                    $assignedForSlot++;
                }

                if ($assignedForSlot < $nbReqd) {
                    $conflicts[] = [
                        'date' => $date,
                        'module_id' => $modId,
                        'fonction_id' => $fonctionId,
                        'requis' => $nbReqd,
                        'assigne' => $assignedForSlot,
                    ];
                }
            }
        }
    }

    // ── PASS 1.5: Entraide — fill AS gaps with cross-module AS ──
    // When a module doesn't have enough AS, borrow from other modules
    if (!empty($conflicts)) {
        $asConflicts = [];
        foreach ($conflicts as $ci => $c) {
            // Only process AS conflicts
            if ($c['fonction_id'] !== $fonctionAS) continue;
            $asConflicts[] = $ci;
        }

        foreach ($asConflicts as $ci) {
            $c = $conflicts[$ci];
            $date = $c['date'];
            $modId = $c['module_id'];
            $nbMissing = $c['requis'] - $c['assigne'];
            $filled = 0;
            $wk = $dateCache[$dateByStr[$date]]['wk'];

            // Find AS from ANY module (not just this one) who are available
            $candidates = [];
            foreach ($users as $u) {
                if ($u['fonction_code'] !== 'AS') continue;
                if (!$isEligible($u, $date)) continue;
                if (!$checkRules($u, null, $modId, $date)) continue; // early module/weekend/max_days check

                // Skip if already at consecutive max (plafonné par LTr art. 21)
                $maxConsecAS = min($iaConsecutifMaxAS, $iaLegalMaxConsecDays);
                if ($getConsecutive($u['id'], $date) >= $maxConsecAS) continue;

                // Weekly hours check (relaxed: +8h tolerance for entraide, mais plafond LTr absolu)
                $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                if ($currentWeekHours >= $weeklyTarget + 8) continue;
                if ($currentWeekHours >= $userWeeklyHardCap[$u['id']]) continue;

                // Monthly hours check
                $targetMonthHours = $userTargetMonthHours[$u['id']];
                $currentHours = $userHours[$u['id']] ?? 0;
                $deficit = $targetMonthHours - $currentHours;

                // Prefer users with most hours deficit + not from this module (real entraide)
                $isPrincipal = ($u['principal_module_id'] === $modId) ? -5 : 0; // deprioritize same module (already tried)
                $candidates[] = [
                    'user' => $u,
                    'score' => $deficit + $isPrincipal + rand(0, $iaRandomMax),
                ];
            }

            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

            foreach ($candidates as $ca) {
                if ($filled >= $nbMissing) break;
                $u = $ca['user'];

                // Determine slot index for shift pairing (continue from what's already assigned)
                $slotIdx = $c['assigne'] + $filled;
                // Approved desir = hard constraint
                $desiredShift = $getDesiredShift($u, $date);
                $isDesir1_5 = (bool) $desiredShift;
                if ($desiredShift) {
                    $shift = $desiredShift;
                } else {
                    $shift = $pickShift($modId, $slotIdx, $c['requis'], (int) substr($date, -2), $u, 'AS');
                    if (!$shift) continue;
                    if (!$checkRules($u, $shift['code'], $modId, $date)) {
                        $shift = $resolveShift($u, $shift, $modId, $date);
                        if (!$shift || !$checkRules($u, $shift['code'], $modId, $date)) continue;
                    }
                }

                // Final weekly check with shift duration — skip if approved desir
                if (!$isDesir1_5) {
                    $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                    $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                    if ($currentWeekHours + (float) $shift['duree_effective'] > $weeklyTarget + 8) continue;
                }
                // ── LTr HARD CAP : plafond hebdo absolu même pour les désirs ──
                $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                if ($currentWeekHours + (float) $shift['duree_effective'] > $userWeeklyHardCap[$u['id']]) continue;
                // ── LTr OLT 2 art. 7 : max 12h effectif par jour ──
                if ((float) $shift['duree_effective'] > $iaLegalMaxHoursDay) continue;
                // ── LTr art. 15a : 11h de repos entre deux journées ──
                if (!$checkRestBetween($u['id'], $date, $shift)) continue;

                $asSlotIdx1_5 = $moduleASSlotIdx[$modId][$date] ?? ($c['assigne'] + $filled);
                $groupeId = $getGroupeId('AS', $modId, $asSlotIdx1_5);
                if (!isset($moduleASSlotIdx[$modId][$date])) $moduleASSlotIdx[$modId][$date] = 0;
                $moduleASSlotIdx[$modId][$date]++;

                $desirNote1_5 = isset($desirMap[$u['id']][$date]) ? 'desir' : null;
                $stmtInsert->execute([
                    Uuid::v4(), $planningId, $u['id'], $date,
                    $shift['id'], $modId, $groupeId, 'entraide', $desirNote1_5
                ]);

                $userWeekHours[$u['id']][$wk] = ($userWeekHours[$u['id']][$wk] ?? 0) + (float) $shift['duree_effective'];
                $userHours[$u['id']] = ($userHours[$u['id']] ?? 0) + (float) $shift['duree_effective'];
                $userDays[$u['id']][$date] = true;
                $userShifts[$u['id']][$date] = $shift['code'];
                $userShiftTimes[$u['id']][$date] = $parseShiftTimes($shift);
                $assigned++;
                $filled++;
            }

            // Update conflict count
            $conflicts[$ci]['assigne'] += $filled;
            if ($conflicts[$ci]['assigne'] >= $conflicts[$ci]['requis']) {
                unset($conflicts[$ci]);
            }
        }
        $conflicts = array_values($conflicts); // re-index
    }

    // ── PASS 2: Fill remaining unassigned employees (balance hours) ──
    if (!$moduleFilter) {
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dm = $dateCache[$d];
            $date = $dm['date'];
            $dow = $dm['dow'];
            $wk = $dm['wk'];

            // Sort users by hours deficit (most underworked first) for fair distribution
            $usersPass2 = $users;
            usort($usersPass2, function ($a, $b) use (&$userHours, &$userTargetMonthHours) {
                $deficitA = $userTargetMonthHours[$a['id']] - ($userHours[$a['id']] ?? 0);
                $deficitB = $userTargetMonthHours[$b['id']] - ($userHours[$b['id']] ?? 0);
                return $deficitB <=> $deficitA; // most deficit first
            });

            foreach ($usersPass2 as $u) {
                if (isset($userDays[$u['id']][$date])) continue;
                if (!$isEligible($u, $date)) continue;

                $isAS = ($u['fonction_code'] === 'AS');
                $principalMod = $u['principal_module_id'];

                // Check if this user has an approved desir for this date
                $hasDesir = isset($desirMap[$u['id']][$date]) && $desirMap[$u['id']][$date]['type'] === 'horaire_special';
                $desiredShift = $hasDesir ? $getDesiredShift($u, $date) : null;

                // Approved desir = hard constraint → bypass soft checks
                if ($desiredShift) {
                    if (!$principalMod) continue;
                    $shift = $desiredShift;
                    goto pass2_assign;
                }

                // Early structured rules check (module/weekend/max_days)
                if ($principalMod && !$checkRules($u, null, $principalMod, $date)) continue;

                $targetMonthHours = $userTargetMonthHours[$u['id']];
                $currentHours = $userHours[$u['id']] ?? 0;
                if ($currentHours >= $targetMonthHours) continue;

                // ── Even distribution: limit days per week based on taux ──
                $weeklyDaysTarget = $userWeeklyDaysTarget[$u['id']];
                $currentWeekDays = $userWeekDays[$u['id']][$wk] ?? 0;
                if ($currentWeekDays >= $weeklyDaysTarget + 1) continue;

                // Consecutive check: AS max 3, Nuit max 5 (LTr 17a), autres max iaConsecutifMax
                // Plafond absolu LTr art. 21 : 6 jours consécutifs max.
                $isNight = ($principalMod === $nightModuleId);
                $maxConsec = $isAS ? $iaConsecutifMaxAS : ($isNight ? $iaConsecutifMaxNuit : $iaConsecutifMax);
                $maxConsec = min($maxConsec, $iaLegalMaxConsecDays);
                if ($getConsecutive($u['id'], $date) >= $maxConsec) continue;

                // AS: weekly hours cap — don't exceed weekly target
                if ($isAS) {
                    $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                    $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                    if ($currentWeekHours >= $weeklyTarget) continue;
                }

                // Weekend: AS work 7j/7 (no skip), others reduced probability
                if (!$isAS && $dow >= 6 && rand(1, 100) <= $iaWeekendSkipProb) continue;

                if (!$principalMod) continue;

                // Direction/weekend off check
                if (in_array($u['role'], ['direction', 'responsable']) && $iaDirWeekendOff && $dow >= 6) continue;

                // Night module → night shift
                $isNight = ($principalMod === $nightModuleId);
                if ($isNight && !empty($nightShifts)) {
                    $shift = $nightShifts[array_rand($nightShifts)];
                } elseif (in_array($u['role'], ['direction', 'responsable']) && $adminShift) {
                    $shift = $adminShift;
                } else if ($isAS) {
                    // AS: only D1, D3, D4, S3, S4 — alternate morning/evening
                    $lastShiftCode = null;
                    for ($back = 1; $back <= 3; $back++) {
                        $pd = $d - $back;
                        if ($pd < 1) break;
                        $prevDate = $dateCache[$pd]['date'];
                        if (isset($userShifts[$u['id']][$prevDate])) { $lastShiftCode = $userShifts[$u['id']][$prevDate]; break; }
                    }
                    $wasMorning = $lastShiftCode && in_array($lastShiftCode, ['D1','D3','D4']);
                    $asAll = array_merge($asMorningShifts, $asFullDayShifts, $asEveningShifts);
                    if ($wasMorning && !empty($asEveningShifts)) {
                        $shift = $asEveningShifts[array_rand($asEveningShifts)];
                    } elseif (!$wasMorning && !empty($asMorningShifts)) {
                        $shift = $asMorningShifts[array_rand($asMorningShifts)];
                    } else {
                        if (empty($asAll)) continue;
                        $shift = $asAll[array_rand($asAll)];
                    }

                    // AS: check shift won't exceed weekly target
                    $weeklyTarget = $userWeeklyTargetHours[$u['id']];
                    $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                    if ($currentWeekHours + (float) $shift['duree_effective'] > $weeklyTarget + 2) continue;
                } else {
                    // INF, ASSC, etc.
                    $lastShiftCode = null;
                    for ($back = 1; $back <= 3; $back++) {
                        $pd = $d - $back;
                        if ($pd < 1) break;
                        $prevDate = $dateCache[$pd]['date'];
                        if (isset($userShifts[$u['id']][$prevDate])) { $lastShiftCode = $userShifts[$u['id']][$prevDate]; break; }
                    }
                    $wasMorning = $lastShiftCode && in_array($lastShiftCode, ['A1','A2','A3','D1','D4','C1']);
                    if ($wasMorning && !empty($eveningShifts)) {
                        $shift = $eveningShifts[array_rand($eveningShifts)];
                    } elseif (!$wasMorning && !empty($morningShifts)) {
                        $shift = $morningShifts[array_rand($morningShifts)];
                    } else {
                        $allShifts = array_merge($morningShifts, $eveningShifts);
                        if (empty($allShifts)) continue;
                        $shift = $allShifts[array_rand($allShifts)];
                    }

                    // Part-time: prefer shorter shifts (non-AS)
                    if ($u['taux'] < 60 && !empty($morningShifts)) {
                        $shortest = $morningShifts[0];
                        foreach ($morningShifts as $ms) {
                            if ($ms['duree_effective'] < $shortest['duree_effective']) $shortest = $ms;
                        }
                        $shift = $shortest;
                    }
                }

                // Structured rules: shift-level check — resolve forced shifts if needed
                if (!$checkRules($u, $shift['code'], $principalMod, $date)) {
                    $shift = $resolveShift($u, $shift, $principalMod, $date);
                    if (!$shift || !$checkRules($u, $shift['code'], $principalMod, $date)) continue;
                }

                pass2_assign:
                // ── LTr HARD CAP : plafond hebdo absolu (s'applique MÊME aux désirs) ──
                if (isset($shift['duree_effective'])) {
                    $currentWeekHours = $userWeekHours[$u['id']][$wk] ?? 0;
                    if ($currentWeekHours + (float) $shift['duree_effective'] > $userWeeklyHardCap[$u['id']]) {
                        error_log("SpocSpace LTr: refus assignation {$u['prenom']} {$u['nom']} le $date — dépasserait plafond hebdo (" . $userWeeklyHardCap[$u['id']] . "h).");
                        continue;
                    }
                    // ── LTr OLT 2 art. 7 : max 12h effectif par jour ──
                    if ((float) $shift['duree_effective'] > $iaLegalMaxHoursDay) {
                        error_log("SpocSpace LTr OLT2 art. 7: refus assignation {$u['prenom']} {$u['nom']} le $date — shift {$shift['code']} dépasse {$iaLegalMaxHoursDay}h/jour.");
                        continue;
                    }
                    // ── LTr art. 15a : 11h de repos entre deux journées ──
                    if (!$checkRestBetween($u['id'], $date, $shift)) {
                        error_log("SpocSpace LTr art. 15a: refus assignation {$u['prenom']} {$u['nom']} le $date — repos quotidien < {$iaLegalReposQuotidienH}h.");
                        continue;
                    }
                }
                // ── LTr art. 21 : max 6 jours consécutifs (même pour désirs) ──
                if ($getConsecutive($u['id'], $date) >= $iaLegalMaxConsecDays) {
                    error_log("SpocSpace LTr art. 21: refus assignation {$u['prenom']} {$u['nom']} le $date — 6 jours consécutifs déjà atteints.");
                    continue;
                }
                $groupeIdP2 = null;
                if ($isAS) {
                    $asSlotP2 = $moduleASSlotIdx[$principalMod][$date] ?? 0;
                    $groupeIdP2 = $getGroupeId('AS', $principalMod, $asSlotP2);
                    if (!isset($moduleASSlotIdx[$principalMod][$date])) $moduleASSlotIdx[$principalMod][$date] = 0;
                    $moduleASSlotIdx[$principalMod][$date]++;
                }

                $desirNoteP2 = isset($desirMap[$u['id']][$date]) ? 'desir' : null;
                $stmtInsert->execute([
                    Uuid::v4(), $planningId, $u['id'], $date,
                    $shift['id'], $principalMod, $groupeIdP2, 'present', $desirNoteP2
                ]);
                $userWeekHours[$u['id']][$wk] = ($userWeekHours[$u['id']][$wk] ?? 0) + (float) $shift['duree_effective'];
                $userHours[$u['id']] = ($userHours[$u['id']] ?? 0) + (float) $shift['duree_effective'];
                $userDays[$u['id']][$date] = true;
                $userShifts[$u['id']][$date] = $shift['code'];
                $userShiftTimes[$u['id']][$date] = $parseShiftTimes($shift);
                $userWeekDays[$u['id']][$wk] = ($userWeekDays[$u['id']][$wk] ?? 0) + 1;
                $assigned++;
            }
        }
    }

    // ── Commit transaction: all passes 1/1.5/2 inserts flushed together ──
    if ($inTx && $pdo->inTransaction()) { $pdo->commit(); $inTx = false; }

    // ── PASS 3 (IA): Optimize planning with AI if hybrid or ai mode ──
    $iaTokensIn = 0;
    $iaTokensOut = 0;
    $iaCostUsd = 0;
    $iaProviderUsed = 'local';
    $iaModelUsed = 'algorithme-v1';
    $iaOptimizations = 0;

    if ($mode !== 'local' && !empty($aiApiKey)) {
        $iaProviderUsed = $aiProvider;
        $iaModelUsed = $aiModel;

        // Build context for IA
        $modulesInfo = Db::fetchAll("SELECT m.id, m.code, m.nom, COUNT(e.id) as nb_etages FROM modules m LEFT JOIN etages e ON e.module_id = m.id GROUP BY m.id ORDER BY m.ordre");
        $fonctionsInfo = Db::fetchAll("SELECT id, code, nom FROM fonctions ORDER BY ordre");

        // Build conflict summary
        $conflictSummary = [];
        foreach ($conflicts as $c) {
            $modCode = '';
            foreach ($modulesInfo as $mi) { if ($mi['id'] === $c['module_id']) { $modCode = $mi['code']; break; } }
            $foncCode = '';
            foreach ($fonctionsInfo as $fi) { if ($fi['id'] === $c['fonction_id']) { $foncCode = $fi['code']; break; } }
            $conflictSummary[] = "{$c['date']} {$modCode} {$foncCode}: requis={$c['requis']} assigné={$c['assigne']}";
        }

        // Build current assignments summary (per user per day)
        $currentAssignments = Db::fetchAll(
            "SELECT pa.user_id, pa.date_jour, ht.code AS horaire_code, m.code AS module_code,
                    u.nom, u.prenom, f.code AS fonction_code
             FROM planning_assignations pa
             JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             JOIN modules m ON m.id = pa.module_id
             JOIN users u ON u.id = pa.user_id
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             WHERE pa.planning_id = ?
             ORDER BY pa.date_jour, m.code, f.code",
            [$planningId]
        );

        // Group assignments by day for concise output
        $byDay = [];
        foreach ($currentAssignments as $a) {
            $byDay[$a['date_jour']][] = "{$a['module_code']}/{$a['fonction_code']}: {$a['prenom']} {$a['nom']} ({$a['horaire_code']})";
        }

        // Hours summary per user
        $hoursSummary = [];
        foreach ($users as $u) {
            if (($userHours[$u['id']] ?? 0) > 0) {
                $target = round($iaJoursOuvres * $iaHeuresJour * ($u['taux'] / 100));
                $actual = round($userHours[$u['id']] ?? 0);
                if (abs($target - $actual) > 5) {
                    $hoursSummary[] = "{$u['prenom']} {$u['nom']} ({$u['fonction_code']}, {$u['taux']}%): {$actual}h / cible {$target}h";
                }
            }
        }

        // Build list of available employees (with IDs so IA can reference them)
        $availableStaff = [];
        foreach ($users as $u) {
            if (in_array($u['fonction_code'], ['AS', 'INF', 'ASSC'])) {
                $mods = $userModuleMap[$u['id']] ?? [];
                $modCodes = [];
                foreach ($modulesInfo as $mi) { if (in_array($mi['id'], $mods)) $modCodes[] = $mi['code']; }
                $currentH = round($userHours[$u['id']] ?? 0);
                $targetH = round($iaJoursOuvres * $iaHeuresJour * ($u['taux'] / 100));
                $weeklyCapH = $userWeeklyHardCap[$u['id']] ?? $iaLegalMaxHoursWeek;
                $weeklyTgtH = $userWeeklyTargetHours[$u['id']] ?? 0;
                $availableStaff[] = "  {$u['id']} | {$u['prenom']} {$u['nom']} | {$u['fonction_code']} | {$u['taux']}% | modules:" . implode(',', $modCodes) . " | mois:{$currentH}/{$targetH}h | hebdo_cible:{$weeklyTgtH}h | plafond_hebdo:{$weeklyCapH}h";
            }
        }

        // Build days with current assignments summary (compact)
        $assignSummary = [];
        foreach ($byDay as $day => $entries) {
            $assignSummary[] = "$day: " . implode(', ', array_slice($entries, 0, 20));
        }

        // Fetch human IA rules from database
        $iaRules = Db::fetchAll(
            "SELECT titre, description, importance FROM ia_human_rules WHERE actif = 1 ORDER BY CASE WHEN importance = 'important' THEN 1 WHEN importance = 'moyen' THEN 2 ELSE 3 END, created_at DESC"
        );
        $importantRules = array_values(array_filter($iaRules, fn($r) => $r['importance'] === 'important'));
        $moyenRules     = array_values(array_filter($iaRules, fn($r) => $r['importance'] === 'moyen'));

        // Build shift catalog for prompt (structured with type classification)
        $shiftCatalog = [];
        $validShiftCodes = [];
        foreach ($horaires as $h) {
            if (in_array($h['code'], $excludeShiftCodes)) continue;
            $deb = (int) substr($h['heure_debut'], 0, 2);
            if ($deb >= $nightThreshold) $type = 'nuit';
            elseif ($deb >= $eveningThreshold) $type = 'soir';
            elseif ((float)$h['duree_effective'] >= $fullDayMinHours) $type = 'journee_complete';
            else $type = 'matin';
            $shiftCatalog[] = "  - {$h['code']} : {$h['heure_debut']}-{$h['heure_fin']} ({$h['duree_effective']}h) [type={$type}]";
            $validShiftCodes[] = $h['code'];
        }

        $validModuleCodes = array_map(fn($mi) => $mi['code'], $modulesInfo);

        // ──────────────────────────────────────────────────────────────
        // NEW PROMPT (XML-structured, strict rules, validation checklist)
        // Goal: the model MUST respect hard constraints and never invent IDs.
        // ──────────────────────────────────────────────────────────────
        $prompt  = "<role>\n";
        $prompt .= "Tu es un planificateur RH pour un EMS (maison de retraite) en Suisse. ";
        $prompt .= "Ta mission : proposer des modifications ponctuelles au planning ci-dessous pour résoudre les conflits de couverture et rééquilibrer les heures, SANS JAMAIS violer une contrainte inviolable.\n";
        $prompt .= "</role>\n\n";

        $prompt .= "<contraintes_inviolables>\n";
        $prompt .= "Chaque optimisation qui viole UNE de ces contraintes doit être REJETÉE (ne pas l'émettre).\n";
        $prompt .= "1. user_id : utiliser UNIQUEMENT les UUID listés dans <staff>. Ne jamais en inventer ni les modifier.\n";
        $prompt .= "2. to_shift : utiliser UNIQUEMENT les codes listés dans <shift_catalog> (codes valides : " . implode(', ', $validShiftCodes) . ").\n";
        $prompt .= "3. module_code : utiliser UNIQUEMENT les codes listés dans <modules> (codes valides : " . implode(', ', $validModuleCodes) . ").\n";
        $prompt .= "4. date : obligatoirement dans le mois cible {$mois} (format YYYY-MM-DD, entre {$mois}-01 et {$mois}-" . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT) . ").\n";
        $prompt .= "5. Fonction du poste : l'employé doit avoir la fonction demandée (un conflit AS se remplit uniquement avec un AS, idem INF/ASSC).\n";
        $prompt .= "6. Désirs validés : INTERDIT de modifier (action=move) ou supprimer (action=remove) une assignation dont la note contient 'desir'. Ce sont des contraintes absolues validées par le responsable.\n";
        $prompt .= "7. Absences validées : l'employé ne doit pas être absent ce jour-là.\n";
        $prompt .= "8. Double assignation : un employé peut avoir au MAXIMUM 1 poste par jour. Ne jamais ajouter un employé déjà assigné ce jour.\n";
        $prompt .= "9. Jours consécutifs : AS max {$iaConsecutifMaxAS} jours, nuit max {$iaConsecutifMaxNuit} (LTr art. 17a), autres max {$iaConsecutifMax}.\n";
        $prompt .= "10. LÉGISLATION SUISSE (LTr art. 9 al. 1 let. b) — plafond hebdomadaire absolu : {$iaLegalMaxHoursWeek}h/semaine MAXIMUM, peu importe le taux contractuel ou un désir validé. Toute optimisation qui ferait dépasser ce seuil est REJETÉE.\n";
        $prompt .= "11. LÉGISLATION SUISSE (LTr art. 21) — jour de repos hebdomadaire : AUCUN employé ne peut travailler plus de {$iaLegalMaxConsecDays} jours consécutifs (tous profils confondus).\n";
        $prompt .= "12. Plafond contractuel : ne pas dépasser taux×heures_hebdo + {$iaMaxOverTauxHoursWeek}h de tolérance (ex : un 50% doit rester ≤ " . round(($iaJoursOuvres * $iaHeuresJour / $nbWeeksInMonth) * 0.5 + $iaMaxOverTauxHoursWeek, 1) . "h/semaine).\n";
        $prompt .= "13. LÉGISLATION SUISSE (OLT 2 art. 7 — santé/EMS) — durée journalière max : {$iaLegalMaxHoursDay}h de travail EFFECTIF par jour (pauses ≥30min déjà exclues). Un shift dont duree_effective > {$iaLegalMaxHoursDay}h ne doit JAMAIS être proposé.\n";
        $prompt .= "14. LÉGISLATION SUISSE (LTr art. 15a al. 1) — repos quotidien : au moins {$iaLegalReposQuotidienH}h de repos entre la fin d'un shift et le début du shift suivant le lendemain. Toute optimisation qui viole ce repos est REJETÉE (ex : finir à 20h30 puis reprendre à 06h30 = 10h = VIOLATION).\n";
        if ($iaDirWeekendOff) {
            $prompt .= "15. Direction/responsable : ne travaillent PAS le samedi ni le dimanche.\n";
        }
        $prompt .= "</contraintes_inviolables>\n\n";

        if (!empty($importantRules)) {
            $prompt .= "<regles_importantes>\n";
            $prompt .= "Règles métier spécifiques à cet EMS, à respecter comme des contraintes dures.\n";
            foreach ($importantRules as $i => $rule) {
                $prompt .= ($i + 1) . ". " . trim($rule['titre']) . " — " . trim($rule['description']) . "\n";
            }
            $prompt .= "</regles_importantes>\n\n";
        }

        $prompt .= "<preferences>\n";
        $prompt .= "À respecter autant que possible, sans compromettre les contraintes inviolables.\n";
        $prompt .= "- Heures hebdomadaires proches du taux contractuel (ex : 100% ≈ " . round($iaJoursOuvres * $iaHeuresJour / $nbWeeksInMonth, 1) . "h/sem).\n";
        $prompt .= "- Alternance matin/soir sur jours consécutifs (éviter 3 matins ou 3 soirs d'affilée).\n";
        $prompt .= "- Module principal prioritaire ; entraide inter-modules seulement si nécessaire.\n";
        if (!empty($moyenRules)) {
            foreach ($moyenRules as $rule) {
                $prompt .= "- " . trim($rule['titre']) . " — " . trim($rule['description']) . "\n";
            }
        }
        $prompt .= "</preferences>\n\n";

        $prompt .= "<contexte>\n";
        $prompt .= "- Mois cible : {$mois}\n";
        $prompt .= "- Jours dans le mois : {$daysInMonth}\n";
        $prompt .= "- Couverture journalière : {$coverageStart} à {$coverageEnd}\n";
        $prompt .= "- AS requis par étage : {$asPerEtage}\n";
        $prompt .= "- Pairage matin/soir : " . ($shiftPairing ? 'oui' : 'non') . "\n";
        $prompt .= "</contexte>\n\n";

        $prompt .= "<modules>\n";
        foreach ($modulesInfo as $mi) {
            $prompt .= "  - code={$mi['code']} nom=\"{$mi['nom']}\" etages={$mi['nb_etages']}\n";
        }
        $prompt .= "</modules>\n\n";

        $prompt .= "<shift_catalog>\n";
        $prompt .= implode("\n", $shiftCatalog) . "\n";
        $prompt .= "</shift_catalog>\n\n";

        $prompt .= "<staff count=\"" . count($availableStaff) . "\">\n";
        $prompt .= "Format par ligne : uuid | prénom nom | fonction | taux% | modules | heures_actuelles/cible\n";
        $prompt .= implode("\n", array_slice($availableStaff, 0, 80));
        if (count($availableStaff) > 80) {
            $prompt .= "\n  (... " . (count($availableStaff) - 80) . " employés supplémentaires non listés)";
        }
        $prompt .= "\n</staff>\n\n";

        $nbConflicts = count($conflictSummary);
        $prompt .= "<conflicts count=\"{$nbConflicts}\">\n";
        if (empty($conflictSummary)) {
            $prompt .= "Aucun conflit de couverture.\n";
        } else {
            $prompt .= implode("\n", array_slice($conflictSummary, 0, 40));
            if (count($conflictSummary) > 40) {
                $prompt .= "\n  (... " . (count($conflictSummary) - 40) . " conflits supplémentaires)";
            }
            $prompt .= "\n";
        }
        $prompt .= "</conflicts>\n\n";

        if (!empty($hoursSummary)) {
            $prompt .= "<hours_imbalance description=\"écart actuel/cible &gt; 5h\">\n";
            $prompt .= implode("\n", array_slice($hoursSummary, 0, 25)) . "\n";
            $prompt .= "</hours_imbalance>\n\n";
        }

        $prompt .= "<tache>\n";
        $prompt .= "1. Pour chaque ligne dans <conflicts>, proposer UNE optimisation qui la résout (action=add ou move), en respectant toutes les <contraintes_inviolables>.\n";
        $prompt .= "2. Pour les déséquilibres d'heures significatifs, proposer des move/add/remove qui rapprochent chaque employé de sa cible, SANS créer de nouveaux conflits.\n";
        $prompt .= "3. Ne proposer AUCUNE optimisation si tu n'es pas sûr qu'elle respecte toutes les contraintes : mieux vaut zéro optimisation qu'une invalide.\n";
        $prompt .= "</tache>\n\n";

        $prompt .= "<checklist_avant_chaque_optimisation>\n";
        $prompt .= "Avant d'émettre CHAQUE élément du tableau optimizations, vérifier mentalement ces 8 points. Si UN SEUL échoue, ne pas émettre l'optimisation.\n";
        $prompt .= "[1] user_id apparaît dans <staff> (copier-coller l'UUID exact).\n";
        $prompt .= "[2] date est au format YYYY-MM-DD et appartient au mois {$mois}.\n";
        $prompt .= "[3] to_shift est un code présent dans <shift_catalog>.\n";
        $prompt .= "[4] module_code est un code présent dans <modules>.\n";
        $prompt .= "[5] La fonction de l'employé (colonne fonction dans <staff>) correspond au besoin du conflit traité.\n";
        $prompt .= "[6] L'employé n'est pas déjà assigné ce jour-là (pour action=add).\n";
        $prompt .= "[7] L'assignation ciblée n'est pas un 'desir' (pour action=move ou remove).\n";
        $prompt .= "[8] Le shift choisi a une duree_effective ≤ {$iaLegalMaxHoursDay}h (plafond journalier OLT 2 art. 7).\n";
        $prompt .= "[9] Le repos quotidien ≥ {$iaLegalReposQuotidienH}h est respecté (écart heure_fin du shift J-1 → heure_debut du shift J).\n";
        $prompt .= "[10] L'employé n'atteindra pas {$iaLegalMaxHoursWeek}h sur la semaine ISO en ajoutant ce shift (LTr art. 9).\n";
        $prompt .= "[11] Aucune <contraintes_inviolables> ni <regles_importantes> n'est violée.\n";
        $prompt .= "</checklist_avant_chaque_optimisation>\n\n";

        $prompt .= "<format_sortie>\n";
        $prompt .= "Réponds UNIQUEMENT par un objet JSON strict, sans markdown, sans balise code, sans texte avant/après.\n";
        $prompt .= "Schéma :\n";
        $prompt .= "{\n";
        $prompt .= "  \"optimizations\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"action\": \"move\" | \"add\" | \"remove\",\n";
        $prompt .= "      \"user_id\": \"<uuid-exact-copié-depuis-staff>\",\n";
        $prompt .= "      \"date\": \"YYYY-MM-DD\",\n";
        $prompt .= "      \"to_shift\": \"<code-du-shift_catalog>\",\n";
        $prompt .= "      \"module_code\": \"<code-des-modules>\",\n";
        $prompt .= "      \"reason\": \"explication concise en français\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"summary\": \"résumé global en français (1-3 phrases)\"\n";
        $prompt .= "}\n\n";
        $prompt .= "Cas où aucune optimisation n'est sûre : {\"optimizations\": [], \"summary\": \"Planning optimal ou aucune modification sûre identifiée.\"}\n";
        $prompt .= "Pour action=remove, les champs to_shift et module_code sont optionnels.\n";
        $prompt .= "</format_sortie>\n";

        // Call IA API
        $iaResponse = null;
        $iaError = null;

        if ($aiProvider === 'gemini') {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";
            $payload = json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 16384, 'responseMimeType' => 'application/json'],
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 90,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $raw) {
                $resp = json_decode($raw, true);
                
                $finishReason = $resp['candidates'][0]['finishReason'] ?? '';
                $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                if ($finishReason === 'MAX_TOKENS' && empty($text)) {
                    $iaError = "Gemini ran out of tokens during thinking (MAX_TOKENS)";
                    error_log("SpocSpace IA Gemini error: MAX_TOKENS reached.");
                } else {
                    $usage = $resp['usageMetadata'] ?? [];
                    $iaTokensIn = (int) ($usage['promptTokenCount'] ?? 0);
                    $iaTokensOut = (int) ($usage['candidatesTokenCount'] ?? 0);

                    // Gemini pricing (approximate per model)
                    $priceIn = 0; $priceOut = 0;
                    if (str_contains($aiModel, 'flash')) { $priceIn = 0.075 / 1000000; $priceOut = 0.30 / 1000000; }
                    elseif (str_contains($aiModel, 'pro')) { $priceIn = 1.25 / 1000000; $priceOut = 5.00 / 1000000; }
                    $iaCostUsd = $iaTokensIn * $priceIn + $iaTokensOut * $priceOut;

                    // Extract JSON from response (may be wrapped in markdown code block)
                    if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                        $iaResponse = json_decode($m[0], true);
                    }
                    if (!$iaResponse) {
                        $iaError = "Failed to parse JSON from Gemini response";
                        error_log("SpocSpace IA Gemini JSON parse error. Raw text: " . substr($text, 0, 500));
                    }
                }
            } else {
                $iaError = "Gemini API error (HTTP $httpCode)";
                error_log("SpocSpace IA Gemini error: HTTP $httpCode — " . substr($raw, 0, 500));
            }

        } elseif ($aiProvider === 'claude') {
            $url = "https://api.anthropic.com/v1/messages";
            $payload = json_encode([
                'model' => $aiModel,
                'max_tokens' => 4096,
                'temperature' => 0.3,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $aiApiKey,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $raw) {
                $resp = json_decode($raw, true);
                $text = $resp['content'][0]['text'] ?? '';
                $usage = $resp['usage'] ?? [];
                $iaTokensIn = (int) ($usage['input_tokens'] ?? 0);
                $iaTokensOut = (int) ($usage['output_tokens'] ?? 0);

                // Claude pricing (approximate per model)
                $priceIn = 0; $priceOut = 0;
                if (str_contains($aiModel, 'haiku')) { $priceIn = 0.80 / 1000000; $priceOut = 4.00 / 1000000; }
                elseif (str_contains($aiModel, 'sonnet')) { $priceIn = 3.00 / 1000000; $priceOut = 15.00 / 1000000; }
                elseif (str_contains($aiModel, 'opus')) { $priceIn = 15.00 / 1000000; $priceOut = 75.00 / 1000000; }
                $iaCostUsd = $iaTokensIn * $priceIn + $iaTokensOut * $priceOut;

                // Extract JSON from response (may be wrapped in markdown code block)
                if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                    $iaResponse = json_decode($m[0], true);
                }
            } else {
                $iaError = "Claude API error (HTTP $httpCode)";
                error_log("SpocSpace IA Claude error: HTTP $httpCode — " . substr($raw, 0, 500));
            }
        }

        // Reconnect to DB because the long API call might have caused a timeout
        Db::reconnect();

        // Apply IA optimizations if any (with strict server-side validation)
        $iaRejected = []; // collect rejections for observability
        if ($iaResponse && !empty($iaResponse['optimizations'])) {
            $horaireByCode = [];
            foreach ($horaires as $h) $horaireByCode[$h['code']] = $h;
            $moduleByCode = [];
            foreach ($modulesInfo as $mi) $moduleByCode[$mi['code']] = $mi['id'];

            // Build set of valid user IDs + absence map for validation
            $validUserIds = [];
            $usersById = [];
            foreach ($users as $u) { $validUserIds[$u['id']] = true; $usersById[$u['id']] = $u; }

            foreach ($iaResponse['optimizations'] as $opt) {
                $action  = $opt['action'] ?? '';
                $userId  = $opt['user_id'] ?? '';
                $date    = $opt['date'] ?? '';
                $toShift = $opt['to_shift'] ?? '';
                $modCode = $opt['module_code'] ?? '';
                $modId   = $moduleByCode[$modCode] ?? null;

                // ── Hard validations (reject silently, log for telemetry) ──
                if (!in_array($action, ['move', 'add', 'remove'])) { $iaRejected[] = "action invalide: $action"; continue; }
                if (!$userId || !$date) { $iaRejected[] = "user_id/date manquant"; continue; }
                if (!isset($validUserIds[$userId])) { $iaRejected[] = "user_id inventé: $userId"; continue; }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !str_starts_with($date, $mois)) {
                    $iaRejected[] = "date hors mois: $date"; continue;
                }
                if (($action === 'move' || $action === 'add') && !isset($horaireByCode[$toShift])) {
                    $iaRejected[] = "to_shift inconnu: $toShift"; continue;
                }
                if ($action === 'add' && !$modId) { $iaRejected[] = "module_code inconnu: $modCode"; continue; }
                if ($action === 'add' && isset($absenceMap[$userId][$date])) {
                    $iaRejected[] = "employé absent: $userId@$date"; continue;
                }

                // ── LTr : validation plafonds + repos pour add/move ──
                if (($action === 'add' || $action === 'move') && isset($horaireByCode[$toShift])) {
                    $shiftObj = $horaireByCode[$toShift];
                    $shiftHours = (float) $shiftObj['duree_effective'];
                    $wkIA = $dateCache[$dateByStr[$date] ?? 0]['wk'] ?? null;
                    $capIA = $userWeeklyHardCap[$userId] ?? $iaLegalMaxHoursWeek;

                    // 1) Plafond hebdo (Art. 9 LTr)
                    if ($action === 'add' && $wkIA !== null) {
                        $alreadyWk = $userWeekHours[$userId][$wkIA] ?? 0;
                        if ($alreadyWk + $shiftHours > $capIA) {
                            $iaRejected[] = "LTr art. 9 plafond hebdo dépassé ({$capIA}h): $userId@$date"; continue;
                        }
                    }
                    // 2) Max 6 jours consécutifs (Art. 21 LTr)
                    if ($action === 'add' && $getConsecutive($userId, $date) >= $iaLegalMaxConsecDays) {
                        $iaRejected[] = "LTr art. 21 (6j consécutifs): $userId@$date"; continue;
                    }
                    // 3) Max 12h effectif/jour (OLT 2 art. 7 — santé)
                    if ($shiftHours > $iaLegalMaxHoursDay) {
                        $iaRejected[] = "LTr OLT 2 art. 7 shift >{$iaLegalMaxHoursDay}h/jour: $userId@$date"; continue;
                    }
                    // 4) Repos quotidien 11h (Art. 15a LTr)
                    if ($action === 'add' && !$checkRestBetween($userId, $date, $shiftObj)) {
                        $iaRejected[] = "LTr art. 15a repos <{$iaLegalReposQuotidienH}h: $userId@$date"; continue;
                    }
                }

                try {
                    // Protect approved desirs — IA cannot move or remove them
                    if ($action === 'move' || $action === 'remove') {
                        $existingNote = Db::getOne(
                            "SELECT notes FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                            [$planningId, $userId, $date]
                        );
                        if ($existingNote === 'desir') { $iaRejected[] = "desir protégé: $userId@$date"; continue; }
                    }

                    if ($action === 'move' && $toShift && isset($horaireByCode[$toShift])) {
                        Db::exec(
                            "UPDATE planning_assignations SET horaire_type_id = ?, module_id = COALESCE(?, module_id)
                             WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                            [$horaireByCode[$toShift]['id'], $modId, $planningId, $userId, $date]
                        );
                        $iaOptimizations++;
                    } elseif ($action === 'add' && $toShift && isset($horaireByCode[$toShift]) && $modId) {
                        $exists = Db::getOne(
                            "SELECT id FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                            [$planningId, $userId, $date]
                        );
                        if (!$exists) {
                            Db::exec(
                                "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut, notes)
                                 VALUES (?, ?, ?, ?, ?, ?, 'present', 'IA')",
                                [Uuid::v4(), $planningId, $userId, $date, $horaireByCode[$toShift]['id'], $modId]
                            );
                            $assigned++;
                            $iaOptimizations++;
                        }
                    } elseif ($action === 'remove') {
                        Db::exec(
                            "DELETE FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                            [$planningId, $userId, $date]
                        );
                        $assigned--;
                        $iaOptimizations++;
                    }
                } catch (\Throwable $e) {
                    error_log("SpocSpace IA optimization error: " . $e->getMessage());
                    continue; // Skip this optimization, don't crash
                }
            }
            if (!empty($iaRejected)) {
                error_log("SpocSpace IA: " . count($iaRejected) . " optimisations rejetées — " . implode(' | ', array_slice($iaRejected, 0, 10)));
            }
        }
    }

    // Log usage for expenses tracking
    $endTime = microtime(true);
    $durationMs = isset($startTime) ? (int)(($endTime - $startTime) * 1000) : 0;
    Db::exec(
        "INSERT INTO ia_usage_log (id, planning_id, mois_annee, provider, model, tokens_in, tokens_out, cost_usd, nb_assignations, nb_conflicts, duration_ms, admin_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [Uuid::v4(), $planningId, $mois, $iaProviderUsed, $iaModelUsed, $iaTokensIn, $iaTokensOut, $iaCostUsd, $assigned, count($conflicts), $durationMs, $_SESSION['ss_user']['id'] ?? null]
    );

    $message = "$assigned assignations générées";
    if ($mode !== 'local') {
        $message .= " · IA: $iaOptimizations optimisations";
        if ($iaError ?? null) $message .= " (⚠ $iaError)";
    }

    // Compute per-user stats for debugging
    $userStats = [];
    foreach ($users as $u) {
        $target = $userTargetMonthHours[$u['id']];
        $actual = $userHours[$u['id']] ?? 0;
        $days = count($userDays[$u['id']] ?? []);
        $diff = round($actual - $target, 1);
        if (abs($diff) > 5 || $days === 0) {
            $userStats[] = [
                'nom' => $u['prenom'] . ' ' . $u['nom'],
                'fonction' => $u['fonction_code'] ?? '?',
                'taux' => $u['taux'],
                'target' => $target,
                'actual' => round($actual, 1),
                'diff' => $diff,
                'days' => $days,
            ];
        }
    }

    // ── Rapport qualité ──
    $qualityReport = [];

    // 1. Couverture : % des besoins remplis
    $totalSlots = 0;
    $filledSlots = 0;
    foreach (array_keys($besoinsIdx) as $bModId) {
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = $dateCache[$d]['dow'];
            $modBesoins = $besoinsIdx[$bModId][$dow] ?? [];
            foreach ($modBesoins as $fId => $nb) {
                $totalSlots += $nb;
            }
        }
    }
    $filledSlots = $assigned;
    $qualityReport['coverage_pct'] = $totalSlots > 0 ? round($filledSlots / $totalSlots * 100, 1) : 100;

    // 2. Équité heures : écart-type entre employés actifs
    $allActualHours = [];
    foreach ($users as $u) {
        $h = $userHours[$u['id']] ?? 0;
        if ($h > 0) $allActualHours[] = $h;
    }
    if (count($allActualHours) > 1) {
        $mean = array_sum($allActualHours) / count($allActualHours);
        $variance = array_sum(array_map(fn($h) => ($h - $mean) ** 2, $allActualHours)) / count($allActualHours);
        $qualityReport['hours_stddev'] = round(sqrt($variance), 1);
        $qualityReport['hours_mean'] = round($mean, 1);
    } else {
        $qualityReport['hours_stddev'] = 0;
        $qualityReport['hours_mean'] = 0;
    }

    // 3. Désirs respectés
    $totalDesirs = 0;
    $desirsSatisfied = 0;
    foreach ($desirMap as $userId => $dates) {
        foreach ($dates as $date => $desir) {
            if ($desir['type'] === 'jour_off') {
                $totalDesirs++;
                if (!isset($userDays[$userId][$date])) $desirsSatisfied++;
            } elseif ($desir['type'] === 'horaire_special' && $desir['horaire_type_id']) {
                $totalDesirs++;
                // Check if assigned with desired shift
                $row = Db::fetch(
                    "SELECT horaire_type_id FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                    [$planningId, $userId, $date]
                );
                if ($row && $row['horaire_type_id'] === $desir['horaire_type_id']) $desirsSatisfied++;
            }
        }
    }
    $qualityReport['desirs_total'] = $totalDesirs;
    $qualityReport['desirs_satisfied'] = $desirsSatisfied;
    $qualityReport['desirs_pct'] = $totalDesirs > 0 ? round($desirsSatisfied / $totalDesirs * 100, 1) : 100;

    // 4. Équité week-ends
    $weekendCounts = [];
    foreach ($users as $u) {
        $wkCount = 0;
        foreach ($userDays[$u['id']] ?? [] as $date => $v) {
            $dow = $dateCache[$dateByStr[$date] ?? 0]['dow'] ?? (int) date('N', strtotime($date));
            if ($dow >= 6) $wkCount++;
        }
        if (($userHours[$u['id']] ?? 0) > 0) $weekendCounts[] = $wkCount;
    }
    if (!empty($weekendCounts)) {
        $qualityReport['weekend_min'] = min($weekendCounts);
        $qualityReport['weekend_max'] = max($weekendCounts);
        $qualityReport['weekend_mean'] = round(array_sum($weekendCounts) / count($weekendCounts), 1);
    }

    // 5. Conformité LTr — détection complète (hebdo, consécutifs, journalier, repos)
    $ltrWeeklyViolations = 0;
    $ltrConsecViolations = 0;
    $ltrDailyOverflow = 0;
    $ltrRestViolations = 0;
    $ltrViolations = [];
    foreach ($users as $u) {
        $uid = $u['id'];
        if (empty($userWeekHours[$uid])) continue;
        // Plafond hebdo (LTr art. 9)
        foreach ($userWeekHours[$uid] as $wkNum => $hWk) {
            if ($hWk > $iaLegalMaxHoursWeek) {
                $ltrWeeklyViolations++;
                $ltrViolations[] = "LTr art. 9: {$u['prenom']} {$u['nom']} sem.{$wkNum} = {$hWk}h (>{$iaLegalMaxHoursWeek}h)";
            }
        }
        // Jours consécutifs (LTr art. 21)
        $streak = 0; $maxStreak = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $dateCache[$d]['date'];
            if (isset($userDays[$uid][$date])) { $streak++; if ($streak > $maxStreak) $maxStreak = $streak; }
            else $streak = 0;
        }
        if ($maxStreak > $iaLegalMaxConsecDays) {
            $ltrConsecViolations++;
            $ltrViolations[] = "LTr art. 21: {$u['prenom']} {$u['nom']} {$maxStreak}j consécutifs (>{$iaLegalMaxConsecDays}j)";
        }
        // Repos quotidien (LTr art. 15a) : fin shift J-1 → début shift J
        if (!empty($userShiftTimes[$uid])) {
            $prevDate = null; $prev = null;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = $dateCache[$d]['date'];
                $cur = $userShiftTimes[$uid][$date] ?? null;
                if ($cur && $prev && $prevDate) {
                    // Continuité J-1 → J ?
                    $prevDayNum = (int) substr($prevDate, -2);
                    if ($prevDayNum === $d - 1) {
                        $gap = $prev['end_min_abs'] <= 1440
                            ? (1440 - $prev['end_min_abs']) + $cur['start_min']
                            : $cur['start_min'] - ($prev['end_min_abs'] - 1440);
                        if ($gap < $iaLegalReposQuotidienH * 60) {
                            $ltrRestViolations++;
                            $hGap = round($gap / 60, 1);
                            $ltrViolations[] = "LTr art. 15a: {$u['prenom']} {$u['nom']} repos {$hGap}h entre {$prevDate} et {$date}";
                        }
                    }
                }
                if ($cur) { $prev = $cur; $prevDate = $date; }
            }
        }
    }
    // Plafond journalier (OLT 2 art. 7) : analysé via les durées de shift en DB
    foreach ($horaires as $h) {
        if ((float) $h['duree_effective'] > $iaLegalMaxHoursDay) {
            // Shift lui-même est au-delà du plafond — mais le seul fait de l'utiliser est un risque
            $used = Db::getOne(
                "SELECT COUNT(*) FROM planning_assignations WHERE planning_id = ? AND horaire_type_id = ?",
                [$planningId, $h['id']]
            );
            if ((int) $used > 0) {
                $ltrDailyOverflow += (int) $used;
                $ltrViolations[] = "LTr OLT 2 art. 7: shift {$h['code']} = {$h['duree_effective']}h (>{$iaLegalMaxHoursDay}h) utilisé {$used}×";
            }
        }
    }
    $qualityReport['ltr'] = [
        'max_hours_week' => $iaLegalMaxHoursWeek,
        'max_consec_days' => $iaLegalMaxConsecDays,
        'max_hours_day' => $iaLegalMaxHoursDay,
        'repos_quotidien_h' => $iaLegalReposQuotidienH,
        'weekly_violations' => $ltrWeeklyViolations,
        'consec_violations' => $ltrConsecViolations,
        'daily_overflow' => $ltrDailyOverflow,
        'rest_violations' => $ltrRestViolations,
        'violations' => array_slice($ltrViolations, 0, 20),
        'conforme' => ($ltrWeeklyViolations === 0 && $ltrConsecViolations === 0
                     && $ltrDailyOverflow === 0 && $ltrRestViolations === 0),
    ];

    // 6. Score global (0-100) — pénalisé si violations LTr
    $scoreCoverage = min($qualityReport['coverage_pct'], 100);
    $scoreEquity = max(0, 100 - ($qualityReport['hours_stddev'] * 3)); // -3pts par heure d'écart
    $scoreDesirs = $qualityReport['desirs_pct'];
    $scoreWeekend = 100;
    if (!empty($weekendCounts) && max($weekendCounts) > 0) {
        $wkRange = max($weekendCounts) - min($weekendCounts);
        $scoreWeekend = max(0, 100 - ($wkRange * 10)); // -10pts par jour d'écart
    }
    // Pénalité LTr : chaque violation -15pts (plafonnée)
    $ltrPenalty = min(100, ($ltrWeeklyViolations + $ltrConsecViolations + $ltrDailyOverflow + $ltrRestViolations) * 15);
    $qualityReport['score'] = max(0, round(
        $scoreCoverage * 0.40 +
        $scoreEquity   * 0.25 +
        $scoreDesirs   * 0.20 +
        $scoreWeekend  * 0.15
        - $ltrPenalty
    ));
    $qualityReport['breakdown'] = [
        'coverage'  => round($scoreCoverage, 1),
        'equity'    => round($scoreEquity, 1),
        'desirs'    => round($scoreDesirs, 1),
        'weekends'  => round($scoreWeekend, 1),
    ];

    respond([
        'success' => true,
        'message' => $message,
        'assigned' => $assigned,
        'conflicts' => $conflicts,
        'nb_conflicts' => count($conflicts),
        'mode' => $mode,
        'ia_optimizations' => $iaOptimizations,
        'ia_rejected' => $iaRejected ?? [],
        'ia_cost' => round($iaCostUsd, 6),
        'ia_summary' => $iaResponse['summary'] ?? null,
        'ia_prompt' => $prompt ?? null,
        'duration_ms' => $durationMs,
        'user_warnings' => $userStats,
        'quality' => $qualityReport,
    ]);
}

/**
 * Clear all assignations for a planning (or just for a module)
 */
function admin_clear_planning()
{
    global $params;
    require_admin();

    $planningId = $params['planning_id'] ?? '';
    if (!$planningId) bad_request('planning_id requis');

    $planning = Db::fetch("SELECT * FROM plannings WHERE id = ?", [$planningId]);
    if (!$planning) not_found('Planning non trouvé');
    if ($planning['statut'] === 'final') bad_request('Planning finalisé, impossible de vider. Repassez-le en brouillon.');

    $moduleId = $params['module_id'] ?? null;

    if ($moduleId) {
        $deleted = Db::exec(
            "DELETE FROM planning_assignations WHERE planning_id = ? AND module_id = ?",
            [$planningId, $moduleId]
        );
    } else {
        $deleted = Db::exec(
            "DELETE FROM planning_assignations WHERE planning_id = ?",
            [$planningId]
        );
    }

    respond(['success' => true, 'message' => "$deleted assignations supprimées", 'deleted' => $deleted]);
}

/**
 * Send planning by email to employees
 */
function admin_send_planning_email()
{
    global $params;
    require_admin();

    $planningId = $params['planning_id'] ?? '';
    $mois = $params['mois'] ?? '';
    $dest = $params['dest'] ?? 'all';
    $moduleId = $params['module_id'] ?? null;
    $customMessage = Sanitize::text($params['message'] ?? '', 1000);

    if (!$planningId || !$mois) bad_request('Paramètres manquants');

    $planning = Db::fetch("SELECT * FROM plannings WHERE id = ?", [$planningId]);
    if (!$planning) not_found('Planning non trouvé');

    // Get users to email
    $query = "SELECT DISTINCT u.id, u.email, u.prenom, u.nom
              FROM planning_assignations pa
              JOIN users u ON u.id = pa.user_id
              WHERE pa.planning_id = ? AND u.email IS NOT NULL AND u.email != ''";
    $queryParams = [$planningId];

    if ($dest === 'module' && $moduleId) {
        $query .= " AND pa.module_id = ?";
        $queryParams[] = $moduleId;
    }

    $recipients = Db::fetchAll($query, $queryParams);

    if (empty($recipients)) {
        respond(['success' => false, 'message' => 'Aucun destinataire trouvé']);
        return;
    }

    // Get EMS name for email subject
    $emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'SpocSpace';
    $monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    [$year, $month] = explode('-', $mois);
    $monthLabel = $monthNames[(int)$month - 1] . ' ' . $year;

    $subject = "Planning $monthLabel — $emsNom";
    $sent = 0;

    foreach ($recipients as $r) {
        // Build individual planning for this user
        $assignations = Db::fetchAll(
            "SELECT pa.date_jour, ht.code AS horaire_code, ht.heure_debut, ht.heure_fin,
                    m.code AS module_code, pa.statut
             FROM planning_assignations pa
             JOIN horaires_types ht ON ht.id = pa.horaire_type_id
             JOIN modules m ON m.id = pa.module_id
             WHERE pa.planning_id = ? AND pa.user_id = ?
             ORDER BY pa.date_jour",
            [$planningId, $r['id']]
        );

        $rows = '';
        foreach ($assignations as $a) {
            $date = date('d/m', strtotime($a['date_jour']));
            $jour = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'][(int)date('w', strtotime($a['date_jour']))];
            $rows .= "<tr>
                <td style='padding:4px 8px;border:1px solid #ddd'>$jour $date</td>
                <td style='padding:4px 8px;border:1px solid #ddd;font-weight:700'>{$a['horaire_code']}</td>
                <td style='padding:4px 8px;border:1px solid #ddd'>{$a['heure_debut']} — {$a['heure_fin']}</td>
                <td style='padding:4px 8px;border:1px solid #ddd'>{$a['module_code']}</td>
            </tr>";
        }

        $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
            <h2 style='color:#2c3e50'>Planning — $monthLabel</h2>
            <p>Bonjour {$r['prenom']},</p>";

        if ($customMessage) {
            $body .= "<p>" . nl2br(htmlspecialchars($customMessage)) . "</p>";
        }

        $body .= "<table style='border-collapse:collapse;width:100%;margin:16px 0'>
            <thead><tr style='background:#2c3e50;color:#fff'>
                <th style='padding:6px 8px;text-align:left'>Jour</th>
                <th style='padding:6px 8px;text-align:left'>Horaire</th>
                <th style='padding:6px 8px;text-align:left'>Heures</th>
                <th style='padding:6px 8px;text-align:left'>Module</th>
            </tr></thead>
            <tbody>$rows</tbody>
        </table>
        <p style='color:#888;font-size:12px'>$emsNom — Planning généré automatiquement</p>
        </div>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $emsNom <noreply@zkriva.com>\r\n";

        if (@mail($r['email'], $subject, $body, $headers)) {
            $sent++;
        }
    }

    respond([
        'success' => true,
        'message' => "$sent email(s) envoyé(s) sur " . count($recipients),
        'sent' => $sent,
        'total' => count($recipients),
    ]);
}

/**
 * Get planning archives (all months with a planning)
 */
function admin_get_planning_archives()
{
    require_responsable();

    $archives = Db::fetchAll(
        "SELECT p.mois_annee, p.statut,
                COUNT(DISTINCT pa.user_id) AS nb_users,
                COUNT(pa.id) AS nb_assignations,
                COALESCE(SUM(ht.duree_effective), 0) AS total_hours
         FROM plannings p
         LEFT JOIN planning_assignations pa ON pa.planning_id = p.id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         GROUP BY p.id
         ORDER BY p.mois_annee DESC"
    );

    respond(['success' => true, 'archives' => $archives]);
}
