<?php
/**
 * Mailer — IMAP reader + SMTP sender for external emails
 * Uses native PHP imap extension + socket-based SMTP
 */
class Mailer
{
    private string $imapHost;
    private int $imapPort;
    private string $imapEncryption;
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpEncryption;
    private string $username;
    private string $password;
    private string $emailAddress;
    private string $displayName;

    // IMAP connection
    private $imap = null;

    // ── Provider presets ──────────────────────────────────────────────────────

    public static function getProviders(): array
    {
        return [
            'infomaniak' => [
                'label' => 'Infomaniak',
                'imap_host' => 'mail.infomaniak.com', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'mail.infomaniak.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'gmail' => [
                'label' => 'Gmail / Google Workspace',
                'imap_host' => 'imap.gmail.com', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'outlook' => [
                'label' => 'Outlook / Office 365',
                'imap_host' => 'outlook.office365.com', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.office365.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'ovh' => [
                'label' => 'OVH',
                'imap_host' => 'ssl0.ovh.net', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'ssl0.ovh.net', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'gandi' => [
                'label' => 'Gandi',
                'imap_host' => 'mail.gandi.net', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'mail.gandi.net', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'ionos' => [
                'label' => 'IONOS / 1&1',
                'imap_host' => 'imap.ionos.fr', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.ionos.fr', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'bluewin' => [
                'label' => 'Bluewin / Swisscom',
                'imap_host' => 'imaps.bluewin.ch', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'smtpauths.bluewin.ch', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
            ],
            'hostpoint' => [
                'label' => 'Hostpoint (Suisse)',
                'imap_host' => 'imap.hostpoint.ch', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.hostpoint.ch', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
            'custom' => [
                'label' => 'Personnalisé',
                'imap_host' => '', 'imap_port' => 993, 'imap_encryption' => 'ssl',
                'smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            ],
        ];
    }

    // ── Password encryption ──────────────────────────────────────────────────

    private static function getEncryptionKey(): string
    {
        // Use a stable key derived from DB credentials
        return hash('sha256', DB_PASS . DB_NAME . 'zt_mailer_key', true);
    }

    public static function encryptPassword(string $password): array
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(12);
        $encrypted = openssl_encrypt($password, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return [
            'encrypted' => base64_encode($encrypted . $tag),
            'iv' => bin2hex($iv),
        ];
    }

    public static function decryptPassword(string $encrypted, string $ivHex): string
    {
        $key = self::getEncryptionKey();
        $iv = hex2bin($ivHex);
        $raw = base64_decode($encrypted);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 0, -16);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted === false) throw new \RuntimeException('Impossible de déchiffrer le mot de passe');
        return $decrypted;
    }

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct(array $config)
    {
        $this->imapHost = $config['imap_host'];
        $this->imapPort = (int) $config['imap_port'];
        $this->imapEncryption = $config['imap_encryption'];
        $this->smtpHost = $config['smtp_host'];
        $this->smtpPort = (int) $config['smtp_port'];
        $this->smtpEncryption = $config['smtp_encryption'];
        $this->username = $config['username'];
        $this->password = $config['password']; // already decrypted
        $this->emailAddress = $config['email_address'];
        $this->displayName = $config['display_name'] ?? '';
    }

    public function __destruct()
    {
        $this->disconnectImap();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // IMAP — Read
    // ═══════════════════════════════════════════════════════════════════════════

    private function getImapMailbox(string $folder = 'INBOX'): string
    {
        $flags = '';
        if ($this->imapEncryption === 'ssl') $flags = '/imap/ssl/validate-cert';
        elseif ($this->imapEncryption === 'tls') $flags = '/imap/tls';
        else $flags = '/imap/notls';
        return '{' . $this->imapHost . ':' . $this->imapPort . $flags . '}' . $folder;
    }

    public function connectImap(string $folder = 'INBOX'): bool
    {
        $this->disconnectImap();
        $mailbox = $this->getImapMailbox($folder);
        $this->imap = @imap_open($mailbox, $this->username, $this->password, 0, 1);
        if (!$this->imap) {
            $err = imap_last_error();
            throw new \RuntimeException('Connexion IMAP échouée : ' . ($err ?: 'erreur inconnue'));
        }
        return true;
    }

    public function disconnectImap(): void
    {
        if ($this->imap) {
            @imap_close($this->imap);
            $this->imap = null;
        }
    }

    /**
     * Test IMAP connection
     */
    public function testImap(): array
    {
        try {
            $this->connectImap();
            $check = imap_check($this->imap);
            $this->disconnectImap();
            return ['success' => true, 'messages' => $check->Nmsgs, 'mailbox' => $check->Mailbox];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get folder list
     */
    public function getFolders(): array
    {
        $this->connectImap();
        $base = '{' . $this->imapHost . ':' . $this->imapPort . '}';
        $list = imap_list($this->imap, $base, '*') ?: [];
        $folders = [];
        foreach ($list as $f) {
            $name = str_replace($base, '', $f);
            $folders[] = $name;
        }
        $this->disconnectImap();
        return $folders;
    }

    /**
     * Fetch email headers from a folder (for cache sync)
     */
    public function fetchHeaders(string $folder = 'INBOX', int $limit = 50, int $offset = 0): array
    {
        $this->connectImap($folder);
        $check = imap_check($this->imap);
        $total = $check->Nmsgs;
        if ($total === 0) { $this->disconnectImap(); return ['emails' => [], 'total' => 0]; }

        // Fetch latest N
        $from = max(1, $total - $offset - $limit + 1);
        $to = max(1, $total - $offset);
        if ($from > $to) { $this->disconnectImap(); return ['emails' => [], 'total' => $total]; }

        $overview = imap_fetch_overview($this->imap, "$from:$to", 0);
        $emails = [];

        foreach (array_reverse($overview) as $msg) {
            $subject = isset($msg->subject) ? $this->decodeMime($msg->subject) : '(sans sujet)';
            $from_decoded = isset($msg->from) ? $this->decodeMime($msg->from) : '';

            $emails[] = [
                'uid' => $msg->uid,
                'msgno' => $msg->msgno,
                'from' => $from_decoded,
                'subject' => $subject,
                'date' => $msg->date ?? '',
                'is_read' => ($msg->seen ?? 0) ? 1 : 0,
                'is_flagged' => ($msg->flagged ?? 0) ? 1 : 0,
                'size' => $msg->size ?? 0,
            ];
        }

        $this->disconnectImap();
        return ['emails' => $emails, 'total' => $total];
    }

    /**
     * Fetch full email body + attachments info (on demand, not cached)
     */
    public function fetchEmail(string $folder, int $uid): array
    {
        $this->connectImap($folder);
        $msgno = imap_msgno($this->imap, $uid);
        if (!$msgno) throw new \RuntimeException('Message introuvable');

        $header = imap_headerinfo($this->imap, $msgno);
        $structure = imap_fetchstructure($this->imap, $msgno);

        // Decode body
        $body = $this->getBody($msgno, $structure);

        // Get attachments list
        $attachments = $this->getAttachmentsList($msgno, $structure);

        // To / Cc
        $to = [];
        if (isset($header->to)) {
            foreach ($header->to as $addr) {
                $to[] = ['email' => $addr->mailbox . '@' . $addr->host, 'name' => $this->decodeMime($addr->personal ?? '')];
            }
        }
        $cc = [];
        if (isset($header->cc)) {
            foreach ($header->cc as $addr) {
                $cc[] = ['email' => $addr->mailbox . '@' . $addr->host, 'name' => $this->decodeMime($addr->personal ?? '')];
            }
        }

        // Mark as read
        imap_setflag_full($this->imap, (string)$uid, '\\Seen', ST_UID);

        $this->disconnectImap();

        return [
            'uid' => $uid,
            'from' => isset($header->from[0]) ? ($header->from[0]->mailbox . '@' . $header->from[0]->host) : '',
            'from_name' => isset($header->from[0]) ? $this->decodeMime($header->from[0]->personal ?? '') : '',
            'to' => $to,
            'cc' => $cc,
            'subject' => $this->decodeMime($header->subject ?? ''),
            'date' => $header->date ?? '',
            'body' => $body,
            'attachments' => $attachments,
        ];
    }

    /**
     * Download an attachment
     */
    public function downloadAttachment(string $folder, int $uid, int $partIndex): array
    {
        $this->connectImap($folder);
        $msgno = imap_msgno($this->imap, $uid);
        $structure = imap_fetchstructure($this->imap, $msgno);
        $parts = $this->flattenParts($structure);

        if (!isset($parts[$partIndex])) throw new \RuntimeException('Pièce jointe introuvable');

        $part = $parts[$partIndex];
        $data = imap_fetchbody($this->imap, $msgno, $part['section']);
        $data = $this->decodePart($data, $part['encoding']);

        $this->disconnectImap();

        return [
            'filename' => $part['filename'],
            'mime' => $part['mime'],
            'data' => $data,
            'size' => strlen($data),
        ];
    }

    /**
     * Delete an email (move to Trash)
     */
    public function deleteEmail(string $folder, int $uid): bool
    {
        $this->connectImap($folder);
        imap_delete($this->imap, (string)$uid, FT_UID);
        imap_expunge($this->imap);
        $this->disconnectImap();
        return true;
    }

    /**
     * Move email to folder
     */
    public function moveEmail(string $fromFolder, int $uid, string $toFolder): bool
    {
        $this->connectImap($fromFolder);
        imap_mail_move($this->imap, (string)$uid, $toFolder, CP_UID);
        imap_expunge($this->imap);
        $this->disconnectImap();
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SMTP — Send
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Test SMTP connection
     */
    public function testSmtp(): array
    {
        try {
            $smtp = $this->smtpConnect();
            $this->smtpCommand($smtp, "QUIT\r\n", 221);
            fclose($smtp);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an email via SMTP
     */
    public function sendEmail(array $to, array $cc, string $subject, string $htmlBody, array $attachments = [], ?string $replyTo = null): bool
    {
        $boundary = 'ZT_' . bin2hex(random_bytes(16));
        $from = $this->displayName ? '=?UTF-8?B?' . base64_encode($this->displayName) . '?= <' . $this->emailAddress . '>' : $this->emailAddress;

        // Build headers
        $headers = "From: $from\r\n";
        $headers .= "To: " . implode(', ', $to) . "\r\n";
        if ($cc) $headers .= "Cc: " . implode(', ', $cc) . "\r\n";
        if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . Uuid::v4() . "@" . explode('@', $this->emailAddress)[1] . ">\r\n";

        if (empty($attachments)) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $body = base64_encode($htmlBody);
        } else {
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= base64_encode($htmlBody) . "\r\n";
            foreach ($attachments as $att) {
                $body .= "--$boundary\r\n";
                $body .= "Content-Type: " . ($att['mime'] ?? 'application/octet-stream') . "; name=\"" . $att['filename'] . "\"\r\n";
                $body .= "Content-Disposition: attachment; filename=\"" . $att['filename'] . "\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($att['data'])) . "\r\n";
            }
            $body .= "--$boundary--\r\n";
        }

        // Send via SMTP
        $smtp = $this->smtpConnect();
        $this->smtpCommand($smtp, "MAIL FROM:<{$this->emailAddress}>\r\n", 250);
        $allRecipients = array_merge($to, $cc);
        foreach ($allRecipients as $rcpt) {
            $email = preg_match('/<(.+?)>/', $rcpt, $m) ? $m[1] : trim($rcpt);
            $this->smtpCommand($smtp, "RCPT TO:<$email>\r\n", 250);
        }
        $this->smtpCommand($smtp, "DATA\r\n", 354);
        fwrite($smtp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $this->smtpRead($smtp, 250);
        $this->smtpCommand($smtp, "QUIT\r\n", 221);
        fclose($smtp);

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════════════════════

    private function smtpConnect()
    {
        $host = $this->smtpHost;
        if ($this->smtpEncryption === 'ssl') $host = 'ssl://' . $host;

        $smtp = @fsockopen($host, $this->smtpPort, $errno, $errstr, 10);
        if (!$smtp) throw new \RuntimeException("Connexion SMTP échouée : $errstr ($errno)");

        $this->smtpRead($smtp, 220);
        $this->smtpCommand($smtp, "EHLO " . gethostname() . "\r\n", 250);

        if ($this->smtpEncryption === 'tls') {
            $this->smtpCommand($smtp, "STARTTLS\r\n", 220);
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpCommand($smtp, "EHLO " . gethostname() . "\r\n", 250);
        }

        // AUTH LOGIN
        $this->smtpCommand($smtp, "AUTH LOGIN\r\n", 334);
        $this->smtpCommand($smtp, base64_encode($this->username) . "\r\n", 334);
        $this->smtpCommand($smtp, base64_encode($this->password) . "\r\n", 235);

        return $smtp;
    }

    private function smtpCommand($smtp, string $cmd, int $expectedCode): string
    {
        fwrite($smtp, $cmd);
        return $this->smtpRead($smtp, $expectedCode);
    }

    private function smtpRead($smtp, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($smtp, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("Erreur SMTP (attendu $expectedCode, reçu $code) : " . trim($response));
        }
        return $response;
    }

    private function decodeMime(?string $str): string
    {
        if (!$str) return '';
        $elements = imap_mime_header_decode($str);
        $result = '';
        foreach ($elements as $el) {
            $result .= $el->text;
        }
        return $result;
    }

    private function getBody(int $msgno, $structure): string
    {
        // Simple message
        if (!isset($structure->parts) || empty($structure->parts)) {
            $body = imap_fetchbody($this->imap, $msgno, '1');
            $body = $this->decodePart($body, $structure->encoding ?? 0);
            if (($structure->subtype ?? '') === 'HTML') return $body;
            return nl2br(htmlspecialchars($body));
        }

        // Multipart — find HTML or plain text
        $htmlBody = '';
        $textBody = '';
        $this->walkParts($msgno, $structure->parts, '1', $htmlBody, $textBody);
        return $htmlBody ?: ($textBody ? nl2br(htmlspecialchars($textBody)) : '');
    }

    private function walkParts(int $msgno, array $parts, string $prefix, string &$html, string &$text): void
    {
        foreach ($parts as $i => $part) {
            $section = $prefix ? ($prefix . '.' . ($i + 1)) : (string)($i + 1);
            if ($prefix === '1') $section = (string)($i + 1);

            $mime = strtolower(($part->subtype ?? ''));
            $isAttachment = false;
            if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') $isAttachment = true;
            if (isset($part->parameters)) {
                foreach ($part->parameters as $p) {
                    if (strtolower($p->attribute) === 'name') $isAttachment = true;
                }
            }

            if (!$isAttachment && $part->type === 0) { // TEXT
                $body = imap_fetchbody($this->imap, $msgno, (string)($i + 1));
                $body = $this->decodePart($body, $part->encoding ?? 0);
                if ($mime === 'html') $html = $body;
                elseif ($mime === 'plain' && !$text) $text = $body;
            }

            if (isset($part->parts) && !empty($part->parts)) {
                $this->walkParts($msgno, $part->parts, (string)($i + 1), $html, $text);
            }
        }
    }

    private function getAttachmentsList(int $msgno, $structure): array
    {
        $attachments = [];
        $parts = $this->flattenParts($structure);
        foreach ($parts as $idx => $part) {
            if ($part['is_attachment']) {
                $attachments[] = [
                    'index' => $idx,
                    'filename' => $part['filename'],
                    'mime' => $part['mime'],
                    'size' => $part['size'] ?? 0,
                ];
            }
        }
        return $attachments;
    }

    private function flattenParts($structure, string $prefix = '', int &$index = 0): array
    {
        $parts = [];
        if (!isset($structure->parts) || empty($structure->parts)) return $parts;

        foreach ($structure->parts as $i => $part) {
            $section = $prefix ? ($prefix . '.' . ($i + 1)) : (string)($i + 1);
            $filename = '';
            $isAttachment = false;

            if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
                $isAttachment = true;
            }
            // Check dparameters for filename
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $p) {
                    if (strtolower($p->attribute) === 'filename') { $filename = $this->decodeMime($p->value); $isAttachment = true; }
                }
            }
            if (isset($part->parameters)) {
                foreach ($part->parameters as $p) {
                    if (strtolower($p->attribute) === 'name') { $filename = $this->decodeMime($p->value); if (!$isAttachment && $part->type !== 0) $isAttachment = true; }
                }
            }

            $types = ['text','multipart','message','application','audio','image','video','model','other'];
            $mime = ($types[$part->type] ?? 'application') . '/' . strtolower($part->subtype ?? 'octet-stream');

            $parts[$index] = [
                'section' => $section,
                'filename' => $filename,
                'mime' => $mime,
                'encoding' => $part->encoding ?? 0,
                'size' => $part->bytes ?? 0,
                'is_attachment' => $isAttachment,
            ];
            $index++;

            if (isset($part->parts)) {
                $sub = $this->flattenParts($part, $section, $index);
                $parts = $parts + $sub;
            }
        }
        return $parts;
    }

    private function decodePart(string $data, int $encoding): string
    {
        switch ($encoding) {
            case 0: return $data; // 7BIT
            case 1: return $data; // 8BIT
            case 2: return $data; // BINARY
            case 3: return base64_decode($data); // BASE64
            case 4: return quoted_printable_decode($data); // QUOTED-PRINTABLE
            default: return $data;
        }
    }
}
