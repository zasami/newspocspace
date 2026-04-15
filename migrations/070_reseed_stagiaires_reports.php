<?php
/**
 * Re-seed des reports pour Léa / Malik / Sofia avec contenu adapté
 * à leur type de formation et niveau.
 * Run: php migrations/070_reseed_stagiaires_reports.php
 */
require_once __DIR__ . '/../init.php';

echo "=== Re-seed reports adaptés par profil ===\n\n";

// Helper: récupère un stagiaire par email
function getStag($email) {
    return Db::fetch(
        "SELECT s.id, s.date_debut, s.formateur_principal_id, u.prenom, u.nom, s.type
         FROM stagiaires s JOIN users u ON u.id = s.user_id
         WHERE u.email = ?",
        [$email]
    );
}

// Helper: cherche une tâche dans le catalogue par code + référentiel
function getTache($ref, $code) {
    return Db::getOne(
        "SELECT id FROM stagiaire_taches_catalogue WHERE referentiel = ? AND code = ? AND is_active = 1",
        [$ref, $code]
    );
}

// Helper: insère un report + lie des tâches
function insertReport($stagId, $formateurId, $daysOffset, $type, $titre, $html, $statut, $commentaire, $refCatalogue, $tacheCodes, $startDate) {
    $date = date('Y-m-d', strtotime("+$daysOffset days", strtotime($startDate)));
    $submitted = $statut !== 'brouillon' ? $date . ' 17:' . rand(10,55) . ':00' : null;
    $validated = $statut === 'valide' ? $date . ' 19:' . rand(10,55) . ':00' : null;
    $validator = $statut === 'valide' ? $formateurId : null;

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO stagiaire_reports (id, stagiaire_id, type, date_report, titre, contenu, statut, submitted_at, validated_by, validated_at, commentaire_formateur)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $stagId, $type, $date, $titre, $html, $statut, $submitted, $validator, $validated, $commentaire]
    );

    foreach ($tacheCodes as $code) {
        $tacheId = getTache($refCatalogue, $code) ?: getTache('commun', $code);
        if (!$tacheId) continue;
        $nbFois = rand(1, 3);
        $niveau = $statut === 'valide' ? (['acquis','acquis','en_cours'][rand(0,2)]) : 'non_evalue';
        Db::exec(
            "INSERT IGNORE INTO stagiaire_report_taches (id, report_id, tache_id, stagiaire_coche, nb_fois, niveau_formateur, evalue_by, evalue_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?)",
            [Uuid::v4(), $id, $tacheId, $nbFois, $niveau, $validator, $validated]
        );
    }
    echo "    + J$daysOffset — $titre ($statut)\n";
    return $id;
}

// Helper: régénère les objectifs d'un stagiaire
function reseedObjectifs($stagId, $ruvId, $objs) {
    Db::exec("DELETE FROM stagiaire_objectifs WHERE stagiaire_id = ?", [$stagId]);
    foreach ($objs as $o) {
        Db::exec(
            "INSERT INTO stagiaire_objectifs (id, stagiaire_id, titre, description, statut, created_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $stagId, $o[0], $o[1], $o[2] ?? 'en_cours', $ruvId]
        );
    }
    echo "    ✓ " . count($objs) . " objectifs\n";
}

// Récupère le RUV
$ruv = Db::getOne("SELECT id FROM users WHERE email LIKE 'michel.berset%' LIMIT 1")
    ?: Db::getOne("SELECT id FROM users WHERE role IN ('admin','direction') LIMIT 1");

// ────────── LÉA DUVAL — CFC ASA 2e année ──────────
$lea = getStag('lea.duval@terrassiere.ch');
if ($lea) {
    echo "▶ {$lea['prenom']} {$lea['nom']} (CFC ASA 2e année)\n";

    // Purge anciens reports + leurs tâches
    $oldIds = Db::fetchAll("SELECT id FROM stagiaire_reports WHERE stagiaire_id = ?", [$lea['id']]);
    foreach ($oldIds as $o) {
        Db::exec("DELETE FROM stagiaire_report_taches WHERE report_id = ?", [$o['id']]);
    }
    Db::exec("DELETE FROM stagiaire_reports WHERE stagiaire_id = ?", [$lea['id']]);

    reseedObjectifs($lea['id'], $ruv, [
        ['Autonomie soins de base', 'Réaliser toilettes, transferts, changes en totale autonomie (sans supervision directe)', 'en_cours'],
        ['Gestion intimité & dignité', 'Respecter systématiquement l\'intimité du résident (frapper, fermer, expliquer)', 'atteint'],
        ['Transmissions orales & écrites', 'Transmettre 3 observations claires par jour en passation et au dossier', 'en_cours'],
        ['Accompagnement fin de vie', 'Participer à au moins 2 accompagnements avec l\'équipe', 'en_cours'],
        ['Relation résidents difficiles', 'Savoir apaiser un résident anxieux ou agité avec les techniques vues en cours', 'en_cours'],
    ]);

    $reports = [
        [-10, 'J1 — Prise de poste au module A', '<p>Accueil par Céline. Présentation de l\'équipe et des 12 résidents du module. J\'ai participé à la distribution du petit-déjeuner et observé les transferts matinaux. Tout me semble bien organisé, je dois juste mémoriser les habitudes de chacun.</p>', 'valide', 'Bonne prise de contact, continue ainsi.', ['HY01','AL01','AN02','CP01']],
        [-9, 'J2 — Toilettes matinales en autonomie', '<p>J\'ai réalisé 4 toilettes au lit et 2 accompagnements à la douche. Gros travail sur la communication pendant les soins — expliquer chaque geste avant de l\'effectuer. Mme R. était très anxieuse ce matin, j\'ai pris le temps de la rassurer.</p><p>Difficulté : gestion du temps, je suis un peu en retard en fin de matinée.</p>', 'valide', 'Très bonne posture professionnelle. Pour le temps, cela vient avec l\'expérience.', ['HY01','SO01','SO03','SO04','OB05']],
        [-7, 'J4 — Transferts et mobilisations', '<p>Formation pratique sur l\'utilisation du lève-personne avec Clara. J\'ai réussi 3 transferts en totale autonomie cet après-midi. Je maîtrise mieux le positionnement au fauteuil.</p><p>Question : quelle est la durée maximale recommandée au fauteuil pour un résident peu mobile ?</p>', 'valide', 'Bonne progression ! Durée max : 2-3h avec changements de position toutes les 30min. On en reparle demain.', ['MO01','MO02','MO04','MO05']],
        [-5, 'J6 — Gestion incontinence et éliminations', '<p>Aujourd\'hui focus sur les changes. J\'ai réalisé 8 changes complets, dont 2 sur résidents alités. J\'ai bien respecté le protocole d\'hygiène et l\'intimité. Une résidente a fait une chute aux WC en fin de matinée — j\'ai prévenu l\'infirmière immédiatement sans déplacer la personne.</p>', 'valide', 'Parfait pour la chute, bon réflexe. Précise dans le dossier soins.', ['HY01','HY02','EL01','EL02','OB02']],
        [-3, 'J8 — Accompagnement fin de vie', '<p>Première confrontation avec la fin de vie. M. D. est entré en phase terminale cette semaine. J\'ai accompagné Céline pour les soins de confort, hydratation buccale, changements de position doux. Moment émotionnellement difficile mais important pour moi.</p>', 'soumis', null, ['FV01','SO05','OB05','SO09']],
        [-2, 'J9 — Observation et transmissions', '<p>J\'ai participé à ma première passation orale ce matin. J\'ai transmis 3 observations sur mes résidents référents. Céline m\'a fait un retour positif sur la clarté de mes transmissions. J\'ai aussi commencé à remplir le dossier informatisé.</p>', 'soumis', null, ['OB02','OB03','OB04','OB05']],
        [-1, 'J10 — Atelier chant après-midi', '<p>Super moment cet après-midi ! J\'ai aidé Louise (animatrice) pour l\'atelier chant. 8 résidents ont participé. Mme B. qui ne parle presque plus a chanté toute la chanson "La vie en rose" — émotion intense.</p>', 'brouillon', null, ['AN01']],
    ];

    foreach ($reports as $r) {
        insertReport($lea['id'], $lea['formateur_principal_id'], $r[0], 'quotidien', $r[1], $r[2], $r[3], $r[4], 'asa_crs', $r[5], $lea['date_debut']);
    }
}

// ────────── MALIK BENALI — Bachelor infirmier 3e année ──────────
$malik = getStag('malik.benali@terrassiere.ch');
if ($malik) {
    echo "\n▶ {$malik['prenom']} {$malik['nom']} (Bachelor inf. 3e année)\n";

    $oldIds = Db::fetchAll("SELECT id FROM stagiaire_reports WHERE stagiaire_id = ?", [$malik['id']]);
    foreach ($oldIds as $o) Db::exec("DELETE FROM stagiaire_report_taches WHERE report_id = ?", [$o['id']]);
    Db::exec("DELETE FROM stagiaire_reports WHERE stagiaire_id = ?", [$malik['id']]);

    reseedObjectifs($malik['id'], $ruv, [
        ['Autonomie technique avancée', 'Réaliser pansements complexes, PVP, prélèvements sanguins sans supervision directe', 'en_cours'],
        ['Conduite d\'un plan de soins', 'Élaborer, mettre en œuvre et ajuster un plan de soins individualisé pour 3 résidents référents', 'en_cours'],
        ['Évaluation clinique & raisonnement', 'Construire un raisonnement clinique SBAR pour toute situation aiguë', 'atteint'],
        ['Délégation & encadrement', 'Encadrer un·e stagiaire ASA sur les soins de base en toute sécurité', 'en_cours'],
        ['Gestion des médicaments à haut risque', 'Maîtriser double-contrôle, stupéfiants, titration morphine', 'en_cours'],
        ['Soins palliatifs', 'Accompagner activement 2 résidents en phase palliative avec l\'équipe interdisciplinaire', 'en_cours'],
    ]);

    $reports = [
        [-10, 'Semaine 1 — Intégration équipe soins', '<p>Prise de poste en 3e année bachelor. J\'ai passé la semaine à observer l\'organisation du module, revoir les protocoles de soins infirmiers et m\'approprier le dossier informatisé. Céline m\'a attribué 4 résidents référents dont 2 avec plaies chroniques complexes.</p><h3>Objectifs personnels</h3><ul><li>Autonomie complète sur les pansements complexes</li><li>Pose de voie veineuse périphérique sans supervision directe</li><li>Conduite d\'un plan de soins</li></ul>', 'valide', 'Excellent cadrage. On commence la PVP dès demain avec moi.', ['IT01','EC01','DS01','OB03']],
        [-8, 'J3 — Administration médicamenteuse', '<p>Distribution matinale sous supervision. 14 résidents, 3 classes de médicaments psychotropes nécessitant double contrôle. J\'ai détecté une erreur de dosage sur M. K. (0.5mg Tavor au lieu de 0.25mg) que j\'ai signalée à l\'infirmière-chef. Vérification : erreur de retranscription au semainier hebdomadaire.</p>', 'valide', 'Bravo pour la vigilance ! C\'est exactement l\'attitude attendue en 3e année.', ['IT01','MD01','MD02','OB02']],
        [-6, 'J5 — Pose de voie veineuse périphérique', '<p>Première PVP en autonomie sur M. L. (veines difficiles, traitement antibiotique IV 7j). Réussite au premier essai, préparation matériel OK, technique aseptique respectée. Surveillance du point de ponction et retour veineux correcte.</p><p>Point à améliorer : ma gestion du stress avant la ponction — je respire mieux.</p>', 'valide', 'Parfait. Tu peux maintenant prendre les nouvelles PVP en autonomie.', ['IT01','IT02','ST05','HY01','HY02']],
        [-5, 'J6 — Pansement complexe escarre sacrée', '<p>Réfection du pansement de Mme F. (escarre stade 3 sacrée). J\'ai évalué la plaie : surface stable, tissu fibrineux diminué depuis dernière semaine. Application alginate + pansement hydrocolloïde. Photos dans le dossier pour le suivi.</p><p>J\'ai revu le plan de soins avec la RUV — espacement pansement à J+3 au lieu de J+2.</p>', 'valide', 'Excellente analyse de l\'évolution, bon ajustement du plan.', ['IT04','EC05','DS02','MD01']],
        [-3, 'J8 — Évaluation clinique M. R. (confusion aiguë)', '<p>M. R. présente ce matin une confusion aiguë avec désorientation temporo-spatiale. Constantes : T° 37.9°, TA 110/70, FC 98, SpO2 94%. Bandelette urinaire positive (leucos, nitrites). Hypothèse d\'infection urinaire avec déshydratation.</p><p>Transmission SBAR au médecin de garde, prescription ECBU + BU + hydratation. Je suis en suivi toutes les 2h.</p>', 'soumis', null, ['EC01','EC02','EC03','EC04','UR03']],
        [-2, 'J9 — Accompagnement fin de vie et soins palliatifs', '<p>M. D. est entré en phase palliative terminale. J\'ai adapté le plan de soins : arrêt des prises de constantes invasives, focus confort, hydratation bouche, soins de positionnement, morphine SC en continu. Entretien avec la famille cet après-midi pour expliquer l\'évolution.</p>', 'soumis', null, ['FV01','IT06','MD01','OB05','RE03']],
        [-1, 'J10 — Encadrement aide-soignante', '<p>J\'ai encadré Léa (stagiaire ASA 2e année) sur les pansements simples ce matin. Bonne pédagogie, j\'ai démontré puis laissé faire avec supervision. Elle a bien progressé sur la technique aseptique.</p><p>Réflexion : l\'encadrement est formateur pour moi aussi.</p>', 'brouillon', null, ['IT03','RE02']],
    ];

    foreach ($reports as $r) {
        insertReport($malik['id'], $malik['formateur_principal_id'], $r[0], 'hebdo', $r[1], $r[2], $r[3], $r[4], 'infirmiere', $r[5], $malik['date_debut']);
    }
}

// ────────── SOFIA MARQUES — Découverte (Maturité) ──────────
$sofia = getStag('sofia.marques@terrassiere.ch');
if ($sofia) {
    echo "\n▶ {$sofia['prenom']} {$sofia['nom']} (Stage de découverte — maturité)\n";

    $oldIds = Db::fetchAll("SELECT id FROM stagiaire_reports WHERE stagiaire_id = ?", [$sofia['id']]);
    foreach ($oldIds as $o) Db::exec("DELETE FROM stagiaire_report_taches WHERE report_id = ?", [$o['id']]);
    Db::exec("DELETE FROM stagiaire_reports WHERE stagiaire_id = ?", [$sofia['id']]);

    reseedObjectifs($sofia['id'], $ruv, [
        ['Découvrir le monde de l\'EMS', 'Observer une journée-type et comprendre l\'organisation (équipes, horaires, interdisciplinarité)', 'atteint'],
        ['Communiquer avec un résident âgé', 'Initier une conversation, pratiquer l\'écoute active, respecter les troubles cognitifs', 'en_cours'],
        ['Identifier les métiers du soin', 'Rencontrer au moins 4 professionnel·les différent·es (ASA, IDE, ASE, physio, médecin)', 'en_cours'],
        ['Construire son projet d\'orientation', 'Rédiger un bilan personnel à la fin du stage sur le choix de filière', 'en_cours'],
    ]);

    $reports = [
        [-8, 'Jour 1 — Première journée', '<p>Je n\'avais jamais mis les pieds dans un EMS. Beaucoup d\'émotions. Céline m\'a montré les lieux, présentée aux résidents. J\'ai juste observé ce matin, puis aidé à installer les résidents pour le repas. Certains sont très touchants, d\'autres font peur au premier abord.</p><p>J\'ai hâte de mieux les connaître.</p>', 'valide', 'Merci pour ton honnêteté. Les émotions sont normales, fais-toi confiance.', ['OB01','CP02','CP04']],
        [-7, 'Jour 2 — Les repas', '<p>Distribution des repas à midi avec Céline. J\'ai appris comment servir, couper la viande de ceux qui ont des tremblements, proposer à boire régulièrement. Une résidente (Mme D.) m\'a demandé si j\'étais sa fille — j\'ai été un peu bouleversée mais Céline m\'a expliqué comment répondre sans la contredire.</p>', 'valide', 'Beau moment, tu gères bien. Pour la suite : "entrer dans la réalité" du résident.', ['AL01','AL02','OB01','CP03']],
        [-6, 'Jour 3 — Aide au lever et toilettes (observation)', '<p>Matinée intense. J\'ai assisté (juste regardé) aux toilettes et aux transferts. Le respect de l\'intimité m\'a beaucoup marquée — les portes fermées, les explications, les gestes doux. Je n\'ai pas réalisé de soins, Céline m\'a dit que ce n\'était pas mon rôle en stage découverte.</p>', 'valide', 'Très bonne observation. L\'intimité est au cœur du métier.', ['OB01','CP03','CP04']],
        [-5, 'Jour 4 — Atelier animation', '<p>J\'ai participé à un atelier mémoire avec 6 résidents. J\'ai aidé à lire les questions, noter les réponses. Impressionnée par la mémoire de Mme G. qui a récité tout un poème de Prévert. Après-midi : accompagnement pour une promenade au jardin.</p>', 'valide', 'Tu as trouvé ta place dans l\'animation !', ['AC02']],
        [-3, 'Jour 6 — Discussion avec une résidente', '<p>J\'ai passé 45 minutes avec Mme H. dans sa chambre. Elle m\'a raconté son métier (sage-femme avant la retraite) et a pleuré en parlant de son mari décédé. Je n\'ai pas su quoi dire, j\'ai juste écouté et tenu sa main. Céline m\'a dit que c\'était exactement ce qu\'il fallait faire.</p><p>Je crois que je veux aller dans le domaine du soin après la maturité.</p>', 'soumis', null, ['RE01','OB01']],
        [-2, 'Jour 7 — Ce que ce stage m\'apporte', '<p>À mi-parcours, je commence à comprendre ce qu\'est vraiment le métier. Ce n\'est pas juste "s\'occuper de personnes âgées", c\'est accompagner des vies entières avec dignité. J\'ai aussi vu que ce n\'est pas toujours facile — il y a des moments durs, des familles difficiles, des résidents qui souffrent.</p><p>Je me sens plus mature, même si je ne sais pas encore exactement quelle filière choisir.</p>', 'brouillon', null, ['OB01','RE01','CP02']],
    ];

    foreach ($reports as $r) {
        insertReport($sofia['id'], $sofia['formateur_principal_id'], $r[0], 'quotidien', $r[1], $r[2], $r[3], $r[4], 'decouverte', $r[5], $sofia['date_debut']);
    }
}

echo "\n═══ Fait ═══\n";

// Stats récap
$rows = Db::fetchAll(
    "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, COUNT(r.id) AS n_reports,
            SUM(r.statut = 'valide') AS valides,
            SUM(r.statut = 'soumis') AS soumis,
            SUM(r.statut = 'brouillon') AS brouillons
     FROM stagiaires s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN stagiaire_reports r ON r.stagiaire_id = s.id
     WHERE u.email IN ('lea.duval@terrassiere.ch','malik.benali@terrassiere.ch','sofia.marques@terrassiere.ch')
     GROUP BY s.id, u.prenom, u.nom"
);
foreach ($rows as $r) {
    echo "  {$r['nom']} — {$r['n_reports']} reports ({$r['valides']} validés, {$r['soumis']} soumis, {$r['brouillons']} brouillon)\n";
}
