# CLAUDE.md — Newspocspace

Ce fichier guide tout le travail dans `newspocspace/`. Il **remplace** localement le CLAUDE.md de spocspace pour ce qui concerne le **visuel et le styling**. Le backend, l'API et la logique métier suivent toujours les conventions de [../spocspace/CLAUDE.md](../spocspace/CLAUDE.md).

## 1 — Contexte du projet

**Newspocspace** est un fork visuel de Spocspace, démarré en avril 2026 pour migrer l'app de Bootstrap 5 vers Tailwind CSS avec un design system propre appelé **Spocspace Care**.

- **Métier** : SaaS de gestion d'EMS (Établissements Médico-Sociaux) genevois — affilié Fegems.
- **Vocabulaire EMS à respecter dans les textes UI** : EMS, Fegems, HPCI, BLS-AED, INC, BPSD ; ASSC, ASA, ASE, AFP, CFC ; référent·e, actes délégués, cartographie de compétences. Toujours en français de Suisse romande.
- **Backend partagé** : la BDD (MariaDB) et la logique PHP/api sont partagées avec spocspace pour l'instant. **Ne pas toucher** à la logique métier ; uniquement au visuel.
- **Origine du fork** : commit `066d4b3` — les chemins `/spocspace/` ont été remplacés par `/newspocspace/`, le service worker renommé (`ns-v*`), Bootstrap retiré, Tailwind CDN installé.

## 2 — Stack visuelle

### Tailwind CSS (Play CDN)

Configuration globale dans [tailwind-config.php](tailwind-config.php) — **ne PAS modifier la palette** sans en parler. Ce fichier est inclus depuis chaque shell PHP :

```php
<?php include __DIR__ . '/tailwind-config.php'; ?>      // depuis index.php (racine)
<?php include __DIR__ . '/../tailwind-config.php'; ?>   // depuis admin/index.php ou care/index.php
```

> Le fichier expose le design system « Spocspace Care » via `tailwind.config = {...}` au moment du load CDN. Toutes les classes custom (teal-*, ink, muted, line, surface, ok/warn/danger/info, sec-*) sont définies là.

### Layout de référence

[_layout_tailwind.php](_layout_tailwind.php) — gabarit complet (sidebar + topbar + cards + boutons + badges + progress) en Tailwind/Spocspace Care. Visitable directement via `/newspocspace/_layout_tailwind.php`. Sert de **base de copie** pour les futures pages migrées.

## 3 — INTERDICTIONS

- ❌ **PAS de Bootstrap** (CSS ni JS). Retiré du chargement global. Si un appel `bootstrap.Modal(...)` apparaît dans une page non-migrée, c'est attendu (la page sera no-op au niveau modal jusqu'à sa migration).
- ❌ **PAS de classes Bootstrap** (`btn`, `btn-primary`, `card`, `container`, `row`, `col-*`, `d-flex`, `bg-light`, etc.) dans toute nouvelle page ou modification.
- ❌ **PAS de couleurs Tailwind par défaut** : `bg-blue-*`, `bg-emerald-*`, `bg-green-*`, `bg-red-*`, `bg-yellow-*`, `bg-gray-*`, `bg-slate-*` interdits.
- ❌ **PAS de Font Awesome**, pas d'emoji comme icônes UI.
- ❌ **PAS de hex hardcodés** (sauf `text-[#cfe0db]` typo douce sur sidebar — déjà couvert dans le layout de réf).

## 4 — Règles de styling (obligatoires)

### Couleurs

| Usage | Tokens à utiliser |
|---|---|
| Primaire | `bg-teal-600` `text-teal-600` `border-teal-600` |
| Hover primaire | `hover:bg-teal-700` |
| Titres | `text-ink` |
| Texte courant | `text-ink-2` |
| Texte secondaire | `text-ink-3` ou `text-muted` |
| Statut OK | `text-ok` + `bg-ok-bg` + `border-ok-line` |
| Statut WARN | `text-warn` + `bg-warn-bg` + `border-warn-line` |
| Statut DANGER | `text-danger` + `bg-danger-bg` + `border-danger-line` |
| Statut INFO | `text-info` + `bg-info-bg` + `border-info-line` |
| Bordures | `border-line` (par défaut), `border-line-2`, `border-line-3` |
| Surfaces | `bg-surface` (cards), `bg-surface-2` (zones secondaires), `bg-surface-3` (input bg) |
| Fond global | `bg-bg` |
| Secteurs EMS Fegems | `bg-sec-soins` `bg-sec-hotel` `bg-sec-anim` `bg-sec-int` `bg-sec-tech` `bg-sec-admin` `bg-sec-mgmt` (chacun a son `-bg`) |

### Polices

- **Titres** (h1-h6) : `font-display` (Fraunces) — appliqué automatiquement
- **Corps** (body) : `font-body` (Outfit) — appliqué automatiquement
- **Données techniques, codes, chiffres** : `font-mono tabular-nums` (JetBrains Mono)

### Ombres

`shadow-sp-sm` `shadow-sp` `shadow-sp-md` `shadow-sp-lg` — calibrées pour le fond pastel.

### Gradients

`bg-grad-hero` `bg-grad-sidebar` `bg-grad-mark` `bg-grad-progress` — voir [tailwind-config.php](tailwind-config.php).

### Composants standards

Les snippets de référence (bouton primaire/secondaire, card, badge statut, sidebar) sont dans [_layout_tailwind.php](_layout_tailwind.php). Quand on migre une page, **on copie ces patterns**, on n'invente pas.

### Icônes

- **SVG inline** uniquement, avec `stroke="currentColor"` et `stroke-width="1.8"` ou `2`
- Tailles standard : `width="14|16|18|20|22"` selon contexte
- Sources recommandées : Lucide, Feather, Heroicons (outline)

## 5 — Bootstrap-icons (transition)

`bootstrap-icons.min.css` est **gardé temporairement** dans le chargement des shells (`index.php`, `admin/index.php`, `care/index.php`). C'est l'icon-font, pas le framework Bootstrap. Les pages non-migrées utilisent encore `<i class="bi bi-foo">` ; sans cette CSS, elles n'auraient plus d'icônes du tout.

→ **Quand toutes les pages seront migrées en SVG inline**, retirer aussi cette ligne et supprimer `assets/css/vendor/bootstrap-icons.min.css` + `admin/assets/css/vendor/bootstrap-icons.min.css`.

## 6 — État de la migration (à mettre à jour à chaque page faite)

| Phase | Pages | Statut |
|---|---|---|
| Shells | `index.php` `admin/index.php` `care/index.php` | ✓ Bootstrap retiré, Tailwind installé. **HTML interne pas encore re-stylé.** |
| Layout référence | `_layout_tailwind.php` | ✓ |
| Pages employé SPA | `pages/*.php` (~30) | ❌ aucune migrée |
| Pages admin | `admin/pages/*.php` (~30) | ❌ aucune migrée |
| Pages care | `care/pages/*.php` | ❌ aucune migrée |

> Les pages **paraissent cassées visuellement** (mises en page Bootstrap qui n'existent plus). C'est normal et attendu pendant la migration. **Ne pas tenter de "réparer" automatiquement avec un script global.**

## 7 — Process pour migrer une page

1. Identifier la page (ex: `pages/profile.php`)
2. Ouvrir [_layout_tailwind.php](_layout_tailwind.php) à côté pour s'inspirer du structurel
3. Récrire le HTML/CSS en remplaçant chaque classe Bootstrap par son équivalent Tailwind/Spocspace Care
4. Garder la **logique PHP intacte** (queries DB, sessions, sanitization — règles dans [../spocspace/CLAUDE.md](../spocspace/CLAUDE.md))
5. Tester : la page s'affiche correctement et le backend fonctionne toujours
6. Mettre à jour la table de §6 et committer

## 8 — Setup git

Repo : `git@github.com:zasami/newspocspace.git` (privé).

Push depuis Infomaniak : la clé SSH dédiée `~/.ssh/id_ed25519_newspocspace` est configurée via `git config core.sshCommand` localement. Donc un `git push` suffit.

## 9 — Quand un futur Claude lit ce fichier

- Si on me demande **« stylise X »** : respecter scrupuleusement Spocspace Care. Pas de Bootstrap. Copier les patterns du layout de référence.
- Si on me demande de **migrer une page** : suivre le process §7.
- Si on me demande de **switcher en Tailwind compilé** (npm/vite plus tard) : ne pas retirer le Play CDN sans plan complet de bascule.
- Si on me demande de **changer la palette** : refuser et renvoyer ici. La palette est validée avec l'utilisateur.
- Si on me demande **« on fait quoi maintenant »** : la priorité est de migrer les pages **une par une**, pas d'optimiser le bundle ni d'ajouter des features.
