<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Response;
use Model\AccountModel;
use Model\OrderModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;
use Service\PaymentService;

/**
 * Gère les commandes et fiches de retour depuis l'espace client.
 */
class AccountOrderController extends AbstractAccountController
{
    private const DATE_FORMAT    = 'd/m/Y';
    private const PER_PAGE       = 10;
    private const VALID_PER_PAGES = [10, 25, 50];

    private UserRepository $userRepo;
    private OrderModel $orders;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository par défaut)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->userRepo = $userRepo ?? new MysqlUserRepository(new AccountModel());
        $this->orders   = new OrderModel();
    }

    /**
     * Instancie le PaymentService (seam de testabilité).
     *
     * @return PaymentService
     */
    protected function newPaymentService(): PaymentService
    {
        return new PaymentService();
    }

    /**
     * Instancie le RefundService (seam de testabilité).
     *
     * @return \Service\RefundService
     */
    protected function newRefundService(): \Service\RefundService
    {
        return new \Service\RefundService();
    }

    /**
     * Instancie le MailService (seam de testabilité).
     *
     * @return \Service\MailService
     */
    protected function newMailService(): \Service\MailService
    {
        return new \Service\MailService();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes
    // ----------------------------------------------------------------

    /**
     * Affiche l'historique des commandes avec filtres et pagination.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function orders(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->requireIndividual($userId, $lang);

        $perPage = (int) $this->request->get('per_page', (string) self::PER_PAGE);
        if (!in_array($perPage, self::VALID_PER_PAGES, true)) {
            $perPage = self::PER_PAGE;
        }

        $period = $this->request->get('period', 'all');
        $year   = null;
        if ($period !== 'all' && $period !== '3months') {
            $year   = (int) $period;
            $period = 'year';
        }

        $rawStatus    = $this->request->get('status', '');
        $validStatuses = [
            'pending', 'paid', 'processing', 'shipped', 'delivered',
            'cancelled', 'refunded', 'return_requested', 'refund_refused',
        ];
        $statusFilter  = in_array($rawStatus, $validStatuses, true) ? $rawStatus : null;

        $total = $this->orders->countForUser($userId, $period === 'all' ? null : $period, $year, $statusFilter);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, (int) $this->request->get('page', '1')), $pages);

        $this->view('account/orders', [
            'lang'         => $lang,
            'orders'       => $this->orders->getForUser(
                $userId,
                $page,
                $perPage,
                $period === 'all' ? null : $period,
                $year,
                $statusFilter
            ),
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'perPage'      => $perPage,
            'period'       => $this->request->get('period', 'all'),
            'statusFilter' => $rawStatus,
            'years'        => $this->orders->getAvailableYearsForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes/{id}
    // ----------------------------------------------------------------

    /**
     * Affiche le détail d'une commande de l'utilisateur.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
    public function orderDetail(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        $this->requireIndividual($userId, $lang);

        $success = $_SESSION['flash']['order_success'] ?? null;
        $error   = $_SESSION['flash']['order_error']   ?? null;
        unset($_SESSION['flash']['order_success'], $_SESSION['flash']['order_error']);

        $order = $this->orders->findDetailForUser($id, $userId);
        if (!$order) {
            Response::redirect("/{$lang}/mon-compte/commandes");
        }

        $items            = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $shippingDiscount = (float) ($order['shipping_discount'] ?? 0);

        $cancellableReturn = false;
        $returnDeadline    = null;
        $returnExpired     = false;
        $deliveredNoDate   = false;
        if ($order['status'] === 'delivered') {
            if (empty($order['delivered_at'])) {
                $deliveredNoDate = true;
            } else {
                $deliveredAt = $order['delivered_at'] . ' +' . \Model\OrderModel::CANCEL_WINDOW_DAYS . ' days';
                $deadlineTs  = strtotime($deliveredAt);
                if ($deadlineTs !== false && time() <= $deadlineTs) {
                    $cancellableReturn = true;
                    $returnDeadline    = date(self::DATE_FORMAT, $deadlineTs);
                } else {
                    $returnExpired = true;
                }
            }
        }

        $this->view('account/order_detail', [
            'lang'              => $lang,
            'order'             => $order,
            'items'             => $items,
            'shippingDiscount'  => $shippingDiscount > 0.0 ? $shippingDiscount : null,
            'success'           => $success,
            'error'             => $error,
            'csrf'              => $_SESSION['csrf'] ?? '',
            'ownerEmail'        => $_ENV['CONTACT_OWNER_EMAIL'] ?? 'crabitan.bellevue@orange.fr',
            'cancellableReturn' => $cancellableReturn,
            'returnDeadline'    => $returnDeadline,
            'returnExpired'     => $returnExpired,
            'deliveredNoDate'   => $deliveredNoDate,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/commandes/{id}/annuler
    // ----------------------------------------------------------------

    /**
     * Annule une commande depuis l'espace-compte client.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
    public function cancelOrder(array $params): void // NOSONAR php:S3776 — complexité flux annulation (card/chèque, GAE, rétractation, emails)
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/commandes/{$id}";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['order_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $order = $this->orders->findDetailForUser($id, $userId);
        if (($order['payment_method'] ?? '') === 'card' && ($order['status'] ?? '') === 'paid') {
            $numappel = (string) ($order['ca_numappel'] ?? '');
            $numtrans = (string) ($order['ca_numtrans'] ?? '');

            if ($numappel !== '' && $numtrans !== '') {
                $paymentService = $this->newPaymentService();
                $refunded = $paymentService->callGaeCancelOrRefund(
                    (string) ($order['order_reference'] ?? ''),
                    $numappel,
                    $numtrans,
                    (int) round((float) ($order['price'] ?? 0) * 100)
                );

                if (!$refunded) {
                    $_SESSION['flash']['order_error'] = __('account.cancel_refund_failed');
                    Response::redirect($back);
                }

                $this->orders->updateStatus($id, 'refunded');
                $this->sendCancelStatusEmails('refunded', $order, $id, $userId, $lang);
                $_SESSION['flash']['order_success'] = __('account.order_cancelled');
                Response::redirect($back);
            } else {
                \Core\ErrorLogger::write([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level'     => 'WARNING',
                    'message'   => "cancelOrder: missing CA transaction refs for order {$id}",
                    'url'       => $_SERVER['REQUEST_URI'] ?? '',
                    'method'    => 'POST',
                ]);
            }
        }

        $ok = $this->orders->cancelForUser($id, $userId);
        if (!$ok) {
            $ok = $this->orders->requestReturnForUser($id, $userId);
            if ($ok) {
                $_SESSION['flash']['order_success'] = __('account.order_return_requested');
                try {
                    $this->sendReturnEmails($id, $userId, $lang);
                } catch (\Exception $e) {
                    \Core\ErrorLogger::warning($e);
                }
            } else {
                $_SESSION['flash']['order_error'] = __('account.order_cancel_failed');
            }
        } else {
            $_SESSION['flash']['order_success'] = __('account.order_cancelled');
            $this->sendCancelStatusEmails('cancelled', $order, $id, $userId, $lang);
        }

        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/commandes/{id}/remboursement
    // ----------------------------------------------------------------

    /**
     * Initie une demande de remboursement depuis l'espace-compte client.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
    public function requestRefund(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/commandes/{$id}";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['order_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $order = $this->orders->findDetailForUser($id, $userId);
        if (!$order) {
            $this->abort(404, 'Commande introuvable');
        }

        $refundableStatuses = ['paid', 'processing'];
        if (!in_array($order['status'], $refundableStatuses, true)) {
            $_SESSION['flash']['order_error'] = __('account.order_refund_not_eligible');
            Response::redirect($back);
        }

        try {
            $this->newRefundService()->refund($id);
            $_SESSION['flash']['order_success'] = __('account.order_refund_initiated');
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
            $_SESSION['flash']['order_error'] = __('account.order_refund_failed');
        }

        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes/{id}/fiche-retour
    // ----------------------------------------------------------------

    /**
     * Génère et affiche la fiche de retour PDF pour une commande éligible.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
    public function returnSlip(array $params): void
    {
        $this->resolveLang($params);
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $id      = (int) ($params['id'] ?? 0);
        $lang    = $params['lang'];

        $this->requireIndividual($userId, $lang);

        $order = $this->orders->findDetailForUser($id, $userId);

        $isReturnRequested   = $order && $order['status'] === 'return_requested';
        $isDeliveredInWindow = false;
        if ($order && $order['status'] === 'delivered' && !empty($order['delivered_at'])) {
            $deadlineTs = strtotime($order['delivered_at'] . ' +' . \Model\OrderModel::CANCEL_WINDOW_DAYS . ' days');
            if ($deadlineTs !== false && time() <= $deadlineTs) {
                $isDeliveredInWindow = true;
            }
        }

        if (!$order || (!$isReturnRequested && !$isDeliveredInWindow)) {
            $this->abort(404, 'Fiche de retour indisponible');
        }

        $pdfBytes = $this->buildReturnSlipPdf($order);
        $filename = 'fiche-retour_' . $order['order_reference'] . '.pdf';
        $this->sendPdfResponse($pdfBytes, $filename);
    }

    /**
     * Envoie le PDF en réponse HTTP inline.
     *
     * @param string $pdfBytes Contenu binaire du PDF
     * @param string $filename Nom de fichier pour Content-Disposition
     * @return never
     */
    protected function sendPdfResponse(string $pdfBytes, string $filename): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, no-cache');
        echo $pdfBytes;
        $this->doExit();
    }

    /**
     * Termine l'exécution du script (seam de testabilité).
     *
     * @return never
     */
    protected function doExit(): never
    {
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux binaire
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Envoie les emails de notification de retour au propriétaire et au client.
     *
     * @param int    $orderId Identifiant de la commande
     * @param int    $userId  Identifiant du client
     * @param string $lang    Langue du client
     * @return void
     */
    /**
     * Envoie les emails de changement de statut annulation au client et au propriétaire.
     *
     * Non bloquant : les erreurs SMTP sont avalées.
     *
     * @param string               $status  Statut cible (`cancelled` ou `refunded`)
     * @param array<string, mixed> $order   Données de la commande (order_reference, price…)
     * @param int                  $orderId Identifiant de la commande
     * @param int                  $userId  Identifiant du client
     * @param string               $lang    Langue de session (fallback)
     * @return void
     */
    private function sendCancelStatusEmails(string $status, array $order, int $orderId, int $userId, string $lang): void
    {
        try {
            $account = $this->userRepo->findById($userId);
            if (!$account || ($account['email'] ?? '') === '') {
                return;
            }
            $clientEmail = (string) $account['email'];
            $clientName  = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? '')) ?: $clientEmail;
            $clientLang  = (string) ($account['lang'] ?? $lang);
            $orderRef    = (string) ($order['order_reference'] ?? '');
            $appUrl      = rtrim($_ENV['APP_URL'] ?? '', '/');
            $mailer      = $this->newMailService();
            $mailer->sendOrderStatusEmail($clientEmail, $clientName, $orderRef, $status, $clientLang, $appUrl);
            $ownerEmail = (string) ($_ENV['CONTACT_OWNER_EMAIL'] ?? '');
            if ($ownerEmail !== '') {
                // phpcs:ignore Generic.Files.LineLength
                $mailer->sendOrderStatusEmailToOwner($ownerEmail, $orderRef, $status, $clientName, $clientEmail, (float) ($order['price'] ?? 0), $appUrl, $orderId);
            }
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
        }
    }

    private function sendReturnEmails(int $orderId, int $userId, string $lang): void
    {
        $order   = $this->orders->findDetailForUser($orderId, $userId);
        $account = $this->userRepo->findById($userId);
        if (!$order || !$account) {
            return;
        }

        $clientName  = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
        $clientEmail = $account['email'] ?? '';
        if ($clientEmail === '') {
            return;
        }

        $pdfBytes = $this->buildReturnSlipPdf($order);
        $tmpPath  = sys_get_temp_dir() . '/retour_' . $order['order_reference'] . '_' . time() . '.pdf';
        file_put_contents($tmpPath, $pdfBytes);

        try {
            $mailLang = (string) ($account['lang'] ?? $lang);
            $mailer   = $this->newMailService();
            $mailer->sendReturnRequestedToOwner($order, $clientName, $clientEmail);
            $mailer->sendReturnConfirmedToClient($clientEmail, $clientName, $order, $tmpPath, $mailLang);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    /**
     * Génère le contenu binaire de la fiche de retour PDF.
     *
     * @param array<string, mixed> $order Données de la commande
     * @return string Contenu binaire du PDF
     */
    private function buildReturnSlipPdf(array $order): string
    {
        $items     = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $appName   = defined('APP_NAME') ? APP_NAME : 'Château Crabitan Bellevue';
        $ownerAddr = "Château Crabitan Bellevue\nau Crabitan\n33410 Sainte-Croix-du-Mont\nFrance";

        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($appName);
        $pdf->SetAuthor($appName);
        $pdf->SetTitle('Fiche de retour — ' . $order['order_reference']);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $clientName    = htmlspecialchars(trim(
            ($order['bill_civility'] ?? '') . ' ' .
            ($order['bill_firstname'] ?? '') . ' ' .
            ($order['bill_lastname'] ?? '')
        ));
        $clientAddr    = htmlspecialchars($order['bill_street'] ?? '');
        $clientZipCity = htmlspecialchars(trim(($order['bill_zip'] ?? '') . ' ' . ($order['bill_city'] ?? '')));
        $clientCountry = htmlspecialchars($order['bill_country'] ?? '');
        $orderedAt     = date(self::DATE_FORMAT, strtotime((string) $order['ordered_at']));
        $returnDate    = date(self::DATE_FORMAT);

        $rows = '';
        foreach ($items as $item) {
            $label       = htmlspecialchars($item['label_name'] ?? '—');
            $qty         = (int) ($item['qty'] ?? 0);
            $price       = number_format((float) ($item['price'] ?? 0), 2, ',', ' ');
            $vintage     = (int) ($item['vintage'] ?? 0);
            $vintageCell = $vintage > 0 ? (string) $vintage : '';
            if (!empty($item['is_cuvee_speciale'])) {
                $label .= '<br><span style="font-size:8pt;color:#c1a14b;font-style:italic;">Cuvée Spéciale</span>';
            }
            $rows .= "<tr><td>{$label}</td>"
                . "<td style=\"text-align:center;\">{$vintageCell}</td>"
                . "<td style=\"text-align:center;\">{$qty}</td>"
                . "<td style=\"text-align:right;\">{$price}&nbsp;€</td></tr>";
        }

        $html = '<style>
            body  { font-family: dejavusans; font-size: 10pt; color: #1a1208; }
            h1    { font-size: 15pt; color: #c1a14b; margin: 0 0 4px; }
            h2    { font-size: 10pt; color: #c1a14b; margin: 14px 0 4px; border-bottom: 1px solid #c1a14b; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
            th    { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; font-size: 9pt; }
            td    { padding: 4px 6px; border-bottom: 1px solid #e8e0d0; font-size: 9pt; }
            .muted { color: #8a7060; font-size: 9pt; }
            .addr  { font-size: 9pt; line-height: 1.6; }
            .legal { font-size: 8pt; color: #888; margin-top: 14px; border-top: 1px solid #e0d8cc; padding-top: 8px; }
        </style>
        <h1>' . htmlspecialchars($appName) . '</h1>
        <p class="muted">FICHE DE RETOUR — droit de rétractation (art. L221-18 Code conso.)</p>

        <table><tr>
            <td style="width:50%;vertical-align:top;">
                <h2>Expéditeur</h2>
                <p class="addr">' . $clientName . '<br>' . $clientAddr
            . '<br>' . $clientZipCity . '<br>' . $clientCountry . '</p>
            </td>
            <td style="width:50%;vertical-align:top;">
                <h2>Retourner à</h2>
                <p class="addr">' . nl2br(htmlspecialchars($ownerAddr)) . '</p>
            </td>
        </tr></table>

        <h2>Détails de la commande</h2>
        <table><tr>
            <td><strong>Référence :</strong> ' . htmlspecialchars($order['order_reference']) . '</td>
            <td><strong>Date commande :</strong> ' . $orderedAt . '</td>
            <td><strong>Date de retour :</strong> ' . $returnDate . '</td>
        </tr></table>

        <h2>Articles retournés</h2>
        <table>
            <tr><th>Vin</th>'
            . '<th style="text-align:center;">Millésime</th>'
            . '<th style="text-align:center;">Qté</th><th style="text-align:right;">Prix unit.</th></tr>
            ' . $rows . '
        </table>

        <p class="legal">
            Droit de rétractation exercé conformément à l\'article L221-18 du Code de la consommation.
            Retour en carton d\'origine scellé, bouteilles non ouvertes, en port payé par l\'acheteur.
            Sous réserve de vérification à réception.
        </p>';

        $pdf->writeHTML($html, true, false, true, false, '');
        return (string) $pdf->Output('', 'S');
    }

    /**
     * Redirige les comptes société vers le dashboard (vues commandes réservées aux particuliers).
     *
     * @param int    $userId Identifiant utilisateur
     * @param string $lang   Langue courante
     * @return void
     */
    private function requireIndividual(int $userId, string $lang): void
    {
        $account = $this->userRepo->findById($userId);
        if ($account && $account['account_type'] === 'company') {
            $_SESSION['flash']['info'] = __('account.b2b_restricted');
            Response::redirect("/{$lang}/mon-compte");
        }
    }
}
