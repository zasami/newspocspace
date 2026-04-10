<?php
/**
 * Admin — Annonces officielles
 * Réutilise la page SpocCare. Les boutons d'édition redirigent vers /spoccare/annonce-edit.
 */
?>
<style>:root { --care-primary: #2d4a43; }</style>
<script<?= function_exists('nonce') ? nonce() : '' ?>>
(function(){
    if (typeof AdminURL === 'undefined') return;
    const _origGo = AdminURL.go.bind(AdminURL);
    AdminURL.go = function(page, id, params) {
        if (page === 'annonce-edit') {
            window.location.href = '/spoccare/annonce-edit' + (id ? '/' + encodeURIComponent(id) : '');
            return;
        }
        return _origGo(page, id, params);
    };
})();
</script>
<?php require __DIR__ . '/../../care/pages/annonces.php'; ?>
