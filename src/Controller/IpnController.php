<?php

declare(strict_types=1);

namespace Controller;

use Core\Exception\HttpException;
use Model\CartModel;
use Model\OrderModel;
use Model\PaymentIntentModel;
use Model\WineModel;
use Service\InvoiceService;
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
    private const LOG_CONTEXT = '[IPN]';

    private PaymentService $payment;
    private OrderModel $orders;
    private CartModel $carts;
    private MailService $mail;
    private PaymentIntentModel $intents;
    private WineModel $wines;

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
        $this->wines   = new WineModel();
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
    public function handle(array $params): void // NOSONAR — php:S1172 : $params imposé par le routeur MVC, non utilisé (IPN = server-to-server)
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
                    \Core\ErrorLogger::warning($alertError, self::LOG_CONTEXT);
                }

                http_response_code(400);
                echo 'INVALID_SIGNATURE';
                throw new HttpException(400);
            }

            $erreur   = $_GET['Erreur'] ?? '';
            $ref      = preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['Ref'] ?? ''); // S5145 — sanitisation avant usage en log/BDD
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
                \Core\ErrorLogger::write([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level'     => 'WARNING',
                    'message'   => '[IPN] INTENT_NOT_FOUND — ref=' . $ref,
                    'file'      => __FILE__,
                    'line'      => __LINE__,
                    'trace'     => '',
                    'url'       => $_SERVER['REQUEST_URI'] ?? '',
                    'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
                ]);
                http_response_code(200);
                echo 'INTENT_NOT_FOUND';
                throw new HttpException(200);
            }

            $finalRef = $this->orders->createFromIpn(
                (int)    $snapshot['user_id'],
                (array)  $snapshot['items'],
                (float)  $snapshot['total'],
                (float)  $snapshot['delivery_discount'],
                (int)    $snapshot['billing_address_id'],
                (int)    $snapshot['delivery_address_id'],
                (string) $snapshot['cgv_version'],
                $ref,
                $numappel,
                $numtrans,
                (string) ($snapshot['delivery_method'] ?? 'shipping')
            );

            $this->intents->delete($ref);
            $this->carts->clear((int) $snapshot['user_id']);

            // Décrémenter le stock pour chaque article commandé
            foreach ((array) $snapshot['items'] as $item) {
                $wineId = (int) ($item['wine_id'] ?? 0);
                $qty    = (int) ($item['qty']     ?? 0);
                if ($wineId > 0 && $qty > 0) {
                    $this->wines->decrementStock($wineId, $qty);
                }
            }

            // Génération de la facture PDF — non bloquante, désactivable via INVOICE_AUTO_GENERATE=false.
            // NB : parse_ini_file() convertit 'false' en chaîne vide → on traite '' comme désactivé.
            $envFlag     = strtolower(trim((string) ($_ENV['INVOICE_AUTO_GENERATE'] ?? 'true')));
            $autoInvoice = !in_array($envFlag, ['', 'false', '0', 'no', 'off'], true);
            try {
                $order = $autoInvoice ? $this->orders->findByReferenceForInvoice($finalRef) : null;
                if ($order !== null) {
                    $invoiceNumber = sprintf('WEB-INVOICE-%s-%04d', date('Y'), (int) $order['id']);
                    $invoiceDir    = ROOT_PATH . '/storage/invoices';
                    $this->mkdirInvoices($invoiceDir);
                    $invoicePath = 'storage/invoices/' . $invoiceNumber . '.pdf';
                    file_put_contents(
                        ROOT_PATH . '/' . $invoicePath, // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
                        $this->createInvoiceService()->generate($order, $invoiceNumber)
                    );
                    $this->orders->updateInvoiceData((int) $order['id'], $invoiceNumber, $invoicePath);

                    // Envoi facture par email — non bloquant
                    try {
                        $invoiceLang  = (string) ($snapshot['lang']         ?? 'fr');
                        $invoiceEmail = (string) ($snapshot['client_email'] ?? '');
                        $invoiceName  = (string) ($snapshot['client_name']  ?? '');
                        if ($invoiceEmail !== '') {
                            $this->mail->sendInvoiceToClient(
                                $invoiceEmail,
                                $invoiceName,
                                $finalRef,
                                $invoiceNumber,
                                realpath(ROOT_PATH . '/' . $invoicePath) ?: ROOT_PATH . '/' . $invoicePath,
                                $invoiceLang
                            );
                        }
                    } catch (\Throwable $mailInvoiceError) {
                        \Core\ErrorLogger::warning($mailInvoiceError, self::LOG_CONTEXT);
                    }
                }
            } catch (\Throwable $invoiceError) {
                \Core\ErrorLogger::warning($invoiceError, self::LOG_CONTEXT);
            }

            // Emails de confirmation — non bloquants
            try {
                $lang     = (string) ($snapshot['lang']              ?? 'fr');
                $email    = (string) ($snapshot['client_email']      ?? '');
                $name     = (string) ($snapshot['client_name']       ?? '');
                $items    = (array)  $snapshot['items'];
                $total    = (float)  $snapshot['total'];
                $discount = (float)  ($snapshot['delivery_discount'] ?? 0.0);

                $pdfContext     = (new \Service\OrderPdfContextBuilder())->build($snapshot);
                $deliveryMethod = (string) ($snapshot['delivery_method'] ?? 'shipping');
                $this->mail->sendOrderConfirmationToClient(
                    $email,
                    $name,
                    $ref,
                    'card',
                    $items,
                    $total,
                    $lang,
                    $discount,
                    $pdfContext,
                    $deliveryMethod
                );
                $this->mail->sendOrderConfirmationToOwner(
                    $email,
                    $name,
                    $ref,
                    'card',
                    $items,
                    $total,
                    $discount,
                    $pdfContext,
                    $deliveryMethod
                );
            } catch (\Throwable $mailError) {
                \Core\ErrorLogger::warning($mailError, self::LOG_CONTEXT);
            }

            unset($_SESSION['ca_payment']);

            http_response_code(200);
            echo 'OK';
            throw new HttpException(200);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
            http_response_code(500);
            echo 'ERROR';
            throw new HttpException(500);
        }
    }

    /**
     * Crée le répertoire de stockage des factures (seam de testabilité).
     *
     * @param string $dir Chemin absolu du répertoire à créer
     * @return void
     */
    protected function mkdirInvoices(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true); // NOSONAR php:S4823 — création de répertoire applicatif contrôlé
        }
    }

    /**
     * Instancie InvoiceService (seam de testabilité).
     *
     * @return InvoiceService
     */
    protected function createInvoiceService(): InvoiceService
    {
        return new InvoiceService();
    }
}
