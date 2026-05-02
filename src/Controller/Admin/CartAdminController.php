<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\CartModel;

/**
 * Liste paginée de tous les paniers actifs (back-office).
 */
class CartAdminController extends AdminController
{
    private const DEFAULT_PER_PAGE  = 25;
    private const ALLOWED_PER_PAGES = [25, 50, 100];

    private CartModel $carts;

    /**
     * @param \Core\Request $request
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->carts = new CartModel();
    }

    /**
     * GET /admin/paniers — liste paginée de tous les paniers.
     *
     * @param  array<string, mixed> $_params Paramètres de route (non utilisés)
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

        $total = $this->carts->countAll();
        $carts = $this->carts->findAllPaginated($page, $perPage);

        $this->view('admin/carts/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'carts',
            'pageTitle'    => 'Paniers',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Paniers'],
            ],
            'carts'   => $carts,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }
}
