<?php
/**
 * Annuaire — Employee API (read-only)
 */

function get_annuaire()
{
    require_auth();
    global $params;
    $type = $params['type'] ?? null;

    $sql = "SELECT id, type, categorie, nom, prenom, fonction, service,
                   telephone_1, telephone_2, email, adresse, notes, is_favori, ordre
            FROM annuaire WHERE is_active = 1";
    $args = [];
    if ($type && in_array($type, ['interne', 'externe', 'urgence'])) {
        $sql .= " AND type = ?";
        $args[] = $type;
    }
    $sql .= " ORDER BY is_favori DESC, ordre ASC, nom ASC, prenom ASC";

    $rows = Db::fetchAll($sql, $args);
    respond(['success' => true, 'data' => $rows]);
}

function search_annuaire()
{
    require_auth();
    global $params;
    $q = trim($params['q'] ?? '');
    if (strlen($q) < 2) {
        respond(['success' => true, 'data' => []]);
    }
    $like = '%' . $q . '%';
    $rows = Db::fetchAll(
        "SELECT id, type, categorie, nom, prenom, fonction, service,
                telephone_1, telephone_2, email, is_favori
         FROM annuaire WHERE is_active = 1
         AND (nom LIKE ? OR prenom LIKE ? OR fonction LIKE ? OR service LIKE ?
              OR telephone_1 LIKE ? OR telephone_2 LIKE ? OR email LIKE ? OR categorie LIKE ?)
         ORDER BY is_favori DESC, nom ASC LIMIT 30",
        [$like, $like, $like, $like, $like, $like, $like, $like]
    );
    respond(['success' => true, 'data' => $rows]);
}
