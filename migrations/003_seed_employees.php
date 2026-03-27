<?php
/**
 * Terrassière - Seed 110 fictitious employees + planning data
 * Run: php migrations/003_seed_employees.php
 */

require_once __DIR__ . '/../init.php';

echo "=== Terrassière Seed Script ===\n\n";

$pdo = Db::connect();

// ── 1. Get existing reference data ──
$fonctions = Db::fetchAll("SELECT id, code FROM fonctions ORDER BY ordre");
$modules   = Db::fetchAll("SELECT id, code FROM modules ORDER BY ordre");
$horaires  = Db::fetchAll("SELECT id, code FROM horaires_types WHERE is_active = 1 ORDER BY code");
$etages    = Db::fetchAll("SELECT id, module_id, code FROM etages ORDER BY ordre");
$groupes   = Db::fetchAll("SELECT id, etage_id, code FROM groupes ORDER BY ordre");

if (empty($fonctions) || empty($modules) || empty($horaires)) {
    echo "ERROR: Reference data missing. Run 001_initial.sql first.\n";
    exit(1);
}

// Index by code
$fMap = array_column($fonctions, 'id', 'code');
$mMap = array_column($modules, 'id', 'code');
$hMap = array_column($horaires, 'id', 'code');

echo "Fonctions: " . implode(', ', array_keys($fMap)) . "\n";
echo "Modules:   " . implode(', ', array_keys($mMap)) . "\n";
echo "Horaires:  " . implode(', ', array_keys($hMap)) . "\n\n";

// ── 2. Employee data ──
// Swiss-French first names & last names
$prenomsFemmes = [
    'Marie', 'Nathalie', 'Isabelle', 'Valérie', 'Sylvie', 'Céline', 'Stéphanie',
    'Christine', 'Sandrine', 'Catherine', 'Florence', 'Laurence', 'Sophie', 'Véronique',
    'Patricia', 'Delphine', 'Caroline', 'Aurélie', 'Émilie', 'Julie', 'Mélanie',
    'Laetitia', 'Virginie', 'Corinne', 'Martine', 'Monique', 'Anne', 'Pascale',
    'Fabienne', 'Dominique', 'Chantal', 'Joëlle', 'Brigitte', 'Françoise', 'Eliane',
    'Muriel', 'Carole', 'Sabine', 'Michèle', 'Nicole', 'Aline', 'Estelle',
    'Manon', 'Léa', 'Camille', 'Morgane', 'Clara', 'Noémie', 'Anaïs', 'Jade',
    'Élodie', 'Audrey', 'Marion', 'Lucie', 'Justine', 'Amandine', 'Pauline',
    'Alexandra', 'Inès', 'Charlotte', 'Zoé', 'Lara', 'Sarah', 'Lisa', 'Eva',
];
$prenomsHommes = [
    'Pierre', 'Jean', 'Michel', 'Alain', 'Philippe', 'Patrick', 'André',
    'Christophe', 'Laurent', 'Didier', 'Stéphane', 'Thierry', 'Frédéric',
    'Olivier', 'Daniel', 'Pascal', 'Bruno', 'Marc', 'David', 'Éric',
    'Nicolas', 'Sébastien', 'Julien', 'Thomas', 'Antoine', 'Mathieu',
    'Romain', 'Maxime', 'Alexandre', 'Jérôme', 'François', 'Raphaël',
    'Yannick', 'Fabien', 'Guillaume', 'Cédric', 'Damien', 'Benoît',
    'Loïc', 'Hugo', 'Léo', 'Nathan', 'Lucas', 'Gabriel', 'Samuel',
];
$noms = [
    'Dupont', 'Martin', 'Bernard', 'Robert', 'Petit', 'Durand', 'Leroy',
    'Moreau', 'Simon', 'Laurent', 'Michel', 'Garcia', 'Thomas', 'Blanc',
    'Favre', 'Roux', 'Fontaine', 'Chevalier', 'Morel', 'Girard', 'André',
    'Muller', 'Lefèvre', 'Mercier', 'Bonnet', 'Lambert', 'Perrin', 'Richard',
    'Vuilleumier', 'Bühler', 'Schneider', 'Meier', 'Weber', 'Zimmermann',
    'Rochat', 'Bovey', 'Pittet', 'Chapuis', 'Delay', 'Pochon', 'Jacot',
    'Magnin', 'Berset', 'Nussbaum', 'Perret', 'Jolivet', 'Tissot', 'Renaud',
    'Dubois', 'Guex', 'Chollet', 'Cosandey', 'Piguet', 'Rey', 'Monnier',
    'Baumgartner', 'Aubert', 'Tschannen', 'Corpataux', 'Savary', 'Meylan',
];

// Employee definitions: [fonction_code, role, taux, type_contrat, module_codes]
// Distribute realistically across an EMS
$employeeTemplates = [];

// ── Direction (2) ──
$employeeTemplates[] = ['RS',   'direction',     100, 'CDI', ['M1','M2','M3']];
$employeeTemplates[] = ['RUV',  'direction',     100, 'CDI', ['M1','M2','M3','M4']];

// ── Responsables d'unité (6 — one per module) ──
$employeeTemplates[] = ['RUV',  'responsable',   100, 'CDI', ['M1']];
$employeeTemplates[] = ['RUV',  'responsable',   100, 'CDI', ['M2']];
$employeeTemplates[] = ['RUV',  'responsable',   100, 'CDI', ['M3']];
$employeeTemplates[] = ['RUV',  'responsable',   100, 'CDI', ['M4']];
$employeeTemplates[] = ['INF',  'responsable',   100, 'CDI', ['NUIT']];
$employeeTemplates[] = ['INF',  'responsable',   100, 'CDI', ['POOL']];

// ── Module 1 — Étages 1+2 (18 staff) ──
for ($i = 0; $i < 6; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['M1']];
for ($i = 0; $i < 5; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['M1']];
$tauxOptions3 = [80,90,100];
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['AS',   'collaborateur', $tauxOptions3[array_rand($tauxOptions3)], 'CDI', ['M1']];
$employeeTemplates[] = ['AS',   'collaborateur', 60,  'CDD', ['M1']];
$employeeTemplates[] = ['APP',  'collaborateur', 100, 'stagiaire', ['M1']];
$employeeTemplates[] = ['CIV',  'collaborateur', 100, 'civiliste', ['M1']];

// ── Module 2 — Étage 3 (14 staff) ──
for ($i = 0; $i < 5; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['M2']];
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['M2']];
for ($i = 0; $i < 3; $i++) $employeeTemplates[] = ['AS',   'collaborateur', [80,100][array_rand([80,100])], 'CDI', ['M2']];
$employeeTemplates[] = ['APP',  'collaborateur', 100, 'stagiaire', ['M2']];
$employeeTemplates[] = ['CIV',  'collaborateur', 100, 'civiliste', ['M2']];

// ── Module 3 — Étages 5+6 (18 staff) ──
for ($i = 0; $i < 6; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['M3']];
for ($i = 0; $i < 5; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['M3']];
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['AS',   'collaborateur', [80,90,100][array_rand([80,90,100])], 'CDI', ['M3']];
$employeeTemplates[] = ['AS',   'collaborateur', 50,  'CDD', ['M3']];
$employeeTemplates[] = ['APP',  'collaborateur', 100, 'stagiaire', ['M3']];
$employeeTemplates[] = ['CIV',  'collaborateur', 100, 'civiliste', ['M3']];

// ── Module 4 — Accueil de jour (8 staff) ──
for ($i = 0; $i < 2; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['M4']];
for ($i = 0; $i < 2; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['M4']];
for ($i = 0; $i < 2; $i++) $employeeTemplates[] = ['ASE',  'collaborateur', [80,100][array_rand([80,100])], 'CDI', ['M4']];
$employeeTemplates[] = ['AS',   'collaborateur', 80,  'CDI', ['M4']];
$employeeTemplates[] = ['APP',  'collaborateur', 100, 'stagiaire', ['M4']];

// ── Nuit (12 staff) ──
for ($i = 0; $i < 5; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['NUIT']];
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['NUIT']];
for ($i = 0; $i < 3; $i++) $employeeTemplates[] = ['AS',   'collaborateur', [80,90,100][array_rand([80,90,100])], 'CDI', ['NUIT']];

// ── Pool / Remplaçants (12 staff — multi-module) ──
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 100, 'CDI', ['POOL','M1','M2','M3']];
for ($i = 0; $i < 4; $i++) $employeeTemplates[] = ['ASSC', 'collaborateur', 100, 'CDI', ['POOL','M1','M3']];
for ($i = 0; $i < 2; $i++) $employeeTemplates[] = ['AS',   'collaborateur', 100, 'CDI', ['POOL','M1','M2']];
for ($i = 0; $i < 2; $i++) $employeeTemplates[] = ['INF',  'collaborateur', 60,  'interim', ['POOL']];

// ── Extra part-timers (10 — various) ──
$employeeTemplates[] = ['INF',  'collaborateur', 50,  'CDI', ['M1']];
$employeeTemplates[] = ['INF',  'collaborateur', 60,  'CDI', ['M2']];
$employeeTemplates[] = ['ASSC', 'collaborateur', 50,  'CDI', ['M3']];
$employeeTemplates[] = ['ASSC', 'collaborateur', 70,  'CDI', ['M1']];
$employeeTemplates[] = ['AS',   'collaborateur', 50,  'CDI', ['M2']];
$employeeTemplates[] = ['AS',   'collaborateur', 60,  'CDI', ['M4']];
$employeeTemplates[] = ['INF',  'collaborateur', 80,  'CDD', ['M3']];
$employeeTemplates[] = ['ASSC', 'collaborateur', 80,  'CDD', ['NUIT']];
$employeeTemplates[] = ['INF',  'collaborateur', 40,  'CDI', ['M1']];
$employeeTemplates[] = ['AS',   'collaborateur', 40,  'CDI', ['M3']];

$totalEmployees = count($employeeTemplates);
echo "Generating {$totalEmployees} employees...\n";

// Default password: "Terr2026!" hashed
$defaultPassword = password_hash('Terr2026!', PASSWORD_BCRYPT, ['cost' => 12]);

// Shuffle name pools
shuffle($prenomsFemmes);
shuffle($prenomsHommes);
shuffle($noms);

$nameIdx = 0;
$femmeIdx = 0;
$hommeIdx = 0;
$usedEmails = [];
$createdUsers = [];

$pdo->beginTransaction();

try {
    // ── Insert employees ──
    $stmtUser = $pdo->prepare("
        INSERT INTO users (id, employee_id, email, password, nom, prenom, telephone,
                           fonction_id, taux, type_contrat, date_entree, solde_vacances,
                           role, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmtModule = $pdo->prepare("
        INSERT INTO user_modules (user_id, module_id, is_principal)
        VALUES (?, ?, ?)
    ");

    foreach ($employeeTemplates as $i => $tpl) {
        [$fonctionCode, $role, $taux, $contrat, $moduleCodes] = $tpl;

        // Pick name (alternate M/F, ~70% female like real EMS)
        $isFemale = ($i % 10) < 7;
        if ($isFemale) {
            $prenom = $prenomsFemmes[$femmeIdx % count($prenomsFemmes)];
            $femmeIdx++;
        } else {
            $prenom = $prenomsHommes[$hommeIdx % count($prenomsHommes)];
            $hommeIdx++;
        }
        $nom = $noms[$nameIdx % count($noms)];
        $nameIdx++;

        // Unique email
        $emailBase = strtolower(
            str_replace(
                ['é','è','ê','ë','à','â','ä','ù','û','ü','ô','ö','î','ï','ç','ñ'],
                ['e','e','e','e','a','a','a','u','u','u','o','o','i','i','c','n'],
                $prenom . '.' . $nom
            )
        );
        $emailBase = preg_replace('/[^a-z0-9.]/', '', $emailBase);
        $email = $emailBase . '@terrassiere.ch';
        $suffix = 2;
        while (isset($usedEmails[$email])) {
            $email = $emailBase . $suffix . '@terrassiere.ch';
            $suffix++;
        }
        $usedEmails[$email] = true;

        $userId = Uuid::v4();
        $employeeId = 'EMS-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
        $phone = '+41 22 ' . rand(300, 399) . ' ' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT) . ' ' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);

        // Entry date: spread over the last 10 years
        $daysAgo = rand(30, 3650);
        $dateEntree = date('Y-m-d', strtotime("-{$daysAgo} days"));

        // Vacation days: based on taux and seniority
        $yearsService = $daysAgo / 365;
        $baseVacances = $yearsService > 5 ? 25 : 20;
        $soldeVacances = round($baseVacances * ($taux / 100) * (rand(20, 95) / 100), 1);

        $fonctionId = $fMap[$fonctionCode] ?? null;

        $stmtUser->execute([
            $userId, $employeeId, $email, $defaultPassword,
            $nom, $prenom, $phone, $fonctionId,
            $taux, $contrat, $dateEntree, $soldeVacances, $role
        ]);

        // Assign modules
        foreach ($moduleCodes as $j => $mCode) {
            if (isset($mMap[$mCode])) {
                $stmtModule->execute([$userId, $mMap[$mCode], $j === 0 ? 1 : 0]);
            }
        }

        $createdUsers[] = [
            'id' => $userId,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'role' => $role,
            'fonction' => $fonctionCode,
            'taux' => $taux,
            'modules' => $moduleCodes,
        ];

        echo ".";
    }

    echo "\n{$totalEmployees} employees created.\n\n";

    // ── 3. Create planning for current month + next month ──
    $currentMonth = date('Y-m');
    $nextMonth = date('Y-m', strtotime('+1 month'));

    foreach ([$currentMonth, $nextMonth] as $mois) {
        $existing = Db::getOne("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
        if ($existing) {
            echo "Planning {$mois} already exists, skipping.\n";
            continue;
        }

        $planningId = Uuid::v4();
        $statut = ($mois === $currentMonth) ? 'final' : 'brouillon';
        Db::exec(
            "INSERT INTO plannings (id, mois_annee, statut, genere_at, created_at)
             VALUES (?, ?, ?, NOW(), NOW())",
            [$planningId, $mois, $statut]
        );
        echo "Created planning {$mois} ({$statut})\n";

        // ── Generate assignations for this month ──
        $firstDay = $mois . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));
        $daysInMonth = (int) date('t', strtotime($firstDay));

        $horaireCodes = array_keys($hMap);
        // Morning shifts for day staff, night for NUIT
        $dayShifts   = array_intersect(['A1','A2','A3','D1','D3','D4','S3','S4'], $horaireCodes);
        $dayShifts   = array_values($dayShifts);
        $adminShift  = in_array('A6', $horaireCodes) ? 'A6' : ($dayShifts[0] ?? null);

        $stmtAssign = $pdo->prepare("
            INSERT INTO planning_assignations
                (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $assignCount = 0;
        foreach ($createdUsers as $u) {
            $principalModule = $mMap[$u['modules'][0]] ?? null;
            $isNuit = in_array('NUIT', $u['modules']);
            $isPool = in_array('POOL', $u['modules']);

            // Determine work pattern based on taux
            $workRatio = $u['taux'] / 100;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = sprintf('%s-%02d', $mois, $d);
                $dow = (int) date('N', strtotime($date)); // 1=Mon 7=Sun

                // Direction/responsable: Mon-Fri only
                if (in_array($u['role'], ['direction', 'responsable'])) {
                    if ($dow >= 6) continue; // weekend off
                    $horaireId = $hMap[$adminShift] ?? null;
                    $stmtAssign->execute([
                        Uuid::v4(), $planningId, $u['id'], $date,
                        $horaireId, $principalModule, 'present'
                    ]);
                    $assignCount++;
                    continue;
                }

                // Night staff: specific pattern (4 nights on, 2 off)
                if ($isNuit) {
                    $cycleDay = ($d - 1) % 6;
                    if ($cycleDay >= 4) continue; // 2 days off per 6-day cycle
                    // Check taux
                    if ($workRatio < 1 && (($d % 3) === 0)) continue;
                    $stmtAssign->execute([
                        Uuid::v4(), $planningId, $u['id'], $date,
                        $hMap['D3'] ?? $hMap[$dayShifts[0]] ?? null,
                        $principalModule, 'present'
                    ]);
                    $assignCount++;
                    continue;
                }

                // Pool: work ~4 days/week, rotate modules
                if ($isPool) {
                    if ($dow === 7) continue; // Sunday off
                    if (rand(1, 7) > 5) continue; // ~70% presence
                    if ($workRatio < 1 && rand(1, 100) > ($workRatio * 100)) continue;

                    // Rotate through available modules
                    $rotateModules = array_filter($u['modules'], fn($m) => $m !== 'POOL');
                    if (empty($rotateModules)) $rotateModules = ['POOL'];
                    $assignModule = $mMap[$rotateModules[($d - 1) % count($rotateModules)]] ?? $principalModule;

                    $shift = $dayShifts[array_rand($dayShifts)];
                    $stmtAssign->execute([
                        Uuid::v4(), $planningId, $u['id'], $date,
                        $hMap[$shift], $assignModule, 'present'
                    ]);
                    $assignCount++;
                    continue;
                }

                // Regular day staff: ~5 days/week pattern with weekends
                // Each person gets 2 days off per week (mostly weekends but not always)
                $offDay1 = ($d + crc32($u['id'])) % 7; // Pseudo-random but consistent per user
                $offDay2 = ($offDay1 + 1) % 7;
                $dayOfWeek = ($dow - 1); // 0=Mon
                if ($dayOfWeek === $offDay1 || $dayOfWeek === $offDay2) continue;

                // Part-time: skip additional days
                if ($workRatio < 1) {
                    $workDaysPerWeek = round(5 * $workRatio);
                    if (($d % round(5 / max(1, 5 - $workDaysPerWeek))) === 0) continue;
                }

                // Pick shift: mostly morning for INF, varied for others
                if ($u['fonction'] === 'INF') {
                    $shift = $dayShifts[($d + ord($u['id'][0])) % min(4, count($dayShifts))];
                } elseif ($u['fonction'] === 'ASE') {
                    $shift = $dayShifts[($d) % count($dayShifts)];
                } else {
                    $shift = $dayShifts[($d + ord($u['id'][1])) % count($dayShifts)];
                }

                $stmtAssign->execute([
                    Uuid::v4(), $planningId, $u['id'], $date,
                    $hMap[$shift], $principalModule, 'present'
                ]);
                $assignCount++;
            }
        }
        echo "  → {$assignCount} assignations for {$mois}\n";
    }

    // ── 4. Seed some désirs for next month ──
    echo "\nSeeding désirs for {$nextMonth}...\n";
    $desirCount = 0;
    $desirTypes = ['jour_off', 'horaire_special'];
    $desirDetails = [
        'Rendez-vous médical',
        'Formation continue',
        'Anniversaire familial',
        'Rendez-vous administratif',
        'Garde enfant',
        'Déménagement',
        null, null, null, // some without detail
    ];

    // ~30 employees submit désirs
    $desirUsers = array_slice($createdUsers, 0, 30);
    shuffle($desirUsers);

    foreach ($desirUsers as $u) {
        if (in_array($u['role'], ['direction'])) continue;

        $nbDesirs = rand(1, 4);
        $usedDates = [];
        for ($j = 0; $j < $nbDesirs; $j++) {
            $day = rand(1, (int) date('t', strtotime($nextMonth . '-01')));
            $date = sprintf('%s-%02d', $nextMonth, $day);
            if (in_array($date, $usedDates)) continue;
            $usedDates[] = $date;

            $type = $desirTypes[array_rand($desirTypes)];
            $detail = ($type === 'horaire_special') ? $desirDetails[array_rand($desirDetails)] : null;
            $statut = ['en_attente', 'en_attente', 'en_attente', 'valide', 'refuse'][array_rand([0,1,2,3,4])];

            Db::exec(
                "INSERT INTO desirs (id, user_id, date_souhaitee, type, detail, statut, mois_cible, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [Uuid::v4(), $u['id'], $date, $type, $detail, $statut, $nextMonth]
            );
            $desirCount++;
        }
    }
    echo "{$desirCount} désirs created.\n";

    // ── 5. Seed some absences ──
    echo "\nSeeding absences...\n";
    $absenceCount = 0;
    $absenceTypes = ['vacances', 'maladie', 'accident', 'conge_special', 'formation', 'autre'];
    $absenceMotifs = [
        'vacances' => ['Vacances annuelles', 'Vacances famille', 'Séjour à la montagne', null],
        'maladie' => ['Grippe', 'Covid', 'Mal de dos', null],
        'accident' => ['Accident domestique', 'Chute', null],
        'conge_special' => ['Mariage', 'Naissance', 'Décès proche', 'Déménagement'],
        'formation' => ['CAS soins palliatifs', 'Journée de formation interne', 'Congrès infirmier'],
        'autre' => ['Motif personnel', null],
    ];

    // ~25 employees have absences
    $absenceUsers = array_slice($createdUsers, 10, 25);
    foreach ($absenceUsers as $u) {
        $nbAbsences = rand(1, 3);
        for ($j = 0; $j < $nbAbsences; $j++) {
            $type = $absenceTypes[array_rand($absenceTypes)];
            // Duration depends on type
            $durations = [
                'vacances' => rand(3, 14),
                'maladie' => rand(1, 7),
                'accident' => rand(5, 30),
                'conge_special' => rand(1, 5),
                'formation' => rand(1, 3),
                'autre' => rand(1, 2),
            ];
            $duration = $durations[$type];

            // Spread across current and next months
            $startOffset = rand(-15, 30);
            $dateDebut = date('Y-m-d', strtotime("{$startOffset} days"));
            $dateFin = date('Y-m-d', strtotime("{$startOffset} days +{$duration} days"));

            $motifs = $absenceMotifs[$type];
            $motif = $motifs[array_rand($motifs)];

            $statut = ['en_attente', 'valide', 'valide', 'valide', 'refuse'][rand(0, 4)];
            $justifie = ($type === 'maladie' && $duration > 3) ? 1 : (rand(0, 1));

            $remplacementTypes = ['collegue', 'interim', 'entraide', 'vacant', null];
            $remplacement = ($statut === 'valide') ? $remplacementTypes[array_rand($remplacementTypes)] : null;

            Db::exec(
                "INSERT INTO absences (id, user_id, date_debut, date_fin, type, motif, justifie,
                                       statut, remplacement_type, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [Uuid::v4(), $u['id'], $dateDebut, $dateFin, $type, $motif, $justifie, $statut, $remplacement]
            );
            $absenceCount++;
        }
    }
    echo "{$absenceCount} absences created.\n";

    // ── 6. Seed some messages ──
    echo "\nSeeding messages...\n";
    $msgCount = 0;
    $msgSujets = [
        'Demande de changement d\'horaire',
        'Question planning',
        'Échange de service',
        'Information absence',
        'Demande de vacances supplémentaires',
        'Problème de planning',
        'Proposition échange weekend',
        'Question sur solde vacances',
        'Demande formation',
        'Signalement matériel',
    ];
    $msgContenus = [
        'Bonjour, serait-il possible de modifier mon horaire pour la semaine prochaine ? Merci.',
        'Je souhaiterais échanger mon service du mardi avec un collègue. Est-ce envisageable ?',
        'Pourriez-vous m\'indiquer mon solde de vacances actuel ? Merci d\'avance.',
        'Suite à notre discussion, je confirme ma disponibilité pour le remplacement.',
        'Je me permets de vous écrire concernant mon planning du mois prochain.',
        'Merci de bien vouloir prendre en compte ma demande.',
        'J\'aimerais participer à la formation proposée le mois prochain.',
        'Bonjour, j\'ai un empêchement le 15 et souhaite permuter avec le 17. Cordialement.',
    ];

    // 20 messages from random employees to direction (to_user_id = null)
    for ($i = 0; $i < 20; $i++) {
        $sender = $createdUsers[rand(0, count($createdUsers) - 1)];
        Db::exec(
            "INSERT INTO messages (id, from_user_id, to_user_id, sujet, contenu, lu, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, NOW() - INTERVAL ? DAY)",
            [
                Uuid::v4(), $sender['id'],
                $msgSujets[array_rand($msgSujets)],
                $msgContenus[array_rand($msgContenus)],
                rand(0, 1),
                rand(0, 30)
            ]
        );
        $msgCount++;
    }
    echo "{$msgCount} messages created.\n";

    // ── 7. Seed besoins de couverture ──
    echo "\nSeeding besoins de couverture...\n";
    $besoinCount = 0;
    // For each module (except POOL), set staffing needs per day of week
    $moduleNeeds = [
        'M1' => ['INF' => 2, 'ASSC' => 2, 'AS' => 1],
        'M2' => ['INF' => 2, 'ASSC' => 1, 'AS' => 1],
        'M3' => ['INF' => 2, 'ASSC' => 2, 'AS' => 1],
        'M4' => ['INF' => 1, 'ASSC' => 1, 'ASE' => 1],
        'NUIT' => ['INF' => 2, 'ASSC' => 1, 'AS' => 1],
    ];

    foreach ($moduleNeeds as $mCode => $needs) {
        if (!isset($mMap[$mCode])) continue;
        foreach ($needs as $fCode => $nb) {
            if (!isset($fMap[$fCode])) continue;
            for ($dow = 1; $dow <= 7; $dow++) {
                // Weekends need fewer staff for some modules
                $required = $nb;
                if ($dow >= 6 && $mCode === 'M4') $required = 0; // Accueil de jour fermé weekend
                if ($dow >= 6) $required = max(1, $required - 1); // Slightly less on weekends

                if ($required <= 0) continue;

                Db::exec(
                    "INSERT INTO besoins_couverture (id, module_id, jour_semaine, fonction_id, nb_requis)
                     VALUES (?, ?, ?, ?, ?)",
                    [Uuid::v4(), $mMap[$mCode], $dow, $fMap[$fCode], $required]
                );
                $besoinCount++;
            }
        }
    }
    echo "{$besoinCount} besoins de couverture created.\n";

    // ── 8. Seed changements d'horaire ──
    echo "\nSeeding changements d'horaire...\n";
    $changementCount = 0;

    $currentPlanningId = Db::getOne("SELECT id FROM plannings WHERE mois_annee = ?", [date('Y-m')]);

    if ($currentPlanningId) {
        // Find an admin/direction user to act as traite_par
        $adminUser = null;
        foreach ($createdUsers as $u) {
            if (in_array($u['role'], ['admin', 'direction'])) { $adminUser = $u; break; }
        }

        $statuts       = ['en_attente_collegue', 'confirme_collegue', 'valide', 'refuse'];
        $motifs        = ['Rendez-vous médical', 'Obligation familiale', 'Formation personnelle', 'Échange convenu', null, null];
        $raisonsRefus  = ['Déjà absent ce jour-là', 'Impossibilité d\'assurer le service', 'Formation prévue'];

        // Take up to 40 non-direction users to form pairs A↔B
        $pool = array_values(array_filter($createdUsers, fn($u) => !in_array($u['role'], ['direction'])));
        shuffle($pool);
        $pool = array_slice($pool, 0, 40);

        // Preload one assignation per user (random date in current month)
        $assignByUser = [];
        foreach ($pool as $u) {
            $rows = Db::fetchAll(
                "SELECT id, date_jour FROM planning_assignations
                  WHERE planning_id = ? AND user_id = ? AND statut = 'present'
                  ORDER BY date_jour LIMIT 10",
                [$currentPlanningId, $u['id']]
            );
            if (!empty($rows)) $assignByUser[$u['id']] = $rows;
        }

        for ($i = 0; $i + 1 < count($pool); $i += 2) {
            $userA = $pool[$i];
            $userB = $pool[$i + 1];

            $rowsA = $assignByUser[$userA['id']] ?? [];
            $rowsB = $assignByUser[$userB['id']] ?? [];
            if (empty($rowsA) || empty($rowsB)) continue;

            // ── Demande 1: A → B (A est demandeur) ──
            $aRow1 = $rowsA[0];
            $bRow1 = $rowsB[0];

            $statut1 = $statuts[array_rand($statuts)];
            $motif1  = $motifs[array_rand($motifs)];
            $confirme1  = in_array($statut1, ['confirme_collegue','valide','refuse'])
                ? date('Y-m-d H:i:s', strtotime('-' . rand(2,8) . ' days')) : null;
            $traiteAt1  = in_array($statut1, ['valide','refuse'])
                ? date('Y-m-d H:i:s', strtotime('-' . rand(0,3) . ' days')) : null;
            $refusePar1 = ($statut1 === 'refuse') ? (['collegue','admin'][rand(0,1)]) : null;
            $raison1    = ($statut1 === 'refuse') ? $raisonsRefus[array_rand($raisonsRefus)] : null;
            $traitePar1 = ($traiteAt1 && $adminUser) ? $adminUser['id'] : null;

            Db::exec(
                "INSERT INTO changements_horaire
                    (id, demandeur_id, destinataire_id, planning_id, date_jour,
                     assignation_demandeur_id, assignation_destinataire_id,
                     motif, statut, refuse_par, raison_refus,
                     confirme_at, traite_par, traite_at, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)",
                [
                    Uuid::v4(), $userA['id'], $userB['id'], $currentPlanningId,
                    $aRow1['date_jour'], $aRow1['id'], $bRow1['id'],
                    $motif1, $statut1, $refusePar1, $raison1,
                    $confirme1, $traitePar1, $traiteAt1,
                    rand(1, 20),
                ]
            );
            $changementCount++;

            // ── Demande 2: B → A (B est demandeur — chaque user a les deux rôles) ──
            // Use different assignations to avoid issues with cascade deletes
            $aRow2 = $rowsA[count($rowsA) > 1 ? 1 : 0];
            $bRow2 = $rowsB[count($rowsB) > 1 ? 1 : 0];

            $statut2 = $statuts[array_rand($statuts)];
            $motif2  = $motifs[array_rand($motifs)];
            $confirme2  = in_array($statut2, ['confirme_collegue','valide','refuse'])
                ? date('Y-m-d H:i:s', strtotime('-' . rand(2,8) . ' days')) : null;
            $traiteAt2  = in_array($statut2, ['valide','refuse'])
                ? date('Y-m-d H:i:s', strtotime('-' . rand(0,3) . ' days')) : null;
            $refusePar2 = ($statut2 === 'refuse') ? (['collegue','admin'][rand(0,1)]) : null;
            $raison2    = ($statut2 === 'refuse') ? $raisonsRefus[array_rand($raisonsRefus)] : null;
            $traitePar2 = ($traiteAt2 && $adminUser) ? $adminUser['id'] : null;

            Db::exec(
                "INSERT INTO changements_horaire
                    (id, demandeur_id, destinataire_id, planning_id, date_jour,
                     assignation_demandeur_id, assignation_destinataire_id,
                     motif, statut, refuse_par, raison_refus,
                     confirme_at, traite_par, traite_at, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)",
                [
                    Uuid::v4(), $userB['id'], $userA['id'], $currentPlanningId,
                    $bRow2['date_jour'], $bRow2['id'], $aRow2['id'],
                    $motif2, $statut2, $refusePar2, $raison2,
                    $confirme2, $traitePar2, $traiteAt2,
                    rand(1, 20),
                ]
            );
            $changementCount++;
        }
    }
    echo "{$changementCount} changements d'horaire created.\n";

    $pdo->commit();
    echo "\n=== DONE ===\n";
    echo "Total employees: {$totalEmployees}\n";
    echo "Default password: Terr2026!\n";
    echo "Default admin: admin@terrassiere.ch / Admin2026!\n\n";

    // Summary by role
    $roles = array_count_values(array_column($createdUsers, 'role'));
    echo "By role:\n";
    foreach ($roles as $r => $c) echo "  {$r}: {$c}\n";

    // Summary by function
    $foncts = array_count_values(array_column($createdUsers, 'fonction'));
    echo "\nBy function:\n";
    foreach ($foncts as $f => $c) echo "  {$f}: {$c}\n";

    // Summary by module
    $modCount = [];
    foreach ($createdUsers as $u) {
        foreach ($u['modules'] as $m) {
            $modCount[$m] = ($modCount[$m] ?? 0) + 1;
        }
    }
    echo "\nBy module (with multi-assignments):\n";
    foreach ($modCount as $m => $c) echo "  {$m}: {$c}\n";

} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
