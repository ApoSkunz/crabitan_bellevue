<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Exception\HttpException;
use Middleware\AuthMiddleware;
use Model\OrderModel;

class InvoiceController extends Controller
{
    private OrderModel $orders;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/factures
    // ----------------------------------------------------------------

    /**
     * Liste toutes les factures (version courante par commande) de l'utilisateur connecté.
     *
     * @param  array<string, mixed> $params Paramètres de route (lang)
     * @return void
     */
    public function list(array $params): void
    {
        $this->resolveLang($params);
        $payload  = AuthMiddleware::handle(redirectToLogin: true);
        $userId   = (int) ($payload['sub'] ?? 0);
        $invoices = $this->orders->getInvoiceFilesForUser($userId);

        $this->view('account/invoices', [
            'invoices'      => $invoices,
            'lang'          => $this->lang(),
            'activeSection' => 'invoices',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes/{id}/facture
    // ----------------------------------------------------------------

    /**
     * Télécharge la facture courante d'une commande.
     *
     * @param  array<string, mixed> $params Paramètres de route (lang, id)
     * @return never
     */
    public function download(array $params): never
    {
        $this->resolveLang($params);
        $payload = AuthMiddleware::handle(redirectToLogin: true);

        $userId  = (int) ($payload['sub'] ?? 0);
        $role    = $payload['role'] ?? '';
        $orderId = (int) $params['id'];

        // Admin/super_admin can download any invoice
        if (in_array($role, ['admin', 'super_admin'], true)) {
            $order = $this->orders->findByIdForAdmin($orderId);
        } else {
            // Regular user: must own the order
            $order = $this->orders->findByIdForUser($orderId, $userId);
        }

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
        throw new HttpException(200); // NOSONAR — terminaison identique à exit dans le contexte du routeur
    }
}
