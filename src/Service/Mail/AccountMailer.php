<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — gestion de compte.
 *
 * Délègue à MailService toutes les méthodes liées au cycle de vie du compte :
 * bienvenue, suppression, inactivité, changement mot de passe, changement email, nouveau appareil.
 */
class AccountMailer
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
     * Envoie les informations de compte Google (compte associé à OAuth).
     *
     * @param string $to   Adresse destinataire
     * @param string $name Nom du destinataire
     * @param string $lang Code langue ('fr'|'en')
     * @return void
     */
    public function sendGoogleAccountInfo(string $to, string $name, string $lang): void
    {
        $this->mailer->sendGoogleAccountInfo($to, $name, $lang);
    }

    /**
     * Envoie l'email de bienvenue après création de compte.
     *
     * @param string $email         Adresse destinataire
     * @param string $firstname     Prénom du destinataire
     * @param string $lang          Code langue ('fr'|'en')
     * @param bool   $isSubscribed  Indique si l'utilisateur est abonné à la newsletter
     * @param string $confirmToken  Token newsletter (brut) pour CTA 1 clic ; requis si !$isSubscribed
     * @return void
     */
    public function sendWelcome(
        string $email,
        string $firstname,
        string $lang,
        bool $isSubscribed = false,
        string $confirmToken = ''
    ): void {
        $this->mailer->sendWelcome($email, $firstname, $lang, $isSubscribed, $confirmToken);
    }

    /**
     * Envoie la confirmation de suppression de compte.
     *
     * @param string $to                Adresse destinataire
     * @param string $name              Nom du destinataire
     * @param string $lang              Code langue ('fr'|'en')
     * @param string $reactivationToken Token de réactivation (optionnel)
     * @return void
     */
    public function sendAccountDeletionConfirmation(
        string $to,
        string $name,
        string $lang,
        string $reactivationToken = ''
    ): void {
        $this->mailer->sendAccountDeletionConfirmation($to, $name, $lang, $reactivationToken);
    }

    /**
     * Envoie un rappel de suppression planifiée.
     *
     * @param string $to                  Adresse destinataire
     * @param string $name                Nom du destinataire
     * @param string $lang                Code langue ('fr'|'en')
     * @param int    $daysAhead           Nombre de jours avant suppression
     * @param string $scheduledDeletionAt Date de suppression planifiée
     * @param string $reactivationToken   Token de réactivation
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
        $this->mailer->sendDeletionReminder($to, $name, $lang, $daysAhead, $scheduledDeletionAt, $reactivationToken);
    }

    /**
     * Avertit l'utilisateur d'une suppression pour inactivité.
     *
     * @param string $to           Adresse destinataire
     * @param string $name         Nom du destinataire
     * @param string $lang         Code langue ('fr'|'en')
     * @param string $deletionDate Date de suppression prévue
     * @return void
     */
    public function sendInactivityWarning(string $to, string $name, string $lang, string $deletionDate): void
    {
        $this->mailer->sendInactivityWarning($to, $name, $lang, $deletionDate);
    }

    /**
     * Informe l'utilisateur que son compte a été supprimé pour inactivité.
     *
     * @param string $to                Adresse destinataire
     * @param string $name              Nom du destinataire
     * @param string $lang              Code langue ('fr'|'en')
     * @param string $deletionDate      Date de suppression
     * @param string $reactivationToken Token de réactivation
     * @return void
     */
    public function sendInactivityDeletion(
        string $to,
        string $name,
        string $lang,
        string $deletionDate,
        string $reactivationToken
    ): void {
        $this->mailer->sendInactivityDeletion($to, $name, $lang, $deletionDate, $reactivationToken);
    }

    /**
     * Envoie l'email de suppression définitive de compte.
     *
     * @param string $to   Adresse destinataire
     * @param string $name Nom du destinataire
     * @param string $lang Code langue ('fr'|'en')
     * @return void
     */
    public function sendFinalDeletion(string $to, string $name, string $lang): void
    {
        $this->mailer->sendFinalDeletion($to, $name, $lang);
    }

    /**
     * Envoie la confirmation de changement de mot de passe.
     *
     * @param string $to           Adresse destinataire
     * @param string $name         Nom du destinataire
     * @param string $lang         Code langue ('fr'|'en')
     * @param string $confirmToken Token de confirmation (optionnel)
     * @return void
     */
    public function sendPasswordChangeConfirmation(
        string $to,
        string $name,
        string $lang,
        string $confirmToken = ''
    ): void {
        $this->mailer->sendPasswordChangeConfirmation($to, $name, $lang, $confirmToken);
    }

    /**
     * Informe l'utilisateur que sa demande de changement de mot de passe a été refusée.
     *
     * @param string $to   Adresse destinataire
     * @param string $lang Code langue ('fr'|'en')
     * @return void
     */
    public function sendPasswordChangeDenied(string $to, string $lang): void
    {
        $this->mailer->sendPasswordChangeDenied($to, $lang);
    }

    /**
     * Alerte l'utilisateur d'une connexion depuis un nouvel appareil.
     *
     * @param string $to          Adresse destinataire
     * @param string $name        Nom du destinataire
     * @param string $deviceName  Nom de l'appareil détecté
     * @param string|null $ipAddress Adresse IP source
     * @param string $lang        Code langue ('fr'|'en')
     * @param string $deviceToken Token de confirmation appareil (optionnel)
     * @return void
     */
    public function sendNewDeviceAlert(
        string $to,
        string $name,
        string $deviceName,
        ?string $ipAddress,
        string $lang,
        string $deviceToken = ''
    ): void {
        $this->mailer->sendNewDeviceAlert($to, $name, $deviceName, $ipAddress, $lang, $deviceToken);
    }

    /**
     * Envoie l'email de confirmation de changement d'adresse email.
     *
     * @param string $to         Adresse destinataire (ancien email)
     * @param string $name       Nom du destinataire
     * @param string $confirmUrl URL de confirmation
     * @param string $lang       Code langue ('fr'|'en')
     * @param string $newEmail   Nouvelle adresse email demandée
     * @param string $revokeUrl  URL de révocation (optionnel)
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
        $this->mailer->sendEmailChangeConfirmation($to, $name, $confirmUrl, $lang, $newEmail, $revokeUrl);
    }

    /**
     * Envoie la notification de changement d'email à l'ancien email.
     *
     * @param string $to   Ancienne adresse email destinataire
     * @param string $lang Code langue ('fr'|'en')
     * @return void
     */
    public function sendEmailChangeNotification(string $to, string $lang): void
    {
        $this->mailer->sendEmailChangeNotification($to, $lang);
    }

    /**
     * Alerte l'utilisateur (en attente de suppression) qui tente de se connecter.
     *
     * @param string $to                  Adresse destinataire
     * @param string $name                Nom du destinataire
     * @param string $lang                Code langue ('fr'|'en')
     * @param string $reactivationToken   Token de réactivation
     * @param string $scheduledDeletionAt Date de suppression planifiée
     * @return void
     */
    public function sendPendingDeletionLoginAlert(
        string $to,
        string $name,
        string $lang,
        string $reactivationToken,
        string $scheduledDeletionAt
    ): void {
        $this->mailer->sendPendingDeletionLoginAlert($to, $name, $lang, $reactivationToken, $scheduledDeletionAt);
    }
}
