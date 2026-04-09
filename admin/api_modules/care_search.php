<?php
/**
 * Recherche globale SpocCare
 * Cherche dans : résidents, wiki, annonces
 */

function admin_care_global_search()
{
    require_auth();
    global $params;

    $q = Sanitize::text($params['q'] ?? '', 200);
    if (mb_strlen($q) < 2) {
        respond(['success' => true, 'results' => []]);
    }

    $like = "%$q%";
    $results = [];

    // ── Résidents ─────────────────────────────────────
    $residents = Db::fetchAll(
        "SELECT id, nom, prenom, chambre, etage
         FROM residents
         WHERE is_active = 1
           AND (nom LIKE ? OR prenom LIKE ? OR chambre LIKE ? OR CONCAT(prenom, ' ', nom) LIKE ?)
         ORDER BY nom, prenom
         LIMIT 5",
        [$like, $like, $like, $like]
    );
    foreach ($residents as $r) {
        $results[] = [
            'type' => 'resident',
            'icon' => 'person-badge',
            'id' => $r['id'],
            'title' => $r['prenom'] . ' ' . $r['nom'],
            'subtitle' => $r['chambre'] ? 'Ch. ' . $r['chambre'] . ($r['etage'] ? ' — Ét. ' . $r['etage'] : '') : '',
            'url' => 'famille/' . $r['id'],
        ];
    }

    // ── Wiki ──────────────────────────────────────────
    $wiki = Db::fetchAll(
        "SELECT p.id, p.titre, p.description, c.nom AS cat_nom, c.icone AS cat_icone
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         WHERE p.archived_at IS NULL AND p.visible = 1
           AND (p.titre LIKE ? OR p.description LIKE ? OR p.contenu LIKE ?)
         ORDER BY p.epingle DESC, p.updated_at DESC
         LIMIT 5",
        [$like, $like, $like]
    );
    foreach ($wiki as $w) {
        $results[] = [
            'type' => 'wiki',
            'icon' => $w['cat_icone'] ?: 'book',
            'id' => $w['id'],
            'title' => $w['titre'],
            'subtitle' => $w['cat_nom'] ?: 'Wiki',
            'url' => 'wiki',
        ];
    }

    // ── Annonces ──────────────────────────────────────
    $annonces = Db::fetchAll(
        "SELECT id, titre, description, categorie
         FROM annonces
         WHERE archived_at IS NULL AND visible = 1
           AND (titre LIKE ? OR description LIKE ? OR contenu LIKE ?)
         ORDER BY epingle DESC, published_at DESC
         LIMIT 5",
        [$like, $like, $like]
    );
    $catIcons = [
        'direction' => 'building', 'rh' => 'person-badge', 'vie_sociale' => 'balloon-heart',
        'cuisine' => 'egg-fried', 'protocoles' => 'heart-pulse', 'securite' => 'shield-check', 'divers' => 'info-circle',
    ];
    foreach ($annonces as $a) {
        $results[] = [
            'type' => 'annonce',
            'icon' => $catIcons[$a['categorie']] ?? 'megaphone',
            'id' => $a['id'],
            'title' => $a['titre'],
            'subtitle' => ucfirst(str_replace('_', ' ', $a['categorie'])),
            'url' => 'annonces',
        ];
    }

    respond(['success' => true, 'results' => $results, 'query' => $q]);
}
