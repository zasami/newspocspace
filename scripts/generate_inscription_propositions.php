<?php
/**
 * Génération automatique des propositions d'inscription FEGEMS.
 *
 * À tourner :
 *   - via cron quotidien (recommandé) selon le paramètre `insc.frequence_scan`
 *   - via bouton admin "Resync. catalogue / regénérer suggestions"
 *
 * Logique :
 *   1. Pour chaque session ouverte future, calcule la liste des collaborateurs
 *      éligibles (écart sur la thématique couverte par la formation).
 *   2. Catégorise par motif :
 *      - renouvellement_expire : compétence avec date_expiration < today + délai
 *      - inc_nouveau : collab sans INC depuis > délai_relance_inc_jours
 *      - plan_cantonal : thématique stratégique avec écart >= 1
 *      - recommandation_ocs : par défaut
 *   3. Crée/met à jour la proposition (idempotent).
 *
 * Skip si paramètre `insc.auto_proposer` = 0.
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['HTTP_X_INTERNAL_CRON'])) {
    require_once __DIR__ . '/../init.php';
    if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin','direction'])) {
        http_response_code(403); die('Forbidden');
    }
} else {
    require_once __DIR__ . '/../init.php';
}

$autoProposer = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.auto_proposer'") === '1';
if (!$autoProposer && !in_array('--force', $argv ?? [], true)) {
    echo "Génération automatique désactivée (paramètre insc.auto_proposer=0). Forcer avec --force.\n";
    exit(0);
}

$delaiAlerte = (int) Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'exp.delai_alerte_expiration_jours'") ?: 60;
$delaiInc    = (int) Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'exp.delai_relance_inc_jours'") ?: 30;

$today = date('Y-m-d');
$dateLimiteExp = date('Y-m-d', strtotime("+$delaiAlerte days"));
$dateLimiteInc = date('Y-m-d', strtotime("-$delaiInc days"));

echo "═══ Génération propositions inscription · $today ═══\n";
echo "Délai alerte expiration : $delaiAlerte j (échéance ≤ $dateLimiteExp)\n";
echo "Délai relance INC       : $delaiInc j (entrée ≤ $dateLimiteInc)\n\n";

// Sessions ouvertes futures avec leurs thématiques
$sessions = Db::fetchAll(
    "SELECT s.id AS session_id, s.formation_id, s.date_debut, s.lieu, s.modalite, s.capacite_max, s.places_inscrites,
            f.titre AS formation_titre
     FROM formation_sessions s
     JOIN formations f ON f.id = s.formation_id
     WHERE s.statut IN ('ouverte','liste_attente') AND s.date_debut >= ?
     ORDER BY s.date_debut ASC",
    [$today]
);
echo "Sessions ouvertes à venir : " . count($sessions) . "\n\n";

$insertedProps = 0;
$insertedUsers = 0;
$skippedProps = 0;

foreach ($sessions as $s) {
    $thems = Db::fetchAll(
        "SELECT thematique_id FROM formation_thematiques WHERE formation_id = ?",
        [$s['formation_id']]
    );
    if (!$thems) continue;
    $themIds = array_column($thems, 'thematique_id');
    $themIdsPh = implode(',', array_fill(0, count($themIds), '?'));

    // ── 1. Collab avec compétence expirée ou en gap >= 1 sur ces thématiques ──
    $candidats = Db::fetchAll(
        "SELECT DISTINCT cu.user_id, u.prenom, u.nom, u.date_entree, cu.ecart, cu.priorite,
                cu.date_expiration, cu.date_evaluation, t.code AS them_code
         FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN competences_thematiques t ON t.id = cu.thematique_id
         WHERE cu.thematique_id IN ($themIdsPh)
           AND u.is_active = 1
           AND (cu.ecart >= 1 OR cu.date_expiration <= ?)",
        array_merge($themIds, [$dateLimiteExp])
    );

    if (!$candidats) continue;

    // Détecter le motif principal
    $motifPrincipal = 'recommandation_ocs';
    $libelleMotif = 'Suggestion automatique basée sur la cartographie d\'équipe.';
    $isInc = false;

    if (in_array('INC', array_column(array_filter($thems, fn($t) => $t), 'thematique_id'), true)
        || in_array('INC', array_column($candidats, 'them_code'), true)) {
        $motifPrincipal = 'inc_nouveau';
        $libelleMotif = 'Nouveaux collaborateurs sans formation INC obligatoire.';
        $isInc = true;
        // Filtrer aux nouveaux collaborateurs uniquement
        $candidats = array_filter($candidats, fn($c) => $c['date_entree'] && $c['date_entree'] >= $dateLimiteInc);
    } else {
        $hasExpiration = false;
        foreach ($candidats as $c) {
            if ($c['date_expiration'] && $c['date_expiration'] <= $dateLimiteExp) { $hasExpiration = true; break; }
        }
        if ($hasExpiration) {
            $motifPrincipal = 'renouvellement_expire';
            $libelleMotif = count($candidats) . ' collaborateurs ont une attestation expirant avant ' . date('d.m.Y', strtotime($dateLimiteExp)) . '.';
        } else {
            $hasHaute = false;
            foreach ($candidats as $c) if ($c['priorite'] === 'haute') { $hasHaute = true; break; }
            if ($hasHaute) {
                $motifPrincipal = 'plan_cantonal';
                $libelleMotif = 'Écarts prioritaires identifiés sur cette thématique.';
            }
        }
    }

    if (!$candidats) continue;

    // Limiter au nombre de places disponibles + 20 % de marge
    $placesDispo = ($s['capacite_max'] ?? 999) - ($s['places_inscrites'] ?? 0);
    if ($placesDispo <= 0) continue;
    $candidats = array_slice($candidats, 0, $placesDispo + 3);

    // ── 2. Créer ou mettre à jour la proposition ──────────────
    $propExistante = Db::fetch(
        "SELECT id, statut FROM inscription_propositions WHERE session_id = ? AND type_motif = ?",
        [$s['session_id'], $motifPrincipal]
    );

    if ($propExistante && in_array($propExistante['statut'], ['envoyee','rejetee'], true)) {
        $skippedProps++;
        continue;
    }

    if (!$propExistante) {
        $propId = Uuid::v4();
        Db::exec(
            "INSERT INTO inscription_propositions
             (id, session_id, type_motif, libelle_motif, deadline_action, statut)
             VALUES (?, ?, ?, ?, ?, 'proposee')",
            [$propId, $s['session_id'], $motifPrincipal, $libelleMotif, $s['date_debut']]
        );
        $insertedProps++;
    } else {
        $propId = $propExistante['id'];
        Db::exec(
            "UPDATE inscription_propositions
             SET libelle_motif = ?, deadline_action = ?, statut = 'proposee'
             WHERE id = ?",
            [$libelleMotif, $s['date_debut'], $propId]
        );
    }

    // Dédupliquer par user_id (un user peut avoir un gap sur plusieurs thématiques de la formation)
    $candidatsUniques = [];
    foreach ($candidats as $c) {
        $uid = $c['user_id'];
        if (!isset($candidatsUniques[$uid])) {
            $candidatsUniques[$uid] = $c;
            continue;
        }
        // Garder le candidat avec le plus fort signal (priorité haute > expiration > autres)
        $existing = $candidatsUniques[$uid];
        if ($c['priorite'] === 'haute' && $existing['priorite'] !== 'haute') {
            $candidatsUniques[$uid] = $c;
        } elseif ($c['date_expiration'] && (!$existing['date_expiration'] || $c['date_expiration'] < $existing['date_expiration'])) {
            $candidatsUniques[$uid] = $c;
        }
    }

    // Ré-insérer les candidats (clean slate)
    Db::exec("DELETE FROM inscription_proposition_users WHERE proposition_id = ?", [$propId]);
    foreach ($candidatsUniques as $c) {
        $motifInd = match (true) {
            $isInc => 'jamais_forme',
            ($c['date_expiration'] ?? '') <= $dateLimiteExp && $c['date_expiration'] !== null => 'expiration',
            $c['priorite'] === 'haute' => 'urgence',
            default => 'plan_carriere',
        };
        Db::exec(
            "INSERT INTO inscription_proposition_users
             (proposition_id, user_id, motif_individuel, statut)
             VALUES (?, ?, ?, 'selectionne')",
            [$propId, $c['user_id'], $motifInd]
        );
        $insertedUsers++;
    }
}

echo "Propositions créées/mises à jour : $insertedProps\n";
echo "Lignes user/proposition : $insertedUsers\n";
echo "Propositions ignorées (déjà envoyées/rejetées) : $skippedProps\n";

// ── Stats finales ─────────────────────────────────────────────
$totalProp = (int) Db::getOne("SELECT COUNT(*) FROM inscription_propositions WHERE statut = 'proposee'");
echo "\nTotal propositions actives en attente : $totalProp\n";
echo "✓ Génération terminée.\n";
