<?php

function admin_get_agenda_events() {
    global $params;
    $start = $params['start'] ?? date('Y-m-01');
    $end = $params['end'] ?? date('Y-m-t');
    $category = $params['category'] ?? '';
    $search = $params['search'] ?? '';
    $userId = $_SESSION['zt_user']['id'];

    $where = "(e.start_at BETWEEN ? AND ? OR e.end_at BETWEEN ? AND ?)";
    $binds = [$start . ' 00:00:00', $end . ' 23:59:59', $start . ' 00:00:00', $end . ' 23:59:59'];

    // Show own events + events where user is participant + non-private events
    $where .= " AND (e.created_by = ? OR e.is_private = 0 OR ap.user_id = ?)";
    $binds[] = $userId;
    $binds[] = $userId;

    if ($category) {
        $where .= " AND e.category = ?";
        $binds[] = $category;
    }
    if ($search) {
        $where .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
        $s = '%' . $search . '%';
        $binds = array_merge($binds, [$s, $s, $s]);
    }

    $events = Db::fetchAll(
        "SELECT DISTINCT e.*, u.prenom AS creator_prenom, u.nom AS creator_nom
         FROM agenda_events e
         JOIN users u ON u.id = e.created_by
         LEFT JOIN agenda_participants ap ON ap.event_id = e.id
         WHERE $where
         ORDER BY e.start_at ASC",
        $binds
    );

    // Get participants for each event
    foreach ($events as &$ev) {
        $ev['participants'] = Db::fetchAll(
            "SELECT ap.*, u.prenom, u.nom, u.email AS user_email
             FROM agenda_participants ap
             LEFT JOIN users u ON u.id = ap.user_id
             WHERE ap.event_id = ?
             ORDER BY ap.created_at",
            [$ev['id']]
        );
    }
    unset($ev);

    respond(['success' => true, 'events' => $events]);
}

function admin_create_agenda_event() {
    global $params;
    $userId = $_SESSION['zt_user']['id'];

    $title = trim($params['title'] ?? '');
    if (!$title) bad_request('Titre requis');

    $id = Uuid::v4();
    $startAt = $params['start_at'] ?? '';
    $endAt = $params['end_at'] ?: null;
    if (!$startAt) bad_request('Date de début requise');

    $allowedCats = ['rdv','reunion','rappel','personnel','medical','formation','autre'];
    $category = in_array($params['category'] ?? '', $allowedCats) ? $params['category'] : 'rdv';

    Db::exec(
        "INSERT INTO agenda_events (id, title, description, location, category, color, all_day, start_at, end_at, recurrence, recurrence_end, reminder_minutes, is_private, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $title,
            $params['description'] ?? null,
            $params['location'] ?? null,
            $category,
            $params['color'] ?? '#2d4a43',
            (int)($params['all_day'] ?? 0),
            $startAt,
            $endAt,
            $params['recurrence'] ?? 'none',
            $params['recurrence_end'] ?: null,
            (int)($params['reminder_minutes'] ?? 15),
            (int)($params['is_private'] ?? 0),
            $params['notes'] ?? null,
            $userId
        ]
    );

    // Add participants
    if (!empty($params['participants']) && is_array($params['participants'])) {
        foreach ($params['participants'] as $p) {
            Db::exec(
                "INSERT INTO agenda_participants (id, event_id, user_id, external_name, external_email, status) VALUES (?, ?, ?, ?, ?, 'pending')",
                [Uuid::v4(), $id, $p['user_id'] ?? null, $p['external_name'] ?? null, $p['external_email'] ?? null]
            );
        }
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Événement créé']);
}

function admin_update_agenda_event() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ev = Db::fetch("SELECT * FROM agenda_events WHERE id = ?", [$id]);
    if (!$ev) not_found();

    $title = trim($params['title'] ?? $ev['title']);
    $startAt = $params['start_at'] ?? $ev['start_at'];
    $endAt = array_key_exists('end_at', $params) ? ($params['end_at'] ?: null) : $ev['end_at'];

    $allowedCats = ['rdv','reunion','rappel','personnel','medical','formation','autre'];
    $category = in_array($params['category'] ?? '', $allowedCats) ? $params['category'] : $ev['category'];

    Db::exec(
        "UPDATE agenda_events SET title=?, description=?, location=?, category=?, color=?, all_day=?, start_at=?, end_at=?, recurrence=?, recurrence_end=?, reminder_minutes=?, is_private=?, notes=? WHERE id=?",
        [
            $title,
            array_key_exists('description', $params) ? $params['description'] : $ev['description'],
            array_key_exists('location', $params) ? $params['location'] : $ev['location'],
            $category,
            $params['color'] ?? $ev['color'],
            (int)($params['all_day'] ?? $ev['all_day']),
            $startAt,
            $endAt,
            $params['recurrence'] ?? $ev['recurrence'],
            array_key_exists('recurrence_end', $params) ? ($params['recurrence_end'] ?: null) : $ev['recurrence_end'],
            (int)($params['reminder_minutes'] ?? $ev['reminder_minutes']),
            (int)($params['is_private'] ?? $ev['is_private']),
            array_key_exists('notes', $params) ? $params['notes'] : $ev['notes'],
            $id
        ]
    );

    // Update participants if provided
    if (isset($params['participants']) && is_array($params['participants'])) {
        Db::exec("DELETE FROM agenda_participants WHERE event_id = ?", [$id]);
        foreach ($params['participants'] as $p) {
            Db::exec(
                "INSERT INTO agenda_participants (id, event_id, user_id, external_name, external_email, status) VALUES (?, ?, ?, ?, ?, ?)",
                [Uuid::v4(), $id, $p['user_id'] ?? null, $p['external_name'] ?? null, $p['external_email'] ?? null, $p['status'] ?? 'pending']
            );
        }
    }

    respond(['success' => true, 'message' => 'Événement mis à jour']);
}

function admin_move_agenda_event() {
    global $params;
    $id = $params['id'] ?? '';
    $startAt = $params['start_at'] ?? '';
    $endAt = $params['end_at'] ?? null;
    if (!$id || !$startAt) bad_request('ID et date requis');

    Db::exec("UPDATE agenda_events SET start_at = ?, end_at = ? WHERE id = ?", [$startAt, $endAt, $id]);
    respond(['success' => true, 'message' => 'Événement déplacé']);
}

function admin_delete_agenda_event() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM agenda_events WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Événement supprimé']);
}

function admin_search_agenda() {
    global $params;
    $q = trim($params['q'] ?? '');
    if (strlen($q) < 2) respond(['success' => true, 'results' => []]);

    $s = '%' . $q . '%';
    $results = Db::fetchAll(
        "SELECT e.id, e.title, e.start_at, e.end_at, e.category, e.color, e.location
         FROM agenda_events e
         WHERE (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)
         ORDER BY e.start_at DESC LIMIT 20",
        [$s, $s, $s]
    );
    respond(['success' => true, 'results' => $results]);
}

function admin_get_agenda_contacts() {
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, f.nom AS fonction_nom
         FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 ORDER BY u.nom, u.prenom"
    );
    respond(['success' => true, 'contacts' => $users]);
}
