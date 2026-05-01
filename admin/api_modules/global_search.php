<?php
/**
 * Recherche globale — API admin
 * Cherche dans : utilisateurs, wiki, annonces, résidents, documents
 */

function admin_global_search()
{
    require_auth();
    global $params;

    $q = Sanitize::text($params['q'] ?? '', 200);
    if (mb_strlen($q) < 2) respond(['success' => true, 'results' => []]);

    $like = "%$q%";
    $results = [];

    // ── Utilisateurs ──────────────────────────────────
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, u.photo, f.nom AS fonction_nom FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND (CONCAT(u.prenom, ' ', u.nom) LIKE ? OR u.email LIKE ?)
         ORDER BY u.nom LIMIT 5",
        [$like, $like]
    );
    foreach ($users as $u) {
        $results[] = [
            'type' => 'user', 'icon' => 'person', 'id' => $u['id'],
            'title' => $u['prenom'] . ' ' . $u['nom'], 'subtitle' => $u['fonction_nom'] ?: $u['email'],
            'page' => 'user-detail', 'page_id' => $u['id'],
            'photo' => $u['photo'],
            'prenom' => $u['prenom'],
            'nom' => $u['nom'],
        ];
    }

    // ── Résidents ─────────────────────────────────────
    $residents = Db::fetchAll(
        "SELECT id, nom, prenom, chambre, etage, photo_path FROM residents
         WHERE is_active = 1 AND (nom LIKE ? OR prenom LIKE ? OR chambre LIKE ?)
         ORDER BY nom LIMIT 5",
        [$like, $like, $like]
    );
    foreach ($residents as $r) {
        $results[] = [
            'type' => 'resident', 'icon' => 'person-badge', 'id' => $r['id'],
            'title' => $r['prenom'] . ' ' . $r['nom'],
            'subtitle' => $r['chambre'] ? 'Ch. ' . $r['chambre'] : '',
            'page' => 'famille', 'page_id' => $r['id'],
            'photo' => $r['photo_path'],
            'prenom' => $r['prenom'],
            'nom' => $r['nom'],
        ];
    }

    // ── Wiki ──────────────────────────────────────────
    $wiki = Db::fetchAll(
        "SELECT p.id, p.titre, c.nom AS cat_nom, c.icone AS cat_icone FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         WHERE p.archived_at IS NULL AND p.visible = 1 AND (p.titre LIKE ? OR p.description LIKE ?)
         ORDER BY p.epingle DESC LIMIT 5",
        [$like, $like]
    );
    foreach ($wiki as $w) {
        $results[] = [
            'type' => 'wiki', 'icon' => $w['cat_icone'] ?: 'book', 'id' => $w['id'],
            'title' => $w['titre'], 'subtitle' => $w['cat_nom'] ?: 'Wiki',
            'page' => 'wiki', 'page_id' => $w['id'],
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
            'page' => 'annonces', 'page_id' => $a['id'],
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
            'page' => 'documents', 'page_id' => $d['id'],
        ];
    }

    respond(['success' => true, 'results' => $results, 'query' => $q]);
}
