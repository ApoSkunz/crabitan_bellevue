<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\OrderModel;

class OrderAdminController extends AdminController
{
    private const ADMIN_URL         = '/admin/commandes';
    private const DEFAULT_PER_PAGE  = 10;
    private const ALLOWED_PER_PAGES = [10, 25, 50];

    private OrderModel $orders;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::ALLOWED_PER_PAGES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $status  = $this->request->get('status') ?: null;
        $payment = $this->request->get('payment') ?: null;
        $search  = trim($this->request->get('search', ''));

        $total = $this->orders->countForAdmin($status, $search ?: null, $payment);
        $list  = $this->orders->getForAdmin($page, $perPage, $status, $search ?: null, $payment);

        $this->view('admin/orders/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'orders',
            'pageTitle'    => 'Commandes',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Commandes']],
            'orders'       => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'status'       => $status,
            'payment'      => $payment,
            'search'       => $search,
            'flash'        => $this->getFlash('success'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes/{id}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $order     = $this->orders->findByIdForAdmin((int) $params['id']);

        if (!$order) {
            $this->abort(404, 'Commande introuvable');
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
            'order'     => $order,
            'csrfToken' => $_SESSION['csrf'] ?? '',
            'flash'     => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/commandes/{id}/statut
    // ----------------------------------------------------------------

    public function updateStatus(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL . '/' . $params['id']);
        }

        $id     = (int) $params['id'];
        $status = $this->request->post('status', '');

        $this->orders->updateStatus($id, $status);
        $this->flash('success', 'Statut mis à jour.');
        Response::redirect(self::ADMIN_URL . '/' . $id);
    }

    // ----------------------------------------------------------------
    // POST /admin/commandes/{id}/facture
    // ----------------------------------------------------------------

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
            $this->abort(404, 'Commande introuvable');
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

        $destDir = ROOT_PATH . '/storage/invoices/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }

        // Remove old invoice file if it exists
        $oldPath = $order['path_invoice'] ?? null;
        if ($oldPath !== null && is_file(ROOT_PATH . '/' . $oldPath)) {
            unlink(ROOT_PATH . '/' . $oldPath);
        }

        $filename = 'invoice_' . $id . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destPath = $destDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->flash('error', 'Erreur lors de l\'enregistrement du fichier.');
            Response::redirect(self::ADMIN_URL . '/' . $id);
        }

        $this->orders->updateInvoice($id, 'storage/invoices/' . $filename);
        $this->flash('success', 'Facture uploadée avec succès.');
        Response::redirect(self::ADMIN_URL . '/' . $id);
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes/{id}/facture/telecharger
    // ----------------------------------------------------------------

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
        readfile($filePath);
        exit;
    }
}
