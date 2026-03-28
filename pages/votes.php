<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['zt_user']['id'];
$initProposals = Db::fetchAll(
    "SELECT pp.id, pp.mois_annee, pp.label, pp.statut, pp.votes_pour, pp.votes_contre, pp.created_at
     FROM planning_proposals pp
     WHERE pp.statut = 'ouvert'
     ORDER BY pp.mois_annee DESC, pp.created_at"
);
foreach ($initProposals as &$p) {
    $myVote = Db::fetch("SELECT vote, commentaire FROM planning_votes WHERE proposal_id = ? AND user_id = ?", [$p['id'], $uid]);
    $p['my_vote'] = $myVote['vote'] ?? null;
    $p['my_comment'] = $myVote['commentaire'] ?? null;
    $votes = Db::fetchAll("SELECT vote, COUNT(*) as cnt FROM planning_votes WHERE proposal_id = ? GROUP BY vote", [$p['id']]);
    $p['votes_pour'] = 0; $p['votes_contre'] = 0;
    foreach ($votes as $v) {
        if ($v['vote'] === 'pour') $p['votes_pour'] = (int)$v['cnt'];
        if ($v['vote'] === 'contre') $p['votes_contre'] = (int)$v['cnt'];
    }
}
unset($p);
?>
<style>
.proposal-card {
  border-radius: 12px; border: 2px solid var(--border-color, #e1e5eb);
  transition: all 0.2s; overflow: hidden;
}
.proposal-card.voted-pour { border-color: #27ae60; }
.proposal-card.voted-contre { border-color: #e74c3c; }
.proposal-header {
  padding: 12px 16px; font-weight: 700; font-size: 1rem;
  background: var(--card-bg, #fff); display: flex; justify-content: space-between; align-items: center;
}
.proposal-body { padding: 12px 16px; }
.vote-bar { height: 8px; border-radius: 4px; background: #eee; overflow: hidden; margin: 8px 0; }
.vote-bar-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.vote-bar-pour { background: #27ae60; }
.vote-bar-contre { background: #e74c3c; }
.vote-btn-group { display: flex; gap: 8px; margin-top: 12px; }
.vote-btn {
  flex: 1; padding: 10px; border-radius: 8px; border: 2px solid #dee2e6;
  background: transparent; font-weight: 600; font-size: 0.9rem; cursor: pointer;
  transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;
}
.vote-btn:hover { border-color: #aaa; }
.vote-btn.pour { color: #27ae60; }
.vote-btn.pour:hover, .vote-btn.pour.active { background: rgba(39,174,96,0.1); border-color: #27ae60; }
.vote-btn.contre { color: #e74c3c; }
.vote-btn.contre:hover, .vote-btn.contre.active { background: rgba(231,76,60,0.1); border-color: #e74c3c; }
.proposal-planning { max-height: 300px; overflow-y: auto; margin-top: 12px; }
.proposal-planning table { font-size: 0.82rem; }
.proposal-planning th { position: sticky; top: 0; background: var(--card-bg, #fff); }
</style>

<div class="page-header">
  <h1><i class="bi bi-hand-thumbs-up"></i> Votes Planning</h1>
  <p>Votez pour la proposition de planning qui vous convient le mieux</p>
</div>

<div id="votesContainer">
  <div class="text-center text-muted" style="padding:3rem">
    <div class="spinner"></div> Chargement...
  </div>
</div>

<script type="application/json" id="__zt_ssr__"><?= json_encode(['proposals' => $initProposals], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
