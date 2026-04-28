<?php
/**
 * Préférence de thème (apparence) — employé.
 * Endpoints : get_apparence, save_apparence
 */

const ALLOWED_THEMES = ['default', 'sombre', 'care'];

function get_apparence()
{
    require_auth();
    $userId = $_SESSION['ss_user']['id'];
    $theme = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$userId]) ?: 'default';
    respond(['success' => true, 'theme' => $theme]);
}

function save_apparence()
{
    require_auth();
    global $params;
    $theme = Sanitize::text($params['theme'] ?? '', 20);
    if (!in_array($theme, ALLOWED_THEMES, true)) bad_request('Thème inconnu');
    $userId = $_SESSION['ss_user']['id'];
    Db::exec("UPDATE users SET theme_preference = ? WHERE id = ?", [$theme, $userId]);
    respond(['success' => true, 'theme' => $theme]);
}
