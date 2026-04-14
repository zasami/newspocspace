<?php
/**
 * Seed: 3 stagiaires test + affectations + reports + évaluations + objectifs
 * Run: php migrations/065_seed_stagiaires.php
 */

require_once __DIR__ . '/../init.php';

echo "=== Seed Stagiaires ===\n\n";

// Pick 2 collaborateurs/responsables for ruv + formateur roles
$staff = Db::fetchAll(
    "SELECT id, prenom, nom, role FROM users
     WHERE is_active = 1 AND role IN ('collaborateur','responsable','admin','direction')
     ORDER BY RAND() LIMIT 4"
);
if (count($staff) < 2) { echo "Pas assez d'utilisateurs staff.\n"; exit(1); }
$ruv = $staff[0];
$formateurs = [$staff[1], $staff[2] ?? $staff[1], $staff[3] ?? $staff[1]];

// Pick an étage
$etage = Db::fetch("SELECT id, nom FROM etages ORDER BY ordre LIMIT 1");
if (!$etage) { echo "Aucun étage.\n"; exit(1); }

echo "RUV: {$ruv['prenom']} {$ruv['nom']}\n";
echo "Étage: {$etage['nom']}\n";
echo "Formateurs: " . implode(', ', array_map(fn($f) => $f['prenom'].' '.$f['nom'], $formateurs)) . "\n\n";

// ── Créer 3 comptes stagiaires ──
$stagProfiles = [
    ['prenom' => 'Léa', 'nom' => 'Duval', 'type' => 'cfc_asa', 'etab' => 'ARPIH Lausanne', 'niveau' => '2e année'],
    ['prenom' => 'Malik', 'nom' => 'Benali', 'type' => 'bachelor_inf', 'etab' => 'HEdS-GE', 'niveau' => '3e année'],
    ['prenom' => 'Sofia', 'nom' => 'Marques', 'type' => 'decouverte', 'etab' => 'Collège de Saussure', 'niveau' => 'Maturité'],
];

$created = [];
$pwHash = password_hash('Terr2026!', PASSWORD_DEFAULT);

foreach ($stagProfiles as $p) {
    // Vérifier existence par email
    $email = strtolower($p['prenom']).'.'.strtolower($p['nom']).'@terrassiere.ch';
    $email = str_replace(['é','è','ê','à','ï','ç'], ['e','e','e','a','i','c'], $email);

    $existing = Db::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        $userId = $existing['id'];
        Db::exec("UPDATE users SET role = 'stagiaire' WHERE id = ?", [$userId]);
        echo "  [skip] {$p['prenom']} {$p['nom']} existe déjà — marqué stagiaire\n";
    } else {
        $userId = Uuid::v4();
        Db::exec(
            "INSERT INTO users (id, email, password, prenom, nom, role, taux, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 'stagiaire', 100, 1, NOW())",
            [$userId, $email, $pwHash, $p['prenom'], $p['nom']]
        );
        echo "  + Compte créé : $email\n";
    }

    // Stagiaire row
    $stagId = Db::getOne("SELECT id FROM stagiaires WHERE user_id = ?", [$userId]);
    if ($stagId) {
        echo "    [skip] stagiaire déjà existant\n";
        $created[] = ['id' => $stagId, 'user_id' => $userId, 'profile' => $p];
        continue;
    }

    $stagId = Uuid::v4();
    $dateDebut = date('Y-m-d', strtotime('-10 days'));
    $dateFin = date('Y-m-d', strtotime('+30 days'));
    Db::exec(
        "INSERT INTO stagiaires (id, user_id, type, etablissement_origine, niveau, date_debut, date_fin,
         etage_id, ruv_id, formateur_principal_id, objectifs_generaux, statut, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?)",
        [$stagId, $userId, $p['type'], $p['etab'], $p['niveau'], $dateDebut, $dateFin,
         $etage['id'], $ruv['id'], $formateurs[0]['id'],
         "Acquérir les compétences de base pour " . $p['type'],
         $ruv['id']]
    );
    echo "    + Stagiaire créé (" . $p['type'] . ")\n";

    // Affectation formateur principal
    Db::exec(
        "INSERT INTO stagiaire_affectations (id, stagiaire_id, formateur_id, etage_id, date_debut, date_fin, role_formateur, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 'principal', ?)",
        [Uuid::v4(), $stagId, $formateurs[0]['id'], $etage['id'], $dateDebut, $dateFin, $ruv['id']]
    );
    // Affectation ponctuelle supplémentaire (sur 3 jours au milieu du stage)
    if (count($formateurs) > 1 && $formateurs[0]['id'] !== $formateurs[1]['id']) {
        Db::exec(
            "INSERT INTO stagiaire_affectations (id, stagiaire_id, formateur_id, etage_id, date_debut, date_fin, role_formateur, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'ponctuel', 'Remplacement formateur principal', ?)",
            [Uuid::v4(), $stagId, $formateurs[1]['id'], $etage['id'],
             date('Y-m-d', strtotime('-3 days')), date('Y-m-d', strtotime('+2 days')),
             $ruv['id']]
        );
    }

    // Objectifs
    $objs = [
        ['Prise d\'initiative', 'Proposer spontanément de l\'aide sans attendre qu\'on demande'],
        ['Communication équipe', 'Transmettre clairement les observations lors des passations'],
        ['Connaissances techniques', 'Maîtriser les protocoles de soins de base'],
    ];
    foreach ($objs as $o) {
        Db::exec(
            "INSERT INTO stagiaire_objectifs (id, stagiaire_id, titre, description, statut, created_by)
             VALUES (?, ?, ?, ?, 'en_cours', ?)",
            [Uuid::v4(), $stagId, $o[0], $o[1], $ruv['id']]
        );
    }

    // 5 reports (mix de statuts)
    $reportContents = [
        ['J1', 'Première journée — découverte de l\'équipe', 'Accueillie par la responsable. J\'ai observé les soins du matin et participé à l\'accompagnement aux repas. L\'équipe est très bienveillante.', 'valide'],
        ['J2', 'Transferts et mobilisations', 'Aujourd\'hui j\'ai appris les techniques de transfert lit-fauteuil. Difficile au début mais j\'ai progressé en fin de journée.', 'valide'],
        ['J3', 'Toilettes matinales', 'Première toilette en autonomie (supervisée). J\'ai respecté l\'intimité de la résidente. Je dois encore travailler la rapidité.', 'soumis'],
        ['J4', 'Journée difficile', 'Une résidente très agitée ce matin. Je ne savais pas comment réagir. J\'ai appelé le formateur qui m\'a montré comment apaiser la situation.', 'soumis'],
        ['J5', 'Animation après-midi', 'Participé à l\'atelier chant. Très enrichissant. Les résidents étaient ravis.', 'brouillon'],
    ];
    foreach ($reportContents as $idx => $r) {
        $dateR = date('Y-m-d', strtotime('-' . (5 - $idx) . ' days'));
        $statut = $r[3];
        $submittedAt = $statut !== 'brouillon' ? date('Y-m-d H:i:s', strtotime('-' . (5 - $idx) . ' days +17 hours')) : null;
        $validatedBy = $statut === 'valide' ? $formateurs[0]['id'] : null;
        $validatedAt = $statut === 'valide' ? date('Y-m-d H:i:s', strtotime('-' . (5 - $idx) . ' days +19 hours')) : null;
        $comm = $statut === 'valide' ? 'Bon report, continue ainsi !' : null;
        Db::exec(
            "INSERT INTO stagiaire_reports (id, stagiaire_id, type, date_report, titre, contenu, statut, submitted_at, validated_by, validated_at, commentaire_formateur)
             VALUES (?, ?, 'quotidien', ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $dateR, $r[0] . ' — ' . $r[1], $r[2], $statut, $submittedAt, $validatedBy, $validatedAt, $comm]
        );
    }

    // 2 évaluations journalières
    for ($i = 0; $i < 2; $i++) {
        Db::exec(
            "INSERT INTO stagiaire_evaluations (id, stagiaire_id, formateur_id, date_eval, periode,
             note_initiative, note_communication, note_connaissances, note_autonomie, note_savoir_etre, note_ponctualite,
             points_forts, points_amelioration, commentaire_general)
             VALUES (?, ?, ?, ?, 'journaliere', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $formateurs[0]['id'],
             date('Y-m-d', strtotime('-' . (4 - $i*2) . ' days')),
             rand(3,5), rand(3,5), rand(2,4), rand(2,4), rand(4,5), 5,
             'Très à l\'écoute, relation agréable avec les résidents',
             'Peut gagner en autonomie sur les gestes techniques',
             'Progresse bien, investi·e et motivé·e']
        );
    }

    $created[] = ['id' => $stagId, 'user_id' => $userId, 'profile' => $p];
}

echo "\n=== Récap ===\n";
echo count($created) . " stagiaires prêts.\n";
echo "Login stagiaire: [prenom].[nom]@terrassiere.ch / Terr2026!\n";
echo "RUV pour validation: {$ruv['prenom']} {$ruv['nom']}\n";
echo "Formateur principal: {$formateurs[0]['prenom']} {$formateurs[0]['nom']}\n";
echo "\nTestez :\n";
echo "  - Admin : https://www.zkriva.com/spocspace/admin/?page=rh-stagiaires\n";
echo "  - Formateur (login): https://www.zkriva.com/spocspace/mes-stagiaires\n";
echo "  - Stagiaire (login): https://www.zkriva.com/spocspace/mon-stage\n";
