<?php

function admin_get_hygiene_produits() {
    $rows = Db::fetchAll("SELECT * FROM hygiene_produits ORDER BY ordre, nom");
    respond(['success' => true, 'produits' => $rows]);
}

function admin_save_hygiene_produit() {
    global $params;
    $id = $params['id'] ?? '';
    $nom = trim($params['nom'] ?? '');
    if (!$nom) bad_request('Nom requis');
    $allowed = ['savon','rasoir','parfum','gel_douche','apres_rasage','dentifrice','shampooing','creme','deodorant','autre'];
    $cat = in_array($params['categorie'] ?? '', $allowed) ? $params['categorie'] : 'autre';

    if ($id) {
        Db::exec("UPDATE hygiene_produits SET nom=?,categorie=?,marque=?,couleur=?,is_active=? WHERE id=?",
            [$nom, $cat, $params['marque']??null, $params['couleur']??'#3B4F6B', (int)($params['is_active']??1), $id]);
    } else {
        $id = Uuid::v4();
        Db::exec("INSERT INTO hygiene_produits (id,nom,categorie,marque,couleur) VALUES (?,?,?,?,?)",
            [$id, $nom, $cat, $params['marque']??null, $params['couleur']??'#3B4F6B']);
    }
    respond(['success' => true, 'id' => $id, 'message' => 'Produit enregistré']);
}

function admin_delete_hygiene_produit() {
    global $params;
    Db::exec("DELETE FROM hygiene_produits WHERE id = ?", [$params['id'] ?? '']);
    respond(['success' => true, 'message' => 'Supprimé']);
}

function admin_get_hygiene_commandes() {
    global $params;
    $jour = $params['jour'] ?? date('Y-m-d');
    $statut = $params['statut'] ?? '';

    $where = "c.jour = ?";
    $binds = [$jour];
    if ($statut) { $where .= " AND c.statut = ?"; $binds[] = $statut; }

    $rows = Db::fetchAll(
        "SELECT c.*, p.nom AS produit_nom, p.categorie, p.marque, p.couleur,
                r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre, r.etage,
                u.prenom AS cmd_prenom, u.nom AS cmd_nom,
                pu.prenom AS prep_prenom, pu.nom AS prep_nom,
                du.prenom AS dist_prenom, du.nom AS dist_nom
         FROM hygiene_commandes c
         JOIN hygiene_produits p ON p.id = c.produit_id
         JOIN residents r ON r.id = c.resident_id
         JOIN users u ON u.id = c.commandeur_id
         LEFT JOIN users pu ON pu.id = c.prepared_by
         LEFT JOIN users du ON du.id = c.delivered_by
         WHERE $where
         ORDER BY r.chambre, r.nom, p.nom",
        $binds
    );

    $stats = Db::fetch(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(c.statut='commandé'),0) AS commandes,
                COALESCE(SUM(c.statut='préparé'),0) AS prepares,
                COALESCE(SUM(c.statut='distribué'),0) AS distribues,
                COUNT(DISTINCT c.resident_id) AS residents
         FROM hygiene_commandes c WHERE c.jour = ?",
        [$jour]
    );

    respond(['success' => true, 'commandes' => $rows, 'stats' => $stats ?: []]);
}

function admin_create_hygiene_commande() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    $produitId = $params['produit_id'] ?? '';
    if (!$residentId || !$produitId) bad_request('Résident et produit requis');

    $userId = $_SESSION['zt_user']['id'] ?? '';
    $jour = $params['jour'] ?? date('Y-m-d');

    Db::exec(
        "INSERT INTO hygiene_commandes (id,resident_id,produit_id,quantite,urgence,notes,commandeur_id,jour) VALUES (?,?,?,?,?,?,?,?)",
        [Uuid::v4(), $residentId, $produitId, max(1,(int)($params['quantite']??1)), (int)($params['urgence']??0), $params['notes']??null, $userId, $jour]
    );
    respond(['success' => true, 'message' => 'Commande créée']);
}

function admin_prepare_hygiene_commandes() {
    global $params;
    $ids = $params['ids'] ?? [];
    $userId = $_SESSION['zt_user']['id'] ?? '';
    if (!$ids || !is_array($ids)) bad_request('IDs requis');

    $ph = implode(',', array_fill(0, count($ids), '?'));
    Db::exec("UPDATE hygiene_commandes SET statut='préparé', prepared_by=?, prepared_at=NOW() WHERE id IN ($ph) AND statut='commandé'",
        array_merge([$userId], $ids));
    respond(['success' => true, 'message' => 'Commandes préparées']);
}

function admin_deliver_hygiene_commandes() {
    global $params;
    $ids = $params['ids'] ?? [];
    $userId = $_SESSION['zt_user']['id'] ?? '';
    if (!$ids || !is_array($ids)) bad_request('IDs requis');

    $ph = implode(',', array_fill(0, count($ids), '?'));
    Db::exec("UPDATE hygiene_commandes SET statut='distribué', delivered_by=?, delivered_at=NOW() WHERE id IN ($ph) AND statut='préparé'",
        array_merge([$userId], $ids));
    respond(['success' => true, 'message' => 'Distributions confirmées']);
}

function admin_get_hygiene_historique() {
    global $params;
    $from = $params['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $to = $params['to'] ?? date('Y-m-d');
    $search = $params['search'] ?? '';

    $where = "c.jour BETWEEN ? AND ?";
    $binds = [$from, $to];
    if ($search) {
        $where .= " AND (r.nom LIKE ? OR r.prenom LIKE ? OR p.nom LIKE ? OR r.chambre LIKE ?)";
        $s = '%' . $search . '%';
        $binds = array_merge($binds, [$s, $s, $s, $s]);
    }

    $rows = Db::fetchAll(
        "SELECT c.*, p.nom AS produit_nom, p.categorie, p.couleur,
                r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre, r.etage,
                u.prenom AS cmd_prenom, u.nom AS cmd_nom,
                pu.prenom AS prep_prenom, pu.nom AS prep_nom,
                du.prenom AS dist_prenom, du.nom AS dist_nom
         FROM hygiene_commandes c
         JOIN hygiene_produits p ON p.id = c.produit_id
         JOIN residents r ON r.id = c.resident_id
         JOIN users u ON u.id = c.commandeur_id
         LEFT JOIN users pu ON pu.id = c.prepared_by
         LEFT JOIN users du ON du.id = c.delivered_by
         WHERE $where
         ORDER BY c.jour DESC, c.created_at DESC
         LIMIT 500",
        $binds
    );
    respond(['success' => true, 'historique' => $rows]);
}

function admin_delete_hygiene_commande() {
    global $params;
    Db::exec("DELETE FROM hygiene_commandes WHERE id = ?", [$params['id'] ?? '']);
    respond(['success' => true, 'message' => 'Supprimé']);
}
