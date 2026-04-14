<?php
/**
 * Seed catalogue tâches stagiaires
 * Run: php migrations/066_seed_taches_catalogue.php
 */
require_once __DIR__ . '/../init.php';

$catalogue = [
    // ─── ASA Croix-Rouge ───
    ['asa_crs', 'Hygiène & sécurité', 'HY01', 'Désinfection des mains', 'Hygiène des mains selon protocole (friction hydro-alcoolique)'],
    ['asa_crs', 'Hygiène & sécurité', 'HY02', 'Port des EPI', 'Gants, tablier, masque selon situation'],
    ['asa_crs', 'Hygiène & sécurité', 'HY03', 'Désinfection d\'un lit', 'Nettoyage et désinfection d\'un lit entre deux résidents'],
    ['asa_crs', 'Hygiène & sécurité', 'HY04', 'Gestion du linge sale', 'Tri et élimination du linge contaminé'],
    ['asa_crs', 'Soins de base', 'SO01', 'Toilette au lit', 'Aide à la toilette complète d\'un résident alité'],
    ['asa_crs', 'Soins de base', 'SO02', 'Toilette au lavabo', 'Accompagnement toilette partielle debout/assis'],
    ['asa_crs', 'Soins de base', 'SO03', 'Douche accompagnée', 'Aide à la douche avec respect de l\'intimité'],
    ['asa_crs', 'Soins de base', 'SO04', 'Habillage / déshabillage', 'Aide à l\'habillage adapté'],
    ['asa_crs', 'Soins de base', 'SO05', 'Soins de bouche', 'Soins d\'hygiène bucco-dentaire'],
    ['asa_crs', 'Soins de base', 'SO06', 'Réfection d\'un lit', 'Lit occupé ou inoccupé'],
    ['asa_crs', 'Mobilité & transferts', 'MO01', 'Transfert lit → fauteuil', 'Transfert manuel ou aidé (disque/ceinture)'],
    ['asa_crs', 'Mobilité & transferts', 'MO02', 'Transfert avec lève-personne', 'Utilisation d\'un verticalisateur ou lève-personne'],
    ['asa_crs', 'Mobilité & transferts', 'MO03', 'Accompagnement à la marche', 'Avec ou sans moyen auxiliaire'],
    ['asa_crs', 'Mobilité & transferts', 'MO04', 'Installation au fauteuil', 'Positionnement confortable et sécurisé'],
    ['asa_crs', 'Mobilité & transferts', 'MO05', 'Prévention des escarres', 'Changements de position réguliers'],
    ['asa_crs', 'Alimentation', 'AL01', 'Distribution des repas', 'Service en salle à manger ou en chambre'],
    ['asa_crs', 'Alimentation', 'AL02', 'Aide à l\'alimentation', 'Faire manger un résident dépendant'],
    ['asa_crs', 'Alimentation', 'AL03', 'Hydratation', 'Proposer et faire boire un résident'],
    ['asa_crs', 'Alimentation', 'AL04', 'Observation des repas', 'Surveillance prise alimentaire et signalement'],
    ['asa_crs', 'Élimination', 'EL01', 'Accompagnement aux toilettes', 'Aide à la mobilisation vers WC'],
    ['asa_crs', 'Élimination', 'EL02', 'Change de protection', 'Change complet d\'un résident incontinent'],
    ['asa_crs', 'Élimination', 'EL03', 'Pose / retrait d\'un urinal ou bassin', 'Utilisation correcte'],
    ['asa_crs', 'Observation & communication', 'OB01', 'Prise de constantes', 'Température, pouls, tension sous supervision'],
    ['asa_crs', 'Observation & communication', 'OB02', 'Observation générale', 'Signaler changement d\'état, douleur, comportement'],
    ['asa_crs', 'Observation & communication', 'OB03', 'Transmissions orales', 'Participer aux passations'],
    ['asa_crs', 'Observation & communication', 'OB04', 'Transmissions écrites', 'Noter dans le dossier résident'],
    ['asa_crs', 'Observation & communication', 'OB05', 'Communication avec résident', 'Relation bienveillante, écoute active'],
    ['asa_crs', 'Observation & communication', 'OB06', 'Communication avec famille', 'Accueil et orientation'],
    ['asa_crs', 'Animation & vie sociale', 'AN01', 'Participation animation', 'Accompagnement à un atelier'],
    ['asa_crs', 'Animation & vie sociale', 'AN02', 'Promenade accompagnée', 'Sortie extérieure sécurisée'],
    ['asa_crs', 'Fin de vie', 'FV01', 'Accompagnement de fin de vie', 'Présence, confort, dignité'],
    ['asa_crs', 'Fin de vie', 'FV02', 'Soins post-mortem', 'Toilette mortuaire dans le respect'],

    // ─── ASE (Assistant socio-éducatif) ───
    ['ase', 'Accompagnement', 'AC01', 'Accompagnement activité quotidienne', 'Soutien dans les AVQ'],
    ['ase', 'Accompagnement', 'AC02', 'Écoute active', 'Entretien individuel avec résident'],
    ['ase', 'Animation', 'AN01', 'Conception atelier', 'Préparer une activité adaptée'],
    ['ase', 'Animation', 'AN02', 'Animation de groupe', 'Conduire une activité collective'],
    ['ase', 'Relation', 'RE01', 'Gestion conflit mineur', 'Médiation entre résidents'],

    // ─── Bachelor infirmier ───
    ['bachelor_inf', 'Soins techniques', 'ST01', 'Administration médicaments oraux', 'Sous supervision'],
    ['bachelor_inf', 'Soins techniques', 'ST02', 'Pose de perfusion', 'Avec validation formateur'],
    ['bachelor_inf', 'Soins techniques', 'ST03', 'Pansement simple', 'Réfection pansement propre'],
    ['bachelor_inf', 'Soins techniques', 'ST04', 'Pansement complexe', 'Plaie chronique, escarre'],
    ['bachelor_inf', 'Soins techniques', 'ST05', 'Prélèvement sanguin', 'Ponction veineuse'],
    ['bachelor_inf', 'Soins techniques', 'ST06', 'Injection sous-cutanée', 'Insuline, HBPM'],
    ['bachelor_inf', 'Évaluation clinique', 'EC01', 'Examen clinique initial', 'Recueil données résident'],
    ['bachelor_inf', 'Évaluation clinique', 'EC02', 'Évaluation de la douleur', 'Utilisation échelles'],
    ['bachelor_inf', 'Démarche de soins', 'DS01', 'Plan de soins', 'Élaboration d\'un plan individualisé'],
    ['bachelor_inf', 'Démarche de soins', 'DS02', 'Évaluation des interventions', 'Ajustement des soins'],

    // ─── Découverte (court terme, tâches simples) ───
    ['decouverte', 'Observation', 'OB01', 'Journée d\'observation', 'Suivre un·e professionnel·le'],
    ['decouverte', 'Relation', 'RE01', 'Discuter avec un résident', 'Échange simple'],
    ['decouverte', 'Activités', 'AC01', 'Aide distribution repas', 'Sous supervision'],
    ['decouverte', 'Activités', 'AC02', 'Participation animation', 'Accompagner atelier'],

    // ─── Commun (toutes filières) ───
    ['commun', 'Comportement pro.', 'CP01', 'Ponctualité', 'Arrivée à l\'heure'],
    ['commun', 'Comportement pro.', 'CP02', 'Tenue professionnelle', 'Uniforme propre, cheveux attachés, pas de bijoux'],
    ['commun', 'Comportement pro.', 'CP03', 'Secret professionnel', 'Respect confidentialité'],
    ['commun', 'Comportement pro.', 'CP04', 'Travail en équipe', 'Collaboration avec collègues'],
];

echo "=== Seed catalogue tâches stagiaires ===\n\n";
$inserted = 0;
$skipped = 0;

foreach ($catalogue as $i => $row) {
    [$ref, $cat, $code, $nom, $desc] = $row;

    // Éviter doublons par (referentiel, code)
    $exists = Db::getOne(
        "SELECT id FROM stagiaire_taches_catalogue WHERE referentiel = ? AND code = ?",
        [$ref, $code]
    );
    if ($exists) { $skipped++; continue; }

    Db::exec(
        "INSERT INTO stagiaire_taches_catalogue (id, referentiel, categorie, code, nom, description, ordre)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [Uuid::v4(), $ref, $cat, $code, $nom, $desc, ($i + 1) * 10]
    );
    $inserted++;
}

echo "Inséré : $inserted\n";
echo "Ignoré (déjà existant) : $skipped\n";
echo "Total catalogue : " . Db::getOne("SELECT COUNT(*) FROM stagiaire_taches_catalogue") . "\n";
