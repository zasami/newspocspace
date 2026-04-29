<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$userRole = $user['role'] ?? 'collaborateur';
$highlightId = $_GET['highlight'] ?? null;
$filterService = $_GET['service'] ?? '';

// Services
$services = Db::fetchAll(
    "SELECT id, nom, slug, icone, couleur
     FROM document_services WHERE actif = 1 ORDER BY ordre, nom"
);

// Documents
$where = ['d.visible = 1'];
$binds = [];
if ($filterService) {
    $where[] = 'd.service_id = ?';
    $binds[] = $filterService;
}
$whereSql = implode(' AND ', $where);

$docs = Db::fetchAll(
    "SELECT d.id, d.titre, d.description, d.original_name, d.mime_type, d.size, d.created_at,
            s.nom AS service_nom, s.slug AS service_slug, s.icone AS service_icone, s.couleur AS service_couleur
     FROM documents d
     JOIN document_services s ON s.id = d.service_id AND s.actif = 1
     WHERE $whereSql
     ORDER BY d.created_at DESC",
    $binds
);

// Filter access
$filtered = [];
foreach ($docs as $doc) {
    $blocked = Db::fetch(
        "SELECT id FROM document_access WHERE document_id = ? AND acces = 'bloque' AND (role = ? OR service_id = ?)",
        [$doc['id'], $userRole, $doc['service_slug'] ?? '']
    );
    if (!$blocked) $filtered[] = $doc;
}

function doc_icon($mime) {
    if (str_contains($mime ?? '', 'pdf')) return ['bi-file-earmark-pdf-fill', 'pdf'];
    if (str_contains($mime ?? '', 'word') || str_contains($mime ?? '', 'document')) return ['bi-file-earmark-word-fill', 'word'];
    if (str_contains($mime ?? '', 'excel') || str_contains($mime ?? '', 'sheet') || str_contains($mime ?? '', 'csv')) return ['bi-file-earmark-excel-fill', 'excel'];
    if (str_contains($mime ?? '', 'presentation') || str_contains($mime ?? '', 'powerpoint')) return ['bi-file-earmark-ppt-fill', 'ppt'];
    if (str_contains($mime ?? '', 'image')) return ['bi-file-earmark-image-fill', 'image'];
    return ['bi-file-earmark-fill', 'other'];
}

function doc_fmt_size($bytes) {
    if (!$bytes) return '';
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '') . ' Ko';
    return number_format($bytes / 1048576, 1, ',', '') . ' Mo';
}
?>
<div class="doc-wrap">
    <?= render_page_header('Documents', 'bi-folder2-open') ?>

    <!-- Service filter pills -->
    <div class="doc-service-pills mb-3">
        <a class="doc-filter-pill <?= !$filterService ? 'active' : '' ?>"
           href="/newspocspace/documents" data-doc-filter="">
            <i class="bi bi-grid-fill pill-icon"></i> Tous
        </a>
        <?php foreach ($services as $s): ?>
            <a class="doc-filter-pill <?= $filterService === $s['id'] ? 'active' : '' ?>"
               href="/newspocspace/documents?service=<?= h($s['id']) ?>" data-doc-filter="<?= h($s['id']) ?>">
                <i class="bi bi-<?= h($s['icone']) ?> pill-icon" <?= $filterService !== $s['id'] ? 'style="color:' . h($s['couleur']) . '"' : '' ?>></i>
                <?= h($s['nom']) ?>
            </a>
        <?php endforeach ?>
    </div>

    <!-- Documents grid -->
    <?php if (!$filtered): ?>
        <?= render_empty_state('Aucun document disponible', 'bi-folder2-open') ?>
    <?php else: ?>
        <div class="doc-grid">
        <?php foreach ($filtered as $d):
            [$icon, $iconCls] = doc_icon($d['mime_type']);
            $isHighlight = $highlightId && $d['id'] === $highlightId;
            $viewUrl = '/newspocspace/api.php?action=serve_document&id=' . urlencode($d['id']);
        ?>
            <div class="doc-card<?= $isHighlight ? ' doc-highlight' : '' ?>"
                 data-doc-id="<?= h($d['id']) ?>"
                 data-doc-url="<?= h($viewUrl) ?>"
                 data-doc-mime="<?= h($d['mime_type'] ?? '') ?>"
                 data-doc-titre="<?= h($d['titre']) ?>"
                 <?= $isHighlight ? 'id="docHighlight"' : '' ?>>
                <div class="doc-card-top">
                    <div class="doc-icon-box <?= $iconCls ?>"><i class="bi <?= $icon ?>"></i></div>
                    <div class="doc-card-info">
                        <div class="doc-card-title"><?= h($d['titre']) ?></div>
                        <div class="doc-card-filename"><?= h($d['original_name']) ?></div>
                    </div>
                </div>
                <?php if ($d['description']): ?>
                    <div class="doc-card-desc"><?= h($d['description']) ?></div>
                <?php else: ?>
                    <div class="doc-card-desc doc-card-desc-empty">Pas de description</div>
                <?php endif ?>
                <div class="doc-card-footer">
                    <div class="doc-card-meta">
                        <span class="doc-service-badge" style="background:<?= h($d['service_couleur']) ?>15;color:<?= h($d['service_couleur']) ?>">
                            <i class="bi bi-<?= h($d['service_icone']) ?>"></i> <?= h($d['service_nom']) ?>
                        </span>
                        <span><?= doc_fmt_size($d['size']) ?></span>
                        <span><?= fmt_date_fr($d['created_at']) ?></span>
                    </div>
                    <div class="doc-card-actions">
                        <button type="button" class="doc-action-btn" title="Voir" data-doc-view>
                            <i class="bi bi-eye"></i>
                        </button>
                        <a href="<?= h($viewUrl) ?>" download="<?= h($d['original_name']) ?>" class="doc-action-btn" title="Télécharger">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
