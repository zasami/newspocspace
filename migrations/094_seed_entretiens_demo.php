<?php
/**
 * Seed démo entretiens annuels.
 *
 * - 30 entretiens 'realise' sur les 12 derniers mois (avec notes manager)
 * - 20 entretiens 'planifie' à venir dans les 60 prochains jours
 * - 5 entretiens 'reporte'
 * - 25 users avec prochain_entretien_date à venir (déclencheurs auto-cron)
 * - 15 objectifs annuels liés à des entretiens réalisés
 *
 * Idempotent (--force pour réinitialiser).
 */
require_once __DIR__ . '/../init.php';
if (!function_exists('nonce')) { function nonce(): string { return ''; } }

$force = in_array('--force', $argv ?? [], true);
echo "→ Seed démo entretiens" . ($force ? " (--force)" : "") . "\n";

if ($force) {
    Db::exec("DELETE FROM competences_objectifs_annuels WHERE entretien_origine_id IS NOT NULL");
    Db::exec("DELETE FROM entretiens_annuels");
    echo "  Tables vidées.\n";
}

$existing = (int) Db::getOne("SELECT COUNT(*) FROM entretiens_annuels");
if ($existing > 0 && !$force) {
    echo "  $existing entretien(s) existent déjà — relancer avec --force pour reset.\n";
    exit(0);
}

// Récupérer 75 users actifs avec leurs N+1
$users = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.n_plus_un_id,
            fn.secteur_fegems, fn.nom AS fonction
     FROM users u
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE u.is_active = 1 AND fn.secteur_fegems IS NOT NULL
     ORDER BY RAND()
     LIMIT 75"
);
echo "  " . count($users) . " users sélectionnés\n";

// Quelques managers pour evaluator_id quand n_plus_un_id manque
$managers = Db::fetchAll(
    "SELECT u.id FROM users u WHERE u.is_active = 1 AND u.role IN ('responsable','admin','direction') LIMIT 10"
);
$mgrIds = array_column($managers, 'id');

$today = new DateTimeImmutable('today');
$nbRealises = 0; $nbPlanifies = 0; $nbReportes = 0; $nbObjectifs = 0;

// Templates de notes
$notesManagerTemplates = [
    "Très bonne année. Implication continue dans l'équipe, qualité du travail constante. À encourager dans la formation continue HPCI cette année.",
    "Année stable, intégration réussie. Quelques points d'attention sur les transmissions à formaliser. Objectif : autonomie sur les actes délégués.",
    "Excellent collaborateur. Capacité à former les nouveaux arrivants — pourrait être pressenti·e comme référent BLS-AED.",
    "Année compliquée sur le plan personnel, soutien apporté. Reprise pleine confirmée. Maintenir le suivi rapproché.",
    "Performance au-delà des attentes. Polyvalence appréciée. Discuter d'une évolution interne et de formations supérieures.",
];
$notesCollabTemplates = [
    "Je me sens bien intégré·e. J'aimerais approfondir mes compétences en bientraitance et démence. Disponible pour les sessions FEGEMS.",
    "Année dense mais formatrice. Les transmissions ciblées me posent encore problème, je souhaite une formation dédiée.",
    "Tout va bien. Je souhaite continuer dans cette voie et développer les soins palliatifs.",
    "J'ai eu quelques difficultés en début d'année (équipe nouvelle). Maintenant tout est rentré dans l'ordre.",
    "Je voudrais m'orienter vers un poste de référent. Quelles formations sont nécessaires ?",
];
$objectifsTemplates = [
    ["Obtenir le BLS-AED renouvellement avant fin Q2", 'Q2'],
    ["Compléter la formation HPCI niveau 3", 'annuel'],
    ["Devenir référent·e bientraitance", 'annuel'],
    ["Maîtriser les transmissions ciblées", 'Q3'],
    ["Suivre la formation BPSD", 'Q4'],
    ["Améliorer l'autonomie sur soins palliatifs", 'annuel'],
    ["Recyclage cyber-sécurité", 'Q1'],
    ["Formation FPP/PF (praticien formateur)", 'annuel'],
];

// 30 réalisés sur les 12 derniers mois
foreach (array_slice($users, 0, 30) as $u) {
    $daysAgo = mt_rand(15, 360);
    $date = $today->modify("-$daysAgo days")->format('Y-m-d');
    $annee = (int) substr($date, 0, 4);
    $eval = $u['n_plus_un_id'] ?: ($mgrIds[array_rand($mgrIds)] ?? null);

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO entretiens_annuels
         (id, user_id, evaluator_id, annee, date_entretien, statut, notes_manager, notes_collaborateur, signed_at)
         VALUES (?, ?, ?, ?, ?, 'realise', ?, ?, ?)",
        [
            $id, $u['id'], $eval, $annee, $date,
            $notesManagerTemplates[array_rand($notesManagerTemplates)],
            mt_rand(0, 100) < 70 ? $notesCollabTemplates[array_rand($notesCollabTemplates)] : null,
            $date . ' 16:30:00',
        ]
    );
    $nbRealises++;

    // 50% des entretiens réalisés génèrent 1-2 objectifs
    if (mt_rand(0, 100) < 50) {
        $nbObj = mt_rand(1, 2);
        for ($i = 0; $i < $nbObj; $i++) {
            $tpl = $objectifsTemplates[array_rand($objectifsTemplates)];
            $statuts = ['en_cours', 'en_cours', 'atteint', 'a_definir'];
            $statut = $statuts[array_rand($statuts)];
            Db::exec(
                "INSERT INTO competences_objectifs_annuels
                 (id, user_id, annee, trimestre_cible, libelle, statut, entretien_origine_id, ordre,
                  date_atteint)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Uuid::v4(), $u['id'], $annee, $tpl[1], $tpl[0], $statut, $id, $i,
                    $statut === 'atteint' ? $today->modify('-' . mt_rand(1, 90) . ' days')->format('Y-m-d') : null,
                ]
            );
            $nbObjectifs++;
        }
    }

    // Mettre à jour prochain_entretien_date selon fréquence (12 mois)
    $next = (new DateTimeImmutable($date))->modify('+12 months')->format('Y-m-d');
    Db::exec("UPDATE users SET prochain_entretien_date = ? WHERE id = ?", [$next, $u['id']]);
}
echo "  $nbRealises entretiens réalisés (avec $nbObjectifs objectifs)\n";

// 20 planifiés à venir (15 dans les 30j, 5 dans les 30-60j)
foreach (array_slice($users, 30, 20) as $i => $u) {
    $daysAhead = $i < 15 ? mt_rand(3, 30) : mt_rand(31, 60);
    $date = $today->modify("+$daysAhead days")->format('Y-m-d');
    $eval = $u['n_plus_un_id'] ?: ($mgrIds[array_rand($mgrIds)] ?? null);

    Db::exec(
        "INSERT INTO entretiens_annuels (id, user_id, evaluator_id, annee, date_entretien, statut)
         VALUES (?, ?, ?, ?, ?, 'planifie')",
        [Uuid::v4(), $u['id'], $eval, (int) substr($date, 0, 4), $date]
    );
    Db::exec("UPDATE users SET prochain_entretien_date = ? WHERE id = ?", [$date, $u['id']]);
    $nbPlanifies++;
}
echo "  $nbPlanifies entretiens planifiés\n";

// 5 reportés
foreach (array_slice($users, 50, 5) as $u) {
    $daysAgo = mt_rand(7, 30);
    $date = $today->modify("-$daysAgo days")->format('Y-m-d');
    $eval = $u['n_plus_un_id'] ?: ($mgrIds[array_rand($mgrIds)] ?? null);

    Db::exec(
        "INSERT INTO entretiens_annuels
         (id, user_id, evaluator_id, annee, date_entretien, statut, notes_manager)
         VALUES (?, ?, ?, ?, ?, 'reporte', ?)",
        [Uuid::v4(), $u['id'], $eval, (int) substr($date, 0, 4), $date, 'Reporté à la demande du collaborateur · à reprogrammer.']
    );
    $nbReportes++;
}
echo "  $nbReportes entretiens reportés\n";

// 20 users avec prochain_entretien_date dans les 30-180j (sans entretien planifié = échéances futures)
foreach (array_slice($users, 55, 20) as $u) {
    $daysAhead = mt_rand(30, 180);
    $next = $today->modify("+$daysAhead days")->format('Y-m-d');
    Db::exec("UPDATE users SET prochain_entretien_date = ? WHERE id = ?", [$next, $u['id']]);
}
echo "  20 échéances futures positionnées\n";

echo "\n✓ Migration 094 terminée.\n";
echo "  Total : " . ($nbRealises + $nbPlanifies + $nbReportes) . " entretiens, $nbObjectifs objectifs\n";
