<?php

declare(strict_types=1);

namespace Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOSONAR — php:S1448 : seams de testabilité (newOrderFormModel/newMailService), pas de logique métier
class MailService
{
    private const PATH_SECURITY     = '/mon-compte/securite';
    private const BTN_STYLE_PRIMARY = 'font-family:Georgia,serif;font-size:14px;'
        . 'letter-spacing:2px;text-transform:uppercase;';
    private const BTN_STYLE_LINK    = 'color:#1a1208;text-decoration:none;font-weight:bold;">';
    private const LOGO_PATH   = '/assets/images/logo/crabitan-bellevue-logo-modern.svg';
    private const URL_PRIVACY = '/fr/politique-confidentialite';
    private const URL_LEGAL   = '/fr/mentions-legales';
    private const URL_SUPPORT = '/fr/support';

    private PHPMailer $mailer;

    public function __construct()
    {
        $mailUser = $_ENV['MAIL_USER'] ?? '';
        $mailPass = $_ENV['MAIL_PASS'] ?? '';
        $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host    = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->mailer->Port    = (int) ($_ENV['MAIL_PORT'] ?? 25);
        $this->mailer->CharSet = 'UTF-8';

        if ($mailUser !== '') {
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $mailUser;
            $this->mailer->Password   = $mailPass;
            $this->mailer->SMTPSecure = $encryption !== '' ? $encryption : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPAuth   = false;
            $this->mailer->SMTPSecure = '';
        }

        $appUrl      = $_ENV['APP_URL'] ?? (defined('APP_URL') ? APP_URL : 'http://localhost');
        $host        = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $fromAddress = $mailUser !== ''
            ? $mailUser
            : ($_ENV['MAIL_FROM'] ?? ('noreply@' . $host));
        $this->mailer->setFrom($fromAddress, $_ENV['MAIL_FROM_NAME'] ?? 'Crabitan Bellevue');
    }

    public function sendEmailVerification(string $to, string $name, string $verifyUrl, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Activez votre compte Crabitan Bellevue'
            : 'Activate your Crabitan Bellevue account';

        $body = $lang === 'fr'
            ? $this->verificationBodyFr($name, $verifyUrl)
            : $this->verificationBodyEn($name, $verifyUrl);

        $this->send($to, $name, $subject, $body);
    }

    public function sendPasswordReset(string $to, string $name, string $resetUrl, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Réinitialisation de votre mot de passe'
            : 'Reset your password';

        $body = $lang === 'fr'
            ? $this->resetBodyFr($name, $resetUrl)
            : $this->resetBodyEn($name, $resetUrl);

        $this->send($to, $name, $subject, $body);
    }

    public function sendContactToOwner(
        string $firstname,
        string $lastname,
        string $email,
        string $subject,
        string $message,
        string $lang
    ): void {
        $ownerEmail = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'];

        $subjectLabel = $this->resolveSubjectLabel($subject, $lang);
        $subjectLine  = 'Contact site : ' . $subjectLabel;

        $safeName    = htmlspecialchars($firstname . ' ' . $lastname, ENT_QUOTES);
        $safeEmail   = htmlspecialchars($email, ENT_QUOTES);
        $safeSubject = htmlspecialchars($subjectLabel, ENT_QUOTES);
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES));

        $msgBody = "<strong>De :</strong> {$safeName} ({$safeEmail})<br>"
            . "<strong>Objet :</strong> {$safeSubject}<br><br>"
            . $safeMessage;

        $body = $this->emailSimpleLayout(
            'Nouveau message',
            "Message reçu via le site",
            $msgBody,
            'fr'
        );

        $this->send($ownerEmail, APP_NAME, $subjectLine, $body, null, $email);
    }

    public function sendContactConfirmation(
        string $to,
        string $firstname,
        string $subject,
        string $lang,
        string $userMessage = ''
    ): void {
        $safeName    = htmlspecialchars($firstname, ENT_QUOTES);
        $isOrderForm = $subject === 'bon_commande';
        $subjectLabel = htmlspecialchars($this->resolveSubjectLabel($subject, $lang), ENT_QUOTES);

        $lines = $this->buildConfirmationLines($isOrderForm, $lang, $subjectLabel);
        $recap = $isOrderForm ? '' : $this->buildRecapBlock($userMessage, $lang);

        $body = $this->emailSimpleLayout(
            'Confirmation',
            $lang === 'fr' ? "Bonjour {$safeName}," : "Hello {$safeName},",
            $lines['message'] . $recap,
            $lang
        );

        $attachmentPath = null;
        if ($isOrderForm) {
            $latest = $this->newOrderFormModel()->getLatest();
            if ($latest !== null) {
                $path = ROOT_PATH . '/storage/order_forms/' . $latest['filename'];
                if (file_exists($path)) {
                    $attachmentPath = $path;
                }
            }
        }

        $this->send($to, $firstname, $lines['subjectLine'], $body, $attachmentPath);
    }

    public function sendNewsletter(
        string $to,
        string $name,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath = null,
        ?string $attachmentName = null
    ): void {
        $this->send($to, $name, $subject, $htmlBody, $attachmentPath, null, $attachmentName);
    }

    /**
     * Envoie une newsletter "nouveau vin disponible" à un abonné.
     *
     * @param string              $toEmail    Adresse email du destinataire
     * @param string              $toName     Nom affiché du destinataire
     * @param string              $unsubToken Token de désabonnement
     * @param array<string,mixed> $wine       Données du vin (label_name, vintage, certification_label,
     *                                        is_cuvee_speciale, award, image_path, slug)
     * @param string              $appUrl     URL de base de l'application
     * @param string              $lang       Langue du destinataire ('fr' ou 'en')
     * @return void
     */
    public function sendNewWineNewsletter(
        string $toEmail,
        string $toName,
        string $unsubToken,
        array $wine,
        string $appUrl,
        string $lang
    ): void {
        $appUrl      = rtrim($appUrl, '/');
        $labelName   = htmlspecialchars((string) ($wine['label_name'] ?? ''), ENT_QUOTES);
        $appellation = htmlspecialchars((string) ($wine['certification_label'] ?? ''), ENT_QUOTES);
        $vintage     = (int) ($wine['vintage'] ?? 0);
        $slug        = rawurlencode((string) ($wine['slug'] ?? ''));
        $imagePath   = (string) ($wine['image_path'] ?? '');
        $isCuvee     = (bool) ($wine['is_cuvee_speciale'] ?? false);

        // Champ award : JSON bilingue {"fr":"...","en":"..."}
        $awardText = $this->resolveAwardText($wine['award'] ?? null, $lang);

        if ($lang === 'fr') {
            $subject      = sprintf(
                'Château Crabitan Bellevue · %s %d — Nouveau millésime disponible',
                $wine['label_name'] ?? '',
                $vintage
            );
            $wineUrl      = htmlspecialchars($appUrl . '/fr/vins/' . $slug, ENT_QUOTES);
            $discoverBtn  = 'Découvrir ce vin';
            $greeting     = "Cher(e) {$toName},";
            $intro        = "Le Château Crabitan Bellevue est heureux de vous informer,"
                . " en avant-première, de la mise en vente d'une nouvelle référence dans notre boutique.";
            $labelLabel   = 'Appellation';
            $vintageLabel = 'Millésime';
            $cuveeLabel   = 'Cuvée Spéciale';
            $awardLabel   = 'Récompense';
            $emailTitle   = "Nouveauté · Millésime {$vintage}";
        } else {
            $subject      = sprintf(
                'Château Crabitan Bellevue · %s %d — New vintage available',
                $wine['label_name'] ?? '',
                $vintage
            );
            $wineUrl      = htmlspecialchars($appUrl . '/en/wines/' . $slug, ENT_QUOTES);
            $discoverBtn  = 'Discover this wine';
            $greeting     = "Dear {$toName},";
            $intro        = 'Château Crabitan Bellevue is pleased to inform you,'
                . ' as one of our valued subscribers, of the availability of a new wine in our boutique.';
            $labelLabel   = 'Appellation';
            $vintageLabel = 'Vintage';
            $cuveeLabel   = 'Special Cuvée';
            $awardLabel   = 'Award';
            $emailTitle   = "New arrival · Vintage {$vintage}";
        }

        $imageHtml = '';
        if ($imagePath !== '') {
            $safeImg   = htmlspecialchars($appUrl . '/assets/images/wines/' . $imagePath, ENT_QUOTES);
            $imageHtml = "<img src=\"{$safeImg}\" alt=\"{$labelName} {$vintage}\""
                . " width=\"192\" style=\"display:block;width:192px;max-width:100%;"
                . "height:auto;margin:24px auto 16px;border:0;border-radius:4px;\">";
        }

        // Lignes du tableau : appellation + millésime + récompense (si applicable)
        // La cuvée spéciale apparaît en sous-titre sous le nom du vin, pas dans le tableau
        $infoRows = [[$labelLabel, $appellation], [$vintageLabel, (string) $vintage]];
        if ($awardText !== '') {
            $infoRows[] = [$awardLabel, $awardText];
        }

        $infoTable = '<table style="width:100%;border-collapse:collapse;margin:16px 0 24px;">';
        foreach ($infoRows as $i => [$rowLabel, $rowValue]) {
            $bg     = $i % 2 === 0 ? 'background:#f5f0e8;' : '';
            $border = $i > 0 ? 'border-top:1px solid #ede8df;' : '';
            $infoTable .= "<tr style=\"{$bg}\">"
                . "<td style=\"padding:8px 12px;font-size:12px;letter-spacing:1px;"
                . "text-transform:uppercase;color:#8a7a60;width:40%;{$border}\">{$rowLabel}</td>"
                . "<td style=\"padding:8px 12px;font-size:14px;color:#3d3425;{$border}\">{$rowValue}</td>"
                . "</tr>";
        }
        $infoTable .= '</table>';

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$wineUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . $discoverBtn
            . '</a></td></tr></table>';

        $safeGreeting = htmlspecialchars($greeting, ENT_QUOTES);
        $htmlContent  = "<p style=\"margin:0 0 24px;font-size:22px;color:#1a1208;font-family:Georgia,serif;\">"
            . $safeGreeting
            . "</p>"
            . "<p style=\"margin:0 0 16px;font-size:15px;line-height:1.7;color:#3d3425;\">"
            . $intro
            . "</p>"
            . "<h2 style=\"margin:0 0 4px;font-family:Georgia,serif;font-size:20px;color:#1a1208;\">"
            . "{$labelName} {$vintage}"
            . "</h2>"
            . ($isCuvee
                ? "<p style=\"margin:0 0 12px;font-size:13px;letter-spacing:2px;text-transform:uppercase;"
                  . "color:#c9a84c;font-family:Georgia,serif;\">{$cuveeLabel}</p>"
                : '')
            . $imageHtml
            . $infoTable
            . $ctaBtn;

        $htmlBody = $this->buildNewsletterHtml($emailTitle, $htmlContent, null, $unsubToken, $lang);

        $this->send($toEmail, $toName, $subject, $htmlBody);
    }

    public function sendAccountDeletionConfirmation(
        string $to,
        string $name,
        string $lang,
        string $reactivationToken = ''
    ): void {
        $subject = $lang === 'fr'
            ? 'Confirmation de suppression de votre compte'
            : 'Account deletion confirmation';

        $safeName  = htmlspecialchars($name, ENT_QUOTES);
        $appUrl    = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $reactUrl  = $reactivationToken !== ''
            ? $appUrl . '/' . $lang . '/compte/reactiver?token=' . urlencode($reactivationToken)
            : '';
        $safeReactUrl = htmlspecialchars($reactUrl, ENT_QUOTES);

        if ($lang === 'fr') {
            $rgpdText = 'Votre demande de suppression de compte a bien été enregistrée.'
                . ' Conformément au RGPD (Art. 17), vos données personnelles (nom, e-mail, adresses…)'
                . ' seront supprimées dans un délai de <strong>30 jours</strong>.<br><br>'
                . 'Vos commandes sont conservées 10 ans conformément aux obligations légales,'
                . ' mais anonymisées — sans aucun lien vers votre identité.<br><br>';
            $reactivateBlock = $reactUrl !== ''
                ? 'Vous avez 30 jours pour annuler cette demande en cliquant sur le bouton ci-dessous.'
                  . ' Passé ce délai, vos données personnelles seront définitivement supprimées.'
                  . '<br><br>'
                  . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
                  . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                  . '<a href="' . $safeReactUrl . '" style="display:inline-block;padding:14px 36px;'
                  . self::BTN_STYLE_PRIMARY
                  . 'color:#1a1208;text-decoration:none;font-weight:bold;">Annuler la suppression</a>'
                  . '</td></tr></table>'
                : '';
            $body = $this->emailSimpleLayout(
                'Suppression de compte',
                "Bonjour {$safeName},",
                $rgpdText . $reactivateBlock,
                'fr'
            );
        } else {
            $rgpdText = 'Your account deletion request has been registered.'
                . ' In accordance with GDPR (Art. 17), your personal data (name, email, addresses…)'
                . ' will be permanently deleted within <strong>30 days</strong>.<br><br>'
                . 'Your orders are retained for 10 years as required by law,'
                . ' but anonymised — with no link to your identity.<br><br>';
            $reactivateBlock = $reactUrl !== ''
                ? 'You have 30 days to cancel this request by clicking the button below.'
                  . ' After this period, your personal data will be permanently deleted.'
                  . '<br><br>'
                  . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
                  . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                  . '<a href="' . $safeReactUrl . '" style="display:inline-block;padding:14px 36px;'
                  . self::BTN_STYLE_PRIMARY
                  . 'color:#1a1208;text-decoration:none;font-weight:bold;">Cancel deletion</a>'
                  . '</td></tr></table>'
                : '';
            $body = $this->emailSimpleLayout(
                'Account deletion',
                "Hello {$safeName},",
                $rgpdText . $reactivateBlock,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie un email de notification de sécurité après un changement de mot de passe.
     *
     * @param string $to   Adresse email du destinataire
     * @param string $name Prénom ou nom complet du destinataire
     * @param string $lang Langue du destinataire ('fr' ou 'en')
     * @return void
     */
    public function sendPasswordChangedAlert(string $to, string $name, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Modification de votre mot de passe'
            : 'Your password has been changed';

        $appUrl      = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $securityUrl = htmlspecialchars($appUrl . '/' . $lang . self::PATH_SECURITY, ENT_QUOTES);
        $safeName    = htmlspecialchars($name, ENT_QUOTES);
        $safeDate    = htmlspecialchars(date('d/m/Y à H:i'), ENT_QUOTES);
        $contactUrl  = htmlspecialchars($appUrl . '/' . $lang . '/contact', ENT_QUOTES);

        $manageLabel = $lang === 'fr' ? 'Gérer la sécurité de mon compte' : 'Manage my account security';

        $ctaBlock = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$securityUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . $manageLabel
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $message = "Votre mot de passe a été modifié le <strong>{$safeDate}</strong>.<br><br>"
                . 'Si vous êtes à l\'origine de cette modification, vous pouvez ignorer cet email.<br><br>'
                . 'Si vous n\'avez pas effectué cette modification, votre compte est peut-être compromis.'
                . ' Veuillez <a href="' . $contactUrl . '" style="color:#c9a84c;">contacter notre support</a>'
                . ' immédiatement.<br><br>'
                . $ctaBlock;
            $body = $this->emailSimpleLayout('Sécurité du compte', "Bonjour {$safeName},", $message, 'fr');
        } else {
            $message = "Your password was changed on <strong>{$safeDate}</strong>.<br><br>"
                . 'If you made this change, you can safely ignore this email.<br><br>'
                . 'If you did not make this change, your account may be compromised.'
                . ' Please <a href="' . $contactUrl . '" style="color:#c9a84c;">contact our support team</a>'
                . ' immediately.<br><br>'
                . $ctaBlock;
            $body = $this->emailSimpleLayout('Account security', "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    public function sendNewDeviceAlert(
        string $to,
        string $name,
        string $deviceName,
        ?string $ipAddress,
        string $lang,
        string $deviceToken = ''
    ): void {
        $subject = $lang === 'fr'
            ? 'Sécurité — Nouvelle connexion depuis un appareil inconnu'
            : 'Security — New sign-in from an unrecognised device';

        $appUrl      = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $confirmUrl  = $deviceToken !== ''
            ? htmlspecialchars(
                $appUrl . '/' . $lang . '/mon-compte/appareil/confirmer?token=' . urlencode($deviceToken),
                ENT_QUOTES
            )
            : '';
        $revokeUrl   = $deviceToken !== ''
            ? htmlspecialchars(
                $appUrl . '/' . $lang . '/mon-compte/appareil/annuler?token=' . urlencode($deviceToken),
                ENT_QUOTES
            )
            : htmlspecialchars($appUrl . '/' . $lang . self::PATH_SECURITY, ENT_QUOTES);
        $securityUrl = htmlspecialchars($appUrl . '/' . $lang . self::PATH_SECURITY, ENT_QUOTES);
        $safeName    = htmlspecialchars($name, ENT_QUOTES);
        $safeDevice  = htmlspecialchars($deviceName, ENT_QUOTES);
        $safeIp      = htmlspecialchars($ipAddress ?? 'N/A', ENT_QUOTES);
        $safeDate    = htmlspecialchars(date('d/m/Y à H:i'), ENT_QUOTES);

        $infoBlock = '<table role="presentation" cellpadding="0" cellspacing="0"'
            . ' style="width:100%;margin-bottom:24px;">'
            . '<tr><td style="padding:12px 16px;background:#f5f0e8;border-left:3px solid #c9a84c;">'
            . '<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8a7a60;">'
            . ($lang === 'fr' ? 'Appareil' : 'Device') . '</p>'
            . "<p style=\"margin:0;font-size:14px;color:#3d3425;\">{$safeDevice}</p>"
            . '</td></tr>'
            . '<tr><td style="padding:12px 16px;background:#f5f0e8;'
            . 'border-left:3px solid #c9a84c;border-top:1px solid #ede8df;">'
            . '<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8a7a60;">'
            . ($lang === 'fr' ? 'Adresse IP' : 'IP address') . '</p>'
            . "<p style=\"margin:0;font-size:14px;color:#3d3425;\">{$safeIp}</p>"
            . '</td></tr>'
            . '<tr><td style="padding:12px 16px;background:#f5f0e8;'
            . 'border-left:3px solid #c9a84c;border-top:1px solid #ede8df;">'
            . '<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;'
            . 'text-transform:uppercase;color:#8a7a60;">Date</p>'
            . "<p style=\"margin:0;font-size:14px;color:#3d3425;\">{$safeDate}</p>"
            . '</td></tr>'
            . '</table>';

        $manageLabel   = $lang === 'fr' ? 'Gérer mes sessions' : 'Manage my sessions';
        $confirmLabel  = $lang === 'fr' ? 'Confirmer cet appareil' : 'Confirm this device';
        $cancelLabel   = $lang === 'fr'
            ? 'Ce n\'était pas moi — Annuler cette tentative'
            : 'This wasn\'t me — Cancel this attempt';
        $confirmBlock  = $confirmUrl !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
              . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
              . "<a href=\"{$confirmUrl}\" style=\"display:inline-block;padding:14px 36px;"
              . self::BTN_STYLE_PRIMARY
              . self::BTN_STYLE_LINK
              . $confirmLabel
              . '</a></td></tr></table>'
              . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
              . '<tr><td>'
              . "<a href=\"{$revokeUrl}\" style=\"display:inline-block;padding:10px 24px;"
              . 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;'
              . 'color:#c0392b;text-decoration:underline;">'
              . $cancelLabel
              . '</a></td></tr></table>'
            : '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
              . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
              . "<a href=\"{$securityUrl}\" style=\"display:inline-block;padding:14px 36px;"
              . self::BTN_STYLE_PRIMARY
              . self::BTN_STYLE_LINK
              . $manageLabel
              . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $message = 'Une connexion depuis un appareil inconnu a été détectée sur votre compte.'
                . ' Si c\'était bien vous, confirmez cet appareil pour ne plus recevoir d\'alerte.'
                . '<br><br>'
                . $infoBlock
                . $confirmBlock;
            $body = $this->emailSimpleLayout('Sécurité du compte', "Bonjour {$safeName},", $message, 'fr');
        } else {
            $message = 'A sign-in from an unrecognised device was detected on your account.'
                . ' If this was you, confirm the device to stop receiving these alerts.'
                . '<br><br>'
                . $infoBlock
                . $confirmBlock;
            $body = $this->emailSimpleLayout('Account security', "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    public function buildNewsletterHtml(
        string $title,
        string $htmlContent,
        ?string $imageUrl = null,
        ?string $unsubToken = null,
        string $lang = 'fr'
    ): string {
        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;
        $urlUnsub   = $unsubToken !== null
            ? $appUrl . '/fr/newsletter/desabonnement?token=' . urlencode($unsubToken)
            : $appUrl . '/fr/mon-compte';
        $safeTitle  = htmlspecialchars($title, ENT_QUOTES);
        // Image optionnelle : centrée dans le bloc blanc, avant le séparateur/désabonnement
        $safeImage = $imageUrl !== null ? htmlspecialchars($imageUrl, ENT_QUOTES) : null;
        $imageHtml = $safeImage !== null
            ? "<img src=\"{$safeImage}\" alt=\"\" width=\"192\""
              . " style=\"display:block;width:192px;max-width:100%;height:auto;"
              . "margin:24px auto 0;border:0;border-radius:4px;\">"
            : '';

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $lang);

        $inner = <<<INNER

          <!-- Header logo -->
          {$headerHtml}

          <!-- Body -->
          <tr>
            <td style="background-color:#ffffff;border:1px solid #ddd5c4;padding:40px 36px;">
              <p style="margin:0 0 8px;font-size:11px;letter-spacing:3px;
                 text-transform:uppercase;color:#8a7a60;">{$safeTitle}</p>
              <div style="font-size:15px;line-height:1.7;color:#3d3425;margin-top:16px;">
                {$htmlContent}
              </div>
              {$imageHtml}
              <!-- Divider -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px;">
                <tr><td style="border-top:1px solid #ede8df;padding-bottom:20px;"></td></tr>
              </table>
              <p style="margin:0;font-size:12px;color:#8a7a60;line-height:1.6;">
                Vous recevez cet email car vous êtes abonné(e) à la newsletter du Château Crabitan Bellevue.<br>
                <a href="{$urlUnsub}" style="color:#c9a84c;">Se désinscrire de la newsletter</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          {$footerHtml}

INNER;

        return $this->emailWrap($safeTitle, $inner);
    }

    /**
     * Envoie un email transactionnel au client lors d'un changement de statut de commande.
     * N'envoie rien si le statut n'est pas dans la liste des déclencheurs.
     *
     * @param string $toEmail   Adresse email du client
     * @param string $toName    Prénom ou nom complet du client
     * @param string $orderRef  Référence de la commande (ex. CB-2026-001)
     * @param string $newStatus Nouveau statut de la commande
     * @param string $lang      Langue du client ('fr' ou 'en')
     * @param string $appUrl    URL de base de l'application
     * @return void
     */
    public function sendOrderStatusEmail(
        string $toEmail,
        string $toName,
        string $orderRef,
        string $newStatus,
        string $lang,
        string $appUrl
    ): void {
        $triggerStatuses = ['processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($newStatus, $triggerStatuses, true)) {
            return;
        }

        $safeRef  = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeName = htmlspecialchars($toName, ENT_QUOTES);
        $safeUrl  = rtrim($appUrl, '/');

        if ($lang === 'fr') {
            [$subject, $title, $greeting, $message] = $this->buildOrderStatusContentFr(
                $newStatus,
                $safeRef,
                $safeName,
                $safeUrl
            );
        } else {
            [$subject, $title, $greeting, $message] = $this->buildOrderStatusContentEn(
                $newStatus,
                $safeRef,
                $safeName,
                $safeUrl
            );
        }

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($toEmail, $toName, $subject, $body);
    }

    /**
     * Extrait et échappe le texte de récompense depuis le champ award JSON bilingue.
     *
     * @param  mixed  $awardRaw Valeur brute du champ award (string JSON, array ou null)
     * @param  string $lang     Langue cible ('fr' ou 'en')
     * @return string           Texte de récompense échappé, vide si absent
     */
    private function resolveAwardText(mixed $awardRaw, string $lang): string
    {
        $awardData = [];
        if ($awardRaw !== null && $awardRaw !== '' && $awardRaw !== '[]') {
            $decoded   = is_array($awardRaw) ? $awardRaw : (json_decode((string) $awardRaw, true) ?? []);
            $awardData = is_array($decoded) ? $decoded : [];
        }
        return htmlspecialchars(trim((string) ($awardData[$lang] ?? '')), ENT_QUOTES);
    }

    /**
     * Construit le contenu de l'email de statut commande en français.
     *
     * @param  string $status   Statut déclencheur
     * @param  string $safeRef  Référence commande échappée
     * @param  string $safeName Nom du client échappé
     * @param  string $appUrl   URL de base
     * @return array{string, string, string, string}  [subject, title, greeting, message]
     */
    private function buildOrderStatusContentFr(
        string $status,
        string $safeRef,
        string $safeName,
        string $appUrl
    ): array {
        $subjects = [
            'processing' => "Votre commande est en préparation — {$safeRef}",
            'shipped'    => "Votre commande a été expédiée — {$safeRef}",
            'delivered'  => "Votre commande a été livrée — {$safeRef}",
            'cancelled'  => "Votre commande a été annulée — {$safeRef}",
            'refunded'   => "Votre commande a été remboursée — {$safeRef}",
        ];
        $titles = [
            'processing' => 'Commande en préparation',
            'shipped'    => 'Commande expédiée',
            'delivered'  => 'Commande livrée',
            'cancelled'  => 'Commande annulée',
            'refunded'   => 'Commande remboursée',
        ];
        $messages = [
            'processing' => "Votre commande <strong>{$safeRef}</strong> est actuellement en cours de préparation."
                . " Nous mettons tout en œuvre pour vous l'expédier dans les meilleurs délais.",
            'shipped'    => "Votre commande <strong>{$safeRef}</strong> a été expédiée."
                . " Vous devriez la recevoir dans les prochains jours ouvrés.",
            'delivered'  => "Votre commande <strong>{$safeRef}</strong> a bien été livrée."
                . " Nous espérons que vous apprécierez nos vins.<br><br>"
                . "Si vous rencontrez un problème, vous avez 15 jours pour faire une demande de retour"
                . " depuis votre espace client.",
            'cancelled'  => "Votre commande <strong>{$safeRef}</strong> a été annulée."
                . " Si vous avez déjà été débité(e), le remboursement sera traité dans les meilleurs délais.",
            'refunded'   => "Votre commande <strong>{$safeRef}</strong> a été remboursée."
                . " Le crédit sera visible sur votre compte bancaire dans un délai de 3 à 5 jours ouvrés.",
        ];

        $orderLinkBlock = "<br><br><a href=\"{$appUrl}/fr/mon-compte/commandes\" style=\"color:#c9a84c;\">"
            . "Voir mes commandes</a>";

        return [
            $subjects[$status],
            $titles[$status],
            "Bonjour {$safeName},",
            $messages[$status] . $orderLinkBlock,
        ];
    }

    /**
     * Construit le contenu de l'email de statut commande en anglais.
     *
     * @param  string $status   Statut déclencheur
     * @param  string $safeRef  Référence commande échappée
     * @param  string $safeName Nom du client échappé
     * @param  string $appUrl   URL de base
     * @return array{string, string, string, string}  [subject, title, greeting, message]
     */
    private function buildOrderStatusContentEn(
        string $status,
        string $safeRef,
        string $safeName,
        string $appUrl
    ): array {
        $subjects = [
            'processing' => "Your order is being prepared — {$safeRef}",
            'shipped'    => "Your order has been shipped — {$safeRef}",
            'delivered'  => "Your order has been delivered — {$safeRef}",
            'cancelled'  => "Your order has been cancelled — {$safeRef}",
            'refunded'   => "Your order has been refunded — {$safeRef}",
        ];
        $titles = [
            'processing' => 'Order in preparation',
            'shipped'    => 'Order shipped',
            'delivered'  => 'Order delivered',
            'cancelled'  => 'Order cancelled',
            'refunded'   => 'Order refunded',
        ];
        $messages = [
            'processing' => "Your order <strong>{$safeRef}</strong> is currently being prepared."
                . " We are doing our best to ship it to you as soon as possible.",
            'shipped'    => "Your order <strong>{$safeRef}</strong> has been shipped."
                . " You should receive it within the next few working days.",
            'delivered'  => "Your order <strong>{$safeRef}</strong> has been delivered."
                . " We hope you enjoy our wines.<br><br>"
                . "If you encounter any issue, you have 15 days to submit a return request"
                . " from your customer account.",
            'cancelled'  => "Your order <strong>{$safeRef}</strong> has been cancelled."
                . " If you have already been charged, a refund will be processed as soon as possible.",
            'refunded'   => "Your order <strong>{$safeRef}</strong> has been refunded."
                . " The credit should appear on your bank account within 3 to 5 working days.",
        ];

        $orderLinkBlock = "<br><br><a href=\"{$appUrl}/en/my-account/orders\" style=\"color:#c9a84c;\">"
            . "View my orders</a>";

        return [
            $subjects[$status],
            $titles[$status],
            "Hello {$safeName},",
            $messages[$status] . $orderLinkBlock,
        ];
    }

    /**
     * Notifie le propriétaire qu'une demande de retour a été enregistrée.
     *
     * @param array<string, mixed> $order
     */
    public function sendReturnRequestedToOwner(array $order, string $clientName, string $clientEmail): void
    {
        $ownerEmail   = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'];
        $safeRef      = htmlspecialchars($order['order_reference'] ?? '', ENT_QUOTES);
        $safeName     = htmlspecialchars($clientName, ENT_QUOTES);
        $safeEmail    = htmlspecialchars($clientEmail, ENT_QUOTES);
        $orderedAt    = isset($order['ordered_at']) ? date('d/m/Y', strtotime((string) $order['ordered_at'])) : '—';
        $items        = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $appUrl       = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $orderId      = (int) ($order['id'] ?? 0);
        $orderLink    = $orderId > 0
            ? htmlspecialchars($appUrl . '/admin/commandes/' . $orderId, ENT_QUOTES)
            : '';

        $rows = '';
        foreach ($items as $item) {
            $label  = htmlspecialchars($item['label_name'] ?? '—', ENT_QUOTES);
            $format = htmlspecialchars($this->resolveItemFormat($item['format'] ?? '', 'fr'), ENT_QUOTES);
            $qty    = (int) ($item['qty'] ?? 0);
            $price  = number_format((float) ($item['price'] ?? 0), 2, ',', ' ');
            $rows  .= "<tr style=\"border-bottom:1px solid #ede8df;\">"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$label}</td>"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$format}</td>"
                . "<td style=\"padding:4px 8px;text-align:center;font-size:13px;color:#3d3425;\">{$qty}</td>"
                . "<td style=\"padding:4px 8px;text-align:right;font-size:13px;color:#3d3425;\">{$price}&nbsp;€</td>"
                . "</tr>";
        }

        $tableHtml = "<table style=\"width:100%;border-collapse:collapse;margin-top:12px;\">"
            . "<thead><tr style=\"background:#f5f0e8;\">"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Vin</th>"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;"
            . "letter-spacing:1px;color:#8a7a60;\">Format</th>"
            . "<th style=\"padding:6px 8px;text-align:center;font-size:11px;"
            . "letter-spacing:1px;color:#8a7a60;\">Qté</th>"
            . "<th style=\"padding:6px 8px;text-align:right;font-size:11px;"
            . "letter-spacing:1px;color:#8a7a60;\">Prix unit.</th>"
            . "</tr></thead><tbody>{$rows}</tbody></table>";

        $orderLinkBlock = $orderLink !== ''
            ? "<br><br><a href=\"{$orderLink}\" style=\"color:#c9a84c;\">Voir la commande dans l'administration</a>"
            : '';

        $message = "Une demande de retour a été enregistrée pour la commande <strong>{$safeRef}</strong>.<br><br>"
            . "<strong>Client :</strong> {$safeName} (<a href=\"mailto:{$safeEmail}\">{$safeEmail}</a>)<br>"
            . "<strong>Date commande :</strong> {$orderedAt}"
            . $orderLinkBlock . "<br><br>"
            . $tableHtml;

        $body = $this->emailSimpleLayout(
            'Demande de retour',
            'Nouvelle demande de rétractation',
            $message,
            'fr'
        );

        $this->send($ownerEmail, APP_NAME, "Retour client — commande {$safeRef}", $body, null, $clientEmail);
    }

    /**
     * Confirme la demande de retour au client avec la fiche de retour en pièce jointe.
     *
     * @param array<string, mixed> $order
     */
    public function sendReturnConfirmedToClient(
        string $to,
        string $name,
        array $order,
        string $pdfPath,
        string $lang
    ): void {
        $safeRef   = htmlspecialchars($order['order_reference'] ?? '', ENT_QUOTES);
        $safeName  = htmlspecialchars($name, ENT_QUOTES);
        $orderedAt = isset($order['ordered_at']) ? date('d/m/Y', strtotime((string) $order['ordered_at'])) : '—';
        $items     = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];

        $rows = '';
        foreach ($items as $item) {
            $label  = htmlspecialchars($item['label_name'] ?? '—', ENT_QUOTES);
            $format = htmlspecialchars($this->resolveItemFormat($item['format'] ?? '', $lang), ENT_QUOTES);
            $qty    = (int) ($item['qty'] ?? 0);
            $price  = number_format((float) ($item['price'] ?? 0), 2, ',', ' ');
            $rows  .= "<tr style=\"border-bottom:1px solid #ede8df;\">"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$label}</td>"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$format}</td>"
                . "<td style=\"padding:4px 8px;text-align:center;font-size:13px;color:#3d3425;\">{$qty}</td>"
                . "<td style=\"padding:4px 8px;text-align:right;font-size:13px;color:#3d3425;\">{$price}&nbsp;€</td>"
                . "</tr>";
        }

        $tableHtml = "<table style=\"width:100%;border-collapse:collapse;margin-top:12px;\">"
            . "<thead><tr style=\"background:#f5f0e8;\">"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">"
            . ($lang === 'fr' ? 'Vin' : 'Wine')
            . "</th>"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;"
            . "letter-spacing:1px;color:#8a7a60;\">Format</th>"
            . "<th style=\"padding:6px 8px;text-align:center;font-size:11px;"
            . "letter-spacing:1px;color:#8a7a60;\">"
            . ($lang === 'fr' ? 'Qté' : 'Qty')
            . "</th>"
            . "<th style=\"padding:6px 8px;text-align:right;font-size:11px;letter-spacing:1px;color:#8a7a60;\">"
            . ($lang === 'fr' ? 'Prix unit.' : 'Unit price')
            . "</th>"
            . "</tr></thead><tbody>{$rows}</tbody></table>";

        $appUrl    = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $orderId   = (int) ($order['id'] ?? 0);
        $orderLink = $orderId > 0
            ? htmlspecialchars($appUrl . '/' . $lang . '/mon-compte/commandes/' . $orderId, ENT_QUOTES)
            : '';

        if ($lang === 'fr') {
            $subject = "Confirmation de votre demande de retour — {$safeRef}";
            $orderLinkBlock = $orderLink !== ''
                ? "<br><br><a href=\"{$orderLink}\" style=\"color:#c9a84c;\">Voir ma commande</a>"
                : '';
            $message = "Votre demande de rétractation pour la commande <strong>{$safeRef}</strong>"
                . " (passée le {$orderedAt})"
                . " a bien été enregistrée.<br><br>"
                . "Vous trouverez ci-joint votre fiche de retour à inclure dans votre colis."
                . " Le retour doit être effectué en carton d'origine scellé, bouteilles non ouvertes."
                . $orderLinkBlock . "<br><br>"
                . "<strong>Récapitulatif de la commande :</strong>"
                . $tableHtml;
            $greeting = "Bonjour {$safeName},";
            $title    = 'Demande de retour enregistrée';
        } else {
            $subject  = "Return request confirmation — {$safeRef}";
            $orderLinkBlock = $orderLink !== ''
                ? "<br><br><a href=\"{$orderLink}\" style=\"color:#c9a84c;\">View my order</a>"
                : '';
            $message  = "Your withdrawal request for order <strong>{$safeRef}</strong> (placed on {$orderedAt})"
                . " has been registered.<br><br>"
                . "Please find your return slip attached — include it in your parcel."
                . " The return must be made in the original sealed carton, with unopened bottles."
                . $orderLinkBlock . "<br><br>"
                . "<strong>Order summary:</strong>"
                . $tableHtml;
            $greeting = "Hello {$safeName},";
            $title    = 'Return request registered';
        }

        $body     = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $filename = 'fiche-retour_' . ($order['order_reference'] ?? 'retour') . '.pdf';

        $this->send($to, $name, $subject, $body, $pdfPath, null, $filename);
    }

    /**
     * Envoie l'email de confirmation de double opt-in newsletter (FR ou EN).
     *
     * @param string $to         Adresse email du destinataire
     * @param string $confirmUrl URL complète de confirmation (avec token brut)
     * @param string $lang       Langue du destinataire ('fr' ou 'en')
     * @return void
     */
    public function sendNewsletterConfirmation(string $to, string $confirmUrl, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Confirmez votre abonnement à la newsletter Crabitan Bellevue'
            : 'Confirm your Crabitan Bellevue newsletter subscription';

        $body = $lang === 'fr'
            ? $this->newsletterConfirmBodyFr($confirmUrl)
            : $this->newsletterConfirmBodyEn($confirmUrl);

        $this->send($to, '', $subject, $body);
    }

    /**
     * Corps de l'email de confirmation newsletter en français.
     *
     * @param string $confirmUrl URL de confirmation avec token brut
     * @return string Corps HTML de l'email
     */
    private function newsletterConfirmBodyFr(string $confirmUrl): string
    {
        $safeUrl = htmlspecialchars($confirmUrl, ENT_QUOTES);

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Confirmer mon abonnement'
            . '</a></td></tr></table>';

        $message = "Merci de votre intérêt pour les actualités du Château Crabitan Bellevue.<br><br>"
            . "Pour finaliser votre inscription et recevoir nos nouvelles, "
            . "cliquez sur le bouton ci-dessous dans les <strong>48 heures</strong> :<br><br>"
            . $ctaBtn
            . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
            . "Si vous n'avez pas demandé cet abonnement, ignorez cet email. "
            . "Aucune action n'est requise.<br>"
            . "Ce lien est valable 48h à compter de sa réception."
            . "</p>";

        return $this->emailSimpleLayout(
            'Confirmation d\'abonnement',
            'Bonjour,',
            $message
        );
    }

    /**
     * Corps de l'email de confirmation newsletter en anglais.
     *
     * @param string $confirmUrl URL de confirmation avec token brut
     * @return string Corps HTML de l'email
     */
    private function newsletterConfirmBodyEn(string $confirmUrl): string
    {
        $safeUrl = htmlspecialchars($confirmUrl, ENT_QUOTES);

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Confirm my subscription'
            . '</a></td></tr></table>';

        $message = "Thank you for your interest in news from Château Crabitan Bellevue.<br><br>"
            . "To complete your subscription and receive our updates, "
            . "please click the button below within <strong>48 hours</strong>:<br><br>"
            . $ctaBtn
            . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
            . "If you did not request this subscription, please ignore this email. "
            . "No action is required.<br>"
            . "This link is valid for 48 hours from receipt."
            . "</p>";

        return $this->emailSimpleLayout(
            'Subscription confirmation',
            'Hello,',
            $message
        );
    }

    /**
     * Envoie l'email de confirmation de changement d'email à la NOUVELLE adresse.
     *
     * Le destinataire doit cliquer sur le lien pour que le changement soit effectif
     * (double opt-in anti account-takeover).
     *
     * @param string $to         Nouvelle adresse email (destinataire)
     * @param string $name       Nom d'affichage du compte
     * @param string $confirmUrl URL complète de confirmation (contient le token brut)
     * @param string $lang       Langue du compte ('fr' ou 'en')
     * @return void
     */
    public function sendEmailChangeConfirmation(
        string $to,
        string $name,
        string $confirmUrl,
        string $lang,
        string $newEmail,
        string $revokeUrl = ''
    ): void {
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeUrl      = htmlspecialchars($confirmUrl, ENT_QUOTES);
        $safeNewEmail = htmlspecialchars($newEmail, ENT_QUOTES);

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 16px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Confirmer le changement' : 'Confirm the change')
            . '</a></td></tr></table>';

        $revokeBlock = '';
        if ($revokeUrl !== '') {
            $safeRevokeUrl = htmlspecialchars($revokeUrl, ENT_QUOTES);
            $revokeLabel   = $lang === 'fr' ? 'Annuler cette demande' : 'Cancel this request';
            $revokeBlock   = '<table role="presentation" cellpadding="0" cellspacing="0"'
                . ' style="margin:0 auto 32px;">'
                . '<tr><td align="center" style="border:1px solid #c9a84c;border-radius:2px;'
                . 'padding:10px 28px;">'
                . "<a href=\"{$safeRevokeUrl}\""
                . ' style="font-family:Georgia,serif;font-size:14px;'
                . 'color:#c9a84c;text-decoration:none;">'
                . $revokeLabel
                . '</a></td></tr></table>';
        }

        if ($lang === 'fr') {
            $subject  = 'Confirmez le changement d\'adresse email — Crabitan Bellevue';
            $title    = 'Changement d\'adresse email';
            $greeting = "Bonjour {$safeName},";
            $message  = "Une demande de modification d'adresse email a été soumise"
                . " sur votre compte Château Crabitan Bellevue.<br><br>"
                . "<strong>Nouvelle adresse demandée :</strong> {$safeNewEmail}<br><br>"
                . "Si c'est bien vous, confirmez ce changement en cliquant sur le bouton ci-dessous "
                . "dans les <strong>24 heures</strong> :<br><br>"
                . $ctaBtn
                . $revokeBlock
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:8px;\">"
                . "Si vous n'êtes pas à l'origine de cette demande, cliquez sur \"Annuler\" ci-dessus "
                . "ou ignorez cet email — votre adresse actuelle reste inchangée. "
                . "Ce lien est valable 24h."
                . "</p>";
        } else {
            $subject  = 'Confirm your email address change — Crabitan Bellevue';
            $title    = 'Email address change';
            $greeting = "Hello {$safeName},";
            $message  = "A request to update the email address on your Château Crabitan Bellevue"
                . " account has been submitted.<br><br>"
                . "<strong>New address requested:</strong> {$safeNewEmail}<br><br>"
                . "If this was you, confirm the change by clicking the button below "
                . "within <strong>24 hours</strong>:<br><br>"
                . $ctaBtn
                . $revokeBlock
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:8px;\">"
                . "If you did not request this change, click \"Cancel\" above "
                . "or ignore this email — your current address remains unchanged. "
                . "This link is valid for 24 hours."
                . "</p>";
        }

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie un email informatif à la NOUVELLE adresse lors d'une demande de changement.
     *
     * Aucune action n'est requise du destinataire — le changement ne sera effectif
     * qu'après confirmation depuis l'ancienne adresse (titulaire prouvé).
     * Le nom du titulaire du compte n'est pas inclus (minimisation DCP — RGPD Art. 5).
     *
     * @param string $to   Nouvelle adresse email (destinataire)
     * @param string $lang Langue du compte ('fr' ou 'en')
     * @return void
     */
    public function sendEmailChangeNotification(string $to, string $lang): void
    {
        if ($lang === 'fr') {
            $subject  = 'Votre adresse email a été soumise sur Crabitan Bellevue';
            $title    = 'Information — changement d\'adresse email';
            $greeting = "Bonjour,";
            $message  = "Cette adresse email a été renseignée comme nouvelle adresse de connexion "
                . "pour un compte Château Crabitan Bellevue.<br><br>"
                . "Un lien de confirmation a été envoyé à l'adresse actuelle du titulaire du compte. "
                . "Le changement ne sera effectif qu'après validation de sa part.<br><br>"
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
                . "Si vous n'êtes pas à l'origine de cette démarche, aucune action n'est requise de votre part."
                . "</p>";
        } else {
            $subject  = 'Your email address was submitted on Crabitan Bellevue';
            $title    = 'Information — email address change';
            $greeting = "Hello,";
            $message  = "This email address has been submitted as the new login address "
                . "for a Château Crabitan Bellevue account.<br><br>"
                . "A confirmation link has been sent to the account's current email address. "
                . "The change will only take effect once the account holder confirms it.<br><br>"
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
                . "If you did not initiate this request, no action is required on your part."
                . "</p>";
        }

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($to, '', $subject, $body);
    }

    private function send(
        string $to,
        string $name,
        string $subject,
        string $body,
        ?string $attachmentPath = null,
        ?string $replyTo = null,
        ?string $attachmentName = null
    ): void {
        $this->mailer->clearAddresses();
        $this->mailer->clearAttachments();
        $this->mailer->clearReplyTos();
        $this->mailer->addAddress($to, $name);
        if ($replyTo !== null) {
            $this->mailer->addReplyTo($replyTo);
        }
        $this->mailer->isHTML(true);
        $this->mailer->Subject = $subject;
        $this->mailer->Body    = $body;
        $this->mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        if ($attachmentPath !== null) {
            $this->mailer->addAttachment($attachmentPath, $attachmentName ?? '');
        }
        $this->mailer->send();
    }

    private function emailSimpleLayout(string $title, string $greeting, string $message, string $lang = 'fr'): string
    {
        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $lang);

        $inner = <<<INNER

          <!-- Header logo -->
          {$headerHtml}

          <!-- Body -->
          <tr>
            <td style="background-color:#ffffff;border:1px solid #ddd5c4;padding:40px 36px;">
              <p style="margin:0 0 8px;font-size:11px;letter-spacing:3px;
                 text-transform:uppercase;color:#8a7a60;">{$title}</p>
              <p style="margin:0 0 24px;font-size:22px;color:#1a1208;font-family:Georgia,serif;">{$greeting}</p>
              <p style="margin:0 0 32px;font-size:15px;line-height:1.7;color:#3d3425;">{$message}</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="border-top:1px solid #ede8df;"></td></tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          {$footerHtml}

INNER;

        return $this->emailWrap($title, $inner);
    }

    private function verificationBodyFr(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return $this->emailLayout(
            'Activation de votre compte',
            "Bonjour {$safeName},",
            'Merci de vous être inscrit au Château Crabitan Bellevue.'
            . ' Veuillez activer votre compte en cliquant sur le bouton ci-dessous.',
            $safeUrl,
            'Activer mon compte',
            'Ce lien est valable <strong>24 heures</strong>. Si vous n\'êtes pas à l\'origine'
            . ' de cette inscription, ignorez cet email.',
            'fr'
        );
    }

    private function verificationBodyEn(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return $this->emailLayout(
            'Account activation',
            "Hello {$safeName},",
            'Thank you for registering at Château Crabitan Bellevue.'
            . ' Please activate your account by clicking the button below.',
            $safeUrl,
            'Activate my account',
            'This link is valid for <strong>24 hours</strong>. If you did not create an account,'
            . ' please ignore this email.',
            'en'
        );
    }

    private function resetBodyFr(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return $this->emailLayout(
            'Réinitialisation de votre mot de passe',
            "Bonjour {$safeName},",
            'Vous avez demandé la réinitialisation de votre mot de passe.'
            . ' Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.',
            $safeUrl,
            'Réinitialiser mon mot de passe',
            'Ce lien est valable <strong>1 heure</strong>. Si vous n\'êtes pas à l\'origine'
            . ' de cette demande, ignorez cet email — votre mot de passe reste inchangé.',
            'fr'
        );
    }

    private function resetBodyEn(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return $this->emailLayout(
            'Password reset',
            "Hello {$safeName},",
            'You requested a password reset for your Château Crabitan Bellevue account.'
            . ' Click the button below to set a new password.',
            $safeUrl,
            'Reset my password',
            'This link is valid for <strong>1 hour</strong>. If you did not request a password reset,'
            . ' please ignore this email — your password will remain unchanged.',
            'en'
        );
    }

    private function emailLayout(
        string $title,
        string $greeting,
        string $message,
        string $url,
        string $ctaLabel,
        string $footnote,
        string $lang = 'fr'
    ): string {
        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback local dev
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $lang);

        $inner = <<<INNER

          <!-- Header logo -->
          {$headerHtml}

          <!-- Body -->
          <tr>
            <td style="background-color:#ffffff;border:1px solid #ddd5c4;padding:40px 36px;">

              <p style="margin:0 0 8px;font-size:11px;letter-spacing:3px;
                 text-transform:uppercase;color:#8a7a60;">{$title}</p>
              <p style="margin:0 0 24px;font-size:22px;color:#1a1208;font-family:Georgia,serif;">{$greeting}</p>
              <p style="margin:0 0 32px;font-size:15px;line-height:1.7;color:#3d3425;">{$message}</p>

              <!-- CTA Button -->
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">
                <tr>
                  <td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">
                    <a href="{$url}"
                       style="display:inline-block;padding:14px 36px;font-family:Georgia,serif;
                              font-size:14px;letter-spacing:2px;text-transform:uppercase;
                              color:#1a1208;text-decoration:none;font-weight:bold;">
                      {$ctaLabel}
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Fallback URL -->
              <p style="margin:0 0 24px;font-size:12px;color:#8a7a60;line-height:1.6;">
                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                <a href="{$url}" style="color:#c9a84c;word-break:break-all;">{$url}</a>
              </p>

              <!-- Divider -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="border-top:1px solid #ede8df;padding-bottom:24px;"></td></tr>
              </table>

              <p style="margin:0;font-size:12px;color:#8a7a60;line-height:1.6;">{$footnote}</p>
            </td>
          </tr>

          <!-- Footer -->
          {$footerHtml}

INNER;

        return $this->emailWrap($title, $inner);
    }

    protected function newOrderFormModel(): \Model\OrderFormModel
    {
        return new \Model\OrderFormModel();
    }

    private function emailWrap(string $title, string $innerContent): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
  <style>
    .footer-link:hover { color: #c9a84c !important; text-decoration: underline !important; }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f0e8;font-family:Georgia,serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
         style="background-color:#f5f0e8;padding:40px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
{$innerContent}
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Génère le bloc footer HTML partagé par tous les emails.
     *
     * Contient obligatoirement la mention Loi Évin (Art. L3323-4 CSP) dans la langue
     * du destinataire, visible en texte clair avec contraste suffisant.
     *
     * @param string $urlPrivacy URL vers la politique de confidentialité
     * @param string $urlLegal   URL vers les mentions légales
     * @param string $urlSupport URL vers la page d'assistance
     * @param string $lang       Langue du destinataire ('fr' ou 'en')
     * @return string HTML du footer email
     */
    private function emailFooterHtml(
        string $urlPrivacy,
        string $urlLegal,
        string $urlSupport,
        string $lang = 'fr'
    ): string {
        $year    = date('Y');
        $mention = $lang === 'fr'
            ? "L'abus d'alcool est dangereux pour la santé. À consommer avec modération."
            : 'Alcohol abuse is dangerous for your health. To be consumed in moderation.';
        $autoMsg = $lang === 'fr'
            ? 'Ce mail est généré automatiquement. Veuillez ne pas y répondre.'
            : 'This email was generated automatically. Please do not reply.';
        return <<<HTML
          <tr>
            <td align="center" style="padding-top:24px;">
              <p style="margin:0 0 10px;">
                <a href="{$urlPrivacy}" class="footer-link"
                   style="font-size:11px;color:#8a7a60;text-decoration:none;letter-spacing:1px;"
                >Politique de confidentialité</a>
                <span style="color:#c4b89a;padding:0 8px;">|</span>
                <a href="{$urlLegal}" class="footer-link"
                   style="font-size:11px;color:#8a7a60;text-decoration:none;letter-spacing:1px;"
                >Mentions légales</a>
                <span style="color:#c4b89a;padding:0 8px;">|</span>
                <a href="{$urlSupport}" class="footer-link"
                   style="font-size:11px;color:#8a7a60;text-decoration:none;letter-spacing:1px;"
                >Assistance</a>
              </p>
              <!-- Mention Loi Évin obligatoire — Art. L3323-4 CSP -->
              <p style="margin:0 0 6px;font-size:11px;color:#6b5e4a;font-style:italic;line-height:1.5;">
                {$mention}
              </p>
              <p style="margin:0 0 6px;font-size:11px;color:#a89880;letter-spacing:1px;">
                © {$year} Château Crabitan Bellevue — Sainte-Croix-du-Mont, Gironde
              </p>
              <p style="margin:0;font-size:10px;color:#b8aa95;">
                {$autoMsg}
              </p>
            </td>
          </tr>
HTML;
    }

    private function emailHeaderHtml(string $appUrl, string $logoUrl): string
    {
        return <<<HTML
          <tr>
            <td align="center" style="padding-bottom:28px;">
              <a href="{$appUrl}" style="display:inline-block;text-decoration:none;">
                <img src="{$logoUrl}" alt="Château Crabitan Bellevue" width="200" height="auto"
                     style="display:block;border:0;max-width:200px;">
              </a>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="padding-top:20px;border-bottom:1px solid #c9a84c;"></td></tr>
              </table>
            </td>
          </tr>
HTML;
    }

    private function resolveItemFormat(string $format, string $lang): string
    {
        $map = [
            'bottle' => ['fr' => 'bouteille', 'en' => 'bottle'],
            'bib'    => ['fr' => 'bag-in-box', 'en' => 'bag-in-box'],
        ];
        return $map[$format][$lang] ?? ($map[$format]['fr'] ?? $format);
    }

    private function resolveSubjectLabel(string $subject, string $lang): string
    {
        $labels = [
            'general'      => ['fr' => 'Renseignement général',    'en' => 'General enquiry'],
            'order'        => ['fr' => 'Question sur une commande', 'en' => 'Order enquiry'],
            'bon_commande' => ['fr' => 'Bon de commande',           'en' => 'Order form'],
            'visit'        => ['fr' => 'Visite du domaine',         'en' => 'Estate visit'],
            'press'        => ['fr' => 'Presse / Partenariat',      'en' => 'Press / Partnership'],
            'other'        => ['fr' => 'Autre',                     'en' => 'Other'],
        ];
        return $labels[$subject][$lang] ?? ($labels[$subject]['fr'] ?? $subject);
    }

    /**
     * @return array{subjectLine: string, message: string}
     */
    private function buildConfirmationLines(bool $isOrderForm, string $lang, string $subjectLabel): array
    {
        if ($lang === 'fr') {
            return [
                'subjectLine' => $isOrderForm
                    ? 'Votre bon de commande Crabitan Bellevue'
                    : 'Nous avons bien reçu votre message',
                'message'     => $isOrderForm
                    ? 'Merci pour votre intérêt. Vous trouverez notre bon de commande en pièce jointe.'
                    : "Nous avons bien reçu votre message concernant <strong>{$subjectLabel}</strong>."
                      . '<br>Notre équipe vous répondra dans les meilleurs délais.',
            ];
        }
        return [
            'subjectLine' => $isOrderForm
                ? 'Your Crabitan Bellevue order form'
                : 'We have received your message',
            'message'     => $isOrderForm
                ? 'Thank you for your interest. Please find our order form attached.'
                : "We have received your message regarding <strong>{$subjectLabel}</strong>."
                  . '<br>Our team will get back to you as soon as possible.',
        ];
    }

    private function buildRecapBlock(string $userMessage, string $lang): string
    {
        if ($userMessage === '') {
            return '';
        }
        $safeMsg    = nl2br(htmlspecialchars($userMessage, ENT_QUOTES));
        $recapLabel = $lang === 'fr' ? 'Votre message' : 'Your message';
        return "<div style=\"margin-top:20px;padding:16px 20px;background:#f5f0e8;"
            . "border-left:3px solid #c9a84c;font-size:14px;color:#5a4e3a;line-height:1.7;\">"
            . "<p style=\"margin:0 0 8px;font-size:11px;letter-spacing:2px;text-transform:uppercase;"
            . "color:#8a7a60;\">{$recapLabel}</p>"
            . "<p style=\"margin:0;\">{$safeMsg}</p>"
            . "</div>";
    }

    // ----------------------------------------------------------------
    // Email anti-énumération — doublon inscription
    // ----------------------------------------------------------------

    /**
     * Informe le titulaire d'un compte existant qu'une tentative d'inscription
     * avec son adresse email a eu lieu (critère 2 anti-énumération R5).
     *
     * @param string $to       Adresse du destinataire (compte existant)
     * @param string $name     Nom affiché du destinataire
     * @param string $lang     Langue de l'email ('fr' ou 'en')
     * @param string $loginUrl URL vers la page de connexion
     * @param string $resetUrl URL vers la page de réinitialisation du mot de passe
     * @return void
     */
    public function sendEmailAlreadyExists(
        string $to,
        string $name,
        string $lang,
        string $loginUrl,
        string $resetUrl
    ): void {
        $subject = $lang === 'fr'
            ? 'Tentative de création de compte — Château Crabitan Bellevue'
            : 'Account registration attempt — Château Crabitan Bellevue';

        $body = $lang === 'fr'
            ? $this->emailAlreadyExistsBodyFr($name, $loginUrl, $resetUrl)
            : $this->emailAlreadyExistsBodyEn($name, $loginUrl, $resetUrl);

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Corps de l'email "email déjà utilisé" en français.
     *
     * @param string $name     Nom affiché du destinataire
     * @param string $loginUrl URL de connexion
     * @param string $resetUrl URL de réinitialisation du mot de passe
     * @return string HTML de l'email
     */
    /**
     * Envoie une alerte sécurité à l'utilisateur quand son compte vient d'être verrouillé
     * suite à 5 tentatives de connexion échouées consécutives.
     *
     * @param string $to       Email du destinataire
     * @param string $name     Nom affiché du destinataire
     * @param string $lang     Langue ('fr' ou 'en')
     * @param string $resetUrl URL de réinitialisation du mot de passe
     * @return void
     */
    public function sendAccountLocked(string $to, string $name, string $lang, string $resetUrl): void
    {
        $subject = $lang === 'fr'
            ? 'Alerte sécurité — Votre compte a été temporairement verrouillé'
            : 'Security alert — Your account has been temporarily locked';

        $body = $lang === 'fr'
            ? $this->accountLockedBodyFr($name, $resetUrl)
            : $this->accountLockedBodyEn($name, $resetUrl);

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Corps de l'email d'alerte verrouillage en français.
     *
     * @param string $name     Nom affiché du destinataire
     * @param string $resetUrl URL de réinitialisation du mot de passe
     * @return string HTML de l'email
     */
    private function accountLockedBodyFr(string $name, string $resetUrl): string
    {
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

        $message = '5 tentatives de connexion échouées ont été détectées sur votre compte Château Crabitan Bellevue.'
            . ' Pour votre sécurité, il a été temporairement verrouillé pendant <strong>15 minutes</strong>.'
            . '<br><br>'
            . 'Si c\'était vous, attendez quelques instants puis réessayez.'
            . '<br><br>'
            . 'Si ce n\'était <strong>pas vous</strong>, nous vous recommandons vivement'
            . ' de changer votre mot de passe immédiatement.'
            . '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Changer mon mot de passe'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout('Alerte sécurité', "Bonjour {$safeName},", $message, 'fr');
    }

    /**
     * Corps de l'email d'alerte verrouillage en anglais.
     *
     * @param string $name     Nom affiché du destinataire
     * @param string $resetUrl URL de réinitialisation du mot de passe
     * @return string HTML de l'email
     */
    private function accountLockedBodyEn(string $name, string $resetUrl): string
    {
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

        $message = '5 failed login attempts were detected on your Château Crabitan Bellevue account.'
            . ' For your security, it has been temporarily locked for <strong>15 minutes</strong>.'
            . '<br><br>'
            . 'If this was you, please wait a few minutes and try again.'
            . '<br><br>'
            . 'If this was <strong>not you</strong>, we strongly recommend changing your password immediately.'
            . '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Change my password'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout('Security alert', "Hello {$safeName},", $message, 'en');
    }

    private function emailAlreadyExistsBodyFr(string $name, string $loginUrl, string $resetUrl): string
    {
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES);
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

        $message = 'Une tentative de création de compte avec votre adresse email vient d\'être effectuée'
            . ' sur le Château Crabitan Bellevue. Vous avez déjà un compte — si c\'était vous,'
            . ' connectez-vous directement ou réinitialisez votre mot de passe si vous l\'avez oublié.'
            . '<br><br>'
            . 'Si ce n\'était pas vous, ignorez cet email. Aucune action n\'a été réalisée sur votre compte.'
            . '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeLoginUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Me connecter'
            . '</a></td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td>'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:10px 24px;"
            . 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;'
            . 'color:#1a1208;text-decoration:underline;">'
            . 'Mot de passe oublié ?'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout('Sécurité du compte', "Bonjour {$safeName},", $message, 'fr');
    }

    /**
     * Corps de l'email "email déjà utilisé" en anglais.
     *
     * @param string $name     Nom affiché du destinataire
     * @param string $loginUrl URL de connexion
     * @param string $resetUrl URL de réinitialisation du mot de passe
     * @return string HTML de l'email
     */
    private function emailAlreadyExistsBodyEn(string $name, string $loginUrl, string $resetUrl): string
    {
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES);
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

        $message = 'Someone just attempted to create an account at Château Crabitan Bellevue'
            . ' using your email address. You already have an account — if this was you,'
            . ' simply log in or reset your password if you have forgotten it.'
            . '<br><br>'
            . 'If this was not you, please ignore this email. No action has been taken on your account.'
            . '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeLoginUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Log in'
            . '</a></td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td>'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:10px 24px;"
            . 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;'
            . 'color:#1a1208;text-decoration:underline;">'
            . 'Forgot your password?'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout('Account security', "Hello {$safeName},", $message, 'en');
    }

    /**
     * Envoie l'email de confirmation de double opt-in newsletter (FR ou EN).
     *
     * @param string $to         Adresse email du destinataire
     * @param string $confirmUrl URL complète de confirmation (avec token brut)
     * @param string $lang       Langue du destinataire ('fr' ou 'en')
     * @return void
     */
    public function sendNewsletterConfirmation(string $to, string $confirmUrl, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Confirmez votre abonnement à la newsletter Crabitan Bellevue'
            : 'Confirm your Crabitan Bellevue newsletter subscription';

        $body = $lang === 'fr'
            ? $this->newsletterConfirmBodyFr($confirmUrl)
            : $this->newsletterConfirmBodyEn($confirmUrl);

        $this->send($to, '', $subject, $body);
    }

    /**
     * Corps de l'email de confirmation newsletter en français.
     *
     * @param string $confirmUrl URL de confirmation avec token brut
     * @return string Corps HTML de l'email
     */
    private function newsletterConfirmBodyFr(string $confirmUrl): string
    {
        $safeUrl = htmlspecialchars($confirmUrl, ENT_QUOTES);

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Confirmer mon abonnement'
            . '</a></td></tr></table>';

        $message = "Merci de votre intérêt pour les actualités du Château Crabitan Bellevue.<br><br>"
            . "Pour finaliser votre inscription et recevoir nos nouvelles, "
            . "cliquez sur le bouton ci-dessous dans les <strong>48 heures</strong> :<br><br>"
            . $ctaBtn
            . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
            . "Si vous n'avez pas demandé cet abonnement, ignorez cet email. "
            . "Aucune action n'est requise.<br>"
            . "Ce lien est valable 48h à compter de sa réception."
            . "</p>";

        return $this->emailSimpleLayout(
            'Confirmation d\'abonnement',
            'Bonjour,',
            $message
        );
    }

    /**
     * Corps de l'email de confirmation newsletter en anglais.
     *
     * @param string $confirmUrl URL de confirmation avec token brut
     * @return string Corps HTML de l'email
     */
    private function newsletterConfirmBodyEn(string $confirmUrl): string
    {
        $safeUrl = htmlspecialchars($confirmUrl, ENT_QUOTES);

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Confirm my subscription'
            . '</a></td></tr></table>';

        $message = "Thank you for your interest in news from Château Crabitan Bellevue.<br><br>"
            . "To complete your subscription and receive our updates, "
            . "please click the button below within <strong>48 hours</strong>:<br><br>"
            . $ctaBtn
            . "<p style=\"font-size:12px;color:#8a7a60;margin-top:16px;\">"
            . "If you did not request this subscription, please ignore this email. "
            . "No action is required.<br>"
            . "This link is valid for 48 hours from receipt."
            . "</p>";

        return $this->emailSimpleLayout(
            'Subscription confirmation',
            'Hello,',
            $message
        );
    }
}
