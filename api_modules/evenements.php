<?php

// ─── Liste des événements ouverts ───
function get_evenements() {
    $user = require_auth();

    $list = Db::fetchAll(
        "SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.heure_debut, e.heure_fin,
                e.lieu, e.image_url, e.max_participants, e.statut, e.inscription_obligatoire,
                (SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = e.id AND statut = 'inscrit') AS nb_inscrits,
                (SELECT COUNT(*) FROM evenement_champs WHERE evenement_id = e.id) AS nb_champs,
                (SELECT id FROM evenement_inscriptions WHERE evenement_id = e.id AND user_id = ? AND statut = 'inscrit' LIMIT 1) AS mon_inscription_id
         FROM evenements e
         WHERE e.statut IN ('ouvert', 'ferme')
         ORDER BY e.date_debut ASC",
        [$user['id']]
    );

    respond(['success' => true, 'list' => $list]);
}

// ─── Détail d'un événement + champs pour inscription ───
function get_evenement_detail() {
    $user = require_auth();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ev = Db::fetch(
        "SELECT e.*,
                (SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = e.id AND statut = 'inscrit') AS nb_inscrits
         FROM evenements e
         WHERE e.id = ? AND e.statut IN ('ouvert', 'ferme')",
        [$id]
    );
    if (!$ev) not_found();

    $champs = Db::fetchAll(
        "SELECT id, label, type, options, obligatoire, ordre FROM evenement_champs WHERE evenement_id = ? ORDER BY ordre ASC",
        [$id]
    );
    foreach ($champs as &$c) {
        if ($c['options']) $c['options'] = json_decode($c['options'], true);
    }
    unset($c);

    // Mon inscription
    $monInscription = Db::fetch(
        "SELECT * FROM evenement_inscriptions WHERE evenement_id = ? AND user_id = ? AND statut = 'inscrit'",
        [$id, $user['id']]
    );
    $mesValeurs = [];
    if ($monInscription) {
        $vals = Db::fetchAll(
            "SELECT champ_id, valeur FROM evenement_inscription_valeurs WHERE inscription_id = ?",
            [$monInscription['id']]
        );
        foreach ($vals as $v) $mesValeurs[$v['champ_id']] = $v['valeur'];
    }

    // Liste des inscrits (prénom + nom seulement)
    $inscrits = Db::fetchAll(
        "SELECT u.prenom, u.nom, ei.created_at
         FROM evenement_inscriptions ei
         JOIN users u ON u.id = ei.user_id
         WHERE ei.evenement_id = ? AND ei.statut = 'inscrit'
         ORDER BY ei.created_at ASC",
        [$id]
    );

    respond([
        'success' => true,
        'evenement' => $ev,
        'champs' => $champs,
        'mon_inscription' => $monInscription,
        'mes_valeurs' => $mesValeurs,
        'inscrits' => $inscrits,
    ]);
}

// ─── S'inscrire à un événement ───
function inscrire_evenement() {
    $user = require_auth();
    global $params;
    $eventId = $params['evenement_id'] ?? '';
    if (!$eventId) bad_request('ID événement requis');

    $ev = Db::fetch("SELECT * FROM evenements WHERE id = ? AND statut = 'ouvert'", [$eventId]);
    if (!$ev) bad_request('Événement non disponible');

    // Vérifier max participants
    if ($ev['max_participants']) {
        $count = (int) Db::getOne("SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = ? AND statut = 'inscrit'", [$eventId]);
        if ($count >= $ev['max_participants']) bad_request('Nombre maximum de participants atteint');
    }

    // Vérifier pas déjà inscrit
    $existing = Db::fetch(
        "SELECT id, statut FROM evenement_inscriptions WHERE evenement_id = ? AND user_id = ?",
        [$eventId, $user['id']]
    );

    if ($existing && $existing['statut'] === 'inscrit') {
        bad_request('Vous êtes déjà inscrit');
    }

    // Charger les champs pour validation
    $champs = Db::fetchAll("SELECT * FROM evenement_champs WHERE evenement_id = ? ORDER BY ordre ASC", [$eventId]);
    $valeurs = $params['valeurs'] ?? [];

    // Valider les champs obligatoires
    foreach ($champs as $c) {
        if ($c['obligatoire'] && empty($valeurs[$c['id']])) {
            $label = $c['label'];
            bad_request("Le champ \"$label\" est obligatoire");
        }
    }

    $inscriptionId = null;

    if ($existing) {
        // Réactiver une inscription annulée
        Db::exec("UPDATE evenement_inscriptions SET statut = 'inscrit', created_at = NOW() WHERE id = ?", [$existing['id']]);
        $inscriptionId = $existing['id'];
        // Supprimer anciennes valeurs
        Db::exec("DELETE FROM evenement_inscription_valeurs WHERE inscription_id = ?", [$inscriptionId]);
    } else {
        $inscriptionId = Uuid::v4();
        Db::exec(
            "INSERT INTO evenement_inscriptions (id, evenement_id, user_id, statut) VALUES (?, ?, ?, 'inscrit')",
            [$inscriptionId, $eventId, $user['id']]
        );
    }

    // Sauvegarder les valeurs des champs
    foreach ($champs as $c) {
        $val = $valeurs[$c['id']] ?? null;
        if ($val !== null && $val !== '') {
            // Pour checkbox, stocker en JSON si array
            if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            Db::exec(
                "INSERT INTO evenement_inscription_valeurs (id, inscription_id, champ_id, valeur) VALUES (?, ?, ?, ?)",
                [Uuid::v4(), $inscriptionId, $c['id'], $val]
            );
        }
    }

    respond(['success' => true, 'message' => 'Inscription confirmée']);
}

// ─── Se désinscrire ───
function desinscrire_evenement() {
    $user = require_auth();
    global $params;
    $eventId = $params['evenement_id'] ?? '';
    if (!$eventId) bad_request('ID événement requis');

    $ev = Db::fetch("SELECT statut FROM evenements WHERE id = ?", [$eventId]);
    if (!$ev || $ev['statut'] !== 'ouvert') bad_request('Désinscription impossible');

    Db::exec(
        "UPDATE evenement_inscriptions SET statut = 'annule' WHERE evenement_id = ? AND user_id = ? AND statut = 'inscrit'",
        [$eventId, $user['id']]
    );

    respond(['success' => true, 'message' => 'Désinscription effectuée']);
}
