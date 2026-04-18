<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// Mes fiches (exclut anonymes puisque auteur_id = NULL)
$mesFiches = Db::fetchAll(
    "SELECT f.*,
            (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments
     FROM fiches_amelioration f
     WHERE f.auteur_id = ?
     ORDER BY f.created_at DESC",
    [$uid]
);

// Fiches publiques
$publiques = Db::fetchAll(
    "SELECT f.*,
            (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom
     FROM fiches_amelioration f
     LEFT JOIN users u ON u.id = f.auteur_id
     WHERE f.visibility = 'public'
     ORDER BY f.created_at DESC
     LIMIT 100"
);

// Fiches où je suis concerné
$concernes = Db::fetchAll(
    "SELECT f.*,
            (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom
     FROM fiches_amelioration f
     INNER JOIN fiches_amelioration_concernes c ON c.fiche_id = f.id AND c.user_id = ?
     LEFT JOIN users u ON u.id = f.auteur_id
     ORDER BY f.created_at DESC",
    [$uid]
);

$statutLabels = [
    'soumise' => ['label' => 'Soumise', 'cls' => 'bg-teal'],
    'en_revue' => ['label' => 'En revue', 'cls' => 'bg-orange'],
    'en_cours' => ['label' => 'En cours', 'cls' => 'bg-purple'],
    'realisee' => ['label' => 'Réalisée', 'cls' => 'bg-green'],
    'rejetee' => ['label' => 'Rejetée', 'cls' => 'bg-red'],
];
$categorieLabels = [
    'securite' => 'Sécurité',
    'qualite_soins' => 'Qualité des soins',
    'organisation' => 'Organisation',
    'materiel' => 'Matériel',
    'communication' => 'Communication',
    'autre' => 'Autre',
];
$criticiteLabels = [
    'faible' => ['label' => 'Faible', 'cls' => 'bg-teal'],
    'moyenne' => ['label' => 'Moyenne', 'cls' => 'bg-orange'],
    'haute' => ['label' => 'Haute', 'cls' => 'bg-red'],
];

function fa_card_html(array $f, array $statutLabels, array $catLabels, array $criLabels, bool $showAuthor = true): string
{
    $st = $statutLabels[$f['statut']] ?? ['label' => $f['statut'], 'cls' => 'bg-neutral'];
    $cr = $criLabels[$f['criticite']] ?? ['label' => $f['criticite'], 'cls' => 'bg-neutral'];
    $cat = $catLabels[$f['categorie']] ?? $f['categorie'];
    $author = 'Anonyme';
    if ($showAuthor && empty($f['is_anonymous']) && !empty($f['auteur_prenom'])) {
        $author = h($f['auteur_prenom'] . ' ' . $f['auteur_nom']);
    }
    $date = date('d.m.Y', strtotime($f['created_at']));
    $desc = mb_substr(strip_tags($f['description']), 0, 140);
    if (strlen($f['description']) > 140) $desc .= '…';

    ob_start(); ?>
    <div class="fa-card" data-fiche-id="<?= h($f['id']) ?>" role="button" tabindex="0">
      <div class="fa-card-head">
        <div class="fa-card-title"><?= h($f['titre']) ?></div>
        <div class="fa-card-badges">
          <span class="fa-badge <?= h($cr['cls']) ?>"><?= h($cr['label']) ?></span>
          <span class="fa-badge <?= h($st['cls']) ?>"><?= h($st['label']) ?></span>
        </div>
      </div>
      <div class="fa-card-desc"><?= h($desc) ?></div>
      <div class="fa-card-foot">
        <span class="fa-cat"><i class="bi bi-tag"></i> <?= h($cat) ?></span>
        <?php if ($showAuthor): ?><span class="fa-author"><i class="bi bi-person"></i> <?= $author ?></span><?php endif ?>
        <span class="fa-date"><i class="bi bi-clock"></i> <?= h($date) ?></span>
        <?php if (!empty($f['nb_comments'])): ?><span class="fa-nbcomm"><i class="bi bi-chat-dots"></i> <?= (int)$f['nb_comments'] ?></span><?php endif ?>
      </div>
    </div>
    <?php return ob_get_clean();
}
?>
<style>
.fa-tabs { display:flex; gap:4px; background:var(--cl-card); padding:4px; border-radius:10px; border:1px solid var(--cl-border-light); margin-bottom:14px; }
.fa-tab { flex:1; padding:7px 12px; border:none; background:transparent; border-radius:7px; font-size:.88rem; color:var(--cl-text-muted); cursor:pointer; transition:all .15s; }
.fa-tab.active { background:var(--cl-bg); color:var(--cl-text); font-weight:600; }
.fa-tab-count { display:inline-block; margin-left:6px; background:rgba(0,0,0,.07); padding:1px 7px; border-radius:10px; font-size:.75rem; }

.fa-cards { display:grid; gap:10px; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); }
.fa-card { background:#fff; border:1px solid var(--cl-border-light); border-radius:10px; padding:14px; cursor:pointer; transition:all .15s; }
.fa-card:hover { border-color:#C4A882; box-shadow:0 2px 8px rgba(196,168,130,.15); transform:translateY(-1px); }
.fa-card:focus-visible { outline:2px solid #C4A882; outline-offset:2px; }
.fa-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:6px; }
.fa-card-title { font-size:.95rem; font-weight:600; color:var(--cl-text); line-height:1.3; }
.fa-card-badges { display:flex; gap:4px; flex-shrink:0; }
.fa-badge { font-size:.67rem; font-weight:600; padding:2px 7px; border-radius:4px; color:#fff; text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
.fa-badge.bg-teal { background:#2d4a43; }
.fa-badge.bg-green { background:#2d4a43; }
.fa-badge.bg-orange { background:#C4A882; color:#3a2e1a; }
.fa-badge.bg-red { background:#A85B4A; }
.fa-badge.bg-purple { background:#5B4B6B; }
.fa-badge.bg-neutral { background:#8a8478; }
.fa-card-desc { font-size:.82rem; color:var(--cl-text-muted); line-height:1.5; margin-bottom:8px; }
.fa-card-foot { display:flex; gap:12px; flex-wrap:wrap; font-size:.75rem; color:var(--cl-text-muted); }
.fa-card-foot i { font-size:.75rem; margin-right:2px; }

.fa-new-btn { background:#2d4a43; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-weight:600; font-size:.88rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.fa-new-btn:hover { background:#1f3530; }

/* Modal form */
.fa-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center; padding:20px; }
.fa-modal.show { display:flex; }
.fa-modal-inner { background:#fff; border-radius:12px; max-width:680px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 12px 40px rgba(0,0,0,.25); }
.fa-modal-head { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--cl-border-light); }
.fa-modal-head h5 { margin:0; font-size:1rem; font-weight:600; }
.fa-modal-close { width:32px; height:32px; border-radius:50%; border:1px solid #dee2e6; background:#fff; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
.fa-modal-body { padding:18px; }
.fa-modal-foot { padding:12px 18px; border-top:1px solid var(--cl-border-light); display:flex; justify-content:flex-end; gap:8px; }

.fa-label { font-size:.78rem; font-weight:600; color:var(--cl-text-secondary); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px; display:block; }
.fa-field { margin-bottom:14px; }
.fa-input, .fa-select, .fa-textarea {
  width:100%; padding:8px 12px; border:1.5px solid var(--cl-border-light); border-radius:8px; font-size:.9rem; font-family:inherit; background:#fff;
  transition:border-color .15s, box-shadow .15s;
}
.fa-input:focus, .fa-select:focus, .fa-textarea:focus {
  outline:none; border-color:#C4A882; box-shadow:0 0 0 3px rgba(196,168,130,.18);
}
.fa-textarea { min-height:90px; resize:vertical; }
.fa-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.fa-options { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:6px; }
.fa-opt { border:1.5px solid var(--cl-border-light); border-radius:8px; padding:9px 12px; cursor:pointer; text-align:center; font-size:.82rem; background:#fff; position:relative; transition:all .15s; }
.fa-opt:hover { background:#F7F5F2; }
.fa-opt.selected { border-color:#2d4a43; background:#bcd2cb33; color:#2d4a43; font-weight:600; }
.fa-opt.selected::after { content:"\f633"; font-family:"bootstrap-icons"; position:absolute; top:4px; right:4px; font-size:.8rem; color:#2d4a43; }
.fa-check-row { display:flex; gap:8px; align-items:center; padding:10px 12px; border:1.5px solid var(--cl-border-light); border-radius:8px; background:#fff; cursor:pointer; transition:all .15s; margin-bottom:6px; }
.fa-check-row input { accent-color:#2d4a43; width:16px; height:16px; }
.fa-check-row:hover { background:#F7F5F2; border-color:#C4A882; }

/* Detail drawer */
.fa-detail-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:12px; }
.fa-detail-title { font-size:1.1rem; font-weight:700; }
.fa-detail-meta { font-size:.78rem; color:var(--cl-text-muted); }
.fa-detail-section { margin-top:14px; }
.fa-detail-section h6 { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--cl-text-muted); margin-bottom:6px; }
.fa-detail-desc { background:#F7F5F2; border-left:3px solid #C4A882; padding:10px 14px; border-radius:0 8px 8px 0; font-size:.9rem; line-height:1.55; white-space:pre-wrap; }
.fa-comments { display:flex; flex-direction:column; gap:8px; }
.fa-comment { padding:10px 12px; border-radius:8px; border:1px solid var(--cl-border-light); background:#fff; font-size:.85rem; }
.fa-comment.admin { background:#F7F5F2; border-left:3px solid #2d4a43; }
.fa-comment-head { display:flex; justify-content:space-between; font-size:.75rem; color:var(--cl-text-muted); margin-bottom:4px; }
.fa-comment-body { line-height:1.5; white-space:pre-wrap; }

.fa-rdv-card { border:1px solid var(--cl-border-light); border-radius:10px; padding:12px; background:#fff; margin-bottom:8px; }
.fa-rdv-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.fa-rdv-date { font-weight:600; }
.fa-rdv-actions { display:flex; gap:6px; margin-top:8px; }
.fa-btn-accept { background:#2d4a43; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:.82rem; }
.fa-btn-refuse { background:#fff; color:#A85B4A; border:1px solid #A85B4A; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:.82rem; }

.fa-user-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.fa-user-chip { background:#F7F5F2; border:1px solid var(--cl-border-light); border-radius:999px; padding:3px 10px 3px 3px; display:inline-flex; align-items:center; gap:6px; font-size:.8rem; }
.fa-user-chip .avatar-mini { width:22px; height:22px; border-radius:50%; background:#C4A882; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:700; }
.fa-user-chip button { background:none; border:none; color:var(--cl-text-muted); padding:0; margin-left:2px; cursor:pointer; }
.fa-user-search-results { border:1px solid var(--cl-border-light); border-radius:8px; margin-top:4px; max-height:180px; overflow:auto; }
.fa-user-search-item { padding:8px 10px; cursor:pointer; font-size:.85rem; border-bottom:1px solid var(--cl-border-light); }
.fa-user-search-item:hover { background:#F7F5F2; }
.fa-user-search-item:last-child { border-bottom:none; }
</style>

<div class="page-wrap">
    <?= render_page_header('Fiches d\'amélioration continue', 'bi-lightbulb', null, null, '<button class="fa-new-btn" data-link="fiche-amelioration-new"><i class="bi bi-plus-lg"></i> Nouvelle fiche</button>') ?>

    <div class="row g-3 mb-3">
        <?= render_stat_card('Mes fiches', count($mesFiches), 'bi-person', 'teal') ?>
        <?= render_stat_card('Publiques', count($publiques), 'bi-globe', 'purple') ?>
        <?= render_stat_card('Me concernant', count($concernes), 'bi-person-check', 'orange') ?>
    </div>

    <div class="fa-tabs" role="tablist">
        <button class="fa-tab active" data-fa-tab="mes">Mes fiches <span class="fa-tab-count"><?= count($mesFiches) ?></span></button>
        <button class="fa-tab" data-fa-tab="publiques">Publiques <span class="fa-tab-count"><?= count($publiques) ?></span></button>
        <button class="fa-tab" data-fa-tab="concerne">Me concernant <span class="fa-tab-count"><?= count($concernes) ?></span></button>
    </div>

    <div id="faPaneMes" class="fa-pane">
        <?php if (!$mesFiches): ?>
            <?= render_empty_state('Aucune fiche soumise', 'bi-lightbulb', 'Cliquez sur "Nouvelle fiche" pour en créer une') ?>
        <?php else: ?>
            <div class="fa-cards">
                <?php foreach ($mesFiches as $f) echo fa_card_html($f, $statutLabels, $categorieLabels, $criticiteLabels, false) ?>
            </div>
        <?php endif ?>
    </div>
    <div id="faPanePubliques" class="fa-pane" style="display:none">
        <?php if (!$publiques): ?>
            <?= render_empty_state('Aucune fiche publique') ?>
        <?php else: ?>
            <div class="fa-cards">
                <?php foreach ($publiques as $f) echo fa_card_html($f, $statutLabels, $categorieLabels, $criticiteLabels, true) ?>
            </div>
        <?php endif ?>
    </div>
    <div id="faPaneConcerne" class="fa-pane" style="display:none">
        <?php if (!$concernes): ?>
            <?= render_empty_state('Aucune fiche ne vous concerne pour l\'instant') ?>
        <?php else: ?>
            <div class="fa-cards">
                <?php foreach ($concernes as $f) echo fa_card_html($f, $statutLabels, $categorieLabels, $criticiteLabels, true) ?>
            </div>
        <?php endif ?>
    </div>
</div>

<!-- Detail modal -->
<div class="fa-modal" id="faDetailModal" aria-hidden="true">
  <div class="fa-modal-inner">
    <div class="fa-modal-head">
      <h5 id="faDetailTitle"><i class="bi bi-lightbulb"></i> Détail de la fiche</h5>
      <button class="fa-modal-close" data-fa-close="detail"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="fa-modal-body" id="faDetailBody">
      <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
    </div>
  </div>
</div>

