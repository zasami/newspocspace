<?php
/**
 * Seed Phase 2bis — Étoffer le référentiel des fonctions et rééquilibrer
 * la distribution des collaborateurs sur les 6 secteurs FEGEMS.
 *
 * Cible démo :
 *   Soins          ~70  (gardent Inf, ASSC, AS, RUV, Resp soins)
 *   Socio-culturel  8   (ASE / Animateur·trice)
 *   Hôtellerie      8   (cuisine, lingerie, service, accueil)
 *   Maintenance     4
 *   Administration 11   (RH, compta, secrétariat, accueil)
 *   Management      4   (direction, responsables transverses)
 */
require_once __DIR__ . '/../init.php';

mt_srand(20260428);

// ── 1. Créer les fonctions manquantes ─────────────────────────
$nouvellesFonctions = [
    // Socio-culturel
    ['Animateur·trice',          'ANIM',   'socio_culturel'],
    ['Maître socio-éducatif',    'MSE',    'socio_culturel'],

    // Hôtellerie
    ['Aide-cuisine',             'AIDC',   'hotellerie'],
    ['Lingère',                  'LING',   'hotellerie'],
    ['Femme/Homme de chambre',   'FDC',    'hotellerie'],
    ['Serveur·euse',             'SERV',   'hotellerie'],

    // Maintenance
    ['Technicien polyvalent',    'TECH',   'maintenance'],
    ['Concierge',                'CONC',   'maintenance'],
    ['Jardinier·ère',            'JARD',   'maintenance'],

    // Administration
    ['Secrétaire',               'SECR',   'administration'],
    ['Comptable',                'CPTA',   'administration'],
    ['Assistant·e RH',           'ARH',    'administration'],
    ['Réceptionniste',           'RECEP',  'administration'],
    ['Assistant·e direction',    'ADIR',   'administration'],

    // Management
    ['Direction',                'DIR',    'management'],
    ['Direction adjointe',       'DIRADJ', 'management'],
    ['Responsable hôtellerie',   'RHOT',   'management'],
    ['Responsable animation',    'RANIM',  'management'],
    ['Responsable RH',           'RRH',    'management'],
];

$created = 0;
foreach ($nouvellesFonctions as [$nom, $code, $secteur]) {
    $exists = Db::fetch("SELECT id FROM fonctions WHERE code = ?", [$code]);
    if ($exists) {
        // Update mapping si manquant
        Db::exec("UPDATE fonctions SET secteur_fegems = COALESCE(secteur_fegems, ?) WHERE id = ?", [$secteur, $exists['id']]);
        continue;
    }
    Db::exec(
        "INSERT INTO fonctions (id, nom, code, secteur_fegems, ordre) VALUES (?, ?, ?, ?, ?)",
        [Uuid::v4(), $nom, $code, $secteur, 100 + $created]
    );
    $created++;
}
echo "Nouvelles fonctions créées : $created\n";

// ── 2. Mapping fonctions existantes oubliées ──────────────────
Db::exec("UPDATE fonctions SET secteur_fegems = 'hotellerie' WHERE nom = 'Hôtellerie' AND secteur_fegems IS NULL");

// ── 3. Réaffecter des collaborateurs ──────────────────────────
//
// On identifie les collab actifs dans Soins (excluant les responsables RUV / Resp. soins
// qui doivent rester) et on les réaffecte vers les autres secteurs selon les cibles.

$cibles = [
    // [code fonction, nb à atteindre]
    ['ANIM',   3],
    ['ASE',    2],  // déjà 2, donc 0 à ajouter — mais on s'assure du quota
    ['MSE',    1],
    ['AIDC',   2],
    ['LING',   2],
    ['FDC',    2],
    ['TECH',   2],
    ['CONC',   1],
    ['JARD',   1],
    ['SECR',   3],
    ['CPTA',   1],
    ['ARH',    2],
    ['RECEP',  2],
    ['ADIR',   1],
    ['DIR',    1],
    ['DIRADJ', 1],
    ['RHOT',   1],
    ['RRH',    1],
];

// Construire le pool de collab "réaffectables" : fonctions Soins basiques sauf
// responsables et infirmières expérimentées
$poolCollab = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.fonction_id, u.date_entree, f.code AS fcode
     FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND u.role IN ('collaborateur','responsable')
       AND f.code IN ('AS','ASSC')
     ORDER BY RAND()"
);
echo "Pool de collab réaffectables : " . count($poolCollab) . "\n";

$reaffected = 0;
$poolIdx = 0;
foreach ($cibles as [$fcode, $nbCible]) {
    $fonction = Db::fetch("SELECT id, secteur_fegems FROM fonctions WHERE code = ?", [$fcode]);
    if (!$fonction) continue;

    // Compter combien sont déjà sur cette fonction
    $deja = (int) Db::getOne(
        "SELECT COUNT(*) FROM users WHERE fonction_id = ? AND is_active = 1",
        [$fonction['id']]
    );
    $toAdd = max(0, $nbCible - $deja);
    if ($toAdd === 0) continue;

    for ($i = 0; $i < $toAdd && $poolIdx < count($poolCollab); $i++, $poolIdx++) {
        $collab = $poolCollab[$poolIdx];
        // Déterminer un nouveau rôle si management : responsable
        $newRole = in_array($fcode, ['DIR','DIRADJ','RHOT','RANIM','RRH'], true) ? 'responsable' : null;
        $sql = "UPDATE users SET fonction_id = ?";
        $binds = [$fonction['id']];
        if ($newRole) {
            $sql .= ", role = ?";
            $binds[] = $newRole;
        }
        $sql .= " WHERE id = ?";
        $binds[] = $collab['id'];
        Db::exec($sql, $binds);

        // Supprimer ses anciennes évaluations Soins (incohérentes avec le nouveau secteur)
        Db::exec("DELETE FROM competences_user WHERE user_id = ?", [$collab['id']]);

        $reaffected++;
    }
}
echo "Collaborateurs réaffectés : $reaffected\n";

// ── 4. Régénérer competences_user pour les réaffectés ─────────
// Pour simplicité, on relance la même logique qu'au seed 088 mais uniquement
// pour les utilisateurs qui n'ont actuellement aucune évaluation.

$matrix = [
    'soins' => [
        ['HPCI_BASE', 4], ['HPCI_PRECAUTIONS', 3], ['BLS_AED', 3], ['INC', 2],
        ['BPSD', 3], ['SOINS_PALLIATIFS', 3], ['CHUTES', 3], ['HYGIENE_BUCCO', 2],
        ['BIENTRAITANCE', 3], ['QUALITE', 2], ['CYBER_SECURITE', 2], ['SECURITE_INCENDIE', 2],
        ['EXAMENS_CLINIQUES', 2], ['TRANSMISSIONS_INF', 3], ['TRANSMISSIONS_AS', 3], ['ACTES_DELEGUES', 3],
    ],
    'socio_culturel' => [
        ['INC', 2], ['BPSD', 2], ['BIENTRAITANCE', 3], ['SOINS_PALLIATIFS', 2],
        ['CYBER_SECURITE', 2], ['SECURITE_INCENDIE', 2], ['BLS_AED', 2],
    ],
    'hotellerie' => [
        ['INC', 2], ['HPCI_BASE', 2], ['BIENTRAITANCE', 3], ['CYBER_SECURITE', 2],
        ['SECURITE_INCENDIE', 2], ['BLS_AED', 2],
    ],
    'maintenance' => [
        ['INC', 2], ['SECURITE_INCENDIE', 3], ['CYBER_SECURITE', 2], ['BIENTRAITANCE', 2],
        ['HPCI_BASE', 2], ['BLS_AED', 2],
    ],
    'administration' => [
        ['INC', 2], ['CYBER_SECURITE', 3], ['QUALITE', 2], ['SECURITE_INCENDIE', 2], ['BIENTRAITANCE', 2],
    ],
    'management' => [
        ['INC', 2], ['BIENTRAITANCE', 4], ['QUALITE', 3], ['CYBER_SECURITE', 3],
        ['SECURITE_INCENDIE', 3], ['HPCI_BASE', 2], ['BPSD', 2], ['SOINS_PALLIATIFS', 2], ['BLS_AED', 2],
    ],
];

$them = function ($code) {
    static $cache = [];
    if (!isset($cache[$code])) {
        $r = Db::fetch("SELECT id FROM competences_thematiques WHERE code = ?", [$code]);
        $cache[$code] = $r ? $r['id'] : null;
    }
    return $cache[$code];
};

$collabANouveaux = Db::fetchAll(
    "SELECT u.id, u.date_entree, f.secteur_fegems
     FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND u.role IN ('collaborateur','responsable')
       AND f.secteur_fegems IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM competences_user cu WHERE cu.user_id = u.id)"
);
$today = new DateTime();
$evalCount = 0;

foreach ($collabANouveaux as $c) {
    $secteur = $c['secteur_fegems'];
    if (!isset($matrix[$secteur])) continue;
    $entree = $c['date_entree'] ? new DateTime($c['date_entree']) : (new DateTime())->modify('-2 years');
    $annees = $today->diff($entree)->y + ($today->diff($entree)->m / 12);

    foreach ($matrix[$secteur] as [$code, $nivR]) {
        $tid = $them($code);
        if (!$tid) continue;
        $r = mt_rand(0, 100);
        if ($annees < 1) {
            $nivA = $r < 70 ? 1 : ($r < 95 ? 2 : 3);
        } elseif ($annees < 3) {
            $nivA = $r < 25 ? 1 : ($r < 65 ? 2 : ($r < 95 ? 3 : 4));
        } elseif ($annees < 7) {
            $nivA = $r < 10 ? 1 : ($r < 30 ? 2 : ($r < 80 ? 3 : 4));
        } else {
            $nivA = $r < 5 ? 2 : ($r < 50 ? 3 : 4);
        }
        if ($r > 95 && $annees < 0.5) $nivA = null;
        Db::exec(
            "INSERT INTO competences_user (id, user_id, thematique_id, niveau_actuel, niveau_requis, date_evaluation)
             VALUES (?, ?, ?, ?, ?, CURDATE())",
            [Uuid::v4(), $c['id'], $tid, $nivA, $nivR]
        );
        $evalCount++;
    }
}
echo "Nouvelles évaluations générées : $evalCount\n";

// ── 5. Distribution finale ────────────────────────────────────
echo "\nDistribution finale par secteur :\n";
$secs = Db::fetchAll(
    "SELECT COALESCE(f.secteur_fegems, '— non mappé —') AS s, COUNT(u.id) AS nb
     FROM users u JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND u.role IN ('collaborateur','responsable')
     GROUP BY s ORDER BY nb DESC"
);
foreach ($secs as $s) echo '  ' . str_pad($s['s'], 22) . $s['nb'] . "\n";

echo "\n✓ Seed Phase 2bis terminé.\n";
