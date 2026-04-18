<?php
/**
 * Admissions — API admin
 * Gestion des demandes d'admission soumises via le formulaire public
 */

function admin_get_admissions()
{
    require_auth();
    global $params;

    $statut = Sanitize::text($params['statut'] ?? '', 50);
    $search = Sanitize::text($params['search'] ?? '', 200);

    $where = ['1=1'];
    $binds = [];

    if ($statut) {
        $where[] = 'statut = ?';
        $binds[] = $statut;
    }
    if ($search) {
        $where[] = '(nom_prenom LIKE ? OR ref_nom_prenom LIKE ? OR ref_email LIKE ?)';
        $s = "%$search%";
        $binds[] = $s;
        $binds[] = $s;
        $binds[] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $rows = Db::fetchAll(
        "SELECT id, nom_prenom, date_naissance, type_demande, situation,
                ref_nom_prenom, ref_email, ref_telephone,
                statut, created_at, updated_at
         FROM admissions_candidats
         WHERE $whereSql
         ORDER BY
           CASE type_demande WHEN 'urgente' THEN 0 ELSE 1 END,
           created_at DESC",
        $binds
    );

    $stats = Db::fetch(
        "SELECT
           COUNT(*) AS total,
           SUM(statut = 'demande_envoyee') AS en_attente,
           SUM(statut = 'en_examen') AS en_examen,
           SUM(statut = 'etape1_validee') AS validees,
           SUM(statut = 'info_manquante') AS info_manquante,
           SUM(statut = 'refuse') AS refusees,
           SUM(statut = 'acceptee_liste_attente') AS acceptees,
           SUM(type_demande = 'urgente' AND statut IN ('demande_envoyee','en_examen')) AS urgentes_actives
         FROM admissions_candidats"
    );

    respond(['success' => true, 'admissions' => $rows, 'stats' => $stats]);
}

function admin_get_admission()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $candidat = Db::fetch("SELECT * FROM admissions_candidats WHERE id = ?", [$id]);
    if (!$candidat) not_found('Admission introuvable');

    $historique = Db::fetchAll(
        "SELECT h.*, u.prenom AS admin_prenom, u.nom AS admin_nom
         FROM admissions_historique h
         LEFT JOIN users u ON u.id = h.by_admin_id
         WHERE h.candidat_id = ?
         ORDER BY h.created_at DESC",
        [$id]
    );

    respond(['success' => true, 'candidat' => $candidat, 'historique' => $historique]);
}

function admin_update_admission_status()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    $newStatut = $params['statut'] ?? '';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 2000);

    $allowed = ['demande_envoyee', 'en_examen', 'etape1_validee', 'info_manquante', 'refuse', 'acceptee_liste_attente'];
    if (!in_array($newStatut, $allowed, true)) bad_request('Statut invalide');

    $candidat = Db::fetch("SELECT statut FROM admissions_candidats WHERE id = ?", [$id]);
    if (!$candidat) not_found('Admission introuvable');

    $oldStatut = $candidat['statut'];

    Db::exec("UPDATE admissions_candidats SET statut = ? WHERE id = ?", [$newStatut, $id]);

    Db::exec(
        "INSERT INTO admissions_historique (id, candidat_id, action, from_status, to_status, commentaire, by_admin_id)
         VALUES (?, ?, 'changement_statut', ?, ?, ?, ?)",
        [Uuid::v4(), $id, $oldStatut, $newStatut, $commentaire ?: null, $_SESSION['ss_user']['id'] ?? null]
    );

    respond(['success' => true]);
}

function admin_update_admission_note()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    $note = $params['note_interne'] ?? '';

    if (!$id) bad_request('ID requis');

    $exists = Db::fetch("SELECT id FROM admissions_candidats WHERE id = ?", [$id]);
    if (!$exists) not_found('Admission introuvable');

    Db::exec("UPDATE admissions_candidats SET note_interne = ? WHERE id = ?", [$note, $id]);

    Db::exec(
        "INSERT INTO admissions_historique (id, candidat_id, action, commentaire, by_admin_id)
         VALUES (?, ?, 'note_interne', 'Note interne mise à jour', ?)",
        [Uuid::v4(), $id, $_SESSION['ss_user']['id'] ?? null]
    );

    respond(['success' => true]);
}

function admin_delete_admission()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM admissions_candidats WHERE id = ?", [$id]);

    respond(['success' => true]);
}
