<?php
/**
 * Extend catalogue with INF (infirmière) + ASE (animation) + extended ASA
 * Run: php migrations/068_seed_taches_inf_ase.php
 */
require_once __DIR__ . '/../init.php';

// Enum referentiel currently: asa_crs, ase, asfm, bachelor_inf, decouverte, civiliste, commun
// We need to add: infirmiere (INF) + animation (via ase already exists, extend)
// Check ENUM
$enumCheck = Db::getOne("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stagiaire_taches_catalogue' AND COLUMN_NAME = 'referentiel'");
echo "Enum actuel: $enumCheck\n";

if (strpos($enumCheck, 'infirmiere') === false) {
    echo "Extension ENUM pour inclure 'infirmiere'...\n";
    Db::exec("ALTER TABLE stagiaire_taches_catalogue
        MODIFY COLUMN referentiel ENUM('asa_crs','ase','asfm','bachelor_inf','decouverte','civiliste','commun','infirmiere')
        NOT NULL DEFAULT 'commun'");
}

$catalogue = [
    // ─── INF — Infirmier·ère ───
    ['infirmiere', 'Soins techniques', 'IT01', 'Administration médicaments', 'Préparation + distribution PO/SC/IM'],
    ['infirmiere', 'Soins techniques', 'IT02', 'Pose de perfusion', 'Voie périphérique + surveillance'],
    ['infirmiere', 'Soins techniques', 'IT03', 'Pansement simple', 'Plaie propre, suture'],
    ['infirmiere', 'Soins techniques', 'IT04', 'Pansement complexe', 'Escarre, plaie chronique, stomie'],
    ['infirmiere', 'Soins techniques', 'IT05', 'Prélèvement sanguin', 'Ponction veineuse'],
    ['infirmiere', 'Soins techniques', 'IT06', 'Injection sous-cutanée', 'Insuline, HBPM'],
    ['infirmiere', 'Soins techniques', 'IT07', 'Injection intramusculaire', 'Vaccins, antalgiques'],
    ['infirmiere', 'Soins techniques', 'IT08', 'Pose de sonde urinaire', 'Hommes / femmes'],
    ['infirmiere', 'Soins techniques', 'IT09', 'Aspiration bronchique', 'Technique aseptique'],
    ['infirmiere', 'Soins techniques', 'IT10', 'Soins de trachéotomie', 'Nettoyage canule'],
    ['infirmiere', 'Soins techniques', 'IT11', 'Oxygénothérapie', 'Lunettes, masque, surveillance SpO2'],
    ['infirmiere', 'Évaluation clinique', 'EC01', 'Prise de constantes complète', 'T°, TA, FC, FR, SpO2, glycémie'],
    ['infirmiere', 'Évaluation clinique', 'EC02', 'Évaluation de la douleur', 'Échelles EVA, Algoplus, Doloplus'],
    ['infirmiere', 'Évaluation clinique', 'EC03', 'Évaluation de l\'état cognitif', 'MMS, confusion aiguë'],
    ['infirmiere', 'Évaluation clinique', 'EC04', 'Surveillance post-chute', 'Neuro, mobilité, douleur'],
    ['infirmiere', 'Évaluation clinique', 'EC05', 'Dépistage escarres / risque', 'Échelle de Braden'],
    ['infirmiere', 'Démarche de soins', 'DS01', 'Recueil de données entrée', 'Anamnèse complète résident'],
    ['infirmiere', 'Démarche de soins', 'DS02', 'Plan de soins individualisé', 'Diagnostics + objectifs + interventions'],
    ['infirmiere', 'Démarche de soins', 'DS03', 'Évaluation des interventions', 'Ajustement du plan'],
    ['infirmiere', 'Démarche de soins', 'DS04', 'Dossier de soins informatisé', 'Saisie complète'],
    ['infirmiere', 'Médicaments & pharma', 'MD01', 'Semainier / pilulier', 'Préparation hebdomadaire'],
    ['infirmiere', 'Médicaments & pharma', 'MD02', 'Gestion des stupéfiants', 'Comptage + armoire fermée'],
    ['infirmiere', 'Médicaments & pharma', 'MD03', 'Commande pharmacie', 'Gestion des stocks'],
    ['infirmiere', 'Médicaments & pharma', 'MD04', 'Réception + contrôle commande', 'Vérification dates/quantités'],
    ['infirmiere', 'Urgence & sécurité', 'UR01', 'Réanimation cardio-pulmonaire', 'BLS/AED'],
    ['infirmiere', 'Urgence & sécurité', 'UR02', 'Prise en charge malaise', 'Hypoglycémie, hypotension'],
    ['infirmiere', 'Urgence & sécurité', 'UR03', 'Appel médecin / 144', 'Transmission SBAR'],
    ['infirmiere', 'Relation & équipe', 'RE01', 'Transmissions ciblées', 'SOAP / DAR / SBAR'],
    ['infirmiere', 'Relation & équipe', 'RE02', 'Encadrement stagiaire/aide-soignant', 'Délégation sécurisée'],
    ['infirmiere', 'Relation & équipe', 'RE03', 'Accompagnement famille', 'Annonce + soutien'],
    ['infirmiere', 'Fin de vie', 'FV01', 'Soins palliatifs', 'Confort, douleur, dignité'],
    ['infirmiere', 'Fin de vie', 'FV02', 'Certificat de décès', 'Constat + déclaration'],

    // ─── ASE — extension animation ───
    ['ase', 'Animation — Conception', 'AN01', 'Conception d\'atelier', 'Objectifs, public, matériel'],
    ['ase', 'Animation — Conception', 'AN02', 'Préparation matériel', 'Logistique amont'],
    ['ase', 'Animation — Conception', 'AN03', 'Adaptation aux capacités', 'Déficits cognitifs, moteurs, sensoriels'],
    ['ase', 'Animation — Conduite', 'AN04', 'Animation atelier manuel', 'Peinture, collage, bricolage'],
    ['ase', 'Animation — Conduite', 'AN05', 'Animation atelier cognitif', 'Jeux mémoire, mots, quiz'],
    ['ase', 'Animation — Conduite', 'AN06', 'Animation atelier chant', 'Karaoké, chants anciens'],
    ['ase', 'Animation — Conduite', 'AN07', 'Atelier cuisine', 'Pâtisserie simple, dégustation'],
    ['ase', 'Animation — Conduite', 'AN08', 'Atelier jardinage', 'Potager, plantations'],
    ['ase', 'Animation — Conduite', 'AN09', 'Atelier gym douce', 'Mobilité assise, équilibre'],
    ['ase', 'Animation — Conduite', 'AN10', 'Lecture à voix haute', 'Journal, histoire courte'],
    ['ase', 'Sorties & évènements', 'SO01', 'Sortie extérieure accompagnée', 'Promenade, marché, parc'],
    ['ase', 'Sorties & évènements', 'SO02', 'Organisation fête', 'Anniversaire, fête saisonnière'],
    ['ase', 'Sorties & évènements', 'SO03', 'Accueil intervenant externe', 'Musicien, thérapeute animalier'],
    ['ase', 'Projet personnalisé', 'PP01', 'Projet de vie individualisé', 'Recueil attentes, histoire de vie'],
    ['ase', 'Projet personnalisé', 'PP02', 'Entretien individuel', 'Écoute active, relation d\'aide'],
    ['ase', 'Observation', 'OB01', 'Observation comportement', 'Signaler troubles, progrès'],
    ['ase', 'Observation', 'OB02', 'Transmission écrite', 'Dossier résident'],

    // ─── ASA extensions pour Croix-Rouge ───
    ['asa_crs', 'Prévention', 'PR01', 'Prévention des chutes', 'Aménagement chambre, surveillance'],
    ['asa_crs', 'Prévention', 'PR02', 'Prévention infections', 'Hygiène des mains, isolement'],
    ['asa_crs', 'Soins de base', 'SO07', 'Soins des yeux', 'Nettoyage, instillation gouttes simples'],
    ['asa_crs', 'Soins de base', 'SO08', 'Soins des ongles', 'Coupe sécurisée (hors diabétiques)'],
    ['asa_crs', 'Soins de base', 'SO09', 'Rasage / coiffage', 'Hygiène et présentation'],

    // ─── APP (commun) — apprenti ───
    ['commun', 'Apprentissage', 'AP01', 'Posture professionnelle', 'Attitude adaptée'],
    ['commun', 'Apprentissage', 'AP02', 'Questionnement pertinent', 'Demander quand on ne sait pas'],
    ['commun', 'Apprentissage', 'AP03', 'Autoévaluation', 'Identifier ses réussites/difficultés'],
];

echo "=== Seed catalogue étendu ===\n\n";
$inserted = 0; $skipped = 0;
foreach ($catalogue as $i => $row) {
    [$ref, $cat, $code, $nom, $desc] = $row;
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

// Stats par référentiel
$rows = Db::fetchAll("SELECT referentiel, COUNT(*) AS n FROM stagiaire_taches_catalogue WHERE is_active = 1 GROUP BY referentiel ORDER BY referentiel");
echo "\n=== Catalogue actuel ===\n";
foreach ($rows as $r) echo "  {$r['referentiel']} : {$r['n']} tâches\n";
echo "Total : " . Db::getOne("SELECT COUNT(*) FROM stagiaire_taches_catalogue") . "\n";
