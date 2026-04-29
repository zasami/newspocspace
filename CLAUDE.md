# CLAUDE.md — Newspocspace

> 🚨 **À LIRE EN PREMIER POUR CHAQUE NOUVELLE SESSION DANS CE DOSSIER.**
> Tout le contexte design + le mode opératoire convenu avec l'utilisateur est ici.
> Le backend, l'API et la logique métier suivent toujours [../spocspace/CLAUDE.md](../spocspace/CLAUDE.md).

## 0 — Décision **CLEAN SLATE** (avril 2026, irréversible côté visuel)

Le 29 avril 2026 l'utilisateur a tranché **« 0 Bootstrap, 0 ancien CSS, on repart à zéro »** :

- ✅ **Tailwind Play CDN** chargé par [tailwind-config.php](tailwind-config.php) — design system Spocspace Care
- ✅ **Helper SVG icons** [_partials/icons.php](_partials/icons.php) → `ss_icon('house', 'w-4 h-4')` — Lucide-style stroke-width 1.8
- ❌ **bootstrap.min.css** retiré
- ❌ **bootstrap.bundle.min.js** retiré
- ❌ **bootstrap-icons.min.css** retiré
- ❌ **assets/css/spocspace.css** retiré
- ❌ **assets/css/ss-colors.css**, **emoji-picker.css**, **annonces.css**, **pages-all.css**, **themes.css** retirés
- ❌ **admin/assets/css/admin.css**, **editor.css**, **competences.css**, **themes.css** retirés
- ❌ Tous les `<i class="bi bi-X">` remplacés par `<?= ss_icon('X') ?>`

> **Conséquence assumée** : ~70 pages internes (chargées dans le shell SPA ou dans le shell admin) sont **visuellement cassées** car elles utilisent encore les classes Bootstrap (`btn`, `card`, `row`, `col-*`, `<i class="bi-*">`, etc.). C'est l'état attendu jusqu'à ce qu'on les migre **une par une**.

## 1 — Mode opératoire convenu

1. **L'utilisateur fournit une maquette** (image/Figma/description) pour chaque écran à migrer.
2. **Claude implémente** en pur Tailwind/Spocspace Care, **en s'inspirant de [_layout_tailwind.php](_layout_tailwind.php)**.
3. **Logique PHP / API / data → intacte** (queries DB, routes, sessions, sanitization). On ne touche que le HTML + classes.
4. **Pas de script de migration globale** — chaque page est faite à la main. *« 5 pages magnifiques plutôt que 70 moches »*.

### Règle d'or git

**TOUJOURS** `pwd` avant `git push` — vérifier qu'on est dans `/sites/zkriva.com/newspocspace/` (jamais `/spocspace/`). Mauvaise erreur déjà commise une fois (push accidentel d'un commit vers `zasami/SpocSpace`).

## 2 — Stack visuelle

### Tailwind CSS (Play CDN) — [tailwind-config.php](tailwind-config.php)

**Inclus depuis** :
- [index.php](index.php) (shell SPA employé) : `<?php include __DIR__ . '/tailwind-config.php'; ?>`
- [admin/index.php](admin/index.php) (shell admin) : `<?php include __DIR__ . '/../tailwind-config.php'; ?>`
- [care/index.php](care/index.php) (shell care/SpocCare) : idem
- [_layout_tailwind.php](_layout_tailwind.php) (référence visuelle)

**Définit** : Google Fonts Fraunces+Outfit+JetBrains Mono · palette teal-* · ink/muted/line/surface · ok/warn/danger/info · sec-* (secteurs Fegems) · sb-* (textes sidebar) · gradients (sidebar-grad / mark-grad / hero / progress) · ombres sp-* + mark.

### Helper SVG icons — [_partials/icons.php](_partials/icons.php)

```php
<?= ss_icon('house') ?>                              // 4x4 par défaut, opacity 85, stroke 1.8
<?= ss_icon('star', 'w-5 h-5 text-teal-600') ?>     // taille / couleur custom
```

**Ajouter une icône** : éditer le `match` dans la fonction. Format SVG (Lucide-style outline). Si nom inconnu → cercle de fallback.

### Layout de référence — [_layout_tailwind.php](_layout_tailwind.php)

Page autonome visitable sur `/newspocspace/_layout_tailwind.php`. Contient le design canonique : sidebar gradient teal foncé + topbar surface + cards stats + badges + progress + boutons. **Toute nouvelle page doit s'inspirer de ce gabarit.**

## 3 — INTERDICTIONS (hard rules)

- ❌ **PAS de Bootstrap** (CSS ni JS ni icons)
- ❌ **PAS de classes Bootstrap** (`btn`, `card`, `row`, `col-*`, `d-flex`, `bg-light`, `mb-3`, etc.) — sauf en lecture pour comprendre l'ancien code à migrer
- ❌ **PAS de `<i class="bi bi-X">`** — utiliser `<?= ss_icon('X') ?>`
- ❌ **PAS de couleurs Tailwind par défaut** : `bg-blue-*`, `bg-emerald-*`, `bg-green-*`, `bg-red-*`, `bg-yellow-*`, `bg-gray-*`, `bg-slate-*` interdits
- ❌ **PAS de Font Awesome**, pas d'emoji comme icône UI
- ❌ **PAS de hex hardcodés** sauf cas justifiés du design system (déjà couverts)
- ❌ **PAS de réimport** des CSS retirés (admin.css, spocspace.css, etc.) — ils sont volontairement supprimés

## 4 — Tokens à utiliser

### Couleurs

| Usage | Tokens |
|---|---|
| Primaire | `bg-teal-600` `text-teal-600` `border-teal-600` `hover:bg-teal-700` |
| Texte | `text-ink` (titres) · `text-ink-2` (corps) · `text-ink-3` / `text-muted` (secondaire) · `text-muted-2` (placeholder) |
| Statut | `text-ok` + `bg-ok-bg` + `border-ok-line` (idem warn/danger/info) |
| Bordures | `border-line` · `border-line-2` · `border-line-3` |
| Surfaces | `bg-surface` (cards) · `bg-surface-2` · `bg-surface-3` (input bg) · `bg-bg` (fond global) |
| Sidebar | `bg-sidebar-grad` · `text-sb-text` · `text-sb-text-hover` · `text-sb-section` · `text-sb-sub` · `text-sb-muted` |
| Mark logo | `bg-mark-grad` · `shadow-mark` |
| Item actif sidebar | `bg-[#7dd3a8]/[0.12]` + `before:bg-[#7dd3a8]` (barre 3×16px à gauche) |
| Secteurs Fegems | `sec-soins` `sec-hotel` `sec-anim` `sec-int` `sec-tech` `sec-admin` `sec-mgmt` |

### Polices

- **h1-h6** : `font-display` (Fraunces) — auto via @layer base
- **Body** : `font-body` (Outfit) — auto
- **Données / chiffres / mono** : `font-mono tabular-nums` (JetBrains Mono)

### Composants standard

Voir [_layout_tailwind.php](_layout_tailwind.php) pour copier-coller :
- Sidebar (mark "S" gradient + brand + sections + items + EMS card footer)
- Topbar (search + notif + avatar)
- Bouton primaire / secondaire / destructif
- Card (header gradient subtil + corps)
- Badge statut
- Progress bar
- Stat card

## 5 — État de la migration

### Shells (déjà migrés)

| Shell | Fichier | Statut |
|---|---|---|
| Employé SPA | [index.php](index.php) | ✓ Tailwind clean (sidebar + topbar + main wrapper) |
| Admin | [admin/index.php](admin/index.php) | ✓ Tailwind clean (sidebar + topbar + main wrapper) |
| Care/SpocCare | [care/index.php](care/index.php) | ⚠️ Tailwind config inclus mais HTML pas encore migré (à faire quand le user demande) |

### Pages migrées

| Page | Fichier | Maquette source |
|---|---|---|
| Login | [pages/login.php](pages/login.php) | Auto (puis raffiné avec footer trust badges Chiffré AES-256 + Hébergé en Suisse) |

### Pages à migrer (~70)

Tous les autres `pages/*.php` (employé) et `admin/pages/*.php` (admin) et `care/pages/*.php` (care). Elles fonctionnent côté backend mais sont **visuellement cassées**.

## 6 — Process pour migrer une page (workflow validé)

1. **L'utilisateur dit** : « migre `pages/profile.php` » (ou similaire) et fournit éventuellement une maquette
2. Claude **lit** le fichier actuel pour comprendre :
   - Ce qu'il affiche (queries DB, données affichées)
   - Les hooks JS (IDs, classes querySelected dans `assets/js/modules/{name}.js`)
   - Les forms (action, fields, IDs requis par auth.js / handler JS)
3. Claude **récrit** le HTML en Tailwind/Spocspace Care, **en gardant intact** :
   - PHP queries
   - IDs JS critiques
   - data-* attributes nécessaires aux modules JS
   - Form action / field names
4. Claude **commit + push** avec `pwd` vérifié, message clair
5. L'utilisateur teste et raffine si besoin

## 7 — Hooks JS à connaître (sidebar/topbar shells)

Pour ne pas casser quand on retouche les shells :

### Shell SPA employé (`index.php`)

| Élément | Hook | Utilisé par |
|---|---|---|
| Sidebar | `id="feSidebar"`, `class="fe-sidebar"` | `assets/js/app.js` (toggle mobile, no-nav) |
| Backdrop | `id="sidebarOverlay"` | `app.js` (mobile show/close) |
| Toggle btn | `id="sidebarToggleBtn"` | `app.js` (collapse desktop) |
| Items nav | `class="fe-sidebar-link"` + `data-link="page"` | `app.js` ligne 125 (active state matcher) |
| Catégories | `class="fe-sidebar-cat"` + `data-cat-toggle="catId"` | `app.js` (collapsibles) |
| Cat-items | `class="fe-sidebar-cat-items"` + `data-cat-body="catId"` + classe `.collapsed` | `app.js` |
| Logout | `id="logoutBtn"` | `app.js` ligne 286 (apiPost logout) |
| Topbar mobile menu | `id="mobileToggle"` | `app.js` (open sidebar mobile) |
| Search | `id="feSearchInput"`, `id="feSearchClear"`, `id="feSearchResults"` | `app.js` search module |
| Avatar topbar | `id="avatarToggleBtn"`, `id="topbarAvatar"` | menu profil dropdown |
| Sync indicator | `id="feSyncIndicator"`, `id="feSyncTime"` | sync status |
| Conn status | `id="feConnStatus"`, `id="feConnPending"` | online/offline |
| Title | `id="feTopbarTitle"` | mis à jour à chaque page load |
| Badges | `id="msgBadge"`, `id="msgBadgeSidebar"`, `id="annBadgeSidebar"` | notifications counters |

### Shell admin (`admin/index.php`)

| Élément | Hook |
|---|---|
| Sidebar | `id="adminSidebar"`, `class="admin-sidebar"` |
| Backdrop | `id="sidebarOverlay"`, `class="sidebar-overlay"` |
| Toggle btn | `id="sidebarToggleBtn"`, `id="sidebarShortcutsBtn"` |
| Items nav | `class="sidebar-link"` + active state via `$activeSection === $key` (server-side) |
| Topbar | `id="topbarSearch"`, `id="topbarSearchInput"`, `id="adminSearchClear"`, `id="topbarSearchResults"`, `id="topbarMsgNotif"`, `id="topbarMsgBadge"`, `id="topbarEmailNotif"`, `id="topbarEmailBadge"`, `id="topbarContactsBtn"`, `id="topbarAnnuaireBtn"`, `id="ztInstallBtn"`, `id="immersiveToggle"`, `id="mobileToggle"` |
| Cat | `class="sidebar-cat"` + `data-cat-toggle`, `class="sidebar-cat-items"` + `data-cat-body` |
| Badges sidebar | `id="sidebarMsgBadge"`, `id="sidebarEmailBadge"` |

⚠️ **Avant de modifier un shell**, grep les hooks ci-dessus dans `assets/js/app.js`, `admin/assets/js/helpers.js`, `admin/assets/js/url-manager.js` pour confirmer ce qui tape sur ces IDs/classes.

## 8 — Setup git

Repo : `git@github.com:zasami/newspocspace.git` (privé).
Clé SSH dédiée : `~/.ssh/id_ed25519_newspocspace` (clé personnelle séparée des autres).
Push depuis Infomaniak : la config git locale a `core.sshCommand` configuré → un simple `git push origin main` suffit. **Toujours `pwd` avant**.

## 9 — Quand un futur Claude ouvre une session ici

1. **Lire ce fichier en entier** (tu y es).
2. **Ne pas re-questionner** les choix tranchés (clean slate, pas de Bootstrap, mockups par l'utilisateur).
3. **Demander une maquette** si l'utilisateur dit « migre X » sans en fournir.
4. **S'inspirer de [_layout_tailwind.php](_layout_tailwind.php)** systématiquement.
5. **Préserver les hooks JS** (cf §7). Grep avant d'éditer un shell.
6. **`pwd` avant chaque `git push`**.
7. **Ne pas restaurer** les anciennes CSS retirées même si une page semble "cassée" — c'est attendu, on attend le mockup pour migrer.
