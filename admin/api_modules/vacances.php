<?php
/**
 * Admin — Vacances: validation des demandes + périodes bloquées
 */
require_once __DIR__ . '/../../core/Notification.php';

/**
 * Get all vacation requests with filters
 */
function admin_get_vacances()
{
    global $params;
    require_responsable();

    $statut = $params['statut'] ?? '';
    $annee = intval($params['annee'] ?? date('Y'));
    $moduleId = $params['module_id'] ?? '';

    $where = ["a.type = 'vacances'"];
    $binds = [];

    if ($statut && in_array($statut, ['en_attente', 'valide', 'refuse'])) {
        $where[] = "a.statut = ?";
        $binds[] = $statut;
    }

    if ($annee) {
        $where[] = "a.date_debut <= ?";
        $where[] = "a.date_fin >= ?";
        $binds[] = "$annee-12-31";
        $binds[] = "$annee-01-01";
    }

    if ($moduleId) {
        $where[] = "um.module_id = ?";
        $binds[] = $moduleId;
    }

    $sql = "SELECT a.id, a.user_id, a.date_debut, a.date_fin, a.statut, a.motif,
                   a.valide_par, a.valide_at, a.created_at,
                   u.prenom, u.nom, u.taux, u.photo,
                   f.code AS fonction_code,
                   m.code AS module_code, m.nom AS module_nom,
                   v.prenom AS valide_prenom, v.nom AS valide_nom
            FROM absences a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
            LEFT JOIN modules m ON m.id = um.module_id
            LEFT JOIN users v ON v.id = a.valide_par
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.statut = 'en_attente' DESC, a.date_debut DESC";

    $vacances = Db::fetchAll($sql, $binds);

    // Compute workdays for each request
    foreach ($vacances as &$vac) {
        $workdays = 0;
        $d = new DateTime($vac['date_debut']);
        $e = new DateTime($vac['date_fin']);
        while ($d <= $e) {
            if ((int)$d->format('N') <= 5) $workdays++;
            $d->modify('+1 day');
        }
        $vac['jours_ouvres'] = $workdays;
    }
    unset($vac);

    $modules = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre");

    respond(['success' => true, 'vacances' => $vacances, 'modules' => $modules]);
}

/**
 * Validate or refuse a vacation request
 */
function admin_validate_vacances()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';

    if (!$id || !in_array($statut, ['valide', 'refuse'])) {
        bad_request('ID et statut requis');
    }

    $absence = Db::fetch("SELECT * FROM absences WHERE id = ? AND type = 'vacances'", [$id]);
    if (!$absence) bad_request('Demande introuvable');

    Db::exec(
        "UPDATE absences SET statut = ?, valide_par = ?, valide_at = NOW() WHERE id = ?",
        [$statut, $_SESSION['zt_user']['id'] ?? $_SESSION['admin']['id'] ?? null, $id]
    );

    $label = $statut === 'valide' ? 'validée' : 'refusée';

    // Notify user
    $type = $statut === 'valide' ? 'vacances_valide' : 'vacances_refuse';
    $title = $statut === 'valide' ? 'Vacances validées' : 'Vacances refusées';
    $msg = "Votre demande du {$absence['date_debut']} au {$absence['date_fin']} a été $label.";
    Notification::create($absence['user_id'], $type, $title, $msg, 'vacances');

    respond(['success' => true, 'message' => "Demande $label"]);
}

/**
 * Bulk validate/refuse vacation requests
 */
function admin_bulk_validate_vacances()
{
    global $params;
    require_responsable();

    $ids = $params['ids'] ?? [];
    $statut = $params['statut'] ?? '';

    if (empty($ids) || !in_array($statut, ['valide', 'refuse'])) {
        bad_request('IDs et statut requis');
    }

    $adminId = $_SESSION['zt_user']['id'] ?? $_SESSION['admin']['id'] ?? null;
    $count = 0;
    $type = $statut === 'valide' ? 'vacances_valide' : 'vacances_refuse';
    $title = $statut === 'valide' ? 'Vacances validées' : 'Vacances refusées';
    foreach ($ids as $id) {
        $absence = Db::fetch("SELECT * FROM absences WHERE id = ? AND type = 'vacances' AND statut = 'en_attente'", [$id]);
        if ($absence) {
            Db::exec("UPDATE absences SET statut = ?, valide_par = ?, valide_at = NOW() WHERE id = ?", [$statut, $adminId, $id]);
            $label = $statut === 'valide' ? 'validée' : 'refusée';
            Notification::create($absence['user_id'], $type, $title, "Votre demande du {$absence['date_debut']} au {$absence['date_fin']} a été $label.", 'vacances');
            $count++;
        }
    }

    $label = $statut === 'valide' ? 'validée(s)' : 'refusée(s)';
    respond(['success' => true, 'message' => "$count demande(s) $label"]);
}

// ═══════════════════════════════════════════════
// Périodes bloquées
// ═══════════════════════════════════════════════

function admin_get_periodes_bloquees()
{
    global $params;
    require_responsable();

    $annee = intval($params['annee'] ?? date('Y'));
    $debut = "$annee-01-01";
    $fin = "$annee-12-31";

    $periodes = Db::fetchAll(
        "SELECT pb.*, u.prenom AS created_by_prenom, u.nom AS created_by_nom
         FROM periodes_bloquees pb
         LEFT JOIN users u ON u.id = pb.created_by
         WHERE pb.date_debut <= ? AND pb.date_fin >= ?
         ORDER BY pb.date_debut",
        [$fin, $debut]
    );

    respond(['success' => true, 'periodes' => $periodes]);
}

function admin_add_periode_bloquee()
{
    global $params;
    require_responsable();

    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');
    $motif = Sanitize::text($params['motif'] ?? '', 255);

    if (!$dateDebut || !$dateFin) bad_request('Dates requises');
    if ($dateFin < $dateDebut) bad_request('La date de fin doit être après la date de début');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO periodes_bloquees (id, date_debut, date_fin, motif, created_by) VALUES (?, ?, ?, ?, ?)",
        [$id, $dateDebut, $dateFin, $motif, $_SESSION['zt_user']['id'] ?? null]
    );

    respond(['success' => true, 'message' => 'Période bloquée ajoutée', 'id' => $id]);
}

function admin_update_periode_bloquee()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');
    $motif = Sanitize::text($params['motif'] ?? '', 255);

    if (!$dateDebut || !$dateFin) bad_request('Dates requises');
    if ($dateFin < $dateDebut) bad_request('La date de fin doit être après la date de début');

    Db::exec(
        "UPDATE periodes_bloquees SET date_debut = ?, date_fin = ?, motif = ? WHERE id = ?",
        [$dateDebut, $dateFin, $motif, $id]
    );

    respond(['success' => true, 'message' => 'Période modifiée']);
}

function admin_delete_periode_bloquee()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM periodes_bloquees WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Période supprimée']);
}
