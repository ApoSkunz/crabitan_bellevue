<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\CronRunModel;

/**
 * Monitoring des exécutions cron — rétention 30 jours.
 * Accès réservé au super_admin.
 */
class CronMonitoringAdminController extends AdminController
{
    private const ALLOWED_PER_PAGE = [25, 50, 100];

    /**
     * Affiche le tableau de bord des runs cron avec pagination.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $perPage = (int) ($_GET['per_page'] ?? 25);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = 25;
        }
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $model = new CronRunModel();
        $total = $model->countAll();
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page  = min($page, $pages);

        $this->view('admin/cron-monitoring/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'cron_monitoring',
            'pageTitle'    => 'Monitoring crons',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Monitoring crons']],
            'summary'      => $model->getSummary24h(),
            'runs'         => $model->getPage($perPage, ($page - 1) * $perPage),
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $pages,
            'totalRuns'    => $total,
            'perPageOpts'  => self::ALLOWED_PER_PAGE,
        ]);
    }
}
