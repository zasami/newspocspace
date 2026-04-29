# Plan de migration SSR — SpocSpace SPA employé

Ce document décrit la migration progressive de la SPA employé depuis le rendu **100% JS** vers un rendu **PHP SSR + JS d'enrichissement**. Pattern identique à l'admin, adapté à la navigation SPA et au mode offline existant.

## 1. Objectifs

- **HTML dans fichiers `.php`** : coloration syntaxique, boucles `foreach`, vraie structure
- **Escape automatique** via `h()` — fin des `escapeHtml()` éparpillés
- **JS minimal** : uniquement les interactions (modals, clics, AJAX de mutation)
- **Conservation** de la navigation SPA (app.js continue de `fetch` `pages/*.php`)
- **Conservation** du mode offline (SW cache le HTML rendu au lieu du JSON)
- **Sécurité** : identique — le backend PHP reste la source de vérité pour les autorisations
- **Cohérence** avec l'admin (même patterns de rendu)

## 2. Pattern standard à appliquer

### Structure d'une page migrée

```php
<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }

$uid = $_SESSION['ss_user']['id'];

// ─── 1. Chargement des données (PHP) ───
$stag = Db::fetch("SELECT ... WHERE user_id = ?", [$uid]);
$reports = Db::fetchAll("SELECT ... WHERE stagiaire_id = ?", [$stag['id']]);

// ─── 2. Calculs préliminaires ───
$nValides = count(array_filter($reports, fn($r) => $r['statut'] === 'valide'));
$pct = $stag ? round((...) * 100) : 0;

// ─── 3. Inclusion helpers partagés ───
require_once __DIR__ . '/_partials/stat_card.php'; // fonction render_stat_card()
?>

<div class="page-wrap">
  <!-- Breadcrumb (si applicable) -->
  <button class="btn btn-sm btn-link re-back-link mb-1 px-0" data-link="parent-page">
    <i class="bi bi-arrow-left"></i> Page parente
  </button>

  <!-- Header standard -->
  <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="page-title mb-0"><i class="bi bi-xxx"></i> Titre de page</h2>
    <div class="d-flex gap-2"><!-- actions --></div>
  </div>

  <!-- Stats cards (helper PHP) -->
  <div class="row g-3 mb-3">
    <?= render_stat_card('Reports validés', $nValides, 'bi-check-circle', 'teal') ?>
    <?= render_stat_card('À valider', $nSoumis, 'bi-clock-history', 'orange') ?>
  </div>

  <!-- Contenu : PHP boucles directement -->
  <?php foreach ($reports as $r): ?>
    <div class="ms-report">
      <strong><?= h($r['date_report']) ?></strong>
      <span class="ss-badge ss-badge-<?= h($r['statut']) ?>"><?= h($r['statut']) ?></span>
      <div class="ms-report-content"><?= $r['contenu'] /* TipTap HTML pré-sanitized */ ?></div>
    </div>
  <?php endforeach; ?>
</div>

<script nonce="<?= CSP_NONCE ?>">
// JS minimal — uniquement interactions
import('/newspocspace/assets/js/modules/page-name.js?v=<?= APP_VERSION ?>');
</script>
```

### Module JS correspondant (léger)

```js
import { apiPost, ssConfirm, toast } from '../helpers.js';

export function init() {
  document.addEventListener('click', async (e) => {
    const delBtn = e.target.closest('[data-del-report]');
    if (delBtn) {
      if (!await ssConfirm('Supprimer ?')) return;
      const r = await apiPost('delete_my_report', { id: delBtn.dataset.delReport });
      if (r.success) {
        // Refresh : recharger la page via SPA router
        location.reload();
      }
    }
  });
}

export function destroy() {}
```

**Principe de refresh** : après mutation, plutôt que de reconstruire le DOM en JS, on recharge la page (via `location.reload()` ou mieux, via le routeur SPA qui re-fetch le fragment PHP). Le serveur renvoie le HTML à jour.

## 3. Ordre de migration (3 phases)

### Phase 1 — Pages simples (affichage principalement)

Priorité haute : ces pages ont peu d'interactions, migration rapide, gros gain de lisibilité.

| Page | Complexité | Note |
|---|---|---|
| `mon-stage` | ⭐ | Prototype de référence — migrer en premier |
| `profile` | ⭐ | Affichage user + édition simple |
| `annuaire` | ⭐⭐ | Liste + recherche + appels (WebRTC reste JS) |
| `fiches-salaire` | ⭐ | Liste téléchargements |
| `annonces` | ⭐⭐ | Liste + marquer lu |
| `documents` | ⭐⭐ | Liste + filtres |

**Estimation** : 2-3h par page une fois le prototype validé.

### Phase 2 — Pages moyennes (interactions modérées)

| Page | Complexité | Note |
|---|---|---|
| `mes-stagiaires` | ⭐⭐ | Cartes + navigation vers détail |
| `stagiaire-detail` | ⭐⭐⭐ | Stats + tabs + modal éval |
| `collegues` | ⭐⭐ | Grille + recherche |
| `covoiturage` | ⭐⭐ | Liste + matching |
| `desirs` | ⭐⭐⭐ | Formulaire + calendrier |
| `vacances` | ⭐⭐⭐ | Calendrier + validation |
| `absences` | ⭐⭐ | Formulaire + justificatif |
| `changements` | ⭐⭐⭐ | Propositions + validation |
| `votes` | ⭐⭐ | Propositions + vote |
| `sondages` | ⭐⭐ | Formulaire dynamique |
| `pv` | ⭐⭐⭐ | Lecture + commentaires + rating |

**Estimation** : 4-5h par page.

### Phase 3 — Pages complexes (JS lourd, garder partiellement)

| Page | Complexité | Stratégie |
|---|---|---|
| `planning` | ⭐⭐⭐⭐ | SSR coque + grille hebdo dynamique reste JS |
| `emails` | ⭐⭐⭐⭐ | Liste SSR, composer reste SPA (TipTap) |
| `mur` | ⭐⭐⭐⭐ | SSR initial + JS pour scroll infini/likes |
| `wiki` | ⭐⭐⭐⭐ | SSR par page, TipTap pour édition |
| `report-edit` | ⭐⭐⭐⭐ | TipTap + checklist dynamique — garde SPA |
| `repartition` | ⭐⭐⭐⭐ | Grille complexe — évaluer au cas par cas |
| `cuisine-*` | ⭐⭐⭐⭐ | Au cas par cas |
| `home` | ⭐⭐⭐ | Dashboard — SSR complet possible |

**Certaines pages resteront en SPA pure** si le coût de migration > bénéfice (TipTap, grilles interactives).

## 4. Helpers PHP à créer (fichiers partagés)

À placer dans `pages/_partials/` :

### `_partials/stat_card.php`
```php
<?php
function render_stat_card($label, $value, $icon, $variant = 'teal', $sub = null) {
    ob_start(); ?>
    <div class="col-sm-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-<?= h($variant) ?>"><i class="bi <?= h($icon) ?>"></i></div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-value"><?= $value /* trust — souvent du HTML safe */ ?></div>
                <div class="stat-label"><?= h($label) ?><?php if ($sub): ?> <span class="stat-sub">· <?= h($sub) ?></span><?php endif ?></div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
```

### `_partials/page_header.php`
```php
<?php
function render_page_header($title, $icon, $backLink = null, $backLabel = null, $actions = '') {
    ob_start(); ?>
    <?php if ($backLink): ?>
    <button class="btn btn-sm btn-link re-back-link mb-1 px-0" data-link="<?= h($backLink) ?>">
        <i class="bi bi-arrow-left"></i> <?= h($backLabel ?: $backLink) ?>
    </button>
    <?php endif ?>
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="page-title mb-0"><i class="bi <?= h($icon) ?>"></i> <?= h($title) ?></h2>
        <div class="d-flex gap-2"><?= $actions ?></div>
    </div>
    <?php return ob_get_clean();
}
```

### `_partials/badges.php`
```php
<?php
function render_statut_badge($statut) {
    $map = [
        'valide' => 'ss-badge-acquis',
        'soumis' => 'ss-badge-en_cours',
        'brouillon' => 'ss-badge-brouillon',
        'a_refaire' => 'ss-badge-non_acquis',
        'actif' => 'ss-badge-actif',
        'prevu' => 'ss-badge-prevu',
    ];
    $cls = $map[$statut] ?? 'ss-badge-brouillon';
    return '<span class="ss-badge ' . $cls . '">' . h($statut) . '</span>';
}

function render_type_badge($type) {
    return '<span class="ss-badge ss-badge-type">' . h($type) . '</span>';
}

function render_empty($message = 'Aucun élément', $icon = 'bi-inbox') {
    return '<div class="card card-body text-center text-muted small">'
         . '<i class="bi ' . h($icon) . '" style="font-size:1.6rem;opacity:.25"></i>'
         . '<div class="mt-2">' . h($message) . '</div></div>';
}
```

### `_partials/progress_bar.php`
```php
<?php
function render_progress($percent, $label = null) {
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
```

## 5. Stratégie Service Worker (offline)

### Changement minimal nécessaire

Aujourd'hui le SW cache `pages/*.php` + `api.php?action=get_*` séparément. Après migration, on cache surtout les **pages PHP** (qui contiennent déjà les données).

**Avantages** :
- Plus besoin de synchroniser JSON + templates pour l'offline
- Le contenu offline est toujours "cohérent" (HTML et data figés ensemble)

**Inconvénient** :
- Les données ne sont rafraîchies qu'au rechargement de la page
- La queue de sync pour les mutations reste inchangée

### Actions à conserver en JSON (mutations + données qui doivent être live)

- Toutes les actions POST (save, delete, validate…) → API JSON classique
- Notifications temps réel (`get_poll_data`) → JSON
- Compteurs de badges (unread, pending) → JSON via polling léger

## 6. Checklist par page (à suivre pour chaque migration)

Pour chaque page migrée :

- [ ] **Avant de commencer** : commit backup (`git add -A && git commit -m "backup avant migration X"`)
- [ ] **Lister les actions API utilisées** dans le module JS actuel
- [ ] **Identifier** : quelles sont les données d'affichage (SSR) vs actions (JSON)
- [ ] **Réécrire `pages/xxx.php`** : chargement DB + HTML complet avec `<?= ?>`
- [ ] **Réduire `assets/js/modules/xxx.js`** : uniquement les handlers d'interaction
- [ ] **Appliquer `h()` systématiquement** sur toutes les variables user
- [ ] **Utiliser les helpers** (`render_stat_card`, `render_page_header`, etc.)
- [ ] **Vérifier** : `require_auth()` ou équivalent en tête du fichier PHP
- [ ] **Tester** :
  - [ ] Navigation depuis une autre page (via data-link)
  - [ ] Rechargement direct (`/newspocspace/page`)
  - [ ] Mode offline (SW cache)
  - [ ] Responsive mobile
  - [ ] Dark mode si applicable
- [ ] **Bump `CACHE_VERSION`** dans sw.js
- [ ] **Commit** : `"migrate page X to SSR"`

## 7. Règles de sécurité à préserver

- **Jamais** de `<?= $var ?>` sans `h()` sur variables user (sauf HTML volontaire type TipTap déjà sanitized à la saisie)
- **Jamais** de `<script>` avec du contenu dynamique non-nonce — toujours `nonce="<?= CSP_NONCE ?>"`
- **Jamais** d'accès DB direct sans `require_auth()` en tête
- **Validation des paramètres** (`Sanitize::text`, `Sanitize::int`) si requête GET contient des filtres
- **Prepared statements uniquement** via `Db::fetch`, `Db::fetchAll`, `Db::exec`

## 8. Critères d'arrêt

On arrête de migrer une page si :
- Elle a >500 lignes de JS d'interaction complexe (TipTap, canvas, drag&drop)
- Elle fait du polling/WebSocket fréquent (update >1x/30s)
- Les bénéfices ne justifient pas 5h+ de travail

Dans ces cas, **documenter** pourquoi la page reste en SPA pure.

## 9. Prototype : commencer par `mon-stage`

Prochaine étape concrète :
1. Créer les helpers `_partials/stat_card.php`, `_partials/page_header.php`, `_partials/badges.php`, `_partials/progress_bar.php`
2. Réécrire `pages/mon-stage.php` en SSR complet
3. Vider `assets/js/modules/mon-stage.js` (ne garder que les handlers clic)
4. Bump SW version
5. Valider avec l'utilisateur en production
6. Si OK, appliquer le pattern aux autres pages Phase 1

## 10. Gain estimé

Par page migrée :
- **-60% de lignes de JS** (moins de templates littéraux)
- **+lisibilité** : HTML lisible dans .php, debug plus facile
- **-bugs** d'escape (plus de `escapeHtml()` oubliés)
- **Même offline** (le SW cache la page PHP rendue)
- **Sécurité inchangée** (API reste le garde-fou)
