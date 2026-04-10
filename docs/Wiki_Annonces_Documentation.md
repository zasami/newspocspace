# Base de connaissances & Annonces officielles
## Documentation fonctionnelle complète — SpocSpace / SpocCare

> Document de référence sur le module Wiki + Annonces de SpocSpace : ce qui existe, pourquoi ces choix, ce qui peut être ajouté ou modifié.

---

## 1. Vision et positionnement

### 1.1 Le problème métier
Dans un EMS, l'information critique (protocoles de soins, procédures d'urgence, consignes RH, menus) est **dispersée** : classeurs papier, mails, post-its, dossier partagé. Conséquences :
- Les soignants ne trouvent pas l'information au bon moment
- Les versions obsolètes circulent
- Les nouveaux collaborateurs perdent du temps en formation
- Aucun moyen de **prouver** que tel protocole a été lu par l'équipe
- Aucune visibilité sur **ce qui manque** dans la base

### 1.2 Notre réponse
Deux outils complémentaires intégrés à SpocCare :

| Outil | Fonction | Inspiration |
|---|---|---|
| **Base de connaissances (Wiki)** | Stockage durable, recherche, structuré par catégories | Notion / Confluence / Guru |
| **Annonces officielles** | Communication descendante one-shot, urgences, news | SharePoint News / Slack #announcements |

**Différence fondamentale :**
- Le Wiki est de **l'information durable** qu'on consulte quand on en a besoin
- Une Annonce est de **l'information ponctuelle** qu'on pousse vers les gens

---

## 2. Architecture technique

### 2.1 Stockage
- **MySQL** (utf8mb4_unicode_ci)
- 11 tables dédiées : `wiki_pages`, `wiki_categories`, `wiki_tags`, `wiki_page_tags`, `wiki_versions`, `wiki_favoris`, `wiki_page_permissions`, `wiki_page_views`, `wiki_search_log`, `wiki_page_reviews`, `wiki_suggestions_log`
- 3 tables annonces : `annonces`, `annonce_views`, `annonce_acks`

### 2.2 Endpoints API
- **Admin / SpocCare** : `/spocspace/admin/api.php` → `admin_api_modules/wiki.php` + `annonces.php`
- **Employé SPA** : `/spocspace/api.php` → `api_modules/wiki.php`
- **Recherche globale unifiée** : `admin_global_search`, `admin_care_global_search`, `global_search`

### 2.3 Pages
- `/spoccare/wiki` — liste + lecture (collaborateurs et responsables)
- `/spoccare/wiki-edit` — éditeur (responsables uniquement)
- `/spoccare/annonces` — liste + lecture
- `/spoccare/annonce-edit` — éditeur
- `/spocspace/admin/wiki` — wrapper inclusion de la page care
- `/spocspace/admin/annonces` — idem
- `/spocspace/admin/wiki-analytics` — dashboard responsables

---

## 3. Fonctionnalités du Wiki

### 3.1 Catégories
**Quoi :** Regroupement thématique des pages (ex : Hygiène, Soins infirmiers, RH, Cuisine).

**Pourquoi :**
- Permet une navigation par thème quand on ne sait pas exactement ce qu'on cherche
- Couleur + icône → repérage visuel rapide
- Crée un sentiment d'ordre dans la base

**Comment :**
- Table `wiki_categories` (id, nom, slug, icône Bootstrap, couleur hex, ordre)
- Modal d'édition avec picker d'icônes (60+ choix Bootstrap Icons)
- Picker de couleur (palette douce)
- Drag & drop d'ordre possible (à implémenter si besoin)

**Alternatives écartées :**
- *Catégories hiérarchiques (sous-catégories)* : trop complexe pour un EMS, on a privilégié **catégories plates + tags**
- *Auto-catégorisation IA* : pas fiable, le manuel reste plus simple

---

### 3.2 Tags
**Quoi :** Étiquettes transversales attachées à une page (ex : `#urgent`, `#nuit`, `#protocole`, `#nouveau`).

**Pourquoi :**
- Une page peut avoir plusieurs facettes (un protocole peut être à la fois `#hygiène` et `#urgence`)
- Filtrage rapide depuis la liste
- Permet de créer des "vues virtuelles" sans dupliquer les pages

**Différence catégorie vs tag :**
- **Catégorie** = 1 et 1 seule (où la page "vit")
- **Tag** = 0 ou plusieurs (comment elle est trouvée)

**Implémentation :**
- Table `wiki_tags` + table de jonction `wiki_page_tags`
- 8 tags par défaut créés à l'install
- Couleur configurable par tag

---

### 3.3 Recherche FULLTEXT
**Quoi :** Recherche dans titre + description + contenu HTML, avec scoring de pertinence.

**Pourquoi le FULLTEXT plutôt que LIKE ?**
- LIKE `%mot%` est lent sur de gros contenus et ne classe pas par pertinence
- FULLTEXT MySQL est indexé → réponse en quelques ms même sur 10 000 pages
- Permet la recherche multi-mots, exclusions (`-mot`), exigences (`+mot`)

**Index :** `FULLTEXT(titre, description, contenu)` créé en migration `058_wiki_phase1.sql`

**Comportement client :**
- Mode BOOLEAN avec `*` à la fin pour le préfixe
- Fallback LIKE si la query est trop courte (< 4 caractères)
- Logging automatique de la query et du nombre de résultats (cf. Knowledge Gaps §6.2)

---

### 3.4 Favoris personnels
**Quoi :** Chaque utilisateur peut épingler des pages dans ses favoris (cœur).

**Pourquoi :**
- Une infirmière de nuit a besoin d'un accès rapide à 5-10 protocoles spécifiques
- Évite de re-chercher les mêmes pages tous les jours
- Filtre "Mes favoris" en haut de la liste

**Implémentation :**
- Table `wiki_favoris (user_id, page_id)`
- Toggle via `toggle_wiki_favori`
- Bouton cœur sur chaque card + filtre dédié

---

### 3.5 Verification (cycle de vérification)
**Quoi :** Chaque page peut avoir un **expert assigné** (responsable, IDE, médecin) qui doit la **revérifier régulièrement** (par défaut tous les 90 jours).

**Pourquoi :**
- Dans la santé, une procédure obsolète peut être dangereuse
- Force la maintenance proactive (pas seulement réactive)
- Donne une marque de confiance visible : "Vérifié par X le ...."
- Inspiré directement de **Guru "Knowledge Verification"**

**Logique :**
- À l'assignation : on définit `expert_id` + `verify_interval_days`
- À chaque vérification : `verified_at = NOW()`, `verify_next = NOW() + interval`
- Quand `verify_next <= NOW()` : badge rouge "À revérifier" + bandeau d'alerte en haut de la liste
- L'expert reçoit (à brancher) une notification

**Champs DB :**
```sql
expert_id CHAR(36) NULL
verified_at DATETIME NULL
verify_next DATETIME NULL
verify_interval_days INT DEFAULT 90
```

---

### 3.6 Permissions par rôle
**Quoi :** Une page peut être restreinte à certains rôles (ex : "RH" visible uniquement par Direction + Admin).

**Pourquoi :**
- Certaines infos sont sensibles (paie, sanctions, dossiers personnels)
- Le rôle est plus simple à gérer que les permissions par utilisateur
- Logique inclusive : si **aucune** permission n'est définie, la page est visible par tous

**Implémentation :**
```sql
wiki_page_permissions (page_id, role)
```
Si la table contient des lignes pour la page, seuls les rôles listés voient la page. Sinon = public.

**Filtre SQL :**
```sql
WHERE NOT EXISTS (SELECT 1 FROM wiki_page_permissions WHERE page_id = p.id)
   OR EXISTS (SELECT 1 FROM wiki_page_permissions WHERE page_id = p.id AND role = ?)
```

---

### 3.7 Versionnage
**Quoi :** Chaque modification du contenu sauvegarde l'ancienne version dans `wiki_versions`.

**Pourquoi :**
- Permet de **revenir en arrière** en cas d'erreur
- Trace qui a modifié quoi et quand
- Audit légal (santé : on doit pouvoir prouver la version en vigueur à une date X)

**Implémentation :**
- Avant chaque `UPDATE` qui change le contenu, INSERT dans `wiki_versions`
- Affichage en liste de cards (date, auteur, note de version, contenu complet)
- Bouton "Restaurer" pour réinjecter une version

---

### 3.8 Suggestions IA
**Quoi :** Sur la page d'accueil du wiki, propose des pages contextuellement pertinentes ("Comme vous travaillez dans X, vous pourriez avoir besoin de...").

**Pourquoi :**
- Découverte de contenu : aide à exploiter ce qui existe déjà
- Réduit le sentiment "il n'y a rien dans le wiki"
- Inspiré de Notion AI / Guru AI Suggest

**Implémentation actuelle (basique) :**
- Algorithme heuristique : pages récentes + tags fréquents + page la plus consultée par les collègues
- Table `wiki_suggestions_log` pour ne pas re-suggérer les mêmes
- Bouton "Masquer" pour dismisser une suggestion

**Évolutions possibles :**
- Brancher une vraie IA (Claude, GPT) : "Voici 10 protocoles, suggère-moi celui qui correspond à 'chute en chambre nuit'"
- Apprentissage des clics utilisateur

---

### 3.9 Image de couverture
**Quoi :** Bandeau image en haut de chaque page (comme les articles de blog).

**Pourquoi :**
- Identification visuelle dans la liste (cards avec image)
- Donne un côté éditorial / professionnel
- Facilite la mémorisation

**Comment :**
- Modal de choix : Upload local (jpg/png/webp, converti en webp) **ou** recherche Pixabay (banque libre de droits)
- Stockage public dans `/spocspace/assets/uploads/wiki/`

---

### 3.10 Import Word / PDF
**Quoi :** Bouton "Importer" qui convertit un .docx ou un .pdf en contenu wiki éditable.

**Pourquoi :**
- 95% des EMS ont déjà leurs procédures en Word ou PDF
- Sans import, la migration est bloquante (personne ne va re-saisir 200 pages)
- Permet une adoption rapide

**Implémentation :**
- `mammoth.js` pour les .docx (préserve titres, listes, gras)
- `pdf.js` pour les PDF (extraction texte, perd la mise en forme)

---

### 3.11 Éditeur TipTap
**Quoi :** Éditeur WYSIWYG basé sur TipTap v3 (ProseMirror).

**Pourquoi TipTap ?**
- **Open source**, gratuit, moderne
- Architecture extensible (extensions pour tableaux, images, liens, etc.)
- Sortie HTML propre (pas de balises bizarres comme certains éditeurs)
- Même rendu en édition et en lecture (mode `editable: false`)

**Toolbar disponible :**
- Bold, Italic, Underline, Strike, Highlight
- Titres H2/H3, listes, blockquote
- Alignement (gauche/centre/droite/justifié)
- Liens (avec modal personnalisé)
- Images (avec resize 25/50/75/100%)
- **Tableaux** (insertion, ajout/suppression de lignes/colonnes)
- Emoji picker
- Undo / Redo

**Sticky toolbar :** Reste visible quand on scroll (top: 56px).

---

## 4. Workflow de publication (Phase 3)

### 4.1 Les 3 statuts
| Statut | Visible par | Quand l'utiliser |
|---|---|---|
| **Brouillon** 🟡 | Auteur seulement | Travail en cours |
| **En review** 🟠 | Responsables | Soumis pour validation |
| **Publié** 🟢 | Tous (selon permissions) | Validé, en production |

### 4.2 Pourquoi ce workflow ?
- Évite la **publication directe** d'infos non validées
- Protocoles de soins : un brouillon peut contenir des erreurs dangereuses
- Permet la collaboration : un junior rédige, un senior valide
- Inspiré de Confluence "Draft → Review → Published"

### 4.3 Actions de review
- **Approuver** → passe en `publié` automatiquement
- **Demander des modifications** → repasse en `brouillon` avec un commentaire
- **Commenter** → reste en review, ajoute juste un commentaire au log

### 4.4 Historique des reviews
Table `wiki_page_reviews` :
- Toutes les décisions sont conservées (qui, quand, quoi, pourquoi)
- Affichage chronologique sous l'éditeur
- Audit complet du processus

### 4.5 Évolutions possibles
- **Reviewer désigné** : forcer une personne spécifique à valider (pas n'importe quel responsable)
- **Multi-review** : exiger N approbations avant publication
- **Notification** : email/push à l'auteur quand sa page est approuvée/rejetée
- **Diff visuel** : montrer en rouge/vert ce qui a changé entre 2 versions

---

## 5. Analytics (Phase 3)

### 5.1 Page Vues
**Quoi :** Chaque ouverture d'une page wiki est loggée dans `wiki_page_views (page_id, user_id, viewed_at)`.

**Pourquoi :**
- Mesurer ce qui est **réellement utilisé**
- Identifier les pages stars (fierté de l'équipe)
- Identifier les pages mortes (cf. cartes orphelines)

**KPIs affichés :**
- Total pages
- Vues sur la période (7/30/90 jours)
- Lecteurs uniques
- Vérifications expirées

### 5.2 Top 10 pages
Classement des pages par nombre de vues sur la période. Permet de :
- Mettre en avant les contenus qui marchent
- Comprendre les besoins de l'équipe
- Justifier l'effort éditorial

### 5.3 Cartes orphelines
**Définition exacte :**
- Page **active** (non archivée)
- **Pas modifiée** depuis 90+ jours
- **0 vue** sur la période sélectionnée

**Pourquoi ces critères ?**
- Une page sans màj récente **et** sans lecture est probablement obsolète ou inutile
- Si elle est juste sans màj mais lue → c'est un classique stable, on ne touche pas
- Si elle est juste sans lecture mais récente → on lui laisse sa chance

**Action recommandée :** Archiver, fusionner ailleurs, ou retravailler le SEO interne (tags, catégorie).

### 5.4 Évolutions possibles analytics
- **Sparkline** : courbe des vues jour par jour
- **Heatmap** : heure de la journée / jour de la semaine
- **Temps de lecture moyen** (nécessite un beacon JS)
- **Bounce rate** : % de gens qui ouvrent et ferment immédiatement
- **Export CSV** des analytics
- **Comparaison période A vs B**

---

## 6. Knowledge Gaps (Phase 3)

### 6.1 Qu'est-ce qu'un gap ?
Une recherche utilisateur qui **ne donne aucun résultat**. C'est la preuve qu'**il manque une page** dans la base.

### 6.2 Le mécanisme de log
À chaque recherche dans le wiki :
```js
adminApiPost('admin_log_wiki_search', { q: 'plaies de pression', results_count: 0 });
```

Stocké dans `wiki_search_log (id, user_id, q, results_count, created_at)`.

### 6.3 Affichage admin
Dans `/spocspace/admin/wiki-analytics` → section "Knowledge Gaps" :
- Liste triée par fréquence (la query la plus cherchée en premier)
- Badge avec le nombre de fois où elle a été tapée
- Bouton **"Créer"** qui ouvre l'éditeur wiki avec le titre pré-rempli

### 6.4 Pourquoi c'est précieux
- C'est une **liste de courses** prioritaire pour l'équipe éditoriale
- Mesurable : "On a comblé 80% des gaps en 3 mois"
- Aligne le contenu sur les **vrais besoins** de l'équipe (pas ce que la direction pense)

### 6.5 Évolutions possibles
- **Suggestions automatiques d'IA** : "Cette query revient 9× — voici un brouillon généré par Claude, à valider"
- **Notification** : alerter le responsable wiki quand une nouvelle query orpheline atteint X occurrences
- **Regrouper les variantes** : "plaie pression", "plaies de pression", "ulcère de pression" → 1 seule entrée
- **Détection de typos** : "désinfction" → suggérer "désinfection"

---

## 7. Annonces officielles

### 7.1 Différence avec le mur social
| | Mur social | Annonces officielles |
|---|---|---|
| **Sens** | Bottom-up (collaborateurs entre eux) | Top-down (direction → équipe) |
| **Likes / commentaires** | Oui | **Non** (lecture seule) |
| **Activable / désactivable** | Oui (toggle EMS) | Oui (toggle EMS) |
| **Catégorisation** | Libre | Stricte (7 catégories EMS) |
| **Image cover** | Optionnelle | Recommandée |
| **Accusé de lecture** | Non | Oui (Phase 3) |

### 7.2 Catégories
- **Direction** (building) — communications de la direction
- **RH** (person-badge) — recrutement, formation, paie
- **Vie sociale** (balloon-heart) — événements, animations
- **Cuisine** (egg-fried) — menus, allergènes
- **Protocoles** (heart-pulse) — nouveaux protocoles soins
- **Sécurité** (shield-check) — exercices feu, alarmes
- **Divers** (info-circle) — fourre-tout

### 7.3 Pourquoi pas de likes/commentaires ?
**Décision design** : une annonce est un **acte de communication formel**. Permettre les réactions :
- Polluerait le canal (smileys sur "Décès de Mme X")
- Donnerait l'illusion d'un dialogue alors que c'est descendant
- Le mur social existe pour ça

### 7.4 Épinglage
Une annonce épinglée reste en haut de la liste. Utile pour les infos urgentes ou stables (numéros d'urgence, planning du week-end).

---

## 8. Accusés de lecture (Phase 3)

### 8.1 Le problème
> *"Tu as lu le nouveau protocole sur les chutes ?"* — *"Heu... lequel ?"*

Sans accusé de lecture, **impossible de prouver** qu'une consigne critique a été reçue.

### 8.2 Solution
Sur chaque annonce, un toggle "Exiger un accusé de lecture" + un sélecteur de cible (rôle).

**Côté lecteur :**
- Bandeau jaune en haut de l'annonce : "Cette annonce nécessite votre accusé de lecture"
- Bouton vert "J'ai lu et compris"
- Une fois cliqué : bandeau devient vert "Vous avez confirmé la lecture"

**Côté responsable :**
- Dans l'éditeur d'annonce, dashboard intégré :
  - Barre de progression % de lecture
  - Liste verte des collaborateurs ayant lu
  - Liste grise de ceux qui n'ont pas encore lu
- Permet de **relancer** ceux qui n'ont pas lu

### 8.3 Inspiration
- **SharePoint** : "Read receipt"
- **Workplace Meta** : "Important post"
- **Réglementation santé** : traçabilité des consignes

### 8.4 Évolutions possibles
- **Rappel automatique** par email/notif si pas d'ack après N jours
- **Quizz obligatoire** : pour les protocoles très critiques, exiger 3 questions correctes avant de pouvoir cocher "Lu"
- **Signature numérique** (avec date + IP)
- **Export PDF** de la liste des lecteurs (preuve légale)
- **Limites temporelles** : "Cette annonce expire dans 30 jours"

---

## 9. Recherche globale (transversale)

### 9.1 Périmètre
Une seule barre en haut de chaque app cherche dans **tout** :
- Wiki (avec filtre permissions)
- Annonces
- Résidents
- Documents
- Collaborateurs

### 9.2 Trois implémentations parallèles
| App | Endpoint | Historique localStorage |
|---|---|---|
| **SpocSpace SPA** (employé) | `global_search` | `spocspace:search-history` |
| **SpocSpace Admin** | `admin_global_search` | `spocadmin:search-history` |
| **SpocCare** | `admin_care_global_search` | `spoccare:search-history` |

### 9.3 Historique avec TTL
- Les recherches sont conservées 2 jours en localStorage
- Maximum 30 entrées
- Affichage : 3 récentes + 4 plus anciennes
- Bouton X pour supprimer une entrée
- Icône horloge pour les récentes, loupe pour les plus anciennes

### 9.4 Click sur un résultat
Le click ouvre directement l'item :
- Wiki → page wiki avec contenu chargé
- Annonce → annonce ouverte
- Résident → fiche famille
- Etc.

(L'id est passé via `params.id` ou `AdminURL.currentId()`)

---

## 10. Ce qui manque encore (roadmap)

### 10.1 Notifications push
Aujourd'hui : aucune notif quand une nouvelle annonce / page wiki est créée.

**À ajouter :**
- Notification web push (Service Worker)
- Email digest hebdomadaire des nouveautés
- Badge "Nouveau" sur les annonces non lues

### 10.2 Templates de pages
Permettre de créer une page à partir d'un template (Procédure, Protocole, FAQ, Note interne...).

### 10.3 Mentions @utilisateur
Dans le contenu, pouvoir taper `@Marie` pour notifier une personne.

### 10.4 Liens internes wiki ↔ wiki
Comme Notion : un lien `[[Protocole hygiène]]` qui crée un lien automatique vers la page.

### 10.5 Export PDF de pages
Bouton "Exporter en PDF" pour avoir une copie papier (utile en chambre).

### 10.6 Application mobile
PWA installable avec :
- Mode hors-ligne (cache des pages favorites)
- Push notifications
- Scan QR code → ouvre directement une page (pour mettre des QR codes en chambre vers le protocole correspondant)

### 10.7 Multi-langue
Une page peut avoir des traductions (FR / EN / PT / ES) pour les équipes internationales.

### 10.8 Statistiques personnelles
"Vous avez lu 12 pages cette semaine, 3 de plus que la moyenne" (gamification douce).

### 10.9 IA générative complète
- Génération de brouillons à partir d'un titre
- Résumé automatique de longues pages
- Q&A : "Demande à l'IA" qui répond en citant les pages wiki sources
- Détection automatique de doublons et de contradictions

### 10.10 Workflow plus avancé
- Reviewer dédié (pas n'importe quel responsable)
- Approbation multi-niveaux (junior → senior → direction)
- Date de publication différée

---

## 11. Choix techniques expliqués

### 11.1 Pourquoi pas une SaaS comme Notion / Guru ?
| | SaaS | SpocSpace |
|---|---|---|
| Coût mensuel | 8-15 €/user | 0 € marginal |
| Données | Aux USA | En Suisse, sur votre serveur |
| RGPD santé | Compliqué (DPA, sous-traitance) | Direct |
| Personnalisation | Limitée | Totale |
| Lien avec planning, RH, résidents | Aucun | Natif |

### 11.2 Pourquoi MySQL et pas un moteur de recherche dédié (Elasticsearch) ?
- Volume attendu : quelques milliers de pages → MySQL FULLTEXT suffit largement
- Pas de serveur supplémentaire à maintenir
- Pas de coût opérationnel
- À très grosse échelle (>100 000 pages), passage à Meilisearch envisageable

### 11.3 Pourquoi vanilla JS et pas React/Vue ?
- Pas de build step → édition en direct
- Pas de surface d'attaque npm
- Démarrage instantané (pas de bundle 500 KB à parser)
- Le style "framework agnostic" facilite la maintenance long terme
- L'équipe SpocSpace maîtrise vanilla JS

### 11.4 Pourquoi TipTap et pas CKEditor / TinyMCE ?
- Open source MIT (gratuit même en commercial)
- Architecture moderne (ProseMirror)
- Extensions à la carte (on ne charge que ce dont on a besoin)
- Excellente prise en charge des tableaux
- Sortie HTML clean
- Communauté active

---

## 12. Glossaire

| Terme | Définition |
|---|---|
| **Page** | Un article wiki avec titre, contenu HTML, métadonnées |
| **Catégorie** | Groupe thématique d'une page (1 par page) |
| **Tag** | Étiquette transversale (N par page) |
| **Expert** | Utilisateur responsable de la véracité d'une page |
| **Verification** | Acte de re-valider qu'une page est encore exacte |
| **Carte orpheline** | Page non lue + non modifiée depuis 90+ jours |
| **Knowledge gap** | Recherche sans résultat → page manquante |
| **Brouillon / Review / Publié** | Les 3 états du workflow de publication |
| **Accusé de lecture** | Confirmation explicite qu'un user a lu une annonce |
| **FULLTEXT** | Index MySQL pour recherche textuelle rapide |

---

## 13. Index des fichiers source

### Backend
- `admin/api_modules/wiki.php` — toutes les actions wiki côté admin/care
- `admin/api_modules/annonces.php` — toutes les actions annonces
- `api_modules/wiki.php` — actions employé (SPA)
- `admin/api_modules/global_search.php` — recherche globale admin
- `admin/api_modules/care_search.php` — recherche globale care
- `api_modules/search.php` — recherche globale SPA

### Frontend (care)
- `care/pages/wiki.php` — liste + lecture
- `care/pages/wiki-edit.php` — éditeur
- `care/pages/annonces.php` — liste + lecture
- `care/pages/annonce-edit.php` — éditeur

### Frontend (SPA employé)
- `pages/wiki.php` — vue lecture employé
- `assets/js/modules/wiki.js` — module SPA

### Admin
- `admin/pages/wiki.php` — wrapper d'inclusion
- `admin/pages/annonces.php` — wrapper d'inclusion
- `admin/pages/wiki-analytics.php` — dashboard analytics

### Migrations
- `migrations/056_wiki.sql` — tables initiales
- `migrations/057_annonces.sql` — annonces + toggle features
- `migrations/058_wiki_phase1.sql` — tags, favoris, expert, FULLTEXT
- `migrations/059_wiki_phase2.sql` — permissions, AI suggestions
- `migrations/060_wiki_phase3.sql` — analytics, search log, ack, workflow

---

*Document généré pour SpocSpace — Module Wiki & Annonces*
*Phase 1 : Tags / Favoris / Expert / FULLTEXT*
*Phase 2 : Permissions par rôle / Suggestions IA*
*Phase 3 : Analytics / Knowledge gaps / Accusés de lecture / Workflow brouillon → review → publié*
