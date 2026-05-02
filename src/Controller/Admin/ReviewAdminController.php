<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\ReviewModel;

/**
 * Liste paginée de tous les avis clients (back-office).
 */
class ReviewAdminController extends AdminController
{
    private const DEFAULT_PER_PAGE  = 25;
    private const ALLOWED_PER_PAGES = [25, 50, 100];

    private ReviewModel $reviews;

    /**
     * @param \Core\Request $request
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->reviews = new ReviewModel();
    }

    /**
     * GET /admin/avis — liste paginée de tous les avis.
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

        $total   = $this->reviews->countAll();
        $reviews = $this->reviews->findPaginated($page, $perPage);

        $this->view('admin/reviews/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'reviews',
            'pageTitle'    => 'Avis clients',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Avis clients'],
            ],
            'reviews'  => $reviews,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
        ]);
    }
}
