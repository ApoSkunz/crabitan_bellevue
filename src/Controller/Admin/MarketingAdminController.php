<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\AccountModel;
use Model\NewsletterModel;
use Model\OrderModel;

/**
 * Dashboard statistiques marketing.
 */
class MarketingAdminController extends AdminController
{
    private OrderModel $orders;
    private AccountModel $accounts;
    private NewsletterModel $newsletter;

    /**
     * Initialise le contrôleur marketing avec les modèles nécessaires.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders     = new OrderModel();
        $this->accounts   = new AccountModel();
        $this->newsletter = new NewsletterModel();
    }

    /**
     * Affiche le tableau de bord marketing.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $avgOrder        = $this->orders->getAvgOrderValue(30);
        $segmentation    = $this->accounts->getSegmentation();
        $newsletterStats = $this->newsletter->getStats();

        $topRated = [];
        try {
            $topRated = (new \Model\RatingModel())->getTopRated(10);
        } catch (\Throwable) {
        }

        $this->view('admin/marketing/index', [
            'adminUser'       => $adminUser,
            'adminSection'    => 'marketing',
            'pageTitle'       => 'Dashboard Marketing',
            'breadcrumbs'     => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Marketing'],
            ],
            'avgOrder'        => $avgOrder,
            'segmentation'    => $segmentation,
            'newsletterStats' => $newsletterStats,
            'topRated'        => $topRated,
        ]);
    }
}
