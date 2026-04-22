<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

// Module désactivé → redirige vers accueil
$flag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($flag !== '1') {
    echo '<div class="page-wrap">';
    echo render_empty_state('Module Suggestions désactivé par l\'établissement.', 'bi-lightbulb-off');
    echo '</div>';
    return;
}

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// Tous les labels
$services = [
    'aide_soignant' => 'Aide-soignant·e',
    'infirmier' => 'Infirmier·e',
    'infirmier_chef' => 'Infirmier·e chef',
    'animation' => 'Animation',
    'cuisine' => 'Cuisine / Hôtellerie',
    'technique' => 'Technique',
    'admin' => 'Administration',
    'rh' => 'RH',
    'direction' => 'Direction',
    'qualite' => 'Qualité',
    'autre' => 'Autre',
];
$categories = [
    'formulaire' => 'Formulaire à informatiser',
    'fonctionnalite' => 'Nouvelle fonctionnalité',
    'amelioration' => 'Amélioration',
    'alerte' => 'Alerte / Notification',
    'bug' => 'Bug',
    'question' => 'Question',
];
$urgences = [
    'critique' => ['label' => 'Critique', 'cls' => 'sug-urg-critique'],
    'eleve'    => ['label' => 'Élevée',   'cls' => 'sug-urg-eleve'],
    'moyen'    => ['label' => 'Moyenne',  'cls' => 'sug-urg-moyen'],
    'faible'   => ['label' => 'Faible',   'cls' => 'sug-urg-faible'],
];
$statuts = [
    'nouvelle'   => ['label' => 'Nouvelle',   'cls' => 'sug-st-nouvelle'],
    'etudiee'    => ['label' => 'Étudiée',    'cls' => 'sug-st-etudiee'],
    'planifiee'  => ['label' => 'Planifiée',  'cls' => 'sug-st-planifiee'],
    'en_dev'     => ['label' => 'En développement', 'cls' => 'sug-st-endev'],
    'livree'     => ['label' => 'Livrée',     'cls' => 'sug-st-livree'],
    'refusee'    => ['label' => 'Refusée',    'cls' => 'sug-st-refusee'],
];

// Chargement initial : toutes les suggestions, triées par votes
$rows = Db::fetchAll(
    "SELECT s.*,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo,
            EXISTS(SELECT 1 FROM suggestions_votes v WHERE v.suggestion_id = s.id AND v.user_id = ?) AS has_voted
     FROM suggestions s
     LEFT JOIN users u ON u.id = s.auteur_id
     ORDER BY s.votes_count DESC, s.created_at DESC
     LIMIT 500",
    [$uid]
);

$nbMine = (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE auteur_id = ?", [$uid]);
$nbVoted = (int) Db::getOne("SELECT COUNT(*) FROM suggestions_votes WHERE user_id = ?", [$uid]);
$nbNouvelles = (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'nouvelle'");
$nbLivrees = (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'livree'");
?>
<style>
.sug-page { max-width: 1200px; margin: 0 auto; }

.sug-toolbar {
    display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 10px;
    padding: 10px 12px; margin-bottom: 14px;
}
.sug-toolbar select, .sug-toolbar input {
    border: 1px solid var(--cl-border-light); border-radius: 7px; padding: 6px 10px;
    font-size: .84rem; background: #fff;
}
.sug-toolbar .sug-search { flex: 1 1 180px; min-width: 160px; }

.sug-tabs {
    display: flex; gap: 4px; background: #fff; padding: 4px; border-radius: 10px;
    border: 1px solid var(--cl-border-light); margin-bottom: 12px;
}
.sug-tab {
    flex: 1; padding: 7px 12px; border: none; background: transparent; border-radius: 7px;
    font-size: .86rem; color: var(--cl-text-muted); cursor: pointer; transition: all .15s;
}
.sug-tab.active { background: var(--cl-bg); color: var(--cl-text); font-weight: 600; }
.sug-tab-count { margin-left: 6px; background: rgba(0,0,0,.07); padding: 1px 7px; border-radius: 10px; font-size: .72rem; }

.sug-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); }

.sug-card {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 14px 14px 12px; display: flex; flex-direction: column; gap: 8px;
    transition: all .15s; position: relative;
}
.sug-card:hover { border-color: #C4A882; box-shadow: 0 3px 12px rgba(196,168,130,.15); transform: translateY(-1px); }

.sug-card-head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
}
.sug-card-title {
    font-size: .97rem; font-weight: 600; color: var(--cl-text); line-height: 1.32;
    cursor: pointer; flex: 1; min-width: 0;
}
.sug-card-title:hover { color: #2d4a43; }

.sug-ref {
    font-size: .68rem; font-family: ui-monospace, Menlo, monospace;
    color: var(--cl-text-muted); letter-spacing: .3px;
}

.sug-badges { display: flex; gap: 4px; flex-wrap: wrap; }
.sug-badge {
    font-size: .68rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
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

.sug-desc { font-size: .84rem; color: var(--cl-text-muted); line-height: 1.5; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

.sug-meta {
    display: flex; flex-wrap: wrap; gap: 10px; font-size: .74rem; color: var(--cl-text-muted);
    padding-top: 6px; border-top: 1px dashed var(--cl-border-light);
}
.sug-meta i { font-size: .8rem; margin-right: 2px; }

.sug-actions { display: flex; gap: 6px; align-items: center; justify-content: space-between; }

.sug-vote-btn {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1.5px solid var(--cl-border-light); border-radius: 999px;
    padding: 5px 12px; background: #fff; cursor: pointer; font-size: .82rem;
    font-weight: 600; color: var(--cl-text); transition: all .15s;
}
.sug-vote-btn:hover { border-color: #2d4a43; color: #2d4a43; }
.sug-vote-btn.voted { background: #bcd2cb; border-color: #2d4a43; color: #2d4a43; }
.sug-vote-btn .sug-vote-count { font-weight: 700; font-variant-numeric: tabular-nums; }

.sug-comment-link {
    font-size: .78rem; color: var(--cl-text-muted); text-decoration: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px;
}
.sug-comment-link:hover { color: #2d4a43; }

.sug-new-btn {
    background: #2d4a43; color: #fff; border: none; padding: 8px 16px;
    border-radius: 8px; font-weight: 600; font-size: .88rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.sug-new-btn:hover { background: #1f3530; }
</style>

<div class="sug-page page-wrap">
    <?= render_page_header(
        'Suggestions & Demandes',
        'bi-lightbulb',
        null, null,
        '<button class="sug-new-btn" data-link="suggestion-new"><i class="bi bi-plus-lg"></i> Nouvelle suggestion</button>'
    ) ?>

    <div class="row g-3 mb-3">
        <?= render_stat_card('Toutes', count($rows), 'bi-card-list', 'teal') ?>
        <?= render_stat_card('Nouvelles', $nbNouvelles, 'bi-lightbulb', 'orange') ?>
        <?= render_stat_card('Mes suggestions', $nbMine, 'bi-person', 'purple') ?>
        <?= render_stat_card('Mes votes', $nbVoted, 'bi-hand-thumbs-up', 'green') ?>
        <?= render_stat_card('Livrées', $nbLivrees, 'bi-check-circle', 'green') ?>
    </div>

    <div class="sug-tabs" role="tablist">
        <button class="sug-tab active" data-sug-tab="tous">Toutes <span class="sug-tab-count" id="sugCountTous"><?= count($rows) ?></span></button>
        <button class="sug-tab" data-sug-tab="mes">Mes suggestions <span class="sug-tab-count"><?= $nbMine ?></span></button>
        <button class="sug-tab" data-sug-tab="votes">J'ai voté <span class="sug-tab-count"><?= $nbVoted ?></span></button>
    </div>

    <div class="sug-toolbar">
        <input type="text" class="sug-search" id="sugSearch" placeholder="Rechercher…" autocomplete="off">
        <select id="sugFilterService">
            <option value="">Tous services</option>
            <?php foreach ($services as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
            <?php endforeach ?>
        </select>
        <select id="sugFilterCategorie">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
            <?php endforeach ?>
        </select>
        <select id="sugFilterStatut">
            <option value="">Tous statuts</option>
            <?php foreach ($statuts as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v['label']) ?></option>
            <?php endforeach ?>
        </select>
        <select id="sugSort">
            <option value="votes">Tri : votes</option>
            <option value="date">Tri : date</option>
            <option value="urgence">Tri : urgence</option>
        </select>
    </div>

    <div id="sugList">
        <?php if (!$rows): ?>
            <?= render_empty_state(
                'Aucune suggestion pour l\'instant. Soyez le premier à en proposer une !',
                'bi-lightbulb',
                'Cliquez sur "Nouvelle suggestion" en haut de la page'
            ) ?>
        <?php else: ?>
            <div class="sug-grid">
            <?php foreach ($rows as $s):
                $urg = $urgences[$s['urgence']] ?? $urgences['moyen'];
                $st  = $statuts[$s['statut']]   ?? $statuts['nouvelle'];
                $svc = $services[$s['service']] ?? $s['service'];
                $cat = $categories[$s['categorie']] ?? $s['categorie'];
                $auteur = trim(($s['auteur_prenom'] ?? '') . ' ' . ($s['auteur_nom'] ?? '')) ?: '—';
                $descTxt = mb_substr(strip_tags($s['description']), 0, 220);
            ?>
                <article class="sug-card"
                         data-sug-id="<?= h($s['id']) ?>"
                         data-mine="<?= $s['auteur_id'] === $uid ? '1' : '0' ?>"
                         data-voted="<?= $s['has_voted'] ? '1' : '0' ?>"
                         data-service="<?= h($s['service']) ?>"
                         data-categorie="<?= h($s['categorie']) ?>"
                         data-statut="<?= h($s['statut']) ?>"
                         data-urgence="<?= h($s['urgence']) ?>">
                    <div class="sug-card-head">
                        <div class="sug-card-title" data-sug-open="<?= h($s['id']) ?>"><?= h($s['titre']) ?></div>
                        <div class="sug-badges">
                            <span class="sug-badge <?= h($urg['cls']) ?>"><?= h($urg['label']) ?></span>
                            <span class="sug-badge <?= h($st['cls']) ?>"><?= h($st['label']) ?></span>
                        </div>
                    </div>
                    <div class="sug-ref"><?= h($s['reference_code']) ?> · <?= h($cat) ?> · <?= h($svc) ?></div>
                    <div class="sug-desc"><?= h($descTxt) ?></div>
                    <div class="sug-meta">
                        <span><i class="bi bi-person"></i> <?= h($auteur) ?></span>
                        <span><i class="bi bi-clock"></i> <?= h(fmt_relative($s['created_at'])) ?></span>
                    </div>
                    <div class="sug-actions">
                        <button class="sug-vote-btn <?= $s['has_voted'] ? 'voted' : '' ?>"
                                data-sug-vote="<?= h($s['id']) ?>"
                                title="<?= $s['has_voted'] ? 'Retirer mon vote' : 'Voter pour cette suggestion' ?>">
                            <i class="bi bi-hand-thumbs-up<?= $s['has_voted'] ? '-fill' : '' ?>"></i>
                            <span class="sug-vote-count"><?= (int)$s['votes_count'] ?></span>
                        </button>
                        <a class="sug-comment-link" data-sug-open="<?= h($s['id']) ?>">
                            <i class="bi bi-chat-dots"></i> <?= (int)$s['comments_count'] ?> commentaire<?= $s['comments_count'] > 1 ? 's' : '' ?>
                        </a>
                    </div>
                </article>
            <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>
