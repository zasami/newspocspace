#!/usr/bin/env php
<?php
/**
 * Cron quotidien SpocSpace · 3h00
 *
 * Exécute en séquence les tâches automatisées :
 *   1. Génération auto des propositions d'inscription FEGEMS (si insc.auto_proposer = 1)
 *   2. (futur) Création auto entretiens à échéance
 *   3. (futur) Relances mail expirations compétences
 *
 * Usage :
 *   php /chemin/vers/spocspace/scripts/cron_daily.php
 *
 * Cron Infomaniak (panel d'admin > Cron) :
 *   0 3 * * *  /usr/bin/php /home/clients/<USER>/sites/zkriva.com/spocspace/scripts/cron_daily.php >> /home/clients/<USER>/logs/cron_spocspace.log 2>&1
 *
 * Lock file pour éviter exécutions concurrentes (en cas de cron qui se chevauche).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Cron CLI uniquement\n");
}

// Charger init.php AVANT tout output (sinon warnings session/headers)
$baseDir = realpath(__DIR__ . '/..');
require_once $baseDir . '/init.php';
if (!function_exists('nonce')) { function nonce(): string { return ''; } }

$lockFile = $baseDir . '/data/cron_daily.lock';
$logPrefix = '[cron_daily ' . date('Y-m-d H:i:s') . ']';

// Lock pour éviter exécutions concurrentes
@mkdir(dirname($lockFile), 0775, true);
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "$logPrefix Lock détenu, skip (cron déjà en cours)\n";
    exit(0);
}

echo "$logPrefix === Démarrage cron quotidien ===\n";
$tStart = microtime(true);

// ── Tâche 1 : Génération propositions FEGEMS ────────────────────────────────
echo "$logPrefix [1/3] Génération propositions FEGEMS\n";
try {
    $autoProposer = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.auto_proposer'");
    if ($autoProposer === '1') {
        // Exécute le script existant en mode CLI
        $script = $baseDir . '/scripts/generate_inscription_propositions.php';
        if (is_file($script)) {
            ob_start();
            require $script;
            $output = ob_get_clean();
            // Indenter chaque ligne avec le préfixe
            foreach (explode("\n", trim($output)) as $line) {
                if ($line) echo "$logPrefix   $line\n";
            }
        } else {
            echo "$logPrefix   Script introuvable : $script\n";
        }
    } else {
        echo "$logPrefix   insc.auto_proposer = 0, skip\n";
    }
} catch (\Throwable $e) {
    echo "$logPrefix   ⚠ Erreur : " . $e->getMessage() . "\n";
}

// ── Tâche 2 : Auto-création entretiens à échéance ───────────────────────────
echo "$logPrefix [2/3] Auto-création entretiens à échéance\n";
try {
    $autoCreer = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'entr.auto_creer_a_echeance'");
    $delaiAvant = (int) (Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'entr.notification_collab_avant_jours'") ?: 14);

    if ($autoCreer === '1') {
        // Users dont prochain_entretien_date est dans le délai et sans entretien planifié futur
        $candidats = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, u.prochain_entretien_date, u.n_plus_un_id
             FROM users u
             WHERE u.is_active = 1
               AND u.prochain_entretien_date IS NOT NULL
               AND u.prochain_entretien_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND u.prochain_entretien_date >= CURDATE()
               AND NOT EXISTS (
                   SELECT 1 FROM entretiens_annuels e2
                   WHERE e2.user_id = u.id AND e2.statut = 'planifie' AND e2.date_entretien >= CURDATE()
               )",
            [$delaiAvant]
        );

        $created = 0;
        foreach ($candidats as $c) {
            $id = Uuid::v4();
            Db::exec(
                "INSERT INTO entretiens_annuels (id, user_id, evaluator_id, annee, date_entretien, statut)
                 VALUES (?, ?, ?, ?, ?, 'planifie')",
                [$id, $c['id'], $c['n_plus_un_id'] ?: null, (int) date('Y'), $c['prochain_entretien_date']]
            );
            $created++;
        }
        echo "$logPrefix   $created entretien(s) créé(s) automatiquement (sur " . count($candidats) . " candidat(s))\n";
    } else {
        echo "$logPrefix   entr.auto_creer_a_echeance = 0, skip\n";
    }
} catch (\Throwable $e) {
    echo "$logPrefix   ⚠ Erreur : " . $e->getMessage() . "\n";
}

// ── Tâche 3 : Recalcul priorité compétences (refresh STORED columns si besoin) ─
echo "$logPrefix [3/3] Vérification priorités compétences\n";
try {
    $nb = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite IS NULL AND niveau_requis IS NOT NULL");
    if ($nb > 0) {
        // Touch updated_at pour déclencher le recalcul des STORED columns
        Db::exec("UPDATE competences_user SET updated_at = updated_at WHERE priorite IS NULL AND niveau_requis IS NOT NULL");
        echo "$logPrefix   $nb ligne(s) compétences rafraîchies\n";
    } else {
        echo "$logPrefix   Toutes les priorités sont à jour\n";
    }
} catch (\Throwable $e) {
    echo "$logPrefix   ⚠ Erreur : " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $tStart, 2);
echo "$logPrefix === Terminé en {$elapsed}s ===\n";

flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
