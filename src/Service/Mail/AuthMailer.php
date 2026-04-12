<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — authentification.
 *
 * Délègue à MailService (façade) toutes les méthodes liées à l'auth :
 * vérification email, reset mot de passe, compte verrouillé, alertes admin.
 */
class AuthMailer
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
     * Envoie l'email de vérification d'adresse email.
     *
     * @param string $to        Adresse destinataire
     * @param string $name      Nom du destinataire
     * @param string $verifyUrl URL de vérification
     * @param string $lang      Code langue ('fr'|'en')
     * @return void
     */
    public function sendEmailVerification(string $to, string $name, string $verifyUrl, string $lang): void
    {
        $this->mailer->sendEmailVerification($to, $name, $verifyUrl, $lang);
    }

    /**
     * Envoie l'email de réinitialisation de mot de passe.
     *
     * @param string $to       Adresse destinataire
     * @param string $name     Nom du destinataire
     * @param string $resetUrl URL de réinitialisation
     * @param string $lang     Code langue ('fr'|'en')
     * @return void
     */
    public function sendPasswordReset(string $to, string $name, string $resetUrl, string $lang): void
    {
        $this->mailer->sendPasswordReset($to, $name, $resetUrl, $lang);
    }

    /**
     * Envoie une alerte de verrouillage de compte.
     *
     * @param string $to       Adresse destinataire
     * @param string $name     Nom du destinataire
     * @param string $lang     Code langue ('fr'|'en')
     * @param string $resetUrl URL de réinitialisation mot de passe
     * @return void
     */
    public function sendAccountLocked(string $to, string $name, string $lang, string $resetUrl): void
    {
        $this->mailer->sendAccountLocked($to, $name, $lang, $resetUrl);
    }

    /**
     * Envoie une alerte admin après plusieurs échecs de connexion.
     *
     * @param string      $to         Adresse destinataire
     * @param string      $name       Nom de l'admin
     * @param string      $lang       Code langue ('fr'|'en')
     * @param string      $ip         Adresse IP source
     * @param string|null $targetName Nom du compte ciblé (optionnel)
     * @return void
     */
    public function sendAdminLoginFailureAlert(
        string $to,
        string $name,
        string $lang,
        string $ip,
        ?string $targetName = null
    ): void {
        $this->mailer->sendAdminLoginFailureAlert($to, $name, $lang, $ip, $targetName);
    }

    /**
     * Envoie une alerte admin lors d'un changement de mot de passe.
     *
     * @param string      $to         Adresse destinataire
     * @param string      $name       Nom de l'admin
     * @param string      $lang       Code langue ('fr'|'en')
     * @param string      $ip         Adresse IP source
     * @param string      $userAgent  User agent du navigateur
     * @param string|null $targetName Nom du compte ciblé (optionnel)
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
        $this->mailer->sendAdminPasswordChangeAlert($to, $name, $lang, $ip, $userAgent, $targetName);
    }

    /**
     * Notifie un admin que son compte a été bloqué.
     *
     * @param string $to          Adresse destinataire
     * @param string $name        Nom de l'admin
     * @param string $lang        Code langue ('fr'|'en')
     * @param string $blockerName Nom de l'opérateur ayant bloqué
     * @return void
     */
    public function sendAdminAccountBlocked(string $to, string $name, string $lang, string $blockerName): void
    {
        $this->mailer->sendAdminAccountBlocked($to, $name, $lang, $blockerName);
    }

    /**
     * Notifie un admin que son compte a été débloqué.
     *
     * @param string $to            Adresse destinataire
     * @param string $name          Nom de l'admin
     * @param string $lang          Code langue ('fr'|'en')
     * @param string $unblockerName Nom de l'opérateur ayant débloqué
     * @return void
     */
    public function sendAdminAccountUnblocked(string $to, string $name, string $lang, string $unblockerName): void
    {
        $this->mailer->sendAdminAccountUnblocked($to, $name, $lang, $unblockerName);
    }

    /**
     * Informe l'utilisateur que son email est déjà associé à un compte.
     *
     * @param string $to       Adresse destinataire
     * @param string $name     Nom du destinataire
     * @param string $lang     Code langue ('fr'|'en')
     * @param string $loginUrl URL de connexion
     * @param string $resetUrl URL de réinitialisation mot de passe
     * @return void
     */
    public function sendEmailAlreadyExists(
        string $to,
        string $name,
        string $lang,
        string $loginUrl,
        string $resetUrl
    ): void {
        $this->mailer->sendEmailAlreadyExists($to, $name, $lang, $loginUrl, $resetUrl);
    }
}
