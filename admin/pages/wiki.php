<?php
/**
 * Admin — Wiki / Base de connaissances
 * Réutilise la page SpocCare. Les boutons d'édition redirigent vers /spoccare/wiki-edit.
 */
?>
<style>:root { --care-primary: #2d4a43; }</style>
<script<?= function_exists('nonce') ? nonce() : '' ?>>
// Override AdminURL.go pour wiki-edit → rediriger vers SpocCare
(function(){
    if (typeof AdminURL === 'undefined') return;
    const _origGo = AdminURL.go.bind(AdminURL);
    AdminURL.go = function(page, id, params) {
        if (page === 'wiki-edit') {
            window.location.href = '/spoccare/wiki-edit' + (id ? '/' + encodeURIComponent(id) : '');
            return;
        }
        return _origGo(page, id, params);
    };
})();
</script>
<?php require __DIR__ . '/../../care/pages/wiki.php'; ?>
