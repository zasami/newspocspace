<?php
// ═══ PRODUITS ═══════════════════════════════════════════════════════════════

function admin_get_protection_produits() {
    $rows = Db::fetchAll("SELECT * FROM protection_produits ORDER BY ordre, nom");
    respond(['success' => true, 'produits' => $rows]);
}

function admin_save_protection_produit() {
    global $params;
    $id = $params['id'] ?? '';
    $nom = trim($params['nom'] ?? '');
    if (!$nom) bad_request('Nom requis');

    if ($id) {
        Db::exec("UPDATE protection_produits SET nom=?, taille=?, marque=?, reference=?, couleur=?, is_active=? WHERE id=?",
            [$nom, $params['taille']??null, $params['marque']??null, $params['reference']??null, $params['couleur']??'#2d4a43', (int)($params['is_active']??1), $id]);
    } else {
        $id = Uuid::v4();
        Db::exec("INSERT INTO protection_produits (id,nom,taille,marque,reference,couleur) VALUES (?,?,?,?,?,?)",
            [$id, $nom, $params['taille']??null, $params['marque']??null, $params['reference']??null, $params['couleur']??'#2d4a43']);
    }
    respond(['success' => true, 'id' => $id, 'message' => 'Produit enregistré']);
}

function admin_delete_protection_produit() {
    global $params;
    Db::exec("DELETE FROM protection_produits WHERE id = ?", [$params['id'] ?? '']);
    respond(['success' => true, 'message' => 'Produit supprimé']);
}

// ═══ ATTRIBUTIONS ═══════════════════════════════════════════════════════════

function admin_get_protection_attributions() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    $where = "1=1"; $binds = [];
    if ($residentId) { $where .= " AND a.resident_id = ?"; $binds[] = $residentId; }

    $rows = Db::fetchAll(
        "SELECT a.*, p.nom AS produit_nom, p.taille, p.marque, p.couleur,
                r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre
         FROM protection_attributions a
         JOIN protection_produits p ON p.id = a.produit_id
         JOIN residents r ON r.id = a.resident_id
         WHERE $where
         ORDER BY r.nom, r.prenom, p.nom",
        $binds
    );
    respond(['success' => true, 'attributions' => $rows]);
}

function admin_save_protection_attribution() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    $produitId = $params['produit_id'] ?? '';
    $qte = max(0, (int)($params['quantite_hebdo'] ?? 0));
    if (!$residentId || !$produitId) bad_request('Résident et produit requis');

    $existing = Db::fetch("SELECT id FROM protection_attributions WHERE resident_id=? AND produit_id=?", [$residentId, $produitId]);
    if ($existing) {
        Db::exec("UPDATE protection_attributions SET quantite_hebdo=?, notes=? WHERE id=?",
            [$qte, $params['notes']??null, $existing['id']]);
    } else {
        Db::exec("INSERT INTO protection_attributions (id,resident_id,produit_id,quantite_hebdo,notes) VALUES (?,?,?,?,?)",
            [Uuid::v4(), $residentId, $produitId, $qte, $params['notes']??null]);
    }
    respond(['success' => true, 'message' => 'Attribution enregistrée']);
}

function admin_delete_protection_attribution() {
    global $params;
    Db::exec("DELETE FROM protection_attributions WHERE id = ?", [$params['id'] ?? '']);
    respond(['success' => true, 'message' => 'Attribution supprimée']);
}

// ═══ COMPTAGES ══════════════════════════════════════════════════════════════

function admin_get_protection_comptages() {
    global $params;
    $semaine = $params['semaine'] ?? date('Y-m-d', strtotime('monday this week'));
    $statut = $params['statut'] ?? '';

    $where = "c.semaine = ?";
    $binds = [$semaine];
    if ($statut) { $where .= " AND c.statut = ?"; $binds[] = $statut; }

    $rows = Db::fetchAll(
        "SELECT c.*, p.nom AS produit_nom, p.taille, p.marque, p.couleur,
                r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre,
                a.quantite_hebdo,
                u.prenom AS compteur_prenom, u.nom AS compteur_nom,
                vu.prenom AS valideur_prenom, vu.nom AS valideur_nom,
                du.prenom AS livreur_prenom, du.nom AS livreur_nom
         FROM protection_comptages c
         JOIN protection_produits p ON p.id = c.produit_id
         JOIN residents r ON r.id = c.resident_id
         LEFT JOIN protection_attributions a ON a.resident_id = c.resident_id AND a.produit_id = c.produit_id
         JOIN users u ON u.id = c.compteur_id
         LEFT JOIN users vu ON vu.id = c.validated_by
         LEFT JOIN users du ON du.id = c.delivered_by
         WHERE $where
         ORDER BY r.chambre, r.nom, p.nom",
        $binds
    );

    // Stats
    $stats = Db::fetch(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(c.statut='compté'),0) AS comptes,
                COALESCE(SUM(c.statut='validé'),0) AS valides,
                COALESCE(SUM(c.statut='livré'),0) AS livres,
                COUNT(DISTINCT c.resident_id) AS residents
         FROM protection_comptages c WHERE c.semaine = ?",
        [$semaine]
    );

    respond(['success' => true, 'comptages' => $rows, 'stats' => $stats ?: []]);
}

function admin_save_protection_comptage() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    $produitId = $params['produit_id'] ?? '';
    $qteRestante = max(0, (int)($params['quantite_restante'] ?? 0));
    $semaine = $params['semaine'] ?? date('Y-m-d', strtotime('monday this week'));
    $userId = $_SESSION['zt_user']['id'] ?? '';

    if (!$residentId || !$produitId) bad_request('Résident et produit requis');

    // Calc quantity to deliver
    $attrib = Db::fetch("SELECT quantite_hebdo FROM protection_attributions WHERE resident_id=? AND produit_id=?", [$residentId, $produitId]);
    $hebdo = $attrib ? (int)$attrib['quantite_hebdo'] : 0;
    $aLivrer = max(0, $hebdo - $qteRestante);

    $existing = Db::fetch("SELECT id FROM protection_comptages WHERE resident_id=? AND produit_id=? AND semaine=?", [$residentId, $produitId, $semaine]);
    if ($existing) {
        Db::exec("UPDATE protection_comptages SET quantite_restante=?, quantite_a_livrer=?, notes=? WHERE id=?",
            [$qteRestante, $aLivrer, $params['notes']??null, $existing['id']]);
    } else {
        Db::exec("INSERT INTO protection_comptages (id,resident_id,produit_id,quantite_restante,quantite_a_livrer,compteur_id,semaine,notes) VALUES (?,?,?,?,?,?,?,?)",
            [Uuid::v4(), $residentId, $produitId, $qteRestante, $aLivrer, $userId, $semaine, $params['notes']??null]);
    }
    respond(['success' => true, 'message' => 'Comptage enregistré', 'quantite_a_livrer' => $aLivrer]);
}

function admin_validate_protection_comptages() {
    global $params;
    $semaine = $params['semaine'] ?? '';
    $ids = $params['ids'] ?? [];
    $userId = $_SESSION['zt_user']['id'] ?? '';

    if ($ids && is_array($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Db::exec("UPDATE protection_comptages SET statut='validé', validated_by=?, validated_at=NOW() WHERE id IN ($placeholders) AND statut='compté'",
            array_merge([$userId], $ids));
    } elseif ($semaine) {
        Db::exec("UPDATE protection_comptages SET statut='validé', validated_by=?, validated_at=NOW() WHERE semaine=? AND statut='compté'",
            [$userId, $semaine]);
    }
    respond(['success' => true, 'message' => 'Comptages validés']);
}

function admin_deliver_protection_comptages() {
    global $params;
    $semaine = $params['semaine'] ?? '';
    $ids = $params['ids'] ?? [];
    $userId = $_SESSION['zt_user']['id'] ?? '';

    if ($ids && is_array($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Db::exec("UPDATE protection_comptages SET statut='livré', delivered_by=?, delivered_at=NOW() WHERE id IN ($placeholders) AND statut='validé'",
            array_merge([$userId], $ids));
    } elseif ($semaine) {
        Db::exec("UPDATE protection_comptages SET statut='livré', delivered_by=?, delivered_at=NOW() WHERE semaine=? AND statut='validé'",
            [$userId, $semaine]);
    }
    respond(['success' => true, 'message' => 'Livraisons confirmées']);
}

function admin_get_protection_dashboard() {
    global $params;
    $semaine = $params['semaine'] ?? date('Y-m-d', strtotime('monday this week'));
    $jourComptage = Db::getOne("SELECT config_value FROM ems_config WHERE config_key='protection_jour_comptage'") ?: 'mardi';

    // Residents with attributions but no counting this week
    $manquants = Db::fetchAll(
        "SELECT DISTINCT r.id, r.nom, r.prenom, r.chambre
         FROM protection_attributions a
         JOIN residents r ON r.id = a.resident_id AND r.is_active = 1
         WHERE NOT EXISTS (SELECT 1 FROM protection_comptages c WHERE c.resident_id = a.resident_id AND c.semaine = ?)",
        [$semaine]
    );

    // Summary per resident
    $parResident = Db::fetchAll(
        "SELECT r.id, r.nom, r.prenom, r.chambre,
                COUNT(DISTINCT a.produit_id) AS nb_produits,
                SUM(a.quantite_hebdo) AS total_hebdo,
                (SELECT COUNT(*) FROM protection_comptages c WHERE c.resident_id = r.id AND c.semaine = ?) AS nb_comptes
         FROM protection_attributions a
         JOIN residents r ON r.id = a.resident_id AND r.is_active = 1
         GROUP BY r.id ORDER BY r.chambre, r.nom",
        [$semaine]
    );

    respond([
        'success' => true,
        'jour_comptage' => $jourComptage,
        'semaine' => $semaine,
        'manquants' => $manquants,
        'par_resident' => $parResident,
    ]);
}
