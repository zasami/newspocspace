<?php
/**
 * SpocSpace — Delta sync for offline-first
 */

function sync_delta()
{
    $user = require_auth();
    global $params;
    $lastSync = $params['last_sync'] ?? null;
    $uid = $user['id'];
    $mois = date('Y-m');

    $result = ['success' => true, 'timestamp' => date('c')];

    // Planning: current month assignations for this user
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

    // Messages: last 30 days
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

    // Users: active collaborators
    if ($lastSync) {
        $result['users'] = Db::fetchAll(
            "SELECT id, prenom, nom, email, role, photo, taux,
                    (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
             FROM users u WHERE is_active = 1 AND updated_at > ?",
            [$lastSync]
        );
    } else {
        $result['users'] = Db::fetchAll(
            "SELECT id, prenom, nom, email, role, photo, taux,
                    (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
             FROM users u WHERE is_active = 1"
        );
    }

    // Horaires types
    $result['horaires'] = Db::fetchAll(
        "SELECT id, code, nom, couleur, heure_debut, heure_fin, duree_effective
         FROM horaires_types WHERE is_active = 1"
    );

    respond($result);
}
