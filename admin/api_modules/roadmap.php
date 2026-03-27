<?php
/**
 * Admin API — Roadmap CRUD
 */

$GLOBALS['_roadmap_file'] = __DIR__ . '/../../data/roadmap.json';

function _roadmap_load() {
    $file = $GLOBALS['_roadmap_file'];
    if (!file_exists($file)) return [];
    $items = json_decode(file_get_contents($file), true);
    return is_array($items) ? $items : [];
}

function _roadmap_save($items) {
    $file = $GLOBALS['_roadmap_file'];
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function admin_roadmap_toggle() {
    require_admin();
    global $params;

    $id   = trim($params['id'] ?? '');
    $done = !empty($params['done']);
    if (!$id) bad_request('ID manquant');

    $items = _roadmap_load();
    $found = false;
    foreach ($items as &$item) {
        if ($item['id'] === $id) { $item['done'] = $done; $found = true; break; }
    }
    unset($item);
    if (!$found) bad_request('Tache introuvable');

    _roadmap_save($items);
    respond(['success' => true]);
}

function admin_roadmap_create() {
    require_admin();
    global $params;

    $title = trim($params['title'] ?? '');
    $desc  = trim($params['desc'] ?? '');
    $cat   = trim($params['category'] ?? 'Admin');
    $diff  = trim($params['difficulty'] ?? 'facile');
    if (!$title) bad_request('Titre requis');
    if (!in_array($diff, ['facile', 'moyen', 'difficile'])) $diff = 'facile';

    $items = _roadmap_load();

    // Generate unique id from title
    $baseId = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $baseId = trim($baseId, '-');
    if (!$baseId) $baseId = 'task';
    $id = $baseId;
    $existing = array_column($items, 'id');
    $i = 2;
    while (in_array($id, $existing)) { $id = $baseId . '-' . $i; $i++; }

    // Calculate order: insert in correct difficulty group
    $diffOrder = ['facile' => 1, 'moyen' => 2, 'difficile' => 3];
    $maxOrder = 0;
    foreach ($items as $item) {
        if (($diffOrder[$item['difficulty']] ?? 2) <= ($diffOrder[$diff] ?? 2)) {
            $maxOrder = max($maxOrder, $item['order'] ?? 0);
        }
    }
    $newOrder = $maxOrder + 1;

    // Shift items after
    foreach ($items as &$item) {
        if (($item['order'] ?? 0) >= $newOrder) {
            $item['order'] = ($item['order'] ?? 0) + 1;
        }
    }
    unset($item);

    $items[] = [
        'id'         => $id,
        'title'      => $title,
        'desc'       => $desc,
        'difficulty'  => $diff,
        'category'   => $cat,
        'done'       => false,
        'order'      => $newOrder,
    ];

    // Sort by order
    usort($items, fn($a, $b) => ($a['order'] ?? 99) - ($b['order'] ?? 99));

    _roadmap_save($items);
    respond(['success' => true, 'id' => $id]);
}

function admin_roadmap_update() {
    require_admin();
    global $params;

    $id    = trim($params['id'] ?? '');
    $title = trim($params['title'] ?? '');
    $desc  = trim($params['desc'] ?? '');
    $cat   = trim($params['category'] ?? '');
    $diff  = trim($params['difficulty'] ?? '');
    if (!$id) bad_request('ID manquant');
    if (!$title) bad_request('Titre requis');
    if (!in_array($diff, ['facile', 'moyen', 'difficile'])) $diff = 'facile';

    $items = _roadmap_load();
    $found = false;
    foreach ($items as &$item) {
        if ($item['id'] === $id) {
            $item['title']      = $title;
            $item['desc']       = $desc;
            $item['category']   = $cat;
            $item['difficulty']  = $diff;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) bad_request('Tache introuvable');

    _roadmap_save($items);
    respond(['success' => true]);
}

function admin_roadmap_delete() {
    require_admin();
    global $params;

    $id = trim($params['id'] ?? '');
    if (!$id) bad_request('ID manquant');

    $items = _roadmap_load();
    $items = array_filter($items, fn($i) => $i['id'] !== $id);

    if (count($items) === count(_roadmap_load())) bad_request('Tache introuvable');

    _roadmap_save($items);
    respond(['success' => true]);
}
