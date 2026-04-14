<?php
/**
 * Applique les permissions par défaut de leur fonction à tous les users existants
 * qui ont une fonction avec un profil défini.
 * Run: php migrations/067_apply_fonction_profiles.php
 */
require_once __DIR__ . '/../init.php';

echo "=== Application des profils de fonctions ===\n\n";

$rows = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, f.code, f.nom AS fonction_nom, f.default_denied_perms
     FROM users u
     JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND f.default_denied_perms IS NOT NULL"
);

echo count($rows) . " user(s) à traiter.\n\n";

$applied = 0;
foreach ($rows as $u) {
    $ok = Permission::applyFonctionDefaults($u['id'], Db::getOne("SELECT fonction_id FROM users WHERE id = ?", [$u['id']]));
    if ($ok) {
        $applied++;
        echo "  ✓ {$u['prenom']} {$u['nom']} ({$u['code']} — {$u['fonction_nom']})\n";
    }
}

echo "\nTotal appliqué : $applied\n";
