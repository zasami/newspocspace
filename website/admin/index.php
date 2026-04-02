<?php
/**
 * Website Admin — Gestionnaire du site vitrine EMS La Terrassière
 * Auth: réutilise la session SpocSpace (admin/direction/responsable)
 */
require_once __DIR__ . '/../../init.php';

// Auth check — mêmes rôles que l'admin SpocSpace
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    header('Location: /spocspace/login');
    exit;
}

$user = $_SESSION['ss_user'];

// Charger les sections
$sections = Db::fetchAll(
    "SELECT * FROM website_sections WHERE page = 'index' ORDER BY sort_order ASC"
);

$sectionTypes = [
    'hero' => ['label' => 'Hero / Bannière', 'icon' => 'bi-display', 'color' => '#6366f1'],
    'cards' => ['label' => 'Cartes', 'icon' => 'bi-grid-3x3-gap', 'color' => '#3b82f6'],
    'services' => ['label' => 'Services', 'icon' => 'bi-clipboard2-pulse', 'color' => '#10b981'],
    'timeline' => ['label' => 'Timeline', 'icon' => 'bi-clock-history', 'color' => '#f59e0b'],
    'team' => ['label' => 'Équipe', 'icon' => 'bi-people', 'color' => '#8b5cf6'],
    'values' => ['label' => 'Valeurs', 'icon' => 'bi-award', 'color' => '#ec4899'],
    'contact' => ['label' => 'Contact', 'icon' => 'bi-chat-dots', 'color' => '#14b8a6'],
    'quote' => ['label' => 'Citation', 'icon' => 'bi-quote', 'color' => '#f97316'],
    'text' => ['label' => 'Texte libre', 'icon' => 'bi-text-paragraph', 'color' => '#64748b'],
    'custom' => ['label' => 'Personnalisé', 'icon' => 'bi-puzzle', 'color' => '#6b7280'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Site Vitrine EMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<!-- Topbar -->
<header class="wa-topbar">
    <div class="wa-topbar-left">
        <a href="/spocspace/website/" class="wa-logo" target="_blank">
            <i class="bi bi-globe2"></i>
            <span>Site Vitrine</span>
        </a>
        <span class="wa-sep"></span>
        <h1 class="wa-topbar-title">Gestionnaire de contenu</h1>
    </div>
    <div class="wa-topbar-right">
        <a href="/spocspace/website/" class="wa-btn wa-btn-ghost" target="_blank">
            <i class="bi bi-eye"></i> Voir le site
        </a>
        <a href="/spocspace/admin/" class="wa-btn wa-btn-ghost">
            <i class="bi bi-arrow-left"></i> Admin SpocSpace
        </a>
        <div class="wa-user">
            <div class="wa-user-avatar"><?= strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)) ?></div>
            <span class="wa-user-name"><?= h($user['prenom'] ?? '') ?></span>
        </div>
    </div>
</header>

<!-- Main -->
<main class="wa-main">
    <!-- Sidebar: liste des sections -->
    <aside class="wa-sidebar" id="waSidebar">
        <div class="wa-sidebar-header">
            <h2><i class="bi bi-layers"></i> Sections</h2>
            <button class="wa-btn wa-btn-sm wa-btn-primary" id="waAddSection">
                <i class="bi bi-plus-lg"></i> Ajouter
            </button>
        </div>
        <div class="wa-section-list" id="waSectionList">
            <?php foreach ($sections as $s):
                $type = $sectionTypes[$s['section_type']] ?? $sectionTypes['custom'];
                $content = json_decode($s['content'], true) ?: [];
            ?>
            <div class="wa-section-item <?= $s['is_visible'] ? '' : 'wa-hidden-section' ?>"
                 data-id="<?= h($s['id']) ?>"
                 data-key="<?= h($s['section_key']) ?>"
                 data-type="<?= h($s['section_type']) ?>"
                 draggable="true">
                <div class="wa-section-drag"><i class="bi bi-grip-vertical"></i></div>
                <div class="wa-section-icon" style="color:<?= $type['color'] ?>">
                    <i class="bi <?= $type['icon'] ?>"></i>
                </div>
                <div class="wa-section-info">
                    <div class="wa-section-name"><?= h($s['section_key']) ?></div>
                    <div class="wa-section-type"><?= h($type['label']) ?></div>
                </div>
                <div class="wa-section-actions">
                    <button class="wa-btn-icon wa-toggle-vis" title="<?= $s['is_visible'] ? 'Masquer' : 'Afficher' ?>">
                        <i class="bi <?= $s['is_visible'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- Editor panel -->
    <section class="wa-editor" id="waEditor">
        <div class="wa-editor-empty" id="waEditorEmpty">
            <div class="wa-editor-empty-icon"><i class="bi bi-hand-index"></i></div>
            <h3>Sélectionnez une section</h3>
            <p>Cliquez sur une section dans la liste pour la modifier, ou ajoutez-en une nouvelle.</p>
        </div>
        <div class="wa-editor-content" id="waEditorContent" style="display:none">
            <!-- Filled dynamically by JS -->
        </div>
    </section>
</main>

<!-- Template: Modal ajout section -->
<div class="wa-modal-overlay" id="waAddModal" style="display:none">
    <div class="wa-modal">
        <div class="wa-modal-header">
            <h3><i class="bi bi-plus-circle"></i> Nouvelle section</h3>
            <button class="wa-modal-close" id="waAddModalClose">&times;</button>
        </div>
        <div class="wa-modal-body">
            <div class="wa-form-group">
                <label>Identifiant (clé unique)</label>
                <input type="text" class="wa-input" id="waNewKey" placeholder="ex: temoignages">
            </div>
            <div class="wa-form-group">
                <label>Type de section</label>
                <div class="wa-type-grid">
                    <?php foreach ($sectionTypes as $key => $t): ?>
                    <label class="wa-type-option">
                        <input type="radio" name="waNewType" value="<?= $key ?>" <?= $key === 'cards' ? 'checked' : '' ?>>
                        <div class="wa-type-card">
                            <i class="bi <?= $t['icon'] ?>" style="color:<?= $t['color'] ?>"></i>
                            <span><?= $t['label'] ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wa-form-group">
                <label>Titre (optionnel)</label>
                <input type="text" class="wa-input" id="waNewTitle" placeholder="Titre de la section">
            </div>
        </div>
        <div class="wa-modal-footer">
            <button class="wa-btn wa-btn-ghost" id="waAddCancel">Annuler</button>
            <button class="wa-btn wa-btn-primary" id="waAddConfirm"><i class="bi bi-plus-lg"></i> Créer</button>
        </div>
    </div>
</div>

<!-- Section data for JS -->
<script>
window.__WA_SECTIONS = <?= json_encode(array_map(function($s) {
    $s['content'] = json_decode($s['content'], true) ?: [];
    return $s;
}, $sections), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
window.__WA_TYPES = <?= json_encode($sectionTypes, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
