<?php
/**
 * Module Formations · côté SPA employé
 *
 * Endpoints :
 *   - get_mes_formations          : passées + à venir + souhaits du user courant
 *   - get_formation_detail_emp    : fiche formation + collègues inscrits
 *   - upload_formation_certificat : upload certificat (PDF, DOC, image)
 *   - get_catalogue_formations    : catalogue (avec match fonction)
 *   - submit_souhait_formation    : exprimer souhait de participer
 *   - propose_covoiturage_formation : envoie message à collègues sélectionnés
 *   - update_user_adresse         : sauvegarde adresse perso (calcul itinéraire)
 */

const FORM_CERTIF_MAX_SIZE = 8 * 1024 * 1024; // 8 MB
const FORM_CERTIF_ALLOWED_MIME = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
];

// ─── Mes formations ──────────────────────────────────────────────────────────

function get_mes_formations()
{
    require_auth();
    $userId = $_SESSION['ss_user']['id'];
    $today  = date('Y-m-d');

    // Formations passées (réalisées ou avec date_fin <= today)
    $passees = Db::fetchAll(
        "SELECT f.id, f.titre, f.type, f.lieu, f.date_debut, f.date_fin, f.duree_heures,
                f.image_url, f.formateur,
                p.id AS participant_id, p.statut, p.certificat_url, p.heures_realisees,
                p.date_realisation, p.evaluation_manager
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.user_id = ?
           AND (p.statut IN ('present','valide','absent')
                OR (f.date_fin IS NOT NULL AND f.date_fin < ?))
         ORDER BY COALESCE(p.date_realisation, f.date_debut) DESC
         LIMIT 100",
        [$userId, $today]
    );

    // Formations à venir (inscrit, date_debut >= today)
    $aVenir = Db::fetchAll(
        "SELECT f.id, f.titre, f.type, f.lieu, f.date_debut, f.date_fin, f.duree_heures,
                f.image_url, f.formateur, f.modalite, f.description,
                p.id AS participant_id, p.statut,
                (SELECT COUNT(*) FROM formation_participants pp WHERE pp.formation_id = f.id) AS nb_participants
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.user_id = ?
           AND p.statut = 'inscrit'
           AND (f.date_debut IS NULL OR f.date_debut >= ?)
         ORDER BY f.date_debut ASC",
        [$userId, $today]
    );

    // Souhaits du user (formations qu'il a demandées)
    $souhaits = Db::fetchAll(
        "SELECT s.id, s.statut AS souhait_statut, s.message, s.created_at,
                f.id AS formation_id, f.titre, f.lieu, f.date_debut, f.image_url
         FROM formation_souhaits s
         JOIN formations f ON f.id = s.formation_id
         WHERE s.user_id = ?
         ORDER BY s.created_at DESC",
        [$userId]
    );

    respond([
        'success' => true,
        'passees'  => $passees,
        'a_venir'  => $aVenir,
        'souhaits' => $souhaits,
    ]);
}

// ─── Détail formation (vue employé) ──────────────────────────────────────────

function get_formation_detail_emp()
{
    require_auth();
    global $params;
    $userId = $_SESSION['ss_user']['id'];
    $formId = Sanitize::text($params['id'] ?? '', 36);
    if (!$formId) bad_request('id requis');

    $formation = Db::fetch(
        "SELECT f.*,
                (SELECT COUNT(*) FROM formation_participants p WHERE p.formation_id = f.id) AS nb_participants
         FROM formations f WHERE f.id = ?",
        [$formId]
    );
    if (!$formation) not_found('Formation introuvable');

    // Le user est-il inscrit ?
    $myParticipation = Db::fetch(
        "SELECT id, statut, certificat_url, heures_realisees, date_realisation
         FROM formation_participants WHERE formation_id = ? AND user_id = ?",
        [$formId, $userId]
    );

    // Collègues inscrits (sauf le user courant)
    $collegues = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo, u.email, fn.nom AS fonction,
                p.statut
         FROM formation_participants p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE p.formation_id = ? AND u.id != ? AND u.is_active = 1
         ORDER BY u.nom, u.prenom",
        [$formId, $userId]
    );

    // Adresse EMS pour itinéraire
    $emsAdresse = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_adresse'") ?: '';
    $emsNpa     = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_npa'") ?: '';
    $emsVille   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_ville'") ?: '';
    $emsAdresseFull = trim($emsAdresse . ', ' . $emsNpa . ' ' . $emsVille, ', ');

    // Adresse user perso
    $userAdr = Db::fetch(
        "SELECT adresse_rue, adresse_complement, adresse_cp, adresse_ville
         FROM users WHERE id = ?", [$userId]
    );
    $userAdrFull = '';
    if ($userAdr && $userAdr['adresse_rue']) {
        $userAdrFull = trim($userAdr['adresse_rue'] . ', ' . $userAdr['adresse_cp'] . ' ' . $userAdr['adresse_ville'], ', ');
    }

    respond([
        'success'        => true,
        'formation'      => $formation,
        'ma_participation' => $myParticipation,
        'collegues'      => $collegues,
        'ems_adresse'    => $emsAdresseFull,
        'mon_adresse'    => $userAdrFull,
    ]);
}

// ─── Upload certificat ──────────────────────────────────────────────────────

function upload_formation_certificat()
{
    $user = require_auth();
    $userId = $user['id'];

    $participantId = $_POST['participant_id'] ?? '';
    if (!$participantId) bad_request('participant_id requis');

    // Vérifier que le user est bien le participant
    $part = Db::fetch(
        "SELECT id, formation_id, user_id, certificat_url FROM formation_participants WHERE id = ?",
        [$participantId]
    );
    if (!$part || $part['user_id'] !== $userId) forbidden('Accès non autorisé');

    if (!isset($_FILES['certificat']) || $_FILES['certificat']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant ou erreur upload');
    }

    $file = $_FILES['certificat'];
    if ($file['size'] > FORM_CERTIF_MAX_SIZE) {
        bad_request('Fichier trop volumineux (max 8 Mo)');
    }

    // Vérifier MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($realMime, FORM_CERTIF_ALLOWED_MIME, true)) {
        bad_request('Type de fichier non autorisé (' . $realMime . ')');
    }

    // Storage
    $uploadDir = __DIR__ . '/../storage/certificats/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    // Extension à partir du MIME
    $ext = match($realMime) {
        'application/pdf'  => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default => 'bin',
    };

    $filename = $userId . '_' . $participantId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        bad_request('Erreur sauvegarde fichier');
    }

    // Supprimer ancien certificat si présent
    if ($part['certificat_url']) {
        $oldPath = __DIR__ . '/../' . ltrim(str_replace('/spocspace/', '', $part['certificat_url']), '/');
        if (is_file($oldPath)) @unlink($oldPath);
    }

    $url = '/spocspace/storage/certificats/' . $filename;
    Db::exec(
        "UPDATE formation_participants SET certificat_url = ? WHERE id = ?",
        [$url, $participantId]
    );

    respond([
        'success' => true,
        'url' => $url,
        'mime' => $realMime,
        'filename' => basename($file['name']),
    ]);
}

// ─── Catalogue formations (avec match fonction) ─────────────────────────────

function get_catalogue_formations()
{
    require_auth();
    $userId = $_SESSION['ss_user']['id'];
    $today  = date('Y-m-d');

    // Récupérer secteur du user (via fonction)
    $secteur = Db::getOne(
        "SELECT fn.secteur_fegems FROM users u
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE u.id = ?", [$userId]
    );

    // Formations futures non inscrites par le user
    $rows = Db::fetchAll(
        "SELECT f.id, f.titre, f.type, f.lieu, f.date_debut, f.date_fin, f.duree_heures,
                f.image_url, f.description, f.objectifs, f.public_cible, f.modalite,
                f.tarif_membres, f.tarif_non_membres, f.formateur, f.intervenants,
                f.max_participants,
                (SELECT COUNT(*) FROM formation_participants p WHERE p.formation_id = f.id) AS nb_participants,
                (SELECT id FROM formation_souhaits s WHERE s.user_id = ? AND s.formation_id = f.id LIMIT 1) AS souhait_id,
                (SELECT statut FROM formation_souhaits s WHERE s.user_id = ? AND s.formation_id = f.id LIMIT 1) AS souhait_statut
         FROM formations f
         WHERE f.statut IN ('planifiee','en_cours')
           AND (f.date_debut IS NULL OR f.date_debut >= ?)
           AND NOT EXISTS (
               SELECT 1 FROM formation_participants p
               WHERE p.formation_id = f.id AND p.user_id = ?
           )
         ORDER BY f.date_debut ASC, f.titre ASC
         LIMIT 80",
        [$userId, $userId, $today, $userId]
    );

    // Pour chaque formation, calculer match si secteur défini :
    // matche si une thématique de la formation a niveau requis pour ce secteur
    $matchByForm = [];
    if ($secteur && $rows) {
        $formIds = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $matches = Db::fetchAll(
            "SELECT DISTINCT ft.formation_id
             FROM formation_thematiques ft
             JOIN competences_profil_attendu cpa
               ON cpa.thematique_id = ft.thematique_id
             WHERE ft.formation_id IN ($placeholders)
               AND cpa.secteur = ? AND cpa.requis = 1",
            array_merge($formIds, [$secteur])
        );
        foreach ($matches as $m) $matchByForm[$m['formation_id']] = true;
    }
    foreach ($rows as &$r) {
        $r['match_fonction'] = isset($matchByForm[$r['id']]) ? 1 : 0;
    }

    respond([
        'success' => true,
        'formations' => $rows,
        'secteur_user' => $secteur,
    ]);
}

// ─── Souhait de participation ───────────────────────────────────────────────

function submit_souhait_formation()
{
    require_auth();
    global $params;
    $userId = $_SESSION['ss_user']['id'];
    $formId = Sanitize::text($params['formation_id'] ?? '', 36);
    $message = trim($params['message'] ?? '');
    if (!$formId) bad_request('formation_id requis');

    // Vérifier formation existe
    $form = Db::fetch("SELECT id, titre FROM formations WHERE id = ?", [$formId]);
    if (!$form) not_found('Formation introuvable');

    // Calculer match fonction
    $secteur = Db::getOne(
        "SELECT fn.secteur_fegems FROM users u
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE u.id = ?", [$userId]
    );
    $match = 0;
    if ($secteur) {
        $match = (int) Db::getOne(
            "SELECT COUNT(*) FROM formation_thematiques ft
             JOIN competences_profil_attendu cpa ON cpa.thematique_id = ft.thematique_id
             WHERE ft.formation_id = ? AND cpa.secteur = ? AND cpa.requis = 1",
            [$formId, $secteur]
        ) > 0 ? 1 : 0;
    }

    // Idempotent : si existe → update, sinon insert
    $existing = Db::fetch(
        "SELECT id FROM formation_souhaits WHERE user_id = ? AND formation_id = ?",
        [$userId, $formId]
    );
    if ($existing) {
        Db::exec(
            "UPDATE formation_souhaits
             SET message = ?, statut = 'en_attente', match_fonction = ?, updated_at = NOW()
             WHERE id = ?",
            [$message, $match, $existing['id']]
        );
        $souhaitId = $existing['id'];
    } else {
        $souhaitId = Uuid::v4();
        Db::exec(
            "INSERT INTO formation_souhaits (id, user_id, formation_id, message, match_fonction)
             VALUES (?, ?, ?, ?, ?)",
            [$souhaitId, $userId, $formId, $message, $match]
        );
    }

    respond([
        'success' => true,
        'souhait_id' => $souhaitId,
        'message' => 'Votre souhait a été enregistré. Le service RH vous répondra bientôt.',
    ]);
}

// ─── Proposition de covoiturage ─────────────────────────────────────────────

function propose_covoiturage_formation()
{
    require_auth();
    global $params;
    $user = $_SESSION['ss_user'];
    $userId = $user['id'];

    $formId = Sanitize::text($params['formation_id'] ?? '', 36);
    $collegueIds = $params['collegue_ids'] ?? [];
    $messageTxt = trim($params['message'] ?? '');

    if (!$formId) bad_request('formation_id requis');
    if (!is_array($collegueIds) || !$collegueIds) bad_request('Au moins un collègue requis');

    $form = Db::fetch(
        "SELECT id, titre, lieu, date_debut FROM formations WHERE id = ?",
        [$formId]
    );
    if (!$form) not_found('Formation introuvable');

    // Vérifier que tous les collègues sont bien inscrits
    $placeholders = implode(',', array_fill(0, count($collegueIds), '?'));
    $valid = Db::fetchAll(
        "SELECT u.id FROM formation_participants p
         JOIN users u ON u.id = p.user_id
         WHERE p.formation_id = ? AND u.id IN ($placeholders) AND u.is_active = 1",
        array_merge([$formId], $collegueIds)
    );
    $validIds = array_column($valid, 'id');
    if (!$validIds) bad_request('Aucun collègue valide');

    // Compose le message via la messagerie interne
    $dateAffichee = $form['date_debut'] ? date('d/m/Y', strtotime($form['date_debut'])) : '—';
    $sujet = '🚗 Covoiturage · ' . $form['titre'];
    $contenu = "<p>Bonjour,</p>";
    $contenu .= "<p>Je vous propose un covoiturage pour la formation <strong>" . h($form['titre']) . "</strong> "
              . "du <strong>" . h($dateAffichee) . "</strong> à <strong>" . h($form['lieu'] ?: 'lieu à confirmer') . "</strong>.</p>";
    if ($messageTxt) {
        $contenu .= "<p>" . nl2br(h($messageTxt)) . "</p>";
    } else {
        $contenu .= "<p>Êtes-vous intéressé·e ? Répondez-moi pour qu'on s'organise.</p>";
    }
    $contenu .= "<p>Merci !</p>";

    // Création du message (table messages + message_recipients)
    $msgId = Uuid::v4();
    Db::exec(
        "INSERT INTO messages (id, thread_id, from_user_id, sujet, contenu)
         VALUES (?, ?, ?, ?, ?)",
        [$msgId, $msgId, $userId, $sujet, $contenu]
    );
    foreach ($validIds as $cid) {
        Db::exec(
            "INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')",
            [Uuid::v4(), $msgId, $cid]
        );
    }

    respond([
        'success' => true,
        'message_id' => $msgId,
        'sent_to' => count($validIds),
        'message' => 'Proposition envoyée à ' . count($validIds) . ' collègue' . (count($validIds) > 1 ? 's' : ''),
    ]);
}

// ─── Adresse perso (pour calcul itinéraire) ─────────────────────────────────

function update_user_adresse()
{
    require_auth();
    global $params;
    $userId = $_SESSION['ss_user']['id'];

    $rue        = Sanitize::text($params['adresse_rue'] ?? '', 255);
    $complement = Sanitize::text($params['adresse_complement'] ?? '', 255);
    $cp         = Sanitize::text($params['adresse_cp'] ?? '', 20);
    $ville      = Sanitize::text($params['adresse_ville'] ?? '', 120);

    Db::exec(
        "UPDATE users SET adresse_rue = ?, adresse_complement = ?, adresse_cp = ?, adresse_ville = ?
         WHERE id = ?",
        [$rue ?: null, $complement ?: null, $cp ?: null, $ville ?: null, $userId]
    );

    respond([
        'success' => true,
        'adresse' => trim($rue . ', ' . $cp . ' ' . $ville, ', '),
    ]);
}
