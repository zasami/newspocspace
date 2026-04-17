<?php
/**
 * EmailTemplate — CMS des templates d'email automatiques
 *
 * Usage :
 *   $rendered = EmailTemplate::render('candidature_deleted', [
 *       'prenom' => 'Marie', 'nom' => 'Dupont', 'offre_titre' => '...', ...
 *   ]);
 *   $mailer->sendEmail([$to], [], $rendered['subject'], $rendered['html']);
 */

class EmailTemplate
{
    /**
     * Liste des templates disponibles avec leur définition par défaut.
     * Chaque template a :
     *   - name, description
     *   - variables : liste des {{variables}} disponibles
     *   - defaults : template par défaut (subject, blocks, etc.)
     */
    public static function getDefinitions(): array
    {
        return [
            'candidature_deleted' => [
                'name' => 'Suppression de candidature (nLPD)',
                'description' => "Envoyé au candidat quand sa candidature est supprimée — conformité Loi suisse sur la protection des données",
                'category' => 'Recrutement',
                'variables' => ['prenom', 'nom', 'email', 'offre_titre', 'code_suivi', 'date', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Suppression de votre dossier de candidature - {{ems_nom}}',
                    'header_color' => '#2d4a43',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Service Ressources Humaines',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Madame, Monsieur {{prenom}} {{nom}},"],
                        ['type' => 'paragraph', 'content' => "Nous vous informons que votre dossier de candidature pour le poste de « <strong>{{offre_titre}}</strong> » a été supprimé de nos systèmes en date du <strong>{{date}}</strong>."],
                        ['type' => 'highlight', 'title' => 'Conformité — Loi fédérale sur la protection des données (nLPD)', 'color' => '#2d4a43', 'bg' => '#f4f9f6', 'content' => "Conformément à la nouvelle Loi fédérale suisse sur la protection des données (nLPD, entrée en vigueur le 1er septembre 2023) et au principe de <em>minimisation des données</em>, vos données personnelles et documents transmis (CV, lettre de motivation, diplômes, etc.) ont été effacés de manière définitive de notre base."],
                        ['type' => 'paragraph', 'content' => "<strong>Ce que cela signifie concrètement :</strong>"],
                        ['type' => 'list', 'items' => [
                            "Toutes vos données personnelles ont été supprimées de nos serveurs",
                            "Les pièces jointes (CV, lettre, documents) ont été effacées du stockage",
                            "Aucune copie n'est conservée (code de suivi {{code_suivi}} désormais inactif)",
                        ]],
                        ['type' => 'paragraph', 'content' => "<strong>Vos droits :</strong> En vertu de la nLPD (art. 25-32), vous disposez d'un droit d'accès, de rectification et d'effacement de vos données."],
                        ['type' => 'paragraph', 'content' => "Nous vous remercions sincèrement de l'intérêt que vous avez porté à notre établissement et vous souhaitons plein succès dans la suite de vos démarches professionnelles."],
                        ['type' => 'signature', 'content' => "Avec nos meilleures salutations,\n<strong>Service Ressources Humaines</strong>\n{{ems_nom}}"],
                    ],
                    'footer_text' => "Cet email est une notification automatique envoyée conformément à l'art. 19 nLPD (devoir d'information).",
                ],
            ],
            'candidature_received' => [
                'name' => 'Accusé de réception candidature',
                'description' => "Envoyé au candidat après soumission de sa candidature",
                'category' => 'Recrutement',
                'variables' => ['prenom', 'nom', 'email', 'offre_titre', 'code_suivi', 'date', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Candidature bien reçue - {{ems_nom}}',
                    'header_color' => '#2d4a43',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Service Ressources Humaines',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}} {{nom}},"],
                        ['type' => 'paragraph', 'content' => "Nous avons bien reçu votre candidature pour le poste de <strong>{{offre_titre}}</strong>. Nous vous remercions pour l'intérêt que vous portez à notre établissement."],
                        ['type' => 'highlight', 'title' => 'Votre code de suivi', 'color' => '#3B4F6B', 'bg' => '#f0f4f8', 'content' => "Conservez précieusement ce code : <strong style=\"font-size:1.1rem\">{{code_suivi}}</strong><br>Il vous permettra de suivre l'avancement de votre candidature."],
                        ['type' => 'paragraph', 'content' => "Notre équipe va étudier attentivement votre dossier. Nous vous recontacterons dans les meilleurs délais (sous 2 à 3 semaines en moyenne)."],
                        ['type' => 'signature', 'content' => "Cordialement,\n<strong>Service Ressources Humaines</strong>\n{{ems_nom}}"],
                    ],
                    'footer_text' => "Vos données sont traitées conformément à la nLPD et ne seront utilisées que dans le cadre du processus de recrutement.",
                ],
            ],
            'candidature_interview' => [
                'name' => 'Invitation entretien',
                'description' => "Envoyé au candidat pour convenir d'un entretien",
                'category' => 'Recrutement',
                'variables' => ['prenom', 'nom', 'email', 'offre_titre', 'code_suivi', 'date', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Invitation à un entretien - {{offre_titre}}',
                    'header_color' => '#5B4B6B',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Invitation à un entretien',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}} {{nom}},"],
                        ['type' => 'paragraph', 'content' => "Suite à l'étude de votre candidature pour le poste de <strong>{{offre_titre}}</strong>, nous avons le plaisir de vous convier à un entretien."],
                        ['type' => 'paragraph', 'content' => "Nous vous recontacterons prochainement pour convenir d'une date et d'une heure qui vous conviennent."],
                        ['type' => 'signature', 'content' => "Cordialement,\n<strong>Service Ressources Humaines</strong>\n{{ems_nom}}"],
                    ],
                    'footer_text' => '',
                ],
            ],
            'candidature_accepted' => [
                'name' => 'Candidature acceptée',
                'description' => "Envoyé au candidat retenu",
                'category' => 'Recrutement',
                'variables' => ['prenom', 'nom', 'email', 'offre_titre', 'code_suivi', 'date', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Bonne nouvelle - {{offre_titre}}',
                    'header_color' => '#2d4a43',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Ressources Humaines',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}} {{nom}},"],
                        ['type' => 'highlight', 'title' => '🎉 Félicitations !', 'color' => '#2d4a43', 'bg' => '#f4f9f6', 'content' => "Nous avons le plaisir de retenir votre candidature pour le poste de <strong>{{offre_titre}}</strong>."],
                        ['type' => 'paragraph', 'content' => "Notre équipe vous contactera dans les prochains jours pour finaliser les démarches administratives et convenir de votre date d'entrée en fonction."],
                        ['type' => 'signature', 'content' => "Bienvenue dans l'équipe,\n<strong>Service Ressources Humaines</strong>\n{{ems_nom}}"],
                    ],
                    'footer_text' => '',
                ],
            ],
            'candidature_rejected' => [
                'name' => 'Candidature refusée',
                'description' => "Envoyé au candidat non retenu",
                'category' => 'Recrutement',
                'variables' => ['prenom', 'nom', 'email', 'offre_titre', 'code_suivi', 'date', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Suite à votre candidature - {{offre_titre}}',
                    'header_color' => '#6c757d',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Ressources Humaines',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}} {{nom}},"],
                        ['type' => 'paragraph', 'content' => "Nous vous remercions sincèrement pour l'intérêt que vous avez porté à notre établissement en postulant au poste de <strong>{{offre_titre}}</strong>."],
                        ['type' => 'paragraph', 'content' => "Après étude attentive de votre candidature, nous sommes au regret de ne pas pouvoir y donner une suite favorable. Votre profil a retenu notre attention mais nous avons choisi un candidat dont l'expérience correspond plus précisément à nos besoins actuels."],
                        ['type' => 'paragraph', 'content' => "Nous vous souhaitons plein succès dans la suite de vos démarches professionnelles."],
                        ['type' => 'signature', 'content' => "Cordialement,\n<strong>Service Ressources Humaines</strong>\n{{ems_nom}}"],
                    ],
                    'footer_text' => "Vos données seront conservées 6 mois avant suppression définitive conformément à la nLPD.",
                ],
            ],
            'user_invitation' => [
                'name' => 'Invitation collaborateur',
                'description' => "Envoyé au collaborateur lors de la création de son compte avec identifiants",
                'category' => 'Utilisateurs',
                'variables' => ['prenom', 'nom', 'email', 'password', 'url_login', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Bienvenue sur SpocSpace - {{ems_nom}}',
                    'header_color' => '#2d4a43',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Votre accès SpocSpace',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}} {{nom}},"],
                        ['type' => 'paragraph', 'content' => "Votre compte SpocSpace a été créé. Vous pouvez dès à présent vous connecter à la plateforme collaborateur."],
                        ['type' => 'highlight', 'title' => 'Vos identifiants', 'color' => '#2d4a43', 'bg' => '#f4f9f6', 'content' => "<strong>Email :</strong> {{email}}<br><strong>Mot de passe :</strong> {{password}}<br><br>Nous vous recommandons de changer votre mot de passe à la première connexion."],
                        ['type' => 'paragraph', 'content' => "Accès à la plateforme : <a href=\"{{url_login}}\">{{url_login}}</a>"],
                        ['type' => 'signature', 'content' => "Bienvenue,\n<strong>L'équipe {{ems_nom}}</strong>"],
                    ],
                    'footer_text' => '',
                ],
            ],
            'password_reset' => [
                'name' => 'Réinitialisation mot de passe',
                'description' => "Envoyé lors d'une demande de réinitialisation de mot de passe",
                'category' => 'Utilisateurs',
                'variables' => ['prenom', 'nom', 'email', 'reset_link', 'expires_hours', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Réinitialisation de votre mot de passe - {{ems_nom}}',
                    'header_color' => '#3B4F6B',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Sécurité du compte',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}},"],
                        ['type' => 'paragraph', 'content' => "Une demande de réinitialisation de votre mot de passe a été effectuée."],
                        ['type' => 'paragraph', 'content' => "Cliquez sur le lien ci-dessous pour définir un nouveau mot de passe :"],
                        ['type' => 'button', 'label' => 'Réinitialiser mon mot de passe', 'url' => '{{reset_link}}', 'color' => '#2d4a43'],
                        ['type' => 'paragraph', 'content' => "Ce lien est valable pendant <strong>{{expires_hours}} heures</strong>. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email."],
                        ['type' => 'signature', 'content' => "<strong>L'équipe {{ems_nom}}</strong>"],
                    ],
                    'footer_text' => '',
                ],
            ],
            'planning_notification' => [
                'name' => 'Notification planning',
                'description' => "Envoyé au collaborateur lors de la publication ou modification du planning",
                'category' => 'Planning',
                'variables' => ['prenom', 'nom', 'mois', 'annee', 'url_planning', 'ems_nom'],
                'defaults' => [
                    'subject' => 'Votre planning {{mois}} {{annee}} est disponible',
                    'header_color' => '#2d4a43',
                    'header_text_color' => '#ffffff',
                    'show_logo' => 1,
                    'header_title' => '{{ems_nom}}',
                    'header_subtitle' => 'Planning',
                    'blocks' => [
                        ['type' => 'paragraph', 'content' => "Bonjour {{prenom}},"],
                        ['type' => 'paragraph', 'content' => "Votre planning pour <strong>{{mois}} {{annee}}</strong> est désormais disponible dans SpocSpace."],
                        ['type' => 'button', 'label' => 'Consulter mon planning', 'url' => '{{url_planning}}', 'color' => '#2d4a43'],
                        ['type' => 'paragraph', 'content' => "N'hésitez pas à consulter la plateforme pour voir vos horaires, demander un changement ou soumettre un désir."],
                        ['type' => 'signature', 'content' => "<strong>L'équipe {{ems_nom}}</strong>"],
                    ],
                    'footer_text' => '',
                ],
            ],
        ];
    }

    /**
     * Récupère un template depuis la base (ou défaut si inexistant)
     */
    public static function getTemplate(string $key): array
    {
        $defs = self::getDefinitions();
        if (!isset($defs[$key])) {
            throw new \RuntimeException("Template email inconnu : $key");
        }

        $custom = Db::fetch("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1", [$key]);
        if ($custom) {
            $custom['blocks'] = json_decode($custom['blocks'] ?? '[]', true) ?: [];
            return $custom;
        }

        // Fallback défaut
        $d = $defs[$key]['defaults'];
        return [
            'template_key' => $key,
            'name' => $defs[$key]['name'],
            'subject' => $d['subject'],
            'header_color' => $d['header_color'],
            'header_text_color' => $d['header_text_color'],
            'show_logo' => $d['show_logo'],
            'header_title' => $d['header_title'],
            'header_subtitle' => $d['header_subtitle'],
            'blocks' => $d['blocks'],
            'footer_text' => $d['footer_text'],
            'is_active' => 1,
        ];
    }

    /**
     * Remplace les variables {{var}} par leurs valeurs.
     * Les valeurs sont HTML-escapées par défaut (les templates sont servis en
     * HTML email). Les variables explicitement destinées à contenir des URLs
     * (reset_link, url_*, etc.) passent par la même échappe — acceptable car
     * l'href environnant est lui-même échappé.
     */
    public static function interpolate(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($m) use ($vars) {
            if (!isset($vars[$m[1]])) return $m[0];
            $val = (string) $vars[$m[1]];
            return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }, $text);
    }

    /**
     * Rend un template en HTML email complet
     * Retourne : ['subject' => '...', 'html' => '...']
     */
    public static function render(string $key, array $vars = []): array
    {
        $tpl = self::getTemplate($key);

        // Merge vars with defaults (ems_nom, ems_logo_url auto-fetched)
        if (!isset($vars['ems_nom'])) {
            $vars['ems_nom'] = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS La Terrassière';
        }
        if (!isset($vars['date'])) $vars['date'] = date('d.m.Y');

        $logoUrl = '';
        if ($tpl['show_logo']) {
            $emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
            if ($emsLogo) {
                // Email clients (Outlook, Apple Mail older) don't support WebP → prefer PNG
                $pngCandidate = preg_replace('/\.webp$/i', '.png', $emsLogo);
                $pngPath = __DIR__ . '/..' . $pngCandidate;
                if ($pngCandidate !== $emsLogo && file_exists($pngPath)) {
                    $emsLogo = $pngCandidate;
                }
                $host = $_SERVER['HTTP_HOST'] ?? 'www.zkriva.com';
                $logoUrl = 'https://' . $host . $emsLogo;
            }
        }

        $subject = self::interpolate($tpl['subject'], $vars);
        $headerTitle = self::interpolate($tpl['header_title'] ?? '', $vars);
        $headerSubtitle = self::interpolate($tpl['header_subtitle'] ?? '', $vars);
        $headerColor = $tpl['header_color'] ?: '#2d4a43';
        $headerTextColor = $tpl['header_text_color'] ?: '#ffffff';

        // Build blocks HTML
        $blocksHtml = '';
        foreach ($tpl['blocks'] as $block) {
            $blocksHtml .= self::renderBlock($block, $vars);
        }

        $footer = self::interpolate($tpl['footer_text'] ?? '', $vars);

        $logoHtml = $logoUrl
            ? '<td style="padding-right:14px;vertical-align:middle"><img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" style="width:52px;height:52px;border-radius:10px;background:#fff;padding:4px;object-fit:contain;display:block"></td>'
            : '';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;max-width:620px;margin:0 auto;padding:20px;background:#f7f5f2">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:' . htmlspecialchars($headerColor) . ';border-radius:10px 10px 0 0">
        <tr>
            <td style="padding:20px 24px">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        ' . $logoHtml . '
                        <td style="vertical-align:middle;color:' . htmlspecialchars($headerTextColor) . '">
                            <h2 style="margin:0;font-size:18px;color:' . htmlspecialchars($headerTextColor) . ';font-family:Arial,sans-serif">' . $headerTitle . '</h2>
                            ' . ($headerSubtitle ? '<p style="margin:4px 0 0;font-size:13px;color:' . htmlspecialchars($headerTextColor) . ';opacity:.85;font-family:Arial,sans-serif">' . $headerSubtitle . '</p>' : '') . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div style="background:#fff;border:1px solid #e9ecef;border-top:none;padding:24px;border-radius:0 0 10px 10px">
        ' . $blocksHtml . '
        ' . ($footer ? '<hr style="border:none;border-top:1px solid #e9ecef;margin:20px 0"><p style="font-size:11px;color:#999;margin:0">' . $footer . '</p>' : '') . '
    </div>
</body></html>';

        return ['subject' => $subject, 'html' => $html];
    }

    private static function renderBlock(array $block, array $vars): string
    {
        $type = $block['type'] ?? 'paragraph';
        switch ($type) {
            case 'paragraph':
                $content = self::interpolate($block['content'] ?? '', $vars);
                return '<p style="margin:0 0 14px">' . $content . '</p>';

            case 'highlight':
                $title = self::interpolate($block['title'] ?? '', $vars);
                $content = self::interpolate($block['content'] ?? '', $vars);
                $color = $block['color'] ?? '#2d4a43';
                $bg = $block['bg'] ?? '#f4f9f6';
                return '<div style="background:' . htmlspecialchars($bg) . ';border-left:3px solid ' . htmlspecialchars($color) . ';padding:12px 16px;border-radius:4px;margin:16px 0">
                    ' . ($title ? '<strong>' . $title . '</strong><br>' : '') . $content . '
                </div>';

            case 'list':
                $items = $block['items'] ?? [];
                $lis = '';
                foreach ($items as $item) {
                    $lis .= '<li>' . self::interpolate($item, $vars) . '</li>';
                }
                return '<ul style="padding-left:20px;margin:0 0 14px">' . $lis . '</ul>';

            case 'button':
                $label = self::interpolate($block['label'] ?? 'Cliquez ici', $vars);
                $url = self::interpolate($block['url'] ?? '#', $vars);
                $color = $block['color'] ?? '#2d4a43';
                return '<div style="text-align:center;margin:20px 0">
                    <a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:' . htmlspecialchars($color) . ';color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">' . $label . '</a>
                </div>';

            case 'signature':
                $content = self::interpolate($block['content'] ?? '', $vars);
                $lines = explode("\n", $content);
                return '<p style="margin:24px 0 0">' . implode('<br>', $lines) . '</p>';

            case 'divider':
                return '<hr style="border:none;border-top:1px solid #e9ecef;margin:20px 0">';

            case 'image':
                $url = self::interpolate($block['url'] ?? '', $vars);
                $alt = $block['alt'] ?? '';
                return '<div style="text-align:center;margin:16px 0"><img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" style="max-width:100%;border-radius:8px"></div>';

            default:
                return '';
        }
    }

    /**
     * Envoi via la config email externe de l'admin donné.
     * Retourne true si envoyé, false sinon (ne lève pas d'exception).
     */
    public static function send(string $key, string $toEmail, array $vars, ?string $adminUserId = null): bool
    {
        try {
            require_once __DIR__ . '/Mailer.php';
            $adminId = $adminUserId ?: ($_SESSION['ss_user']['id'] ?? null);
            if (!$adminId) return false;

            $config = Db::fetch("SELECT * FROM email_externe_config WHERE user_id = ? AND is_active = 1", [$adminId]);
            if (!$config) {
                // Fallback: n'importe quelle config active
                $config = Db::fetch("SELECT * FROM email_externe_config WHERE is_active = 1 LIMIT 1");
                if (!$config) return false;
            }

            $password = Mailer::decryptPassword($config['encrypted_password'], $config['password_iv']);
            $mailer = new Mailer([
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

            $rendered = self::render($key, $vars);
            $mailer->sendEmail([$toEmail], [], $rendered['subject'], $rendered['html']);
            return true;
        } catch (\Throwable $e) {
            error_log('[EmailTemplate::send] ' . $key . ' - ' . $e->getMessage());
            return false;
        }
    }
}
