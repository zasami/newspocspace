<?php
/**
 * Terrassière — Seed 9 demo sondages (3 brouillon, 3 ouvert, 3 fermé)
 * Run: php migrations/017_seed_sondages.php
 */
require_once __DIR__ . '/../init.php';

echo "=== Seed Sondages Demo ===\n\n";

// Get a responsable/admin user to assign as creator
$creator = Db::fetch("SELECT id FROM users WHERE role IN ('admin','direction','responsable') AND is_active = 1 LIMIT 1");
if (!$creator) { echo "ERROR: No admin/responsable user found.\n"; exit(1); }
$creatorId = $creator['id'];

// Get some active users for responses
$users = Db::fetchAll("SELECT id FROM users WHERE is_active = 1 ORDER BY RAND() LIMIT 20");
if (count($users) < 5) { echo "ERROR: Need at least 5 active users.\n"; exit(1); }

$sondages = [
    // ── 3 BROUILLON ──
    [
        'titre' => 'Horaires d\'été 2026',
        'description' => 'Sondage pour déterminer les préférences horaires durant la période estivale.',
        'statut' => 'brouillon',
        'is_anonymous' => 0,
        'questions' => [
            ['question' => 'Préférez-vous des horaires décalés en été ?', 'type' => 'choix_unique', 'options' => ['Oui, plus tôt le matin', 'Oui, plus tard le soir', 'Non, horaires habituels']],
            ['question' => 'Seriez-vous disponible pour des gardes le week-end en juillet ?', 'type' => 'choix_unique', 'options' => ['Oui', 'Non', 'Peut-être']],
            ['question' => 'Commentaires ou suggestions', 'type' => 'texte_libre', 'options' => []],
        ],
    ],
    [
        'titre' => 'Nouveau mobilier salle de pause',
        'description' => 'Choix du mobilier pour le réaménagement de la salle de pause.',
        'statut' => 'brouillon',
        'is_anonymous' => 0,
        'questions' => [
            ['question' => 'Quel type de table préférez-vous ?', 'type' => 'choix_unique', 'options' => ['Tables rondes (4 places)', 'Tables rectangulaires (6 places)', 'Mix des deux']],
            ['question' => 'Quels équipements souhaitez-vous ?', 'type' => 'choix_multiple', 'options' => ['Micro-ondes supplémentaire', 'Machine à café premium', 'Distributeur de snacks', 'Coin lecture']],
        ],
    ],
    [
        'titre' => 'Thème de la fête du personnel',
        'description' => 'Votez pour le thème de la prochaine fête annuelle du personnel.',
        'statut' => 'brouillon',
        'is_anonymous' => 1,
        'questions' => [
            ['question' => 'Quel thème pour la fête ?', 'type' => 'choix_unique', 'options' => ['Soirée Casino', 'Garden Party', 'Années 80', 'Bal masqué']],
            ['question' => 'Quel jour vous convient le mieux ?', 'type' => 'choix_unique', 'options' => ['Vendredi soir', 'Samedi après-midi', 'Samedi soir']],
            ['question' => 'Avez-vous des restrictions alimentaires ?', 'type' => 'texte_libre', 'options' => []],
        ],
    ],

    // ── 3 OUVERT ──
    [
        'titre' => 'Satisfaction conditions de travail',
        'description' => 'Évaluez votre satisfaction concernant vos conditions de travail actuelles.',
        'statut' => 'ouvert',
        'is_anonymous' => 1,
        'questions' => [
            ['question' => 'Comment évaluez-vous l\'ambiance de travail ?', 'type' => 'choix_unique', 'options' => ['Très bonne', 'Bonne', 'Moyenne', 'À améliorer']],
            ['question' => 'Quels aspects souhaitez-vous améliorer ?', 'type' => 'choix_multiple', 'options' => ['Communication interne', 'Équipements', 'Organisation des plannings', 'Formation continue', 'Espaces de repos']],
            ['question' => 'La charge de travail est-elle adaptée ?', 'type' => 'choix_unique', 'options' => ['Trop légère', 'Adaptée', 'Un peu élevée', 'Trop élevée']],
            ['question' => 'Suggestions d\'amélioration', 'type' => 'texte_libre', 'options' => []],
        ],
    ],
    [
        'titre' => 'Formation continue 2026-2027',
        'description' => 'Identifiez les formations qui vous intéressent pour le prochain plan de formation.',
        'statut' => 'ouvert',
        'is_anonymous' => 0,
        'questions' => [
            ['question' => 'Quelles formations vous intéressent ?', 'type' => 'choix_multiple', 'options' => ['Soins palliatifs', 'Gestion du stress', 'Premiers secours avancés', 'Communication bienveillante', 'Informatique / logiciels']],
            ['question' => 'Préférence de format', 'type' => 'choix_unique', 'options' => ['Présentiel (1 journée)', 'En ligne (modules courts)', 'Hybride', 'Pas de préférence']],
            ['question' => 'Y a-t-il une formation spécifique que vous souhaitez ?', 'type' => 'texte_libre', 'options' => []],
        ],
    ],
    [
        'titre' => 'Organisation des pauses',
        'description' => 'Donnez votre avis sur l\'organisation actuelle des pauses.',
        'statut' => 'ouvert',
        'is_anonymous' => 1,
        'questions' => [
            ['question' => 'La durée des pauses est-elle suffisante ?', 'type' => 'choix_unique', 'options' => ['Oui, parfaite', 'Un peu courte', 'Trop courte', 'Trop longue']],
            ['question' => 'Préférez-vous des pauses fixes ou flexibles ?', 'type' => 'choix_unique', 'options' => ['Fixes (horaires définis)', 'Flexibles (quand possible)', 'Mixte selon le service']],
        ],
    ],

    // ── 3 FERMÉ ──
    [
        'titre' => 'Bilan formation premiers secours',
        'description' => 'Retour sur la formation premiers secours dispensée en janvier 2026.',
        'statut' => 'ferme',
        'is_anonymous' => 0,
        'questions' => [
            ['question' => 'La formation a-t-elle répondu à vos attentes ?', 'type' => 'choix_unique', 'options' => ['Tout à fait', 'Plutôt oui', 'Plutôt non', 'Pas du tout']],
            ['question' => 'Le formateur était-il compétent ?', 'type' => 'choix_unique', 'options' => ['Excellent', 'Bon', 'Moyen', 'Insuffisant']],
            ['question' => 'Quels points améliorer pour la prochaine session ?', 'type' => 'texte_libre', 'options' => []],
        ],
    ],
    [
        'titre' => 'Choix traiteur repas de Noël 2025',
        'description' => 'Sélection du traiteur pour le repas de Noël du personnel.',
        'statut' => 'ferme',
        'is_anonymous' => 0,
        'questions' => [
            ['question' => 'Quel traiteur préférez-vous ?', 'type' => 'choix_unique', 'options' => ['Le Jardin des Saveurs', 'Traiteur du Léman', 'La Table de Marie', 'Autre']],
            ['question' => 'Quelles options supplémentaires ?', 'type' => 'choix_multiple', 'options' => ['Menu végétarien', 'Menu sans gluten', 'Bar à desserts', 'Animation musicale']],
        ],
    ],
    [
        'titre' => 'Évaluation journée portes ouvertes',
        'description' => 'Retour sur la journée portes ouvertes de février 2026.',
        'statut' => 'ferme',
        'is_anonymous' => 1,
        'questions' => [
            ['question' => 'L\'organisation globale était-elle satisfaisante ?', 'type' => 'choix_unique', 'options' => ['Très satisfaisante', 'Satisfaisante', 'Peu satisfaisante', 'Pas satisfaisante']],
            ['question' => 'Quels ateliers avez-vous préférés ?', 'type' => 'choix_multiple', 'options' => ['Visite des locaux', 'Atelier soins', 'Présentation équipe', 'Buffet convivial']],
            ['question' => 'Vos remarques sur l\'événement', 'type' => 'texte_libre', 'options' => []],
        ],
    ],
];

// Sample text responses for texte_libre questions
$textResponses = [
    'Très bonne initiative, merci !',
    'Il faudrait plus de temps pour les pauses.',
    'RAS, tout est bien.',
    'J\'aimerais plus de formations pratiques.',
    'Excellente organisation, bravo à l\'équipe.',
    'Peut-être revoir les horaires du matin.',
    'Rien à signaler.',
    'Super ambiance, continuez comme ça.',
    'Il manquait un peu de communication en amont.',
    'Très satisfait(e), à refaire !',
];

$countCreated = 0;

foreach ($sondages as $s) {
    $sId = Uuid::v4();

    // Offset dates: brouillon = recent, ouvert = 1-2 weeks ago, ferme = 1-3 months ago
    $dateOffset = match ($s['statut']) {
        'brouillon' => rand(0, 3),
        'ouvert'    => rand(5, 14),
        'ferme'     => rand(30, 90),
    };
    $createdAt = date('Y-m-d H:i:s', strtotime("-{$dateOffset} days"));

    Db::exec(
        "INSERT INTO sondages (id, titre, description, is_anonymous, created_by, statut, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$sId, $s['titre'], $s['description'], $s['is_anonymous'], $creatorId, $s['statut'], $createdAt]
    );

    $questionIds = [];
    foreach ($s['questions'] as $i => $q) {
        $qId = Uuid::v4();
        $questionIds[] = ['id' => $qId, 'type' => $q['type'], 'options' => $q['options']];
        $opts = !empty($q['options']) ? json_encode($q['options']) : null;

        Db::exec(
            "INSERT INTO sondage_questions (id, sondage_id, question, type, options, ordre)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$qId, $sId, $q['question'], $q['type'], $opts, $i]
        );
    }

    // Add responses for ouvert and ferme sondages
    if ($s['statut'] !== 'brouillon') {
        $nbRespondents = $s['statut'] === 'ferme' ? rand(8, 15) : rand(3, 8);
        $respondents = array_slice($users, 0, min($nbRespondents, count($users)));

        foreach ($respondents as $user) {
            foreach ($questionIds as $qInfo) {
                $response = '';
                if ($qInfo['type'] === 'texte_libre') {
                    // ~60% chance of answering text questions
                    if (rand(1, 100) > 40) continue;
                    $response = $textResponses[array_rand($textResponses)];
                } elseif ($qInfo['type'] === 'choix_unique') {
                    $response = $qInfo['options'][array_rand($qInfo['options'])];
                } elseif ($qInfo['type'] === 'choix_multiple') {
                    $picks = array_rand($qInfo['options'], rand(1, min(3, count($qInfo['options']))));
                    if (!is_array($picks)) $picks = [$picks];
                    $response = json_encode(array_map(fn($i) => $qInfo['options'][$i], $picks));
                }

                try {
                    Db::exec(
                        "INSERT INTO sondage_reponses (id, sondage_id, question_id, user_id, reponse)
                         VALUES (?, ?, ?, ?, ?)",
                        [Uuid::v4(), $sId, $qInfo['id'], $user['id'], $response]
                    );
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
        }
    }

    $countCreated++;
    echo "  ✓ [{$s['statut']}] {$s['titre']}\n";
}

echo "\nDone: {$countCreated} sondages created.\n";
