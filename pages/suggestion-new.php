<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$flag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($flag !== '1') { http_response_code(403); exit; }

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// Mode édition si ?id=…
$isEdit = false;
$sug = null;
$editId = $_GET['id'] ?? '';
if ($editId) {
    $sug = Db::fetch("SELECT * FROM suggestions WHERE id = ? AND auteur_id = ?", [$editId, $uid]);
    if ($sug && $sug['statut'] === 'nouvelle') $isEdit = true;
}

// Référence auto
$year = date('Y');
$nb = (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE YEAR(created_at) = ?", [$year]);
$reference = $isEdit ? $sug['reference_code'] : sprintf('SUG-%d-%03d', $year, $nb + 1);

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
    'formulaire' => ['Formulaire à informatiser', 'bi-file-earmark-text'],
    'fonctionnalite' => ['Nouvelle fonctionnalité', 'bi-stars'],
    'amelioration' => ['Amélioration', 'bi-arrow-up-circle'],
    'alerte' => ['Alerte / Notification', 'bi-bell'],
    'bug' => ['Bug', 'bi-bug'],
    'question' => ['Question', 'bi-question-circle'],
];
$urgences = [
    'critique' => ['Critique',    '#A85B4A'],
    'eleve'    => ['Élevée',      '#C4A882'],
    'moyen'    => ['Moyenne',     '#6B5B3E'],
    'faible'   => ['Faible',      '#8A8680'],
];
$frequences = [
    'multi_jour' => 'Plusieurs fois par jour',
    'quotidien'  => 'Quotidien',
    'hebdo'      => 'Hebdomadaire',
    'mensuel'    => 'Mensuel',
    'ponctuel'   => 'Ponctuel',
];
$benefices = [
    'gain_temps' => ['Gain de temps', 'bi-speedometer'],
    'reduction_erreurs' => ['Réduction d\'erreurs', 'bi-shield-check'],
    'tracabilite' => ['Traçabilité', 'bi-search'],
    'conformite' => ['Conformité', 'bi-patch-check'],
    'confort_resident' => ['Confort résident', 'bi-heart'],
    'securite' => ['Sécurité', 'bi-shield-lock'],
];

$existingBenefices = $sug && $sug['benefices'] ? explode(',', $sug['benefices']) : [];
?>
<style>
.sgn-page { max-width: 860px; margin: 0 auto; }
.sgn-hdr-card {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 14px 18px; margin-bottom: 12px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px;
}
.sgn-hdr-info { flex: 1; min-width: 0; }
.sgn-hdr-user { font-size: .9rem; font-weight: 600; color: var(--cl-text); }
.sgn-hdr-date { font-size: .78rem; color: var(--cl-text-muted); margin-top: 2px; }
.sgn-ref-badge {
    border: 1.5px solid #2d4a43; border-radius: 10px; padding: 6px 14px; text-align: center;
}
.sgn-ref-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .6px; color: var(--cl-text-muted); }
.sgn-ref-value { font-size: .95rem; font-weight: 700; color: #2d4a43; font-family: ui-monospace, monospace; }

.sgn-card {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 16px 20px 18px; margin-bottom: 12px;
}
.sgn-card-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.sgn-card-ico {
    width: 36px; height: 36px; border-radius: 9px; background: #F5F4F0; color: #6B5B3E;
    display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;
}
.sgn-card-title { font-weight: 600; font-size: .95rem; }
.sgn-card-sub { font-size: .76rem; color: var(--cl-text-muted); }

.sgn-field { margin-bottom: 12px; }
.sgn-label { font-size: .76rem; font-weight: 600; color: var(--cl-text-muted); text-transform: uppercase; letter-spacing: .3px; display: block; margin-bottom: 4px; }
.sgn-label .req { color: #A85B4A; }
.sgn-input, .sgn-select, .sgn-textarea {
    width: 100%; padding: 8px 12px; border: 1.5px solid var(--cl-border-light); border-radius: 8px;
    font-size: .9rem; font-family: inherit; background: #fff;
}
.sgn-input:focus, .sgn-select:focus, .sgn-textarea:focus {
    outline: none; border-color: #C4A882; box-shadow: 0 0 0 3px rgba(196,168,130,.18);
}
.sgn-textarea { min-height: 110px; resize: vertical; }

.sgn-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.sgn-pill {
    border: 1.5px solid var(--cl-border-light); border-radius: 999px; padding: 7px 14px;
    font-size: .84rem; background: #fff; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px; transition: all .15s;
}
.sgn-pill:hover { background: #F7F5F2; }
.sgn-pill.selected {
    background: #bcd2cb33; border-color: #2d4a43; color: #2d4a43; font-weight: 600;
}

.sgn-urg-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.sgn-urg {
    border: 1.5px solid var(--cl-border-light); border-radius: 10px; padding: 10px 12px;
    background: #fff; text-align: center; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-size: .88rem; transition: all .15s;
}
.sgn-urg::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--urg); }
.sgn-urg:hover { background: #F7F5F2; }
.sgn-urg.selected { background: color-mix(in srgb, var(--urg) 15%, #fff); border-color: var(--urg); font-weight: 600; }
@media (max-width: 540px) { .sgn-urg-row { grid-template-columns: repeat(2, 1fr); } }

.sgn-benef-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; }
.sgn-benef {
    display: flex; align-items: center; gap: 9px; padding: 9px 12px;
    border: 1.5px solid var(--cl-border-light); border-radius: 8px; background: #fff; cursor: pointer;
    transition: all .15s; font-size: .86rem;
}
.sgn-benef input { accent-color: #2d4a43; width: 16px; height: 16px; }
.sgn-benef:hover { background: #F7F5F2; border-color: #C4A882; }
.sgn-benef.checked { background: #bcd2cb33; border-color: #2d4a43; }
.sgn-benef i { color: #6B5B3E; font-size: 1rem; }

.sgn-dropzone {
    border: 2px dashed var(--cl-border-light); border-radius: 10px; padding: 22px; text-align: center;
    background: #F7F5F2; transition: all .15s; cursor: pointer;
}
.sgn-dropzone.drag { border-color: #2d4a43; background: #bcd2cb33; }
.sgn-dropzone i { font-size: 1.8rem; color: #8A8680; }
.sgn-dropzone-label { font-size: .88rem; color: var(--cl-text); font-weight: 500; margin-top: 6px; }
.sgn-dropzone-hint { font-size: .76rem; color: var(--cl-text-muted); margin-top: 2px; }
.sgn-attach-list { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.sgn-attach-item {
    display: flex; align-items: center; gap: 10px; padding: 7px 10px;
    border: 1px solid var(--cl-border-light); border-radius: 7px; background: #fff; font-size: .82rem;
}
.sgn-attach-item .bi { color: #6B5B3E; }
.sgn-attach-item .sgn-attach-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sgn-attach-del { background: none; border: none; color: #A85B4A; cursor: pointer; padding: 2px 6px; }

.sgn-footer {
    display: flex; gap: 10px; justify-content: flex-end; padding-top: 6px;
}
.sgn-btn-primary {
    background: #2d4a43; color: #fff; border: none; padding: 10px 24px;
    border-radius: 8px; font-weight: 600; font-size: .92rem; cursor: pointer;
}
.sgn-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.sgn-btn-primary:hover:not(:disabled) { background: #1f3530; }
.sgn-btn-cancel {
    background: #fff; color: var(--cl-text); border: 1.5px solid var(--cl-border-light);
    padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: .92rem; cursor: pointer;
}
</style>

<div class="sgn-page page-wrap">
    <?= render_page_header(
        $isEdit ? 'Modifier la suggestion' : 'Nouvelle suggestion',
        'bi-lightbulb',
        'suggestions', 'Retour aux suggestions'
    ) ?>

    <div class="sgn-hdr-card">
        <div class="sgn-hdr-info">
            <div class="sgn-hdr-user"><i class="bi bi-person-circle"></i> <?= h(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))) ?></div>
            <div class="sgn-hdr-date"><?= h(date('d.m.Y, H:i')) ?></div>
        </div>
        <div class="sgn-ref-badge">
            <div class="sgn-ref-label">Référence</div>
            <div class="sgn-ref-value"><?= h($reference) ?></div>
        </div>
    </div>

    <form id="sgnForm" autocomplete="off" <?= $isEdit ? 'data-edit-id="' . h($sug['id']) . '"' : '' ?>>

        <!-- Titre + service -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-pencil"></i></div>
                <div>
                    <div class="sgn-card-title">Votre idée en une phrase</div>
                    <div class="sgn-card-sub">Soyez court et précis</div>
                </div>
            </div>
            <div class="sgn-field">
                <label class="sgn-label">Titre <span class="req">*</span></label>
                <input type="text" class="sgn-input" name="titre" maxlength="255" required
                       placeholder="Ex. Informatiser le suivi de continence"
                       value="<?= h($sug['titre'] ?? '') ?>">
            </div>
            <div class="sgn-field">
                <label class="sgn-label">Votre service / rôle <span class="req">*</span></label>
                <select class="sgn-select" name="service" required>
                    <?php foreach ($services as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= ($sug['service'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <!-- Catégorie -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-tag"></i></div>
                <div>
                    <div class="sgn-card-title">Catégorie</div>
                    <div class="sgn-card-sub">De quel type de demande s'agit-il ?</div>
                </div>
            </div>
            <div class="sgn-pills" data-sgn-group="categorie">
                <?php foreach ($categories as $k => [$lbl, $ico]):
                    $sel = ($sug['categorie'] ?? 'fonctionnalite') === $k;
                ?>
                    <div class="sgn-pill <?= $sel ? 'selected' : '' ?>" data-val="<?= h($k) ?>">
                        <i class="bi <?= h($ico) ?>"></i> <?= h($lbl) ?>
                    </div>
                <?php endforeach ?>
            </div>
            <input type="hidden" name="categorie" value="<?= h($sug['categorie'] ?? 'fonctionnalite') ?>">
        </div>

        <!-- Urgence -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="sgn-card-title">Urgence / Niveau de besoin</div>
                    <div class="sgn-card-sub">À quel point c'est bloquant aujourd'hui ?</div>
                </div>
            </div>
            <div class="sgn-urg-row" data-sgn-group="urgence">
                <?php foreach ($urgences as $k => [$lbl, $col]):
                    $sel = ($sug['urgence'] ?? 'moyen') === $k;
                ?>
                    <div class="sgn-urg <?= $sel ? 'selected' : '' ?>" data-val="<?= h($k) ?>" style="--urg: <?= h($col) ?>"><?= h($lbl) ?></div>
                <?php endforeach ?>
            </div>
            <input type="hidden" name="urgence" value="<?= h($sug['urgence'] ?? 'moyen') ?>">
        </div>

        <!-- Fréquence -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="sgn-card-title">Fréquence d'usage estimée</div>
                    <div class="sgn-card-sub">À quelle fréquence cette fonction serait utilisée ?</div>
                </div>
            </div>
            <div class="sgn-field">
                <select class="sgn-select" name="frequence">
                    <option value="">— non précisé —</option>
                    <?php foreach ($frequences as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= ($sug['frequence'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <!-- Description -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-chat-left-text"></i></div>
                <div>
                    <div class="sgn-card-title">Description détaillée</div>
                    <div class="sgn-card-sub">Contexte, situation actuelle, ce que vous souhaiteriez</div>
                </div>
            </div>
            <div class="sgn-field">
                <label class="sgn-label">Description <span class="req">*</span></label>
                <textarea class="sgn-textarea" name="description" minlength="10" maxlength="10000" required
                          placeholder="Décrivez la situation actuelle, le problème, et ce que vous aimeriez voir informatisé…"><?= h($sug['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Bénéfices -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="sgn-card-title">Bénéfices attendus</div>
                    <div class="sgn-card-sub">Cochez ceux qui s'appliquent (facultatif)</div>
                </div>
            </div>
            <div class="sgn-benef-grid">
                <?php foreach ($benefices as $k => [$lbl, $ico]):
                    $chk = in_array($k, $existingBenefices);
                ?>
                    <label class="sgn-benef <?= $chk ? 'checked' : '' ?>">
                        <input type="checkbox" name="benefices[]" value="<?= h($k) ?>" <?= $chk ? 'checked' : '' ?>>
                        <i class="bi <?= h($ico) ?>"></i>
                        <span><?= h($lbl) ?></span>
                    </label>
                <?php endforeach ?>
            </div>
        </div>

        <!-- Pièces jointes -->
        <div class="sgn-card">
            <div class="sgn-card-head">
                <div class="sgn-card-ico"><i class="bi bi-paperclip"></i></div>
                <div>
                    <div class="sgn-card-title">Pièces jointes (facultatif)</div>
                    <div class="sgn-card-sub">Photo du formulaire papier, capture d'écran, document exemple</div>
                </div>
            </div>
            <div class="sgn-dropzone" id="sgnDropzone">
                <i class="bi bi-cloud-upload"></i>
                <div class="sgn-dropzone-label">Glisser-déposer des fichiers ici</div>
                <div class="sgn-dropzone-hint">ou cliquez pour parcourir — max 10 Mo par fichier</div>
                <input type="file" id="sgnFileInput" multiple style="display:none"
                       accept="image/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            </div>
            <div class="sgn-attach-list" id="sgnAttachList"></div>
        </div>

        <div class="sgn-footer">
            <button type="button" class="sgn-btn-cancel" data-link="suggestions">Annuler</button>
            <button type="submit" class="sgn-btn-primary" id="sgnSubmit">
                <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Enregistrer les modifications' : 'Soumettre la suggestion' ?>
            </button>
        </div>

    </form>
</div>
