<?php

function admin_get_todos()
{
    global $params;
    require_responsable();

    $where = ['1=1'];
    $bind  = [];

    $statut = $params['statut'] ?? '';
    if ($statut) { $where[] = 't.statut = ?'; $bind[] = $statut; }

    $priorite = $params['priorite'] ?? '';
    if ($priorite) { $where[] = 't.priorite = ?'; $bind[] = $priorite; }

    $assigned = $params['assigned_to'] ?? '';
    if ($assigned) { $where[] = 't.assigned_to = ?'; $bind[] = $assigned; }

    $search = trim($params['search'] ?? '');
    if ($search) { $where[] = '(t.titre LIKE ? OR t.description LIKE ?)'; $bind[] = "%$search%"; $bind[] = "%$search%"; }

    $dateFrom = $params['date_from'] ?? '';
    if ($dateFrom) { $where[] = 't.date_echeance >= ?'; $bind[] = $dateFrom; }

    $dateTo = $params['date_to'] ?? '';
    if ($dateTo) { $where[] = 't.date_echeance <= ?'; $bind[] = $dateTo; }

    // Vue "aujourd'hui" : tâches du jour + en retard + urgentes
    $vue = $params['vue'] ?? '';
    if ($vue === 'today') {
        $today = date('Y-m-d');
        $where[] = '(t.date_echeance <= ? OR t.priorite = ?) AND t.statut IN (?,?)';
        $bind[] = $today; $bind[] = 'urgente'; $bind[] = 'a_faire'; $bind[] = 'en_cours';
    } elseif ($vue === 'week') {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $sunday = date('Y-m-d', strtotime('sunday this week'));
        $where[] = 't.date_echeance BETWEEN ? AND ? AND t.statut IN (?,?)';
        $bind[] = $monday; $bind[] = $sunday; $bind[] = 'a_faire'; $bind[] = 'en_cours';
    }

    $sql = "SELECT t.*,
                u1.prenom AS assigned_prenom, u1.nom AS assigned_nom,
                u2.prenom AS creator_prenom, u2.nom AS creator_nom
            FROM admin_todos t
            LEFT JOIN users u1 ON u1.id = t.assigned_to
            LEFT JOIN users u2 ON u2.id = t.created_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                FIELD(t.priorite, 'urgente','haute','normale','basse'),
                CASE WHEN t.date_echeance IS NULL THEN 1 ELSE 0 END,
                t.date_echeance ASC,
                t.created_at DESC";

    $todos = Db::fetchAll($sql, $bind);

    // Stats rapides
    $stats = Db::fetch("SELECT
        COUNT(*) AS total,
        SUM(statut = 'a_faire') AS a_faire,
        SUM(statut = 'en_cours') AS en_cours,
        SUM(statut = 'termine') AS termine,
        SUM(statut = 'annule') AS annule,
        SUM(date_echeance < CURDATE() AND statut IN ('a_faire','en_cours')) AS en_retard,
        SUM(priorite = 'urgente' AND statut IN ('a_faire','en_cours')) AS urgentes
    FROM admin_todos");

    respond(['success' => true, 'todos' => $todos, 'stats' => $stats]);
}

function admin_create_todo()
{
    global $params;
    require_responsable();

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO admin_todos (id, titre, description, priorite, statut, date_echeance, assigned_to, created_by)
         VALUES (?, ?, ?, ?, 'a_faire', ?, ?, ?)",
        [
            $id,
            $titre,
            Sanitize::text($params['description'] ?? '', 2000),
            in_array($params['priorite'] ?? '', ['basse','normale','haute','urgente']) ? $params['priorite'] : 'normale',
            Sanitize::date($params['date_echeance'] ?? '') ?: null,
            ($params['assigned_to'] ?? '') ?: null,
            $_SESSION['admin']['id'],
        ]
    );

    respond(['success' => true, 'id' => $id]);
}

function admin_update_todo()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fields = [];
    $bind = [];

    if (isset($params['titre'])) {
        $fields[] = 'titre = ?';
        $bind[] = Sanitize::text($params['titre'], 255);
    }
    if (isset($params['description'])) {
        $fields[] = 'description = ?';
        $bind[] = Sanitize::text($params['description'], 2000);
    }
    if (isset($params['priorite']) && in_array($params['priorite'], ['basse','normale','haute','urgente'])) {
        $fields[] = 'priorite = ?';
        $bind[] = $params['priorite'];
    }
    if (isset($params['statut']) && in_array($params['statut'], ['a_faire','en_cours','termine','annule'])) {
        $fields[] = 'statut = ?';
        $bind[] = $params['statut'];
        if ($params['statut'] === 'termine') {
            $fields[] = 'completed_at = NOW()';
        } elseif (in_array($params['statut'], ['a_faire', 'en_cours'])) {
            $fields[] = 'completed_at = NULL';
        }
    }
    if (isset($params['date_echeance'])) {
        $fields[] = 'date_echeance = ?';
        $bind[] = Sanitize::date($params['date_echeance']) ?: null;
    }
    if (isset($params['assigned_to'])) {
        $fields[] = 'assigned_to = ?';
        $bind[] = $params['assigned_to'] ?: null;
    }

    if (empty($fields)) bad_request('Rien à mettre à jour');

    $bind[] = $id;
    Db::exec("UPDATE admin_todos SET " . implode(', ', $fields) . " WHERE id = ?", $bind);

    respond(['success' => true]);
}

function admin_delete_todo()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM admin_todos WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_get_todo_users()
{
    require_responsable();
    $users = Db::fetchAll("SELECT id, prenom, nom FROM users WHERE is_active = 1 ORDER BY prenom, nom");
    respond(['success' => true, 'users' => $users]);
}
