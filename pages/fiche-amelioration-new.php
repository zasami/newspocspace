<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// Récupère infos user
$userData = Db::fetch(
    "SELECT u.prenom, u.nom, f.nom AS fonction_nom
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.id = ?",
    [$uid]
);
$userLabel = trim(($userData['prenom'] ?? '') . ' ' . ($userData['nom'] ?? ''));

// EMS + modules (unités)
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';
$modules = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre, code");

// Référence auto-générée : FAC-YYYY-NNN
$year = date('Y');
$nbThisYear = (int) Db::getOne(
    "SELECT COUNT(*) FROM fiches_amelioration WHERE YEAR(created_at) = ?",
    [$year]
);
$reference = sprintf('FAC-%d-%03d', $year, $nbThisYear + 1);
$dateToday = date('d.m.Y, H:i');
?>
<style>
.fan-page { max-width: 1080px; margin: 0 auto; }
.fan-label-sm { font-size: .82rem; }
.fan-hdr-card {
    background: #fff; border: 1px solid var(--cl-border-light); border-radius: 12px;
    padding: 18px 20px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px;
}
.fan-hdr-icon {
    width: 44px; height: 44px; border-radius: 10px; background: var(--cl-bg, #F7F5F2);
    color: var(--cl-teal-fg, #2d4a43); display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.fan-ref-badge {
    border: 1.5px solid var(--cl-teal-fg, #2d4a43); border-radius: 10px; padding: 6px 14px;
    text-align: center; flex-shrink: 0;
}
.fan-ref-badge-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .6px; color: var(--cl-text-muted); margin-bottom: 1px; }
.fan-ref-badge-value { font-size: .95rem; font-weight: 700; color: var(--cl-teal-fg, #2d4a43); font-family: ui-monospace, Menlo, monospace; }

.fan-card {
    background: #fff; border: 1px solid var(--cl-border-light, #EAEAEA); border-radius: 12px;
    padding: 18px 22px 20px; margin-bottom: 14px;
    transition: border-color .15s, box-shadow .15s;
}
.fan-card:hover { border-color: var(--cl-border, #DDDDDD); }
.fan-card-head {
    display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
}
.fan-card-ico {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: var(--cl-bg, #F5F4F0);
    color: var(--cl-text-muted, #8A8680);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.05rem;
}
.fan-card-title-wrap { flex: 1; min-width: 0; }
.fan-card-title { font-weight: 600; font-size: .98rem; color: var(--cl-text, #1A1A1A); line-height: 1.3; }
.fan-card-sub { font-size: .78rem; color: var(--cl-text-muted, #8A8680); margin-top: 2px; }
.fan-card-tag { font-size: .7rem; padding: 3px 9px; background: var(--cl-bg, #F5F4F0); border: 1px solid var(--cl-border-light); border-radius: 5px; color: var(--cl-text-muted); flex-shrink: 0; }

/* Pills (personnes, visibilité) — style cohérent avec les badges palette */
.fan-pill-group { display: flex; flex-wrap: wrap; gap: 6px; }
.fan-pill {
    border: 1.5px solid var(--cl-border-light); border-radius: 999px; padding: 6px 14px;
    font-size: .82rem; background: #fff; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s, border-color .15s, color .15s;
}
.fan-pill:hover { background: var(--cl-bg, #F7F5F2); }
.fan-pill.selected {
    background: var(--cl-orange-bg, #D4C4A8); border-color: var(--cl-orange-bg, #D4C4A8);
    color: var(--cl-orange-fg, #6B5B3E); font-weight: 600;
}

/* Gravité — 3 colonnes, palette */
.fan-sev-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.fan-sev {
    border: 1.5px solid var(--cl-border-light); border-radius: 10px; padding: 10px 12px;
    background: #fff; text-align: center; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-size: .88rem; transition: background .15s, border-color .15s;
}
.fan-sev::before { content: ''; width: 8px; height: 8px; border-radius: 50%; }
.fan-sev[data-val="faible"]::before { background: var(--cl-teal-fg, #2d4a43); }
.fan-sev[data-val="moyenne"]::before { background: var(--cl-orange-fg, #6B5B3E); }
.fan-sev[data-val="haute"]::before { background: var(--cl-red-fg, #7B3B2C); }
.fan-sev:hover { background: var(--cl-bg, #F7F5F2); }
.fan-sev[data-val="faible"].selected  { background: var(--cl-teal-bg); border-color: var(--cl-teal-fg); color: var(--cl-teal-fg); }
.fan-sev[data-val="moyenne"].selected { background: var(--cl-orange-bg); border-color: var(--cl-orange-fg); color: var(--cl-orange-fg); }
.fan-sev[data-val="haute"].selected   { background: var(--cl-red-bg); border-color: var(--cl-red-fg); color: var(--cl-red-fg); }

/* Rich editor (contenteditable + toolbar) */
.fan-rich-wrap {
    border: 1px solid var(--cl-border, #DDDDDD); border-radius: 8px;
    overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s;
}
.fan-rich-wrap:focus-within {
    border-color: var(--cl-accent, #191918);
    box-shadow: 0 0 0 3px rgba(25,25,24,.08);
}
.fan-rich-toolbar {
    display: flex; align-items: center; gap: 1px;
    background: #fff; border-bottom: 1px solid var(--cl-border-light);
    padding: 4px 6px;
}
.fan-rich-btn {
    width: 28px; height: 28px; border: none; background: none; border-radius: 5px;
    color: var(--cl-text-muted, #6c757d); cursor: pointer; font-size: .85rem;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .1s, color .1s;
}
.fan-rich-btn:hover { background: var(--cl-bg, #F5F4F0); color: var(--cl-text, #1A1A1A); }
.fan-rich-btn.active { background: var(--cl-bg, #F5F4F0); color: var(--cl-text, #1A1A1A); }
.fan-rich-sep { width: 1px; height: 16px; background: var(--cl-border-light); margin: 0 3px; }
.fan-rich-editor {
    min-height: 90px; padding: 10px 12px; font-size: .88rem; line-height: 1.55;
    outline: none; background: #fff; overflow-y: auto; max-height: 300px;
}
.fan-rich-editor:empty::before {
    content: attr(data-placeholder); color: var(--cl-text-muted); font-style: italic; pointer-events: none;
}
.fan-rich-editor p { margin: 0 0 .5em; }
.fan-rich-editor ul, .fan-rich-editor ol { margin: 0 0 .5em 1.4em; padding: 0; }
.fan-rich-editor ul.checklist { list-style: none; margin-left: 0; padding-left: 0; }
.fan-rich-editor ul.checklist li { position: relative; padding-left: 26px; margin-bottom: 4px; }
.fan-rich-editor ul.checklist li::before {
    content: ''; position: absolute; left: 0; top: 4px;
    width: 16px; height: 16px; border: 1.5px solid var(--cl-border); border-radius: 3px; background: #fff;
}
.fan-rich-editor ul.checklist li.checked { text-decoration: line-through; color: var(--cl-text-muted); }
.fan-rich-editor ul.checklist li.checked::before {
    background: var(--cl-teal-fg, #2d4a43); border-color: var(--cl-teal-fg, #2d4a43);
    content: '\f633'; font-family: 'bootstrap-icons'; color: #fff; font-size: 10px;
    display: flex; align-items: center; justify-content: center;
}

/* Dropzone */
.fan-drop {
    border: 1.5px dashed var(--cl-border-light); border-radius: 10px; padding: 26px 20px;
    text-align: center; cursor: pointer; transition: background .15s, border-color .15s;
    background: #fff;
}
.fan-drop:hover, .fan-drop.drag {
    background: var(--cl-bg, #F7F5F2); border-color: var(--cl-border, #E8E5E0);
}
.fan-drop i { font-size: 1.4rem; color: var(--cl-text-muted); display: block; margin-bottom: 6px; }
.fan-drop-text { font-size: .86rem; color: var(--cl-text-muted); }
.fan-drop-hint { font-size: .74rem; color: var(--cl-text-muted); margin-top: 4px; }
.fan-drop-file { margin-top: 10px; font-size: .85rem; color: var(--cl-teal-fg); font-weight: 500; }

</style>

<div class="page-wrap fan-page">
    <?= render_page_header('Nouvelle fiche d\'amélioration', 'bi-shield-check', 'fiches-amelioration', 'Amélioration continue') ?>

    <!-- Header card -->
    <div class="fan-hdr-card">
        <div class="fan-hdr-icon"><i class="bi bi-shield-check"></i></div>
        <div style="flex:1; min-width:0">
            <div class="fw-bold" style="font-size:1.05rem">Fiche d'amélioration continue</div>
            <div class="text-muted small">Signalement d'un incident, dysfonctionnement ou suggestion — <?= h($emsNom) ?></div>
        </div>
        <div class="fan-ref-badge">
            <div class="fan-ref-badge-label">Référence</div>
            <div class="fan-ref-badge-value">#<?= h($reference) ?></div>
        </div>
    </div>

    <!-- Section 1: Identification -->
    <div class="fan-card">
        <div class="fan-card-head">
            <div class="fan-card-ico"><i class="bi bi-person"></i></div>
            <div class="fan-card-title-wrap">
                <div class="fan-card-title">Identification</div>
                <div class="fan-card-sub">Section 1 · Auteur, date et unité</div>
            </div>
            <span class="fan-card-tag">Auto-rempli</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold fan-label-sm">Auteur <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" value="<?= h($userLabel) ?>" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold fan-label-sm">Date de saisie</label>
                <input type="text" class="form-control form-control-sm" value="<?= h($dateToday) ?>" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold fan-label-sm">Unité / Département <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="fanUnite">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= h($m['id']) ?>"><?= h($m['code'] . ' — ' . $m['nom']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Section 2: Qualification -->
    <div class="fan-card">
        <div class="fan-card-head">
            <div class="fan-card-ico"><i class="bi bi-exclamation-circle"></i></div>
            <div class="fan-card-title-wrap">
                <div class="fan-card-title">Qualification de l'événement</div>
                <div class="fan-card-sub">Section 2 · Type, catégorie, personnes et gravité</div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold fan-label-sm">Type d'événement <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="fanType">
                    <option value="">— Sélectionner —</option>
                    <option value="incident">Incident</option>
                    <option value="dysfonctionnement">Dysfonctionnement</option>
                    <option value="suggestion" selected>Suggestion d'amélioration</option>
                    <option value="non_conformite">Non-conformité</option>
                    <option value="plainte">Plainte / réclamation</option>
                    <option value="presque_accident">Presque-accident (near miss)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fan-label-sm">Catégorie <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="fanCategorie">
                    <option value="">— Sélectionner —</option>
                    <option value="qualite_soins">Soins / médical</option>
                    <option value="autre">Hôtellerie / restauration</option>
                    <option value="securite">Sécurité des personnes</option>
                    <option value="organisation">Organisation / process</option>
                    <option value="communication">Communication</option>
                    <option value="materiel">Matériel / équipement</option>
                    <option value="autre">Ressources humaines</option>
                    <option value="autre">Informatique</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fan-label-sm">Personnes concernées</label>
            <div class="fan-pill-group" id="fanPersonnes">
                <div class="fan-pill" data-val="resident"><i class="bi bi-person-hearts"></i> Résident</div>
                <div class="fan-pill" data-val="collaborateur"><i class="bi bi-person-badge"></i> Collaborateur</div>
                <div class="fan-pill" data-val="visiteur"><i class="bi bi-people"></i> Visiteur / Famille</div>
                <div class="fan-pill" data-val="prestataire"><i class="bi bi-tools"></i> Prestataire externe</div>
            </div>
        </div>
        <div class="mb-0">
            <label class="form-label fw-semibold fan-label-sm">Niveau de gravité <span class="text-danger">*</span></label>
            <div class="fan-sev-row" id="fanGravite">
                <div class="fan-sev" data-val="faible">Mineur</div>
                <div class="fan-sev selected" data-val="moyenne">Modéré</div>
                <div class="fan-sev" data-val="haute">Majeur</div>
            </div>
        </div>
    </div>

    <!-- Section 3: Description -->
    <div class="fan-card">
        <div class="fan-card-head">
            <div class="fan-card-ico"><i class="bi bi-file-text"></i></div>
            <div class="fan-card-title-wrap">
                <div class="fan-card-title">Description de la situation</div>
                <div class="fan-card-sub">Section 3 · Date, lieu et récit de l'événement</div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold fan-label-sm">Date de l'événement <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" id="fanDateEvt" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fan-label-sm">Heure approximative</label>
                <input type="time" class="form-control form-control-sm" id="fanHeureEvt">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fan-label-sm">Lieu précis</label>
            <input type="text" class="form-control form-control-sm" id="fanLieu" placeholder="ex. Chambre 12, couloir nord, salle de bain unité 2…" maxlength="255">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fan-label-sm">Titre <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="fanTitre" placeholder="Résumé court de la situation" maxlength="255">
        </div>
        <?php
        $richFields = [
            ['id' => 'fanDescription', 'label' => 'Description de la situation', 'required' => true,  'ph' => 'Décrivez ce qui s\'est passé, le contexte, les conséquences…'],
            ['id' => 'fanMesures',     'label' => 'Mesures immédiates prises',   'required' => false, 'ph' => 'Actions entreprises immédiatement sur le moment…'],
            ['id' => 'fanSuggestion',  'label' => 'Solution proposée',            'required' => false, 'ph' => 'Votre proposition concrète pour améliorer…', 'opt' => true],
        ];
        foreach ($richFields as $f): ?>
        <div class="mb-3">
            <label class="form-label fw-semibold fan-label-sm">
                <?= h($f['label']) ?>
                <?php if ($f['required']): ?><span class="text-danger">*</span><?php endif ?>
                <?php if (!empty($f['opt'])): ?><span class="text-muted fw-normal"> (optionnel)</span><?php endif ?>
            </label>
            <div class="fan-rich-wrap">
                <div class="fan-rich-toolbar" data-for="<?= h($f['id']) ?>">
                    <button type="button" class="fan-rich-btn" data-fmt="bold" title="Gras (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="fan-rich-btn" data-fmt="italic" title="Italique (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="fan-rich-btn" data-fmt="underline" title="Souligné (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
                    <button type="button" class="fan-rich-btn" data-fmt="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
                    <span class="fan-rich-sep"></span>
                    <button type="button" class="fan-rich-btn" data-fmt="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="fan-rich-btn" data-fmt="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                    <button type="button" class="fan-rich-btn" data-fmt="checklist" title="Checklist"><i class="bi bi-check2-square"></i></button>
                </div>
                <div class="fan-rich-editor" id="<?= h($f['id']) ?>" contenteditable="true" data-placeholder="<?= h($f['ph']) ?>"></div>
            </div>
        </div>
        <?php endforeach ?>
        <div class="mb-0">
            <label class="form-label fw-semibold fan-label-sm">Pièce jointe (optionnel)</label>
            <div class="fan-drop" id="fanDrop">
                <i class="bi bi-cloud-arrow-up"></i>
                <div class="fan-drop-text">Glisser un fichier ou cliquer pour téléverser</div>
                <div class="fan-drop-hint">Photo, PDF, document — max 10 Mo</div>
                <div class="fan-drop-file" id="fanDropFile"></div>
                <input type="file" id="fanFile" class="d-none" accept="image/*,.pdf,.doc,.docx,.xlsx">
            </div>
        </div>
    </div>

    <!-- Visibilité + Anonymat (2 colonnes) -->
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="fan-card h-100 mb-0">
                <div class="fan-card-head">
                    <div class="fan-card-ico"><i class="bi bi-eye"></i></div>
                    <div class="fan-card-title-wrap">
                        <div class="fan-card-title">Visibilité</div>
                        <div class="fan-card-sub">Qui peut voir cette fiche ?</div>
                    </div>
                </div>
                <div class="fan-pill-group" id="fanVisibility">
                    <div class="fan-pill selected" data-val="private"><i class="bi bi-lock"></i> Privée (admin uniquement)</div>
                    <div class="fan-pill" data-val="public"><i class="bi bi-globe"></i> Publique (tous les collègues)</div>
                    <div class="fan-pill" data-val="targeted"><i class="bi bi-bullseye"></i> Ciblée</div>
                </div>
                <div class="mt-3" id="fanTargetWrap" style="display:none">
                    <label class="form-label fw-semibold fan-label-sm">Collaborateurs concernés</label>
                    <input type="text" class="form-control form-control-sm" id="fanTargetSearch" placeholder="Rechercher…">
                    <div id="fanTargetResults" class="mt-1" style="display:none; border:1px solid var(--cl-border-light); border-radius:8px; max-height:160px; overflow:auto; background:#fff"></div>
                    <div id="fanTargetChips" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="fan-card h-100 mb-0">
                <div class="fan-card-head">
                    <div class="fan-card-ico"><i class="bi bi-incognito"></i></div>
                    <div class="fan-card-title-wrap">
                        <div class="fan-card-title">Signalement anonyme</div>
                        <div class="fan-card-sub">Protection de l'auteur</div>
                    </div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="fanAnonymous">
                    <label class="form-check-label fw-semibold fan-label-sm" for="fanAnonymous">
                        Soumettre cette fiche anonymement
                    </label>
                </div>
                <p class="text-muted small mb-0" style="line-height:1.5">
                    <i class="bi bi-shield-lock"></i>
                    Votre identité ne sera <strong>pas enregistrée</strong> — anonymat strict, même l'administration ne pourra pas vous retrouver.
                    L'auteur anonyme ne peut pas recevoir de réponse par email ni de proposition de rendez-vous.
                </p>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex justify-content-end gap-2 mt-3 mb-4">
        <button class="btn btn-sm btn-outline-secondary" data-link="fiches-amelioration"><i class="bi bi-x-lg"></i> Annuler</button>
        <button class="btn btn-sm btn-outline-dark" id="fanDraftBtn"><i class="bi bi-file-earmark"></i> Enregistrer brouillon</button>
        <button class="btn btn-sm btn-dark" id="fanSubmitBtn"><i class="bi bi-send"></i> Enregistrer &amp; soumettre</button>
    </div>
</div>
