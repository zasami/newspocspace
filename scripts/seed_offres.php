<?php
require_once __DIR__ . '/../init.php';

$offres = [
    ['titre' => 'Aide soignant·e (CFC)', 'desc' => 'Nous recherchons un·e aide soignant·e diplômé·e pour rejoindre notre équipe de soins. Vous assurerez les soins de base aux résidents et contribuerez au bien-être de nos aînés.', 'type' => 'CDI', 'taux' => '60-80%', 'dept' => 'Soins', 'exigences' => "CFC d'aide soignant·e\nExpérience en EMS appréciée\nEmpathie et sens de l'écoute\nPermis de travail suisse", 'avantages' => "Horaires flexibles\nFormation continue\nAmbiance chaleureuse\nRepas offerts"],
    ['titre' => 'Infirmier·ère HES', 'desc' => "Poste d'infirmier·ère au sein de notre équipe soignante. Vous serez responsable de la prise en charge globale des résidents et du suivi médical.", 'type' => 'CDI', 'taux' => '80-100%', 'dept' => 'Soins', 'exigences' => "Bachelor HES en soins infirmiers\nASCIR souhaité\n2 ans d'expérience minimum\nMaîtrise du français", 'avantages' => "Salaire selon CCT\n5 semaines de vacances\nParking gratuit\nPrime de nuit"],
    ['titre' => 'Cuisinier·ère', 'desc' => 'Rejoignez notre cuisine pour préparer des repas équilibrés et savoureux pour nos résidents dans une cuisine moderne et bien équipée.', 'type' => 'CDD', 'taux' => '100%', 'dept' => 'Cuisine', 'exigences' => "CFC de cuisinier·ère\nConnaissance des régimes spéciaux\nHygiène HACCP", 'avantages' => "Horaires de jour\nWeekends en rotation\nRepas offerts"],
];

foreach ($offres as $o) {
    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO offres_emploi (id, titre, description, type_contrat, taux_activite, departement, lieu, exigences, avantages, date_limite, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Genève', ?, ?, '2026-05-31', 1, NOW())",
        [$id, $o['titre'], $o['desc'], $o['type'], $o['taux'], $o['dept'], $o['exigences'], $o['avantages']]
    );
    echo "Created: " . $o['titre'] . "\n";
}
echo "Done.\n";
