<?php
/**
 * Admin API — Email Externe (IMAP/SMTP)
 */

require_once __DIR__ . '/../../core/Mailer.php';

// ── Helper: get mailer instance for current user ──
function _getMailer(): Mailer
{
    $user = require_auth();
    $config = Db::fetch("SELECT * FROM email_externe_config WHERE user_id = ? AND is_active = 1", [$user['id']]);
    if (!$config) bad_request('Email externe non configuré. Allez dans Configuration Email.');

    $password = Mailer::decryptPassword($config['encrypted_password'], $config['password_iv']);

    return new Mailer([
        'imap_host' => $config['imap_host'],
        'imap_port' => $config['imap_port'],
        'imap_encryption' => $config['imap_encryption'],
        'smtp_host' => $config['smtp_host'],
        'smtp_port' => $config['smtp_port'],
        'smtp_encryption' => $config['smtp_encryption'],
        'username' => $config['username'],
        'password' => $password,
        'email_address' => $config['email_address'],
        'display_name' => $config['display_name'] ?? '',
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Configuration
// ═══════════════════════════════════════════════════════════════════════════════

function admin_email_ext_get_providers()
{
    require_auth();
    respond(['success' => true, 'providers' => Mailer::getProviders()]);
}

function admin_email_ext_get_config()
{
    $user = require_auth();
    $config = Db::fetch(
        "SELECT id, provider, email_address, display_name, imap_host, imap_port, imap_encryption,
                smtp_host, smtp_port, smtp_encryption, username, signature, is_active, last_sync
         FROM email_externe_config WHERE user_id = ?",
        [$user['id']]
    );
    respond(['success' => true, 'config' => $config]);
}

function admin_email_ext_save_config()
{
    global $params;
    $user = require_auth();

    $provider = $params['provider'] ?? 'custom';
    $emailAddress = trim($params['email_address'] ?? '');
    $displayName = trim($params['display_name'] ?? '');
    $imapHost = trim($params['imap_host'] ?? '');
    $imapPort = (int) ($params['imap_port'] ?? 993);
    $imapEncryption = in_array($params['imap_encryption'] ?? '', ['ssl', 'tls', 'none']) ? $params['imap_encryption'] : 'ssl';
    $smtpHost = trim($params['smtp_host'] ?? '');
    $smtpPort = (int) ($params['smtp_port'] ?? 587);
    $smtpEncryption = in_array($params['smtp_encryption'] ?? '', ['ssl', 'tls', 'none']) ? $params['smtp_encryption'] : 'tls';
    $username = trim($params['username'] ?? '');
    $password = $params['password'] ?? '';
    $signature = $params['signature'] ?? '';

    if (!$emailAddress || !$imapHost || !$smtpHost || !$username) {
        bad_request('Champs requis manquants');
    }

    $existing = Db::fetch("SELECT id, encrypted_password, password_iv FROM email_externe_config WHERE user_id = ?", [$user['id']]);

    // Encrypt password (only if changed)
    if ($password) {
        $enc = Mailer::encryptPassword($password);
        $encPassword = $enc['encrypted'];
        $passwordIv = $enc['iv'];
    } elseif ($existing) {
        $encPassword = $existing['encrypted_password'];
        $passwordIv = $existing['password_iv'];
    } else {
        bad_request('Mot de passe requis');
    }

    if ($existing) {
        // If email address changed, clear cached emails from old account
        $oldEmail = Db::getOne("SELECT email_address FROM email_externe_config WHERE user_id = ?", [$user['id']]);
        if ($oldEmail && $oldEmail !== $emailAddress) {
            Db::exec("DELETE FROM email_externe_cache WHERE user_id = ?", [$user['id']]);
        }

        Db::exec(
            "UPDATE email_externe_config SET provider = ?, email_address = ?, display_name = ?,
             imap_host = ?, imap_port = ?, imap_encryption = ?,
             smtp_host = ?, smtp_port = ?, smtp_encryption = ?,
             username = ?, encrypted_password = ?, password_iv = ?, signature = ?
             WHERE user_id = ?",
            [$provider, $emailAddress, $displayName, $imapHost, $imapPort, $imapEncryption,
             $smtpHost, $smtpPort, $smtpEncryption, $username, $encPassword, $passwordIv, $signature, $user['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO email_externe_config (id, user_id, provider, email_address, display_name,
             imap_host, imap_port, imap_encryption, smtp_host, smtp_port, smtp_encryption,
             username, encrypted_password, password_iv, signature)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $user['id'], $provider, $emailAddress, $displayName,
             $imapHost, $imapPort, $imapEncryption, $smtpHost, $smtpPort, $smtpEncryption,
             $username, $encPassword, $passwordIv, $signature]
        );
    }

    respond(['success' => true, 'message' => 'Configuration enregistrée']);
}

function admin_email_ext_test()
{
    global $params;
    require_auth();

    $password = $params['password'] ?? '';
    // If no password given, use saved one
    if (!$password) {
        $user = require_auth();
        $config = Db::fetch("SELECT encrypted_password, password_iv FROM email_externe_config WHERE user_id = ?", [$user['id']]);
        if ($config) $password = Mailer::decryptPassword($config['encrypted_password'], $config['password_iv']);
    }
    if (!$password) bad_request('Mot de passe requis');

    $mailer = new Mailer([
        'imap_host' => $params['imap_host'] ?? '',
        'imap_port' => (int) ($params['imap_port'] ?? 993),
        'imap_encryption' => $params['imap_encryption'] ?? 'ssl',
        'smtp_host' => $params['smtp_host'] ?? '',
        'smtp_port' => (int) ($params['smtp_port'] ?? 587),
        'smtp_encryption' => $params['smtp_encryption'] ?? 'tls',
        'username' => $params['username'] ?? '',
        'password' => $password,
        'email_address' => $params['email_address'] ?? '',
        'display_name' => $params['display_name'] ?? '',
    ]);

    $imapResult = $mailer->testImap();
    $smtpResult = $mailer->testSmtp();

    respond([
        'success' => true,
        'imap' => $imapResult,
        'smtp' => $smtpResult,
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Inbox / Folders
// ═══════════════════════════════════════════════════════════════════════════════

function admin_email_ext_get_folders()
{
    $mailer = _getMailer();
    respond(['success' => true, 'folders' => $mailer->getFolders()]);
}

function admin_email_ext_fetch_list()
{
    global $params;
    $mailer = _getMailer();
    $folder = $params['folder'] ?? 'INBOX';
    $limit = min(50, max(10, (int)($params['limit'] ?? 30)));
    $offset = max(0, (int)($params['offset'] ?? 0));

    $result = $mailer->fetchHeaders($folder, $limit, $offset);
    respond(['success' => true, 'emails' => $result['emails'], 'total' => $result['total']]);
}

function admin_email_ext_fetch_email()
{
    global $params;
    $mailer = _getMailer();
    $folder = $params['folder'] ?? 'INBOX';
    $uid = (int) ($params['uid'] ?? 0);
    if (!$uid) bad_request('UID requis');

    $email = $mailer->fetchEmail($folder, $uid);
    respond(['success' => true, 'email' => $email]);
}

function admin_email_ext_download_attachment()
{
    global $params;
    $mailer = _getMailer();
    $folder = $params['folder'] ?? 'INBOX';
    $uid = (int) ($params['uid'] ?? 0);
    $partIndex = (int) ($params['part_index'] ?? 0);

    $att = $mailer->downloadAttachment($folder, $uid, $partIndex);

    header('Content-Type: ' . $att['mime']);
    header('Content-Disposition: ' . safe_content_disposition($att['filename'], 'attachment'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . $att['size']);
    echo $att['data'];
    exit;
}

function admin_email_ext_delete()
{
    global $params;
    $mailer = _getMailer();
    $folder = $params['folder'] ?? 'INBOX';
    $uid = (int) ($params['uid'] ?? 0);
    if (!$uid) bad_request('UID requis');

    $mailer->deleteEmail($folder, $uid);
    respond(['success' => true, 'message' => 'Email supprimé']);
}

function admin_email_ext_empty_trash()
{
    $mailer = _getMailer();
    $deleted = $mailer->emptyTrash();
    respond(['success' => true, 'deleted' => $deleted, 'message' => $deleted . ' email(s) supprimé(s) définitivement']);
}

function admin_email_ext_send()
{
    global $params;
    $user = require_auth();
    $mailer = _getMailer();

    $to = $params['to'] ?? [];
    $cc = $params['cc'] ?? [];
    $subject = trim($params['subject'] ?? '');
    $body = $params['body'] ?? '';
    $replyTo = $params['reply_to'] ?? null;

    if (!$to || !$subject) bad_request('Destinataire et sujet requis');
    if (!is_array($to)) $to = [$to];
    if (!is_array($cc)) $cc = [$cc];

    // Wrap body in proper HTML envelope for email clients
    $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333">'
        . ($body ?: '<p>&nbsp;</p>');

    // Append signature
    $config = Db::fetch("SELECT signature FROM email_externe_config WHERE user_id = ?", [$user['id']]);
    if ($config && $config['signature']) {
        $htmlBody .= '<br><br>' . $config['signature'];
    }

    $htmlBody .= '</body></html>';

    $mailer->sendEmail($to, $cc, $subject, $htmlBody, [], $replyTo);
    respond(['success' => true, 'message' => 'Email envoyé']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Contacts externes
// ═══════════════════════════════════════════════════════════════════════════════

function admin_email_ext_get_contacts()
{
    $user = require_auth();
    $contacts = Db::fetchAll(
        "SELECT * FROM email_externe_contacts WHERE created_by = ? OR is_shared = 1 ORDER BY nom, prenom",
        [$user['id']]
    );
    respond(['success' => true, 'contacts' => $contacts]);
}

function admin_email_ext_save_contact()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    $nom = trim($params['nom'] ?? '');
    $prenom = trim($params['prenom'] ?? '');
    $email = trim($params['email'] ?? '');
    $entreprise = trim($params['entreprise'] ?? '');
    $telephone = trim($params['telephone'] ?? '');
    $notes = trim($params['notes'] ?? '');
    $isShared = (int) ($params['is_shared'] ?? 0);

    if (!$email) bad_request('Email requis');

    if ($id) {
        Db::exec(
            "UPDATE email_externe_contacts SET nom = ?, prenom = ?, email = ?, entreprise = ?, telephone = ?, notes = ?, is_shared = ? WHERE id = ?",
            [$nom, $prenom, $email, $entreprise, $telephone, $notes, $isShared, $id]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO email_externe_contacts (id, nom, prenom, email, entreprise, telephone, notes, created_by, is_shared) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $nom, $prenom, $email, $entreprise, $telephone, $notes, $user['id'], $isShared]
        );
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Contact enregistré']);
}

function admin_email_ext_delete_contact()
{
    global $params;
    require_auth();
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM email_externe_contacts WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Contact supprimé']);
}

function admin_email_ext_extract_contacts()
{
    $mailer = _getMailer();
    $contacts = $mailer->extractContacts(500);
    respond(['success' => true, 'contacts' => $contacts, 'total' => count($contacts)]);
}

function admin_email_ext_import_contacts()
{
    global $params;
    $user = require_auth();
    $contacts = $params['contacts'] ?? [];
    if (!is_array($contacts) || empty($contacts)) bad_request('Aucun contact à importer');

    $imported = 0;
    $skipped = 0;
    foreach ($contacts as $c) {
        $email = trim($c['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }

        // Skip duplicates
        $exists = Db::getOne("SELECT COUNT(*) FROM email_externe_contacts WHERE email = ? AND (created_by = ? OR is_shared = 1)", [$email, $user['id']]);
        if ($exists) { $skipped++; continue; }

        Db::exec(
            "INSERT INTO email_externe_contacts (id, nom, prenom, email, entreprise, telephone, notes, created_by, is_shared) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [Uuid::v4(), trim($c['nom'] ?? ''), trim($c['prenom'] ?? ''), $email, trim($c['entreprise'] ?? ''), trim($c['telephone'] ?? ''), trim($c['notes'] ?? ''), $user['id']]
        );
        $imported++;
    }

    respond(['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => "$imported contact(s) importé(s)" . ($skipped ? ", $skipped ignoré(s)" : '')]);
}
