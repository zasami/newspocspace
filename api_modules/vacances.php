<?php
/**
 * Vacances API — page dédiée au dépôt de vacances
 */

/**
 * Get all data for the vacation page: users by module, absences for the year, blocked periods, config
 */
function get_vacances_annee()
{
    global $params;
    $user = require_auth();

    $annee = intval($params['annee'] ?? date('Y'));
    if ($annee < 2020 || $annee > 2040) $annee = (int) date('Y');

    $debut = "$annee-01-01";
    $fin = "$annee-12-31";

    // All users grouped by module
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.taux, u.solde_vacances,
                f.code AS fonction_code, f.nom AS fonction_nom,
                m.id AS module_id, m.code AS module_code, m.nom AS module_nom, m.ordre AS module_ordre
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE u.is_active = 1
         ORDER BY m.ordre, f.ordre, u.nom"
    );

    // All absences (vacances only) for this year — validated + pending
    $absences = Db::fetchAll(
        "SELECT a.id, a.user_id, a.date_debut, a.date_fin, a.type, a.statut,
                u.prenom, u.nom
         FROM absences a
         JOIN users u ON u.id = a.user_id
         WHERE a.type = 'vacances'
           AND a.date_debut <= ? AND a.date_fin >= ?
           AND a.statut IN ('valide', 'en_attente')
         ORDER BY a.date_debut",
        [$fin, $debut]
    );

    // Blocked periods
    $bloquees = Db::fetchAll(
        "SELECT id, date_debut, date_fin, motif FROM periodes_bloquees
         WHERE date_debut <= ? AND date_fin >= ?
         ORDER BY date_debut",
        [$fin, $debut]
    );

    // Modules list
    $modules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");

    // Config
    $config = [];
    $rows = Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key LIKE 'vacances_%'");
    foreach ($rows as $r) $config[$r['config_key']] = $r['config_value'];

    // Current user's solde
    $moi = Db::fetch("SELECT solde_vacances FROM users WHERE id = ?", [$user['id']]);

    // Count used days this year for current user
    $joursUtilises = Db::getOne(
        "SELECT COALESCE(SUM(DATEDIFF(LEAST(date_fin, ?), GREATEST(date_debut, ?)) + 1), 0)
         FROM absences
         WHERE user_id = ? AND type = 'vacances' AND statut IN ('valide', 'en_attente')
           AND date_debut <= ? AND date_fin >= ?",
        [$fin, $debut, $user['id'], $fin, $debut]
    );

    respond([
        'success'       => true,
        'annee'         => $annee,
        'users'         => $users,
        'absences'      => $absences,
        'bloquees'      => $bloquees,
        'modules'       => $modules,
        'config'        => $config,
        'mon_solde'     => floatval($moi['solde_vacances'] ?? 27),
        'jours_utilises'=> intval($joursUtilises),
    ]);
}

/**
 * Submit vacation request (drag or form)
 */
function submit_vacances()
{
    global $params;
    $user = require_auth();

    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');

    if (!$dateDebut || !$dateFin) bad_request('Dates requises');
    if ($dateFin < $dateDebut) bad_request('La date de fin doit être après la date de début');

    // Config checks
    $maxConsec = intval(Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'vacances_max_consecutifs'") ?: 28);

    // Check max consecutive (4 semaines)
    $nbJours = (new DateTime($dateDebut))->diff(new DateTime($dateFin))->days + 1;
    if ($nbJours > $maxConsec) {
        bad_request("Maximum $maxConsec jours consécutifs autorisés");
    }

    // Check blocked periods
    $blocked = Db::getOne(
        "SELECT COUNT(*) FROM periodes_bloquees
         WHERE date_debut <= ? AND date_fin >= ?",
        [$dateFin, $dateDebut]
    );
    if ($blocked > 0) {
        bad_request('Cette période est bloquée par l\'administration');
    }

    // Check overlap with own existing absences
    $overlap = Db::getOne(
        "SELECT COUNT(*) FROM absences
         WHERE user_id = ? AND statut != 'refuse'
           AND date_debut <= ? AND date_fin >= ?",
        [$user['id'], $dateFin, $dateDebut]
    );
    if ($overlap > 0) {
        bad_request('Vous avez déjà une absence sur cette période');
    }

    // Check solde
    $solde = floatval(Db::getOne("SELECT solde_vacances FROM users WHERE id = ?", [$user['id']]) ?: 27);
    $annee = substr($dateDebut, 0, 4);
    $joursUtilises = intval(Db::getOne(
        "SELECT COALESCE(SUM(DATEDIFF(LEAST(date_fin, ?), GREATEST(date_debut, ?)) + 1), 0)
         FROM absences
         WHERE user_id = ? AND type = 'vacances' AND statut IN ('valide', 'en_attente')
           AND date_debut <= ? AND date_fin >= ?",
        ["$annee-12-31", "$annee-01-01", $user['id'], "$annee-12-31", "$annee-01-01"]
    ));

    // Count workdays in the request
    $workdays = 0;
    $d = new DateTime($dateDebut);
    $e = new DateTime($dateFin);
    while ($d <= $e) {
        $dow = (int) $d->format('N');
        if ($dow <= 5) $workdays++;
        $d->modify('+1 day');
    }

    if (($joursUtilises + $workdays) > $solde) {
        bad_request("Solde insuffisant. Vous avez " . ($solde - $joursUtilises) . " jour(s) restant(s)");
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO absences (id, user_id, date_debut, date_fin, type, motif, statut)
         VALUES (?, ?, ?, ?, 'vacances', 'Demande via page vacances', 'en_attente')",
        [$id, $user['id'], $dateDebut, $dateFin]
    );

    respond(['success' => true, 'message' => 'Demande de vacances soumise', 'id' => $id]);
}

/**
 * Delete own pending vacation
 */
function annuler_vacances()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $absence = Db::fetch(
        "SELECT * FROM absences WHERE id = ? AND user_id = ? AND statut = 'en_attente'",
        [$id, $user['id']]
    );
    if (!$absence) bad_request('Demande introuvable ou déjà traitée');

    Db::exec("DELETE FROM absences WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Demande annulée']);
}

/**
 * Modify dates of own pending vacation
 */
function modifier_vacances()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');

    if (!$id) bad_request('ID requis');
    if (!$dateDebut || !$dateFin) bad_request('Dates requises');
    if ($dateFin < $dateDebut) bad_request('La date de fin doit être après la date de début');

    $absence = Db::fetch(
        "SELECT * FROM absences WHERE id = ? AND user_id = ? AND statut = 'en_attente'",
        [$id, $user['id']]
    );
    if (!$absence) bad_request('Demande introuvable ou déjà validée');

    // Check max consecutive
    $maxConsec = intval(Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'vacances_max_consecutifs'") ?: 28);
    $nbJours = (new DateTime($dateDebut))->diff(new DateTime($dateFin))->days + 1;
    if ($nbJours > $maxConsec) bad_request("Maximum $maxConsec jours consécutifs autorisés");

    // Check blocked periods
    $blocked = Db::getOne(
        "SELECT COUNT(*) FROM periodes_bloquees WHERE date_debut <= ? AND date_fin >= ?",
        [$dateFin, $dateDebut]
    );
    if ($blocked > 0) bad_request('Cette période est bloquée par l\'administration');

    // Check overlap (exclude self)
    $overlap = Db::getOne(
        "SELECT COUNT(*) FROM absences
         WHERE user_id = ? AND id != ? AND statut != 'refuse'
           AND date_debut <= ? AND date_fin >= ?",
        [$user['id'], $id, $dateFin, $dateDebut]
    );
    if ($overlap > 0) bad_request('Vous avez déjà une absence sur cette période');

    // Check solde
    $solde = floatval(Db::getOne("SELECT solde_vacances FROM users WHERE id = ?", [$user['id']]) ?: 27);
    $annee = substr($dateDebut, 0, 4);
    $joursUtilises = intval(Db::getOne(
        "SELECT COALESCE(SUM(DATEDIFF(LEAST(date_fin, ?), GREATEST(date_debut, ?)) + 1), 0)
         FROM absences
         WHERE user_id = ? AND id != ? AND type = 'vacances' AND statut IN ('valide', 'en_attente')
           AND date_debut <= ? AND date_fin >= ?",
        ["$annee-12-31", "$annee-01-01", $user['id'], $id, "$annee-12-31", "$annee-01-01"]
    ));
    $workdays = 0;
    $d = new DateTime($dateDebut);
    $e = new DateTime($dateFin);
    while ($d <= $e) {
        if ((int) $d->format('N') <= 5) $workdays++;
        $d->modify('+1 day');
    }
    if (($joursUtilises + $workdays) > $solde) {
        bad_request("Solde insuffisant. Vous avez " . ($solde - $joursUtilises) . " jour(s) restant(s)");
    }

    Db::exec(
        "UPDATE absences SET date_debut = ?, date_fin = ? WHERE id = ?",
        [$dateDebut, $dateFin, $id]
    );

    respond(['success' => true, 'message' => 'Vacances modifiées']);
}
