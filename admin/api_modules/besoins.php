<?php
function admin_get_besoins() {
    require_admin();
    $besoins = Db::fetchAll(
        "SELECT bc.*, m.code AS module_code, m.nom AS module_nom, f.code AS fonction_code, f.nom AS fonction_nom
         FROM besoins_couverture bc
         JOIN modules m ON m.id = bc.module_id
         JOIN fonctions f ON f.id = bc.fonction_id
         ORDER BY m.ordre, f.ordre, bc.jour_semaine"
    );
    $modules = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre");
    $fonctions = Db::fetchAll("SELECT id, code, nom FROM fonctions ORDER BY ordre");
    respond(['success' => true, 'besoins' => $besoins, 'modules' => $modules, 'fonctions' => $fonctions]);
}

function admin_save_besoin() {
    global $params;
    require_admin();
    $moduleId = $params['module_id'] ?? '';
    $fonctionId = $params['fonction_id'] ?? '';
    $jourSemaine = Sanitize::int($params['jour_semaine'] ?? 0);
    $nbRequis = Sanitize::int($params['nb_requis'] ?? 1);
    if (!$moduleId || !$fonctionId || $jourSemaine < 1 || $jourSemaine > 7 || $nbRequis < 0) bad_request('Données invalides');

    // Upsert: check if exists
    $existing = Db::fetch(
        "SELECT id FROM besoins_couverture WHERE module_id = ? AND fonction_id = ? AND jour_semaine = ?",
        [$moduleId, $fonctionId, $jourSemaine]
    );
    if ($nbRequis === 0) {
        if ($existing) Db::exec("DELETE FROM besoins_couverture WHERE id = ?", [$existing['id']]);
        respond(['success' => true, 'message' => 'Besoin supprimé']);
    }
    if ($existing) {
        Db::exec("UPDATE besoins_couverture SET nb_requis = ? WHERE id = ?", [$nbRequis, $existing['id']]);
    } else {
        Db::exec("INSERT INTO besoins_couverture (id, module_id, fonction_id, jour_semaine, nb_requis) VALUES (?, ?, ?, ?, ?)",
            [Uuid::v4(), $moduleId, $fonctionId, $jourSemaine, $nbRequis]);
    }
    respond(['success' => true, 'message' => 'Besoin enregistré']);
}

function admin_delete_besoin() {
    global $params;
    require_admin();
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM besoins_couverture WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Besoin supprimé']);
}

function admin_reset_module_besoins() {
    global $params;
    require_admin();
    $moduleId = $params['module_id'] ?? '';
    if (!$moduleId) bad_request('module_id requis');
    // Verify module exists
    $mod = Db::fetch("SELECT id FROM modules WHERE id = ?", [$moduleId]);
    if (!$mod) not_found('Module introuvable');
    $deleted = Db::exec("DELETE FROM besoins_couverture WHERE module_id = ?", [$moduleId]);
    respond(['success' => true, 'message' => 'Besoins du module remis à zéro', 'deleted' => $deleted]);
}

function admin_copy_module_besoins() {
    global $params;
    require_admin();
    $sourceId = $params['source_module_id'] ?? '';
    $targetId = $params['target_module_id'] ?? '';
    if (!$sourceId || !$targetId) bad_request('source_module_id et target_module_id requis');
    if ($sourceId === $targetId) bad_request('Les modules source et cible doivent être différents');

    // Verify both modules exist
    $src = Db::fetch("SELECT id FROM modules WHERE id = ?", [$sourceId]);
    $tgt = Db::fetch("SELECT id FROM modules WHERE id = ?", [$targetId]);
    if (!$src || !$tgt) not_found('Module introuvable');

    // Delete existing target besoins
    Db::exec("DELETE FROM besoins_couverture WHERE module_id = ?", [$targetId]);

    // Copy from source
    $sourceBesoins = Db::fetchAll(
        "SELECT fonction_id, jour_semaine, nb_requis FROM besoins_couverture WHERE module_id = ?",
        [$sourceId]
    );
    $copied = 0;
    foreach ($sourceBesoins as $b) {
        Db::exec(
            "INSERT INTO besoins_couverture (id, module_id, fonction_id, jour_semaine, nb_requis) VALUES (?, ?, ?, ?, ?)",
            [Uuid::v4(), $targetId, $b['fonction_id'], $b['jour_semaine'], $b['nb_requis']]
        );
        $copied++;
    }
    respond(['success' => true, 'message' => $copied . ' besoins copiés', 'copied' => $copied]);
}
