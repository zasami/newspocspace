<?php
/**
 * Recherche globale — API employé
 * Cherche dans : pages SPA, collègues, wiki, annonces, documents
 */

function global_search()
{
    $user = require_auth();
    global $params;

    $q = Sanitize::text($params['q'] ?? '', 200);
    if (mb_strlen($q) < 2) respond(['success' => true, 'results' => []]);

    $like = "%$q%";
    $userRole = $user['role'] ?? 'collaborateur';
    $results = [];

    // ── Collègues ─────────────────────────────────────
    $users = Db::fetchAll(
        "SELECT id, prenom, nom, photo FROM users
         WHERE is_active = 1 AND (CONCAT(prenom, ' ', nom) LIKE ? OR nom LIKE ? OR prenom LIKE ?)
         ORDER BY nom, prenom LIMIT 5",
        [$like, $like, $like]
    );
    foreach ($users as $u) {
        $results[] = [
            'type' => 'collegue', 'icon' => 'person', 'id' => $u['id'],
            'title' => $u['prenom'] . ' ' . $u['nom'], 'subtitle' => '',
            'page' => 'collegues',
        ];
    }

    // ── Wiki ──────────────────────────────────────────
    $wiki = Db::fetchAll(
        "SELECT p.id, p.titre, c.nom AS cat_nom, c.icone AS cat_icone
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         WHERE p.archived_at IS NULL AND p.visible = 1
           AND (p.titre LIKE ? OR p.description LIKE ?)
           AND (NOT EXISTS (SELECT 1 FROM wiki_page_permissions wpp WHERE wpp.page_id = p.id)
                OR EXISTS (SELECT 1 FROM wiki_page_permissions wpp2 WHERE wpp2.page_id = p.id AND wpp2.role = ?))
         ORDER BY p.epingle DESC LIMIT 5",
        [$like, $like, $userRole]
    );
    foreach ($wiki as $w) {
        $results[] = [
            'type' => 'wiki', 'icon' => $w['cat_icone'] ?: 'book', 'id' => $w['id'],
            'title' => $w['titre'], 'subtitle' => $w['cat_nom'] ?: 'Wiki',
            'page' => 'wiki',
        ];
    }

    // ── Annonces ──────────────────────────────────────
    $ann = Db::fetchAll(
        "SELECT id, titre, categorie FROM annonces
         WHERE archived_at IS NULL AND visible = 1 AND (titre LIKE ? OR description LIKE ?)
         ORDER BY epingle DESC LIMIT 5",
        [$like, $like]
    );
    $catIcons = ['direction'=>'building','rh'=>'person-badge','vie_sociale'=>'balloon-heart','cuisine'=>'egg-fried','protocoles'=>'heart-pulse','securite'=>'shield-check','divers'=>'info-circle'];
    foreach ($ann as $a) {
        $results[] = [
            'type' => 'annonce', 'icon' => $catIcons[$a['categorie']] ?? 'megaphone', 'id' => $a['id'],
            'title' => $a['titre'], 'subtitle' => ucfirst(str_replace('_', ' ', $a['categorie'])),
            'page' => 'annonces',
        ];
    }

    // ── Documents ─────────────────────────────────────
    $docs = Db::fetchAll(
        "SELECT d.id, d.titre, s.nom AS service_nom FROM documents d
         LEFT JOIN document_services s ON s.id = d.service_id
         WHERE d.archived_at IS NULL AND d.visible = 1 AND (d.titre LIKE ? OR d.original_name LIKE ?)
         ORDER BY d.created_at DESC LIMIT 5",
        [$like, $like]
    );
    foreach ($docs as $d) {
        $results[] = [
            'type' => 'document', 'icon' => 'file-earmark-text', 'id' => $d['id'],
            'title' => $d['titre'], 'subtitle' => $d['service_nom'] ?: 'Document',
            'page' => 'documents',
        ];
    }

    respond(['success' => true, 'results' => $results, 'query' => $q]);
}
