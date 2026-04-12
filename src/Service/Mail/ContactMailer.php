<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — contact.
 *
 * Délègue à MailService les méthodes liées aux formulaires de contact :
 * notification au propriétaire et confirmation à l'expéditeur.
 */
class ContactMailer
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
     * Envoie le message de contact au propriétaire du site.
     *
     * @param string $firstname Prénom de l'expéditeur
     * @param string $lastname  Nom de l'expéditeur
     * @param string $email     Email de l'expéditeur
     * @param string $subject   Sujet du message (clé interne)
     * @param string $message   Corps du message
     * @param string $lang      Code langue ('fr'|'en')
     * @return void
     */
    public function sendContactToOwner(
        string $firstname,
        string $lastname,
        string $email,
        string $subject,
        string $message,
        string $lang
    ): void {
        $this->mailer->sendContactToOwner($firstname, $lastname, $email, $subject, $message, $lang);
    }

    /**
     * Envoie la confirmation de prise en charge du message à l'expéditeur.
     *
     * @param string $to          Adresse destinataire
     * @param string $firstname   Prénom de l'expéditeur
     * @param string $subject     Sujet du message (clé interne)
     * @param string $lang        Code langue ('fr'|'en')
     * @param string $userMessage Corps du message original (optionnel)
     * @return void
     */
    public function sendContactConfirmation(
        string $to,
        string $firstname,
        string $subject,
        string $lang,
        string $userMessage = ''
    ): void {
        $this->mailer->sendContactConfirmation($to, $firstname, $subject, $lang, $userMessage);
    }
}
