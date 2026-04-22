<?php
/**
 * Seed — Suggestions de démo pour la phase de co-construction.
 *
 * Idempotent : si SUG-2026-001 existe déjà, ne refait rien.
 * Sinon crée ~15 suggestions variées, les votes croisés, quelques commentaires.
 *
 * Usage : php migrations/083_seed_suggestions.php
 */

require_once __DIR__ . '/../init.php';

function pickByFonction(string $code): ?string
{
    return Db::getOne(
        "SELECT u.id FROM users u
         JOIN fonctions f ON f.id = u.fonction_id
         WHERE f.code = ? AND u.is_active = 1
         ORDER BY RAND() LIMIT 1",
        [$code]
    );
}

function pickByRole(string $role): ?string
{
    return Db::getOne(
        "SELECT id FROM users WHERE role = ? AND is_active = 1 ORDER BY RAND() LIMIT 1",
        [$role]
    );
}

function randomUsersExcept(string $except, int $n): array
{
    return array_column(
        Db::fetchAll(
            "SELECT id FROM users WHERE id != ? AND is_active = 1 ORDER BY RAND() LIMIT " . (int)$n,
            [$except]
        ),
        'id'
    );
}

function addComment(string $sugId, string $userId, string $content, string $role = 'user', string $visibility = 'public'): void
{
    Db::exec(
        "INSERT INTO suggestions_commentaires (id, suggestion_id, auteur_id, role, visibility, content)
         VALUES (?, ?, ?, ?, ?, ?)",
        [Uuid::v4(), $sugId, $userId, $role, $visibility, $content]
    );
    if ($visibility === 'public') {
        Db::exec("UPDATE suggestions SET comments_count = comments_count + 1 WHERE id = ?", [$sugId]);
    }
}

function addVotes(string $sugId, int $n, string $exceptUser): void
{
    $voters = randomUsersExcept($exceptUser, $n);
    foreach ($voters as $v) {
        Db::exec(
            "INSERT IGNORE INTO suggestions_votes (suggestion_id, user_id) VALUES (?, ?)",
            [$sugId, $v]
        );
    }
    $real = (int) Db::getOne("SELECT COUNT(*) FROM suggestions_votes WHERE suggestion_id = ?", [$sugId]);
    Db::exec("UPDATE suggestions SET votes_count = ? WHERE id = ?", [$real, $sugId]);
}

function createSug(array $row): ?string
{
    $author = $row['auteur_id'];
    if (!$author) { echo "  skipped (no matching user for fonction)\n"; return null; }

    $year = date('Y');
    $nb = (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE YEAR(created_at) = ?", [$year]);
    $ref = sprintf('SUG-%d-%03d', $year, $nb + 1);
    $id = Uuid::v4();

    $statut = $row['statut'] ?? 'nouvelle';
    $resolvedAt = in_array($statut, ['livree', 'refusee']) ? (new DateTime('-' . rand(1, 30) . ' days'))->format('Y-m-d H:i:s') : null;
    $createdAt = (new DateTime('-' . rand(1, 45) . ' days'))->format('Y-m-d H:i:s');

    Db::exec(
        "INSERT INTO suggestions
         (id, reference_code, auteur_id, titre, service, categorie, urgence, frequence,
          description, benefices, statut, motif_admin, sprint, resolved_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $ref, $author,
            $row['titre'], $row['service'], $row['categorie'], $row['urgence'],
            $row['frequence'] ?? null, $row['description'],
            !empty($row['benefices']) ? implode(',', $row['benefices']) : null,
            $statut, $row['motif_admin'] ?? null, $row['sprint'] ?? null,
            $resolvedAt, $createdAt, $createdAt,
        ]
    );

    // Historique initial
    Db::exec(
        "INSERT INTO suggestions_statut_history (id, suggestion_id, old_statut, new_statut, changed_by, created_at)
         VALUES (?, ?, NULL, 'nouvelle', ?, ?)",
        [Uuid::v4(), $id, $author, $createdAt]
    );

    // Historique si statut != nouvelle
    if ($statut !== 'nouvelle') {
        $progression = ['nouvelle', 'etudiee', 'planifiee', 'en_dev', 'livree'];
        $targetIdx = array_search($statut, $progression, true);
        if ($statut === 'refusee') {
            $adminId = pickByRole('direction') ?? pickByRole('admin');
            if ($adminId) {
                Db::exec(
                    "INSERT INTO suggestions_statut_history (id, suggestion_id, old_statut, new_statut, changed_by, motif, created_at)
                     VALUES (?, ?, 'nouvelle', 'refusee', ?, ?, ?)",
                    [Uuid::v4(), $id, $adminId, $row['motif_admin'] ?? null,
                     (new DateTime('-' . rand(1, 10) . ' days'))->format('Y-m-d H:i:s')]
                );
            }
        } elseif ($targetIdx !== false && $targetIdx > 0) {
            $adminId = pickByRole('direction') ?? pickByRole('admin');
            if ($adminId) {
                $steps = array_slice($progression, 1, $targetIdx);
                $date = new DateTime($createdAt);
                foreach ($steps as $i => $step) {
                    $date->modify('+' . (rand(2, 7)) . ' days');
                    $prev = $progression[array_search($step, $progression, true) - 1];
                    Db::exec(
                        "INSERT INTO suggestions_statut_history (id, suggestion_id, old_statut, new_statut, changed_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [Uuid::v4(), $id, $prev, $step, $adminId, $date->format('Y-m-d H:i:s')]
                    );
                }
            }
        }
    }

    return $id;
}

// Idempotence
$existing = (int) Db::getOne("SELECT COUNT(*) FROM suggestions");
if ($existing > 0) {
    echo "⚠️  Il y a déjà $existing suggestion(s) en base.\n";
    echo "Appuyez sur Ctrl+C pour annuler, ou Entrée pour continuer (des doublons seront créés)… ";
    if (defined('STDIN')) fgets(STDIN);
}

echo "🌱 Création des suggestions de démo…\n\n";

$directionUser = pickByRole('direction');

$suggestions = [
    // ─── Soins / AS / ASSC ─────────────────────────────────────────────────
    [
        'auteur_id'   => pickByFonction('AS'),
        'titre'       => 'Informatiser le formulaire de suivi de continence',
        'service'     => 'aide_soignant',
        'categorie'   => 'formulaire',
        'urgence'     => 'eleve',
        'frequence'   => 'multi_jour',
        'description' => "Nous remplissons encore à la main le classeur de suivi de continence pour chaque résident (changement, heure, type). Avec une centaine de résidents et 3 à 6 contrôles par jour, c'est chronophage, on oublie parfois de le faire, et c'est impossible de faire des statistiques.\n\nJ'aimerais un écran simple sur tablette : je sélectionne le résident, je coche ce qui a été fait, je valide. Historique consultable par l'infirmière et passation à l'équipe de nuit automatique.",
        'benefices'   => ['gain_temps', 'tracabilite', 'conformite', 'confort_resident'],
        'statut'      => 'etudiee',
        'votes'       => 22,
        'comments'    => [
            ['AS',   "Oui 1000 fois ! Le classeur disparaît tout le temps et on perd 10 min à chaque début de tour à le chercher."],
            ['INF',  "À combiner avec le suivi de protection (taille, marque) pour avoir une vue d'ensemble."],
            ['ASSC', "+1, ça permettrait aussi de suivre l'évolution d'un résident incontinent dans le temps."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('INF'),
        'titre'       => 'Alarme automatique si pas de changement de position depuis X heures',
        'service'     => 'infirmier',
        'categorie'   => 'alerte',
        'urgence'     => 'critique',
        'frequence'   => 'multi_jour',
        'description' => "Pour les résidents alités à risque d'escarres, le protocole est de les retourner toutes les 2-3 heures. Aujourd'hui on se fie à la passation orale et au cahier papier, mais on passe parfois à côté, surtout quand il y a beaucoup d'absents ou des nouveaux.\n\nProposition : un écran « résidents à retourner » qui affiche en rouge ceux qui n'ont pas bougé depuis leur seuil personnalisé, et une notification push à l'équipe soignante. Validation en 2 clics quand c'est fait.",
        'benefices'   => ['reduction_erreurs', 'tracabilite', 'conformite', 'confort_resident', 'securite'],
        'statut'      => 'planifiee',
        'sprint'      => 'Sprint 14',
        'votes'       => 28,
        'comments'    => [
            ['AS',      "Très attendu, on avait eu un audit qualité où c'est remonté."],
            ['RUV',     "On peut lier ça avec les profils Braden dans le dossier résident."],
            ['INF',     "Attention à ne pas sur-alerter. Seuil par résident adaptable à son risque."],
            ['DIRECT',  "Validé pour le prochain sprint. On prévoit la formation équipe de nuit en amont."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('AS'),
        'titre'       => 'Photos évolutives des plaies avec historique daté',
        'service'     => 'aide_soignant',
        'categorie'   => 'formulaire',
        'urgence'     => 'moyen',
        'frequence'   => 'hebdo',
        'description' => "Actuellement on prend des photos avec le téléphone perso (pas RGPD) pour suivre l'évolution d'une plaie. On aimerait pouvoir le faire depuis SpocSpace sur la tablette du module, avec date/heure automatique, annotation possible, et comparaison côte à côte avec la dernière photo.",
        'benefices'   => ['tracabilite', 'conformite', 'confort_resident'],
        'statut'      => 'nouvelle',
        'votes'       => 16,
        'comments'    => [
            ['ASSC', "Avec le médecin qui pourrait voir l'évolution à distance avant de venir."],
            ['INF',  "Il faut que ça soit chiffré et séparé du mur social."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('ASSC'),
        'titre'       => 'Checklist soins du matin avec validation mobile',
        'service'     => 'infirmier',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'moyen',
        'frequence'   => 'quotidien',
        'description' => "Tableau de bord des soins à faire le matin par résident, check list par soignant. Toilette, transfert, habillage, aide aux repas, prise médicament — coché sur mobile au fur et à mesure.\n\nBénéfice : la relève arrive, elle voit en un coup d'œil ce qui a été fait et ce qui reste.",
        'benefices'   => ['gain_temps', 'tracabilite', 'reduction_erreurs'],
        'statut'      => 'en_dev',
        'sprint'      => 'Sprint 13',
        'votes'       => 19,
        'comments'    => [
            ['AS',     "Parfait pour les journées à 6 AS et 2 infirmières, on se coordonne mal."],
            ['RUV',    "Maquette en cours de validation avec les chefs de module."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('AS'),
        'titre'       => 'Saisie vocale pour les transmissions',
        'service'     => 'aide_soignant',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'moyen',
        'frequence'   => 'quotidien',
        'description' => "Écrire les transmissions en fin de tour prend 10-15 minutes par soignant et on fait ça souvent en retard. Si on pouvait dicter sur tablette et que ça se mettait en forme automatiquement, ça serait génial.\n\n(Je sais que vous avez Whisper local pour les PV, ça peut marcher ici aussi non ?)",
        'benefices'   => ['gain_temps', 'tracabilite'],
        'statut'      => 'etudiee',
        'votes'       => 24,
        'comments'    => [
            ['ASSC', "Oui ! Surtout quand on enchaîne 3 tours de toilettes et qu'à la fin on a rien noté."],
            ['DIRECT', "Techniquement réalisable. À prioriser après la checklist."],
        ],
    ],

    // ─── Cuisine / Hôtellerie ──────────────────────────────────────────────
    [
        'auteur_id'   => pickByFonction('CUIS'),
        'titre'       => 'Tablette en cuisine pour voir les commandes en temps réel',
        'service'     => 'cuisine',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'eleve',
        'frequence'   => 'multi_jour',
        'description' => "On imprime encore la liste des repas le matin à 6h30. Quand quelqu'un change son menu à 10h, on le sait à midi… et c'est la course.\n\nUne tablette en cuisine affichant les réservations + les changements en rouge (avec un petit ping sonore) nous changerait la vie.",
        'benefices'   => ['gain_temps', 'reduction_erreurs'],
        'statut'      => 'planifiee',
        'sprint'      => 'Sprint 15',
        'votes'       => 14,
        'comments'    => [
            ['CHEF', "+1, et aussi voir les régimes spéciaux (sans sel, diabétique, mixé) en un coup d'œil."],
            ['HOT',  "On pourrait aussi avoir l'info des retours au même endroit."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('CHEF'),
        'titre'       => 'Mode hors-ligne pour cuisine (chambre froide, livraison)',
        'service'     => 'cuisine',
        'categorie'   => 'amelioration',
        'urgence'     => 'moyen',
        'frequence'   => 'quotidien',
        'description' => "Dans la chambre froide et la réserve, il n'y a pas de wifi. Quand on veut vérifier un lot ou marquer un produit, on doit ressortir. Un mode offline qui se synchronise dès qu'on revient dans la zone couverte serait précieux.",
        'benefices'   => ['gain_temps', 'tracabilite'],
        'statut'      => 'nouvelle',
        'votes'       => 9,
        'comments'    => [],
    ],
    [
        'auteur_id'   => pickByFonction('HOT'),
        'titre'       => 'QR code sur chaque chambre pour signaler un souci',
        'service'     => 'cuisine',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'faible',
        'frequence'   => 'hebdo',
        'description' => "Un QR code à côté du numéro de chambre. Un soignant flashe avec son téléphone → il sélectionne « lumière cassée », « robinet qui fuit », « demande ménage urgente ». La technique reçoit direct, plus besoin de chercher qui prévenir.",
        'benefices'   => ['gain_temps', 'tracabilite'],
        'statut'      => 'etudiee',
        'votes'       => 11,
        'comments'    => [
            ['AS', "Simple et malin. Surtout pour le week-end quand la technique n'est pas là, la liste se remplit et lundi ils savent direct où aller."],
        ],
    ],

    // ─── RH / Direction ────────────────────────────────────────────────────
    [
        'auteur_id'   => pickByRole('direction'),
        'titre'       => 'Alerte automatique fins de contrats CDD',
        'service'     => 'rh',
        'categorie'   => 'alerte',
        'urgence'     => 'eleve',
        'frequence'   => 'mensuel',
        'description' => "On a régulièrement des CDD qui finissent sans qu'on ait anticipé. Il faut une alerte 60 jours / 30 jours / 15 jours avant la fin, adressée à la RH et au responsable du collaborateur, avec un bouton « prolonger » / « ne pas renouveler » / « demander entretien ».",
        'benefices'   => ['reduction_erreurs', 'conformite', 'tracabilite'],
        'statut'      => 'livree',
        'sprint'      => 'Sprint 11',
        'votes'       => 8,
        'comments'    => [
            ['DIRECT', "Mis en prod lundi 15. Premier retour très positif côté RH, on a déjà rattrapé 2 dossiers."],
        ],
    ],
    [
        'auteur_id'   => pickByRole('direction'),
        'titre'       => 'Export mensuel automatique des heures supplémentaires',
        'service'     => 'direction',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'moyen',
        'frequence'   => 'mensuel',
        'description' => "Chaque fin de mois je passe 2h à compiler les heures supp par collaborateur pour la compta. Export Excel automatique le 1er du mois suivant, avec répartition par module et par fonction, directement envoyé à l'email de la compta.",
        'benefices'   => ['gain_temps', 'conformite'],
        'statut'      => 'en_dev',
        'votes'       => 6,
        'comments'    => [],
    ],
    [
        'auteur_id'   => pickByFonction('RUV'),
        'titre'       => 'Tableau de bord absentéisme temps réel par module',
        'service'     => 'direction',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'moyen',
        'frequence'   => 'hebdo',
        'description' => "Pouvoir voir en un écran : taux d'absentéisme de la semaine par module, évolution sur 3 mois, nombre d'arrêts maladie courts répétés (signal faible burn-out). Actuellement on le calcule en fin de mois quand c'est trop tard.",
        'benefices'   => ['tracabilite', 'reduction_erreurs'],
        'statut'      => 'etudiee',
        'votes'       => 12,
        'comments'    => [
            ['DIRECT', "À croiser avec les données LTr existantes."],
        ],
    ],
    [
        'auteur_id'   => pickByRole('direction'),
        'titre'       => 'Tableau de bord LTr en temps réel (dépassements du jour)',
        'service'     => 'direction',
        'categorie'   => 'alerte',
        'urgence'     => 'critique',
        'frequence'   => 'quotidien',
        'description' => "J'aimerais ouvrir SpocSpace le matin et voir tout de suite qui est en dépassement du temps de travail LTr ce jour / cette semaine, avec alerte rouge si 11h/jour ou 50h/semaine dépassées. Aujourd'hui on découvre ça en fin de mois.",
        'benefices'   => ['conformite', 'securite', 'reduction_erreurs'],
        'statut'      => 'en_dev',
        'sprint'      => 'Sprint 13',
        'votes'       => 10,
        'comments'    => [
            ['DIRECT', "Les règles LTr sont déjà dans ems_config, il reste juste le widget de dashboard."],
        ],
    ],

    // ─── Animation / ASE ───────────────────────────────────────────────────
    [
        'auteur_id'   => pickByFonction('ASE'),
        'titre'       => 'Planning animation visible par les familles',
        'service'     => 'animation',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'moyen',
        'frequence'   => 'hebdo',
        'description' => "Les familles demandent souvent quelles animations il y a cette semaine. On leur envoie le flyer papier ou ils oublient. Si elles pouvaient voir sur l'espace famille : « lundi 15h loto au salon », « mercredi 14h sortie Carouge », avec photos une fois passé.",
        'benefices'   => ['confort_resident', 'tracabilite'],
        'statut'      => 'nouvelle',
        'votes'       => 15,
        'comments'    => [
            ['ASE', "Si on pouvait même permettre aux familles de s'inscrire à une sortie avec leur proche ça serait parfait."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('ASE'),
        'titre'       => 'Agenda partagé des événements par module',
        'service'     => 'animation',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'faible',
        'frequence'   => 'hebdo',
        'description' => "Chaque module a ses petits événements (anniversaires, visites thérapeutiques, rdv kiné). Avoir un agenda partagé par module que toute l'équipe soignante peut voir évite les doublons et les oublis.",
        'benefices'   => ['gain_temps', 'tracabilite'],
        'statut'      => 'nouvelle',
        'votes'       => 7,
        'comments'    => [],
    ],

    // ─── Divers ─────────────────────────────────────────────────────────────
    [
        'auteur_id'   => pickByFonction('INF'),
        'titre'       => 'Rappels vaccinations grippe / COVID par résident',
        'service'     => 'infirmier',
        'categorie'   => 'alerte',
        'urgence'     => 'moyen',
        'frequence'   => 'mensuel',
        'description' => "Chaque automne on court après les consentements et les rappels pour la grippe. Un tableau « état vaccinal » par résident avec alertes dès que la dose précédente a > 11 mois nous ferait gagner du temps et éviter les oublis.",
        'benefices'   => ['reduction_erreurs', 'conformite', 'tracabilite'],
        'statut'      => 'nouvelle',
        'votes'       => 13,
        'comments'    => [
            ['ASSC', "Et les rappels zona pour les plus de 65 ans aussi."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('AS'),
        'titre'       => 'Bouton « je prends ma pause » partagé équipe',
        'service'     => 'aide_soignant',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'faible',
        'frequence'   => 'quotidien',
        'description' => "Petit bouton sur l'accueil : « je pars en pause — retour 10h15 ». L'équipe voit qui est dispo, quand. Moins d'interruptions, moins de stress, et pas besoin de crier dans le couloir.",
        'benefices'   => ['gain_temps', 'confort_resident'],
        'statut'      => 'nouvelle',
        'votes'       => 11,
        'comments'    => [
            ['ASSC', "Simple mais utile, surtout sur les gros modules."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('APP'),
        'titre'       => 'Ma journée type d\'apprentie avec gestes à valider',
        'service'     => 'aide_soignant',
        'categorie'   => 'formulaire',
        'urgence'     => 'faible',
        'frequence'   => 'quotidien',
        'description' => "En tant qu'apprentie, j'aurais besoin d'une check-list des gestes que je dois apprendre (toilette complète, aide transfert, surveillance, administration médicaments sous supervision, etc.) avec un endroit où mon formateur peut valider chaque geste quand il m'a vue le faire. Ça me permettrait de suivre ma progression.",
        'benefices'   => ['tracabilite', 'conformite'],
        'statut'      => 'etudiee',
        'votes'       => 5,
        'comments'    => [
            ['RUV', "À intégrer avec le module stagiaires existant."],
        ],
    ],
    [
        'auteur_id'   => pickByFonction('AS'),
        'titre'       => 'Remplacer le tableau blanc du poste soignant',
        'service'     => 'aide_soignant',
        'categorie'   => 'fonctionnalite',
        'urgence'     => 'faible',
        'frequence'   => 'quotidien',
        'description' => "Le tableau blanc du poste où on écrit « chambre 12 : rdv kiné 14h », « chambre 8 : transfert hôpital » est illisible, on l'efface tout le temps par erreur, et l'équipe suivante ne sait pas ce qu'il y avait avant.\n\nUn écran partagé tablette/grand écran avec historique et rattachement au résident serait parfait.",
        'benefices'   => ['tracabilite', 'reduction_erreurs'],
        'statut'      => 'refusee',
        'motif_admin' => "Fonctionnalité trop proche des transmissions existantes. On préfère muscler les transmissions plutôt que créer un deuxième canal.",
        'votes'       => 4,
        'comments'    => [],
    ],
    [
        'auteur_id'   => pickByFonction('ASSC'),
        'titre'       => 'Notification quand un résident revient d\'hospitalisation',
        'service'     => 'infirmier',
        'categorie'   => 'alerte',
        'urgence'     => 'eleve',
        'frequence'   => 'ponctuel',
        'description' => "Quand un résident revient d'hôpital, il y a toujours un flou : qui a reçu les documents de sortie, qui a refait le pilulier, qui a prévenu la famille. Un process guidé (checklist retour d'hospitalisation avec responsable pour chaque tâche) serait un vrai plus.",
        'benefices'   => ['reduction_erreurs', 'conformite', 'confort_resident', 'securite'],
        'statut'      => 'planifiee',
        'sprint'      => 'Sprint 16',
        'votes'       => 18,
        'comments'    => [
            ['INF',    "+1000, on a eu 2 erreurs médicamenteuses liées à ça l'année passée."],
            ['DIRECT', "Priorité haute. À spécifier avec la pharmacie référente."],
        ],
    ],
];

$created = 0;
$totalVotes = 0;
$totalComments = 0;

foreach ($suggestions as $i => $row) {
    echo sprintf("[%02d/%d] %s\n", $i + 1, count($suggestions), $row['titre']);

    $id = createSug($row);
    if (!$id) continue;
    $created++;

    // Votes
    if (!empty($row['votes']) && !empty($row['auteur_id'])) {
        addVotes($id, $row['votes'], $row['auteur_id']);
        $totalVotes += $row['votes'];
    }

    // Comments
    foreach ($row['comments'] ?? [] as $c) {
        [$who, $text] = $c;
        $uid = match ($who) {
            'DIRECT'  => $directionUser ?? pickByRole('admin'),
            'AS'      => pickByFonction('AS'),
            'ASSC'    => pickByFonction('ASSC'),
            'INF'     => pickByFonction('INF'),
            'ASE'     => pickByFonction('ASE'),
            'RUV'     => pickByFonction('RUV'),
            'CHEF'    => pickByFonction('CHEF'),
            'CUIS'    => pickByFonction('CUIS'),
            'HOT'     => pickByFonction('HOT'),
            default   => pickByRole('collaborateur'),
        };
        if (!$uid) continue;
        $role = ($who === 'DIRECT') ? 'admin' : 'user';
        addComment($id, $uid, $text, $role, 'public');
        $totalComments++;
    }
}

echo "\n✅ Terminé\n";
echo "  - $created suggestions créées\n";
echo "  - $totalVotes votes ajoutés\n";
echo "  - $totalComments commentaires ajoutés\n";
echo "\n🔗 Accès :\n";
echo "  - Employé : /spocspace/suggestions\n";
echo "  - Admin   : /spocspace/admin/suggestions\n";
