<?php

declare(strict_types=1);

namespace Service\Mail;

use Service\MailService;

/**
 * Mailer de domaine — commandes.
 *
 * Délègue à MailService toutes les méthodes liées aux commandes :
 * confirmation, statut, retours, remboursements, factures, paniers abandonnés.
 */
class OrderMailer
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
     * Envoie la confirmation de commande au client.
     *
     * @param string $toEmail         Adresse email du client
     * @param string $toName          Nom du client
     * @param string $orderRef        Référence de commande
     * @param string $paymentMethod   Méthode de paiement (card|virement|cheque)
     * @param array<mixed> $items     Articles de la commande
     * @param float  $total           Montant total TTC
     * @param string $lang            Code langue ('fr'|'en')
     * @param float  $shippingDiscount Remise livraison (optionnel)
     * @return void
     */
    public function sendOrderConfirmationToClient( // NOSONAR — php:S107 : 8 paramètres délégués de MailService
        string $toEmail,
        string $toName,
        string $orderRef,
        string $paymentMethod,
        array $items,
        float $total,
        string $lang,
        float $shippingDiscount = 0.0
    ): void {
        $this->mailer->sendOrderConfirmationToClient(
            $toEmail,
            $toName,
            $orderRef,
            $paymentMethod,
            $items,
            $total,
            $lang,
            $shippingDiscount
        );
    }

    /**
     * Envoie la confirmation de commande au propriétaire.
     *
     * @param string $clientEmail     Email du client
     * @param string $clientName      Nom du client
     * @param string $orderRef        Référence de commande
     * @param string $paymentMethod   Méthode de paiement
     * @param array<mixed> $items     Articles de la commande
     * @param float  $total           Montant total
     * @param float  $shippingDiscount Remise livraison (optionnel)
     * @param array<string, mixed>|null $pdfContext Contexte PDF récap (billing, delivery, ordered_at)
     * @return void
     */
    public function sendOrderConfirmationToOwner( // NOSONAR — php:S107 : 8 paramètres requis pour passer le contexte PDF récap
        string $clientEmail,
        string $clientName,
        string $orderRef,
        string $paymentMethod,
        array $items,
        float $total,
        float $shippingDiscount = 0.0,
        ?array $pdfContext = null
    ): void {
        $this->mailer->sendOrderConfirmationToOwner(
            $clientEmail,
            $clientName,
            $orderRef,
            $paymentMethod,
            $items,
            $total,
            $shippingDiscount,
            $pdfContext
        );
    }

    /**
     * Envoie un email de changement de statut de commande.
     *
     * @param string $toEmail              Email du client
     * @param string $toName               Nom du client
     * @param string $orderRef             Référence de commande
     * @param string $newStatus            Nouveau statut
     * @param string $lang                 Code langue ('fr'|'en')
     * @param string $appUrl               URL de base de l'application
     * @param array<mixed> $items          Articles de la commande
     * @param array<mixed> $deliveryAddress Adresse de livraison
     * @param string $trackingUrl          URL de suivi (optionnel)
     * @param int    $orderId              Identifiant de commande (optionnel)
     * @return void
     */
    public function sendOrderStatusEmail( // NOSONAR — php:S107 : 10 paramètres délégués de MailService
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
        $this->mailer->sendOrderStatusEmail(
            $toEmail,
            $toName,
            $orderRef,
            $newStatus,
            $lang,
            $appUrl,
            $items,
            $deliveryAddress,
            $trackingUrl,
            $orderId
        );
    }

    /**
     * Notifie le propriétaire d'une demande de retour.
     *
     * @param array<mixed> $order   Données de la commande
     * @param string $clientName    Nom du client
     * @param string $clientEmail   Email du client
     * @return void
     */
    public function sendReturnRequestedToOwner(array $order, string $clientName, string $clientEmail): void
    {
        $this->mailer->sendReturnRequestedToOwner($order, $clientName, $clientEmail);
    }

    /**
     * Alerte le propriétaire d'un échec de remboursement GAE.
     *
     * @param string $orderRef Référence de commande
     * @param float  $amount   Montant du remboursement
     * @param int    $orderId  Identifiant de commande (optionnel)
     * @return void
     */
    public function sendRefundGaeKoToOwner(string $orderRef, float $amount, int $orderId = 0): void
    {
        $this->mailer->sendRefundGaeKoToOwner($orderRef, $amount, $orderId);
    }

    /**
     * Notifie le propriétaire d'une demande de remboursement.
     *
     * @param array<mixed> $order  Données de la commande
     * @param string $clientName   Nom du client
     * @param string $clientEmail  Email du client
     * @return void
     */
    public function sendRefundRequestedToOwner(array $order, string $clientName, string $clientEmail): void
    {
        $this->mailer->sendRefundRequestedToOwner($order, $clientName, $clientEmail);
    }

    /**
     * Envoie une alerte de commandes en attente.
     *
     * @param array<mixed> $orders Liste des commandes en attente
     * @param int          $days   Nombre de jours d'attente seuil
     * @return void
     */
    public function sendPendingOrdersAlert(array $orders, int $days = 7): void
    {
        $this->mailer->sendPendingOrdersAlert($orders, $days);
    }

    /**
     * Confirme au client que son retour a été accepté.
     *
     * @param string $to           Email du client
     * @param string $name         Nom du client
     * @param array<mixed> $order  Données de la commande
     * @param string $pdfPath      Chemin du PDF bon de retour
     * @param string $lang         Code langue ('fr'|'en')
     * @return void
     */
    public function sendReturnConfirmedToClient(
        string $to,
        string $name,
        array $order,
        string $pdfPath,
        string $lang
    ): void {
        $this->mailer->sendReturnConfirmedToClient($to, $name, $order, $pdfPath, $lang);
    }

    /**
     * Envoie la facture au client.
     *
     * @param string $toEmail       Email du client
     * @param string $toName        Nom du client
     * @param string $orderRef      Référence de commande
     * @param string $invoiceNumber Numéro de facture
     * @param string $pdfPath       Chemin du PDF facture
     * @param string $lang          Code langue ('fr'|'en')
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
        $this->mailer->sendInvoiceToClient($toEmail, $toName, $orderRef, $invoiceNumber, $pdfPath, $lang);
    }

    /**
     * Informe le client que son remboursement est en cours.
     *
     * @param string $toEmail  Email du client
     * @param string $toName   Nom du client
     * @param string $orderRef Référence de commande
     * @param string $lang     Code langue ('fr'|'en')
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
        $this->mailer->sendRefundPendingEmail($toEmail, $toName, $orderRef, $lang, $appUrl);
    }

    /**
     * Alerte le propriétaire d'une signature IPN invalide.
     *
     * @param string $to   Email du destinataire
     * @param string $date Date de la notification IPN
     * @param string $ip   Adresse IP source
     * @return void
     */
    public function sendIpnSignatureAlert(string $to, string $date, string $ip): void
    {
        $this->mailer->sendIpnSignatureAlert($to, $date, $ip);
    }

    /**
     * Envoie l'email post-achat (invitation à noter les produits).
     *
     * @param string $to          Email du client
     * @param string $name        Nom du client
     * @param int    $accountId   Identifiant du compte
     * @param int    $orderId     Identifiant de commande
     * @param array<mixed> $items Articles commandés
     * @param string $lang        Code langue ('fr'|'en')
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
        $this->mailer->sendPostPurchase($to, $name, $accountId, $orderId, $items, $lang);
    }

    /**
     * Envoie un email de relance panier abandonné.
     *
     * @param string $to          Email du client
     * @param string $name        Nom du client
     * @param array<mixed> $items Articles du panier
     * @param string $lang        Code langue ('fr'|'en')
     * @param string $cartUrl     URL du panier
     * @return void
     */
    public function sendAbandonedCart(string $to, string $name, array $items, string $lang, string $cartUrl): void
    {
        $this->mailer->sendAbandonedCart($to, $name, $items, $lang, $cartUrl);
    }
}
