<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
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
    // GET /{lang}/mon-compte/commandes/{id}/facture
    // ----------------------------------------------------------------

    public function download(array $params): void
    {
        $this->resolveLang($params);
        $payload = AuthMiddleware::handle();

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
        exit;
    }
}
