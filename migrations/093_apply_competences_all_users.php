<?php
/**
 * Rétro-applique le hook init_competences_for_user sur tous les users actifs.
 *
 * Pour chaque user actif avec une fonction → secteur FEGEMS :
 *   - INSERT des thématiques manquantes du profil_attendu (niveau_actuel = NULL)
 *   - UPDATE niveau_requis pour les lignes existantes (sans toucher niveau_actuel)
 *
 * Lancer : php migrations/093_apply_competences_all_users.php
 */
require_once __DIR__ . '/../init.php';
if (!function_exists('nonce')) { function nonce(): string { return ''; } }
require_once __DIR__ . '/../admin/api_modules/formations_competences.php';

echo "→ Application du profil FEGEMS à tous les users actifs\n";

$users = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.fonction_id, fn.secteur_fegems
     FROM users u
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE u.is_active = 1
     ORDER BY fn.secteur_fegems, u.nom"
);

$totalInserted = 0;
$totalUpdated = 0;
$skipped = 0;

foreach ($users as $u) {
    $res = init_competences_for_user($u['id'], $u['fonction_id']);
    if (isset($res['skipped'])) {
        $skipped++;
        continue;
    }
    $totalInserted += $res['inserted'];
    $totalUpdated += $res['updated'];
}

echo "  Users traités    : " . (count($users) - $skipped) . "\n";
echo "  Users sans secteur : $skipped\n";
echo "  Lignes insérées  : $totalInserted\n";
echo "  Lignes mises à jour : $totalUpdated\n";

// Synthèse couverture par secteur
echo "\n→ Couverture finale par secteur :\n";
$rows = Db::fetchAll(
    "SELECT COALESCE(fn.secteur_fegems, 'sans_secteur') AS secteur,
            COUNT(DISTINCT u.id) AS n_users,
            COUNT(cu.id) AS n_thematiques,
            ROUND(COUNT(cu.id) / COUNT(DISTINCT u.id), 1) AS moy_par_user
     FROM users u
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     LEFT JOIN competences_user cu ON cu.user_id = u.id
     WHERE u.is_active = 1
     GROUP BY secteur
     ORDER BY n_users DESC"
);
foreach ($rows as $r) {
    echo sprintf("  %-18s %3d users · %4d lignes · moy %.1f/user\n",
        $r['secteur'], $r['n_users'], $r['n_thematiques'], $r['moy_par_user']);
}

echo "\n✓ Migration 093 terminée.\n";
