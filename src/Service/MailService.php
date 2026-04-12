<?php

declare(strict_types=1);

namespace Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService // NOSONAR php:S1448 — seams de testabilité (newOrderFormModel/newMailService), pas de logique métier
{
    private const DEFAULT_URL       = 'https://crabitanbellevue.fr';
    private const PATH_SECURITY     = '/mon-compte/securite';
    private const PATH_REACTIVER    = '/compte/reactiver?token=';
    private const BTN_ANCHOR_STYLE_ATTR = '" style="';
    private const BTN_STYLE_PRIMARY     = 'display:inline-block;padding:14px 36px;'
        . 'font-family:Georgia,serif;font-size:14px;'
        . 'letter-spacing:2px;text-transform:uppercase;';
    private const BTN_STYLE_LINK        = 'color:#1a1208;text-decoration:none;font-weight:bold;">';
    private const BTN_STYLE_SECONDARY   = 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;';
    private const MAIL_SUBJECT_SEC_FR   = 'Sécurité du compte';
    private const MAIL_SUBJECT_SEC_EN   = 'Account security';
    private const LOGO_PATH   = '/assets/images/logo/crabitan-bellevue-logo-modern.svg';
    private const URL_PRIVACY = '/fr/politique-de-confidentialite';
    private const URL_LEGAL   = '/fr/mentions-legales';
    private const URL_SUPPORT = '/fr/support';
    private const URL_CGV        = '/fr/conditions-generales-de-vente';
    private const DATE_FORMAT_FR        = 'd/m/Y à H:i';
    private const DATE_FORMAT_DATE      = 'd/m/Y';
    private const PAYMENT_LABEL_CHEQUE  = 'Chèque';
    private const WINE_IMAGE_PATH       = '/assets/images/wines/';
    private const COL_UNIT_EN           = 'Unit price';
    private const COL_UNIT_FR           = 'Prix unitaire';

    private PHPMailer $mailer;

    public function __construct()
    {
        $mailUser = $_ENV['MAIL_USER'] ?? '';
        $mailPass = $_ENV['MAIL_PASS'] ?? '';
        $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host    = $_ENV['MAIL_HOST'] ?? 'crabitanbellevue.fr';
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
            $this->mailer->SMTPAutoTLS = false;
        }

        $appUrl      = $_ENV['APP_URL'] ?? (defined('APP_URL') ? APP_URL : self::DEFAULT_URL);
        $host        = parse_url($appUrl, PHP_URL_HOST) ?? 'crabitanbellevue.fr';
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

    /**
     * Envoie un email informatif à un compte Google-only ayant demandé un reset de mot de passe.
     *
     * @param string $to   Adresse email du destinataire
     * @param string $name Prénom ou nom complet
     * @param string $lang Langue du compte ('fr' ou 'en')
     * @return void
     */
    public function sendGoogleAccountInfo(string $to, string $name, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Votre compte est associé à Google'
            : 'Your account is linked to Google';

        $body = $lang === 'fr'
            ? $this->googleAccountInfoBodyFr($name)
            : $this->googleAccountInfoBodyEn($name);

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
        $ownerEmail = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '';

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
        ?string $attachmentName = null,
        ?string $unsubToken = null
    ): void {
        if ($unsubToken !== null) {
            $appUrl = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
            $this->mailer->addCustomHeader(
                'List-Unsubscribe',
                '<' . $appUrl . '/fr/newsletter/desabonnement?token=' . urlencode($unsubToken) . '>'
            );
            $this->mailer->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
        $this->send($to, $name, $subject, $htmlBody, $attachmentPath, null, $attachmentName);
        if ($unsubToken !== null) {
            $this->mailer->clearCustomHeaders();
        }
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
            $safeImg   = htmlspecialchars($appUrl . self::WINE_IMAGE_PATH . $imagePath, ENT_QUOTES);
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
            . "<a href=\"{$wineUrl}\" style=\""
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
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $reactUrl  = $reactivationToken !== ''
            ? $appUrl . '/' . $lang . self::PATH_REACTIVER . urlencode($reactivationToken)
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
                  . '<a href="' . $safeReactUrl . self::BTN_ANCHOR_STYLE_ATTR
                  . self::BTN_STYLE_PRIMARY
                  . self::BTN_STYLE_LINK . 'Annuler la suppression</a>'
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
                  . '<a href="' . $safeReactUrl . self::BTN_ANCHOR_STYLE_ATTR
                  . self::BTN_STYLE_PRIMARY
                  . self::BTN_STYLE_LINK . 'Cancel deletion</a>'
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
    /**
     * Envoie une alerte de changement de mot de passe avec boutons "C'était moi" / "Ce n'était pas moi".
     *
     * @param  string $to           Adresse email du destinataire
     * @param  string $name         Nom d'affichage
     * @param  string $lang         Langue ('fr' ou 'en')
     * @param  string $confirmToken Token brut (non haché) pour le bouton "C'était moi", vide si non généré
     * @return void
     */
    /**
     * Envoie la demande de confirmation de changement de mot de passe.
     * Le mot de passe n'est PAS encore modifié — il sera appliqué uniquement après clic sur "C'était moi".
     *
     * @param string $to           Destinataire
     * @param string $name         Nom affiché
     * @param string $lang         Langue (fr|en)
     * @param string $confirmToken Token brut (non haché) pour les deux CTAs
     * @return void
     */
    /**
     * Envoie un rappel avant suppression définitive du compte (J-7 ou J-1).
     *
     * @param string $to                  Adresse email du destinataire
     * @param string $name                Prénom ou nom d'affichage
     * @param string $lang                Langue ('fr' ou 'en')
     * @param int    $daysAhead           Délai restant en jours (1 ou 7)
     * @param string $scheduledDeletionAt Date d'anonymisation programmée (Y-m-d H:i:s)
     * @param string $reactivationToken   Token brut pour le lien d'annulation
     * @return void
     */
    public function sendDeletionReminder(
        string $to,
        string $name,
        string $lang,
        int $daysAhead,
        string $scheduledDeletionAt,
        string $reactivationToken
    ): void {
        $appUrl      = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $reactUrl    = $appUrl . '/' . $lang . self::PATH_REACTIVER . urlencode($reactivationToken);
        $safeName    = htmlspecialchars($name, ENT_QUOTES);
        $safeReact   = htmlspecialchars($reactUrl, ENT_QUOTES);
        $safeDate    = htmlspecialchars($scheduledDeletionAt, ENT_QUOTES);

        if ($daysAhead === 1) {
            $urgencyNote = $lang === 'fr'
                ? '<strong>⚠ Dernier avertissement — votre compte sera anonymisé demain.</strong><br><br>'
                : '<strong>⚠ Final warning — your account will be anonymised tomorrow.</strong><br><br>';
        } else {
            $urgencyNote = '';
        }

        $reactBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . '<a href="' . $safeReact . self::BTN_ANCHOR_STYLE_ATTR
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Annuler la suppression' : 'Cancel deletion')
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $subject = $daysAhead === 1
                ? 'Dernier avertissement : votre compte Crabitan sera supprimé demain'
                : "Rappel : votre compte Crabitan sera supprimé dans {$daysAhead} jours";
            $message = $urgencyNote
                . "Votre demande de suppression de compte est en cours de traitement."
                . " Vos données personnelles seront définitivement anonymisées le <strong>{$safeDate}</strong>.<br><br>"
                . "Si vous avez changé d'avis, vous pouvez annuler cette demande en cliquant sur le bouton ci-dessous.<br><br>"
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'Passé ce délai, vos données ne pourront plus être récupérées.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Suppression de compte — rappel',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = $daysAhead === 1
                ? 'Final warning: your Crabitan account will be deleted tomorrow'
                : "Reminder: your Crabitan account will be deleted in {$daysAhead} days";
            $message = $urgencyNote
                . "Your account deletion request is being processed."
                . " Your personal data will be permanently anonymised on <strong>{$safeDate}</strong>.<br><br>"
                . "If you have changed your mind, you can cancel this request by clicking the button below.<br><br>"
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'After this date, your data cannot be recovered.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Account deletion — reminder',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie un email d'avertissement d'inactivité RGPD (Art. 5 §1e).
     *
     * Le compte n'est PAS encore supprimé — l'utilisateur peut se reconnecter
     * pour annuler la suppression programmée dans 30 jours.
     *
     * @param string $to           Destinataire
     * @param string $name         Nom affiché
     * @param string $lang         Langue (fr|en)
     * @param string $deletionDate Date de suppression prévue (format lisible)
     * @return void
     */
    public function sendInactivityWarning(
        string $to,
        string $name,
        string $lang,
        string $deletionDate
    ): void {
        $appUrl      = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $loginUrl    = $appUrl . '/' . $lang . '?login=1';
        $safeName    = htmlspecialchars($name, ENT_QUOTES);
        $safeDate    = htmlspecialchars($deletionDate, ENT_QUOTES);
        $safeLogin   = htmlspecialchars($loginUrl, ENT_QUOTES);

        $loginBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . '<a href="' . $safeLogin . self::BTN_ANCHOR_STYLE_ATTR
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Me connecter' : 'Log in')
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $subject = 'Votre compte Crabitan sera supprimé pour inactivité';
            $message = "Nous n'avons pas détecté de connexion à votre compte depuis plus de 36 mois."
                . " Conformément à notre politique RGPD (Art. 5 §1e), votre compte sera automatiquement"
                . " supprimé le <strong>{$safeDate}</strong>.<br><br>"
                . "Si vous souhaitez conserver votre compte, il vous suffit de vous connecter avant cette date.<br><br>"
                . $loginBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'Si vous n\'utilisez plus ce compte, vous n\'avez rien à faire.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Avertissement inactivité',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = 'Your Crabitan account will be deleted due to inactivity';
            $message = "We have not detected any login to your account for over 36 months."
                . " In accordance with our GDPR policy (Art. 5 §1e), your account will be automatically"
                . " deleted on <strong>{$safeDate}</strong>.<br><br>"
                . "If you wish to keep your account, simply log in before that date.<br><br>"
                . $loginBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'If you no longer use this account, no action is required.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Inactivity warning',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Notifie l'utilisateur que son compte a été suspendu pour inactivité.
     * Le compte est déjà soft-deleted — l'utilisateur dispose de 30 jours
     * pour le réactiver via le token avant suppression définitive.
     *
     * @param string $to               Destinataire
     * @param string $name             Nom affiché
     * @param string $lang             Langue (fr|en)
     * @param string $deletionDate     Date de suppression définitive (format lisible)
     * @param string $reactivationToken Token de réactivation (brut, non haché)
     * @return void
     */
    public function sendInactivityDeletion(
        string $to,
        string $name,
        string $lang,
        string $deletionDate,
        string $reactivationToken
    ): void {
        $appUrl       = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $reactUrl     = $appUrl . '/' . $lang . self::PATH_REACTIVER . urlencode($reactivationToken);
        $safeName     = htmlspecialchars($name, ENT_QUOTES);
        $safeDate     = htmlspecialchars($deletionDate, ENT_QUOTES);
        $safeReactUrl = htmlspecialchars($reactUrl, ENT_QUOTES);

        $reactBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . '<a href="' . $safeReactUrl . self::BTN_ANCHOR_STYLE_ATTR
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Réactiver mon compte' : 'Reactivate my account')
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $subject = 'Votre compte Crabitan a été suspendu pour inactivité';
            $message = "Votre compte n'ayant enregistré aucune connexion depuis plus de 36 mois,"
                . " il a été <strong>suspendu automatiquement</strong> conformément à notre politique RGPD"
                . " (Art. 5 §1e — minimisation des données).<br><br>"
                . "Vos données personnelles seront définitivement supprimées le <strong>{$safeDate}</strong>."
                . " Vous disposez de 30 jours pour réactiver votre compte en cliquant sur le bouton ci-dessous.<br><br>"
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'Si vous n\'utilisez plus ce compte, vous n\'avez rien à faire —'
                . ' vos données seront supprimées à la date indiquée.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Compte suspendu',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = 'Your Crabitan account has been suspended due to inactivity';
            $message = "Your account has not recorded any login for over 36 months and has been"
                . " <strong>automatically suspended</strong> in accordance with our GDPR policy"
                . " (Art. 5 §1e — data minimisation).<br><br>"
                . "Your personal data will be permanently deleted on <strong>{$safeDate}</strong>."
                . " You have 30 days to reactivate your account by clicking the button below.<br><br>"
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'If you no longer use this account, no action is required —'
                . ' your data will be deleted on the date shown above.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Account suspended',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Notifie l'utilisateur que son compte vient d'être définitivement supprimé.
     * Envoyé juste avant l'anonymisation RGPD — dernière communication possible.
     *
     * @param string $to   Destinataire
     * @param string $name Nom affiché
     * @param string $lang Langue (fr|en)
     * @return void
     */
    public function sendFinalDeletion(string $to, string $name, string $lang): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES);

        if ($lang === 'fr') {
            $subject = 'Votre compte Crabitan a été définitivement supprimé';
            $message = "Conformément à notre politique RGPD (Art. 17), votre compte et l'ensemble"
                . " de vos données personnelles (nom, e-mail, adresses…) ont été"
                . " <strong>définitivement supprimés</strong>.<br><br>"
                . "Vos commandes sont conservées 10 ans conformément aux obligations légales"
                . " comptables (Art. L123-22 Code de commerce), mais anonymisées —"
                . " sans aucun lien vers votre identité.<br><br>"
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'Si vous souhaitez créer un nouveau compte à l\'avenir, rendez-vous sur notre site.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Compte supprimé',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = 'Your Crabitan account has been permanently deleted';
            $message = "In accordance with our GDPR policy (Art. 17), your account and all"
                . " your personal data (name, email, addresses…) have been"
                . " <strong>permanently deleted</strong>.<br><br>"
                . "Your orders are retained for 10 years as required by accounting law,"
                . " but anonymised — with no link to your identity.<br><br>"
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'If you wish to create a new account in the future, visit our website.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Account deleted',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie la demande de confirmation de changement de mot de passe.
     * Le mot de passe n'est PAS encore modifié — il sera appliqué uniquement après clic sur "C'était moi".
     *
     * @param string $to           Destinataire
     * @param string $name         Nom affiché
     * @param string $lang         Langue (fr|en)
     * @param string $confirmToken Token brut (non haché) pour les deux CTAs
     * @return void
     */
    public function sendPasswordChangeConfirmation(
        string $to,
        string $name,
        string $lang,
        string $confirmToken = ''
    ): void {
        $subject = $lang === 'fr'
            ? 'Confirmez votre changement de mot de passe'
            : 'Confirm your password change';

        $appUrl   = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $safeDate = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

        $confirmUrl = $confirmToken !== ''
            ? htmlspecialchars(
                $appUrl . '/' . $lang . '/securite/confirmer?token=' . urlencode($confirmToken),
                ENT_QUOTES
            )
            : '';
        $denyUrl = $confirmToken !== ''
            ? htmlspecialchars(
                $appUrl . '/' . $lang . '/securite/ce-netait-pas-moi?token=' . urlencode($confirmToken),
                ENT_QUOTES
            )
            : htmlspecialchars($appUrl . '/' . $lang . '/mot-de-passe-oublie', ENT_QUOTES);

        $confirmLabel = $lang === 'fr' ? 'C\'était moi — Appliquer le changement' : 'This was me — Apply the change';
        $notMeLabel   = $lang === 'fr'
            ? 'Ce n\'était pas moi — Bloquer et révoquer mes sessions'
            : 'This wasn\'t me — Block and revoke my sessions';

        $ctaBlock = $confirmUrl !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
              . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
              . "<a href=\"{$confirmUrl}\" style=\""
              . self::BTN_STYLE_PRIMARY . self::BTN_STYLE_LINK . $confirmLabel
              . '</a></td></tr></table>'
              . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
              . '<tr><td>'
              . "<a href=\"{$denyUrl}\" style=\"display:inline-block;padding:10px 24px;"
              . self::BTN_STYLE_SECONDARY
              . 'color:#c0392b;text-decoration:underline;">'
              . $notMeLabel
              . '</a></td></tr></table>'
            : '';

        if ($lang === 'fr') {
            $message = "Une demande de changement de mot de passe a été initiée le <strong>{$safeDate}</strong>"
                . ' depuis votre compte.<br><br>'
                . '<strong>Votre mot de passe n\'a pas encore été modifié.</strong>'
                . ' Cliquez sur "C\'était moi" pour appliquer le changement.<br><br>'
                . 'Si vous n\'êtes pas à l\'origine de cette demande, cliquez sur "Ce n\'était pas moi"'
                . ' — la demande sera annulée et toutes vos sessions révoquées immédiatement.'
                . '<br><br>'
                . $ctaBlock;
            $body = $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_FR, "Bonjour {$safeName},", $message, 'fr');
        } else {
            $message = "A password change request was initiated on <strong>{$safeDate}</strong>"
                . ' from your account.<br><br>'
                . '<strong>Your password has not been changed yet.</strong>'
                . ' Click "This was me" to apply the change.<br><br>'
                . 'If you did not initiate this request, click "This wasn\'t me"'
                . ' — the request will be cancelled and all your sessions immediately revoked.'
                . '<br><br>'
                . $ctaBlock;
            $body = $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_EN, "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Alerte de sécurité après refus du changement de mot de passe ("Ce n'était pas moi") :
     * confirme que la demande a été annulée et les sessions révoquées.
     *
     * @param string $to   Destinataire
     * @param string $lang Langue (fr|en)
     * @return void
     */
    public function sendPasswordChangeDenied(string $to, string $lang): void
    {
        $subject  = $lang === 'fr'
            ? 'Sécurité — Demande de changement de mot de passe annulée'
            : 'Security — Password change request cancelled';
        $appUrl   = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $safeDate = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);
        $loginUrl = htmlspecialchars($appUrl . '/' . $lang . '/connexion', ENT_QUOTES);

        if ($lang === 'fr') {
            $message = "La demande de changement de mot de passe du <strong>{$safeDate}</strong>"
                . ' a bien été annulée.<br><br>'
                . 'Toutes vos sessions actives ont été révoquées par mesure de sécurité.<br><br>'
                . 'Si vous pensez que votre compte est compromis, réinitialisez votre mot de passe'
                . ' depuis la page de connexion.<br><br>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
                . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                . "<a href=\"{$loginUrl}\" style=\""
                . self::BTN_STYLE_PRIMARY . self::BTN_STYLE_LINK . 'Se connecter'
                . '</a></td></tr></table>';
            $body = $this->emailSimpleLayout(
                'Alerte de sécurité',
                'Votre compte est protégé.',
                $message,
                'fr'
            );
        } else {
            $message = "The password change request from <strong>{$safeDate}</strong>"
                . ' has been cancelled.<br><br>'
                . 'All your active sessions have been revoked as a security measure.<br><br>'
                . 'If you believe your account is compromised, reset your password from the sign-in page.<br><br>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
                . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                . "<a href=\"{$loginUrl}\" style=\""
                . self::BTN_STYLE_PRIMARY . self::BTN_STYLE_LINK . 'Sign in'
                . '</a></td></tr></table>';
            $body = $this->emailSimpleLayout(
                'Security alert',
                'Your account is protected.',
                $message,
                'en'
            );
        }

        $this->send($to, $to, $subject, $body);
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

        $appUrl      = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
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
        $safeDate    = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

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
              . "<a href=\"{$confirmUrl}\" style=\""
              . self::BTN_STYLE_PRIMARY
              . self::BTN_STYLE_LINK
              . $confirmLabel
              . '</a></td></tr></table>'
              . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
              . '<tr><td>'
              . "<a href=\"{$revokeUrl}\" style=\"display:inline-block;padding:10px 24px;"
              . self::BTN_STYLE_SECONDARY
              . 'color:#c0392b;text-decoration:underline;">'
              . $cancelLabel
              . '</a></td></tr></table>'
            : '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
              . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
              . "<a href=\"{$securityUrl}\" style=\""
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
            $body = $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_FR, "Bonjour {$safeName},", $message, 'fr');
        } else {
            $message = 'A sign-in from an unrecognised device was detected on your account.'
                . ' If this was you, confirm the device to stop receiving these alerts.'
                . '<br><br>'
                . $infoBlock
                . $confirmBlock;
            $body = $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_EN, "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    public function buildNewsletterHtml(
        string $title,
        string $htmlContent,
        ?string $imageUrl = null,
        ?string $unsubToken = null,
        string $lang = 'fr',
        ?int $campaignId = null
    ): string {
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;
        $urlCgv     = $appUrl . self::URL_CGV;
        $cidParam   = $campaignId !== null ? '&cid=' . $campaignId : '';
        $urlUnsub   = $unsubToken !== null
            ? $appUrl . '/' . $lang . '/newsletter/desabonnement?token=' . urlencode($unsubToken) . $cidParam
            : $appUrl . '/' . $lang . '/mon-compte';
        $safeTitle  = htmlspecialchars($title, ENT_QUOTES);
        // Image optionnelle : centrée dans le bloc blanc, avant le séparateur/désabonnement
        $safeImage = $imageUrl !== null ? htmlspecialchars($imageUrl, ENT_QUOTES) : null;
        $imageHtml = $safeImage !== null
            ? "<img src=\"{$safeImage}\" alt=\"\" width=\"192\""
              . " style=\"display:block;width:192px;max-width:100%;height:auto;"
              . "margin:24px auto 0;border:0;border-radius:4px;\">"
            : '';
        // Pixel de tracking d'ouverture (1×1 GIF invisible)
        $pixelHtml = '';
        if ($campaignId !== null && $unsubToken !== null) {
            $pixelSrc  = htmlspecialchars(
                $appUrl . '/track/open/' . $campaignId . '/' . rawurlencode($unsubToken),
                ENT_QUOTES
            );
            $pixelHtml = "\n              <img src=\"{$pixelSrc}\" width=\"1\" height=\"1\" alt=\"\""
                . " style=\"display:block;width:1px;height:1px;border:0;\">";
        }

        $unsubInfo  = $lang === 'en'
            ? 'You are receiving this email because you subscribed to the Château Crabitan Bellevue newsletter.'
            : 'Vous recevez cet email car vous êtes abonné(e) à la newsletter du Château Crabitan Bellevue.';
        $unsubLink  = $lang === 'en' ? 'Unsubscribe from the newsletter' : 'Se désinscrire de la newsletter';

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $urlCgv, $lang);

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
              {$pixelHtml}
              <!-- Divider -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px;">
                <tr><td style="border-top:1px solid #ede8df;padding-bottom:20px;"></td></tr>
              </table>
              <p style="margin:0;font-size:12px;color:#8a7a60;line-height:1.6;">
                {$unsubInfo}<br>
                <a href="{$urlUnsub}" style="color:#c9a84c;">{$unsubLink}</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          {$footerHtml}

INNER;

        return $this->emailWrap($safeTitle, $inner);
    }

    /**
     * Envoie le récapitulatif de commande au client après validation du paiement.
     *
     * @param string               $toEmail         Adresse email du client
     * @param string               $toName          Prénom ou nom complet du client
     * @param string               $orderRef        Référence de la commande (ex. CB-2026-001)
     * @param string               $paymentMethod   Méthode de paiement (card, virement, cheque)
     * @param array<int, array<string, mixed>> $items Articles de la commande [{name, qty, price}]
     * @param float                $total           Total TTC en euros
     * @param string               $lang            Langue du client ('fr' ou 'en')
     * @return void
     */
    public function sendOrderConfirmationToClient( // NOSONAR — php:S107 : 8 paramètres requis pour composer l'email de confirmation complet
        string $toEmail,
        string $toName,
        string $orderRef,
        string $paymentMethod,
        array $items,
        float $total,
        string $lang,
        float $shippingDiscount = 0.0
    ): void {
        $safeRef  = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeName = htmlspecialchars($toName, ENT_QUOTES);
        $appUrl   = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $ordersUrl = htmlspecialchars(
            $appUrl . '/' . $lang . '/mon-compte/commandes',
            ENT_QUOTES
        );

        if ($lang === 'en') {
            $subject        = "Your order {$safeRef} — Château Crabitan Bellevue";
            $title          = 'Order confirmed';
            $greeting       = "Hello {$safeName},";
            $colName        = 'Product';
            $colQty         = 'Qty';
            $colUnit        = self::COL_UNIT_EN;
            $colSub         = 'Subtotal';
            $labelTotal     = 'Total incl. tax';
            $labelDiscount  = 'Delivery discount';
            $labelPayment   = 'Payment method';
            $labelDelay     = 'Estimated delivery';
            $delayValue     = '7 to 15 days upon receipt of payment';
            $labelOrders    = 'My orders';
            $paymentLabels  = [
                'card'     => 'Credit card',
                'virement' => 'Bank transfer',
                'cheque'   => 'Cheque',
            ];
            $withdrawalNote = '<br><em style="font-size:12px;color:#8a7a60;">Under applicable consumer law, you have 14 days from receipt to exercise your right of withdrawal.</em>'; // phpcs:ignore Generic.Files.LineLength
            $deferredNote   = in_array($paymentMethod, ['virement', 'cheque'], true)
                ? '<br><em>Your order will be dispatched upon receipt of payment.</em>'
                : '';
            if ($paymentMethod === 'virement') {
                $deferredNote .= '<br><br><strong>Bank details:</strong>'
                    . '<br>Beneficiary: G.F.A Bernard Solane &amp; Fils'
                    . '<br>IBAN: FR76 3000 6000 0112 3456 7890 189'
                    . "<br>Reference: {$safeRef}";
            } elseif ($paymentMethod === 'cheque') {
                $deferredNote .= '<br><br><strong>Cheque payable to:</strong>'
                    . '<br>G.F.A Bernard Solane &amp; Fils'
                    . '<br>1 Château Crabitan Bellevue'
                    . '<br>33410 Sainte-Croix-du-Mont, France'
                    . "<br>Please write your reference on the back: {$safeRef}";
            }
        } else {
            $subject        = "Votre commande {$safeRef} — Château Crabitan Bellevue";
            $title          = 'Commande confirmée';
            $greeting       = "Bonjour {$safeName},";
            $colName        = 'Produit';
            $colQty         = 'Qté';
            $colUnit        = self::COL_UNIT_FR;
            $colSub         = 'Sous-total';
            $labelTotal     = 'Total TTC';
            $labelDiscount  = 'Remise livraison';
            $labelPayment   = 'Méthode de paiement';
            $labelDelay     = 'Délai estimé';
            $delayValue     = '7 à 15 jours à compter de la réception du paiement';
            $labelOrders    = 'Mes commandes';
            $paymentLabels  = [
                'card'     => 'Carte bancaire',
                'virement' => 'Virement bancaire',
                'cheque'   => self::PAYMENT_LABEL_CHEQUE,
            ];
            $withdrawalNote = '<br><em style="font-size:12px;color:#8a7a60;">Conformément à l\'art. L221-18 du Code de la consommation, vous disposez de 14 jours pour exercer votre droit de rétractation à compter de la réception.</em>'; // phpcs:ignore Generic.Files.LineLength
            $deferredNote   = in_array($paymentMethod, ['virement', 'cheque'], true)
                ? '<br><em>Commande expédiée dès réception du paiement.</em>'
                : '';
            if ($paymentMethod === 'virement') {
                $deferredNote .= '<br><br><strong>Coordonnées bancaires :</strong>'
                    . '<br>Bénéficiaire : G.F.A Bernard Solane &amp; Fils'
                    . '<br>IBAN : FR76 3000 6000 0112 3456 7890 189'
                    . "<br>Référence à indiquer : {$safeRef}";
            } elseif ($paymentMethod === 'cheque') {
                $deferredNote .= '<br><br><strong>Chèque à l\'ordre de :</strong>'
                    . '<br>G.F.A Bernard Solane &amp; Fils'
                    . '<br>1 Château Crabitan Bellevue'
                    . '<br>33410 Sainte-Croix-du-Mont'
                    . "<br>Merci d'indiquer au dos : {$safeRef}";
            }
        }

        $paymentLabel = $paymentLabels[$paymentMethod] ?? htmlspecialchars($paymentMethod, ENT_QUOTES);
        $itemsHtml    = $this->buildOrderItemsTable(
            $items,
            $total,
            $colName,
            $colQty,
            $colUnit,
            $colSub,
            $labelTotal,
            $shippingDiscount,
            $labelDiscount
        );

        $message = $itemsHtml
            . "<br><strong>{$labelPayment} :</strong> {$paymentLabel}{$deferredNote}"
            . "<br><strong>{$labelDelay} :</strong> {$delayValue}"
            . "<br>{$withdrawalNote}"
            . '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$ordersUrl}\" style=\""
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . $labelOrders
            . '</a></td></tr></table>';

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($toEmail, $toName, $subject, $body);
    }

    /**
     * Notifie le propriétaire de la boutique qu'une nouvelle commande a été passée.
     *
     * @param string               $clientEmail   Adresse email du client
     * @param string               $clientName    Prénom ou nom complet du client
     * @param string               $orderRef      Référence de la commande (ex. CB-2026-001)
     * @param string               $paymentMethod Méthode de paiement (card, virement, cheque)
     * @param array<int, array<string, mixed>> $items Articles de la commande [{name, qty, price}]
     * @param float                $total         Total TTC en euros
     * @return void
     */
    public function sendOrderConfirmationToOwner(
        string $clientEmail,
        string $clientName,
        string $orderRef,
        string $paymentMethod,
        array $items,
        float $total,
        float $shippingDiscount = 0.0
    ): void {
        $ownerEmail    = $_ENV['CONTACT_OWNER_EMAIL'] ?? 'crabitan.bellevue@orange.fr';
        $safeRef       = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeClient    = htmlspecialchars($clientName, ENT_QUOTES);
        $safeEmail     = htmlspecialchars($clientEmail, ENT_QUOTES);
        $paymentLabels = [
            'card'     => 'Carte bancaire',
            'virement' => 'Virement bancaire',
            'cheque'   => self::PAYMENT_LABEL_CHEQUE,
        ];
        $paymentLabel  = $paymentLabels[$paymentMethod] ?? htmlspecialchars($paymentMethod, ENT_QUOTES);

        $itemsHtml = $this->buildOrderItemsTable(
            $items,
            $total,
            'Produit',
            'Qté',
            self::COL_UNIT_FR,
            'Sous-total',
            'Total TTC',
            $shippingDiscount,
            'Remise livraison'
        );

        $message = "<strong>Client :</strong> {$safeClient} ({$safeEmail})<br>"
            . "<strong>Référence :</strong> {$safeRef}<br>"
            . "<strong>Paiement :</strong> {$paymentLabel}<br><br>"
            . $itemsHtml;

        $subject = "Nouvelle commande {$safeRef} — {$safeEmail}";
        $body    = $this->emailSimpleLayout(
            'Nouvelle commande',
            "Nouvelle commande reçue",
            $message,
            'fr'
        );
        $this->send($ownerEmail, APP_NAME, $subject, $body);
    }

    /**
     * Construit le tableau HTML des articles d'une commande pour les emails.
     *
     * @param array<int, array<string, mixed>> $items      Articles [{name, qty, price}]
     * @param float                            $total      Total TTC
     * @param string                           $colName    En-tête colonne nom
     * @param string                           $colQty     En-tête colonne quantité
     * @param string                           $colUnit    En-tête colonne prix unitaire
     * @param string                           $colSub     En-tête colonne sous-total
     * @param string                           $labelTotal Libellé ligne total
     * @return string HTML du tableau
     */
    private function buildOrderItemsTable( // NOSONAR — php:S107 : 9 paramètres requis pour générer le tableau articles email (colonnes i18n + remise)
        array $items,
        float $total,
        string $colName,
        string $colQty,
        string $colUnit,
        string $colSub,
        string $labelTotal,
        float $shippingDiscount = 0.0,
        string $labelDiscount = ''
    ): string {
        $tdStyle   = 'padding:6px 8px;border-bottom:1px solid #ede8df;font-size:14px;color:#3d3425;';
        $thStyle   = 'padding:8px;background:#f7f3ec;font-size:13px;'
            . 'color:#8a7a60;letter-spacing:1px;text-align:left;';
        $discountStyle = $tdStyle . 'color:#c9a84c;';
        $rows = '';
        foreach ($items as $item) {
            $name    = htmlspecialchars((string) ($item['name'] ?? $item['label_name'] ?? ''), ENT_QUOTES);
            $vintage = (int) ($item['vintage'] ?? 0);
            $vintageHtml = $vintage > 0
                ? ' <span style="font-size:12px;color:#8a7a60;">' . $vintage . '</span>'
                : '';
            $isCuvee = (bool) ($item['is_cuvee_speciale'] ?? false);
            $cuveeStyle = 'font-size:11px;letter-spacing:1.5px;'
                . 'text-transform:uppercase;color:#c9a84c;font-family:Georgia,serif;';
            $cuveeHtml  = $isCuvee ? "<br><span style=\"{$cuveeStyle}\">Cuvée Spéciale</span>" : '';
            $qty      = (int) ($item['qty'] ?? 0);
            $price    = (float) ($item['price'] ?? 0.0);
            $subtotal = number_format($qty * $price, 2, ',', ' ');
            $unitFmt  = number_format($price, 2, ',', ' ');
            $rows .= "<tr>"
                . "<td style=\"{$tdStyle}\">{$name}{$vintageHtml}{$cuveeHtml}</td>"
                . "<td style=\"{$tdStyle}\">{$qty}</td>"
                . "<td style=\"{$tdStyle}\">{$unitFmt} €</td>"
                . "<td style=\"{$tdStyle}\">{$subtotal} €</td>"
                . "</tr>";
        }
        $discountRow = '';
        if ($shippingDiscount > 0.0 && $labelDiscount !== '') {
            $discountFmt = number_format($shippingDiscount, 2, ',', ' ');
            $discountRow = "<tr>"
                . "<td colspan=\"3\" style=\"{$discountStyle}\"><em>{$labelDiscount}</em></td>"
                . "<td style=\"{$discountStyle}\"><em>−{$discountFmt} €</em></td>"
                . "</tr>";
        }
        $totalFmt = number_format($total, 2, ',', ' ');
        return '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<tr>'
            . "<th style=\"{$thStyle}\">{$colName}</th>"
            . "<th style=\"{$thStyle}\">{$colQty}</th>"
            . "<th style=\"{$thStyle}\">{$colUnit}</th>"
            . "<th style=\"{$thStyle}\">{$colSub}</th>"
            . '</tr>'
            . $rows
            . $discountRow
            . '<tr>'
            . "<td colspan=\"3\" style=\"{$tdStyle}\"><strong>{$labelTotal}</strong></td>"
            . "<td style=\"{$tdStyle}\"><strong>{$totalFmt} €</strong></td>"
            . '</tr>'
            . '</table>';
    }

    /**
     * Envoie un email transactionnel au client lors d'un changement de statut de commande.
     * N'envoie rien si le statut n'est pas dans la liste des déclencheurs.
     *
     * @param string  $toEmail         Adresse email du client
     * @param string  $toName          Prénom ou nom complet du client
     * @param string  $orderRef        Référence de la commande (ex. CB-2026-001)
     * @param string  $newStatus       Nouveau statut de la commande
     * @param string  $lang            Langue du client ('fr' ou 'en')
     * @param string  $appUrl          URL de base de l'application
     * @param array   $items           Articles commandés (optionnel)
     * @param array   $deliveryAddress Adresse de livraison (optionnel)
     * @param string  $trackingUrl     Lien de suivi transporteur (optionnel, affiché si shipped)
     * @param int     $orderId         Identifiant de la commande pour le CTA (optionnel)
     * @return void
     */
    public function sendOrderStatusEmail( // NOSONAR — php:S107 : 10 paramètres requis (statuts multiples, i18n, tracking, items)
        string $toEmail,
        string $toName,
        string $orderRef,
        string $newStatus,
        string $lang,
        string $appUrl,
        array $items = [],
        array $deliveryAddress = [],
        string $trackingUrl = '',
        int $orderId = 0
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
                $safeUrl,
                $items,
                $deliveryAddress,
                $trackingUrl,
                $orderId
            );
        } else {
            [$subject, $title, $greeting, $message] = $this->buildOrderStatusContentEn(
                $newStatus,
                $safeRef,
                $safeName,
                $safeUrl,
                $items,
                $deliveryAddress,
                $trackingUrl,
                $orderId
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
     * @param  string $status          Statut déclencheur
     * @param  string $safeRef         Référence commande échappée
     * @param  string $safeName        Nom du client échappé
     * @param  string $appUrl          URL de base
     * @param  array  $items           Articles commandés (optionnel)
     * @param  array  $deliveryAddress Adresse de livraison (optionnel)
     * @param  string $trackingUrl     Lien de suivi (affiché si shipped et non vide)
     * @param  int    $orderId         ID de la commande pour le CTA direct
     * @return array{string, string, string, string}  [subject, title, greeting, message]
     */
    private function buildOrderStatusContentFr( // NOSONAR — php:S107 : 8 paramètres requis (statuts FR + items + adresse + tracking)
        string $status,
        string $safeRef,
        string $safeName,
        string $appUrl,
        array $items = [],
        array $deliveryAddress = [],
        string $trackingUrl = '',
        int $orderId = 0
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

        $extraBlocks = '';

        // Récapitulatif des articles
        if (!empty($items)) {
            $total = array_sum(array_map(
                fn(array $i) => (float) ($i['price'] ?? 0.0) * (int) ($i['qty'] ?? 0),
                $items
            ));
            $extraBlocks .= '<br><br>' . $this->buildOrderItemsTable(
                $items,
                $total,
                'Produit',
                'Qté',
                'Prix unit.',
                'Sous-total',
                'Total'
            );
        }

        // Adresse de livraison
        if (!empty($deliveryAddress)) {
            $addr = $this->buildDeliveryAddressBlockFr($deliveryAddress);
            if ($addr !== '') {
                $extraBlocks .= '<br><br>' . $addr;
            }
        }

        // Lien de suivi (shipped uniquement)
        if ($status === 'shipped' && $trackingUrl !== '') {
            $safeTracking  = htmlspecialchars($trackingUrl, ENT_QUOTES);
            $extraBlocks  .= '<br><br>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
                . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                . "<a href=\"{$safeTracking}\" style=\"display:inline-block;padding:12px 28px;"
                . 'font-family:Georgia,serif;font-size:13px;letter-spacing:2px;text-transform:uppercase;'
                . self::BTN_STYLE_LINK
                . 'Suivre ma livraison</a>'
                . '</td></tr></table>';
        }

        // CTA "Voir ma commande"
        $orderPath = $orderId > 0
            ? "/fr/mon-compte/commandes/{$orderId}"
            : '/fr/mon-compte/commandes';
        $extraBlocks .= '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">'
            . '<tr><td style="border:1px solid #c9a84c;border-radius:2px;">'
            . "<a href=\"{$appUrl}{$orderPath}\" style=\"display:inline-block;padding:10px 24px;"
            . 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;text-transform:uppercase;'
            . 'color:#c9a84c;text-decoration:none;font-weight:bold;">'
            . 'Voir ma commande</a>'
            . '</td></tr></table>';

        return [
            $subjects[$status],
            $titles[$status],
            "Bonjour {$safeName},",
            $messages[$status] . $extraBlocks,
        ];
    }

    /**
     * Construit le contenu de l'email de statut commande en anglais.
     *
     * @param  string $status          Statut déclencheur
     * @param  string $safeRef         Référence commande échappée
     * @param  string $safeName        Nom du client échappé
     * @param  string $appUrl          URL de base
     * @param  array  $items           Articles commandés (optionnel)
     * @param  array  $deliveryAddress Adresse de livraison (optionnel)
     * @param  string $trackingUrl     Lien de suivi (affiché si shipped et non vide)
     * @param  int    $orderId         ID de la commande pour le CTA direct
     * @return array{string, string, string, string}  [subject, title, greeting, message]
     */
    private function buildOrderStatusContentEn( // NOSONAR — php:S107 : 8 paramètres requis (statuts EN + items + adresse + tracking)
        string $status,
        string $safeRef,
        string $safeName,
        string $appUrl,
        array $items = [],
        array $deliveryAddress = [],
        string $trackingUrl = '',
        int $orderId = 0
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

        $extraBlocks = '';

        // Items summary
        if (!empty($items)) {
            $total = array_sum(array_map(
                fn(array $i) => (float) ($i['price'] ?? 0.0) * (int) ($i['qty'] ?? 0),
                $items
            ));
            $extraBlocks .= '<br><br>' . $this->buildOrderItemsTable(
                $items,
                $total,
                'Product',
                'Qty',
                self::COL_UNIT_EN,
                'Subtotal',
                'Total'
            );
        }

        // Delivery address
        if (!empty($deliveryAddress)) {
            $addr = $this->buildDeliveryAddressBlockEn($deliveryAddress);
            if ($addr !== '') {
                $extraBlocks .= '<br><br>' . $addr;
            }
        }

        // Tracking link (shipped only)
        if ($status === 'shipped' && $trackingUrl !== '') {
            $safeTracking  = htmlspecialchars($trackingUrl, ENT_QUOTES);
            $extraBlocks  .= '<br><br>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
                . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
                . "<a href=\"{$safeTracking}\" style=\"display:inline-block;padding:12px 28px;"
                . 'font-family:Georgia,serif;font-size:13px;letter-spacing:2px;text-transform:uppercase;'
                . self::BTN_STYLE_LINK
                . 'Track my delivery</a>'
                . '</td></tr></table>';
        }

        // CTA "View my order"
        $orderPath = $orderId > 0
            ? "/en/my-account/orders/{$orderId}"
            : '/en/my-account/orders';
        $extraBlocks .= '<br><br>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">'
            . '<tr><td style="border:1px solid #c9a84c;border-radius:2px;">'
            . "<a href=\"{$appUrl}{$orderPath}\" style=\"display:inline-block;padding:10px 24px;"
            . 'font-family:Georgia,serif;font-size:13px;letter-spacing:1px;text-transform:uppercase;'
            . 'color:#c9a84c;text-decoration:none;font-weight:bold;">'
            . 'View my order</a>'
            . '</td></tr></table>';

        return [
            $subjects[$status],
            $titles[$status],
            "Hello {$safeName},",
            $messages[$status] . $extraBlocks,
        ];
    }

    /**
     * Notifie le propriétaire qu'une demande de retour a été enregistrée.
     *
     * @param array<string, mixed> $order
     */
    public function sendReturnRequestedToOwner(array $order, string $clientName, string $clientEmail): void
    {
        $ownerEmail   = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '';
        $safeRef      = htmlspecialchars($order['order_reference'] ?? '', ENT_QUOTES);
        $safeName     = htmlspecialchars($clientName, ENT_QUOTES);
        $safeEmail    = htmlspecialchars($clientEmail, ENT_QUOTES);
        $orderedAt    = isset($order['ordered_at']) ? date(self::DATE_FORMAT_DATE, strtotime((string) $order['ordered_at'])) : '—';
        $items        = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $appUrl       = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
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
     * Notifie les gérants qu'un remboursement GAE a échoué — action manuelle requise.
     *
     * @param string $orderRef Référence de la commande
     * @param float  $amount   Montant de la commande
     * @param int    $orderId  Identifiant de la commande (pour le lien admin)
     * @return void
     */
    public function sendRefundGaeKoToOwner(string $orderRef, float $amount, int $orderId = 0): void
    {
        $ownerEmail = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '';
        $safeRef    = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeAmount = number_format($amount, 2, ',', ' ');
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');

        $orderLink = $orderId > 0
            ? htmlspecialchars($appUrl . '/admin/commandes/' . $orderId, ENT_QUOTES)
            : '';
        $orderLinkBlock = $orderLink !== ''
            ? "<br><br><a href=\"{$orderLink}\" style=\"color:#c9a84c;\">Accéder à la commande dans l'administration</a>"
            : '';

        $subject = 'Remboursement GAE KO — action manuelle requise';
        $message = "Le remboursement CB (Crédit Agricole) de la commande <strong>{$safeRef}</strong> "
            . "({$safeAmount}&nbsp;€) a échoué côté serveur bancaire.<br><br>"
            . "La demande a automatiquement basculé en <strong>contrôle manuel</strong> conformément à la procédure "
            . "de traitement des incidents de paiement. Le statut de la commande est désormais "
            . "<em>Retour demandé</em> dans le back-office.<br><br>"
            . "<strong>Action requise :</strong> Traiter le remboursement manuellement via Vision Air, "
            . "puis mettre à jour le statut dans l'administration."
            . $orderLinkBlock;

        $body = $this->emailSimpleLayout(
            'Remboursement GAE KO',
            'Remboursement GAE KO — action manuelle requise',
            $message,
            'fr'
        );

        $this->send($ownerEmail, APP_NAME, $subject, $body);
    }

    /**
     * Notifie le propriétaire qu'une demande de remboursement manuel a été soumise (CA_ENABLE_GAE=false).
     *
     * @param array<string, mixed> $order       Données de la commande
     * @param string               $clientName  Nom du client
     * @param string               $clientEmail Email du client
     * @return void
     */
    public function sendRefundRequestedToOwner(array $order, string $clientName, string $clientEmail): void
    {
        $ownerEmail = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '';
        $safeRef    = htmlspecialchars($order['order_reference'] ?? '', ENT_QUOTES);
        $safeName   = htmlspecialchars($clientName, ENT_QUOTES);
        $safeEmail  = htmlspecialchars($clientEmail, ENT_QUOTES);
        $amount     = number_format((float) ($order['price'] ?? 0), 2, ',', ' ');
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $orderId    = (int) ($order['id'] ?? 0);
        $orderLink  = $orderId > 0
            ? htmlspecialchars($appUrl . '/admin/commandes/' . $orderId, ENT_QUOTES)
            : '';

        $orderLinkBlock = $orderLink !== ''
            ? "<br><br><a href=\"{$orderLink}\" style=\"color:#c9a84c;\">Voir la commande dans l'administration</a>"
            : '';

        $message = "Une demande de remboursement manuel a été soumise pour la commande <strong>{$safeRef}</strong>.<br><br>"
            . "<strong>Client :</strong> {$safeName} (<a href=\"mailto:{$safeEmail}\">{$safeEmail}</a>)<br>"
            . "<strong>Montant :</strong> {$amount}&nbsp;€<br>"
            . "<strong>Action requise :</strong> Traiter le remboursement dans Vision Air, puis confirmer dans l'administration."
            . $orderLinkBlock;

        $body = $this->emailSimpleLayout(
            'Remboursement à traiter',
            'Nouvelle demande de remboursement',
            $message,
            'fr'
        );

        $this->send($ownerEmail, APP_NAME, "Remboursement client — commande {$safeRef}", $body, null, $clientEmail);
    }

    /**
     * Envoie au propriétaire la liste des commandes sans mouvement depuis plus de $days jours.
     *
     * @param array<int, array<string, mixed>> $orders Commandes retournées par OrderModel::getStaleOrders()
     * @param int                              $days   Seuil de stagnation utilisé (pour le sujet du mail)
     * @return void
     */
    public function sendPendingOrdersAlert(array $orders, int $days = 7): void
    {
        $ownerEmail = $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '';
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $count      = count($orders);
        $statusLabels = [
            'pending'         => 'En attente',
            'paid'            => 'Payée',
            'processing'      => 'En préparation',
            'shipped'         => 'Expédiée',
            'return_requested' => 'Retour demandé',
            'refund_refused'  => 'Remboursement refusé',
        ];
        $paymentLabels = [
            'card'     => 'CB',
            'virement' => 'Virement',
            'cheque'   => self::PAYMENT_LABEL_CHEQUE,
        ];

        $rows = '';
        foreach ($orders as $order) {
            $ref       = htmlspecialchars($order['order_reference'] ?? '—', ENT_QUOTES);
            $status    = htmlspecialchars($statusLabels[$order['status'] ?? ''] ?? ($order['status'] ?? '—'), ENT_QUOTES);
            $price     = number_format((float) ($order['price'] ?? 0), 2, ',', ' ');
            $payment   = htmlspecialchars($paymentLabels[$order['payment_method'] ?? ''] ?? ($order['payment_method'] ?? '—'), ENT_QUOTES);
            $updatedAt = isset($order['updated_at']) ? date(self::DATE_FORMAT_DATE, strtotime((string) $order['updated_at'])) : '—';
            $orderId   = (int) ($order['id'] ?? 0);
            $link      = $orderId > 0
                ? "<a href=\"{$appUrl}/admin/commandes/{$orderId}\" style=\"color:#c9a84c;\">{$ref}</a>"
                : $ref;

            $rows .= "<tr style=\"border-bottom:1px solid #ede8df;\">"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$link}</td>"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$status}</td>"
                . "<td style=\"padding:4px 8px;text-align:right;font-size:13px;color:#3d3425;\">{$price}&nbsp;€</td>"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$payment}</td>"
                . "<td style=\"padding:4px 8px;font-size:13px;color:#3d3425;\">{$updatedAt}</td>"
                . "</tr>";
        }

        $tableHtml = "<table style=\"width:100%;border-collapse:collapse;margin-top:12px;\">"
            . "<thead><tr style=\"background:#f5f0e8;\">"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Référence</th>"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Statut</th>"
            . "<th style=\"padding:6px 8px;text-align:right;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Montant</th>"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Paiement</th>"
            . "<th style=\"padding:6px 8px;text-align:left;font-size:11px;letter-spacing:1px;color:#8a7a60;\">Dernière MAJ</th>"
            . "</tr></thead><tbody>{$rows}</tbody></table>";

        $message = "{$count} commande(s) sans mouvement depuis plus de {$days} jours :<br><br>"
            . $tableHtml
            . "<br><br><a href=\"{$appUrl}/admin/commandes\" style=\"color:#c9a84c;\">Accéder à la liste des commandes</a>";

        $body = $this->emailSimpleLayout(
            'Commandes bloquées',
            "Alerte — commandes bloquées ({$count})",
            $message,
            'fr'
        );

        $this->send($ownerEmail, APP_NAME, "Commandes bloquées — {$count} sans mouvement depuis {$days}j", $body);
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
        $orderedAt = isset($order['ordered_at']) ? date(self::DATE_FORMAT_DATE, strtotime((string) $order['ordered_at'])) : '—';
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

        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
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
            . "<a href=\"{$safeUrl}\" style=\""
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
            . "<a href=\"{$safeUrl}\" style=\""
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
     * Envoie l'email de bienvenue post-inscription (vérification d'email).
     *
     * Si $isSubscribed vaut false, un bouton CTA "S'abonner à la newsletter" est ajouté
     * afin d'inviter l'utilisateur à rejoindre la newsletter en un clic.
     *
     * @param string $email        Adresse email du destinataire
     * @param string $firstname    Prénom de l'utilisateur (peut être vide)
     * @param string $lang         Langue ('fr' ou 'en')
     * @param bool   $isSubscribed True si l'utilisateur est déjà abonné à la newsletter
     * @return void
     */
    public function sendWelcome(string $email, string $firstname, string $lang, bool $isSubscribed = false): void
    {
        $appUrl      = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $defaultName = $lang === 'fr' ? 'Madame, Monsieur' : 'Dear Customer';
        $safeName    = $firstname !== '' ? htmlspecialchars($firstname, ENT_QUOTES) : $defaultName;
        $greeting    = $lang === 'fr' ? "Bonjour {$safeName}," : "Hello {$safeName},";

        // CTA catalogue
        $catalogUrl = $lang === 'fr'
            ? htmlspecialchars("{$appUrl}/fr/vins", ENT_QUOTES)
            : htmlspecialchars("{$appUrl}/en/wines", ENT_QUOTES);
        $catalogLabel = $lang === 'fr' ? 'Découvrir notre catalogue' : 'Discover our catalogue';
        $catalogCta = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$catalogUrl}\" style=\"" . self::BTN_STYLE_PRIMARY . self::BTN_STYLE_LINK
            . $catalogLabel . '</a></td></tr></table>';

        // CTA newsletter conditionnel
        $newsletterCta = '';
        if (!$isSubscribed) {
            $subscribeUrl = $lang === 'fr'
                ? htmlspecialchars("{$appUrl}/fr/newsletter/inscription-bienvenue?email=" . urlencode($email), ENT_QUOTES)
                : htmlspecialchars("{$appUrl}/en/newsletter/welcome-subscribe?email=" . urlencode($email), ENT_QUOTES);
            $newsletterLabel = $lang === 'fr' ? "S'abonner à la newsletter" : 'Subscribe to the newsletter';
            $newsletterCta = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">'
                . '<tr><td style="border:1px solid #c9a84c;border-radius:2px;">'
                . "<a href=\"{$subscribeUrl}\" style=\"display:inline-block;padding:10px 24px;"
                . self::BTN_STYLE_SECONDARY
                . 'text-transform:uppercase;color:#c9a84c;text-decoration:none;font-weight:bold;">'
                . $newsletterLabel . '</a></td></tr></table>';
        }

        if ($lang === 'fr') {
            $subject = 'Bienvenue au Château Crabitan Bellevue';
            $message = "Votre adresse email est confirmée — bienvenue dans la famille du Château Crabitan Bellevue !<br><br>"
                . 'Niché au cœur de l\'appellation <strong>Sainte-Croix-du-Mont</strong>, notre château familial'
                . ' perpétue depuis des générations l\'art des vins de Bordeaux liquoreux et secs.'
                . ' Chaque bouteille est l\'expression d\'un terroir d\'exception et d\'un savoir-faire artisanal.<br><br>'
                . $catalogCta . '<br>'
                . $newsletterCta
                . '<br><em>L\'équipe du Château Crabitan Bellevue</em>';
        } else {
            $subject = 'Welcome to Château Crabitan Bellevue';
            $message = 'Your email address has been confirmed — welcome to the Château Crabitan Bellevue family!<br><br>'
                . 'Nestled in the heart of the <strong>Sainte-Croix-du-Mont</strong> appellation,'
                . ' our family estate has been crafting exceptional Bordeaux wines for generations.'
                . ' Each bottle reflects the unique character of our terroir and our dedication to artisanal winemaking.<br><br>'
                . $catalogCta . '<br>'
                . $newsletterCta
                . '<br><em>The Château Crabitan Bellevue team</em>';
        }

        $body = $this->emailSimpleLayout(
            $lang === 'fr' ? 'Bienvenue' : 'Welcome',
            $greeting,
            $message,
            $lang
        );

        $this->send($email, $firstname, $subject, $body);
    }

    /**
     * Envoie un email de bienvenue après inscription newsletter via le tunnel de commande.
     *
     * Contrairement au double opt-in (inscription indépendante), l'abonnement via checkout
     * est direct car le client est déjà authentifié et son email vérifié.
     *
     * @param string $to   Adresse email du destinataire
     * @param string $name Nom affiché dans l'email
     * @param string $lang Langue ('fr' ou 'en')
     * @return void
     */
    public function sendNewsletterWelcome(string $to, string $name, string $lang): void
    {
        $subject = $lang === 'fr'
            ? 'Bienvenue dans la newsletter Crabitan Bellevue'
            : 'Welcome to the Crabitan Bellevue newsletter';

        $defaultName = $lang === 'fr' ? 'Madame, Monsieur' : 'Dear Customer';
        $safeName    = $name !== '' ? htmlspecialchars($name, ENT_QUOTES) : $defaultName;
        $greeting = $lang === 'fr' ? "Bonjour {$safeName}," : "Hello {$safeName},";

        if ($lang === 'fr') {
            $message = "Vous êtes désormais inscrit(e) à la newsletter du Château Crabitan Bellevue.<br><br>"
                . "Vous recevrez en avant-première nos actualités : nouvelles cuvées, événements au château et offres exclusives.<br><br>"
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:24px;\">"
                . "Pour vous désinscrire à tout moment, utilisez le lien présent en bas de chaque newsletter.</p>";
        } else {
            $message = "You are now subscribed to the Château Crabitan Bellevue newsletter.<br><br>"
                . "You will receive our news first: new vintages, château events and exclusive offers.<br><br>"
                . "<p style=\"font-size:12px;color:#8a7a60;margin-top:24px;\">"
                . "To unsubscribe at any time, use the link at the bottom of each newsletter.</p>";
        }

        $body = $this->emailSimpleLayout(
            'Newsletter',
            $greeting,
            $message,
            $lang
        );

        $this->send($to, $name, $subject, $body);
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
            . "<a href=\"{$safeUrl}\" style=\""
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

    /**
     * Envoie une alerte email au mainteneur lorsqu'une signature IPN CA est invalide.
     *
     * Indique une possible rotation de la clé publique CA — mettre à jour CA_PUBKEY_PATH.
     *
     * @param string $to   Adresse du mainteneur (MAINTAINER_MAIL)
     * @param string $date Date/heure de l'IPN reçu
     * @param string $ip   Adresse IP source de l'IPN
     * @return void
     */
    /**
     * Envoie la facture PDF au client en pièce jointe.
     *
     * @param string $toEmail       Adresse email du client
     * @param string $toName        Prénom ou nom complet du client
     * @param string $orderRef      Référence de la commande
     * @param string $invoiceNumber Numéro de facture (WEB-INVOICE-YYYY-XXXX)
     * @param string $pdfPath       Chemin absolu vers le fichier PDF
     * @param string $lang          Langue du client ('fr' ou 'en')
     * @return void
     */
    public function sendInvoiceToClient(
        string $toEmail,
        string $toName,
        string $orderRef,
        string $invoiceNumber,
        string $pdfPath,
        string $lang
    ): void {
        $safeName = htmlspecialchars($toName, ENT_QUOTES);
        $safeRef  = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeNum  = htmlspecialchars($invoiceNumber, ENT_QUOTES);

        if ($lang === 'en') {
            $subject  = 'Your invoice ' . $invoiceNumber . ' — Château Crabitan Bellevue';
            $greeting = 'Hello ' . $safeName . ',';
            $message  = 'Please find attached your invoice <strong>' . $safeNum . '</strong>'
                . ' for order <strong>' . $safeRef . '</strong>.<br><br>'
                . 'You can also download it at any time from your account in the Orders section.';
        } else {
            $subject  = 'Votre facture ' . $invoiceNumber . ' — Château Crabitan Bellevue';
            $greeting = 'Bonjour ' . $safeName . ',';
            $message  = 'Veuillez trouver en pièce jointe votre facture <strong>' . $safeNum . '</strong>'
                . ' pour la commande <strong>' . $safeRef . '</strong>.<br><br>'
                . 'Vous pouvez également la télécharger à tout moment depuis votre espace client,'
                . ' rubrique Mes commandes.';
        }

        $body     = $this->emailSimpleLayout('Votre facture', $greeting, $message, $lang);
        $filename = $invoiceNumber . '.pdf';

        $this->send($toEmail, $toName, $subject, $body, $pdfPath, null, $filename);
    }

    /**
     * Notifie le client que son remboursement a été initié (statut refund_pending).
     *
     * Envoyée lorsque CA_ENABLE_GAE=false — le remboursement sera traité manuellement.
     *
     * @param string $toEmail  Adresse email du client
     * @param string $toName   Nom affiché du client
     * @param string $orderRef Référence de la commande
     * @param string $lang     Langue du client (fr|en)
     * @param string $appUrl   URL de base de l'application
     * @return void
     */
    public function sendRefundPendingEmail(
        string $toEmail,
        string $toName,
        string $orderRef,
        string $lang,
        string $appUrl
    ): void {
        $safeRef  = htmlspecialchars($orderRef, ENT_QUOTES);
        $safeName = htmlspecialchars($toName, ENT_QUOTES);
        $safeUrl  = rtrim($appUrl, '/');

        if ($lang === 'fr') {
            $subject  = "Votre remboursement a été initié — {$safeRef}";
            $title    = 'Remboursement en cours';
            $greeting = "Bonjour {$safeName},";
            $message  = "Votre demande de remboursement pour la commande <strong>{$safeRef}</strong> "
                . "a bien été prise en compte. Le remboursement sera traité dans les meilleurs délais.<br><br>"
                . "<a href=\"{$safeUrl}/fr/mon-compte/commandes\" style=\"color:#c9a84c;\">Voir mes commandes</a>";
        } else {
            $subject  = "Your refund has been initiated — {$safeRef}";
            $title    = 'Refund in progress';
            $greeting = "Hello {$safeName},";
            $message  = "Your refund request for order <strong>{$safeRef}</strong> has been received. "
                . "The refund will be processed as soon as possible.<br><br>"
                . "<a href=\"{$safeUrl}/en/my-account/orders\" style=\"color:#c9a84c;\">View my orders</a>";
        }

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($toEmail, $toName, $subject, $body);
    }

    public function sendIpnSignatureAlert(string $to, string $date, string $ip): void
    {
        $safeDate = htmlspecialchars($date, ENT_QUOTES);
        $safeIp   = htmlspecialchars($ip, ENT_QUOTES);

        $message = "Une notification IPN du Crédit Agricole (Up2pay e-Transactions) "
            . "a été reçue avec une <strong>signature RSA invalide</strong>.<br><br>"
            . "<strong>Date :</strong> {$safeDate}<br>"
            . "<strong>IP source :</strong> {$safeIp}<br><br>"
            . "Causes possibles :<br>"
            . "<ul style=\"margin:8px 0;padding-left:20px;\">"
            . "<li>CA a <strong>fait tourner sa paire de clés</strong> — mettre à jour "
            . "<code>CA_PUBKEY_PATH</code> avec la nouvelle clé publique.</li>"
            . "<li>Tentative d'IPN frauduleux (vérifier les logs serveur).</li>"
            . "</ul><br>"
            . "Tant que la clé n'est pas mise à jour, les paiements CB ne seront pas enregistrés.";

        $body = $this->emailSimpleLayout(
            'Alerte sécurité paiement',
            'Action requise — IPN CA signature invalide',
            $message,
            'fr'
        );

        $this->send($to, 'Mainteneur', '[ALERTE] IPN CA — signature invalide', $body);
    }

    /**
     * Alerte de sécurité : trop d'échecs de connexion admin détectés.
     *
     * Envoyée à l'admin concerné et/ou au mainteneur selon le contexte.
     * Si $targetName est fourni, le message mentionne le compte ciblé (email du mainteneur).
     *
     * @param string      $to         Adresse email du destinataire
     * @param string      $name       Nom affiché dans l'email
     * @param string      $lang       Langue (fr|en)
     * @param string      $ip         Adresse IP de l'attaquant
     * @param string|null $targetName Nom du compte admin ciblé (null si destinataire = admin lui-même)
     * @return void
     */
    public function sendAdminLoginFailureAlert(
        string $to,
        string $name,
        string $lang,
        string $ip,
        ?string $targetName = null
    ): void {
        $safeIp   = htmlspecialchars($ip, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $safeDate = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

        if ($lang === 'fr') {
            $subject = '[ALERTE] Tentatives de connexion admin suspectes — Crabitan Bellevue';
            if ($targetName !== null) {
                $safeTarget = htmlspecialchars($targetName, ENT_QUOTES);
                $intro      = "Des tentatives de connexion suspectes ont été détectées sur le compte admin "
                    . "<strong>{$safeTarget}</strong>.";
            } else {
                $intro = "Plusieurs tentatives de connexion suspectes ont été détectées sur votre compte administrateur.";
            }
            $action = $targetName !== null
                ? "Contactez <strong>" . htmlspecialchars($targetName, ENT_QUOTES) . "</strong> afin d'être sûr de la tentative de connexion."
                : "Si vous êtes à l'origine de ces tentatives, vous pouvez ignorer ce message.<br>"
                . "Dans le cas contraire, changez immédiatement votre mot de passe et signalez l'incident.";
            $message = "{$intro}<br><br>"
                . "<strong>Date :</strong> {$safeDate}<br>"
                . "<strong>IP source :</strong> {$safeIp}<br><br>"
                . $action;
            $body = $this->emailSimpleLayout(
                'Alerte sécurité admin',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = '[ALERT] Suspicious admin login attempts — Crabitan Bellevue';
            if ($targetName !== null) {
                $safeTarget = htmlspecialchars($targetName, ENT_QUOTES);
                $intro      = "Suspicious login attempts were detected on admin account <strong>{$safeTarget}</strong>.";
            } else {
                $intro = "Multiple suspicious login attempts were detected on your admin account.";
            }
            $action = $targetName !== null
                ? "Please contact <strong>" . htmlspecialchars($targetName, ENT_QUOTES)
                    . "</strong> to confirm whether the login attempt was legitimate."
                : "If you made these attempts, you can ignore this message.<br>Otherwise, change your password immediately and report the incident.";
            $message = "{$intro}<br><br>"
                . "<strong>Date:</strong> {$safeDate}<br>"
                . "<strong>Source IP:</strong> {$safeIp}<br><br>"
                . $action;
            $body = $this->emailSimpleLayout(
                'Admin security alert',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Alerte de sécurité : changement de mot de passe admin effectué.
     *
     * Envoyée à l'admin concerné et/ou au mainteneur selon le contexte.
     * Si $targetName est fourni, le message mentionne le compte ciblé.
     *
     * @param string      $to         Adresse email du destinataire
     * @param string      $name       Nom affiché dans l'email
     * @param string      $lang       Langue (fr|en)
     * @param string      $ip         Adresse IP de la requête
     * @param string      $userAgent  User-Agent du navigateur
     * @param string|null $targetName Nom du compte admin ciblé (null si destinataire = admin lui-même)
     * @return void
     */
    public function sendAdminPasswordChangeAlert(
        string $to,
        string $name,
        string $lang,
        string $ip,
        string $userAgent,
        ?string $targetName = null
    ): void {
        $safeIp    = htmlspecialchars($ip, ENT_QUOTES);
        $safeUa    = htmlspecialchars(substr($userAgent, 0, 200), ENT_QUOTES);
        $safeName  = htmlspecialchars($name, ENT_QUOTES);
        $safeDate  = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

        if ($lang === 'fr') {
            $subject = '[ALERTE] Mot de passe admin modifié — Crabitan Bellevue';
            if ($targetName !== null) {
                $safeTarget = htmlspecialchars($targetName, ENT_QUOTES);
                $intro      = "Le mot de passe du compte admin <strong>{$safeTarget}</strong> vient d'être modifié.";
            } else {
                $intro = "Le mot de passe de votre compte administrateur vient d'être modifié.";
            }
            $message = "{$intro}<br><br>"
                . "<strong>Date :</strong> {$safeDate}<br>"
                . "<strong>IP :</strong> {$safeIp}<br>"
                . "<strong>Navigateur :</strong> {$safeUa}<br><br>"
                . "Si vous êtes à l'origine de cette action, vous pouvez ignorer ce message.<br>"
                . "Dans le cas contraire, contactez immédiatement le mainteneur.";
            $body = $this->emailSimpleLayout(
                'Alerte sécurité admin',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = '[ALERT] Admin password changed — Crabitan Bellevue';
            if ($targetName !== null) {
                $safeTarget = htmlspecialchars($targetName, ENT_QUOTES);
                $intro      = "The password for admin account <strong>{$safeTarget}</strong> was just changed.";
            } else {
                $intro = "Your admin account password was just changed.";
            }
            $message = "{$intro}<br><br>"
                . "<strong>Date:</strong> {$safeDate}<br>"
                . "<strong>IP:</strong> {$safeIp}<br>"
                . "<strong>Browser:</strong> {$safeUa}<br><br>"
                . "If you made this change, you can ignore this message.<br>"
                . "Otherwise, contact the maintainer immediately.";
            $body = $this->emailSimpleLayout(
                'Admin security alert',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Notifie un admin que son compte a été bloqué par un super_admin.
     *
     * @param string $to          Email du compte bloqué
     * @param string $name        Nom affiché du compte bloqué
     * @param string $lang        Langue (fr|en)
     * @param string $blockerName Prénom + nom du super_admin ayant effectué le blocage
     * @return void
     */
    public function sendAdminAccountBlocked(string $to, string $name, string $lang, string $blockerName): void
    {
        $safeName    = htmlspecialchars($name, ENT_QUOTES);
        $safeBlocker = htmlspecialchars($blockerName, ENT_QUOTES);
        $safeDate    = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

        if ($lang === 'fr') {
            $subject = '[ACTION] Votre compte administrateur a été bloqué — Crabitan Bellevue';
            $message = "Votre compte administrateur a été bloqué le <strong>{$safeDate}</strong> "
                . "par <strong>{$safeBlocker}</strong>.<br><br>"
                . "Vous ne pouvez plus vous connecter au back-office.<br><br>"
                . "Pour retrouver l'accès, demandez à votre super administrateur de débloquer "
                . "votre compte depuis la page <em>Gestion des admins</em>.";
            $body = $this->emailSimpleLayout('Compte bloqué', "Bonjour {$safeName},", $message, 'fr');
        } else {
            $subject = '[ACTION] Your administrator account has been blocked — Crabitan Bellevue';
            $message = "Your administrator account was blocked on <strong>{$safeDate}</strong> "
                . "by <strong>{$safeBlocker}</strong>.<br><br>"
                . "You can no longer sign in to the back-office.<br><br>"
                . "To restore access, ask your super administrator to unblock your account "
                . "from the <em>Admin management</em> page.";
            $body = $this->emailSimpleLayout('Account blocked', "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Notifie un admin que son compte a été débloqué par un super_admin.
     *
     * @param string $to           Email du compte débloqué
     * @param string $name         Nom affiché du compte débloqué
     * @param string $lang         Langue (fr|en)
     * @param string $unblockerName Prénom + nom du super_admin ayant effectué le déblocage
     * @return void
     */
    public function sendAdminAccountUnblocked(string $to, string $name, string $lang, string $unblockerName): void
    {
        $safeName      = htmlspecialchars($name, ENT_QUOTES);
        $safeUnblocker = htmlspecialchars($unblockerName, ENT_QUOTES);
        $safeDate      = htmlspecialchars(date(self::DATE_FORMAT_FR), ENT_QUOTES);

        if ($lang === 'fr') {
            $subject = '[INFO] Votre compte administrateur a été débloqué — Crabitan Bellevue';
            $message = "Votre compte administrateur a été débloqué le <strong>{$safeDate}</strong> "
                . "par <strong>{$safeUnblocker}</strong>.<br><br>"
                . "Vous pouvez à nouveau vous connecter au back-office.";
            $body = $this->emailSimpleLayout('Compte débloqué', "Bonjour {$safeName},", $message, 'fr');
        } else {
            $subject = '[INFO] Your administrator account has been unblocked — Crabitan Bellevue';
            $message = "Your administrator account was unblocked on <strong>{$safeDate}</strong> "
                . "by <strong>{$safeUnblocker}</strong>.<br><br>"
                . "You can sign in to the back-office again.";
            $body = $this->emailSimpleLayout('Account unblocked', "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie l'archive RGPD (ZIP ou JSON) à l'adresse email du titulaire du compte.
     *
     * Utilise addStringAttachment() pour joindre le contenu binaire en mémoire,
     * sans écrire de fichier temporaire sur le disque.
     *
     * @param string $to          Adresse email du destinataire
     * @param string $name        Prénom ou nom complet
     * @param string $zipContent  Contenu binaire du fichier à joindre
     * @param string $filename    Nom du fichier joint (ex : mes-donnees-2026-04-06.zip)
     * @param string $lang        Langue du compte ('fr' ou 'en')
     * @return void
     */
    public function sendRgpdExport(
        string $to,
        string $name,
        string $zipContent,
        string $filename,
        string $lang
    ): void {
        $safeName = htmlspecialchars($name, ENT_QUOTES);

        if ($lang === 'fr') {
            $subject  = 'Vos données personnelles — RGPD Art. 20';
            $title    = 'Export RGPD';
            $greeting = "Bonjour {$safeName},";
            $message  = 'Suite à votre demande, votre archive de données personnelles est jointe à cet email '
                . '(RGPD Art. 20 — droit à la portabilité).<br><br>'
                . 'Elle contient l\'ensemble des données enregistrées sur votre compte Crabitan Bellevue.<br><br>'
                . '<strong>Conservez ce fichier en lieu sûr</strong> et supprimez cet email après téléchargement.';
        } else {
            $subject  = 'Your personal data — GDPR Art. 20';
            $title    = 'GDPR Export';
            $greeting = "Hello {$safeName},";
            $message  = 'Following your request, your personal data archive is attached to this email '
                . '(GDPR Art. 20 — right to data portability).<br><br>'
                . 'It contains all data stored on your Crabitan Bellevue account.<br><br>'
                . '<strong>Keep this file in a safe place</strong> and delete this email after downloading.';
        }

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);

        $this->mailer->clearAddresses();
        $this->mailer->clearAttachments();
        $this->mailer->clearReplyTos();
        $this->mailer->addAddress($to, $name);
        $this->mailer->isHTML(true);
        $this->mailer->Subject = $subject;
        $this->mailer->Body    = $body;
        $this->mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        $this->mailer->addStringAttachment($zipContent, $filename);
        $this->mailer->send();
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
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;
        $urlCgv     = $appUrl . self::URL_CGV;

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $urlCgv, $lang);

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

    /**
     * Construit le bloc HTML d'adresse de livraison pour les emails FR.
     *
     * @param  array $address Tableau avec les clés : firstname, lastname, line1, line2, zip, city, country
     * @return string HTML du bloc adresse, vide si données insuffisantes
     */
    private function buildDeliveryAddressBlockFr(array $address): string
    {
        $city = trim((string) ($address['city'] ?? ''));
        if ($city === '') {
            return '';
        }
        $name    = trim(
            htmlspecialchars((string) ($address['firstname'] ?? ''), ENT_QUOTES)
            . ' '
            . htmlspecialchars((string) ($address['lastname'] ?? ''), ENT_QUOTES)
        );
        $line1   = htmlspecialchars((string) ($address['line1'] ?? ''), ENT_QUOTES);
        $line2   = htmlspecialchars((string) ($address['line2'] ?? ''), ENT_QUOTES);
        $zip     = htmlspecialchars((string) ($address['zip'] ?? ''), ENT_QUOTES);
        $safeCity    = htmlspecialchars($city, ENT_QUOTES);
        $country = htmlspecialchars((string) ($address['country'] ?? ''), ENT_QUOTES);

        $lines = array_filter([$name, $line1, $line2, trim("{$zip} {$safeCity}"), $country]);
        $addrHtml = implode('<br>', $lines);

        return '<p style="margin:0 0 8px;font-size:12px;letter-spacing:2px;text-transform:uppercase;'
            . 'color:#8a7a60;">Adresse de livraison</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;color:#3d3425;">' . $addrHtml . '</p>';
    }

    /**
     * Construit le bloc HTML d'adresse de livraison pour les emails EN.
     *
     * @param  array $address Tableau avec les clés : firstname, lastname, line1, line2, zip, city, country
     * @return string HTML du bloc adresse, vide si données insuffisantes
     */
    private function buildDeliveryAddressBlockEn(array $address): string
    {
        $city = trim((string) ($address['city'] ?? ''));
        if ($city === '') {
            return '';
        }
        $name    = trim(
            htmlspecialchars((string) ($address['firstname'] ?? ''), ENT_QUOTES)
            . ' '
            . htmlspecialchars((string) ($address['lastname'] ?? ''), ENT_QUOTES)
        );
        $line1   = htmlspecialchars((string) ($address['line1'] ?? ''), ENT_QUOTES);
        $line2   = htmlspecialchars((string) ($address['line2'] ?? ''), ENT_QUOTES);
        $zip     = htmlspecialchars((string) ($address['zip'] ?? ''), ENT_QUOTES);
        $safeCity    = htmlspecialchars($city, ENT_QUOTES);
        $country = htmlspecialchars((string) ($address['country'] ?? ''), ENT_QUOTES);

        $lines = array_filter([$name, $line1, $line2, trim("{$zip} {$safeCity}"), $country]);
        $addrHtml = implode('<br>', $lines);

        return '<p style="margin:0 0 8px;font-size:12px;letter-spacing:2px;text-transform:uppercase;'
            . 'color:#8a7a60;">Delivery address</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;color:#3d3425;">' . $addrHtml . '</p>';
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

    private function googleAccountInfoBodyFr(string $name): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $message  = 'Vous avez tenté de réinitialiser votre mot de passe, mais votre compte Crabitan Bellevue'
            . ' est associé à Google — il ne possède pas de mot de passe.'
            . '<br><br>Pour vous connecter, cliquez sur le bouton <strong>« Continuer avec Google »</strong>'
            . ' sur la page de connexion.';
        return $this->emailSimpleLayout(
            'Information sur votre compte',
            "Bonjour {$safeName},",
            $message,
            'fr'
        );
    }

    private function googleAccountInfoBodyEn(string $name): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $message  = 'You requested a password reset, but your Crabitan Bellevue account is linked to Google'
            . ' — it does not have a password.'
            . '<br><br>To sign in, click the <strong>"Continue with Google"</strong> button'
            . ' on the login page.';
        return $this->emailSimpleLayout(
            'Account information',
            "Hello {$safeName},",
            $message,
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
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $logoUrl    = $appUrl . self::LOGO_PATH;
        $urlPrivacy = $appUrl . self::URL_PRIVACY;
        $urlLegal   = $appUrl . self::URL_LEGAL;
        $urlSupport = $appUrl . self::URL_SUPPORT;
        $urlCgv     = $appUrl . self::URL_CGV;

        $headerHtml = $this->emailHeaderHtml($appUrl, $logoUrl);
        $footerHtml = $this->emailFooterHtml($urlPrivacy, $urlLegal, $urlSupport, $urlCgv, $lang);

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

    protected function newRatingModel(): \Model\RatingModel
    {
        return new \Model\RatingModel();
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
     * @param string $urlCgv     URL vers les conditions générales de vente
     * @param string $lang       Langue du destinataire ('fr' ou 'en')
     * @return string HTML du footer email
     */
    private function emailFooterHtml(
        string $urlPrivacy,
        string $urlLegal,
        string $urlSupport,
        string $urlCgv,
        string $lang = 'fr'
    ): string {
        $year     = date('Y');
        $mention  = $lang === 'fr'
            ? "L'abus d'alcool est dangereux pour la santé. À consommer avec modération."
            : 'Alcohol abuse is dangerous for your health. To be consumed in moderation.';
        $autoMsg  = $lang === 'fr'
            ? 'Ce mail est généré automatiquement. Veuillez ne pas y répondre.'
            : 'This email was generated automatically. Please do not reply.';
        $phishing = $lang === 'fr'
            // phpcs:ignore Generic.Files.LineLength
            ? 'Château Crabitan Bellevue ne vous demandera jamais votre mot de passe ni vos coordonnées bancaires par email.'
            // phpcs:ignore Generic.Files.LineLength
            : 'Château Crabitan Bellevue will never ask for your password or banking details by email.';
        return <<<HTML
          <tr>
            <td align="center" style="padding-top:24px;">
              <p style="margin:0 0 4px;">
                <a href="{$urlPrivacy}" class="footer-link"
                   style="font-size:11px;color:#8a7a60;text-decoration:none;letter-spacing:1px;"
                >Politique de confidentialité</a>
                <span style="color:#c4b89a;padding:0 8px;">|</span>
                <a href="{$urlCgv}" class="footer-link"
                   style="font-size:11px;color:#8a7a60;text-decoration:none;letter-spacing:1px;"
                >CGV</a>
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
              <p style="margin:0 0 6px;font-size:10px;color:#b8aa95;font-style:italic;">
                {$phishing}
              </p>
              <p style="margin:0;font-size:10px;color:#b8aa95;">
                {$autoMsg}
              </p>
            </td>
          </tr>
HTML;
    }

    /**
     * Envoie l'email post-achat J+7 avec boutons de notation par article.
     *
     * Pour chaque article commandé, génère 5 liens de notation (notes 1 à 5)
     * via RatingModel::generateEmailToken(). Le token est unique par triplet
     * (account_id, wine_id, order_id) ; seule la note varie dans l'URL.
     *
     * @param string $to        Email client
     * @param string $name      Prénom client
     * @param int    $accountId ID compte
     * @param int    $orderId   ID commande
     * @param array<int, array<string, mixed>> $items Articles commandés (wine_id, label_name, vintage, image_path)
     * @param string $lang      Langue
     * @return void
     */
    public function sendPostPurchase(
        string $to,
        string $name,
        int $accountId,
        int $orderId,
        array $items,
        string $lang
    ): void {
        $appUrl     = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $safeName   = htmlspecialchars($name, ENT_QUOTES);
        $ratingModel = $this->newRatingModel();
        $orderToken  = $ratingModel->generateOrderToken($orderId, $accountId);
        $orderPageUrl = htmlspecialchars($appUrl . '/rating/order/' . $orderToken, ENT_QUOTES);

        if ($lang === 'fr') {
            $subject    = 'Comment s\'est passée votre dégustation ? — Château Crabitan Bellevue';
            $title      = 'Votre avis compte';
            $greeting   = "Bonjour {$safeName},";
            $intro      = "Nous espérons que vous avez apprécié votre commande."
                . " Votre avis nous aide à progresser — prenez un instant pour noter vos vins :";
            $rateLabel  = 'Note :';
            $ctaLabel   = 'Noter mes vins';
            $ctaFallback = 'Vous préférez noter vin par vin ? Utilisez les liens ci-dessous :';
        } else {
            $subject    = 'How was your tasting experience? — Château Crabitan Bellevue';
            $title      = 'Your opinion matters';
            $greeting   = "Hello {$safeName},";
            $intro      = "We hope you enjoyed your order."
                . " Your feedback helps us improve — take a moment to rate your wines:";
            $rateLabel  = 'Rate:';
            $ctaLabel   = 'Rate my wines';
            $ctaFallback = 'Prefer to rate wine by wine? Use the links below:';
        }

        // CTA principal — page de notation centralisée par commande
        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$orderPageUrl}\" style=\""
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . $ctaLabel
            . '</a></td></tr></table>';

        // Liens individuels par vin (fallback)
        $winesHtml = '';
        foreach ($items as $item) {
            $wineId    = (int) ($item['wine_id'] ?? 0);
            $label     = htmlspecialchars((string) ($item['label_name'] ?? ''), ENT_QUOTES);
            $vintage   = (int) ($item['vintage'] ?? 0);
            $imagePath = (string) ($item['image_path'] ?? '');

            $token = $ratingModel->generateEmailToken($accountId, $wineId, $orderId);

            $imageHtml = '';
            if ($imagePath !== '') {
                $safeImg   = htmlspecialchars($appUrl . self::WINE_IMAGE_PATH . $imagePath, ENT_QUOTES);
                $imageHtml = "<img src=\"{$safeImg}\" alt=\"{$label}\" width=\"80\""
                    . " style=\"display:block;width:80px;height:auto;border:0;border-radius:3px;"
                    . "margin-bottom:8px;\">";
            }

            $starsHtml = '';
            for ($i = 1; $i <= 5; $i++) {
                $ratingUrl = htmlspecialchars(
                    $appUrl . '/rating/commande/' . $token . '/' . $i,
                    ENT_QUOTES
                );
                $starsHtml .= "<a href=\"{$ratingUrl}\""
                    . " style=\"display:inline-block;padding:8px 12px;margin:0 3px;"
                    . "background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;"
                    . "font-family:Georgia,serif;font-size:14px;font-weight:bold;"
                    . "color:#1a1208;text-decoration:none;\">&#9733; {$i}</a>";
            }

            $vintageHtml = $vintage > 0
                ? " <span style=\"font-size:12px;color:#8a7a60;\">{$vintage}</span>"
                : '';

            $winesHtml .= '<tr><td style="padding:16px 0;border-bottom:1px solid #ede8df;">'
                . $imageHtml
                . "<p style=\"margin:0 0 8px;font-family:Georgia,serif;font-size:16px;color:#1a1208;\">"
                . "{$label}{$vintageHtml}</p>"
                . "<p style=\"margin:0 0 8px;font-size:12px;letter-spacing:1px;"
                . "text-transform:uppercase;color:#8a7a60;\">{$rateLabel}</p>"
                . "<p style=\"margin:0;\">{$starsHtml}</p>"
                . '</td></tr>';
        }

        $fallbackSection = '<p style="margin:24px 0 8px;font-size:13px;color:#8a7a60;">'
            . htmlspecialchars($ctaFallback, ENT_QUOTES)
            . '</p>'
            . '<table style="width:100%;border-collapse:collapse;">'
            . $winesHtml
            . '</table>';

        $message = "<p style=\"margin:0 0 24px;font-size:15px;line-height:1.7;color:#3d3425;\">"
            . $intro
            . "</p>"
            . $ctaBtn
            . $fallbackSection;

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie l'email de relance panier abandonné.
     *
     * Rappelle au client les articles laissés dans son panier avec un CTA
     * de retour vers la boutique.
     *
     * @param string $to       Email client
     * @param string $name     Prénom client
     * @param array<int, array<string, mixed>> $items Articles du panier (label_name, vintage, image_path, price, qty)
     * @param string $lang     Langue
     * @param string $cartUrl  URL de retour vers le panier
     * @return void
     */
    public function sendAbandonedCart(
        string $to,
        string $name,
        array $items,
        string $lang,
        string $cartUrl
    ): void {
        $appUrl   = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $safeCart = htmlspecialchars($cartUrl, ENT_QUOTES);

        if ($lang === 'fr') {
            $subject    = 'Votre panier vous attend — Château Crabitan Bellevue';
            $title      = 'Votre panier';
            $greeting   = "Bonjour {$safeName},";
            $intro      = "Vous avez laissé des articles dans votre panier."
                . " Ils vous attendent toujours — finalisez votre commande avant qu'ils ne soient plus disponibles.";
            $ctaLabel   = 'Reprendre ma commande';
            $colQty     = 'Qté';
            $colPrice   = self::COL_UNIT_FR;
        } else {
            $subject    = 'Your cart is waiting for you — Château Crabitan Bellevue';
            $title      = 'Your cart';
            $greeting   = "Hello {$safeName},";
            $intro      = "You left some items in your cart."
                . " They are still waiting for you — complete your order before they sell out.";
            $ctaLabel   = 'Resume my order';
            $colQty     = 'Qty';
            $colPrice   = self::COL_UNIT_EN;
        }

        $rows = '';
        foreach ($items as $item) {
            $label     = htmlspecialchars((string) ($item['label_name'] ?? ''), ENT_QUOTES);
            $vintage   = (int) ($item['vintage'] ?? 0);
            $imagePath = (string) ($item['image_path'] ?? '');
            $price     = number_format((float) ($item['price'] ?? 0.0), 2, ',', ' ');
            $qty       = (int) ($item['qty'] ?? 0);

            $imageHtml = '';
            if ($imagePath !== '') {
                $safeImg   = htmlspecialchars($appUrl . self::WINE_IMAGE_PATH . $imagePath, ENT_QUOTES);
                $imageHtml = "<img src=\"{$safeImg}\" alt=\"{$label}\" width=\"60\""
                    . " style=\"display:block;width:60px;height:auto;border:0;border-radius:3px;\">";
            }

            $vintageHtml = $vintage > 0
                ? " <span style=\"font-size:12px;color:#8a7a60;\">{$vintage}</span>"
                : '';

            $tdStyle = 'padding:10px 8px;border-bottom:1px solid #ede8df;font-size:13px;color:#3d3425;';
            $rows   .= '<tr>'
                . "<td style=\"{$tdStyle}\">{$imageHtml}"
                . "<span style=\"font-family:Georgia,serif;font-size:14px;color:#1a1208;\">"
                . "{$label}{$vintageHtml}</span></td>"
                . "<td style=\"{$tdStyle}text-align:center;\">{$qty}</td>"
                . "<td style=\"{$tdStyle}text-align:right;\">{$price}&nbsp;€</td>"
                . '</tr>';
        }

        $thStyle = 'padding:8px;background:#f7f3ec;font-size:11px;'
            . 'color:#8a7a60;letter-spacing:1px;text-transform:uppercase;text-align:left;';
        $tableHtml = '<table style="width:100%;border-collapse:collapse;margin:16px 0 24px;">'
            . '<tr>'
            . "<th style=\"{$thStyle}\">Vin</th>"
            . "<th style=\"{$thStyle}text-align:center;\">{$colQty}</th>"
            . "<th style=\"{$thStyle}text-align:right;\">{$colPrice}</th>"
            . '</tr>'
            . $rows
            . '</table>';

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$safeCart}\" style=\""
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . $ctaLabel
            . '</a></td></tr></table>';

        $message = "<p style=\"margin:0 0 16px;font-size:15px;line-height:1.7;color:#3d3425;\">"
            . $intro
            . "</p>"
            . $tableHtml
            . $ctaBtn;

        $body = $this->emailSimpleLayout($title, $greeting, $message, $lang);
        $this->send($to, $name, $subject, $body);
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
            . "<a href=\"{$safeResetUrl}\" style=\""
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
            . "<a href=\"{$safeResetUrl}\" style=\""
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
            . "<a href=\"{$safeLoginUrl}\" style=\""
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Me connecter'
            . '</a></td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td>'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:10px 24px;"
            . self::BTN_STYLE_SECONDARY
            . 'color:#1a1208;text-decoration:underline;">'
            . 'Mot de passe oublié ?'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_FR, "Bonjour {$safeName},", $message, 'fr');
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
            . "<a href=\"{$safeLoginUrl}\" style=\""
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . 'Log in'
            . '</a></td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">'
            . '<tr><td>'
            . "<a href=\"{$safeResetUrl}\" style=\"display:inline-block;padding:10px 24px;"
            . self::BTN_STYLE_SECONDARY
            . 'color:#1a1208;text-decoration:underline;">'
            . 'Forgot your password?'
            . '</a></td></tr></table>';

        return $this->emailSimpleLayout(self::MAIL_SUBJECT_SEC_EN, "Hello {$safeName},", $message, 'en');
    }

    /**
     * Envoie un email de rappel de réactivation lorsqu'un utilisateur tente de se
     * connecter avec des identifiants valides sur un compte en cours de suppression.
     *
     * @param string $to               Adresse email du destinataire
     * @param string $name             Prénom ou nom affiché
     * @param string $lang             Langue (fr|en)
     * @param string $reactivationToken Token de réactivation brut
     * @param string $scheduledDeletionAt Date de suppression programmée (Y-m-d H:i:s)
     * @return void
     */
    public function sendPendingDeletionLoginAlert(
        string $to,
        string $name,
        string $lang,
        string $reactivationToken,
        string $scheduledDeletionAt
    ): void {
        $appUrl   = rtrim($_ENV['APP_URL'] ?? self::DEFAULT_URL, '/');
        $reactUrl = $appUrl . '/' . $lang . self::PATH_REACTIVER . urlencode($reactivationToken);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $safeDate = htmlspecialchars($scheduledDeletionAt, ENT_QUOTES);
        $safeUrl  = htmlspecialchars($reactUrl, ENT_QUOTES);

        $reactBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . '<a href="' . $safeUrl . self::BTN_ANCHOR_STYLE_ATTR
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Annuler la suppression' : 'Cancel deletion')
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $subject = 'Tentative de connexion — votre compte Crabitan est en cours de suppression';
            $message = 'Nous avons détecté une tentative de connexion à votre compte, qui est actuellement en cours de suppression.'
                . ' Vos données seront définitivement anonymisées le <strong>' . $safeDate . '</strong>.<br><br>'
                . 'Si vous souhaitez conserver votre compte, cliquez sur le bouton ci-dessous avant cette date.<br><br>'
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . "Si vous n'êtes pas à l'origine de cette tentative, vous pouvez ignorer cet email."
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Réactivation de compte',
                "Bonjour {$safeName},",
                $message,
                'fr'
            );
        } else {
            $subject = 'Login attempt — your Crabitan account is scheduled for deletion';
            $message = 'We detected a login attempt on your account, which is currently scheduled for deletion.'
                . ' Your data will be permanently anonymised on <strong>' . $safeDate . '</strong>.<br><br>'
                . 'If you wish to keep your account, click the button below before that date.<br><br>'
                . $reactBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'If you did not make this attempt, you can ignore this email.'
                . '</p>';
            $body = $this->emailSimpleLayout(
                'Account reactivation',
                "Hello {$safeName},",
                $message,
                'en'
            );
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Notifie un utilisateur d'une mise à jour substantielle d'un document légal.
     *
     * @param string $to         Adresse email du destinataire
     * @param string $name       Prénom ou nom affiché
     * @param string $lang       Langue du compte (fr|en)
     * @param string $docLabel   Nom du document (ex. "Conditions Générales de Vente")
     * @param string $docUrl     URL publique complète du document
     * @param string $version    Nouvelle version (ex. "1.2")
     * @param string $updatedAt  Date de mise à jour (Y-m-d)
     * @param string $changeNote Résumé des modifications (optionnel)
     * @return void
     */
    public function sendLegalDocumentUpdate( // NOSONAR — php:S107 : 8 paramètres nécessaires pour l'email légal bilingue
        string $to,
        string $name,
        string $lang,
        string $docLabel,
        string $docUrl,
        string $version,
        string $updatedAt,
        string $changeNote
    ): void {
        $safeName  = htmlspecialchars($name, ENT_QUOTES);
        $safeLabel = htmlspecialchars($docLabel, ENT_QUOTES);
        $safeUrl   = htmlspecialchars($docUrl, ENT_QUOTES);
        $safeNote  = $changeNote !== '' ? htmlspecialchars($changeNote, ENT_QUOTES) : '';

        $dateFormatted = $lang === 'fr'
            ? (new \DateTimeImmutable($updatedAt))->format(self::DATE_FORMAT_DATE)
            : (new \DateTimeImmutable($updatedAt))->format('m/d/Y');

        $docBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . '<a href="' . $safeUrl . self::BTN_ANCHOR_STYLE_ATTR
            . self::BTN_STYLE_PRIMARY
            . self::BTN_STYLE_LINK
            . ($lang === 'fr' ? 'Consulter le document' : 'View document')
            . '</a></td></tr></table>';

        if ($lang === 'fr') {
            $subject = '[Mise à jour] ' . $safeLabel . ' — version ' . $version;
            $noteHtml = $safeNote !== ''
                ? '<p style="background:#f5f0e8;border-left:3px solid #c1a14b;padding:10px 14px;'
                  . 'margin:16px 0;font-size:13px;color:#5a4030;">'
                  . '<strong>Résumé des modifications :</strong><br>' . $safeNote . '</p>'
                : '';
            $message = 'Nous vous informons que le document <strong>' . $safeLabel . '</strong>'
                . ' a été mis à jour en version <strong>' . $version . '</strong>'
                . ' le ' . $dateFormatted . '.<br><br>'
                . $noteHtml
                . 'Nous vous invitons à prendre connaissance de ce document mis à jour.<br><br>'
                . $docBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'Si vous avez des questions, n\'hésitez pas à nous contacter.'
                . '</p>';
            $body = $this->emailSimpleLayout($safeLabel . ' mise à jour', "Bonjour {$safeName},", $message, 'fr');
        } else {
            $subject = '[Update] ' . $safeLabel . ' — version ' . $version;
            $noteHtml = $safeNote !== ''
                ? '<p style="background:#f5f0e8;border-left:3px solid #c1a14b;padding:10px 14px;'
                  . 'margin:16px 0;font-size:13px;color:#5a4030;">'
                  . '<strong>Summary of changes:</strong><br>' . $safeNote . '</p>'
                : '';
            $message = 'We are informing you that our <strong>' . $safeLabel . '</strong>'
                . ' has been updated to version <strong>' . $version . '</strong>'
                . ' on ' . $dateFormatted . '.<br><br>'
                . $noteHtml
                . 'We invite you to review this updated document.<br><br>'
                . $docBtn
                . '<p style="font-size:12px;color:#8a7a60;margin-top:16px;">'
                . 'If you have any questions, please do not hesitate to contact us.'
                . '</p>';
            $body = $this->emailSimpleLayout($safeLabel . ' updated', "Hello {$safeName},", $message, 'en');
        }

        $this->send($to, $name, $subject, $body);
    }

    /**
     * Envoie un email d'alerte stock bas (corps HTML pré-construit par StockNotificationService).
     *
     * @param string $to      Adresse email destinataire
     * @param string $name    Nom ou email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $body    Corps HTML complet (construit par StockNotificationService)
     * @return void
     */
    public function sendLowStockAlert(string $to, string $name, string $subject, string $body): void
    {
        $this->send($to, $name, $subject, $body);
    }
}
