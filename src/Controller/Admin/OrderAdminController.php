<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\OrderModel;
use Service\AuditLogger;
use Service\InvoiceService;
use Service\MailService;
use Service\PaymentService;

class OrderAdminController extends AdminController
{
    private const ADMIN_URL         = '/admin/commandes';
    private const DEFAULT_PER_PAGE  = 10;
    private const ALLOWED_PER_PAGES = [10, 25, 50];
    private const ORDER_NOT_FOUND   = 'Commande introuvable';

    private const TRIGGER_STATUSES = ['processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    private OrderModel $orders;

    /**
     * Initialise le contrôleur avec le modèle commandes.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    /**
     * Instancie le MailService (seam de testabilité).
     *
     * @return MailService
     */
    protected function newMailService(): MailService
    {
        return new MailService();
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
     * Déplace le fichier uploadé vers la destination (seam de testabilité).
     *
     * @param string $src  Chemin temporaire du fichier uploadé
     * @param string $dest Chemin de destination
     * @return bool
     */
    protected function moveUploadedFile(string $src, string $dest): bool
    {
        return move_uploaded_file($src, $dest);
    }

    /**
     * Retourne le dossier de stockage des factures (seam de testabilité).
     *
     * @return string Chemin absolu du dossier de factures (sans slash final)
     */
    protected function getInvoiceDir(): string
    {
        return ROOT_PATH . '/storage/invoices';
    }

    /**
     * Envoie le contenu binaire d'un fichier au client (seam de testabilité).
     *
     * @param string $path Chemin absolu du fichier à streamer
     * @return void
     */
    protected function streamFile(string $path): void
    {
        readfile($path); // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
    }

    /**
     * Termine l'exécution du script après l'envoi des headers de download
     * (seam de testabilité).
     *
     * @return never
     */
    protected function doExit(): never
    {
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux binaire
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes
    // ----------------------------------------------------------------

    /**
     * Affiche la liste paginée des commandes avec filtres.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $status   = $this->request->get('status') ?: null;
        $payment  = $this->request->get('payment') ?: null;
        $search   = trim($this->request->get('search', ''));
        $archived = $this->request->get('archived') === '1';

        $total = $this->orders->countForAdmin($status, $search ?: null, $payment, $archived);
        $list  = $this->orders->getForAdmin($page, $perPage, $status, $search ?: null, $payment, $archived);

        $this->view('admin/orders/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'orders',
            'pageTitle'    => $archived ? 'Commandes archivées (> 10 ans)' : 'Commandes',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Commandes']],
            'orders'       => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'status'       => $status,
            'payment'      => $payment,
            'search'       => $search,
            'archived'     => $archived,
            'flash'        => $this->getFlash('success'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes/{id}
    // ----------------------------------------------------------------

    /**
     * Affiche le détail d'une commande admin.
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function show(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $order     = $this->orders->findByIdForAdmin((int) $params['id']);

        if (!$order) {
            $this->abort(404, self::ORDER_NOT_FOUND);
        }

        $this->view('admin/orders/show', [
            'adminUser'    => $adminUser,
            'adminSection' => 'orders',
            'pageTitle'    => 'Commande #' . $order['order_reference'],
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Commandes', 'url' => self::ADMIN_URL],
                ['label' => '#' . $order['order_reference']],
            ],
            'order'        => $order,
            'invoiceFiles' => $this->orders->getInvoiceFiles((int) $order['id']),
            'csrfToken'    => $_SESSION['csrf'] ?? '',
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/commandes/{id}/statut
    // ----------------------------------------------------------------

    /**
     * Met à jour le statut d'une commande (POST).
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function updateStatus(array $params): void // NOSONAR — php:S3776 : complexité flux changement statut admin (GAE, emails, CSRF, guards)
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL . '/' . $params['id']);
        }

        $id    = (int) $params['id'];
        $order = $this->orders->findByIdForAdmin($id);

        if (!$order) {
            $this->abort(404, self::ORDER_NOT_FOUND);
        }

        if ($order['status'] === 'cancelled') {
            $this->flash('error', 'Une commande annulée ne peut plus être modifiée.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $oldStatus = $order['status'];
        $status    = $this->request->post('status', '');

        // Remboursement CB automatique si passage à "refunded" sur une commande carte
        if ($status === 'refunded' && $status !== $oldStatus && ($order['payment_method'] ?? '') === 'card') {
            $numappel = (string) ($order['ca_numappel'] ?? '');
            $numtrans = (string) ($order['ca_numtrans'] ?? '');
            $ref      = (string) ($order['order_reference'] ?? '');
            $amount   = (int) round((float) ($order['price'] ?? 0) * 100);

            if ($numappel === '' || $numtrans === '') {
                $this->flash('error', 'Remboursement CB impossible : NUMAPPEL/NUMTRANS manquants.');
                Response::redirect(self::ADMIN_URL . '/' . $id);
            }

            $refunded = $this->newPaymentService()->callGaeCancelOrRefund($ref, $numappel, $numtrans, $amount);

            if (!$refunded) {
                $this->flash('error', 'Échec du remboursement CB (CA). Les gérants ont été notifiés pour un remboursement manuel.');
                try {
                    $this->newMailService()->sendRefundGaeKoToOwner(
                        $ref,
                        (float) ($order['price'] ?? 0),
                        $id
                    );
                } catch (\Throwable $mailErr) {
                    \Core\ErrorLogger::warning($mailErr);
                }
                $this->orders->updateStatus($id, 'return_requested');
                AuditLogger::log(
                    $adminUser['id'],
                    'order.refund_gae_ko',
                    'order',
                    $id,
                    ['ref' => $ref, 'amount' => $order['price'] ?? 0, 'new_status' => 'return_requested'],
                    AuditLogger::getIp()
                );
                Response::redirect(self::ADMIN_URL . '/' . $id);
            }
        }

        $this->orders->updateStatus($id, $status);

        AuditLogger::log(
            $adminUser['id'],
            'order.status_updated',
            'order',
            $id,
            ['old_status' => $oldStatus, 'new_status' => $status],
            AuditLogger::getIp()
        );

        if ($status !== $oldStatus && in_array($status, self::TRIGGER_STATUSES, true)) {
            $clientEmail = (string) ($order['email'] ?? '');
            $firstname   = (string) ($order['firstname'] ?? '');
            $lastname    = (string) ($order['lastname'] ?? '');
            $clientName  = trim($firstname . ' ' . $lastname) ?: $clientEmail;
            $clientLang  = (string) ($order['lang'] ?? 'fr');
            $orderRef    = (string) ($order['order_reference'] ?? '');
            // phpcs:ignore Generic.Files.LineLength
            $appUrl      = rtrim($_ENV['APP_URL'] ?? (defined('APP_URL') ? APP_URL : 'https://crabitanbellevue.fr'), '/');

            if ($clientEmail !== '' && !str_ends_with($clientEmail, '@purged.invalid')) {
                try {
                    $this->newMailService()->sendOrderStatusEmail(
                        $clientEmail,
                        $clientName,
                        $orderRef,
                        $status,
                        $clientLang,
                        $appUrl
                    );
                } catch (\Throwable $e) {
                    \Core\ErrorLogger::warning($e);
                }
            }
        }

        // Génération automatique de la facture dès le premier changement de statut actif (virement/chèque)
        // Désactivable via INVOICE_AUTO_GENERATE=false — cohérent avec IpnController.
        // NB : parse_ini_file() convertit 'false' en chaîne vide → on traite '' comme désactivé.
        $envFlag     = strtolower(trim((string) ($_ENV['INVOICE_AUTO_GENERATE'] ?? 'true')));
        $autoInvoice = !in_array($envFlag, ['', 'false', '0', 'no', 'off'], true);
        if ($autoInvoice && $status !== $oldStatus && empty($order['path_invoice'])) {
            try {
                $fullOrder = $this->orders->findByReferenceForInvoice(
                    (string) ($order['order_reference'] ?? '')
                );
                if ($fullOrder !== null) {
                    $invoiceNumber = sprintf('WEB-INVOICE-%s-%04d', date('Y'), $id);
                    $invoiceDir    = $this->getInvoiceDir();
                    if (!is_dir($invoiceDir)) {
                        mkdir($invoiceDir, 0755, true);
                    }
                    $invoicePath = 'storage/invoices/' . $invoiceNumber . '.pdf';
                    file_put_contents(
                        ROOT_PATH . '/' . $invoicePath,
                        (new InvoiceService())->generate($fullOrder, $invoiceNumber)
                    );
                    $this->orders->updateInvoiceData($id, $invoiceNumber, $invoicePath);
                    $this->orders->addInvoiceFile($id, $invoicePath, $invoiceNumber, 'auto');

                    // Envoi facture par email au client — non bloquant
                    try {
                        $invoiceEmail = (string) ($order['email']     ?? '');
                        $invFname     = (string) ($order['firstname'] ?? '');
                        $invLname     = (string) ($order['lastname']  ?? '');
                        $invoiceName  = trim($invFname . ' ' . $invLname) ?: $invoiceEmail;
                        $invoiceLang  = (string) ($order['lang']      ?? 'fr');
                        if ($invoiceEmail !== '' && !str_ends_with($invoiceEmail, '@purged.invalid')) {
                            $this->newMailService()->sendInvoiceToClient(
                                $invoiceEmail,
                                $invoiceName,
                                (string) ($order['order_reference'] ?? ''),
                                $invoiceNumber,
                                realpath(ROOT_PATH . '/' . $invoicePath) ?: ROOT_PATH . '/' . $invoicePath,
                                $invoiceLang
                            );
                        }
                    } catch (\Throwable $mailErr) {
                        \Core\ErrorLogger::warning($mailErr);
                    }
                }
            } catch (\Throwable $invoiceError) {
                \Core\ErrorLogger::warning($invoiceError);
            }
        }

        $this->flash('success', 'Statut mis à jour.');
        Response::redirect(self::ADMIN_URL . '/' . $id);
    }

    // ----------------------------------------------------------------
    // POST /admin/commandes/{id}/facture
    // ----------------------------------------------------------------

    /**
     * Téléverse une facture PDF pour une commande (POST).
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function uploadInvoice(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL . '/' . $params['id']);
        }

        $id    = (int) $params['id'];
        $order = $this->orders->findByIdForAdmin($id);

        if (!$order) {
            $this->abort(404, self::ORDER_NOT_FOUND);
        }

        // Si une facture existe déjà, exiger confirmation explicite (archivage légal — L441-9 C.com.)
        $existingInvoice = $order['path_invoice'] ?? null;
        if ($existingInvoice !== null && $this->request->post('confirm_replace') !== '1') {
            $this->flash('error', 'Une facture existe déjà. Cochez la case de confirmation pour la remplacer.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $file = $_FILES['invoice'] ?? [];
        if (empty($file['tmp_name'])) {
            $this->flash('error', 'Aucun fichier sélectionné.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if ($mimeType !== 'application/pdf') {
            $this->flash('error', 'Seuls les fichiers PDF sont acceptés.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $destDir = $this->getInvoiceDir() . '/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }

        // Ne pas supprimer l'ancienne facture — obligation d'archivage L441-9 C.com. (10 ans)

        $filename = 'invoice_' . $id . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destPath = $destDir . $filename;
        if (!$this->moveUploadedFile($file['tmp_name'], $destPath)) {
            $this->flash('error', 'Erreur lors de l\'enregistrement du fichier.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $invoicePath = 'storage/invoices/' . $filename;
        $this->orders->updateInvoice($id, $invoicePath);
        $this->orders->addInvoiceFile($id, $invoicePath, null, 'upload');
        $this->flash('success', 'Facture uploadée avec succès.');
        Response::redirect(self::ADMIN_URL . '/' . $id);
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes/{id}/facture/telecharger
    // ----------------------------------------------------------------

    /**
     * Génère et retourne la facture PDF d'une commande.
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function downloadInvoice(array $params): void
    {
        $this->requireAdmin();

        $id    = (int) $params['id'];
        $order = $this->orders->findByIdForAdmin($id);

        if (!$order || empty($order['path_invoice'])) {
            $this->abort(404, 'Facture introuvable');
        }

        $filePath = ROOT_PATH . '/' . $order['path_invoice'];
        if (!is_file($filePath)) {
            $this->abort(404, 'Fichier introuvable');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="facture_' . $order['order_reference'] . '.pdf"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, no-cache');
        $this->streamFile($filePath);
        $this->doExit();
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes/{id}/facture/historique/{fileId}
    // ----------------------------------------------------------------

    /**
     * Télécharge un fichier de l'historique des factures d'une commande.
     *
     * @param array<string, string> $params Paramètres de route : id (commande), fileId (order_invoice_files.id)
     */
    public function downloadInvoiceFile(array $params): void
    {
        $this->requireAdmin();

        $orderId = (int) $params['id'];
        $fileId  = (int) $params['fileId'];

        $files = $this->orders->getInvoiceFiles($orderId);
        $entry = null;
        foreach ($files as $f) {
            if ((int) $f['id'] === $fileId) {
                $entry = $f;
                break;
            }
        }

        if ($entry === null) {
            $this->abort(404, 'Fichier introuvable');
        }

        $filePath = ROOT_PATH . '/' . $entry['path'];
        if (!is_file($filePath)) {
            $this->abort(404, 'Fichier introuvable sur le disque');
        }

        $filename = $entry['invoice_number']
            ? $entry['invoice_number'] . '.pdf'
            : basename($entry['path']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, no-cache');
        $this->streamFile($filePath);
        $this->doExit();
    }
}
