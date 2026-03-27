<?php
require 'config/config.php';
require 'core/Db.php';
require 'core/Uuid.php';

// Mock Auth
class Auth {
    public static function isAdmin() { return true; }
    public static function check() {}
    public static function id() { return '1'; }
}

function require_admin() {}
function bad_request($msg) { die("Bad Request: $msg\n"); }
function json_response($data) { die(json_encode($data)); }

$params = ["action" => "admin_generate_planning", "mois" => "2026-03", "mode" => "ai"];

require 'admin/api_modules/planning.php';
admin_generate_planning();
