<?php

declare(strict_types=1);

namespace Controller;

use Core\Exception\HttpException;
use Model\CartModel;
use Model\OrderModel;
use Model\PaymentIntentModel;
use Service\MailService;
use Service\PaymentService;

/**
 * Contrôleur de notification IPN CA Up2pay e-Transactions.
 *
 * POST /payment/ipn — appel server-to-server sans authentification utilisateur.
 * Source de vérité pour la création des commandes carte bancaire.
 */
class IpnController
{
    private PaymentService $payment;
    private OrderModel $orders;
    private CartModel $carts;
    private MailService $mail;
    private PaymentIntentModel $intents;

    /**
     * Initialise les dépendances du contrôleur IPN.
     */
    public function __construct()
    {
        $this->payment = new PaymentService();
        $this->orders  = new OrderModel();
        $this->carts   = new CartModel();
        $this->mail    = new MailService();
        $this->intents = new PaymentIntentModel();
    }

    /**
     * Traite la notification IPN CA Up2pay e-Transactions.
     *
     * Vérifie la signature RSA, applique l'idempotence sur la référence,
     * crée la commande si le paiement est accepté, vide le panier et
     * envoie les emails de confirmation.
     *
     * @param array<string, string> $params Paramètres de route (non utilisés pour l'IPN)
     * @return void
     * @throws HttpException 400 si signature invalide, 200 dans tous les autres cas, 500 en erreur inattendue
     */
    public function handle(array $params): void
    {
        try {
            $rawQuery = $_SERVER['QUERY_STRING'] ?? '';

            if (!$this->payment->verifyIpnSignature($rawQuery)) {
                try {
                    $maintainerMail = $_ENV['MAINTAINER_MAIL'] ?? getenv('MAINTAINER_MAIL');
                    if (is_string($maintainerMail) && $maintainerMail !== '') {
                        $this->mail->sendIpnSignatureAlert(
                            $maintainerMail,
                            date('d/m/Y à H:i:s'),
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        );
                    }
                } catch (\Throwable $alertError) {
                    error_log('[IPN] Alert mail error: ' . $alertError->getMessage());
                }

                http_response_code(400);
                echo 'INVALID_SIGNATURE';
                throw new HttpException(400);
            }

            $erreur   = $_GET['Erreur'] ?? '';
            $ref      = $_GET['Ref']    ?? '';
            $numappel = $_GET['Appel']  ?? '';
            $numtrans = $_GET['Trans']  ?? '';

            // Idempotence : si la commande existe déjà, répondre OK sans recréer
            if ($this->orders->findByReferenceOnly($ref) !== null) {
                http_response_code(200);
                echo 'OK';
                throw new HttpException(200);
            }

            // Paiement refusé par la banque
            if ($erreur !== '00000') {
                http_response_code(200);
                echo 'REFUSED';
                throw new HttpException(200);
            }

            // Purge opportuniste des intents expirés
            $this->intents->purgeExpired();

            // Lecture du snapshot depuis BDD (l'IPN est server-to-server, pas de session navigateur)
            /** @var array<string, mixed>|null $snapshot */
            $snapshot = $this->intents->findByReference($ref);

            if ($snapshot === null) {
                error_log('[IPN] INTENT_NOT_FOUND — ref=' . $ref);
                http_response_code(200);
                echo 'INTENT_NOT_FOUND';
                throw new HttpException(200);
            }

            $this->orders->createFromIpn(
                (int)    $snapshot['user_id'],
                (array)  $snapshot['items'],
                (float)  $snapshot['total'],
                (float)  $snapshot['delivery_discount'],
                (int)    $snapshot['billing_address_id'],
                (int)    $snapshot['delivery_address_id'],
                (string) $snapshot['cgv_version'],
                $ref,
                $numappel,
                $numtrans
            );

            $this->intents->delete($ref);
            $this->carts->clear((int) $snapshot['user_id']);

            // Emails de confirmation — non bloquants
            try {
                $lang  = (string) ($snapshot['lang']         ?? 'fr');
                $email = (string) ($snapshot['client_email'] ?? '');
                $name  = (string) ($snapshot['client_name']  ?? '');
                $items = (array)  $snapshot['items'];
                $total = (float)  $snapshot['total'];

                $this->mail->sendOrderConfirmationToClient(
                    $email,
                    $name,
                    $ref,
                    'card',
                    $items,
                    $total,
                    $lang
                );

                $this->mail->sendOrderConfirmationToOwner(
                    $email,
                    $name,
                    $ref,
                    'card',
                    $items,
                    $total
                );
            } catch (\Throwable $mailError) {
                error_log('[IPN] Mail error for ref=' . $ref . ' : ' . $mailError->getMessage());
            }

            unset($_SESSION['ca_payment']);

            http_response_code(200);
            echo 'OK';
            throw new HttpException(200);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[IPN] Unexpected error: ' . $e->getMessage());
            http_response_code(500);
            echo 'ERROR';
            throw new HttpException(500);
        }
    }
}
