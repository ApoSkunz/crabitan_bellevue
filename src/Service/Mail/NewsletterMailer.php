<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — newsletter.
 *
 * Délègue à MailService toutes les méthodes liées à la newsletter :
 * envoi, confirmation d'abonnement, bienvenue, nouveau vin.
 */
class NewsletterMailer
{
    /**
     * Injecte le MailService sous-jacent.
     *
     * @param MailService $mailer Service d'envoi d'email
     */
    public function __construct(private MailService $mailer)
    {
    }

    /**
     * Envoie une newsletter à un abonné.
     *
     * @param string      $to              Adresse destinataire
     * @param string      $name            Nom du destinataire
     * @param string      $subject         Objet du message
     * @param string      $htmlBody        Corps HTML
     * @param string|null $attachmentPath  Chemin pièce jointe (optionnel)
     * @param string|null $attachmentName  Nom pièce jointe (optionnel)
     * @param string|null $unsubToken      Token de désabonnement (optionnel)
     * @return void
     */
    public function sendNewsletter(
        string $to,
        string $name,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        ?string $unsubToken = null
    ): void {
        $this->mailer->sendNewsletter($to, $name, $subject, $htmlBody, $attachmentPath, $attachmentName, $unsubToken);
    }

    /**
     * Envoie une newsletter "nouveau vin disponible".
     *
     * @param string       $toEmail    Adresse email du destinataire
     * @param string       $toName     Nom du destinataire
     * @param string       $unsubToken Token de désabonnement
     * @param array<mixed> $wine       Données du vin
     * @param string       $appUrl     URL de base de l'application
     * @param string       $lang       Code langue ('fr'|'en')
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
        $this->mailer->sendNewWineNewsletter($toEmail, $toName, $unsubToken, $wine, $appUrl, $lang);
    }

    /**
     * Envoie l'email de confirmation d'abonnement à la newsletter.
     *
     * @param string $to         Adresse destinataire
     * @param string $confirmUrl URL de confirmation
     * @param string $lang       Code langue ('fr'|'en')
     * @return void
     */
    public function sendNewsletterConfirmation(string $to, string $confirmUrl, string $lang): void
    {
        $this->mailer->sendNewsletterConfirmation($to, $confirmUrl, $lang);
    }

    /**
     * Envoie l'email de bienvenue à un nouvel abonné newsletter.
     *
     * @param string $to   Adresse destinataire
     * @param string $name Nom du destinataire
     * @param string $lang Code langue ('fr'|'en')
     * @return void
     */
    public function sendNewsletterWelcome(string $to, string $name, string $lang): void
    {
        $this->mailer->sendNewsletterWelcome($to, $name, $lang);
    }
}
