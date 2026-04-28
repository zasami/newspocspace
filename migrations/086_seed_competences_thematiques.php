<?php
/**
 * Seed des thématiques FEGEMS — référentiel officiel.
 * Idempotent : INSERT IGNORE sur le code unique.
 */
require_once __DIR__ . '/../init.php';

// ── 1. Thématiques de base FEGEMS ─────────────────────────────
$base = [
    ['HPCI_BASE',         'HPCI de base',                                   'Obligatoire FEGEMS', 'shield-check',  'teal',   12, 1],
    ['HPCI_PRECAUTIONS',  'HPCI · précautions standards et MA',             'Obligatoire FEGEMS', 'shield-check',  'teal',   12, 2],
    ['BLS_AED',           'BLS-AED · réanimation cardio-pulmonaire',        'Obligatoire (2 ans)','heart-pulse',  'red',    24, 3],
    ['INC',               'Intégration nouveaux collaborateurs',            'Obligatoire FEGEMS', 'person-plus',   'blue',   null, 4],
    ['BPSD',              'BPSD · symptômes comportementaux et démence',    'FEGEMS',             'brain',         'purple', null, 5],
    ['SOINS_PALLIATIFS',  'Soins palliatifs',                               'Plan cantonal',      'heart',         'rose',   null, 6],
    ['CHUTES',            'Chutes',                                         'FEGEMS',             'exclamation-triangle','sand',null,7],
    ['HYGIENE_BUCCO',     'Hygiène bucco-dentaire',                         'FEGEMS',             'droplet',       'blue',   null, 8],
    ['BIENTRAITANCE',     'Bientraitance',                                  'Obligatoire FEGEMS', 'hand-thumbs-up','teal',   null, 9],
    ['QUALITE',           'Qualité',                                        'FEGEMS',             'patch-check',   'green',  null, 10],
    ['ERGO_PDSP',         'Ergo · PDSP/GAPA',                               'FEGEMS',             'arrow-up-square','teal',  null, 11],
    ['ERGO_POSTE',        'Ergo · poste de travail',                        'FEGEMS',             'pc-display',    'blue',   null, 12],
    ['BASSE_VISION',      'Basse-vision',                                   'FEGEMS',             'eye',           'purple', null, 13],
    ['CYBER_SECURITE',    'Cyber-sécurité',                                 'Recommandé OCS',     'shield-lock',   'red',    24, 14],
    ['SECURITE_INCENDIE', 'Sécurité incendie',                              'Obligatoire',        'fire',          'red',    24, 15],
    ['EXAMENS_CLINIQUES', 'Examens cliniques',                              'Soins',              'clipboard-pulse','teal',  null, 16],
    ['TRANSMISSIONS_INF', 'Transmissions ciblées · Infirmiers',             'Soins',              'chat-quote',    'teal',   null, 17],
    ['TRANSMISSIONS_AS',  'Transmissions ciblées · ASA-ASSC',               'Soins',              'chat-quote',    'teal',   null, 18],
    ['ACTES_DELEGUES',    'Actes délégués · Infirmiers/ASSC/aides',         'Validé OCS',         'check2-square', 'sand',   24, 19],
    ['FPP',               'FPP · Formation professionnelle pratique',       'FEGEMS',             'mortarboard',   'blue',   null, 20],
    ['PF',                'PF · Praticien formateur',                       'Soins',              'mortarboard',   'blue',   null, 21],
];

// ── 2. Référents (rôles spécialisés Soins) ─────────────────────
$referents = [
    ['REF_ERGO',          'Référent Ergo',                  'shield-check',     22],
    ['REF_HPCI',          'Référent HPCI',                  'shield-check',     23],
    ['REF_DOULEUR',       'Référent douleur',               'bandaid',          24],
    ['REF_SP',            'Référent soins palliatifs',      'heart',            25],
    ['REF_DEMENCE',       'Référent démence',               'brain',            26],
    ['REF_BASSE_VISION',  'Référent basse-vision',          'eye',              27],
    ['REF_CHUTES',        'Référent chutes',                'exclamation-triangle',28],
    ['REF_HYGIENE_BUCCO', 'Référent hygiène bucco-dentaire','droplet',          29],
    ['REF_NUTRITION',     'Référent nutrition',             'cup-straw',        30],
    ['REF_PLAIE',         'Référent plaies',                'bandaid',          31],
    ['REF_IQM',           'Référent IQM',                   'clipboard-data',   32],
    ['REF_PLAISIR_PLEX',  'Référent Plaisir/PLEX',          'emoji-smile',      33],
    ['REF_PHARMACIE',     'Référent pharmacie',             'capsule',          34],
];

// ── 3. Référents pédagogiques (parent = référent métier) ───────
$referentsPedago = [
    ['REF_PEDAGO_ERGO',          'REF_ERGO',          35],
    ['REF_PEDAGO_HPCI',          'REF_HPCI',          36],
    ['REF_PEDAGO_DOULEUR',       'REF_DOULEUR',       37],
    ['REF_PEDAGO_SP',            'REF_SP',            38],
    ['REF_PEDAGO_DEMENCE',       'REF_DEMENCE',       39],
    ['REF_PEDAGO_BASSE_VISION',  'REF_BASSE_VISION',  40],
    ['REF_PEDAGO_CHUTES',        'REF_CHUTES',        41],
    ['REF_PEDAGO_HYGIENE_BUCCO', 'REF_HYGIENE_BUCCO', 42],
];

$inserted = 0;
$skipped = 0;

foreach ($base as [$code, $nom, $tag, $icone, $couleur, $duree, $ordre]) {
    $exists = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
    if ($exists) { $skipped++; continue; }
    Db::exec(
        "INSERT INTO competences_thematiques (id, code, nom, categorie, tag_affichage, icone, couleur, duree_validite_mois, ordre)
         VALUES (?, ?, ?, 'fegems_base', ?, ?, ?, ?, ?)",
        [Uuid::v4(), $code, $nom, $tag, $icone, $couleur, $duree, $ordre]
    );
    $inserted++;
}

foreach ($referents as [$code, $nom, $icone, $ordre]) {
    $exists = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
    if ($exists) { $skipped++; continue; }
    Db::exec(
        "INSERT INTO competences_thematiques (id, code, nom, categorie, tag_affichage, icone, couleur, ordre)
         VALUES (?, ?, ?, 'referent', 'Référent', ?, 'teal', ?)",
        [Uuid::v4(), $code, $nom, $icone, $ordre]
    );
    $inserted++;
}

foreach ($referentsPedago as [$code, $parentCode, $ordre]) {
    $exists = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
    if ($exists) { $skipped++; continue; }
    $parent = Db::fetch("SELECT id, nom FROM competences_thematiques WHERE code = ?", [$parentCode]);
    if (!$parent) { fwrite(STDERR, "Parent introuvable : $parentCode\n"); continue; }
    Db::exec(
        "INSERT INTO competences_thematiques (id, code, nom, categorie, parent_thematique_id, tag_affichage, icone, couleur, ordre)
         VALUES (?, ?, ?, 'referent_pedago', ?, 'Compétences pédagogiques', 'mortarboard', 'purple', ?)",
        [Uuid::v4(), $code, 'Compétences pédagogiques · ' . $parent['nom'], $parent['id'], $ordre]
    );
    $inserted++;
}

echo "Thématiques FEGEMS — insérées: $inserted · ignorées (déjà présentes): $skipped\n";

// ── 4. Auto-mapping fonctions → secteur FEGEMS (best-effort) ───
$mapping = [
    'inf'      => 'soins',         'infirmier' => 'soins',
    'assc'     => 'soins',         'asa'       => 'soins',
    'aide'     => 'soins',         'soin'      => 'soins',
    'anim'     => 'socio_culturel','social'    => 'socio_culturel',
    'cuisine'  => 'hotellerie',    'hotel'     => 'hotellerie',
    'lingerie' => 'hotellerie',    'service'   => 'hotellerie',
    'tech'     => 'maintenance',   'maint'     => 'maintenance',
    'concier'  => 'maintenance',
    'admin'    => 'administration','rh'        => 'administration',
    'compta'   => 'administration','secret'    => 'administration',
    'direct'   => 'management',    'cadre'     => 'management',
    'resp'     => 'management',
];

$fonctions = Db::fetchAll("SELECT id, nom, code FROM fonctions WHERE secteur_fegems IS NULL");
$mapped = 0;
foreach ($fonctions as $f) {
    $key = mb_strtolower($f['nom'] . ' ' . ($f['code'] ?? ''));
    foreach ($mapping as $needle => $secteur) {
        if (mb_strpos($key, $needle) !== false) {
            Db::exec("UPDATE fonctions SET secteur_fegems = ? WHERE id = ?", [$secteur, $f['id']]);
            $mapped++;
            break;
        }
    }
}
echo "Fonctions auto-mappées vers secteurs FEGEMS : $mapped / " . count($fonctions) . "\n";
