<?php
/**
 * Correction de la matrice profil_attendu pour conformité Excel FEGEMS.
 *
 * Source : référentiel officiel FEGEMS (cas 1 = transversal, cas 2 = sectoriel).
 * Idempotent : on RESET d'abord toutes les lignes 'fegems_base' puis on réinsère.
 *
 * Niveaux échelle FEGEMS :
 *   1 = Connaît / débute
 *   2 = Applique avec supervision
 *   3 = Autonome
 *   4 = Référent / transmet
 *
 * Lancer : php migrations/091_fix_profil_attendu_fegems.php
 */
require_once __DIR__ . '/../init.php';

// Stub si lancé en CLI hors index admin
if (!function_exists('nonce')) {
    function nonce(): string { return ''; }
}

echo "→ Correction matrice profil_attendu (conformité FEGEMS)\n";

// 1. Récupérer les IDs des thématiques par code
$thematiques = [];
foreach (Db::fetchAll("SELECT id, code FROM competences_thematiques WHERE categorie = 'fegems_base'") as $t) {
    $thematiques[$t['code']] = $t['id'];
}
echo "  Thématiques fegems_base trouvées : " . count($thematiques) . "\n";

// 2. Reset des lignes existantes pour ces thématiques (on régénère proprement)
$inClause = implode(',', array_fill(0, count($thematiques), '?'));
$ids = array_values($thematiques);
Db::exec("DELETE FROM competences_profil_attendu WHERE thematique_id IN ($inClause)", $ids);
echo "  Lignes existantes purgées.\n";

// 3. Définition de la matrice conforme à l'Excel FEGEMS
//    Format : [code => [secteur => [niveau, pct, type, objectif]]]
$matrice = [

    // ── CAS 1 : TRANSVERSAL (tous secteurs) ────────────────────────────────
    'HPCI_BASE' => [
        'soins'          => [4, 100, 'continue_catalogue', 'Référent HPCI dans chaque équipe'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Tous formés HPCI base'],
        'hotellerie'     => [2, 100, 'continue_catalogue', 'Sensibilisation hygiène hôtelière'],
        'maintenance'    => [2, 100, 'continue_catalogue', 'Sensibilisation HPCI'],
        'administration' => [1, 100, 'continue_catalogue', 'Sensibilisation HPCI'],
        'management'     => [2, 100, 'continue_catalogue', 'Pilotage HPCI'],
    ],
    'HPCI_PRECAUTIONS' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Précautions standards maîtrisées'],
        'socio_culturel' => [1, 100, 'continue_catalogue', 'Sensibilisation'],
        'hotellerie'     => [1, 100, 'continue_catalogue', 'Sensibilisation'],
        'maintenance'    => [1, 100, 'continue_catalogue', 'Sensibilisation'],
        'administration' => [1, 100, 'continue_catalogue', 'Sensibilisation'],
        'management'     => [1, 100, 'continue_catalogue', 'Pilotage'],
    ],
    'BLS_AED' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Recyclage 24 mois'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Recyclage 24 mois'],
        'hotellerie'     => [2, 100, 'continue_catalogue', 'Recyclage 24 mois'],
        'maintenance'    => [2, 100, 'continue_catalogue', 'Recyclage 24 mois'],
        'administration' => [2, 100, 'continue_catalogue', 'Recyclage 24 mois'],
        'management'     => [2, 100, 'continue_catalogue', 'Recyclage 24 mois'],
    ],
    'INC' => [
        'soins'          => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
        'socio_culturel' => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
        'hotellerie'     => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
        'maintenance'    => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
        'administration' => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
        'management'     => [2, 100, 'interne', 'Intégration nouveaux collaborateurs'],
    ],
    'BPSD' => [
        // Tous croisent les résidents déments — formation transversale 100%
        'soins'          => [3, 100, 'continue_catalogue', 'Maîtrise BPSD'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Sensibilisation BPSD'],
        'hotellerie'     => [2, 100, 'continue_catalogue', 'Sensibilisation BPSD'],
        'maintenance'    => [1, 100, 'continue_catalogue', 'Sensibilisation BPSD'],
        'administration' => [1, 100, 'continue_catalogue', 'Sensibilisation BPSD'],
        'management'     => [2, 100, 'continue_catalogue', 'Pilotage BPSD'],
    ],
    'BIENTRAITANCE' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Maîtrise bientraitance'],
        'socio_culturel' => [3, 100, 'continue_catalogue', 'Maîtrise bientraitance'],
        'hotellerie'     => [3, 50,  'continue_catalogue', '50% du secteur formé'],
        'maintenance'    => [2, 50,  'continue_catalogue', '50% du secteur formé'],
        'administration' => [2, 50,  'continue_catalogue', '50% du secteur formé'],
        'management'     => [4, 100, 'continue_catalogue', 'Référent bientraitance'],
    ],
    'CYBER_SECURITE' => [
        'soins'          => [2, 100, 'autodidacte', 'Recyclage 24 mois'],
        'socio_culturel' => [2, 100, 'autodidacte', 'Recyclage 24 mois'],
        'hotellerie'     => [2, 100, 'autodidacte', 'Recyclage 24 mois'],
        'maintenance'    => [2, 100, 'autodidacte', 'Recyclage 24 mois'],
        'administration' => [3, 100, 'continue_catalogue', 'Renforcé pour admin'],
        'management'     => [3, 100, 'continue_catalogue', 'Renforcé pour management'],
    ],
    'SECURITE_INCENDIE' => [
        'soins'          => [2, 100, 'interne', 'Recyclage 24 mois'],
        'socio_culturel' => [2, 100, 'interne', 'Recyclage 24 mois'],
        'hotellerie'     => [2, 100, 'interne', 'Recyclage 24 mois'],
        'maintenance'    => [3, 100, 'interne', 'Recyclage 24 mois — équipe technique référente'],
        'administration' => [2, 100, 'interne', 'Recyclage 24 mois'],
        'management'     => [3, 100, 'interne', 'Pilotage sécurité incendie'],
    ],

    // ── CAS 2 : SECTORIEL (réservé certains secteurs) ──────────────────────
    'SOINS_PALLIATIFS' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Maîtrise soins palliatifs'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Accompagnement fin de vie'],
        'hotellerie'     => [1, 50,  'continue_catalogue', 'Service repas et présence en fin de vie'],
        // Pas de maintenance / admin / mgmt
    ],
    'CHUTES' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Prévention chutes'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Prévention chutes'],
    ],
    'HYGIENE_BUCCO' => [
        'soins'          => [2, 100, 'continue_catalogue', 'Hygiène bucco-dentaire'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Hygiène bucco-dentaire'],
    ],
    'BASSE_VISION' => [
        'soins'          => [2, 100, 'continue_catalogue', 'Accompagnement basse-vision'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'Accompagnement basse-vision'],
    ],
    'ERGO_PDSP' => [
        // Manutention résidents : Soins + Socio uniquement
        'soins'          => [3, 100, 'continue_catalogue', 'PDSP/GAPA — manutention résidents'],
        'socio_culturel' => [2, 100, 'continue_catalogue', 'PDSP/GAPA — manutention résidents'],
    ],
    'ERGO_POSTE' => [
        // Ergonomie poste de travail : tous SAUF Soins (car eux ont PDSP)
        'hotellerie'     => [2, 100, 'continue_catalogue', 'Ergonomie poste hôtelier'],
        'maintenance'    => [2, 100, 'continue_catalogue', 'Ergonomie poste technique'],
        'administration' => [2, 100, 'continue_catalogue', 'Ergonomie poste bureau'],
        'management'     => [2, 100, 'continue_catalogue', 'Ergonomie poste bureau'],
    ],
    'EXAMENS_CLINIQUES' => [
        'soins'          => [2, 50,  'continue_catalogue', 'Examens cliniques · Inf'],
    ],
    'TRANSMISSIONS_INF' => [
        // Cas 3 : session dédiée Inf
        'soins'          => [3, 100, 'continue_catalogue', 'Transmissions ciblées Inf'],
    ],
    'TRANSMISSIONS_AS' => [
        // Cas 3 : session dédiée ASA-ASSC
        'soins'          => [3, 100, 'continue_catalogue', 'Transmissions ciblées ASA-ASSC'],
    ],
    'ACTES_DELEGUES' => [
        'soins'          => [3, 100, 'continue_catalogue', 'Actes délégués validés OCS'],
    ],
    'QUALITE' => [
        'soins'          => [2, 50,  'continue_catalogue', 'Démarche qualité'],
        'administration' => [2, 100, 'continue_catalogue', 'Démarche qualité administrative'],
        'management'     => [3, 100, 'continue_catalogue', 'Pilotage qualité'],
    ],
    // FPP / PF : laissés vides (formations spécifiques sur dossier individuel)
];

// 4. Insertion des lignes
$total = 0;
$skipped = 0;
foreach ($matrice as $code => $secteurs) {
    if (!isset($thematiques[$code])) {
        echo "  ⚠ Thématique introuvable : $code (skip)\n";
        $skipped++;
        continue;
    }
    $themId = $thematiques[$code];
    foreach ($secteurs as $secteur => [$niv, $pct, $type, $obj]) {
        Db::exec(
            "INSERT INTO competences_profil_attendu
             (id, thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande, objectif_strategique)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?)",
            [Uuid::v4(), $themId, $secteur, $niv, $pct, $type, $obj]
        );
        $total++;
    }
}

echo "  ✓ Lignes insérées : $total\n";
if ($skipped) echo "  ⚠ Thématiques skippées : $skipped\n";

// 5. Vérification synthèse
echo "\n→ Vérification couverture finale par thématique :\n";
$rows = Db::fetchAll(
    "SELECT t.code, COUNT(p.id) AS n_secteurs
     FROM competences_thematiques t
     LEFT JOIN competences_profil_attendu p ON p.thematique_id = t.id AND p.requis = 1
     WHERE t.categorie = 'fegems_base'
     GROUP BY t.id, t.code, t.ordre
     ORDER BY t.ordre"
);
foreach ($rows as $r) {
    $marker = $r['n_secteurs'] > 0 ? '✓' : '○';
    echo "  $marker {$r['code']} : {$r['n_secteurs']} secteur(s)\n";
}

echo "\n✓ Migration 091 terminée.\n";
