<?php
// Accès : admin ou direction uniquement
if (!in_array($_SESSION['ss_user']['role'] ?? '', ['admin', 'direction'])) {
    echo '<div class="alert alert-warning m-3"><i class="bi bi-shield-lock"></i> Accès réservé à l\'administration et à la direction.</div>';
    return;
}

// Flag module
$flag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($flag !== '1') {
    echo '<div class="alert alert-info m-3"><i class="bi bi-info-circle"></i> Le module Suggestions est actuellement désactivé dans la config EMS (clé <code>allow_feature_requests</code>).</div>';
}

$stats = [
    'total'     => (int) Db::getOne("SELECT COUNT(*) FROM suggestions"),
    'nouvelle'  => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'nouvelle'"),
    'etudiee'   => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'etudiee'"),
    'planifiee' => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'planifiee'"),
    'en_dev'    => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'en_dev'"),
    'livree'    => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'livree'"),
    'critique'  => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE urgence = 'critique' AND statut NOT IN ('livree','refusee')"),
];

$statutLabels = [
    'nouvelle' => 'Nouvelle', 'etudiee' => 'Étudiée', 'planifiee' => 'Planifiée',
    'en_dev' => 'En développement', 'livree' => 'Livrée', 'refusee' => 'Refusée',
];
$serviceLabels = [
    'aide_soignant' => 'AS', 'infirmier' => 'INF', 'infirmier_chef' => 'IC', 'animation' => 'Anim',
    'cuisine' => 'Cuis', 'technique' => 'Tech', 'admin' => 'Admin', 'rh' => 'RH',
    'direction' => 'Dir', 'qualite' => 'Qual', 'autre' => 'Autre',
];
$catLabels = [
    'formulaire' => 'Formulaire', 'fonctionnalite' => 'Fonctionnalité', 'amelioration' => 'Amélioration',
    'alerte' => 'Alerte', 'bug' => 'Bug', 'question' => 'Question',
];
$urgenceLabels = [
    'critique' => ['Critique', 'sug-adm-urg-crit'],
    'eleve'    => ['Élevée',   'sug-adm-urg-eleve'],
    'moyen'    => ['Moyenne',  'sug-adm-urg-moy'],
    'faible'   => ['Faible',   'sug-adm-urg-faible'],
];
?>
<link rel="stylesheet" href="/newspocspace/admin/assets/css/emoji-picker.css?v=<?= APP_VERSION ?>">
<style>
.sug-adm-wrap { padding: 18px 22px; }

/* ── Rich text editor (réutilise le pattern alertes.php) ── */
.sug-rte-wrap { border: 1px solid var(--cl-border, #E8E5E0); border-radius: 8px; background: #fff; }
.sug-rte-toolbar {
    display: flex; align-items: center; gap: 2px; padding: 5px 7px; flex-wrap: wrap;
    border-bottom: 1px solid var(--cl-border, #E8E5E0);
    background: var(--cl-bg, #F7F5F2); border-radius: 8px 8px 0 0;
}
.sug-rte-btn {
    width: 28px; height: 28px; border: none; background: none; border-radius: 5px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--cl-text-muted, #888); transition: all .12s;
}
.sug-rte-btn:hover { background: #fff; color: var(--cl-text, #333); }
.sug-rte-sep { width: 1px; height: 18px; background: var(--cl-border, #E8E5E0); margin: 0 3px; }
.sug-rte-editor {
    width: 100%; min-height: 80px; max-height: 220px; padding: 10px 12px; overflow-y: auto;
    font-size: .88rem; line-height: 1.55; color: var(--cl-text, #333); outline: none;
}
.sug-rte-editor:empty:before {
    content: attr(data-placeholder); color: #aaa; pointer-events: none;
}
.sug-rte-editor p { margin: 0 0 4px; }
.sug-rte-editor ul, .sug-rte-editor ol { margin: 4px 0; padding-left: 20px; }

.sug-rte-footer {
    display: flex; align-items: center; gap: 10px; padding: 7px 10px;
    border-top: 1px solid var(--cl-border, #E8E5E0); background: var(--cl-bg, #F7F5F2);
    border-radius: 0 0 8px 8px;
}
.sug-rte-footer .form-check { margin: 0; }

/* Rendu HTML dans les commentaires affichés */
.sug-adm-comment-body p { margin: 0 0 .4em; }
.sug-adm-comment-body p:last-child { margin: 0; }
.sug-adm-comment-body ul, .sug-adm-comment-body ol { margin: 4px 0; padding-left: 20px; }
.sug-adm-comment-body strong { font-weight: 600; }
.sug-adm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
.sug-adm-stat {
    background: #fff; border: 1px solid #E8E4DE; border-radius: 10px;
    padding: 10px 14px; cursor: pointer; transition: all .15s;
}
.sug-adm-stat:hover { border-color: #C4A882; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,.04); }
.sug-adm-stat.active { border-color: #1A1A1A; background: #F7F5F2; box-shadow: 0 0 0 2px #1A1A1A; }
.sug-adm-stat-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .4px; color: #9B9B9B; font-weight: 700; }
.sug-adm-stat-value { font-size: 1.55rem; font-weight: 700; color: #1A1A1A; margin-top: 2px; line-height: 1; }

.sug-adm-toolbar {
    background: #fff; border: 1px solid #E8E4DE; border-radius: 10px;
    padding: 10px 14px; margin-bottom: 12px;
    display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
}
.sug-adm-toolbar select, .sug-adm-toolbar input {
    border: 1px solid #E8E4DE; border-radius: 6px; padding: 6px 10px; font-size: .84rem;
}
.sug-adm-toolbar .sug-search { flex: 1 1 200px; min-width: 160px; }

.sug-adm-table { background: #fff; border: 1px solid #E8E4DE; border-radius: 12px; overflow: hidden; }
.sug-adm-table table { margin: 0; font-size: .88rem; width: 100%; }
.sug-adm-table thead { background: #F7F5F2; }
.sug-adm-table th { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6c757d; padding: 10px 12px; text-align: left; border-bottom: 1px solid #E8E4DE; white-space: nowrap; }
.sug-adm-table td { padding: 10px 12px; border-bottom: 1px solid #F0EDE8; vertical-align: middle; }
.sug-adm-table tr { cursor: pointer; transition: background .1s; }
.sug-adm-table tr:hover td { background: #FAF9F6; }
.sug-adm-table tr:last-child td { border-bottom: none; }

.sug-adm-ref { font-family: ui-monospace, Menlo, monospace; font-size: .78rem; color: #6B6B6B; }
.sug-adm-title { font-weight: 600; color: #1A1A1A; }
.sug-adm-votes { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; font-variant-numeric: tabular-nums; }

.sug-adm-badge {
    display: inline-block; font-size: .68rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
}
.sug-adm-urg-crit   { background: #A85B4A; color: #fff; }
.sug-adm-urg-eleve  { background: #C4A882; color: #3a2e1a; }
.sug-adm-urg-moy    { background: #F5F4F0; color: #6B5B3E; border: 1px solid #E8E4DE; }
.sug-adm-urg-faible { background: #F5F4F0; color: #8A8680; border: 1px solid #E8E4DE; }
.sug-adm-st-nouvelle  { background: #2d4a43; color: #fff; }
.sug-adm-st-etudiee   { background: #C4A882; color: #3a2e1a; }
.sug-adm-st-planifiee { background: #5B4B6B; color: #fff; }
.sug-adm-st-en_dev    { background: #5B4B6B; color: #fff; }
.sug-adm-st-livree    { background: #bcd2cb; color: #2d4a43; }
.sug-adm-st-refusee   { background: #A85B4A; color: #fff; }

.sug-adm-author { display: flex; align-items: center; gap: 8px; }
.sug-adm-avatar { width: 28px; height: 28px; border-radius: 50%; background: #C4A882; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; flex-shrink: 0; }

/* Modal détail (Bootstrap standard) */
.sug-adm-section { margin-bottom: 16px; }
.sug-adm-section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; margin-bottom: 6px; }
.sug-adm-desc-box {
    background: #F7F5F2; border-left: 3px solid #C4A882; padding: 12px 16px;
    border-radius: 0 8px 8px 0; font-size: .9rem; line-height: 1.55; white-space: pre-wrap;
}

.sug-adm-status-form {
    background: #F7F5F2; border: 1px solid #E8E4DE; border-radius: 10px; padding: 14px 16px;
}
.sug-adm-status-form select, .sug-adm-status-form input, .sug-adm-status-form textarea {
    width: 100%; border: 1px solid #E8E4DE; border-radius: 6px; padding: 7px 10px; font-size: .88rem;
}
.sug-adm-status-form textarea { min-height: 70px; resize: vertical; }

.sug-adm-comments { display: flex; flex-direction: column; gap: 8px; }
.sug-adm-comment { padding: 10px 14px; border-radius: 8px; border: 1px solid #E8E4DE; background: #fff; font-size: .86rem; }
.sug-adm-comment.admin { background: #F7F5F2; border-left: 3px solid #2d4a43; }
.sug-adm-comment.internal { background: #fff4e6; border-left: 3px solid #C4A882; }
.sug-adm-comment-head { display: flex; justify-content: space-between; font-size: .76rem; color: #6c757d; margin-bottom: 4px; }
.sug-adm-comment-head strong { color: #1A1A1A; }

.sug-adm-top { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px; }
.sug-adm-top-item { background: #F7F5F2; border: 1px solid #E8E4DE; border-radius: 8px; padding: 8px 12px; cursor: pointer; transition: all .15s; font-size: .85rem; }
.sug-adm-top-item:hover { background: #bcd2cb33; border-color: #2d4a43; }
.sug-adm-top-votes { float: right; font-weight: 700; color: #2d4a43; }
</style>

<div class="sug-adm-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0"><i class="bi bi-lightbulb"></i> Suggestions & Demandes de développement</h2>
        <div>
            <label class="small me-2">Module :
                <select id="sugAdmFlag" class="form-select form-select-sm d-inline-block" style="width: auto;">
                    <option value="1" <?= $flag === '1' ? 'selected' : '' ?>>Activé</option>
                    <option value="0" <?= $flag !== '1' ? 'selected' : '' ?>>Désactivé</option>
                </select>
            </label>
        </div>
    </div>

    <!-- Stats cliquables -->
    <div class="sug-adm-stats">
        <div class="sug-adm-stat" data-filter-statut=""><div class="sug-adm-stat-label">Total</div><div class="sug-adm-stat-value"><?= $stats['total'] ?></div></div>
        <div class="sug-adm-stat" data-filter-statut="nouvelle"><div class="sug-adm-stat-label">Nouvelles</div><div class="sug-adm-stat-value"><?= $stats['nouvelle'] ?></div></div>
        <div class="sug-adm-stat" data-filter-statut="etudiee"><div class="sug-adm-stat-label">Étudiées</div><div class="sug-adm-stat-value"><?= $stats['etudiee'] ?></div></div>
        <div class="sug-adm-stat" data-filter-statut="planifiee"><div class="sug-adm-stat-label">Planifiées</div><div class="sug-adm-stat-value"><?= $stats['planifiee'] ?></div></div>
        <div class="sug-adm-stat" data-filter-statut="en_dev"><div class="sug-adm-stat-label">En dev</div><div class="sug-adm-stat-value"><?= $stats['en_dev'] ?></div></div>
        <div class="sug-adm-stat" data-filter-statut="livree"><div class="sug-adm-stat-label">Livrées</div><div class="sug-adm-stat-value"><?= $stats['livree'] ?></div></div>
        <div class="sug-adm-stat" data-filter-urgence="critique"><div class="sug-adm-stat-label" style="color:#A85B4A">Critique ouvertes</div><div class="sug-adm-stat-value" style="color:#A85B4A"><?= $stats['critique'] ?></div></div>
    </div>

    <!-- Top 10 votées -->
    <div class="sug-adm-section">
        <div class="sug-adm-section-title"><i class="bi bi-trophy"></i> Top 10 des suggestions les plus votées (ouvertes)</div>
        <div class="sug-adm-top" id="sugAdmTop"></div>
    </div>

    <!-- Filtres -->
    <div class="sug-adm-toolbar">
        <input type="text" class="sug-search" id="sugAdmSearch" placeholder="Rechercher (titre, description, référence)…">
        <select id="sugAdmFilterService">
            <option value="">Tous services</option>
            <?php foreach ($serviceLabels as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach ?>
        </select>
        <select id="sugAdmFilterCategorie">
            <option value="">Toutes catégories</option>
            <?php foreach ($catLabels as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach ?>
        </select>
        <select id="sugAdmFilterUrgence">
            <option value="">Toutes urgences</option>
            <option value="critique">Critique</option>
            <option value="eleve">Élevée</option>
            <option value="moyen">Moyenne</option>
            <option value="faible">Faible</option>
        </select>
    </div>

    <!-- Tableau -->
    <div class="sug-adm-table">
        <table>
            <thead>
                <tr>
                    <th>Réf.</th>
                    <th>Titre</th>
                    <th>Auteur</th>
                    <th>Service</th>
                    <th>Catégorie</th>
                    <th>Urgence</th>
                    <th>Statut</th>
                    <th>Votes</th>
                    <th>Créée</th>
                </tr>
            </thead>
            <tbody id="sugAdmTbody">
                <tr><td colspan="9" class="text-center text-muted py-4">Chargement…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal détail (pattern standard SpocCare/residents) -->
<div class="modal fade" id="sugAdmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 880px;">
        <div class="modal-content" style="display:flex; flex-direction:column; max-height:85vh;">
            <div class="modal-header" style="flex-shrink:0;">
                <h5 class="modal-title" id="sugAdmModalTitle"><i class="bi bi-lightbulb"></i> Détail</h5>
                <button type="button" class="ss-modal-close" data-bs-dismiss="modal" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body" id="sugAdmModalBody" style="flex:1; overflow-y:auto;">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
            <div class="modal-footer d-flex" style="flex-shrink:0;">
                <button type="button" class="btn btn-sm btn-outline-danger" id="sugAdmDelete">
                    <i class="bi bi-trash"></i> Supprimer
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-dismiss="modal">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>" src="/newspocspace/admin/assets/js/pages/suggestions.js?v=<?= APP_VERSION ?>"></script>
