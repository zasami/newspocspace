<?php
/**
 * Admin — Roadmap / Todolist
 * Stockage en JSON pour persistence simple
 */
$roadmapFile = __DIR__ . '/../../data/roadmap.json';

// Donnees par defaut
$defaultItems = [
    ['id'=>'stat-filtres',      'title'=>'Statistiques : filtres temporels',                'desc'=>'Filtres par jour, semaine, mois, annee, periode personnalisee sur les stats existantes.', 'difficulty'=>'facile', 'category'=>'Statistiques',   'done'=>false, 'order'=>1],
    ['id'=>'planning-absences', 'title'=>'Planning : afficher absences/grossesse/vacances', 'desc'=>'Marquer visuellement dans le planning les absences longues, conges maternite, vacances, accidents.', 'difficulty'=>'facile', 'category'=>'Planning',       'done'=>false, 'order'=>2],
    ['id'=>'export-stats',      'title'=>'Export PDF / Word / Excel des statistiques',      'desc'=>'Boutons d\'export sur les pages de stats (tableaux + graphiques).', 'difficulty'=>'facile', 'category'=>'Statistiques',   'done'=>false, 'order'=>3],
    ['id'=>'petites-annonces',  'title'=>'Rubrique petites annonces',                       'desc'=>'Page simple pour publier/consulter des annonces internes entre collegues.', 'difficulty'=>'facile', 'category'=>'Communication', 'done'=>false, 'order'=>4],
    ['id'=>'annonces',          'title'=>'Ajouter une annonce (alertes ameliorees)',         'desc'=>'Enrichir le systeme d\'alertes existant pour servir d\'annonces internes.', 'difficulty'=>'facile', 'category'=>'Communication', 'done'=>false, 'order'=>5],
    ['id'=>'creation-employes', 'title'=>'Creation d\'employes + attribution compte',       'desc'=>'Formulaire admin pour creer un employe, generer identifiants, envoyer par email.', 'difficulty'=>'facile', 'category'=>'Admin',          'done'=>false, 'order'=>6],
    ['id'=>'pdf-menu',          'title'=>'Impression PDF/Word du menu cuisine',             'desc'=>'Export du menu de la semaine en PDF et Word depuis la page restauration.', 'difficulty'=>'facile', 'category'=>'Restauration',   'done'=>false, 'order'=>7],
    ['id'=>'stats-interim',     'title'=>'Statistiques interim, absences, grossesses',      'desc'=>'Tableaux + graphiques comparatifs sur plusieurs annees, par periode. Export PDF/Word/Excel.', 'difficulty'=>'moyen', 'category'=>'Statistiques',   'done'=>false, 'order'=>8],
    ['id'=>'import-contacts',   'title'=>'Importation de contacts (Google, CSV)',           'desc'=>'Import de contacts pour chaque admin depuis Google Contacts, fichier CSV ou autre.', 'difficulty'=>'moyen', 'category'=>'Admin',          'done'=>false, 'order'=>9],
    ['id'=>'agenda-rdv',        'title'=>'Agenda / prise de RDV',                           'desc'=>'Calendrier pour fixer des rendez-vous, gerer les disponibilites selon vacances et planning.', 'difficulty'=>'moyen', 'category'=>'Planning',       'done'=>false, 'order'=>10],
    ['id'=>'backup-restore',    'title'=>'Sauvegarde auto BDD + restauration',              'desc'=>'Cron de backup quotidien (BDD + fichiers), page admin pour lister/restaurer les sauvegardes.', 'difficulty'=>'moyen', 'category'=>'Admin',          'done'=>false, 'order'=>11],
    ['id'=>'mise-a-jour',       'title'=>'Script de mise a jour a distance',                'desc'=>'Script pour deployer les MAJ du code sur le serveur (git pull ou upload ZIP + migration auto).', 'difficulty'=>'moyen', 'category'=>'Admin',          'done'=>false, 'order'=>12],
    ['id'=>'mur-actu',          'title'=>'Mur d\'actualite / fil d\'actu',                  'desc'=>'Page actualites : posts, commentaires, likes. Confidentialite par role.', 'difficulty'=>'moyen', 'category'=>'Communication', 'done'=>false, 'order'=>13],
    ['id'=>'module-cuisine',    'title'=>'Module Restauration / Cuisine',                   'desc'=>'Saisie des menus, base de plats recurrents, affichage automatique sur le site, export PDF/Word.', 'difficulty'=>'moyen', 'category'=>'Restauration',   'done'=>false, 'order'=>14],
    ['id'=>'table-vip',         'title'=>'Table VIP (residents)',                            'desc'=>'Selectionner une liste de residents, notifier les modules concernes par alerte/notification.', 'difficulty'=>'moyen', 'category'=>'Restauration',   'done'=>false, 'order'=>15],
    ['id'=>'module-animation',  'title'=>'Module Animation',                                'desc'=>'Saisie des activites, animations repetitives, affichage auto sur le site, objectifs animation.', 'difficulty'=>'difficile', 'category'=>'Animation',      'done'=>false, 'order'=>16],
    ['id'=>'ia-animations',     'title'=>'IA : suggestions d\'animations',                  'desc'=>'IA qui suggere des idees selon budget, faisabilite, securite, competences disponibles.', 'difficulty'=>'difficile', 'category'=>'Animation',      'done'=>false, 'order'=>17],
    ['id'=>'formation',         'title'=>'Rubrique Formation (Marceline)',                   'desc'=>'Catalogue de formations, suivi par employe, lien avec logiciels de formation EMS.', 'difficulty'=>'difficile', 'category'=>'Formation',      'done'=>false, 'order'=>18],
    ['id'=>'postulation',       'title'=>'Formulaire de postulation',                       'desc'=>'Formulaire public de candidature + interface admin pour gerer les candidatures.', 'difficulty'=>'difficile', 'category'=>'Recrutement',    'done'=>false, 'order'=>19],
    ['id'=>'suivi-dossier',     'title'=>'Suivi de dossier candidat',                       'desc'=>'Statuts (en cours/entretien/accepte/refuse), rapport formateur, points a ameliorer, visibilite admin+formateur+candidat, tchat.', 'difficulty'=>'difficile', 'category'=>'Recrutement',    'done'=>false, 'order'=>20],
    ['id'=>'transcription-pv',  'title'=>'Ameliorer la transcription de PV',                'desc'=>'Qualite vocale, locuteurs multiples, correction contexte EMS, formatage intelligent. HAUTE PRIORITE.', 'difficulty'=>'difficile', 'category'=>'PV / IA',        'done'=>false, 'order'=>21],
    ['id'=>'chiffrement',       'title'=>'Confidentialite et chiffrement des donnees',      'desc'=>'Niveaux d\'acces par role, chiffrement E2E donnees sensibles, audit securite global.', 'difficulty'=>'difficile', 'category'=>'Securite',       'done'=>false, 'order'=>22],
];

if (file_exists($roadmapFile)) {
    $items = json_decode(file_get_contents($roadmapFile), true);
    if (!is_array($items)) $items = $defaultItems;
} else {
    $items = $defaultItems;
    @mkdir(dirname($roadmapFile), 0755, true);
    file_put_contents($roadmapFile, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Trier par order
usort($items, fn($a, $b) => ($a['order'] ?? 99) - ($b['order'] ?? 99));

$totalDone  = count(array_filter($items, fn($i) => $i['done']));
$totalItems = count($items);
$pctDone    = $totalItems > 0 ? round($totalDone / $totalItems * 100) : 0;
$facile     = array_filter($items, fn($i) => $i['difficulty'] === 'facile');
$moyen      = array_filter($items, fn($i) => $i['difficulty'] === 'moyen');
$difficile  = array_filter($items, fn($i) => $i['difficulty'] === 'difficile');

$categories = ['Statistiques','Planning','Communication','Admin','Restauration','Animation','Formation','Recrutement','PV / IA','Securite','Site web','General'];

$diffColors = [
    'facile'    => ['bg'=>'#bcd2cb','color'=>'#2d4a43'],
    'moyen'     => ['bg'=>'#D4C4A8','color'=>'#6B5B3E'],
    'difficile' => ['bg'=>'#E2B8AE','color'=>'#7B3B2C'],
];
$catColors = [
    'Statistiques'  => ['bg'=>'#B8C9D4','color'=>'#3B4F6B'],
    'Planning'      => ['bg'=>'#bcd2cb','color'=>'#2d4a43'],
    'Communication' => ['bg'=>'#D0C4D8','color'=>'#5B4B6B'],
    'Admin'         => ['bg'=>'#C8C4BE','color'=>'#5A5550'],
    'Restauration'  => ['bg'=>'#D4C4A8','color'=>'#6B5B3E'],
    'Animation'     => ['bg'=>'#C8D4C0','color'=>'#4A5548'],
    'Formation'     => ['bg'=>'#B8C9D4','color'=>'#3B4F6B'],
    'Recrutement'   => ['bg'=>'#D0C4D8','color'=>'#5B4B6B'],
    'PV / IA'       => ['bg'=>'#E2B8AE','color'=>'#7B3B2C'],
    'Securite'      => ['bg'=>'#E2B8AE','color'=>'#7B3B2C'],
    'Site web'      => ['bg'=>'#B8D4D0','color'=>'#2D5A4F'],
    'General'       => ['bg'=>'#D4D0C8','color'=>'#4A4840'],
];

// Map category names to CSS class slugs
$catCssMap = [
    'Statistiques'  => 'rm-cat-statistiques',
    'Planning'      => 'rm-cat-planning',
    'Communication' => 'rm-cat-communication',
    'Admin'         => 'rm-cat-admin',
    'Restauration'  => 'rm-cat-restauration',
    'Animation'     => 'rm-cat-animation',
    'Formation'     => 'rm-cat-formation',
    'Recrutement'   => 'rm-cat-recrutement',
    'PV / IA'       => 'rm-cat-pv-ia',
    'Securite'      => 'rm-cat-securite',
    'Site web'      => 'rm-cat-site-web',
    'General'       => 'rm-cat-general',
];
?>

<style>
/* Roadmap table rows */
.roadmap-row { cursor:pointer; transition:background .15s ease, transform .1s ease; }
.roadmap-row:hover { background:#f0f5f3 !important; }
.roadmap-row:active { transform:scale(.998); }
.roadmap-row.done-row { opacity:.55; }
.roadmap-row.done-row:hover { opacity:.75; }
.roadmap-row.rm-hidden { display:none; }

/* Modal */
.rm-modal-overlay { position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.4);backdrop-filter:blur(3px);opacity:0;transition:opacity .2s ease; }
.rm-modal-overlay.rm-modal-open { display:flex; }
.rm-modal-overlay.show { opacity:1; }
.rm-modal { background:#fff;border-radius:16px;max-width:780px;width:94%;box-shadow:0 20px 60px rgba(0,0,0,.15);transform:translateY(20px) scale(.97);transition:transform .25s ease; }
.rm-modal.rm-modal-preview { max-width:880px; }
.rm-modal-overlay.show .rm-modal { transform:translateY(0) scale(1); }
.rm-modal-header { padding:1.25rem 1.5rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:.75rem; }
.rm-modal-body { padding:1.5rem; max-height:65vh; overflow-y:auto; }
.rm-modal-footer { padding:.75rem 1.5rem;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center; }

/* Modal header elements */
.rm-modal-icon-wrap { width:40px;height:40px;border-radius:50%;background:#bcd2cb;color:#2d4a43;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0; }
.rm-modal-title { margin:0;font-weight:600;flex:1; }
.rm-modal-close-btn { width:34px;height:34px;border-radius:50%;border:1px solid #e5e7eb;background:#f9fafb;cursor:pointer;display:flex;align-items:center;justify-content:center; }
.rm-modal-close-btn i { font-size:.8rem; }

/* Form fields */
.rm-field { margin-bottom:1rem; }
.rm-field label { display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.35rem; }
.rm-field input, .rm-field textarea, .rm-field select { width:100%;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:10px;font-size:.88rem;outline:none;transition:border-color .15s; }
.rm-field input:focus, .rm-field textarea:focus, .rm-field select:focus { border-color:#2d4a43;box-shadow:0 0 0 3px rgba(45,74,67,.1); }
.rm-field textarea { resize:vertical;min-height:120px; }
.rm-field-error { border-color:#E2B8AE !important; }
.rm-modal .zs-list { max-width:280px !important; }

/* Difficulty pills */
.rm-diff-pills { display:flex;gap:.5rem; }
.rm-diff-pill { flex:1;padding:.5rem;border-radius:10px;border:2px solid #e5e7eb;text-align:center;cursor:pointer;font-size:.82rem;font-weight:600;transition:all .15s; }
.rm-diff-pill:hover { border-color:#bbb; }
.rm-diff-pill.active-facile { border-color:#2d4a43;background:#bcd2cb;color:#2d4a43; }
.rm-diff-pill.active-moyen { border-color:#6B5B3E;background:#D4C4A8;color:#6B5B3E; }
.rm-diff-pill.active-difficile { border-color:#7B3B2C;background:#E2B8AE;color:#7B3B2C; }

/* Badges - reusable */
.rm-badge { font-weight:600;font-size:.72rem; }
.rm-badge-done { background:#bcd2cb;color:#2d4a43; }
.rm-badge-pending { background:#f3f4f6;color:#6b7280; }
.rm-badge-progress { background:#bcd2cb;color:#2d4a43; }

/* Difficulty badge colors */
.rm-diff-facile { background:#bcd2cb;color:#2d4a43; }
.rm-diff-moyen { background:#D4C4A8;color:#6B5B3E; }
.rm-diff-difficile { background:#E2B8AE;color:#7B3B2C; }

/* Category badge colors */
.rm-cat-statistiques { background:#B8C9D4;color:#3B4F6B; }
.rm-cat-planning { background:#bcd2cb;color:#2d4a43; }
.rm-cat-communication { background:#D0C4D8;color:#5B4B6B; }
.rm-cat-admin { background:#C8C4BE;color:#5A5550; }
.rm-cat-restauration { background:#D4C4A8;color:#6B5B3E; }
.rm-cat-animation { background:#C8D4C0;color:#4A5548; }
.rm-cat-formation { background:#B8C9D4;color:#3B4F6B; }
.rm-cat-recrutement { background:#D0C4D8;color:#5B4B6B; }
.rm-cat-pv-ia { background:#E2B8AE;color:#7B3B2C; }
.rm-cat-securite { background:#E2B8AE;color:#7B3B2C; }
.rm-cat-site-web { background:#B8D4D0;color:#2D5A4F; }
.rm-cat-general { background:#D4D0C8;color:#4A4840; }

/* Progress bar */
.rm-progress-track { height:10px;border-radius:8px;background:#e9ecef; }
.rm-progress-fill { background:#2d4a43;border-radius:8px;transition:width .6s ease;width:var(--rm-progress, 0%); }

/* Table header */
.rm-thead-row { background:#f8f9fa; }
.rm-th-check { width:40px;text-align:center;padding-left:1rem; }
.rm-th-check-icon { opacity:.4; }
.rm-th-order { width:40px;text-align:center; }
.rm-th-category { width:120px; }
.rm-th-difficulty { width:100px;text-align:center; }
.rm-th-status { width:80px;text-align:center; }

/* Table cells */
.rm-td-check { text-align:center;padding-left:1rem; }
.rm-td-center { text-align:center; }
.rm-task-title { font-size:.9rem; }
.rm-task-desc { font-size:.78rem;line-height:1.4; }

/* Checkbox */
.rm-checkbox { cursor:pointer;width:18px;height:18px; }

/* Filter buttons */
.rm-filter-active { background:#1a1a1a !important;color:#fff !important;font-weight:500; }
.rm-filter-dot { display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px; }
.rm-filter-dot-facile { background:#2d4a43; }
.rm-filter-dot-moyen { background:#6B5B3E; }
.rm-filter-dot-difficile { background:#7B3B2C; }

/* Preview modal */
.rm-prev-body { padding:1.5rem; }
.rm-prev-label { font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:.4rem; }
.rm-prev-info-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:.9rem;margin-bottom:1.25rem; }
@media (max-width:640px) { .rm-prev-info-grid { grid-template-columns:1fr; } }
.rm-prev-info-card {
    border-radius:14px;padding:1rem 1.1rem;
    border:1.5px solid transparent;
    display:flex;flex-direction:column;gap:.4rem;
    transition:transform .15s;
}
.rm-prev-info-card:hover { transform:translateY(-1px); }
.rm-prev-info-card .rm-prev-label { color:inherit;opacity:.7;margin-bottom:0; }
.rm-prev-info-val { font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem; }
.rm-prev-info-val i { font-size:1.1rem; }
.rm-prev-desc-wrap { margin-top:.5rem; }
.rm-prev-desc {
    background:#faf8f5;border:1px solid #f0ede8;border-radius:12px;
    padding:1.1rem 1.25rem;font-size:.92rem;line-height:1.65;color:#374151;
    white-space:pre-wrap;word-wrap:break-word;
}
.rm-btn-edit { background:#2d4a43;color:#fff;border-radius:8px;font-weight:500;padding:.45rem 1.1rem;border:none; }
.rm-btn-edit:hover { background:#1f3530;color:#fff; }

/* Action buttons */
.rm-btn-add { background:#2d4a43;color:#fff;font-weight:500;border-radius:10px;padding:.45rem 1rem; }
.rm-btn-save { background:#2d4a43;color:#fff;border-radius:8px;font-weight:500;padding:.4rem 1.25rem; }
.rm-btn-cancel { border-radius:8px; }
.rm-btn-delete { color:#7B3B2C; }
.rm-btn-delete.rm-hidden { display:none; }
</style>

<!-- Stats globales -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-teal"><i class="bi bi-rocket-takeoff"></i></div>
                <div><div class="text-muted small">Total</div><div class="fw-bold fs-5" id="rmStatTotal"><?= $totalItems ?> taches</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div>
                <div><div class="text-muted small">Terminees</div><div class="fw-bold fs-5" id="rmStatDone"><?= $totalDone ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-orange"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="text-muted small">En attente</div><div class="fw-bold fs-5" id="rmStatPending"><?= $totalItems - $totalDone ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-purple"><i class="bi bi-percent"></i></div>
                <div><div class="text-muted small">Progression</div><div class="fw-bold fs-5" id="rmStatPct"><?= $pctDone ?>%</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Barre de progression globale -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Progression globale</span>
            <span class="badge rounded-pill rm-badge-progress" id="rmProgressBadge"><?= $totalDone ?>/<?= $totalItems ?></span>
        </div>
        <div class="progress rm-progress-track">
            <div class="progress-bar rm-progress-fill" id="rmProgressBar" role="progressbar" data-progress="<?= $pctDone ?>"></div>
        </div>
    </div>
</div>

<!-- Filtres + bouton ajouter -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap" id="roadmapFilters">
        <button class="btn btn-sm rounded-pill rm-filter-active" data-filter="all">Tout (<?= $totalItems ?>)</button>
        <button class="btn btn-sm btn-light rounded-pill" data-filter="facile"><span class="rm-filter-dot rm-filter-dot-facile"></span>Facile (<?= count($facile) ?>)</button>
        <button class="btn btn-sm btn-light rounded-pill" data-filter="moyen"><span class="rm-filter-dot rm-filter-dot-moyen"></span>Moyen (<?= count($moyen) ?>)</button>
        <button class="btn btn-sm btn-light rounded-pill" data-filter="difficile"><span class="rm-filter-dot rm-filter-dot-difficile"></span>Difficile (<?= count($difficile) ?>)</button>
        <button class="btn btn-sm btn-light rounded-pill" data-filter="done"><i class="bi bi-check-lg"></i> Terminees</button>
        <button class="btn btn-sm btn-light rounded-pill" data-filter="pending"><i class="bi bi-clock"></i> En attente</button>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="zs-select" id="rmCatSelect" data-placeholder="Toutes catégories"></div>
        <button class="btn btn-sm d-inline-flex align-items-center gap-1 rm-btn-add" id="btnAddTask">
            <i class="bi bi-plus-lg"></i> Ajouter une tache
        </button>
    </div>
</div>

<!-- Tableau -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="roadmapTable">
            <thead>
                <tr class="rm-thead-row">
                    <th class="rm-th-check"><i class="bi bi-check2-square rm-th-check-icon"></i></th>
                    <th class="rm-th-order">#</th>
                    <th>Tache</th>
                    <th class="rm-th-category">Categorie</th>
                    <th class="rm-th-difficulty">Difficulte</th>
                    <th class="rm-th-status">Statut</th>
                </tr>
            </thead>
            <tbody id="roadmapBody">
                <?php foreach ($items as $idx => $item):
                    $dc = $diffColors[$item['difficulty']] ?? $diffColors['moyen'];
                    $cc = $catColors[$item['category']] ?? ['bg'=>'#C8C4BE','color'=>'#5A5550'];
                    $stars = $item['difficulty'] === 'facile' ? '&#9733;' : ($item['difficulty'] === 'moyen' ? '&#9733;&#9733;' : '&#9733;&#9733;&#9733;');
                ?>
                <tr class="roadmap-row <?= $item['done'] ? 'done-row' : '' ?>"
                    data-id="<?= h($item['id']) ?>"
                    data-difficulty="<?= h($item['difficulty']) ?>"
                    data-done="<?= $item['done'] ? '1' : '0' ?>"
                    data-title="<?= h($item['title']) ?>"
                    data-desc="<?= h($item['desc']) ?>"
                    data-category="<?= h($item['category']) ?>">
                    <td class="rm-td-check td-check">
                        <input type="checkbox" class="form-check-input roadmap-check rm-checkbox" <?= $item['done'] ? 'checked' : '' ?>>
                    </td>
                    <td class="rm-td-center"><span class="text-muted small"><?= $idx + 1 ?></span></td>
                    <td class="td-clickable">
                        <div class="fw-semibold rm-task-title <?= $item['done'] ? 'text-decoration-line-through' : '' ?>"><?= h($item['title']) ?></div>
                        <div class="text-muted small rm-task-desc"><?= h($item['desc']) ?></div>
                    </td>
                    <td class="td-clickable"><span class="badge rounded-pill rm-badge <?= $catCssMap[$item['category']] ?? 'rm-cat-general' ?>"><?= h($item['category']) ?></span></td>
                    <td class="td-clickable rm-td-center">
                        <span class="badge rounded-pill rm-badge rm-diff-<?= h($item['difficulty']) ?>">
                            <?= $stars ?> <?= ucfirst(h($item['difficulty'])) ?>
                        </span>
                    </td>
                    <td class="td-clickable rm-td-center">
                        <?php if ($item['done']): ?>
                            <span class="badge rounded-pill rm-badge-done"><i class="bi bi-check-lg"></i> Fait</span>
                        <?php else: ?>
                            <span class="badge rounded-pill rm-badge-pending">En attente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Prévisualisation -->
<div class="rm-modal-overlay" id="rmPreviewModal">
    <div class="rm-modal rm-modal-preview">
        <div class="rm-modal-header">
            <div class="rm-modal-icon-wrap"><i class="bi bi-eye"></i></div>
            <h6 class="rm-modal-title" id="rmPrevTitle">Détail de la tâche</h6>
            <button class="rm-modal-close-btn" id="rmPrevClose"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="rm-modal-body rm-prev-body">
            <div class="rm-prev-info-grid">
                <div class="rm-prev-info-card" id="rmPrevCardStatus">
                    <div class="rm-prev-label">Statut</div>
                    <div class="rm-prev-info-val" id="rmPrevStatus">—</div>
                </div>
                <div class="rm-prev-info-card" id="rmPrevCardDifficulty">
                    <div class="rm-prev-label">Difficulté</div>
                    <div class="rm-prev-info-val" id="rmPrevDifficulty">—</div>
                </div>
                <div class="rm-prev-info-card" id="rmPrevCardCategory">
                    <div class="rm-prev-label">Catégorie</div>
                    <div class="rm-prev-info-val" id="rmPrevCategory">—</div>
                </div>
            </div>
            <div class="rm-prev-desc-wrap">
                <div class="rm-prev-label">Description</div>
                <div class="rm-prev-desc" id="rmPrevDesc">—</div>
            </div>
        </div>
        <div class="rm-modal-footer">
            <button class="btn btn-sm btn-light rm-btn-cancel" id="rmPrevCancelBtn">Fermer</button>
            <div class="d-flex gap-2">
                <button class="btn btn-sm rm-btn-edit" id="rmPrevEditBtn"><i class="bi bi-pencil"></i> Modifier</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter / Modifier -->
<div class="rm-modal-overlay" id="rmModal">
    <div class="rm-modal">
        <div class="rm-modal-header">
            <div class="rm-modal-icon-wrap">
                <i class="bi" id="rmModalIcon"></i>
            </div>
            <h6 class="rm-modal-title" id="rmModalTitle">Ajouter une tache</h6>
            <button class="rm-modal-close-btn" id="rmModalClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="rm-modal-body">
            <input type="hidden" id="rmEditId" value="">
            <div class="rm-field">
                <label>Titre</label>
                <input type="text" id="rmTitle" placeholder="Ex: Ajouter export PDF des stats">
            </div>
            <div class="rm-field">
                <label>Description</label>
                <textarea id="rmDesc" placeholder="Details de la tache..."></textarea>
            </div>
            <div class="rm-field">
                <label>Categorie</label>
                <div class="zs-select" id="rmCategory" data-placeholder="Catégorie"></div>
            </div>
            <div class="rm-field">
                <label>Difficulte</label>
                <div class="rm-diff-pills">
                    <div class="rm-diff-pill" data-diff="facile"><i class="bi bi-star-fill"></i><br>Facile</div>
                    <div class="rm-diff-pill" data-diff="moyen"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><br>Moyen</div>
                    <div class="rm-diff-pill" data-diff="difficile"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><br>Difficile</div>
                </div>
            </div>
        </div>
        <div class="rm-modal-footer">
            <div>
                <button class="btn btn-sm rm-btn-delete rm-hidden" id="rmDeleteBtn"><i class="bi bi-trash3"></i> Supprimer</button>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light rm-btn-cancel" id="rmCancelBtn">Annuler</button>
                <button class="btn btn-sm rm-btn-save" id="rmSaveBtn">
                    <i class="bi bi-check-lg"></i> <span id="rmSaveTxt">Ajouter</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    // Init progress bar from data attribute
    const progressBar = document.getElementById('rmProgressBar');
    if (progressBar) progressBar.style.setProperty('--rm-progress', progressBar.dataset.progress + '%');

    const modal     = document.getElementById('rmModal');
    const editId    = document.getElementById('rmEditId');
    const fTitle    = document.getElementById('rmTitle');
    const fDesc     = document.getElementById('rmDesc');
    const diffPills = document.querySelectorAll('.rm-diff-pill');
    const deleteBtn = document.getElementById('rmDeleteBtn');
    const saveTxt   = document.getElementById('rmSaveTxt');
    const modalTitle = document.getElementById('rmModalTitle');
    const modalIcon  = document.getElementById('rmModalIcon');
    let selectedDiff = 'facile';

    // Init category select
    const rmCatOptions = <?= json_encode(array_map(fn($c) => ['value' => $c, 'label' => $c], $categories)) ?>;
    zerdaSelect.init('#rmCategory', rmCatOptions, { value: rmCatOptions[0]?.value || '' });

    // Difficulty pills
    diffPills.forEach(pill => {
        pill.addEventListener('click', () => {
            diffPills.forEach(p => p.className = 'rm-diff-pill');
            pill.classList.add('active-' + pill.dataset.diff);
            selectedDiff = pill.dataset.diff;
        });
    });

    function setDifficulty(diff) {
        selectedDiff = diff;
        diffPills.forEach(p => {
            p.className = 'rm-diff-pill';
            if (p.dataset.diff === diff) p.classList.add('active-' + diff);
        });
    }

    function openModal(mode, data) {
        editId.value = '';
        fTitle.value = '';
        fDesc.value = '';
        zerdaSelect.setValue('#rmCategory', 'Statistiques');
        setDifficulty('facile');

        if (mode === 'edit' && data) {
            editId.value = data.id;
            fTitle.value = data.title;
            fDesc.value = data.desc;
            zerdaSelect.setValue('#rmCategory', data.category);
            setDifficulty(data.difficulty);
            modalTitle.textContent = 'Modifier la tache';
            modalIcon.className = 'bi bi-pencil';
            saveTxt.textContent = 'Enregistrer';
            deleteBtn.classList.remove('rm-hidden');
        } else {
            modalTitle.textContent = 'Ajouter une tache';
            modalIcon.className = 'bi bi-plus-lg';
            saveTxt.textContent = 'Ajouter';
            deleteBtn.classList.add('rm-hidden');
        }

        modal.classList.add('rm-modal-open');
        requestAnimationFrame(() => modal.classList.add('show'));
        fTitle.focus();
    }

    function closeModal() {
        modal.classList.remove('show');
        setTimeout(() => modal.classList.remove('rm-modal-open'), 200);
    }

    // Open modal: add
    document.getElementById('btnAddTask').addEventListener('click', () => openModal('add'));

    // Open preview modal on row click (not checkbox)
    const prevModal = document.getElementById('rmPreviewModal');
    let currentPreviewData = null;

    const diffColorMap = {
        facile:    { bg: '#e6efe9', border: '#bcd2cb', color: '#2d4a43', icon: 'bi-star-fill', label: 'Facile' },
        moyen:     { bg: '#f4ecdd', border: '#D4C4A8', color: '#6B5B3E', icon: 'bi-star-half', label: 'Moyen' },
        difficile: { bg: '#f4dcd5', border: '#E2B8AE', color: '#7B3B2C', icon: 'bi-fire',      label: 'Difficile' },
    };
    const catColorMap = <?= json_encode($catColors) ?>;

    function openPreview(data) {
        currentPreviewData = data;
        document.getElementById('rmPrevTitle').textContent = data.title || 'Détail de la tâche';
        document.getElementById('rmPrevDesc').textContent = data.desc || '—';

        const isDone = data.done === '1' || data.done === true;
        const statusCard = document.getElementById('rmPrevCardStatus');
        const statusVal = document.getElementById('rmPrevStatus');
        if (isDone) {
            statusCard.style.cssText = 'background:#e6efe9;border-color:#bcd2cb;color:#2d4a43';
            statusVal.innerHTML = '<i class="bi bi-check-circle-fill"></i> Terminée';
        } else {
            statusCard.style.cssText = 'background:#f3f4f6;border-color:#e5e7eb;color:#4b5563';
            statusVal.innerHTML = '<i class="bi bi-hourglass-split"></i> En attente';
        }

        const dc = diffColorMap[data.difficulty] || diffColorMap.moyen;
        const diffCard = document.getElementById('rmPrevCardDifficulty');
        diffCard.style.cssText = `background:${dc.bg};border-color:${dc.border};color:${dc.color}`;
        document.getElementById('rmPrevDifficulty').innerHTML = `<i class="bi ${dc.icon}"></i> ${dc.label}`;

        const cc = catColorMap[data.category] || { bg: '#D4D0C8', color: '#4A4840' };
        const catCard = document.getElementById('rmPrevCardCategory');
        catCard.style.cssText = `background:${cc.bg};border-color:${cc.bg};color:${cc.color}`;
        document.getElementById('rmPrevCategory').innerHTML = `<i class="bi bi-tag-fill"></i> ${escapeHtmlJs(data.category || '—')}`;

        prevModal.classList.add('rm-modal-open');
        requestAnimationFrame(() => prevModal.classList.add('show'));
    }

    function closePreview() {
        prevModal.classList.remove('show');
        setTimeout(() => prevModal.classList.remove('rm-modal-open'), 200);
    }

    function escapeHtmlJs(s) { return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

    document.getElementById('roadmapBody').addEventListener('click', (e) => {
        if (e.target.closest('.td-check')) return;
        const row = e.target.closest('.roadmap-row');
        if (!row) return;
        openPreview({
            id: row.dataset.id,
            title: row.dataset.title,
            desc: row.dataset.desc,
            category: row.dataset.category,
            difficulty: row.dataset.difficulty,
            done: row.dataset.done,
        });
    });

    document.getElementById('rmPrevClose').addEventListener('click', closePreview);
    document.getElementById('rmPrevCancelBtn').addEventListener('click', closePreview);
    prevModal.addEventListener('click', (e) => { if (e.target === prevModal) closePreview(); });

    document.getElementById('rmPrevEditBtn').addEventListener('click', () => {
        if (!currentPreviewData) return;
        closePreview();
        setTimeout(() => openModal('edit', currentPreviewData), 220);
    });

    // Close
    document.getElementById('rmModalClose').addEventListener('click', closeModal);
    document.getElementById('rmCancelBtn').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // Save
    document.getElementById('rmSaveBtn').addEventListener('click', async () => {
        const title = fTitle.value.trim();
        if (!title) { fTitle.classList.add('rm-field-error'); fTitle.focus(); return; }
        fTitle.classList.remove('rm-field-error');

        const payload = {
            id: editId.value || '',
            title,
            desc: fDesc.value.trim(),
            category: zerdaSelect.getValue('#rmCategory'),
            difficulty: selectedDiff,
        };

        const action = editId.value ? 'admin_roadmap_update' : 'admin_roadmap_create';
        try {
            const res = await adminApiPost(action, payload);
            if (!res.success) { showToast(res.message || 'Erreur', 'danger'); return; }
            showToast(editId.value ? 'Tache mise a jour' : 'Tache ajoutee', 'success');
            closeModal();
            // Reload page to reflect changes
            setTimeout(() => window.location.reload(), 300);
        } catch(e) {
            showToast('Erreur de sauvegarde', 'danger');
        }
    });

    // Delete
    deleteBtn.addEventListener('click', async () => {
        if (!editId.value) return;
        if (!confirm('Supprimer cette tache ?')) return;
        try {
            const res = await adminApiPost('admin_roadmap_delete', { id: editId.value });
            if (!res.success) { showToast(res.message || 'Erreur', 'danger'); return; }
            showToast('Tache supprimee', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 300);
        } catch(e) {
            showToast('Erreur', 'danger');
        }
    });

    // Toggle checkbox (without opening modal)
    document.querySelectorAll('.roadmap-check').forEach(cb => {
        cb.addEventListener('change', async (e) => {
            e.stopPropagation();
            const row = cb.closest('.roadmap-row');
            const id = row.dataset.id;
            const done = cb.checked;

            row.dataset.done = done ? '1' : '0';
            row.classList.toggle('done-row', done);
            const title = row.querySelector('.fw-semibold');
            title.classList.toggle('text-decoration-line-through', done);

            const statusCell = row.querySelector('td:last-child');
            statusCell.innerHTML = done
                ? '<span class="badge rounded-pill rm-badge-done"><i class="bi bi-check-lg"></i> Fait</span>'
                : '<span class="badge rounded-pill rm-badge-pending">En attente</span>';

            updateStats();

            try {
                await adminApiPost('admin_roadmap_toggle', { id, done: done ? 1 : 0 });
                showToast(done ? 'Tache terminee !' : 'Tache remise en attente', 'success');
            } catch(e) {
                showToast('Erreur de sauvegarde', 'danger');
            }
        });
    });

    // Update stats counters
    function updateStats() {
        const rows = document.querySelectorAll('.roadmap-row');
        const total = rows.length;
        let doneCount = 0;
        rows.forEach(r => { if (r.dataset.done === '1') doneCount++; });
        const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;

        document.getElementById('rmStatTotal').textContent = total + ' taches';
        document.getElementById('rmStatDone').textContent = doneCount;
        document.getElementById('rmStatPending').textContent = total - doneCount;
        document.getElementById('rmStatPct').textContent = pct + '%';
        document.getElementById('rmProgressBadge').textContent = doneCount + '/' + total;
        document.getElementById('rmProgressBar').style.setProperty('--rm-progress', pct + '%');
    }

    // Filters
    let currentDiffFilter = 'all';
    let currentSearchQuery = '';

    function applyFilters() {
        const catFilter = selectedCategory;
        const q = currentSearchQuery.toLowerCase().trim();
        document.querySelectorAll('.roadmap-row').forEach(row => {
            const diff = row.dataset.difficulty;
            const done = row.dataset.done === '1';
            const cat = row.dataset.category;
            const title = (row.dataset.title || '').toLowerCase();
            const desc = (row.dataset.desc || '').toLowerCase();
            let show = true;
            if (currentDiffFilter === 'facile' || currentDiffFilter === 'moyen' || currentDiffFilter === 'difficile') show = diff === currentDiffFilter;
            else if (currentDiffFilter === 'done') show = done;
            else if (currentDiffFilter === 'pending') show = !done;
            if (catFilter && cat !== catFilter) show = false;
            if (q && !(title.includes(q) || desc.includes(q) || (cat || '').toLowerCase().includes(q))) show = false;
            row.classList.toggle('rm-hidden', !show);
        });
    }

    // ── Recherche locale via topbar (mode @) ──
    window.__pageLocalSearch = function(q) {
      currentSearchQuery = q || '';
      applyFilters();
    };
    window.__pageLocalSearchCount = function() {
      return document.querySelectorAll('.roadmap-row:not(.rm-hidden)').length;
    };

    document.querySelectorAll('#roadmapFilters button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#roadmapFilters button').forEach(b => {
                b.classList.remove('rm-filter-active');
            });
            btn.classList.add('rm-filter-active');
            currentDiffFilter = btn.dataset.filter;
            applyFilters();
        });
    });

    // Category dropdown via zerdaSelect
    let selectedCategory = '';
    zerdaSelect.init('#rmCatSelect', [
        { value: '', label: 'Toutes catégories' },
        <?php foreach ($categories as $cat):
            $cc = $catColors[$cat] ?? ['bg'=>'#C8C4BE','color'=>'#5A5550'];
        ?>
        { value: <?= json_encode($cat) ?>, label: <?= json_encode($cat) ?>, dot: '<?= $cc['bg'] ?>' },
        <?php endforeach; ?>
    ], {
        dots: true,
        align: 'right',
        width: '200px',
        onSelect: (val) => { selectedCategory = val; applyFilters(); }
    });

    // Keyboard: Escape to close modals, Enter to save in edit modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (prevModal.classList.contains('rm-modal-open')) { closePreview(); return; }
            if (modal.classList.contains('rm-modal-open')) { closeModal(); return; }
        }
        if (e.key === 'Enter' && modal.classList.contains('rm-modal-open') && !e.target.matches('textarea')) {
            document.getElementById('rmSaveBtn').click();
        }
    });
})();
</script>
