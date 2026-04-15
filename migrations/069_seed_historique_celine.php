<?php
/**
 * Seed historique stagiaires pour Céline Garcia (formatrice)
 * Run: php migrations/069_seed_historique_celine.php
 */
require_once __DIR__ . '/../init.php';

echo "=== Seed historique Céline Garcia ===\n\n";

$celine = Db::fetch("SELECT id, prenom, nom FROM users WHERE email = 'celine.garcia@terrassiere.ch'");
if (!$celine) { echo "Céline Garcia introuvable.\n"; exit(1); }
echo "Formatrice : {$celine['prenom']} {$celine['nom']}\n";

$ruv = Db::fetch("SELECT id FROM users WHERE email LIKE 'michel.berset%' OR role IN ('admin','direction') LIMIT 1");
$etage = Db::fetch("SELECT id, nom FROM etages ORDER BY ordre LIMIT 1");

$pwHash = password_hash('Terr2026!', PASSWORD_DEFAULT);

// 2 anciens stagiaires (stages terminés il y a quelques mois)
$past = [
    [
        'prenom' => 'Noah', 'nom' => 'Freymond',
        'type' => 'cfc_asa', 'etab' => 'ARPIH Lausanne', 'niveau' => '1re année',
        'debut' => '2025-09-15', 'fin' => '2025-12-15',
        'statut' => 'termine',
        'notes_final' => 'Stage validé avec mention. Excellent contact résidents, initiative exemplaire.',
    ],
    [
        'prenom' => 'Emma', 'nom' => 'Charrière',
        'type' => 'bachelor_inf', 'etab' => 'HEdS-GE', 'niveau' => '2e année',
        'debut' => '2026-01-05', 'fin' => '2026-03-20',
        'statut' => 'termine',
        'notes_final' => 'Bonne progression, à poursuivre sur l\'autonomie technique.',
    ],
];

foreach ($past as $p) {
    $email = strtolower($p['prenom'] . '.' . $p['nom']) . '@terrassiere.ch';
    $email = strtr($email, ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','ï'=>'i','ç'=>'c',' '=>'']);

    $user = Db::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if (!$user) {
        $userId = Uuid::v4();
        Db::exec(
            "INSERT INTO users (id, email, password, prenom, nom, role, taux, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 'stagiaire', 100, 0, ?)",
            [$userId, $email, $pwHash, $p['prenom'], $p['nom'], $p['debut']]
        );
        echo "  + Compte créé : $email\n";
    } else {
        $userId = $user['id'];
        Db::exec("UPDATE users SET role = 'stagiaire' WHERE id = ?", [$userId]);
        echo "  [skip] $email existe déjà\n";
    }

    $existStag = Db::getOne("SELECT id FROM stagiaires WHERE user_id = ?", [$userId]);
    if ($existStag) { echo "    [skip] stagiaire déjà présent\n"; continue; }

    $stagId = Uuid::v4();
    Db::exec(
        "INSERT INTO stagiaires (id, user_id, type, etablissement_origine, niveau, date_debut, date_fin,
         etage_id, ruv_id, formateur_principal_id, objectifs_generaux, statut, notes_ruv, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$stagId, $userId, $p['type'], $p['etab'], $p['niveau'], $p['debut'], $p['fin'],
         $etage['id'], $ruv['id'] ?? null, $celine['id'],
         "Stage de " . ($p['type'] === 'bachelor_inf' ? 'bachelor infirmier' : 'CFC ASA'),
         $p['statut'], $p['notes_final'], $ruv['id'] ?? null]
    );

    // Affectation principale (période passée)
    Db::exec(
        "INSERT INTO stagiaire_affectations (id, stagiaire_id, formateur_id, etage_id, date_debut, date_fin, role_formateur, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 'principal', ?)",
        [Uuid::v4(), $stagId, $celine['id'], $etage['id'], $p['debut'], $p['fin'], $ruv['id'] ?? null]
    );

    // Reports validés (8)
    $reportContents = [
        ['Semaine 1 — accueil', 'Prise de poste, découverte de l\'équipe et de l\'étage. Beaucoup à apprendre mais l\'équipe est bienveillante.'],
        ['Semaine 2 — soins de base', 'Progression sur les toilettes au lit. Encore besoin d\'assistance pour les transferts complexes.'],
        ['Semaine 3 — transferts', 'J\'ai maîtrisé le transfert lit-fauteuil avec disque. Fier·e de cette progression.'],
        ['Semaine 4 — relation résidents', 'Des échanges riches avec Mme D. et M. R. Je commence à connaître leurs histoires.'],
        ['Semaine 5 — médicaments', 'Observation de la distribution. Rigueur impressionnante requise.'],
        ['Semaine 6 — urgence', 'Gestion d\'une chute. Je suis resté·e calme et j\'ai appelé l\'infirmière.'],
        ['Semaine 7 — animation', 'Participation à un atelier chant. Les résidents adorent.'],
        ['Bilan final', 'Ce stage m\'a permis de confirmer mon choix professionnel. Merci à Céline pour son encadrement.'],
    ];
    $start = strtotime($p['debut']);
    foreach ($reportContents as $idx => $r) {
        $dateR = date('Y-m-d', strtotime("+" . ($idx * 7) . " days", $start));
        Db::exec(
            "INSERT INTO stagiaire_reports (id, stagiaire_id, type, date_report, titre, contenu, statut, submitted_at, validated_by, validated_at, commentaire_formateur)
             VALUES (?, ?, 'hebdo', ?, ?, ?, 'valide', ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $dateR, $r[0], '<p>'.$r[1].'</p>',
             $dateR . ' 17:00:00', $celine['id'], $dateR . ' 19:00:00',
             $idx === 7 ? 'Bravo pour ton parcours, tu vas faire une excellente professionnelle.' : 'Bon report, continue ainsi.']
        );
    }

    // 3 évaluations : 1 mi-stage, 1 finale, 1 journalière
    $evals = [
        ['journaliere', "+15 days", 3, 4, 3, 3, 4, 5, 'Bon contact', 'Gestes techniques à affiner', 'Stage bien démarré'],
        ['mi_stage', "+42 days", 4, 4, 4, 4, 5, 5, 'Autonomie en progression', 'Consolider les soins complexes', 'Mi-parcours très satisfaisant'],
        ['finale', $p['fin'] . ' 00:00:00', 5, 5, 4, 4, 5, 5, 'Excellente posture pro, relation privilégiée avec les résidents', 'Continuer à approfondir les connaissances cliniques', $p['notes_final']],
    ];
    foreach ($evals as $e) {
        $dateEval = str_starts_with($e[1], '+') ? date('Y-m-d', strtotime($e[1], $start)) : substr($e[1], 0, 10);
        Db::exec(
            "INSERT INTO stagiaire_evaluations (id, stagiaire_id, formateur_id, date_eval, periode,
             note_initiative, note_communication, note_connaissances, note_autonomie, note_savoir_etre, note_ponctualite,
             points_forts, points_amelioration, commentaire_general)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $celine['id'], $dateEval, $e[0],
             $e[2], $e[3], $e[4], $e[5], $e[6], $e[7], $e[8], $e[9], $e[10]]
        );
    }

    // Objectifs (tous atteints pour un stage terminé)
    $objs = [
        ['Autonomie soins de base', 'Réaliser toilettes et transferts sans supervision directe', 'atteint'],
        ['Communication équipe', 'Transmissions orales et écrites claires', 'atteint'],
        ['Relation résidents', 'Établir un lien de confiance avec chaque résident', 'atteint'],
    ];
    foreach ($objs as $o) {
        Db::exec(
            "INSERT INTO stagiaire_objectifs (id, stagiaire_id, titre, description, statut, created_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $o[0], $o[1], $o[2], $ruv['id'] ?? null]
        );
    }

    echo "    ✓ Stagiaire {$p['prenom']} {$p['nom']} — 8 reports validés, 3 évals, 3 objectifs atteints\n";
}

echo "\nFait.\n";
echo "Céline verra maintenant 2 stagiaires dans son historique : Noah Freymond + Emma Charrière.\n";
