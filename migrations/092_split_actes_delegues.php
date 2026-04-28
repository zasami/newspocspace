<?php
/**
 * Split ACTES_DELEGUES en 2 thématiques distinctes (Cas 3 FEGEMS).
 *
 * Le contenu / périmètre légal des actes autorisés diffère selon le diplôme :
 *   - ACTES_DELEGUES_INF  → Infirmier·ère·s + ASSC (actes étendus)
 *   - ACTES_DELEGUES_ASA  → ASE / ASA / aides-soignant·es / auxiliaires
 *
 * Idempotent : crée les 2 nouvelles thématiques si absentes,
 * migre les liens existants depuis ACTES_DELEGUES.
 *
 * Lancer : php migrations/092_split_actes_delegues.php
 */
require_once __DIR__ . '/../init.php';
if (!function_exists('nonce')) { function nonce(): string { return ''; } }

echo "→ Split ACTES_DELEGUES en 2 thématiques (Inf/ASSC vs ASA/ASE)\n";

// 1. Récupérer l'ancien ACTES_DELEGUES
$ancien = Db::fetch("SELECT id FROM competences_thematiques WHERE code = 'ACTES_DELEGUES'");
if (!$ancien) {
    echo "  ℹ ACTES_DELEGUES n'existe pas — rien à splitter.\n";
    exit(0);
}
$ancienId = $ancien['id'];

// 2. Créer les 2 nouvelles thématiques (idempotent)
$nouvelles = [
    'ACTES_DELEGUES_INF' => [
        'nom' => 'Actes délégués · Infirmier·ère·s / ASSC',
        'tag' => 'Validé OCS · session Inf',
        'icone' => 'check2-square',
        'couleur' => 'sand',
        'duree_validite_mois' => 24,
        'ordre' => 19,
    ],
    'ACTES_DELEGUES_ASA' => [
        'nom' => 'Actes délégués · ASE / ASA / aides-soignant·es',
        'tag' => 'Validé OCS · session ASA',
        'icone' => 'check2-square',
        'couleur' => 'sand',
        'duree_validite_mois' => 24,
        'ordre' => 20,
    ],
];

$nouveauxIds = [];
foreach ($nouvelles as $code => $meta) {
    $existe = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
    if ($existe) {
        $nouveauxIds[$code] = $existe['id'];
        echo "  ✓ $code existe déjà (id={$existe['id']})\n";
        continue;
    }
    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO competences_thematiques
         (id, code, nom, categorie, tag_affichage, icone, couleur, duree_validite_mois, ordre, actif)
         VALUES (?, ?, ?, 'fegems_base', ?, ?, ?, ?, ?, 1)",
        [$id, $code, $meta['nom'], $meta['tag'], $meta['icone'], $meta['couleur'], $meta['duree_validite_mois'], $meta['ordre']]
    );
    $nouveauxIds[$code] = $id;
    echo "  + Créée : $code (id=$id)\n";
}

// 3. Migrer les lignes profil_attendu : ACTES_DELEGUES → ACTES_DELEGUES_INF (par défaut)
//    L'INF couvre déjà ASSC, c'est plus prudent que d'écraser.
$nbProfil = (int) Db::getOne("SELECT COUNT(*) FROM competences_profil_attendu WHERE thematique_id = ?", [$ancienId]);
if ($nbProfil) {
    Db::exec(
        "UPDATE competences_profil_attendu SET thematique_id = ? WHERE thematique_id = ?",
        [$nouveauxIds['ACTES_DELEGUES_INF'], $ancienId]
    );
    echo "  → $nbProfil ligne(s) profil_attendu migrées vers ACTES_DELEGUES_INF\n";

    // Dupliquer pour ACTES_DELEGUES_ASA
    $rows = Db::fetchAll(
        "SELECT secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande, objectif_strategique
         FROM competences_profil_attendu WHERE thematique_id = ?",
        [$nouveauxIds['ACTES_DELEGUES_INF']]
    );
    foreach ($rows as $r) {
        Db::exec(
            "INSERT INTO competences_profil_attendu
             (id, thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande, objectif_strategique)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $nouveauxIds['ACTES_DELEGUES_ASA'], $r['secteur'], $r['requis'],
             $r['niveau_requis'], $r['part_a_former_pct'], $r['type_formation_recommande'],
             'Actes délégués session ASE/ASA — périmètre adapté']
        );
    }
    echo "  → " . count($rows) . " ligne(s) dupliquées pour ACTES_DELEGUES_ASA\n";
}

// 4. Migrer competences_user : par défaut → ACTES_DELEGUES_INF
//    (un correctif manuel sera nécessaire pour les ASA — voir alerte plus bas)
$nbUserComp = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE thematique_id = ?", [$ancienId]);
if ($nbUserComp) {
    Db::exec(
        "UPDATE competences_user SET thematique_id = ? WHERE thematique_id = ?",
        [$nouveauxIds['ACTES_DELEGUES_INF'], $ancienId]
    );
    echo "  → $nbUserComp ligne(s) competences_user migrées vers ACTES_DELEGUES_INF\n";
}

// 5. Migrer formation_thematiques (liens formations → thématiques)
$nbFormThem = (int) Db::getOne("SELECT COUNT(*) FROM formation_thematiques WHERE thematique_id = ?", [$ancienId]);
if ($nbFormThem) {
    Db::exec(
        "UPDATE formation_thematiques SET thematique_id = ? WHERE thematique_id = ?",
        [$nouveauxIds['ACTES_DELEGUES_INF'], $ancienId]
    );
    echo "  → $nbFormThem lien(s) formation_thematiques migrés\n";
}

// 6. Désactiver l'ancienne thématique (au lieu de DELETE pour préserver l'historique)
Db::exec("UPDATE competences_thematiques SET actif = 0 WHERE id = ?", [$ancienId]);
echo "  → Ancienne ACTES_DELEGUES désactivée (actif=0)\n";

echo "\n⚠ Note : les lignes competences_user ont toutes été migrées vers ACTES_DELEGUES_INF.\n";
echo "  Pour les collaborateurs ASE/ASA, un admin devra ré-affecter leur ligne vers ACTES_DELEGUES_ASA\n";
echo "  (depuis la fiche compétences). Ou alternativement relancer 088 puis 091.\n";

echo "\n✓ Migration 092 terminée.\n";
