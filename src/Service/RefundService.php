<?php

declare(strict_types=1);

namespace Service;

use Exception\GaeRefundException;
use Model\OrderModel;

/**
 * Orchestre le remboursement d'une commande.
 *
 * - Si CA_ENABLE_GAE=true  → appel API GAE → statut `refunded`.
 * - Si CA_ENABLE_GAE=false → statut `refund_pending`, traitement manuel dans Vision Air.
 */
class RefundService
{
    private OrderModel $orders;
    private GaeApiInterface $gae;
    private MailService $mailer;

    /**
     * Initialise le service de remboursement.
     *
     * @param OrderModel|null      $orders Instance optionnelle du modèle commandes
     * @param GaeApiInterface|null $gae    Client GAE (injecter un mock en test)
     * @param MailService|null     $mailer Service d'envoi d'email
     */
    public function __construct(
        ?OrderModel $orders = null,
        ?GaeApiInterface $gae = null,
        ?MailService $mailer = null
    ) {
        $this->orders = $orders ?? new OrderModel();
        $this->gae    = $gae    ?? new GaeApiClient();
        $this->mailer = $mailer ?? new MailService();
    }

    /**
     * Déclenche le remboursement d'une commande.
     *
     * Selon la valeur de CA_ENABLE_GAE :
     * - true  : appel API GAE, passage à `refunded` si succès, exception si échec.
     * - false : passage à `refund_pending`, log PHP, email "remboursement initié".
     *
     * @param int $orderId Identifiant de la commande à rembourser
     * @return string Nouveau statut de la commande (`refunded` ou `refund_pending`)
     * @throws GaeRefundException Si GAE est activé et que le remboursement échoue
     * @throws \InvalidArgumentException Si la commande est introuvable
     */
    public function refund(int $orderId): string
    {
        $order = $this->orders->findByIdForAdmin($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException("Commande introuvable : {$orderId}");
        }

        $gaeEnabled = \Core\FeatureFlags::getBool('ca_enable_gae', false);

        if ($gaeEnabled) {
            return $this->refundViaGae($orderId, $order);
        }

        return $this->refundPending($orderId, $order);
    }

    /**
     * Effectue le remboursement via l'API GAE Crédit Agricole.
     *
     * @param int                  $orderId Identifiant de la commande
     * @param array<string, mixed> $order   Données de la commande
     * @return string Statut `refunded`
     * @throws GaeRefundException Si le remboursement GAE échoue
     */
    private function refundViaGae(int $orderId, array $order): string
    {
        $numappel = (string) ($order['ca_numappel'] ?? '');
        $numtrans = (string) ($order['ca_numtrans'] ?? '');
        $ref      = (string) ($order['order_reference'] ?? '');
        $amount   = (int) round((float) ($order['price'] ?? 0) * 100);

        $success = $this->gae->refund($ref, $numappel, $numtrans, $amount);

        if (!$success) {
            throw new GaeRefundException("Échec remboursement GAE pour commande #{$orderId}");
        }

        $this->orders->updateStatus($orderId, 'refunded');
        $this->sendRefundInitiatedEmail($order, 'refunded');

        return 'refunded';
    }

    /**
     * Passe la commande en attente de remboursement manuel (Vision Air).
     *
     * @param int                  $orderId Identifiant de la commande
     * @param array<string, mixed> $order   Données de la commande
     * @return string Statut `refund_pending`
     */
    private function refundPending(int $orderId, array $order): string
    {
        $this->orders->updateStatus($orderId, 'refund_pending');

        \Core\ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'WARNING',
            'message'   => sprintf(
                '[RefundService] Commande #%d (%s) passée en refund_pending — traitement manuel requis dans Vision Air.',
                $orderId,
                $order['order_reference'] ?? 'N/A'
            ),
            'file'   => __FILE__,
            'line'   => __LINE__,
            'trace'  => '',
            'url'    => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);

        $this->sendRefundInitiatedEmail($order, 'refund_pending');
        $this->notifyOwnerRefundPending($order);

        return 'refund_pending';
    }

    /**
     * Notifie le propriétaire qu'une demande de remboursement manuel a été soumise.
     *
     * Non bloquant : les erreurs SMTP sont loggées sans lever d'exception.
     *
     * @param array<string, mixed> $order Données de la commande
     * @return void
     */
    private function notifyOwnerRefundPending(array $order): void
    {
        $clientEmail = (string) ($order['email'] ?? '');
        if ($clientEmail === '' || str_ends_with($clientEmail, '@purged.invalid')) {
            return;
        }

        $firstname  = (string) ($order['firstname'] ?? '');
        $lastname   = (string) ($order['lastname']  ?? '');
        $clientName = trim($firstname . ' ' . $lastname) ?: $clientEmail;

        try {
            $this->mailer->sendRefundRequestedToOwner($order, $clientName, $clientEmail);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[RefundService]');
        }
    }

    /**
     * Envoie l'email "votre remboursement a été initié" au client.
     *
     * Non bloquant : les erreurs SMTP sont loggées sans lever d'exception.
     *
     * @param array<string, mixed> $order     Données de la commande
     * @param string               $newStatus Nouveau statut (`refunded` ou `refund_pending`)
     * @return void
     */
    private function sendRefundInitiatedEmail(array $order, string $newStatus): void
    {
        $clientEmail = (string) ($order['email'] ?? '');
        if ($clientEmail === '' || str_ends_with($clientEmail, '@purged.invalid')) {
            return;
        }

        $firstname  = (string) ($order['firstname'] ?? '');
        $lastname   = (string) ($order['lastname']  ?? '');
        $clientName = trim($firstname . ' ' . $lastname) ?: $clientEmail;
        $orderRef   = (string) ($order['order_reference'] ?? '');
        $lang       = (string) ($order['lang'] ?? 'fr');
        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

        try {
            if ($newStatus === 'refunded') {
                // sendOrderStatusEmail gère déjà 'refunded'
                $this->mailer->sendOrderStatusEmail($clientEmail, $clientName, $orderRef, 'refunded', $lang, $appUrl);
            } else {
                // refund_pending : email personnalisé "remboursement initié"
                $this->mailer->sendRefundPendingEmail($clientEmail, $clientName, $orderRef, $lang, $appUrl);
            }
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[RefundService]');
        }
    }
}
