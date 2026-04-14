<?php
/**
 * Stagiaires — Admin API
 * CRUD + affectations formateurs + reports + évaluations + objectifs
 */

// ─── List ────────────────────────────────────────────────────────────────
function admin_get_stagiaires()
{
    require_admin();
    global $params;
    $statut = $params['statut'] ?? null;
    $etageId = $params['etage_id'] ?? null;
    $ruvId = $params['ruv_id'] ?? null;

    $sql = "SELECT s.*,
                   u.prenom, u.nom, u.email, u.photo,
                   (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
                   (SELECT CONCAT(r.prenom, ' ', r.nom) FROM users r WHERE r.id = s.ruv_id) AS ruv_nom,
                   (SELECT CONCAT(f.prenom, ' ', f.nom) FROM users f WHERE f.id = s.formateur_principal_id) AS formateur_nom,
                   (SELECT COUNT(*) FROM stagiaire_reports sr WHERE sr.stagiaire_id = s.id AND sr.statut = 'soumis') AS reports_a_valider,
                   (SELECT COUNT(*) FROM stagiaire_reports sr WHERE sr.stagiaire_id = s.id) AS reports_total,
                   (SELECT COUNT(*) FROM stagiaire_evaluations se WHERE se.stagiaire_id = s.id) AS evals_total
            FROM stagiaires s
            JOIN users u ON u.id = s.user_id
            WHERE 1=1";
    $args = [];
    if ($statut) { $sql .= " AND s.statut = ?"; $args[] = $statut; }
    if ($etageId) { $sql .= " AND s.etage_id = ?"; $args[] = $etageId; }
    if ($ruvId) { $sql .= " AND s.ruv_id = ?"; $args[] = $ruvId; }
    $sql .= " ORDER BY s.statut, s.date_debut DESC";

    $rows = Db::fetchAll($sql, $args);

    $stats = [
        'total'      => (int) Db::getOne("SELECT COUNT(*) FROM stagiaires"),
        'actif'      => (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'actif'"),
        'prevu'      => (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'prevu'"),
        'termine'    => (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'termine'"),
    ];

    respond(['success' => true, 'stagiaires' => $rows, 'stats' => $stats]);
}

// ─── Refs (listes déroulantes) ───────────────────────────────────────────
function admin_get_stagiaires_refs()
{
    require_admin();
    $formateurs = Db::fetchAll(
        "SELECT id, prenom, nom, email,
                (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
         FROM users u WHERE is_active = 1 AND role IN ('collaborateur','responsable','admin','direction')
         ORDER BY nom, prenom"
    );
    $etages = Db::fetchAll("SELECT id, nom, code FROM etages ORDER BY ordre, nom");
    $ruvs = Db::fetchAll(
        "SELECT id, prenom, nom FROM users
         WHERE is_active = 1 AND role IN ('responsable','admin','direction')
         ORDER BY nom, prenom"
    );
    respond(['success' => true, 'formateurs' => $formateurs, 'etages' => $etages, 'ruvs' => $ruvs]);
}

// ─── Detail ──────────────────────────────────────────────────────────────
function admin_get_stagiaire_detail()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $stag = Db::fetch(
        "SELECT s.*, u.prenom, u.nom, u.email, u.photo, u.telephone,
                (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
                (SELECT CONCAT(r.prenom,' ',r.nom) FROM users r WHERE r.id = s.ruv_id) AS ruv_nom,
                (SELECT CONCAT(f.prenom,' ',f.nom) FROM users f WHERE f.id = s.formateur_principal_id) AS formateur_nom
         FROM stagiaires s JOIN users u ON u.id = s.user_id WHERE s.id = ?",
        [$id]
    );
    if (!$stag) not_found('Stagiaire introuvable');

    $affectations = Db::fetchAll(
        "SELECT a.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom,
                (SELECT e.nom FROM etages e WHERE e.id = a.etage_id) AS etage_nom
         FROM stagiaire_affectations a
         JOIN users u ON u.id = a.formateur_id
         WHERE a.stagiaire_id = ? ORDER BY a.date_debut DESC",
        [$id]
    );

    $reports = Db::fetchAll(
        "SELECT r.*, (SELECT CONCAT(u.prenom,' ',u.nom) FROM users u WHERE u.id = r.validated_by) AS valideur_nom
         FROM stagiaire_reports r WHERE r.stagiaire_id = ?
         ORDER BY r.date_report DESC",
        [$id]
    );
    foreach ($reports as &$rep) {
        $rep['taches'] = Db::fetchAll(
            "SELECT rt.*, c.nom AS tache_nom, c.categorie, c.code
             FROM stagiaire_report_taches rt
             JOIN stagiaire_taches_catalogue c ON c.id = rt.tache_id
             WHERE rt.report_id = ?",
            [$rep['id']]
        );
    }
    unset($rep);

    $evaluations = Db::fetchAll(
        "SELECT e.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom
         FROM stagiaire_evaluations e
         JOIN users u ON u.id = e.formateur_id
         WHERE e.stagiaire_id = ? ORDER BY e.date_eval DESC",
        [$id]
    );

    $objectifs = Db::fetchAll(
        "SELECT * FROM stagiaire_objectifs WHERE stagiaire_id = ? ORDER BY created_at DESC",
        [$id]
    );

    // Moyenne des notes
    $moyennes = Db::fetch(
        "SELECT
            AVG(note_initiative) AS initiative,
            AVG(note_communication) AS communication,
            AVG(note_connaissances) AS connaissances,
            AVG(note_autonomie) AS autonomie,
            AVG(note_savoir_etre) AS savoir_etre,
            AVG(note_ponctualite) AS ponctualite,
            COUNT(*) AS n
         FROM stagiaire_evaluations WHERE stagiaire_id = ?",
        [$id]
    );

    respond([
        'success' => true,
        'stagiaire' => $stag,
        'affectations' => $affectations,
        'reports' => $reports,
        'evaluations' => $evaluations,
        'objectifs' => $objectifs,
        'moyennes' => $moyennes,
    ]);
}

// ─── Create / Update ─────────────────────────────────────────────────────
function admin_save_stagiaire()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;

    $id = $params['id'] ?? '';
    $userId = $params['user_id'] ?? '';
    $type = $params['type'] ?? 'autre';
    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');

    if (!$userId) bad_request('Utilisateur requis');
    if (!$dateDebut || !$dateFin) bad_request('Dates requises');
    if ($dateFin < $dateDebut) bad_request('Date fin avant date début');

    // Force user role = stagiaire
    Db::exec("UPDATE users SET role = 'stagiaire' WHERE id = ? AND role != 'stagiaire'", [$userId]);

    $data = [
        'user_id' => $userId,
        'type' => in_array($type, ['decouverte','cfc_asa','cfc_ase','cfc_asfm','bachelor_inf','civiliste','autre']) ? $type : 'autre',
        'etablissement_origine' => Sanitize::text($params['etablissement_origine'] ?? '', 200),
        'niveau' => Sanitize::text($params['niveau'] ?? '', 80),
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin,
        'etage_id' => $params['etage_id'] ?? null,
        'ruv_id' => $params['ruv_id'] ?? null,
        'formateur_principal_id' => $params['formateur_principal_id'] ?? null,
        'objectifs_generaux' => Sanitize::text($params['objectifs_generaux'] ?? '', 2000),
        'statut' => in_array($params['statut'] ?? 'prevu', ['prevu','actif','termine','interrompu']) ? $params['statut'] : 'prevu',
        'notes_ruv' => Sanitize::text($params['notes_ruv'] ?? '', 2000),
    ];

    if ($id) {
        $set = [];
        $vals = [];
        foreach ($data as $k => $v) { $set[] = "$k = ?"; $vals[] = $v; }
        $vals[] = $id;
        Db::exec("UPDATE stagiaires SET " . implode(', ', $set) . " WHERE id = ?", $vals);
        respond(['success' => true, 'id' => $id, 'message' => 'Stagiaire mis à jour']);
    } else {
        $id = Uuid::v4();
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        Db::exec(
            "INSERT INTO stagiaires (id, " . implode(',', $cols) . ", created_by) VALUES (?, " . implode(',', $placeholders) . ", ?)",
            [$id, ...array_values($data), $admin['id'] ?? null]
        );
        // Affectation principale automatique si formateur défini
        if (!empty($data['formateur_principal_id'])) {
            Db::exec(
                "INSERT INTO stagiaire_affectations (id, stagiaire_id, formateur_id, etage_id, date_debut, date_fin, role_formateur, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'principal', ?)",
                [Uuid::v4(), $id, $data['formateur_principal_id'], $data['etage_id'], $dateDebut, $dateFin, $admin['id'] ?? null]
            );
        }
        respond(['success' => true, 'id' => $id, 'message' => 'Stagiaire créé']);
    }
}

function admin_delete_stagiaire()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM stagiaires WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Stagiaire supprimé']);
}

// ─── Affectations ────────────────────────────────────────────────────────
function admin_add_stagiaire_affectation()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;

    $stagId = $params['stagiaire_id'] ?? '';
    $formId = $params['formateur_id'] ?? '';
    $dd = Sanitize::date($params['date_debut'] ?? '');
    $df = Sanitize::date($params['date_fin'] ?? '');
    $role = $params['role_formateur'] ?? 'ponctuel';

    if (!$stagId || !$formId || !$dd || !$df) bad_request('Champs requis manquants');
    if (!in_array($role, ['principal','remplacant','ponctuel'])) $role = 'ponctuel';

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO stagiaire_affectations (id, stagiaire_id, formateur_id, etage_id, date_debut, date_fin, role_formateur, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $stagId, $formId, $params['etage_id'] ?? null, $dd, $df, $role,
         Sanitize::text($params['notes'] ?? '', 255), $admin['id'] ?? null]
    );
    respond(['success' => true, 'id' => $id, 'message' => 'Formateur affecté']);
}

function admin_delete_stagiaire_affectation()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM stagiaire_affectations WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Affectation supprimée']);
}

// ─── Reports (vue RUV) ───────────────────────────────────────────────────
function admin_validate_stagiaire_report()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;
    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? 'valide';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 2000);

    if (!$id) bad_request('ID requis');
    if (!in_array($statut, ['valide','a_refaire'])) $statut = 'valide';

    Db::exec(
        "UPDATE stagiaire_reports SET statut = ?, validated_by = ?, validated_at = NOW(), commentaire_formateur = ?
         WHERE id = ?",
        [$statut, $admin['id'] ?? null, $commentaire, $id]
    );
    respond(['success' => true, 'message' => 'Report mis à jour']);
}

// ─── Objectifs ───────────────────────────────────────────────────────────
function admin_save_stagiaire_objectif()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;

    $id = $params['id'] ?? '';
    $stagId = $params['stagiaire_id'] ?? '';
    $titre = Sanitize::text($params['titre'] ?? '', 200);
    $description = Sanitize::text($params['description'] ?? '', 2000);
    $dateCible = Sanitize::date($params['date_cible'] ?? '') ?: null;
    $statut = $params['statut'] ?? 'en_cours';
    $commentaire = Sanitize::text($params['commentaire_ruv'] ?? '', 1000);

    if (!$titre) bad_request('Titre requis');
    if (!in_array($statut, ['en_cours','atteint','non_atteint','abandonne'])) $statut = 'en_cours';

    if ($id) {
        Db::exec(
            "UPDATE stagiaire_objectifs SET titre=?, description=?, date_cible=?, statut=?, commentaire_ruv=?
             WHERE id = ?",
            [$titre, $description, $dateCible, $statut, $commentaire, $id]
        );
        respond(['success' => true, 'id' => $id, 'message' => 'Objectif mis à jour']);
    } else {
        if (!$stagId) bad_request('Stagiaire requis');
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO stagiaire_objectifs (id, stagiaire_id, titre, description, date_cible, statut, commentaire_ruv, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $stagId, $titre, $description, $dateCible, $statut, $commentaire, $admin['id'] ?? null]
        );
        respond(['success' => true, 'id' => $id, 'message' => 'Objectif créé']);
    }
}

function admin_delete_stagiaire_objectif()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM stagiaire_objectifs WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Objectif supprimé']);
}

// ─── Dashboard RUV (reports à valider toutes périmètres) ────────────────
function admin_get_stagiaires_dashboard()
{
    require_admin();
    $reportsPending = Db::fetchAll(
        "SELECT r.id, r.date_report, r.titre, r.contenu, r.statut, r.submitted_at,
                s.id AS stagiaire_id, u.prenom, u.nom
         FROM stagiaire_reports r
         JOIN stagiaires s ON s.id = r.stagiaire_id
         JOIN users u ON u.id = s.user_id
         WHERE r.statut = 'soumis'
         ORDER BY r.submitted_at ASC LIMIT 50"
    );
    respond(['success' => true, 'reports_pending' => $reportsPending]);
}
