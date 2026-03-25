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
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['MAIL_USER'];
        $this->mailer->Password   = $_ENV['MAIL_PASS'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port       = (int) $_ENV['MAIL_PORT'];
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->setFrom($_ENV['MAIL_USER'], $_ENV['MAIL_FROM_NAME']);
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
        return "<p>Bonjour {$safeName},</p>"
            . "<p>Merci de vous être inscrit sur <strong>Crabitan Bellevue</strong>.</p>"
            . "<p>Veuillez cliquer sur le lien ci-dessous pour activer votre compte :</p>"
            . "<p><a href=\"{$safeUrl}\">{$safeUrl}</a></p>"
            . "<p>Ce lien est valable 24 heures.</p>"
            . "<p>L'équipe Crabitan Bellevue</p>";
    }

    private function verificationBodyEn(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return "<p>Hello {$safeName},</p>"
            . "<p>Thank you for registering on <strong>Crabitan Bellevue</strong>.</p>"
            . "<p>Please click the link below to activate your account:</p>"
            . "<p><a href=\"{$safeUrl}\">{$safeUrl}</a></p>"
            . "<p>This link is valid for 24 hours.</p>"
            . "<p>The Crabitan Bellevue team</p>";
    }

    private function resetBodyFr(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return "<p>Bonjour {$safeName},</p>"
            . "<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>"
            . "<p>Cliquez sur le lien ci-dessous (valable 1 heure) :</p>"
            . "<p><a href=\"{$safeUrl}\">{$safeUrl}</a></p>"
            . "<p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>"
            . "<p>L'équipe Crabitan Bellevue</p>";
    }

    private function resetBodyEn(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        return "<p>Hello {$safeName},</p>"
            . "<p>You requested a password reset.</p>"
            . "<p>Click the link below (valid for 1 hour):</p>"
            . "<p><a href=\"{$safeUrl}\">{$safeUrl}</a></p>"
            . "<p>If you did not request this, please ignore this email.</p>"
            . "<p>The Crabitan Bellevue team</p>";
    }
}
