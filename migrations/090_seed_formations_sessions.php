<?php
/**
 * Seed Phase 2bis — Formations FEGEMS réalistes + sessions + participants.
 *
 * Crée 10 formations couvrant les principales thématiques FEGEMS, avec :
 *  - 1-3 sessions chacune (passées, en cours, futures)
 *  - Participants pour les sessions passées (validation + heures + coût)
 *  - Lien formation_thematiques pour relier aux compétences
 *
 * Idempotent par titre.
 */
require_once __DIR__ . '/../init.php';

mt_srand(20260429);

$admin = Db::fetch("SELECT id FROM users WHERE role IN ('admin','direction') ORDER BY created_at LIMIT 1");
$createdBy = $admin['id'] ?? null;

$them = function ($code) {
    static $cache = [];
    if (!isset($cache[$code])) {
        $r = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
        $cache[$code] = $r ? $r['id'] : null;
    }
    return $cache[$code];
};

// ── Formations FEGEMS (titre, description, type, durée, thématiques liées,
//     niveau acquis, sessions [date_debut, date_fin, lieu, capacite, cout]) ──
$formations = [
    [
        'titre' => 'HPCI · précautions standards et microorganismes multi-résistants',
        'description' => 'Formation obligatoire FEGEMS. Renouvellement annuel pour le personnel Soins.',
        'type' => 'externe',
        'modalite' => 'Présentiel',
        'lieu' => 'Centre HUG, Genève',
        'duree' => 7.0,
        'cout_membre' => 0,
        'cout_non_membre' => 280,
        'thematiques' => [['HPCI_PRECAUTIONS', 4], ['HPCI_BASE', 3]],
        'sessions' => [
            ['2026-03-12', '2026-03-12', '08:30', '16:30', 'Centre HUG', 'presentiel', 20, 'passee'],
            ['2026-05-18', '2026-05-18', '08:30', '16:30', 'Centre HUG', 'presentiel', 20, 'ouverte'],
            ['2026-09-21', '2026-09-21', '08:30', '16:30', 'Centre HUG', 'presentiel', 20, 'ouverte'],
        ],
    ],
    [
        'titre' => 'BLS-AED · réanimation cardio-pulmonaire de base',
        'description' => 'Renouvellement obligatoire tous les 2 ans. Manipulation du défibrillateur semi-automatique.',
        'type' => 'externe',
        'modalite' => 'Présentiel',
        'lieu' => 'SPARK Plan-les-Ouates',
        'duree' => 4.0,
        'cout_membre' => 0,
        'cout_non_membre' => 180,
        'thematiques' => [['BLS_AED', 3]],
        'sessions' => [
            ['2026-02-08', '2026-02-08', '13:30', '17:30', 'SPARK Plan-les-Ouates', 'presentiel', 16, 'passee'],
            ['2026-06-05', '2026-06-05', '13:30', '17:30', 'SPARK Plan-les-Ouates', 'presentiel', 16, 'ouverte'],
            ['2026-10-09', '2026-10-09', '13:30', '17:30', 'SPARK Plan-les-Ouates', 'presentiel', 16, 'ouverte'],
        ],
    ],
    [
        'titre' => 'Intégration nouveaux collaborateurs (INC) · session 23',
        'description' => 'Formation d\'intégration FEGEMS obligatoire pour tout nouveau collaborateur dans les 6 mois.',
        'type' => 'externe',
        'modalite' => 'Présentiel',
        'lieu' => 'FEGEMS Genève',
        'duree' => 14.0,
        'cout_membre' => 0,
        'cout_non_membre' => 380,
        'thematiques' => [['INC', 2]],
        'sessions' => [
            ['2026-01-22', '2026-01-23', '09:00', '17:00', 'FEGEMS Genève', 'presentiel', 30, 'passee'],
            ['2026-06-11', '2026-06-12', '09:00', '17:00', 'FEGEMS Genève', 'presentiel', 30, 'ouverte'],
            ['2026-11-19', '2026-11-20', '09:00', '17:00', 'FEGEMS Genève', 'presentiel', 30, 'ouverte'],
        ],
    ],
    [
        'titre' => 'BPSD · symptômes comportementaux et psychologiques de la démence',
        'description' => 'Approche relationnelle et non médicamenteuse. Cas cliniques et mises en situation.',
        'type' => 'externe',
        'modalite' => 'Présentiel',
        'lieu' => 'Centre HUG',
        'duree' => 14.0,
        'cout_membre' => 0,
        'cout_non_membre' => 460,
        'thematiques' => [['BPSD', 3]],
        'sessions' => [
            ['2026-03-05', '2026-03-06', '09:00', '17:00', 'Centre HUG', 'presentiel', 18, 'passee'],
            ['2026-09-10', '2026-09-11', '09:00', '17:00', 'Centre HUG', 'presentiel', 18, 'ouverte'],
        ],
    ],
    [
        'titre' => 'Soins palliatifs · sensibilisation e-learning',
        'description' => 'Module d\'auto-formation. Préalable aux ateliers présentiels du certificat cantonal.',
        'type' => 'e-learning',
        'modalite' => 'E-learning',
        'lieu' => 'Plateforme FEGEMS',
        'duree' => 4.0,
        'cout_membre' => 0,
        'cout_non_membre' => 120,
        'thematiques' => [['SOINS_PALLIATIFS', 2]],
        'sessions' => [
            ['2026-01-01', '2026-12-31', null, null, 'Plateforme FEGEMS', 'elearning', null, 'ouverte'],
        ],
    ],
    [
        'titre' => 'Soins palliatifs · ateliers présentiels (3 jours)',
        'description' => 'Atelier 1/3 du certificat cantonal. E-learning préalable requis.',
        'type' => 'externe',
        'modalite' => 'Présentiel',
        'lieu' => 'Centre HUG',
        'duree' => 21.0,
        'cout_membre' => 0,
        'cout_non_membre' => 540,
        'thematiques' => [['SOINS_PALLIATIFS', 3]],
        'sessions' => [
            ['2026-06-24', '2026-06-26', '09:00', '17:00', 'Centre HUG', 'presentiel', 20, 'complete'],
        ],
    ],
    [
        'titre' => 'Bientraitance · regard et posture professionnelle',
        'description' => 'Réflexion collective sur la bientraitance, étude de cas et outils pratiques.',
        'type' => 'interne',
        'modalite' => 'Présentiel',
        'lieu' => 'Salle polyvalente · EMS',
        'duree' => 7.0,
        'cout_membre' => 0,
        'cout_non_membre' => 0,
        'thematiques' => [['BIENTRAITANCE', 3]],
        'sessions' => [
            ['2026-02-20', '2026-02-20', '08:30', '16:30', 'Salle polyvalente', 'presentiel', 25, 'passee'],
            ['2026-04-17', '2026-04-17', '08:30', '16:30', 'Salle polyvalente', 'presentiel', 25, 'passee'],
        ],
    ],
    [
        'titre' => 'Sécurité incendie · exercice annuel et procédures EMS',
        'description' => 'Formation obligatoire annuelle. Exercices d\'évacuation, manipulation extincteurs.',
        'type' => 'interne',
        'modalite' => 'Présentiel',
        'lieu' => 'EMS · zones techniques',
        'duree' => 3.0,
        'cout_membre' => 0,
        'cout_non_membre' => 0,
        'thematiques' => [['SECURITE_INCENDIE', 2]],
        'sessions' => [
            ['2026-04-08', '2026-04-08', '14:00', '17:00', 'EMS Terrassière', 'presentiel', 30, 'passee'],
            ['2026-10-15', '2026-10-15', '14:00', '17:00', 'EMS Terrassière', 'presentiel', 30, 'ouverte'],
        ],
    ],
    [
        'titre' => 'Cyber-sécurité en EMS · sensibilisation',
        'description' => 'Recommandation OCS. Phishing, mots de passe, données patients.',
        'type' => 'e-learning',
        'modalite' => 'E-learning',
        'lieu' => 'Webinaire FEGEMS',
        'duree' => 2.0,
        'cout_membre' => 0,
        'cout_non_membre' => 60,
        'thematiques' => [['CYBER_SECURITE', 2]],
        'sessions' => [
            ['2026-09-16', '2026-09-16', '14:00', '16:00', 'Webinaire en ligne', 'elearning', null, 'ouverte'],
        ],
    ],
    [
        'titre' => 'Bonnes pratiques actes délégués · Inf./ASSC · session 119',
        'description' => 'Validé OCS. Renouvellement biennal recommandé. E-learning préalable + journée présentielle.',
        'type' => 'externe',
        'modalite' => 'Hybride',
        'lieu' => 'Centre HUG',
        'duree' => 8.0,
        'cout_membre' => 0,
        'cout_non_membre' => 320,
        'thematiques' => [['ACTES_DELEGUES', 3]],
        'sessions' => [
            ['2026-07-02', '2026-07-02', '09:00', '17:00', 'Centre HUG', 'hybride', 18, 'ouverte'],
        ],
    ],
];

$insertedF = 0; $insertedS = 0; $insertedFT = 0;
$today = date('Y-m-d');
$formationIds = [];

foreach ($formations as $f) {
    $existing = Db::fetch("SELECT id FROM formations WHERE titre = ?", [$f['titre']]);
    if ($existing) {
        $fid = $existing['id'];
    } else {
        $fid = Uuid::v4();
        $dateDebut = $f['sessions'][0][0];
        $dateFin = end($f['sessions'])[1];
        // statut formation parent : si toutes sessions passées → terminée, sinon planifiée
        $allPast = true;
        foreach ($f['sessions'] as $s) { if ($s[1] >= $today) { $allPast = false; break; } }
        $statutF = $allPast ? 'terminee' : 'planifiee';

        Db::exec(
            "INSERT INTO formations
             (id, titre, description, type, formateur, lieu, date_debut, date_fin, duree_heures,
              max_participants, statut, modalite, cout_formation, created_by)
             VALUES (?, ?, ?, ?, 'FEGEMS', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$fid, $f['titre'], $f['description'], $f['type'], $f['lieu'],
             $dateDebut, $dateFin, $f['duree'],
             $f['sessions'][0][6], $statutF, $f['modalite'], $f['cout_non_membre'], $createdBy]
        );
        $insertedF++;
    }
    $formationIds[] = $fid;

    // Lier aux thématiques
    foreach ($f['thematiques'] as [$code, $niv]) {
        $tid = $them($code);
        if (!$tid) continue;
        $linkExists = Db::fetch(
            "SELECT 1 FROM formation_thematiques WHERE formation_id = ? AND thematique_id = ?",
            [$fid, $tid]
        );
        if ($linkExists) continue;
        Db::exec(
            "INSERT INTO formation_thematiques (formation_id, thematique_id, niveau_acquis) VALUES (?, ?, ?)",
            [$fid, $tid, $niv]
        );
        $insertedFT++;
    }

    // Sessions
    foreach ($f['sessions'] as [$dDeb, $dFin, $hDeb, $hFin, $lieu, $mod, $cap, $statut]) {
        $sid = Uuid::v4();
        // statut auto si passée
        if ($dFin < $today) $statut = 'passee';
        $exists = Db::fetch(
            "SELECT id FROM formation_sessions WHERE formation_id = ? AND date_debut = ?",
            [$fid, $dDeb]
        );
        if ($exists) continue;
        Db::exec(
            "INSERT INTO formation_sessions
             (id, formation_id, date_debut, date_fin, heure_debut, heure_fin, lieu, modalite,
              capacite_max, places_inscrites, cout_membre, cout_non_membre, statut, contact_inscription_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'inscription@fegems.ch')",
            [$sid, $fid, $dDeb, $dFin, $hDeb, $hFin, $lieu, $mod, $cap,
             $f['cout_membre'], $f['cout_non_membre'], $statut]
        );
        $insertedS++;
    }
}
echo "Formations créées : $insertedF\n";
echo "Sessions créées : $insertedS\n";
echo "Liens formation→thématique : $insertedFT\n";

// ── Participants pour les sessions passées ────────────────────
$pastSessions = Db::fetchAll(
    "SELECT s.id AS session_id, s.formation_id, s.capacite_max, s.cout_membre, s.cout_non_membre,
            f.duree_heures, f.titre
     FROM formation_sessions s JOIN formations f ON f.id = s.formation_id
     WHERE s.statut = 'passee'"
);

$insertedP = 0;
foreach ($pastSessions as $s) {
    // Tirer 60-90 % de la capacité, ou 8-12 si capacité null
    $cap = $s['capacite_max'] ?? 12;
    $nbParts = (int) ($cap * (0.6 + mt_rand(0, 30) / 100));
    $nbParts = min($nbParts, 18); // pas trop pour le seed

    // Trouver les thématiques de la formation pour piocher les bons collab (cible le secteur pertinent)
    $thems = Db::fetchAll(
        "SELECT thematique_id FROM formation_thematiques WHERE formation_id = ?",
        [$s['formation_id']]
    );
    $themIds = array_column($thems, 'thematique_id');

    // Sélectionner aléatoirement parmi les collab qui ont cette thématique avec écart >= 1
    $candidats = $themIds ? Db::fetchAll(
        "SELECT DISTINCT cu.user_id FROM competences_user cu
         WHERE cu.thematique_id IN (" . implode(',', array_fill(0, count($themIds), '?')) . ")
           AND cu.ecart >= 1
         ORDER BY RAND() LIMIT $nbParts",
        $themIds
    ) : [];

    if (count($candidats) < $nbParts) {
        // Compléter avec des collab actifs au hasard
        $extra = Db::fetchAll(
            "SELECT id AS user_id FROM users WHERE is_active = 1 AND role IN ('collaborateur','responsable')
             ORDER BY RAND() LIMIT " . ($nbParts - count($candidats))
        );
        $candidats = array_merge($candidats, $extra);
    }

    $cout = $s['cout_membre'] ?? 0;
    foreach ($candidats as $c) {
        $exists = Db::fetch(
            "SELECT id FROM formation_participants WHERE formation_id = ? AND user_id = ?",
            [$s['formation_id'], $c['user_id']]
        );
        if ($exists) continue;

        $statut = mt_rand(0, 100) < 92 ? 'valide' : (mt_rand(0, 1) ? 'present' : 'absent');
        $eval = $statut === 'absent' ? null : (mt_rand(0, 100) < 85 ? 'satisfaisant' : 'insatisfaisant');
        $heures = $statut === 'absent' ? 0 : (float) $s['duree_heures'];

        Db::exec(
            "INSERT INTO formation_participants
             (id, formation_id, user_id, statut, evaluation_manager, heures_realisees, cout_individuel,
              date_realisation, session_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT date_debut FROM formation_sessions WHERE id = ?), ?)",
            [Uuid::v4(), $s['formation_id'], $c['user_id'], $statut, $eval,
             $heures, $cout, $s['session_id'], $s['session_id']]
        );
        $insertedP++;

        // Mettre à jour places_inscrites
        Db::exec("UPDATE formation_sessions SET places_inscrites = places_inscrites + 1 WHERE id = ?", [$s['session_id']]);

        // Pour les validés : trace dans l'historique + mise à jour niveau si auto-update activé
        if ($statut === 'valide' && $themIds) {
            foreach ($themIds as $tid) {
                $cu = Db::fetch(
                    "SELECT id, niveau_actuel FROM competences_user WHERE user_id = ? AND thematique_id = ?",
                    [$c['user_id'], $tid]
                );
                if (!$cu) continue;
                $linkRow = Db::fetch(
                    "SELECT niveau_acquis FROM formation_thematiques WHERE formation_id = ? AND thematique_id = ?",
                    [$s['formation_id'], $tid]
                );
                $nivAcquis = $linkRow ? (int) $linkRow['niveau_acquis'] : 0;
                if ($nivAcquis > (int) ($cu['niveau_actuel'] ?? 0)) {
                    Db::exec(
                        "INSERT INTO competences_evaluations_historique
                         (id, user_id, thematique_id, niveau_avant, niveau_apres, formation_id, date_evaluation)
                         VALUES (?, ?, ?, ?, ?, ?, CURDATE())",
                        [Uuid::v4(), $c['user_id'], $tid, $cu['niveau_actuel'], $nivAcquis, $s['formation_id']]
                    );
                    Db::exec(
                        "UPDATE competences_user SET niveau_actuel = ?, formation_validation_id = ?
                         WHERE id = ?",
                        [$nivAcquis, $s['formation_id'], $cu['id']]
                    );
                }
            }
        }
    }
}
echo "Participants ajoutés : $insertedP\n";

// ── Récap ─────────────────────────────────────────────────────
echo "\n--- Récap ---\n";
echo "Formations : " . Db::getOne("SELECT COUNT(*) FROM formations") . "\n";
echo "Sessions   : " . Db::getOne("SELECT COUNT(*) FROM formation_sessions") . "\n";
echo "Participants : " . Db::getOne("SELECT COUNT(*) FROM formation_participants") . "\n";
echo "Heures réalisées : " . number_format((float) Db::getOne(
    "SELECT COALESCE(SUM(heures_realisees), 0) FROM formation_participants WHERE statut IN ('present','valide')"
), 0) . " h\n";

echo "\n✓ Seed formations terminé.\n";
