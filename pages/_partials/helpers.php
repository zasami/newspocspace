<?php
/**
 * Helpers PHP réutilisables pour le rendu SSR des pages SPA.
 * À inclure en tête des pages : require_once __DIR__ . '/_partials/helpers.php';
 */

if (!function_exists('render_stat_card')) {
    /**
     * Carte stat (palette SpocSpace)
     * @param string $label       Libellé (UPPERCASE auto via CSS)
     * @param mixed  $value       Valeur principale (chiffre ou HTML safe)
     * @param string $icon        Classe Bootstrap Icons (ex: 'bi-check-circle')
     * @param string $variant     'teal'|'green'|'orange'|'red'|'purple'|'neutral'
     * @param string $sub         Sous-texte (ex: 'sur 12', 'Moy. 4.0/5')
     */
    function render_stat_card($label, $value, $icon, $variant = 'teal', $sub = null)
    {
        ob_start(); ?>
        <div class="col-sm-6 col-md-4 col-lg">
            <div class="stat-card">
                <div class="stat-icon bg-<?= h($variant) ?>"><i class="bi <?= h($icon) ?>"></i></div>
                <div class="flex-grow-1 min-width-0">
                    <div class="stat-value"><?= $value /* peut contenir du HTML safe */ ?></div>
                    <div class="stat-label"><?= h($label) ?><?php if ($sub): ?> <span class="stat-sub">· <?= h($sub) ?></span><?php endif ?></div>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}

if (!function_exists('render_page_header')) {
    /**
     * Header standard SpocSpace avec breadcrumb optionnel
     * @param string $title       Titre
     * @param string $icon        Classe bi-xxx
     * @param string $backLink    Page parente (data-link), null = pas de breadcrumb
     * @param string $backLabel   Label du lien retour
     * @param string $actions     HTML des boutons d'action (déjà rendu)
     */
    function render_page_header($title, $icon, $backLink = null, $backLabel = null, $actions = '')
    {
        ob_start(); ?>
        <?php if ($backLink): ?>
        <button class="btn btn-sm btn-link re-back-link mb-1 px-0" data-link="<?= h($backLink) ?>">
            <i class="bi bi-arrow-left"></i> <?= h($backLabel ?: $backLink) ?>
        </button>
        <?php endif ?>
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="page-title mb-0"><i class="bi <?= h($icon) ?>"></i> <?= h($title) ?></h2>
            <?php if ($actions): ?><div class="d-flex gap-2"><?= $actions ?></div><?php endif ?>
        </div>
        <?php return ob_get_clean();
    }
}

if (!function_exists('render_statut_badge')) {
    /**
     * Badge statut (palette SpocSpace)
     */
    function render_statut_badge($statut, $label = null)
    {
        $map = [
            'valide'      => 'ss-badge-acquis',
            'acquis'      => 'ss-badge-acquis',
            'actif'       => 'ss-badge-actif',
            'soumis'      => 'ss-badge-en_cours',
            'en_cours'    => 'ss-badge-en_cours',
            'brouillon'   => 'ss-badge-brouillon',
            'a_refaire'   => 'ss-badge-non_acquis',
            'non_acquis'  => 'ss-badge-non_acquis',
            'refuse'      => 'ss-badge-non_acquis',
            'interrompu'  => 'ss-badge-non_acquis',
            'prevu'       => 'ss-badge-prevu',
            'termine'     => 'ss-badge-brouillon',
        ];
        $cls = $map[$statut] ?? 'ss-badge-brouillon';
        return '<span class="ss-badge ' . $cls . '">' . h($label ?? $statut) . '</span>';
    }
}

if (!function_exists('render_type_badge')) {
    function render_type_badge($type)
    {
        return '<span class="ss-badge ss-badge-type">' . h($type) . '</span>';
    }
}

if (!function_exists('render_empty_state')) {
    /**
     * État vide standardisé
     */
    function render_empty_state($message = 'Aucun élément', $icon = 'bi-inbox', $hint = null)
    {
        ob_start(); ?>
        <div class="card card-body text-center text-muted small py-4">
            <i class="bi <?= h($icon) ?>" style="font-size:1.8rem;opacity:.25"></i>
            <div class="mt-2"><?= h($message) ?></div>
            <?php if ($hint): ?><div class="mt-1 small"><?= h($hint) ?></div><?php endif ?>
        </div>
        <?php return ob_get_clean();
    }
}

if (!function_exists('render_progress_bar')) {
    /**
     * Barre de progression (0-100)
     */
    function render_progress_bar($percent, $label = null)
    {
        $percent = max(0, min(100, (int) $percent));
        ob_start(); ?>
        <?php if ($label): ?>
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span><?= h($label) ?></span><span><?= $percent ?>%</span>
        </div>
        <?php endif ?>
        <div class="mst-progress"><div class="mst-progress-bar" style="width:<?= $percent ?>%"></div></div>
        <?php return ob_get_clean();
    }
}

if (!function_exists('fmt_date_fr')) {
    function fmt_date_fr($date, $format = 'd.m.Y')
    {
        if (!$date) return '—';
        try { return (new DateTime($date))->format($format); }
        catch (Exception $e) { return h($date); }
    }
}

if (!function_exists('fmt_relative')) {
    /**
     * Date relative "il y a X"
     */
    function fmt_relative($date)
    {
        if (!$date) return '';
        $diff = time() - strtotime($date);
        if ($diff < 60)       return 'à l\'instant';
        if ($diff < 3600)     return 'il y a ' . floor($diff/60) . ' min';
        if ($diff < 86400)    return 'il y a ' . floor($diff/3600) . ' h';
        if ($diff < 2592000)  return 'il y a ' . floor($diff/86400) . ' j';
        return fmt_date_fr($date);
    }
}
