<?php
/**
 * Seed démo Phase 2 — profil attendu réaliste + évaluations individuelles.
 *
 * Idempotent : skip si la matrice profil_attendu est déjà partiellement remplie
 *              et si les utilisateurs ont déjà des évaluations.
 *              Forcer avec : php 088_seed_competences_demo.php --force
 */
require_once __DIR__ . '/../init.php';

$force = in_array('--force', $argv ?? [], true);

if (!$force) {
    $existing = (int) Db::getOne("SELECT COUNT(*) FROM competences_profil_attendu");
    if ($existing > 0) {
        echo "Profil attendu déjà partiellement rempli ($existing cellules). Forcer avec --force.\n";
        exit(0);
    }
}

if ($force) {
    Db::exec("DELETE FROM competences_profil_attendu");
    Db::exec("DELETE FROM competences_user");
    echo "Reset complet (mode --force).\n";
}

$them = function ($code) {
    $r = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
    return $r ? $r['id'] : null;
};

// ── 1. Profil d'équipe attendu — matrice par secteur ──────────
$matrix = [
    'soins' => [
        // [code thématique, niveau requis, % à former]
        ['HPCI_BASE', 4, 100],
        ['HPCI_PRECAUTIONS', 3, 100],
        ['BLS_AED', 3, 100],
        ['INC', 2, 100],
        ['BPSD', 3, 100],
        ['SOINS_PALLIATIFS', 3, 100],
        ['CHUTES', 3, 100],
        ['HYGIENE_BUCCO', 2, 100],
        ['BIENTRAITANCE', 3, 100],
        ['QUALITE', 2, 100],
        ['CYBER_SECURITE', 2, 100],
        ['SECURITE_INCENDIE', 2, 100],
        ['EXAMENS_CLINIQUES', 2, 70],
        ['TRANSMISSIONS_INF', 3, 50],
        ['TRANSMISSIONS_AS', 3, 50],
        ['ACTES_DELEGUES', 3, 80],
    ],
    'socio_culturel' => [
        ['INC', 2, 100],
        ['BPSD', 2, 100],
        ['BIENTRAITANCE', 3, 100],
        ['SOINS_PALLIATIFS', 2, 80],
        ['CYBER_SECURITE', 2, 100],
        ['SECURITE_INCENDIE', 2, 100],
        ['BLS_AED', 2, 100],
    ],
    'hotellerie' => [
        ['INC', 2, 100],
        ['HPCI_BASE', 2, 100],
        ['BIENTRAITANCE', 3, 100],
        ['CYBER_SECURITE', 2, 100],
        ['SECURITE_INCENDIE', 2, 100],
        ['BLS_AED', 2, 100],
    ],
    'maintenance' => [
        ['INC', 2, 100],
        ['SECURITE_INCENDIE', 3, 100],
        ['CYBER_SECURITE', 2, 100],
        ['BIENTRAITANCE', 2, 100],
        ['HPCI_BASE', 2, 100],
        ['BLS_AED', 2, 100],
    ],
    'administration' => [
        ['INC', 2, 100],
        ['CYBER_SECURITE', 3, 100],
        ['QUALITE', 2, 100],
        ['SECURITE_INCENDIE', 2, 100],
        ['BIENTRAITANCE', 2, 100],
    ],
    'management' => [
        ['INC', 2, 100],
        ['BIENTRAITANCE', 4, 100],
        ['QUALITE', 3, 100],
        ['CYBER_SECURITE', 3, 100],
        ['SECURITE_INCENDIE', 3, 100],
        ['HPCI_BASE', 2, 100],
        ['BPSD', 2, 100],
        ['SOINS_PALLIATIFS', 2, 100],
        ['BLS_AED', 2, 100],
    ],
];

$cellsInserted = 0;
foreach ($matrix as $secteur => $rows) {
    foreach ($rows as [$code, $niv, $pct]) {
        $tid = $them($code);
        if (!$tid) continue;
        Db::exec(
            "INSERT INTO competences_profil_attendu
             (id, thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande)
             VALUES (?, ?, ?, 1, ?, ?, 'continue_catalogue')",
            [Uuid::v4(), $tid, $secteur, $niv, $pct]
        );
        $cellsInserted++;
    }
}
echo "Profil attendu : $cellsInserted cellules insérées.\n";

// ── 2. Évaluations individuelles par collaborateur ────────────
// Stratégie : pour chaque collab actif, on prend le profil attendu de son secteur,
// on tire au hasard un niveau actuel pondéré par son ancienneté.

// S'assurer que les fonctions hôtellerie/cuisine sont mappées (le seed initial
// avait raté à cause des accents/suffixes).
$fixMapping = [
    'hotellerie' => ['Hôtellerie', 'Chef cuisinier', 'Cuisinier'],
];
foreach ($fixMapping as $secteur => $noms) {
    foreach ($noms as $nom) {
        Db::exec("UPDATE fonctions SET secteur_fegems = ? WHERE nom = ? AND secteur_fegems IS NULL", [$secteur, $nom]);
    }
}

$collaborateurs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.date_entree, f.secteur_fegems
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND u.role IN ('collaborateur','responsable')
       AND f.secteur_fegems IS NOT NULL"
);

$today = new DateTime();
$evalCount = 0;
mt_srand(2026); // reproductible

foreach ($collaborateurs as $c) {
    $secteur = $c['secteur_fegems'];
    if (!isset($matrix[$secteur])) continue;

    // Calcul ancienneté pour pondérer la maturité
    $entree = $c['date_entree'] ? new DateTime($c['date_entree']) : (new DateTime())->modify('-2 years');
    $anneesAnciennete = $today->diff($entree)->y + ($today->diff($entree)->m / 12);

    foreach ($matrix[$secteur] as [$code, $nivRequis, $pct]) {
        $tid = $them($code);
        if (!$tid) continue;

        // Distribution du niveau actuel selon ancienneté
        $rand = mt_rand(0, 100);
        if ($anneesAnciennete < 1) {
            // Junior : majorité niveau 1, peu niveau 2
            $nivActuel = $rand < 70 ? 1 : ($rand < 95 ? 2 : 3);
        } elseif ($anneesAnciennete < 3) {
            // Confirmé : majorité niveau 2, certains 3
            $nivActuel = $rand < 25 ? 1 : ($rand < 65 ? 2 : ($rand < 95 ? 3 : 4));
        } elseif ($anneesAnciennete < 7) {
            // Senior : majorité 3, certains 2 et 4
            $nivActuel = $rand < 10 ? 1 : ($rand < 30 ? 2 : ($rand < 80 ? 3 : 4));
        } else {
            // Expert : majorité 3-4
            $nivActuel = $rand < 5 ? 2 : ($rand < 50 ? 3 : 4);
        }

        // Quelques utilisateurs avec niveau NULL (jamais évalués) pour diversité
        if ($rand > 95 && $anneesAnciennete < 0.5) $nivActuel = null;

        Db::exec(
            "INSERT INTO competences_user
             (id, user_id, thematique_id, niveau_actuel, niveau_requis, date_evaluation)
             VALUES (?, ?, ?, ?, ?, CURDATE())",
            [Uuid::v4(), $c['id'], $tid, $nivActuel, $nivRequis]
        );
        $evalCount++;
    }
}
echo "Évaluations insérées : $evalCount sur " . count($collaborateurs) . " collaborateurs.\n";

// ── 3. Quelques référents nommés (pour la maquette fiche employé) ──
// On nomme les responsables Soins comme référents sur leur thématique de prédilection
$referentsAssign = [
    ['REF_PLAIE',         'soins'],
    ['REF_HPCI',          'soins'],
    ['REF_DOULEUR',       'soins'],
    ['REF_DEMENCE',       'soins'],
];
$assignCount = 0;
foreach ($referentsAssign as [$code, $secteur]) {
    $tid = $them($code);
    if (!$tid) continue;
    // Choisir un responsable Soins au hasard
    $candidat = Db::fetch(
        "SELECT u.id FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND u.role = 'responsable' AND f.secteur_fegems = ?
         ORDER BY RAND() LIMIT 1",
        [$secteur]
    );
    if (!$candidat) {
        $candidat = Db::fetch(
            "SELECT u.id FROM users u
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             WHERE u.is_active = 1 AND f.secteur_fegems = ?
             ORDER BY u.date_entree ASC LIMIT 1",
            [$secteur]
        );
    }
    if (!$candidat) continue;

    $exists = Db::fetch(
        "SELECT id FROM competences_referents WHERE user_id = ? AND thematique_id = ?",
        [$candidat['id'], $tid]
    );
    if ($exists) continue;

    Db::exec(
        "INSERT INTO competences_referents (id, user_id, thematique_id, depuis_le, has_competences_pedago, actif)
         VALUES (?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 18 MONTH), 1, 1)",
        [Uuid::v4(), $candidat['id'], $tid]
    );
    $assignCount++;
}
echo "Référents nommés : $assignCount.\n";

// ── 4. Quelques objectifs annuels pour la timeline de la fiche ──
$collabSamples = Db::fetchAll(
    "SELECT id FROM users WHERE is_active = 1 AND role IN ('collaborateur','responsable')
     ORDER BY RAND() LIMIT 8"
);
$objCount = 0;
$objLibelles = [
    ['Q1', 'Renouveler la certification HPCI', 'atteint',  'HPCI_PRECAUTIONS'],
    ['Q2', 'Suivre l\'atelier soins palliatifs', 'en_cours', 'SOINS_PALLIATIFS'],
    ['Q3', 'Devenir personne ressource Plaies', 'en_cours', 'REF_PLAIE'],
    ['Q4', 'Mettre à jour BLS-AED', 'a_definir', 'BLS_AED'],
];
foreach ($collabSamples as $u) {
    foreach ($objLibelles as $i => [$tri, $libelle, $statut, $themCode]) {
        if (mt_rand(0, 100) > 60) continue; // seulement ~40 % des objectifs
        $tid = $them($themCode);
        Db::exec(
            "INSERT INTO competences_objectifs_annuels
             (id, user_id, annee, trimestre_cible, libelle, thematique_id_liee, statut, ordre, date_atteint)
             VALUES (?, ?, YEAR(CURDATE()), ?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(), $u['id'], $tri, $libelle, $tid, $statut, $i,
                $statut === 'atteint' ? date('Y-m-d', strtotime('-2 months')) : null
            ]
        );
        $objCount++;
    }
}
echo "Objectifs annuels : $objCount.\n";

echo "\n✓ Seed démo Phase 2 terminé.\n";
