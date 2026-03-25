<?php

declare(strict_types=1);

namespace Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $mailUser = $_ENV['MAIL_USER'] ?? '';
        $mailPass = $_ENV['MAIL_PASS'] ?? '';
        $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host    = $_ENV['MAIL_HOST'];
        $this->mailer->Port    = (int) $_ENV['MAIL_PORT'];
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

        $host        = parse_url($_ENV['APP_URL'] ?? 'localhost', PHP_URL_HOST);
        $fromAddress = $mailUser !== '' ? $mailUser : ('noreply@' . $host);
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
        $subjectLine = ($lang === 'fr' ? 'Nouveau message de contact' : 'New contact message')
            . ' — ' . htmlspecialchars($subject, ENT_QUOTES);

        $safeName    = htmlspecialchars($firstname . ' ' . $lastname, ENT_QUOTES);
        $safeEmail   = htmlspecialchars($email, ENT_QUOTES);
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES);
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES));

        $body = "<p><strong>De :</strong> {$safeName} ({$safeEmail})</p>"
            . "<p><strong>Objet :</strong> {$safeSubject}</p>"
            . "<hr>"
            . "<p>{$safeMessage}</p>";

        $this->send($ownerEmail, APP_NAME, $subjectLine, $body);
    }

    public function sendContactConfirmation(
        string $to,
        string $firstname,
        string $subject,
        string $lang
    ): void {
        $safeName    = htmlspecialchars($firstname, ENT_QUOTES);
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES);

        if ($lang === 'fr') {
            $subjectLine = 'Nous avons bien reçu votre message';
            $body = "<p>Bonjour {$safeName},</p>"
                . "<p>Nous avons bien reçu votre message concernant <strong>{$safeSubject}</strong>.</p>"
                . "<p>Notre équipe vous répondra dans les meilleurs délais.</p>"
                . "<p>Cordialement,<br>L'équipe du Château Crabitan Bellevue</p>";
        } else {
            $subjectLine = 'We have received your message';
            $body = "<p>Hello {$safeName},</p>"
                . "<p>We have received your message regarding <strong>{$safeSubject}</strong>.</p>"
                . "<p>Our team will get back to you as soon as possible.</p>"
                . "<p>Best regards,<br>The Château Crabitan Bellevue team</p>";
        }

        $this->send($to, $firstname, $subjectLine, $body);
    }

    private function send(string $to, string $name, string $subject, string $body): void
    {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($to, $name);
        $this->mailer->isHTML(true);
        $this->mailer->Subject = $subject;
        $this->mailer->Body    = $body;
        $this->mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));
        $this->mailer->send();
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
            . ' de cette inscription, ignorez cet email.'
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
            . ' please ignore this email.'
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
            . ' de cette demande, ignorez cet email — votre mot de passe reste inchangé.'
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
            . ' please ignore this email — your password will remain unchanged.'
        );
    }

    private function emailLayout(
        string $title,
        string $greeting,
        string $message,
        string $url,
        string $ctaLabel,
        string $footnote
    ): string {
        $appUrl   = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/');
        $logoUrl  = $appUrl . '/assets/images/logo/crabitan-bellevue-logo-modern.svg';
        $urlPrivacy  = $appUrl . '/fr/politique-confidentialite';
        $urlLegal    = $appUrl . '/fr/mentions-legales';
        $urlSupport  = $appUrl . '/fr/support';
        $year        = date('Y');

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

          <!-- Header logo -->
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
              <p style="margin:0 0 6px;font-size:11px;color:#a89880;letter-spacing:1px;">
                © {$year} Château Crabitan Bellevue — Sainte-Croix-du-Mont, Gironde
              </p>
              <p style="margin:0;font-size:10px;color:#b8aa95;">
                Ce mail est généré automatiquement. Veuillez ne pas y répondre.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}
