<?php
/**
 * WebRTC calls — signaling API
 *
 * Flow :
 *   A → call_invite  (offre SDP)      → DB status=ringing
 *   B → call_poll    (scrute toutes les 2s les appels entrants)
 *   B → call_accept  (réponse SDP)    → DB status=accepted
 *   A/B → call_ice   (candidats ICE pendant la négociation)
 *   A → call_ice_poll (récupère les candidats de l'autre)
 *   A/B → call_end   (raccroche)      → DB status=ended
 *   B → call_reject  (refuse)         → DB status=rejected
 */

function call_invite()
{
    $user = require_auth();
    global $params;
    $toUserId = $params['to_user_id'] ?? '';
    $sdpOffer = $params['sdp_offer'] ?? '';
    $media = in_array($params['media'] ?? 'audio', ['audio', 'video']) ? $params['media'] : 'audio';

    if (!$toUserId || !$sdpOffer) bad_request('Paramètres manquants');
    if ($toUserId === $user['id']) bad_request('Impossible de s\'appeler soi-même');

    // Verify recipient exists and is active
    $to = Db::fetch("SELECT id, prenom, nom, photo FROM users WHERE id = ? AND is_active = 1", [$toUserId]);
    if (!$to) not_found('Utilisateur non trouvé');

    // Cancel any previous ringing call from this user (cleanup)
    Db::exec(
        "UPDATE calls SET status = 'ended', ended_at = NOW()
         WHERE from_user_id = ? AND status = 'ringing'",
        [$user['id']]
    );

    // Mark missed any old ringing call addressed to recipient older than 40s
    Db::exec(
        "UPDATE calls SET status = 'missed', ended_at = NOW()
         WHERE status = 'ringing' AND started_at < DATE_SUB(NOW(), INTERVAL 40 SECOND)"
    );

    $callId = Uuid::v4();
    Db::exec(
        "INSERT INTO calls (id, from_user_id, to_user_id, status, media, sdp_offer)
         VALUES (?, ?, ?, 'ringing', ?, ?)",
        [$callId, $user['id'], $toUserId, $media, $sdpOffer]
    );

    respond(['success' => true, 'call_id' => $callId]);
}

function call_poll()
{
    $user = require_auth();

    // Heartbeat: mark user as online
    Db::exec("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$user['id']]);

    // Auto-expire old ringing calls (40s timeout)
    Db::exec(
        "UPDATE calls SET status = 'missed', ended_at = NOW()
         WHERE to_user_id = ? AND status = 'ringing' AND started_at < DATE_SUB(NOW(), INTERVAL 40 SECOND)",
        [$user['id']]
    );

    // Incoming calls (others calling me)
    $incoming = Db::fetchAll(
        "SELECT c.id, c.from_user_id, c.media, c.sdp_offer, c.started_at,
                u.prenom, u.nom, u.photo, u.fonction_id,
                (SELECT f.nom FROM fonctions f WHERE f.id = u.fonction_id) AS fonction_nom
         FROM calls c
         JOIN users u ON u.id = c.from_user_id
         WHERE c.to_user_id = ? AND c.status = 'ringing'
         ORDER BY c.started_at DESC LIMIT 1",
        [$user['id']]
    );

    // Status of my outgoing calls (to detect accept/reject)
    $outgoing = Db::fetchAll(
        "SELECT id, to_user_id, status, sdp_answer, ended_at
         FROM calls
         WHERE from_user_id = ? AND status IN ('accepted', 'rejected', 'ended', 'missed')
           AND started_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
         ORDER BY started_at DESC LIMIT 5",
        [$user['id']]
    );

    respond([
        'success' => true,
        'incoming' => $incoming,
        'outgoing' => $outgoing,
    ]);
}

function call_accept()
{
    $user = require_auth();
    global $params;
    $callId = $params['call_id'] ?? '';
    $sdpAnswer = $params['sdp_answer'] ?? '';
    if (!$callId || !$sdpAnswer) bad_request('Paramètres manquants');

    $call = Db::fetch("SELECT * FROM calls WHERE id = ? AND to_user_id = ? AND status = 'ringing'", [$callId, $user['id']]);
    if (!$call) not_found('Appel non trouvé ou déjà traité');

    Db::exec(
        "UPDATE calls SET status = 'accepted', sdp_answer = ?, answered_at = NOW() WHERE id = ?",
        [$sdpAnswer, $callId]
    );
    respond(['success' => true]);
}

function call_reject()
{
    $user = require_auth();
    global $params;
    $callId = $params['call_id'] ?? '';
    if (!$callId) bad_request('ID requis');

    Db::exec(
        "UPDATE calls SET status = 'rejected', ended_at = NOW()
         WHERE id = ? AND to_user_id = ? AND status = 'ringing'",
        [$callId, $user['id']]
    );
    respond(['success' => true]);
}

function call_end()
{
    $user = require_auth();
    global $params;
    $callId = $params['call_id'] ?? '';
    if (!$callId) bad_request('ID requis');

    // Must be either caller or callee
    $call = Db::fetch(
        "SELECT * FROM calls WHERE id = ? AND (from_user_id = ? OR to_user_id = ?)",
        [$callId, $user['id'], $user['id']]
    );
    if (!$call) not_found('Appel non trouvé');
    if (in_array($call['status'], ['ended', 'rejected', 'missed'])) {
        respond(['success' => true, 'already_ended' => true]);
    }

    $duration = null;
    if ($call['answered_at']) {
        $duration = time() - strtotime($call['answered_at']);
    }

    Db::exec(
        "UPDATE calls SET status = 'ended', ended_at = NOW(), duration_sec = ? WHERE id = ?",
        [$duration, $callId]
    );
    respond(['success' => true]);
}

function call_ice()
{
    $user = require_auth();
    global $params;
    $callId = $params['call_id'] ?? '';
    $candidate = $params['candidate'] ?? '';
    if (!$callId || !$candidate) bad_request('Paramètres manquants');

    // Verify user is part of the call
    $ok = Db::getOne(
        "SELECT COUNT(*) FROM calls WHERE id = ? AND (from_user_id = ? OR to_user_id = ?)",
        [$callId, $user['id'], $user['id']]
    );
    if (!$ok) forbidden('Pas autorisé');

    Db::exec(
        "INSERT INTO call_ice (call_id, from_user_id, candidate) VALUES (?, ?, ?)",
        [$callId, $user['id'], $candidate]
    );
    respond(['success' => true]);
}

function call_ice_poll()
{
    $user = require_auth();
    global $params;
    $callId = $params['call_id'] ?? '';
    if (!$callId) bad_request('ID requis');

    // Get candidates from the OTHER party (not mine), unconsumed
    $rows = Db::fetchAll(
        "SELECT id, candidate FROM call_ice
         WHERE call_id = ? AND from_user_id != ? AND consumed = 0
         ORDER BY id ASC LIMIT 20",
        [$callId, $user['id']]
    );

    if ($rows) {
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Db::exec("UPDATE call_ice SET consumed = 1 WHERE id IN ($placeholders)", $ids);
    }

    respond(['success' => true, 'candidates' => array_column($rows, 'candidate')]);
}

function call_history()
{
    $user = require_auth();
    $rows = Db::fetchAll(
        "SELECT c.id, c.from_user_id, c.to_user_id, c.status, c.media, c.started_at, c.duration_sec,
                uf.prenom AS from_prenom, uf.nom AS from_nom,
                ut.prenom AS to_prenom, ut.nom AS to_nom
         FROM calls c
         LEFT JOIN users uf ON uf.id = c.from_user_id
         LEFT JOIN users ut ON ut.id = c.to_user_id
         WHERE c.from_user_id = ? OR c.to_user_id = ?
         ORDER BY c.started_at DESC LIMIT 50",
        [$user['id'], $user['id']]
    );
    respond(['success' => true, 'data' => $rows]);
}
