<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — RGPD.
 *
 * Délègue à MailService les méthodes liées à la conformité RGPD :
 * export des données personnelles (portabilité Art. 20).
 */
class RgpdMailer
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
     * Envoie l'archive RGPD (portabilité Art. 20) à l'utilisateur.
     *
     * @param string $to         Adresse destinataire
     * @param string $name       Nom du destinataire
     * @param string $zipContent Contenu binaire du ZIP
     * @param string $filename   Nom du fichier ZIP en pièce jointe
     * @param string $lang       Code langue ('fr'|'en')
     * @return void
     */
    public function sendRgpdExport(
        string $to,
        string $name,
        string $zipContent,
        string $filename,
        string $lang
    ): void {
        $this->mailer->sendRgpdExport($to, $name, $zipContent, $filename, $lang);
    }
}
