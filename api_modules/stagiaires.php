<?php
/**
 * Stagiaires — Employee API
 * - Formateur: voit stagiaires affectés (période active), valide reports, crée évaluations
 * - Stagiaire: voit son profil, rédige reports, voit évaluations reçues
 */

// ─── Pour formateur: liste stagiaires où il est affecté actuellement ────
function get_my_stagiaires_as_formateur()
{
    $user = require_auth();
    $uid = $user['id'];
    $today = date('Y-m-d');

    $rows = Db::fetchAll(
        "SELECT DISTINCT s.id, s.type, s.date_debut, s.date_fin, s.statut,
                u.prenom, u.nom, u.email, u.photo,
                (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
                (SELECT COUNT(*) FROM stagiaire_reports r WHERE r.stagiaire_id = s.id AND r.statut = 'soumis') AS reports_a_valider
         FROM stagiaire_affectations a
         JOIN stagiaires s ON s.id = a.stagiaire_id
         JOIN users u ON u.id = s.user_id
         WHERE a.formateur_id = ?
           AND a.date_debut <= ? AND a.date_fin >= ?
           AND s.statut IN ('prevu','actif')
         ORDER BY s.date_debut DESC",
        [$uid, $today, $today]
    );

    // History (formateur passé)
    $history = Db::fetchAll(
        "SELECT DISTINCT s.id, s.type, s.date_debut, s.date_fin, s.statut,
                u.prenom, u.nom, u.email, u.photo
         FROM stagiaire_affectations a
         JOIN stagiaires s ON s.id = a.stagiaire_id
         JOIN users u ON u.id = s.user_id
         WHERE a.formateur_id = ?
           AND a.date_fin < ?
         ORDER BY a.date_fin DESC LIMIT 20",
        [$uid, $today]
    );

    respond(['success' => true, 'actifs' => $rows, 'history' => $history]);
}

// ─── Détail stagiaire côté formateur (vérifier qu'il est affecté) ──────
function get_stagiaire_view_formateur()
{
    $user = require_auth();
    global $params;
    $uid = $user['id'];
    $stagId = $params['id'] ?? '';
    if (!$stagId) bad_request('ID requis');
    $today = date('Y-m-d');

    // Vérifier droit: formateur affecté (actuellement ou passé)
    $hasAccess = Db::getOne(
        "SELECT COUNT(*) FROM stagiaire_affectations
         WHERE stagiaire_id = ? AND formateur_id = ?",
        [$stagId, $uid]
    );
    if (!$hasAccess) forbidden('Vous n\'êtes pas formateur de ce stagiaire');

    $isActive = (int) Db::getOne(
        "SELECT COUNT(*) FROM stagiaire_affectations
         WHERE stagiaire_id = ? AND formateur_id = ? AND date_debut <= ? AND date_fin >= ?",
        [$stagId, $uid, $today, $today]
    ) > 0;

    $stag = Db::fetch(
        "SELECT s.id, s.type, s.date_debut, s.date_fin, s.statut, s.objectifs_generaux,
                u.prenom, u.nom, u.email, u.photo, u.telephone,
                (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom
         FROM stagiaires s JOIN users u ON u.id = s.user_id WHERE s.id = ?",
        [$stagId]
    );

    $reports = Db::fetchAll(
        "SELECT * FROM stagiaire_reports WHERE stagiaire_id = ?
         ORDER BY date_report DESC LIMIT 60",
        [$stagId]
    );

    $evaluations = Db::fetchAll(
        "SELECT e.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom
         FROM stagiaire_evaluations e
         JOIN users u ON u.id = e.formateur_id
         WHERE e.stagiaire_id = ?
         ORDER BY e.date_eval DESC",
        [$stagId]
    );

    $objectifs = Db::fetchAll(
        "SELECT * FROM stagiaire_objectifs WHERE stagiaire_id = ? ORDER BY created_at DESC",
        [$stagId]
    );

    respond([
        'success' => true,
        'stagiaire' => $stag,
        'reports' => $reports,
        'evaluations' => $evaluations,
        'objectifs' => $objectifs,
        'can_edit' => $isActive,
    ]);
}

function validate_stagiaire_report()
{
    $user = require_auth();
    global $params;
    $uid = $user['id'];
    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? 'valide';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 2000);

    if (!$id) bad_request('ID requis');
    if (!in_array($statut, ['valide','a_refaire'])) $statut = 'valide';

    $rep = Db::fetch("SELECT stagiaire_id FROM stagiaire_reports WHERE id = ?", [$id]);
    if (!$rep) not_found('Report introuvable');

    $today = date('Y-m-d');
    $hasAccess = (int) Db::getOne(
        "SELECT COUNT(*) FROM stagiaire_affectations
         WHERE stagiaire_id = ? AND formateur_id = ? AND date_debut <= ? AND date_fin >= ?",
        [$rep['stagiaire_id'], $uid, $today, $today]
    );
    if (!$hasAccess) forbidden('Pas affecté à ce stagiaire');

    Db::exec(
        "UPDATE stagiaire_reports SET statut = ?, validated_by = ?, validated_at = NOW(), commentaire_formateur = ?
         WHERE id = ?",
        [$statut, $uid, $commentaire, $id]
    );

    Db::exec(
        "INSERT INTO notifications (id, user_id, type, title, message, url, created_at)
         VALUES (?, (SELECT user_id FROM stagiaires WHERE id = ?), 'stagiaire_report', ?, ?, 'mon-stage', NOW())",
        [Uuid::v4(), $rep['stagiaire_id'],
         $statut === 'valide' ? 'Report validé' : 'Report à refaire',
         $commentaire ?: ($statut === 'valide' ? 'Votre report a été validé' : 'Votre formateur demande une correction')]
    );

    respond(['success' => true, 'message' => 'Report mis à jour']);
}

function save_stagiaire_evaluation()
{
    $user = require_auth();
    global $params;
    $uid = $user['id'];

    $id = $params['id'] ?? '';
    $stagId = $params['stagiaire_id'] ?? '';
    $dateEval = Sanitize::date($params['date_eval'] ?? date('Y-m-d'));
    $periode = $params['periode'] ?? 'journaliere';

    if (!$stagId) bad_request('Stagiaire requis');
    if (!in_array($periode, ['journaliere','hebdo','mi_stage','finale'])) $periode = 'journaliere';

    $today = date('Y-m-d');
    $hasAccess = (int) Db::getOne(
        "SELECT COUNT(*) FROM stagiaire_affectations
         WHERE stagiaire_id = ? AND formateur_id = ? AND date_debut <= ? AND date_fin >= ?",
        [$stagId, $uid, $today, $today]
    );
    if (!$hasAccess) forbidden('Pas affecté à ce stagiaire');

    $clamp = fn($v) => is_null($v) || $v === '' ? null : max(1, min(5, (int) $v));
    $data = [
        'note_initiative' => $clamp($params['note_initiative'] ?? null),
        'note_communication' => $clamp($params['note_communication'] ?? null),
        'note_connaissances' => $clamp($params['note_connaissances'] ?? null),
        'note_autonomie' => $clamp($params['note_autonomie'] ?? null),
        'note_savoir_etre' => $clamp($params['note_savoir_etre'] ?? null),
        'note_ponctualite' => $clamp($params['note_ponctualite'] ?? null),
        'points_forts' => Sanitize::text($params['points_forts'] ?? '', 2000),
        'points_amelioration' => Sanitize::text($params['points_amelioration'] ?? '', 2000),
        'commentaire_general' => Sanitize::text($params['commentaire_general'] ?? '', 2000),
    ];

    if ($id) {
        // vérifier propriété
        $own = Db::getOne("SELECT COUNT(*) FROM stagiaire_evaluations WHERE id = ? AND formateur_id = ?", [$id, $uid]);
        if (!$own) forbidden('Pas votre évaluation');
        $set = []; $vals = [];
        foreach ($data as $k => $v) { $set[] = "$k = ?"; $vals[] = $v; }
        $set[] = "date_eval = ?"; $vals[] = $dateEval;
        $set[] = "periode = ?"; $vals[] = $periode;
        $vals[] = $id;
        Db::exec("UPDATE stagiaire_evaluations SET " . implode(', ', $set) . " WHERE id = ?", $vals);
        respond(['success' => true, 'id' => $id, 'message' => 'Évaluation mise à jour']);
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO stagiaire_evaluations (id, stagiaire_id, formateur_id, date_eval, periode,
             note_initiative, note_communication, note_connaissances, note_autonomie, note_savoir_etre, note_ponctualite,
             points_forts, points_amelioration, commentaire_general)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $stagId, $uid, $dateEval, $periode,
             $data['note_initiative'], $data['note_communication'], $data['note_connaissances'],
             $data['note_autonomie'], $data['note_savoir_etre'], $data['note_ponctualite'],
             $data['points_forts'], $data['points_amelioration'], $data['commentaire_general']]
        );
        respond(['success' => true, 'id' => $id, 'message' => 'Évaluation enregistrée']);
    }
}

// ─── Vue stagiaire: son propre profil ──────────────────────────────────
function get_my_stage()
{
    $user = require_auth();
    $uid = $user['id'];

    $stag = Db::fetch(
        "SELECT s.*,
                (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
                (SELECT CONCAT(r.prenom,' ',r.nom) FROM users r WHERE r.id = s.ruv_id) AS ruv_nom,
                (SELECT CONCAT(f.prenom,' ',f.nom) FROM users f WHERE f.id = s.formateur_principal_id) AS formateur_nom,
                (SELECT f.email FROM users f WHERE f.id = s.formateur_principal_id) AS formateur_email
         FROM stagiaires s WHERE s.user_id = ? ORDER BY s.date_debut DESC LIMIT 1",
        [$uid]
    );
    if (!$stag) respond(['success' => true, 'stagiaire' => null]);

    $reports = Db::fetchAll(
        "SELECT r.*, (SELECT CONCAT(u.prenom,' ',u.nom) FROM users u WHERE u.id = r.validated_by) AS valideur_nom
         FROM stagiaire_reports r WHERE r.stagiaire_id = ?
         ORDER BY r.date_report DESC",
        [$stag['id']]
    );

    $evaluations = Db::fetchAll(
        "SELECT e.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom
         FROM stagiaire_evaluations e
         JOIN users u ON u.id = e.formateur_id
         WHERE e.stagiaire_id = ? AND e.periode IN ('mi_stage','finale')
         ORDER BY e.date_eval DESC",
        [$stag['id']]
    );

    $objectifs = Db::fetchAll(
        "SELECT * FROM stagiaire_objectifs WHERE stagiaire_id = ? ORDER BY created_at DESC",
        [$stag['id']]
    );

    $formateurs_actifs = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, a.date_debut, a.date_fin, a.role_formateur
         FROM stagiaire_affectations a
         JOIN users u ON u.id = a.formateur_id
         WHERE a.stagiaire_id = ? AND a.date_fin >= CURDATE()
         ORDER BY a.date_debut",
        [$stag['id']]
    );

    respond([
        'success' => true,
        'stagiaire' => $stag,
        'reports' => $reports,
        'evaluations' => $evaluations,
        'objectifs' => $objectifs,
        'formateurs_actifs' => $formateurs_actifs,
    ]);
}

function save_my_report()
{
    $user = require_auth();
    global $params;
    $uid = $user['id'];

    $stag = Db::fetch("SELECT id FROM stagiaires WHERE user_id = ? ORDER BY date_debut DESC LIMIT 1", [$uid]);
    if (!$stag) forbidden('Vous n\'êtes pas stagiaire');

    $id = $params['id'] ?? '';
    $type = in_array($params['type'] ?? 'quotidien', ['quotidien','hebdo']) ? $params['type'] : 'quotidien';
    $dateReport = Sanitize::date($params['date_report'] ?? date('Y-m-d'));
    $titre = Sanitize::text($params['titre'] ?? '', 200);
    $contenu = Sanitize::text($params['contenu'] ?? '', 10000);
    $action = $params['action'] ?? 'save'; // 'save' = brouillon, 'submit' = soumis

    if (!$contenu) bad_request('Contenu requis');

    $statut = $action === 'submit' ? 'soumis' : 'brouillon';
    $submittedAt = $action === 'submit' ? date('Y-m-d H:i:s') : null;

    if ($id) {
        // vérifier propriété + statut modifiable
        $own = Db::fetch("SELECT statut FROM stagiaire_reports WHERE id = ? AND stagiaire_id = ?", [$id, $stag['id']]);
        if (!$own) forbidden('Pas votre report');
        if ($own['statut'] === 'valide') bad_request('Report déjà validé, non modifiable');
        Db::exec(
            "UPDATE stagiaire_reports SET type=?, date_report=?, titre=?, contenu=?, statut=?, submitted_at=COALESCE(?, submitted_at)
             WHERE id = ?",
            [$type, $dateReport, $titre, $contenu, $statut, $submittedAt, $id]
        );
        respond(['success' => true, 'id' => $id, 'message' => $action === 'submit' ? 'Report soumis' : 'Brouillon enregistré']);
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO stagiaire_reports (id, stagiaire_id, type, date_report, titre, contenu, statut, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $stag['id'], $type, $dateReport, $titre, $contenu, $statut, $submittedAt]
        );
        respond(['success' => true, 'id' => $id, 'message' => $action === 'submit' ? 'Report soumis' : 'Brouillon enregistré']);
    }
}

function delete_my_report()
{
    $user = require_auth();
    global $params;
    $uid = $user['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $row = Db::fetch(
        "SELECT r.statut FROM stagiaire_reports r
         JOIN stagiaires s ON s.id = r.stagiaire_id
         WHERE r.id = ? AND s.user_id = ?",
        [$id, $uid]
    );
    if (!$row) forbidden('Pas votre report');
    if ($row['statut'] !== 'brouillon') bad_request('Seul un brouillon est supprimable');

    Db::exec("DELETE FROM stagiaire_reports WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Brouillon supprimé']);
}
