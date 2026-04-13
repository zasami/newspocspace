<?php
/**
 * SpocSpace — Delta sync for offline-first
 * Syncs all user-relevant data incrementally.
 */

function sync_delta()
{
    $user = require_auth();
    global $params;
    $lastSync = $params['last_sync'] ?? null;
    $uid = $user['id'];
    $mois = date('Y-m');

    $result = ['success' => true, 'timestamp' => date('c')];

    // ── Planning: current month assignations for this user ──
    if ($lastSync) {
        $result['planning'] = Db::fetchAll(
            "SELECT pa.* FROM planning_assignations pa
             JOIN plannings p ON p.id = pa.planning_id
             WHERE pa.user_id = ? AND p.mois_annee = ? AND pa.updated_at > ?",
            [$uid, $mois, $lastSync]
        );
    } else {
        $result['planning'] = Db::fetchAll(
            "SELECT pa.* FROM planning_assignations pa
             JOIN plannings p ON p.id = pa.planning_id
             WHERE pa.user_id = ? AND p.mois_annee = ?",
            [$uid, $mois]
        );
    }

    // ── Messages: last 30 days ──
    $since = $lastSync ?: date('Y-m-d', strtotime('-30 days'));
    $result['messages'] = Db::fetchAll(
        "SELECT m.id, m.sujet, m.contenu, m.from_user_id, m.created_at,
                uf.prenom AS from_prenom, uf.nom AS from_nom
         FROM messages m
         JOIN users uf ON uf.id = m.from_user_id
         WHERE m.is_draft = 0 AND m.created_at > ?
           AND (m.from_user_id = ? OR EXISTS (
               SELECT 1 FROM message_recipients mr WHERE mr.email_id = m.id AND mr.user_id = ?
           ))
         ORDER BY m.created_at DESC LIMIT 100",
        [$since, $uid, $uid]
    );

    // ── Users: active collaborators ──
    if ($lastSync) {
        $result['users'] = Db::fetchAll(
            "SELECT id, prenom, nom, email, role, photo, taux, telephone,
                    (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
             FROM users u WHERE is_active = 1 AND updated_at > ?",
            [$lastSync]
        );
    } else {
        $result['users'] = Db::fetchAll(
            "SELECT id, prenom, nom, email, role, photo, taux, telephone,
                    (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
             FROM users u WHERE is_active = 1"
        );
    }

    // ── Horaires types ──
    $result['horaires'] = Db::fetchAll(
        "SELECT id, code, nom, couleur, heure_debut, heure_fin, duree_effective
         FROM horaires_types WHERE is_active = 1"
    );

    // ── Desirs: current user's desirs ──
    $desirsSince = $lastSync ?: date('Y-m-d', strtotime('-60 days'));
    $result['desirs'] = Db::fetchAll(
        "SELECT * FROM desirs WHERE user_id = ? AND created_at > ?
         ORDER BY date_souhaitee DESC",
        [$uid, $desirsSince]
    );

    // ── Absences: current user's absences ──
    $result['absences'] = Db::fetchAll(
        "SELECT * FROM absences WHERE user_id = ? AND date_fin >= ?
         ORDER BY date_debut DESC",
        [$uid, date('Y-m-d', strtotime('-30 days'))]
    );

    // ── Vacances: current year ──
    $result['vacances'] = Db::fetchAll(
        "SELECT * FROM absences WHERE user_id = ? AND type = 'vacances' AND YEAR(date_debut) >= ?
         ORDER BY date_debut DESC",
        [$uid, date('Y') - 1]
    );

    // ── Notifications: last 30 days ──
    $notifSince = $lastSync ?: date('Y-m-d', strtotime('-30 days'));
    $result['notifications'] = Db::fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? AND created_at > ?
         ORDER BY created_at DESC LIMIT 50",
        [$uid, $notifSince]
    );

    // ── Changements ──
    $changeSince = $lastSync ?: date('Y-m-d', strtotime('-30 days'));
    $result['changements'] = Db::fetchAll(
        "SELECT ch.*,
                u1.prenom AS demandeur_prenom, u1.nom AS demandeur_nom,
                u2.prenom AS destinataire_prenom, u2.nom AS destinataire_nom
         FROM changements_horaire ch
         LEFT JOIN users u1 ON u1.id = ch.demandeur_id
         LEFT JOIN users u2 ON u2.id = ch.destinataire_id
         WHERE (ch.demandeur_id = ? OR ch.destinataire_id = ?) AND ch.updated_at > ?
         ORDER BY ch.created_at DESC LIMIT 50",
        [$uid, $uid, $changeSince]
    );

    // ── Annonces: published, last 60 days ──
    $annonceSince = $lastSync ?: date('Y-m-d', strtotime('-60 days'));
    $result['annonces'] = Db::fetchAll(
        "SELECT a.id, a.titre, a.description, a.categorie, a.priorite, a.created_at, a.updated_at,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users u ON u.id = a.created_by
         WHERE a.is_published = 1 AND a.created_at > ?
         ORDER BY a.created_at DESC LIMIT 30",
        [$annonceSince]
    );

    // ── Documents: list (metadata only, not files) ──
    $result['documents'] = Db::fetchAll(
        "SELECT d.id, d.titre, d.description, d.service_id, d.file_path, d.file_size, d.mime_type, d.created_at
         FROM documents d WHERE d.is_active = 1
         ORDER BY d.created_at DESC LIMIT 100"
    );

    // ── Votes: open proposals ──
    $result['votes'] = Db::fetchAll(
        "SELECT pp.*,
                (SELECT COUNT(*) FROM planning_votes pv WHERE pv.proposal_id = pp.id AND pv.user_id = ?) AS user_voted
         FROM planning_proposals pp
         WHERE pp.status = 'ouvert'
         ORDER BY pp.created_at DESC",
        [$uid]
    );

    // ── Sondages: open surveys ──
    $result['sondages'] = Db::fetchAll(
        "SELECT s.*,
                (SELECT COUNT(*) FROM sondage_reponses sr WHERE sr.sondage_id = s.id AND sr.user_id = ?) AS user_replied
         FROM sondages s
         WHERE s.status = 'ouvert'
         ORDER BY s.created_at DESC",
        [$uid]
    );

    // ── PV: last 6 months ──
    $pvSince = $lastSync ?: date('Y-m-d', strtotime('-180 days'));
    $result['pv'] = Db::fetchAll(
        "SELECT pv.id, pv.titre, pv.date_reunion, pv.statut, pv.resume, pv.created_at, pv.updated_at
         FROM pv WHERE pv.created_at > ?
         ORDER BY pv.date_reunion DESC LIMIT 30",
        [$pvSince]
    );

    // ── Wiki: categories + pages (metadata) ──
    $result['wiki_categories'] = Db::fetchAll(
        "SELECT * FROM wiki_categories ORDER BY ordre, nom"
    );
    if ($lastSync) {
        $result['wiki_pages'] = Db::fetchAll(
            "SELECT id, category_id, titre, slug, resume, auteur_id, is_published, created_at, updated_at
             FROM wiki_pages WHERE updated_at > ? AND is_published = 1
             ORDER BY updated_at DESC",
            [$lastSync]
        );
    } else {
        $result['wiki_pages'] = Db::fetchAll(
            "SELECT id, category_id, titre, slug, resume, auteur_id, is_published, created_at, updated_at
             FROM wiki_pages WHERE is_published = 1
             ORDER BY updated_at DESC LIMIT 100"
        );
    }

    // ── Mur social: recent posts ──
    $murSince = $lastSync ?: date('Y-m-d', strtotime('-14 days'));
    $result['mur'] = Db::fetchAll(
        "SELECT mp.*, u.prenom, u.nom, u.photo,
                (SELECT COUNT(*) FROM mur_likes ml WHERE ml.post_id = mp.id) AS like_count,
                (SELECT COUNT(*) FROM mur_likes ml WHERE ml.post_id = mp.id AND ml.user_id = ?) AS user_liked,
                (SELECT COUNT(*) FROM mur_comments mc WHERE mc.post_id = mp.id) AS comment_count
         FROM mur_posts mp
         LEFT JOIN users u ON u.id = mp.user_id
         WHERE mp.created_at > ?
         ORDER BY mp.created_at DESC LIMIT 50",
        [$uid, $murSince]
    );

    // ── Collegues: active users basic info ──
    // Only full sync, not delta (small dataset)
    if (!$lastSync) {
        $result['collegues'] = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, u.email, u.photo, u.taux, u.telephone,
                    (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
             FROM users u WHERE u.is_active = 1 ORDER BY u.nom, u.prenom"
        );
    }

    // ── Covoiturage ──
    $result['covoiturage'] = Db::fetchAll(
        "SELECT cb.*, u.prenom, u.nom, u.photo
         FROM covoiturage_buddies cb
         LEFT JOIN users u ON u.id = cb.buddy_id
         WHERE cb.user_id = ?",
        [$uid]
    );

    // ── Cuisine menus: current + next week ──
    $result['cuisine_menus'] = Db::fetchAll(
        "SELECT * FROM menus WHERE date_menu BETWEEN ? AND ?
         ORDER BY date_menu",
        [date('Y-m-d'), date('Y-m-d', strtotime('+14 days'))]
    );

    // ── Fiches salaire: metadata (not files) ──
    $result['fiches_salaire'] = Db::fetchAll(
        "SELECT id, mois, annee, file_path, created_at FROM fiches_salaire
         WHERE user_id = ? ORDER BY annee DESC, mois DESC LIMIT 24",
        [$uid]
    );

    // ── Conflict detection: send server timestamps for queued items ──
    $result['_server_time'] = date('c');

    respond($result);
}
