<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$proposals = Db::fetchAll(
    "SELECT pp.id, pp.mois_annee, pp.label, pp.statut, pp.created_at
     FROM planning_proposals pp
     WHERE pp.statut = 'ouvert'
     ORDER BY pp.mois_annee DESC, pp.created_at"
);

// Enrichir : vote de l'utilisateur + totaux
foreach ($proposals as &$p) {
    $myVote = Db::fetch(
        "SELECT vote, commentaire FROM planning_votes WHERE proposal_id = ? AND user_id = ?",
        [$p['id'], $uid]
    );
    $p['my_vote']    = $myVote['vote'] ?? null;
    $p['my_comment'] = $myVote['commentaire'] ?? null;

    $rows = Db::fetchAll(
        "SELECT vote, COUNT(*) AS cnt FROM planning_votes WHERE proposal_id = ? GROUP BY vote",
        [$p['id']]
    );
    $p['votes_pour'] = 0; $p['votes_contre'] = 0;
    foreach ($rows as $v) {
        if ($v['vote'] === 'pour')   $p['votes_pour']   = (int) $v['cnt'];
        if ($v['vote'] === 'contre') $p['votes_contre'] = (int) $v['cnt'];
    }
    $p['total_votes'] = $p['votes_pour'] + $p['votes_contre'];
    $p['pct_pour']    = $p['total_votes'] ? round(100 * $p['votes_pour']   / $p['total_votes']) : 0;
    $p['pct_contre']  = $p['total_votes'] ? round(100 * $p['votes_contre'] / $p['total_votes']) : 0;
}
unset($p);

function fmt_mois_annee($ym) {
    [$y, $m] = explode('-', $ym);
    $mois = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    return ($mois[(int)$m] ?? $m) . ' ' . $y;
}
?>
<div class="votes-wrap">
    <div class="mb-3">
        <h2 class="page-title mb-0"><i class="bi bi-hand-thumbs-up"></i> Votes Planning</h2>
        <p class="text-muted small mb-0">Votez pour la proposition de planning qui vous convient le mieux.</p>
    </div>

    <?php if (!$proposals): ?>
        <?= render_empty_state('Aucun vote en cours', 'bi-hand-thumbs-up', 'Les propositions de planning à voter apparaîtront ici.') ?>
    <?php else: foreach ($proposals as $p):
        $voted = $p['my_vote'] ? 'voted-' . $p['my_vote'] : '';
    ?>
        <div class="proposal-card <?= $voted ?> mb-3" data-proposal-id="<?= h($p['id']) ?>">
            <div class="proposal-header">
                <span><?= h(fmt_mois_annee($p['mois_annee'])) ?><?php if ($p['label']): ?> · <?= h($p['label']) ?><?php endif ?></span>
                <span class="text-muted small"><?= $p['total_votes'] ?> vote(s)</span>
            </div>
            <div class="proposal-body">
                <?php if ($p['total_votes'] > 0): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-success">Pour <?= $p['votes_pour'] ?> (<?= $p['pct_pour'] ?>%)</span>
                        <span class="text-danger">Contre <?= $p['votes_contre'] ?> (<?= $p['pct_contre'] ?>%)</span>
                    </div>
                    <div class="vote-bar">
                        <div class="vote-bar-fill vote-bar-pour" style="width:<?= $p['pct_pour'] ?>%"></div>
                    </div>
                <?php endif ?>

                <div class="vote-btn-group">
                    <button class="vote-btn pour<?= $p['my_vote'] === 'pour' ? ' active' : '' ?>" data-vote="pour">
                        <i class="bi bi-hand-thumbs-up"></i> Pour
                    </button>
                    <button class="vote-btn contre<?= $p['my_vote'] === 'contre' ? ' active' : '' ?>" data-vote="contre">
                        <i class="bi bi-hand-thumbs-down"></i> Contre
                    </button>
                </div>

                <?php if ($p['my_vote']): ?>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-check-circle"></i> Vous avez voté <strong><?= h($p['my_vote']) ?></strong>
                        <?php if ($p['my_comment']): ?> — « <?= h($p['my_comment']) ?> »<?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach; endif ?>
</div>
