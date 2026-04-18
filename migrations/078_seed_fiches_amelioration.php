<?php
/**
 * 078_seed_fiches_amelioration.php — Seed fiches d'amélioration pour démo
 *
 * Usage : php migrations/078_seed_fiches_amelioration.php
 */

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../init.php';

// ─── Users (par email pour être robuste) ───────────────────────────────
$samiId    = Db::getOne("SELECT id FROM users WHERE email = 'zaghbani.sami@gmail.com'");
$laetitiaId = Db::getOne("SELECT id FROM users WHERE email = 'laetitia.aubert@terrassiere.ch'");
$adminId   = Db::getOne("SELECT id FROM users WHERE email = 'admin@terrassiere.ch'");
$marieId   = Db::getOne("SELECT id FROM users WHERE email = 'marie.petit@terrassiere.ch'");
$cedricId  = Db::getOne("SELECT id FROM users WHERE prenom = 'Cédric' AND nom = 'Guex' LIMIT 1");
$philippeId = Db::getOne("SELECT id FROM users WHERE prenom = 'Philippe' AND nom = 'Dubois' LIMIT 1");
$romainId  = Db::getOne("SELECT id FROM users WHERE prenom = 'Romain' AND nom = 'Michel' LIMIT 1");

foreach (['sami' => $samiId, 'laetitia' => $laetitiaId, 'admin' => $adminId, 'marie' => $marieId] as $label => $id) {
    echo ($id ? "✓" : "✗") . " $label: " . ($id ?: 'MISSING') . PHP_EOL;
}

// ─── Modules ────────────────────────────────────────────────────────────
$mod = Db::fetchAll("SELECT id, code FROM modules ORDER BY ordre");
$modById = array_column($mod, 'id', 'code');
$m1 = $modById['M1'] ?? null;
$m2 = $modById['M2'] ?? null;
$m3 = $modById['M3'] ?? null;
$m4 = $modById['M4'] ?? null;

// ─── Référence auto ─────────────────────────────────────────────────────
$year = date('Y');
$existingNb = (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE YEAR(created_at) = ?", [$year]);
$counter = $existingNb;

// ─── Helpers ───────────────────────────────────────────────────────────
function nextRef(&$counter) {
    $counter++;
    return sprintf('FAC-%d-%03d', date('Y'), $counter);
}

function insertFiche(array $fiche, &$counter) {
    $ref = nextRef($counter);
    $id = Uuid::v4();
    $createdAt = $fiche['created_at'] ?? date('Y-m-d H:i:s', strtotime('-' . rand(1, 25) . ' days'));

    Db::exec(
        "INSERT INTO fiches_amelioration
         (id, reference_code, auteur_id, is_anonymous, visibility, type_evenement,
          personnes_concernees_types, unite_module_id, titre, categorie, criticite,
          description, suggestion, date_evenement, heure_evenement, lieu_precis,
          mesures_immediates, causes_identifiees, actions_correctives,
          statut, is_draft, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $ref,
            $fiche['auteur_id'],
            $fiche['is_anonymous'] ?? 0,
            $fiche['visibility'] ?? 'private',
            $fiche['type_evenement'] ?? 'suggestion',
            $fiche['personnes_concernees_types'] ?? null,
            $fiche['unite_module_id'] ?? null,
            $fiche['titre'],
            $fiche['categorie'] ?? 'autre',
            $fiche['criticite'] ?? 'moyenne',
            $fiche['description'],
            $fiche['suggestion'] ?? null,
            $fiche['date_evenement'] ?? null,
            $fiche['heure_evenement'] ?? null,
            $fiche['lieu_precis'] ?? null,
            $fiche['mesures_immediates'] ?? null,
            $fiche['causes_identifiees'] ?? null,
            $fiche['actions_correctives'] ?? null,
            $fiche['statut'] ?? 'soumise',
            $fiche['is_draft'] ?? 0,
            $createdAt,
        ]
    );

    // Concernés
    if (!empty($fiche['concernes_ids'])) {
        $stmt = Db::connect()->prepare("INSERT IGNORE INTO fiches_amelioration_concernes (fiche_id, user_id) VALUES (?, ?)");
        foreach ($fiche['concernes_ids'] as $uid) {
            if ($uid) $stmt->execute([$id, $uid]);
        }
    }

    // Commentaires
    if (!empty($fiche['comments'])) {
        $cStmt = Db::connect()->prepare(
            "INSERT INTO fiches_amelioration_commentaires (id, fiche_id, auteur_id, is_anonymous, role, content, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($fiche['comments'] as $i => $c) {
            $cStmt->execute([
                Uuid::v4(), $id,
                $c['anonymous'] ?? false ? null : ($c['auteur_id'] ?? null),
                !empty($c['anonymous']) ? 1 : 0,
                $c['role'] ?? 'user',
                $c['content'],
                date('Y-m-d H:i:s', strtotime($createdAt . ' +' . ($i + 1) . ' day')),
            ]);
        }
    }

    echo "  + $ref | " . substr($fiche['titre'], 0, 60) . PHP_EOL;
    return $id;
}

// ─── Données ───────────────────────────────────────────────────────────
echo PHP_EOL . "Insertion des fiches..." . PHP_EOL;

// 1. Sami — Suggestion publique, statut soumise, gravité faible
insertFiche([
    'auteur_id' => $samiId,
    'is_anonymous' => 0,
    'visibility' => 'public',
    'type_evenement' => 'suggestion',
    'personnes_concernees_types' => 'collaborateur',
    'unite_module_id' => $m1,
    'titre' => 'Ajouter des plantes vertes dans les couloirs',
    'categorie' => 'organisation',
    'criticite' => 'faible',
    'description' => '<p>Plusieurs résidents et familles ont remarqué que les couloirs paraissent un peu tristes et médicalisés. Ajouter quelques plantes vertes d\'entretien facile apporterait de la vie et améliorerait le bien-être général.</p>',
    'suggestion' => '<p>Proposer 3-4 plantes (pothos, sansevieria, zamioculcas) par étage, confiées à un roulement de collaborateurs volontaires.</p><ul class="checklist"><li>Identifier 2 collaborateurs volontaires par étage</li><li>Budget de 150 CHF pour l\'achat initial</li><li>Planning d\'arrosage affiché</li></ul>',
    'date_evenement' => date('Y-m-d', strtotime('-8 days')),
    'lieu_precis' => 'Couloirs des étages 1 et 2',
    'statut' => 'soumise',
    'comments' => [
        ['auteur_id' => $laetitiaId, 'role' => 'user', 'content' => 'Super idée Sami ! Je suis volontaire pour m\'en occuper à l\'étage 2.'],
    ],
], $counter);

// 2. Laetitia — Incident, gravité haute, statut en_cours, ciblée
insertFiche([
    'auteur_id' => $laetitiaId,
    'visibility' => 'targeted',
    'type_evenement' => 'incident',
    'personnes_concernees_types' => 'resident,collaborateur',
    'unite_module_id' => $m2,
    'titre' => 'Erreur de régime alimentaire au repas du midi',
    'categorie' => 'qualite_soins',
    'criticite' => 'haute',
    'description' => '<p>Lors de la distribution du repas de midi, il a été constaté que le plateau du résident de la chambre 12 <strong>ne correspondait pas au régime alimentaire prescrit (sans sel)</strong>.</p><p>Le plateau standard lui a été servi par erreur.</p>',
    'suggestion' => '<p>Mettre en place un système de double-vérification : l\'aide-soignant vérifie l\'étiquette du plateau avant de le remettre au résident.</p>',
    'mesures_immediates' => '<p>Le plateau a été échangé immédiatement. Le résident n\'a pas consommé le repas incorrect. L\'infirmière responsable a été informée.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-4 days')),
    'heure_evenement' => '12:15',
    'lieu_precis' => 'Chambre 12, Module 2',
    'causes_identifiees' => 'facteur_humain,organisation',
    'actions_correctives' => '<p>Formation rappel pour toute l\'équipe du module 2. Affichage plastifié du régime alimentaire dans chaque chambre.</p>',
    'statut' => 'en_cours',
    'concernes_ids' => [$marieId, $cedricId],
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Merci Laetitia pour ce signalement. Je propose une réunion d\'équipe cette semaine pour rappeler les procédures.'],
        ['auteur_id' => $laetitiaId, 'role' => 'user', 'content' => 'Parfait, je suis disponible mercredi après-midi.'],
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Réunion planifiée. Affichage plastifié en cours de préparation.'],
    ],
], $counter);

// 3. Anonyme (Sami) — Plainte, gravité moyenne, privée
insertFiche([
    'auteur_id' => $samiId, // sera transformé en NULL par is_anonymous
    'is_anonymous' => 1,
    'visibility' => 'private',
    'type_evenement' => 'plainte',
    'personnes_concernees_types' => 'collaborateur',
    'unite_module_id' => $m3,
    'titre' => 'Tensions répétées dans l\'équipe de nuit',
    'categorie' => 'communication',
    'criticite' => 'moyenne',
    'description' => '<p>Il existe depuis plusieurs semaines des tensions relationnelles entre certains membres de l\'équipe de nuit qui créent une ambiance pesante et affectent la qualité des transmissions.</p>',
    'suggestion' => '<p>Une médiation encadrée par une personne externe pourrait aider à désamorcer la situation.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-14 days')),
    'statut' => 'en_revue',
], $counter);

// Réécrit la dernière fiche pour supprimer auteur_id (anonymat strict)
$lastAnonId = Db::getOne("SELECT id FROM fiches_amelioration WHERE is_anonymous = 1 ORDER BY created_at DESC LIMIT 1");
if ($lastAnonId) Db::exec("UPDATE fiches_amelioration SET auteur_id = NULL WHERE id = ?", [$lastAnonId]);

// 4. Admin (Marie) — Non-conformité, statut en_revue
insertFiche([
    'auteur_id' => $marieId,
    'visibility' => 'private',
    'type_evenement' => 'non_conformite',
    'personnes_concernees_types' => 'resident',
    'unite_module_id' => $m1,
    'titre' => 'Traçabilité des températures frigos incomplète',
    'categorie' => 'securite',
    'criticite' => 'haute',
    'description' => '<p>Le registre de contrôle des températures des frigos médicaments n\'a pas été rempli pendant 5 jours consécutifs (du 8 au 12).</p><p>Conformité HACCP non respectée.</p>',
    'suggestion' => '<p>Automatiser la traçabilité avec des sondes connectées (ThermoTrack ou équivalent) qui envoient une alerte en cas de dépassement.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-10 days')),
    'lieu_precis' => 'Local médicaments — Module 1',
    'causes_identifiees' => 'procedure,organisation',
    'statut' => 'en_revue',
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'J\'ai consulté le fournisseur ThermoTrack, devis en cours. Je reviens vers toi d\'ici la semaine prochaine.'],
    ],
], $counter);

// 5. Philippe — Presque-accident, réalisée
insertFiche([
    'auteur_id' => $philippeId,
    'visibility' => 'public',
    'type_evenement' => 'presque_accident',
    'personnes_concernees_types' => 'resident,visiteur',
    'unite_module_id' => $m4,
    'titre' => 'Chute évitée — sol mouillé non signalé',
    'categorie' => 'securite',
    'criticite' => 'moyenne',
    'description' => '<p>Un visiteur a failli glisser sur le sol mouillé du couloir de l\'étage 7 après le passage de l\'équipe de ménage.</p><p>Aucun panneau "sol mouillé" n\'avait été installé.</p>',
    'suggestion' => '<p>Rendre obligatoire la pose du panneau jaune pendant tout le temps du nettoyage, et 30 min après.</p>',
    'mesures_immediates' => '<p>Panneau installé immédiatement après l\'incident. Information transmise à l\'équipe de ménage.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-21 days')),
    'heure_evenement' => '09:40',
    'lieu_precis' => 'Couloir principal — Étage 7',
    'causes_identifiees' => 'procedure,facteur_humain',
    'actions_correctives' => '<p>Rappel écrit à l\'équipe de ménage. Ajout de la vérification au check-list journalier du responsable hygiène.</p>',
    'statut' => 'realisee',
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Merci Philippe. Action corrective en place depuis 2 semaines, aucun nouvel incident.'],
    ],
], $counter);

// 6. Cédric — Dysfonctionnement matériel, soumise
insertFiche([
    'auteur_id' => $cedricId,
    'visibility' => 'private',
    'type_evenement' => 'dysfonctionnement',
    'personnes_concernees_types' => 'collaborateur',
    'unite_module_id' => $m2,
    'titre' => 'Lève-personne bloqué depuis 2 jours',
    'categorie' => 'materiel',
    'criticite' => 'haute',
    'description' => '<p>Le lève-personne électrique du module 2 reste bloqué en position basse et ne répond plus à la télécommande.</p><p>Impact : les transferts se font manuellement, ce qui est pénible pour les résidents et risqué pour les soignants (TMS).</p>',
    'suggestion' => '<p>Contacter le fournisseur en urgence et prévoir un lève-personne de secours en attendant.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-2 days')),
    'lieu_precis' => 'Salle de transfert — Module 2',
    'statut' => 'soumise',
], $counter);

// 7. Laetitia — Suggestion publique, réalisée
insertFiche([
    'auteur_id' => $laetitiaId,
    'visibility' => 'public',
    'type_evenement' => 'suggestion',
    'personnes_concernees_types' => 'resident',
    'unite_module_id' => $m1,
    'titre' => 'Atelier musicothérapie hebdomadaire',
    'categorie' => 'qualite_soins',
    'criticite' => 'faible',
    'description' => '<p>Proposer un atelier musicothérapie hebdomadaire d\'1h pour les résidents atteints de troubles cognitifs.</p><p>Plusieurs études montrent des bénéfices significatifs sur l\'humeur, l\'anxiété et les interactions sociales.</p>',
    'suggestion' => '<p>Embaucher une musicothérapeute diplômée en prestation externe, 1 demi-journée par semaine.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-60 days')),
    'causes_identifiees' => 'organisation',
    'actions_correctives' => '<p>Musicothérapeute recrutée (Mme Bertholet), intervention le jeudi matin 10h-12h depuis le 15 mars.</p>',
    'statut' => 'realisee',
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Excellente initiative. Budget validé en conseil de direction.'],
        ['auteur_id' => $samiId, 'role' => 'user', 'content' => 'Les résidents du module 1 adorent, on voit une vraie différence.'],
        ['auteur_id' => $marieId, 'role' => 'user', 'content' => 'Je confirme, Mme Favre qui ne parlait plus depuis 6 mois a fredonné hier !'],
    ],
], $counter);

// 8. Romain — Incident communication, rejetée
insertFiche([
    'auteur_id' => $romainId,
    'visibility' => 'private',
    'type_evenement' => 'plainte',
    'personnes_concernees_types' => 'collaborateur',
    'unite_module_id' => $m3,
    'titre' => 'Demande de parking réservé collaborateurs',
    'categorie' => 'organisation',
    'criticite' => 'faible',
    'description' => '<p>Il faudrait des places de parking réservées au personnel, car on arrive parfois en retard faute de place.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-30 days')),
    'causes_identifiees' => 'indeterminee',
    'actions_correctives' => '<p>La convention avec la commune ne permet pas de réserver des places. L\'EMS encourage plutôt le covoiturage via SpocSpace.</p>',
    'statut' => 'rejetee',
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Merci pour la suggestion. Malheureusement ce n\'est pas possible juridiquement : les places sont gérées par la commune. Nous encourageons le covoiturage via SpocSpace — 12 binômes actifs actuellement.'],
    ],
], $counter);

// 9. Anonyme — Brouillon
insertFiche([
    'auteur_id' => null,
    'is_anonymous' => 1,
    'visibility' => 'private',
    'type_evenement' => 'suggestion',
    'titre' => '[Brouillon] Idée sur la gestion des pauses',
    'categorie' => 'organisation',
    'criticite' => 'faible',
    'description' => '<p>Note en cours de rédaction…</p>',
    'is_draft' => 1,
    'statut' => 'soumise',
], $counter);

// 10. Sami — Suggestion communication, soumise
insertFiche([
    'auteur_id' => $samiId,
    'visibility' => 'public',
    'type_evenement' => 'suggestion',
    'personnes_concernees_types' => 'collaborateur,visiteur',
    'unite_module_id' => $m1,
    'titre' => 'QR code pour les familles dans les chambres',
    'categorie' => 'communication',
    'criticite' => 'faible',
    'description' => '<p>Ajouter un QR code dans chaque chambre qui renvoie vers une page web "Famille" avec : photos récentes du résident, activités de la semaine, contact direct du médecin référent, menu de la semaine.</p>',
    'suggestion' => '<p>Pourrait être intégré à SpocSpace module Famille. Pas de développement supplémentaire nécessaire — juste générer les QR codes et les plastifier.</p>',
    'date_evenement' => date('Y-m-d', strtotime('-5 days')),
    'statut' => 'en_revue',
    'comments' => [
        ['auteur_id' => $adminId, 'role' => 'admin', 'content' => 'Très bonne idée, je transfère à l\'équipe IT pour évaluation technique.'],
    ],
], $counter);

echo PHP_EOL . "✓ Seed terminé. $counter fiches au total." . PHP_EOL;
