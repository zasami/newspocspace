<?php
/**
 * Préférence de thème (apparence) — admin.
 * Endpoints : admin_get_apparence, admin_save_apparence
 */

const ADMIN_ALLOWED_THEMES = ['default', 'sombre', 'care'];

function admin_get_apparence()
{
    require_responsable();
    $userId = $_SESSION['admin']['id'];
    $theme = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$userId]) ?: 'default';
    respond(['success' => true, 'theme' => $theme]);
}

function admin_save_apparence()
{
    require_responsable();
    global $params;
    $theme = Sanitize::text($params['theme'] ?? '', 20);
    if (!in_array($theme, ADMIN_ALLOWED_THEMES, true)) bad_request('Thème inconnu');
    $userId = $_SESSION['admin']['id'];
    Db::exec("UPDATE users SET theme_preference = ? WHERE id = ?", [$theme, $userId]);
    respond(['success' => true, 'theme' => $theme]);
}
