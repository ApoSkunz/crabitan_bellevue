<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\EmailRateLogModel;

/**
 * Interface super_admin pour le monitoring des emails envoyés (rate log).
 *
 * Accès réservé au super_admin — consultation des 25 dernières heures.
 */
class EmailRateAdminController extends AdminController
{
    private const ALLOWED_PER_PAGE = [25, 50, 100];

    /**
     * Affiche le tableau de bord de monitoring des emails avec pagination.
     * Accès réservé au super_admin.
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
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $model    = new EmailRateLogModel();
        $total    = $model->countAll();
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page     = min($page, $pages);
        $offset   = ($page - 1) * $perPage;

        $this->view('admin/email-rate/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'email_rate',
            'pageTitle'    => 'Monitoring emails',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Monitoring emails']],
            'summary'      => $model->getSummary(),
            'logs'         => $model->getPage($perPage, $offset),
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $pages,
            'totalLogs'    => $total,
            'perPageOpts'  => self::ALLOWED_PER_PAGE,
            'currentRole'  => $adminUser['role'] ?? '',
        ]);
    }
}
