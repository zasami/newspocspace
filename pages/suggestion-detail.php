<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$flag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($flag !== '1') { http_response_code(403); exit; }

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

$id = $_GET['slug'] ?? $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="page-wrap">' . render_empty_state('Suggestion introuvable') . '</div>';
    return;
}

$sug = Db::fetch(
    "SELECT s.*,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo,
            EXISTS(SELECT 1 FROM suggestions_votes v WHERE v.suggestion_id = s.id AND v.user_id = ?) AS has_voted
     FROM suggestions s
     LEFT JOIN users u ON u.id = s.auteur_id
     WHERE s.id = ?",
    [$uid, $id]
);
if (!$sug) {
    echo '<div class="page-wrap">' . render_empty_state('Suggestion introuvable') . '</div>';
    return;
}

$comments = Db::fetchAll(
    "SELECT c.id, c.content, c.role, c.created_at,
            u.prenom, u.nom, u.photo
     FROM suggestions_commentaires c
     LEFT JOIN users u ON u.id = c.auteur_id
     WHERE c.suggestion_id = ? AND c.visibility = 'public'
     ORDER BY c.created_at ASC",
    [$id]
);

$attachments = Db::fetchAll(
    "SELECT id, original_name, mime_type, size_bytes, kind, created_at
     FROM suggestions_attachments WHERE suggestion_id = ? ORDER BY created_at ASC",
    [$id]
);

$history = Db::fetchAll(
    "SELECT h.*, u.prenom, u.nom
     FROM suggestions_statut_history h
     LEFT JOIN users u ON u.id = h.changed_by
     WHERE h.suggestion_id = ?
     ORDER BY h.created_at ASC",
    [$id]
);

$services = [
    'aide_soignant' => 'Aide-soignant·e', 'infirmier' => 'Infirmier·e',
    'infirmier_chef' => 'Infirmier·e chef', 'animation' => 'Animation',
    'cuisine' => 'Cuisine / Hôtellerie', 'technique' => 'Technique',
    'admin' => 'Administration', 'rh' => 'RH', 'direction' => 'Direction',
    'qualite' => 'Qualité', 'autre' => 'Autre',
];
$categories = [
    'formulaire' => 'Formulaire à informatiser', 'fonctionnalite' => 'Nouvelle fonctionnalité',
    'amelioration' => 'Amélioration', 'alerte' => 'Alerte / Notification',
    'bug' => 'Bug', 'question' => 'Question',
];
$urgences = [
    'critique' => ['Critique', 'sug-urg-critique'],
    'eleve'    => ['Élevée',   'sug-urg-eleve'],
    'moyen'    => ['Moyenne',  'sug-urg-moyen'],
    'faible'   => ['Faible',   'sug-urg-faible'],
];
$statuts = [
    'nouvelle'   => ['Nouvelle',   'sug-st-nouvelle'],
    'etudiee'    => ['Étudiée',    'sug-st-etudiee'],
    'planifiee'  => ['Planifiée',  'sug-st-planifiee'],
    'en_dev'     => ['En développement', 'sug-st-endev'],
    'livree'     => ['Livrée',     'sug-st-livree'],
    'refusee'    => ['Refusée',    'sug-st-refusee'],
];
$frequences = [
    'multi_jour' => 'Plusieurs fois par jour', 'quotidien'  => 'Quotidien',
    'hebdo' => 'Hebdomadaire', 'mensuel' => 'Mensuel', 'ponctuel' => 'Ponctuel',
];
$benefLabels = [
    'gain_temps' => 'Gain de temps', 'reduction_erreurs' => 'Réduction d\'erreurs',
    'tracabilite' => 'Traçabilité', 'conformite' => 'Conformité',
    'confort_resident' => 'Confort résident', 'securite' => 'Sécurité',
];

$isMine = ($sug['auteur_id'] === $uid);
$canEdit = $isMine && $sug['statut'] === 'nouvelle';

$urg = $urgences[$sug['urgence']] ?? ['—', 'sug-urg-moyen'];
$st  = $statuts[$sug['statut']]   ?? ['—', 'sug-st-nouvelle'];
$svcLbl = $services[$sug['service']] ?? $sug['service'];
$catLbl = $categories[$sug['categorie']] ?? $sug['categorie'];
$freqLbl = $sug['frequence'] ? ($frequences[$sug['frequence']] ?? $sug['frequence']) : '';
$beneficesArr = $sug['benefices'] ? explode(',', $sug['benefices']) : [];
$auteurLbl = trim(($sug['auteur_prenom'] ?? '') . ' ' . ($sug['auteur_nom'] ?? '')) ?: '—';
?>
<style>
.sgd-page { max-width: 920px; margin: 0 auto; }
.sgd-hero {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 18px 22px; margin-bottom: 14px;
}
.sgd-ref { font-family: ui-monospace, monospace; font-size: .78rem; color: var(--cl-text-muted); letter-spacing: .3px; }
.sgd-title { font-size: 1.35rem; font-weight: 700; color: var(--cl-text); margin-top: 4px; line-height: 1.25; }
.sgd-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.sgd-badge {
    font-size: .7rem; font-weight: 700; padding: 4px 10px; border-radius: 5px;
    text-transform: uppercase; letter-spacing: .3px;
}
.sug-urg-critique { background: #A85B4A; color: #fff; }
.sug-urg-eleve    { background: #C4A882; color: #3a2e1a; }
.sug-urg-moyen    { background: #F5F4F0; color: #6B5B3E; border: 1px solid #E8E4DE; }
.sug-urg-faible   { background: #F5F4F0; color: #8A8680; border: 1px solid #E8E4DE; }
.sug-st-nouvelle  { background: #2d4a43; color: #fff; }
.sug-st-etudiee   { background: #C4A882; color: #3a2e1a; }
.sug-st-planifiee { background: #5B4B6B; color: #fff; }
.sug-st-endev     { background: #5B4B6B; color: #fff; }
.sug-st-livree    { background: #bcd2cb; color: #2d4a43; }
.sug-st-refusee   { background: #A85B4A; color: #fff; }

.sgd-meta-row { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 12px; font-size: .82rem; color: var(--cl-text-muted); }
.sgd-meta-row .bi { margin-right: 4px; }

.sgd-actions { display: flex; gap: 10px; margin-top: 14px; align-items: center; }
.sgd-vote-btn {
    display: inline-flex; align-items: center; gap: 8px;
    border: 1.5px solid var(--cl-border-light); border-radius: 999px;
    padding: 8px 18px; background: #fff; cursor: pointer; font-size: .92rem; font-weight: 600;
    color: var(--cl-text); transition: all .15s;
}
.sgd-vote-btn:hover { border-color: #2d4a43; color: #2d4a43; }
.sgd-vote-btn.voted { background: #bcd2cb; border-color: #2d4a43; color: #2d4a43; }
.sgd-edit-btn, .sgd-del-btn {
    background: #fff; border: 1.5px solid var(--cl-border-light); border-radius: 8px;
    padding: 7px 14px; font-size: .85rem; cursor: pointer; color: var(--cl-text);
    display: inline-flex; align-items: center; gap: 5px;
}
.sgd-edit-btn:hover { border-color: #C4A882; }
.sgd-del-btn { color: #A85B4A; }
.sgd-del-btn:hover { border-color: #A85B4A; }

.sgd-card {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 16px 20px; margin-bottom: 12px;
}
.sgd-card-title {
    font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
    color: var(--cl-text-muted); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
}
.sgd-desc {
    background: #F7F5F2; border-left: 3px solid #C4A882; padding: 12px 16px;
    border-radius: 0 8px 8px 0; font-size: .92rem; line-height: 1.6; white-space: pre-wrap;
}

.sgd-benef-list { display: flex; flex-wrap: wrap; gap: 6px; }
.sgd-benef-chip {
    background: #F7F5F2; border: 1px solid var(--cl-border-light); border-radius: 999px;
    padding: 4px 12px; font-size: .82rem; color: #6B5B3E;
}

.sgd-att-list { display: flex; flex-direction: column; gap: 6px; }
.sgd-att-item {
    display: flex; align-items: center; gap: 10px; padding: 8px 12px;
    border: 1px solid var(--cl-border-light); border-radius: 8px; background: #fff;
    font-size: .86rem;
}
.sgd-att-item .bi { color: #6B5B3E; font-size: 1rem; }
.sgd-att-item .sgd-att-size { margin-left: auto; font-size: .74rem; color: var(--cl-text-muted); }
.sgd-att-link { color: #2d4a43; text-decoration: none; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sgd-att-link:hover { text-decoration: underline; }
.sgd-att-audio { width: 100%; margin-top: 4px; }

.sgd-timeline { display: flex; flex-direction: column; gap: 10px; }
.sgd-tl-item { display: flex; gap: 10px; }
.sgd-tl-dot {
    width: 10px; height: 10px; border-radius: 50%; background: #C4A882; flex-shrink: 0;
    margin-top: 5px; border: 2px solid #fff; box-shadow: 0 0 0 1px #C4A882;
}
.sgd-tl-content { flex: 1; font-size: .84rem; }
.sgd-tl-content strong { color: var(--cl-text); }
.sgd-tl-date { font-size: .72rem; color: var(--cl-text-muted); }

.sgd-comments { display: flex; flex-direction: column; gap: 8px; }
.sgd-comment {
    padding: 10px 14px; border-radius: 8px; border: 1px solid var(--cl-border-light);
    background: #fff; font-size: .86rem;
}
.sgd-comment.admin { background: #F7F5F2; border-left: 3px solid #2d4a43; }
.sgd-comment-head {
    display: flex; justify-content: space-between; gap: 8px; align-items: center;
    font-size: .76rem; color: var(--cl-text-muted); margin-bottom: 4px;
}
.sgd-comment-head strong { color: var(--cl-text); }
.sgd-comment-head .sgd-admin-tag { color: #2d4a43; font-weight: 600; }
.sgd-comment-body { line-height: 1.5; white-space: pre-wrap; }

/* Rich-text editor */
.sgd-rte-wrap { border: 1.5px solid var(--cl-border-light); border-radius: 10px; background: #fff; margin-top: 10px; }
.sgd-rte-toolbar {
    display: flex; align-items: center; gap: 2px; padding: 5px 7px; flex-wrap: wrap;
    border-bottom: 1px solid var(--cl-border-light);
    background: var(--cl-bg, #F7F5F2); border-radius: 9px 9px 0 0;
}
.sgd-rte-btn {
    width: 28px; height: 28px; border: none; background: none; border-radius: 5px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--cl-text-muted, #888); transition: all .12s;
}
.sgd-rte-btn:hover { background: #fff; color: var(--cl-text, #333); }
.sgd-rte-sep { width: 1px; height: 18px; background: var(--cl-border-light); margin: 0 3px; }
.sgd-rte-editor {
    width: 100%; min-height: 80px; max-height: 240px; padding: 10px 12px; overflow-y: auto;
    font-size: .88rem; line-height: 1.55; color: var(--cl-text); outline: none;
}
.sgd-rte-editor:empty:before {
    content: attr(data-placeholder); color: #aaa; pointer-events: none;
}
.sgd-rte-editor p { margin: 0 0 4px; }
.sgd-rte-editor ul, .sgd-rte-editor ol { margin: 4px 0; padding-left: 20px; }
.sgd-rte-footer {
    display: flex; justify-content: flex-end; padding: 7px 10px;
    border-top: 1px solid var(--cl-border-light); background: var(--cl-bg, #F7F5F2);
    border-radius: 0 0 9px 9px;
}
.sgd-comment-send {
    background: #2d4a43; color: #fff; border: none; padding: 7px 18px;
    border-radius: 8px; cursor: pointer; font-weight: 600; font-size: .85rem;
    display: inline-flex; align-items: center; gap: 6px;
}
.sgd-comment-send:disabled { opacity: .5; cursor: not-allowed; }
.sgd-comment-send:hover:not(:disabled) { background: #1f3530; }

.sgd-comment-body p { margin: 0 0 .4em; }
.sgd-comment-body p:last-child { margin: 0; }
.sgd-comment-body ul, .sgd-comment-body ol { margin: 4px 0; padding-left: 20px; }
.sgd-comment-body strong { font-weight: 600; }
</style>

<div class="sgd-page page-wrap">
    <button class="btn btn-sm btn-link re-back-link mb-2 px-0" data-link="suggestions">
        <i class="bi bi-arrow-left"></i> Retour aux suggestions
    </button>

    <div class="sgd-hero">
        <div class="sgd-ref"><?= h($sug['reference_code']) ?></div>
        <h1 class="sgd-title"><?= h($sug['titre']) ?></h1>
        <div class="sgd-badges">
            <span class="sgd-badge <?= h($urg[1]) ?>"><?= h($urg[0]) ?></span>
            <span class="sgd-badge <?= h($st[1]) ?>"><?= h($st[0]) ?></span>
        </div>
        <div class="sgd-meta-row">
            <span><i class="bi bi-person"></i><?= h($auteurLbl) ?></span>
            <span><i class="bi bi-building"></i><?= h($svcLbl) ?></span>
            <span><i class="bi bi-tag"></i><?= h($catLbl) ?></span>
            <?php if ($freqLbl): ?><span><i class="bi bi-clock-history"></i><?= h($freqLbl) ?></span><?php endif ?>
            <span><i class="bi bi-clock"></i><?= h(fmt_date_fr($sug['created_at'])) ?></span>
        </div>
        <div class="sgd-actions">
            <button class="sgd-vote-btn <?= $sug['has_voted'] ? 'voted' : '' ?>" data-sug-vote="<?= h($sug['id']) ?>">
                <i class="bi bi-hand-thumbs-up<?= $sug['has_voted'] ? '-fill' : '' ?>"></i>
                <span class="sug-vote-count"><?= (int)$sug['votes_count'] ?></span>
                <span>vote<?= $sug['votes_count'] > 1 ? 's' : '' ?></span>
            </button>
            <?php if ($canEdit): ?>
                <button class="sgd-edit-btn" data-sgd-edit="<?= h($sug['id']) ?>"><i class="bi bi-pencil"></i> Modifier</button>
                <?php if ((int)$sug['votes_count'] === 0): ?>
                    <button class="sgd-del-btn" data-sgd-del="<?= h($sug['id']) ?>"><i class="bi bi-trash"></i> Supprimer</button>
                <?php endif ?>
            <?php endif ?>
        </div>
    </div>

    <div class="sgd-card">
        <div class="sgd-card-title"><i class="bi bi-chat-left-text"></i> Description</div>
        <div class="sgd-desc"><?= h($sug['description']) ?></div>
    </div>

    <?php if ($beneficesArr): ?>
    <div class="sgd-card">
        <div class="sgd-card-title"><i class="bi bi-check2-circle"></i> Bénéfices attendus</div>
        <div class="sgd-benef-list">
            <?php foreach ($beneficesArr as $b): ?>
                <span class="sgd-benef-chip"><?= h($benefLabels[$b] ?? $b) ?></span>
            <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>

    <?php if ($sug['motif_admin']): ?>
    <div class="sgd-card" style="border-left: 3px solid #2d4a43;">
        <div class="sgd-card-title"><i class="bi bi-shield-check"></i> Réponse de la direction</div>
        <div class="sgd-desc" style="border-left-color: #2d4a43;"><?= $sug['motif_admin'] /* déjà HtmlSanitize côté serveur */ ?></div>
        <?php if ($sug['sprint']): ?>
            <div class="mt-2 small text-muted"><i class="bi bi-rocket-takeoff"></i> Sprint : <strong><?= h($sug['sprint']) ?></strong></div>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php if ($attachments): ?>
    <div class="sgd-card">
        <div class="sgd-card-title"><i class="bi bi-paperclip"></i> Pièces jointes</div>
        <div class="sgd-att-list">
            <?php foreach ($attachments as $a):
                $url = '/newspocspace/api.php?action=download_suggestion_attachment&id=' . urlencode($a['id']);
                $ico = 'bi-file-earmark';
                if ($a['kind'] === 'photo') $ico = 'bi-image';
                elseif ($a['kind'] === 'audio') $ico = 'bi-music-note';
                elseif ($a['kind'] === 'screenshot') $ico = 'bi-camera';
                $sizeKb = round($a['size_bytes'] / 1024);
            ?>
                <div class="sgd-att-item">
                    <i class="bi <?= h($ico) ?>"></i>
                    <?php if ($a['kind'] === 'audio'): ?>
                        <div style="flex:1">
                            <div><?= h($a['original_name']) ?></div>
                            <audio controls src="<?= h($url) ?>" class="sgd-att-audio" preload="metadata"></audio>
                        </div>
                    <?php elseif ($a['kind'] === 'photo'): ?>
                        <a class="sgd-att-link" href="<?= h($url) ?>" target="_blank"><?= h($a['original_name']) ?></a>
                    <?php else: ?>
                        <a class="sgd-att-link" href="<?= h($url) ?>" target="_blank"><?= h($a['original_name']) ?></a>
                    <?php endif ?>
                    <span class="sgd-att-size"><?= h($sizeKb) ?> Ko</span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>

    <!-- Timeline -->
    <?php if (count($history) > 1): ?>
    <div class="sgd-card">
        <div class="sgd-card-title"><i class="bi bi-clock-history"></i> Suivi</div>
        <div class="sgd-timeline">
            <?php foreach ($history as $h):
                $stNew = $statuts[$h['new_statut']] ?? [$h['new_statut'], 'sug-st-nouvelle'];
                $who = trim(($h['prenom'] ?? '') . ' ' . ($h['nom'] ?? ''));
            ?>
                <div class="sgd-tl-item">
                    <div class="sgd-tl-dot"></div>
                    <div class="sgd-tl-content">
                        <div><strong><?= h($stNew[0]) ?></strong> <?= $who ? '· ' . h($who) : '' ?></div>
                        <?php if ($h['motif']): ?><div class="text-muted small mt-1"><?= $h['motif'] /* HtmlSanitize côté serveur */ ?></div><?php endif ?>
                        <div class="sgd-tl-date"><?= h(fmt_date_fr($h['created_at'], 'd.m.Y H:i')) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
    <?php endif ?>

    <!-- Commentaires -->
    <div class="sgd-card">
        <div class="sgd-card-title"><i class="bi bi-chat-dots"></i> Discussion (<?= count($comments) ?>)</div>
        <div class="sgd-comments" id="sgdComments">
            <?php if (!$comments): ?>
                <div class="text-muted small">Aucun commentaire pour l'instant. Soyez le premier à réagir.</div>
            <?php else: foreach ($comments as $c):
                $who = trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')) ?: '—';
                $isAdminRole = ($c['role'] === 'admin');
            ?>
                <div class="sgd-comment <?= $isAdminRole ? 'admin' : '' ?>">
                    <div class="sgd-comment-head">
                        <span>
                            <strong><?= h($who) ?></strong>
                            <?php if ($isAdminRole): ?><span class="sgd-admin-tag">· Équipe</span><?php endif ?>
                        </span>
                        <span><?= h(fmt_relative($c['created_at'])) ?></span>
                    </div>
                    <div class="sgd-comment-body"><?= $c['content'] /* déjà sanitizé via HtmlSanitize côté serveur */ ?></div>
                </div>
            <?php endforeach; endif ?>
        </div>
        <form id="sgdCommentForm" data-sug-id="<?= h($sug['id']) ?>">
            <div class="sgd-rte-wrap">
                <div class="sgd-rte-toolbar">
                    <button type="button" class="sgd-rte-btn" data-cmd="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="sgd-rte-btn" data-cmd="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="sgd-rte-btn" data-cmd="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
                    <button type="button" class="sgd-rte-btn" data-cmd="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
                    <span class="sgd-rte-sep"></span>
                    <button type="button" class="sgd-rte-btn" data-cmd="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="sgd-rte-btn" data-cmd="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                    <span class="sgd-rte-sep"></span>
                    <button type="button" class="sgd-rte-btn" id="sgdEmojiBtn" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                </div>
                <div class="sgd-rte-editor" contenteditable="true" id="sgdCommentEditor" data-placeholder="Partagez votre avis ou apportez un complément…"></div>
                <div class="sgd-rte-footer">
                    <button type="submit" class="sgd-comment-send"><i class="bi bi-send"></i> Envoyer</button>
                </div>
            </div>
        </form>
    </div>
</div>
