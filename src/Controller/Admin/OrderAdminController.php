<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\OrderModel;

class OrderAdminController extends AdminController
{
    private const PER_PAGE = 20;

    private OrderModel $orders;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/commandes
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $page   = max(1, (int) $this->request->get('page', 1));
        $status = $this->request->get('status') ?: null;
        $search = trim($this->request->get('search', ''));

        $total  = $this->orders->countForAdmin($status, $search ?: null);
        $list   = $this->orders->getForAdmin($page, self::PER_PAGE, $status, $search ?: null);

        $this->view('admin/orders/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'orders',
            'pageTitle'    => 'Commandes',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Commandes']],
            'orders'       => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'status'       => $status,
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
                ['label' => 'Commandes', 'url' => '/admin/commandes'],
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
            Response::redirect('/admin/commandes/' . $params['id']);
        }

        $id     = (int) $params['id'];
        $status = $this->request->post('status', '');

        $this->orders->updateStatus($id, $status);
        $this->flash('success', 'Statut mis à jour.');
        Response::redirect('/admin/commandes/' . $id);
    }
}
