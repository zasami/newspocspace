<?php
/**
 * 037: Seed 3 cuisine/hotellerie users + permissions
 * Run once: php migrations/037_seed_cuisine_users.php
 */
require_once __DIR__ . '/../init.php';

echo "=== Seed cuisine users ===\n";

// Get fonction IDs
$chef = Db::fetch("SELECT id FROM fonctions WHERE code = 'CHEF'");
$cuis = Db::fetch("SELECT id FROM fonctions WHERE code = 'CUIS'");
$hot  = Db::fetch("SELECT id FROM fonctions WHERE code = 'HOT'");

if (!$chef || !$cuis || !$hot) {
    echo "ERROR: fonctions CHEF/CUIS/HOT not found. Run migration 036 first.\n";
    exit(1);
}

$password = password_hash('123', PASSWORD_BCRYPT, ['cost' => 12]);

$users = [
    [
        'prenom' => 'Olivier',
        'nom'    => 'Deresto',
        'email'  => 'olivier.deresto@terrassiere.ch',
        'fonction_id' => $chef['id'],
        // Chef: full cuisine access + emails
        'allowed' => ['page_cuisine', 'cuisine_saisie_menu', 'cuisine_reservations_collab', 'cuisine_reservations_famille', 'cuisine_table_vip', 'page_emails'],
    ],
    [
        'prenom' => 'Dominiq',
        'nom'    => 'Soupe',
        'email'  => 'dominiq.soupe@terrassiere.ch',
        'fonction_id' => $cuis['id'],
        // Cuisinier: reservations collab + emails
        'allowed' => ['page_cuisine', 'cuisine_reservations_collab', 'page_emails'],
    ],
    [
        'prenom' => 'Myriam',
        'nom'    => 'Hotellerie',
        'email'  => 'myriam.hotellerie@terrassiere.ch',
        'fonction_id' => $hot['id'],
        // Hotellerie: reservations famille + collab + emails — NO planning, desirs, absences, etc.
        'allowed' => ['page_cuisine', 'cuisine_reservations_famille', 'cuisine_reservations_collab', 'page_emails'],
    ],
];

// All permission keys
$allKeys = array_keys(Permission::ALL_KEYS);

foreach ($users as $u) {
    // Check if already exists
    $existing = Db::getOne("SELECT COUNT(*) FROM users WHERE email = ?", [$u['email']]);
    if ($existing > 0) {
        echo "SKIP: {$u['email']} already exists\n";
        continue;
    }

    // Auto employee_id
    $lastNum = Db::getOne("SELECT MAX(CAST(SUBSTRING(employee_id, 5) AS UNSIGNED)) FROM users WHERE employee_id LIKE 'EMS-%'");
    $nextNum = ($lastNum ?: 0) + 1;
    $employeeId = 'EMS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO users (id, employee_id, email, password, nom, prenom, fonction_id, taux, role, type_contrat)
         VALUES (?, ?, ?, ?, ?, ?, ?, 100, 'collaborateur', 'CDI')",
        [$id, $employeeId, $u['email'], $password, $u['nom'], $u['prenom'], $u['fonction_id']]
    );

    // Set permissions: deny everything not in allowed list
    $perms = [];
    foreach ($allKeys as $key) {
        $perms[$key] = in_array($key, $u['allowed']) ? 1 : 0;
    }
    Permission::setForUser($id, $perms);

    echo "OK: {$u['prenom']} {$u['nom']} ({$u['email']}) — {$employeeId}\n";
}

echo "=== Done ===\n";
