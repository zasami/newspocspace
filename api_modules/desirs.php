<?php
/**
 * Désirs API actions (employee-side)
 */

function get_mes_desirs()
{
    global $params;
    $user = require_auth();

    $mois = $params['mois'] ?? null;
    $sql = "SELECT d.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
                   ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur
            FROM desirs d
            LEFT JOIN users u2 ON u2.id = d.valide_par
            LEFT JOIN horaires_types ht ON ht.id = d.horaire_type_id
            WHERE d.user_id = ?";
    $p = [$user['id']];

    if ($mois && preg_match('/^\d{4}-\d{2}$/', $mois)) {
        $sql .= " AND d.mois_cible = ?";
        $p[] = $mois;
    }

    $sql .= " ORDER BY d.date_souhaitee DESC";

    $desirs = Db::fetchAll($sql, $p);
    $maxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: MAX_DESIRS_PAR_MOIS);
    respond(['success' => true, 'desirs' => $desirs, 'max_desirs' => $maxDesirs]);
}

function submit_desir()
{
    global $params;
    $user = require_auth();

    $dateSouhaitee = Sanitize::date($params['date_souhaitee'] ?? '');
    $type = $params['type'] ?? '';
    $detail = Sanitize::text($params['detail'] ?? '', 500);
    $horaireTypeId = $params['horaire_type_id'] ?? null;

    if (!$dateSouhaitee) {
        bad_request('Date requise');
    }
    if (!in_array($type, ['jour_off', 'horaire_special'])) {
        bad_request('Type invalide');
    }

    // Validate horaire_type_id if provided
    if ($horaireTypeId) {
        $ht = Db::fetch("SELECT id FROM horaires_types WHERE id = ? AND is_active = 1", [$horaireTypeId]);
        if (!$ht) $horaireTypeId = null;
    }

    $moisCible = substr($dateSouhaitee, 0, 7);

    // Check max per month (from ems_config or fallback to constant)
    $maxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: MAX_DESIRS_PAR_MOIS);
    $count = Db::getOne(
        "SELECT COUNT(*) FROM desirs WHERE user_id = ? AND mois_cible = ? AND statut != 'refuse' AND permanent_id IS NULL",
        [$user['id'], $moisCible]
    );
    if ($count >= $maxDesirs) {
        bad_request('Maximum ' . $maxDesirs . ' désirs par mois atteint');
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO desirs (id, user_id, date_souhaitee, type, detail, horaire_type_id, mois_cible)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $user['id'], $dateSouhaitee, $type, $detail ?: null, $horaireTypeId ?: null, $moisCible]
    );

    respond(['success' => true, 'message' => 'Désir soumis', 'id' => $id]);
}

function update_desir()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    $dateSouhaitee = Sanitize::date($params['date_souhaitee'] ?? '');
    $type = $params['type'] ?? '';
    $detail = Sanitize::text($params['detail'] ?? '', 500);
    $horaireTypeId = $params['horaire_type_id'] ?? null;

    if (!$id || !$dateSouhaitee) bad_request('ID et date requis');
    if (!in_array($type, ['jour_off', 'horaire_special'])) bad_request('Type invalide');

    $desir = Db::fetch("SELECT * FROM desirs WHERE id = ? AND user_id = ? AND statut = 'en_attente'", [$id, $user['id']]);
    if (!$desir) bad_request('Désir non trouvé ou non modifiable');

    if ($horaireTypeId) {
        $ht = Db::fetch("SELECT id FROM horaires_types WHERE id = ? AND is_active = 1", [$horaireTypeId]);
        if (!$ht) $horaireTypeId = null;
    }

    $moisCible = substr($dateSouhaitee, 0, 7);
    $maxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: MAX_DESIRS_PAR_MOIS);
    $count = Db::getOne(
        "SELECT COUNT(*) FROM desirs WHERE user_id = ? AND mois_cible = ? AND statut != 'refuse' AND permanent_id IS NULL AND id != ?",
        [$user['id'], $moisCible, $id]
    );
    if ($count >= $maxDesirs) {
        bad_request('Maximum ' . $maxDesirs . ' désirs par mois atteint');
    }

    Db::exec(
        "UPDATE desirs SET date_souhaitee = ?, type = ?, detail = ?, horaire_type_id = ?, mois_cible = ? WHERE id = ?",
        [$dateSouhaitee, $type, $detail ?: null, $horaireTypeId ?: null, $moisCible, $id]
    );

    respond(['success' => true, 'message' => 'Désir modifié']);
}

function delete_desir()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $desir = Db::fetch(
        "SELECT id FROM desirs WHERE id = ? AND user_id = ? AND statut = 'en_attente'",
        [$id, $user['id']]
    );
    if (!$desir) {
        bad_request('Désir non trouvé ou déjà traité');
    }

    Db::exec("DELETE FROM desirs WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Désir supprimé']);
}

function get_mes_permanents()
{
    $user = require_auth();

    $permanents = Db::fetchAll(
        "SELECT dp.*, ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
                dp2.horaire_type_id AS ancien_horaire_type_id, dp2.detail AS ancien_detail,
                ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
         FROM desirs_permanents dp
         LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
         LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
         LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
         WHERE dp.user_id = ?
         ORDER BY dp.jour_semaine",
        [$user['id']]
    );

    // Flag permanents that have a pending modification
    $pendingReplacements = Db::fetchAll(
        "SELECT replaces_id FROM desirs_permanents WHERE user_id = ? AND replaces_id IS NOT NULL AND statut = 'en_attente'",
        [$user['id']]
    );
    $pendingIds = array_column($pendingReplacements, 'replaces_id');
    foreach ($permanents as &$p) {
        $p['has_pending_modification'] = in_array($p['id'], $pendingIds) ? 1 : 0;
    }

    respond(['success' => true, 'permanents' => $permanents]);
}

function submit_desir_permanent()
{
    global $params;
    $user = require_auth();

    $jourSemaine = (int) ($params['jour_semaine'] ?? -1);
    $type = $params['type'] ?? '';
    $horaireTypeId = $params['horaire_type_id'] ?? null;
    $detail = Sanitize::text($params['detail'] ?? '', 500);

    if ($jourSemaine < 0 || $jourSemaine > 6) bad_request('Jour invalide');
    if (!in_array($type, ['jour_off', 'horaire_special'])) bad_request('Type invalide');

    // Max 4 active permanents (only count validated active ones)
    $count = Db::getOne(
        "SELECT COUNT(*) FROM desirs_permanents WHERE user_id = ? AND is_active = 1 AND statut = 'valide'",
        [$user['id']]
    );
    if ($count >= 4) {
        bad_request('Maximum 4 désirs permanents actifs');
    }

    // Check if same day already exists (active or pending)
    $existing = Db::fetch(
        "SELECT id FROM desirs_permanents WHERE user_id = ? AND jour_semaine = ? AND (is_active = 1 OR statut = 'en_attente')",
        [$user['id'], $jourSemaine]
    );
    if ($existing) {
        bad_request('Vous avez déjà un désir permanent pour ce jour');
    }

    // Validate horaire
    if ($horaireTypeId) {
        $ht = Db::fetch("SELECT id FROM horaires_types WHERE id = ? AND is_active = 1", [$horaireTypeId]);
        if (!$ht) $horaireTypeId = null;
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO desirs_permanents (id, user_id, jour_semaine, type, horaire_type_id, detail, is_active, statut)
         VALUES (?, ?, ?, ?, ?, ?, 0, 'en_attente')",
        [$id, $user['id'], $jourSemaine, $type, $horaireTypeId ?: null, $detail ?: null]
    );

    respond(['success' => true, 'message' => 'Désir permanent créé — en attente de validation', 'id' => $id]);
}

function update_desir_permanent()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    $jourSemaine = (int) ($params['jour_semaine'] ?? -1);
    $type = $params['type'] ?? '';
    $horaireTypeId = $params['horaire_type_id'] ?? null;
    $detail = Sanitize::text($params['detail'] ?? '', 500);

    if (!$id || $jourSemaine < 0 || $jourSemaine > 6) bad_request('Données invalides');
    if (!in_array($type, ['jour_off', 'horaire_special'])) bad_request('Type invalide');

    $perm = Db::fetch("SELECT * FROM desirs_permanents WHERE id = ? AND user_id = ? AND is_active = 1 AND statut = 'valide'", [$id, $user['id']]);
    if (!$perm) bad_request('Désir permanent non trouvé ou non modifiable');

    // Check if there's already a pending modification for this permanent
    $pendingModif = Db::fetch(
        "SELECT id FROM desirs_permanents WHERE replaces_id = ? AND statut = 'en_attente'",
        [$id]
    );
    if ($pendingModif) {
        bad_request('Une modification est déjà en attente de validation pour ce désir');
    }

    // Check day conflict (only with other active permanents, excluding the one being replaced)
    $existing = Db::fetch(
        "SELECT id FROM desirs_permanents WHERE user_id = ? AND jour_semaine = ? AND is_active = 1 AND id != ?",
        [$user['id'], $jourSemaine, $id]
    );
    if ($existing) bad_request('Vous avez déjà un désir permanent pour ce jour');

    if ($horaireTypeId) {
        $ht = Db::fetch("SELECT id FROM horaires_types WHERE id = ? AND is_active = 1", [$horaireTypeId]);
        if (!$ht) $horaireTypeId = null;
    }

    // Create a new permanent desire as a modification proposal
    // The old one stays active until admin validates the new one
    $newId = Uuid::v4();
    Db::exec(
        "INSERT INTO desirs_permanents (id, user_id, jour_semaine, type, horaire_type_id, detail, is_active, statut, replaces_id)
         VALUES (?, ?, ?, ?, ?, ?, 0, 'en_attente', ?)",
        [$newId, $user['id'], $jourSemaine, $type, $horaireTypeId ?: null, $detail ?: null, $id]
    );

    respond(['success' => true, 'message' => 'Modification proposée — en attente de validation', 'id' => $newId]);
}

function delete_desir_permanent()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $perm = Db::fetch(
        "SELECT * FROM desirs_permanents WHERE id = ? AND user_id = ?",
        [$id, $user['id']]
    );
    if (!$perm) bad_request('Désir permanent non trouvé');

    // If this is a pending modification proposal, just delete it
    if ($perm['statut'] === 'en_attente' && $perm['replaces_id']) {
        Db::exec("DELETE FROM desirs_permanents WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Proposition de modification annulée']);
    }

    // If this is a pending new permanent (not a modification), delete it
    if ($perm['statut'] === 'en_attente' && !$perm['replaces_id']) {
        Db::exec("DELETE FROM desirs_permanents WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Désir permanent supprimé']);
    }

    // Otherwise deactivate (keep history) and also delete any pending modifications
    Db::exec("DELETE FROM desirs_permanents WHERE replaces_id = ? AND statut = 'en_attente'", [$id]);
    Db::exec("UPDATE desirs_permanents SET is_active = 0, statut = 'refuse' WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Désir permanent désactivé']);
}

function get_horaires_types()
{
    require_auth();
    $horaires = Db::fetchAll(
        "SELECT id, code, nom, heure_debut, heure_fin, couleur, duree_effective
         FROM horaires_types WHERE is_active = 1 ORDER BY code"
    );
    respond(['success' => true, 'horaires' => $horaires]);
}
