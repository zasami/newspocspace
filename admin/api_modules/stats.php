<?php

function admin_get_absence_stats()
{
    require_responsable();
    global $params;

    $period = $params['period'] ?? 'month';
    $date   = $params['date'] ?? date('Y-m-d');

    if (!in_array($period, ['week', 'month', 'year'], true)) {
        bad_request('Période invalide (week, month, year)');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        bad_request('Date invalide (format YYYY-MM-DD)');
    }

    // --- Calculate date range ---
    $dt = new DateTimeImmutable($date);

    switch ($period) {
        case 'week':
            $dow = (int) $dt->format('N'); // 1=Mon … 7=Sun
            $dateDebut = $dt->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
            $dateFin   = $dt->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');
            break;
        case 'month':
            $dateDebut = $dt->format('Y-m-01');
            $dateFin   = $dt->format('Y-m-t');
            break;
        case 'year':
            $dateDebut = $dt->format('Y-01-01');
            $dateFin   = $dt->format('Y-12-31');
            break;
    }

    $yearStart = $dt->format('Y-01-01');
    $yearEnd   = $dt->format('Y-12-31');

    // --- 1. All absences overlapping the period (with user details) ---
    $absences = Db::fetchAll(
        "SELECT a.id, a.user_id, a.date_debut, a.date_fin, a.type, a.motif,
                a.statut, a.justifie, a.justificatif_path,
                u.prenom, u.nom, u.photo, u.taux_activite,
                f.nom AS fonction_nom
         FROM absences a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE a.date_debut <= ? AND a.date_fin >= ?
           AND a.statut != 'refuse'
         ORDER BY a.date_debut ASC",
        [$dateFin, $dateDebut]
    );

    // --- Helper: count business days between two dates (clamped to period) ---
    $countBusinessDays = function (string $start, string $end, string $clampStart = null, string $clampEnd = null): int {
        $s = max($start, $clampStart ?? $start);
        $e = min($end, $clampEnd ?? $end);
        if ($s > $e) return 0;

        $days = 0;
        $current = new DateTimeImmutable($s);
        $last    = new DateTimeImmutable($e);
        while ($current <= $last) {
            $dow = (int) $current->format('N');
            if ($dow <= 5) $days++;
            $current = $current->modify('+1 day');
        }
        return $days;
    };

    // --- 2. Build per-user data & global stats ---
    $parType = [
        'vacances' => 0, 'maladie' => 0, 'accident' => 0,
        'formation' => 0, 'conge_special' => 0, 'autre' => 0,
    ];
    $totalJours    = 0;
    $justifiees    = 0;
    $nonJustifiees = 0;
    $usersMap      = []; // user_id => collaborateur data

    foreach ($absences as $a) {
        $jours = $countBusinessDays($a['date_debut'], $a['date_fin'], $dateDebut, $dateFin);

        $totalJours += $jours;
        if (isset($parType[$a['type']])) {
            $parType[$a['type']] += $jours;
        }
        if ($a['justifie']) {
            $justifiees++;
        } else {
            $nonJustifiees++;
        }

        // Per-user accumulation
        $uid = $a['user_id'];
        if (!isset($usersMap[$uid])) {
            $usersMap[$uid] = [
                'id'              => $uid,
                'prenom'          => $a['prenom'],
                'nom'             => $a['nom'],
                'photo'           => $a['photo'],
                'fonction_nom'    => $a['fonction_nom'],
                'taux'            => $a['taux_activite'],
                'nb_jours_periode' => 0,
                'nb_jours_annee'  => 0,
                'absences'        => [],
                'vacances_adjacentes' => [],
            ];
        }

        $usersMap[$uid]['nb_jours_periode'] += $jours;
        $usersMap[$uid]['absences'][] = [
            'id'            => $a['id'],
            'type'          => $a['type'],
            'date_debut'    => $a['date_debut'],
            'date_fin'      => $a['date_fin'],
            'motif'         => $a['motif'],
            'statut'        => $a['statut'],
            'duree_jours'   => $countBusinessDays($a['date_debut'], $a['date_fin']),
            'has_justificatif' => (bool) $a['justifie'],
        ];
    }

    // --- 3. Year-to-date totals for each absent user ---
    if (!empty($usersMap)) {
        $userIds = array_keys($usersMap);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $yearAbsences = Db::fetchAll(
            "SELECT user_id, date_debut, date_fin
             FROM absences
             WHERE user_id IN ($placeholders)
               AND date_debut <= ? AND date_fin >= ?
               AND statut != 'refuse'",
            array_merge($userIds, [$yearEnd, $yearStart])
        );
        foreach ($yearAbsences as $ya) {
            $uid = $ya['user_id'];
            if (isset($usersMap[$uid])) {
                $usersMap[$uid]['nb_jours_annee'] += $countBusinessDays(
                    $ya['date_debut'], $ya['date_fin'], $yearStart, $yearEnd
                );
            }
        }
    }

    // --- 4. Vacances adjacentes detection ---
    // For non-vacances absences, check if the same user had vacances within 2 days
    foreach ($usersMap as $uid => &$collab) {
        foreach ($collab['absences'] as $abs) {
            if ($abs['type'] === 'vacances') continue;

            $absStart = $abs['date_debut'];
            $absEnd   = $abs['date_fin'];

            // Find vacances for this user that are adjacent (within 2 days)
            $adjacentes = Db::fetchAll(
                "SELECT id, date_debut, date_fin
                 FROM absences
                 WHERE user_id = ?
                   AND type = 'vacances'
                   AND statut != 'refuse'
                   AND (
                       (date_fin >= DATE_SUB(?, INTERVAL 2 DAY) AND date_fin < ?)
                       OR
                       (date_debut > ? AND date_debut <= DATE_ADD(?, INTERVAL 2 DAY))
                   )",
                [$uid, $absStart, $absStart, $absEnd, $absEnd]
            );

            foreach ($adjacentes as $vac) {
                $position = ($vac['date_fin'] < $absStart) ? 'avant' : 'apres';
                $collab['vacances_adjacentes'][] = [
                    'absence_id'    => $abs['id'],
                    'vacance_dates' => $vac['date_debut'] . ' - ' . $vac['date_fin'],
                    'position'      => $position,
                ];
            }
        }
    }
    unset($collab);

    // --- 5. Chart data ---
    $chartData = [];

    switch ($period) {
        case 'year':
            // Group by month
            for ($m = 1; $m <= 12; $m++) {
                $mStr = $dt->format('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                $mStart = $mStr . '-01';
                $mEnd   = date('Y-m-t', strtotime($mStart));
                $jours = 0;
                foreach ($absences as $a) {
                    $jours += $countBusinessDays($a['date_debut'], $a['date_fin'], $mStart, $mEnd);
                }
                $chartData[] = ['label' => $mStr, 'jours' => $jours];
            }
            break;

        case 'month':
            // Group by week (ISO weeks within the month)
            $weekStart = new DateTimeImmutable($dateDebut);
            while ($weekStart->format('Y-m-d') <= $dateFin) {
                $wEnd = min(
                    $weekStart->modify('Sunday this week')->format('Y-m-d'),
                    $dateFin
                );
                $wStart = max($weekStart->format('Y-m-d'), $dateDebut);
                $jours = 0;
                foreach ($absences as $a) {
                    $jours += $countBusinessDays($a['date_debut'], $a['date_fin'], $wStart, $wEnd);
                }
                $chartData[] = [
                    'label' => 'S' . $weekStart->format('W'),
                    'jours' => $jours,
                ];
                $weekStart = $weekStart->modify('next Monday');
            }
            break;

        case 'week':
            // Group by day
            $cursor = new DateTimeImmutable($dateDebut);
            $last   = new DateTimeImmutable($dateFin);
            while ($cursor <= $last) {
                $d = $cursor->format('Y-m-d');
                $jours = 0;
                foreach ($absences as $a) {
                    $jours += $countBusinessDays($a['date_debut'], $a['date_fin'], $d, $d);
                }
                $chartData[] = [
                    'label' => $cursor->format('D d'),
                    'jours' => $jours,
                ];
                $cursor = $cursor->modify('+1 day');
            }
            break;
    }

    // --- 6. Response ---
    respond([
        'success'       => true,
        'period'        => $period,
        'date_debut'    => $dateDebut,
        'date_fin'      => $dateFin,
        'stats'         => [
            'total_absents'   => count($usersMap),
            'total_jours'     => $totalJours,
            'total_heures'    => round($totalJours * 7.6, 1),
            'justifiees'      => $justifiees,
            'non_justifiees'  => $nonJustifiees,
            'par_type'        => $parType,
        ],
        'chart_data'    => $chartData,
        'collaborateurs' => array_values($usersMap),
    ]);
}
