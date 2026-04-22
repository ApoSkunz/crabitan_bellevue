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
    /**
     * Affiche le tableau de bord de monitoring des emails.
     * Accès réservé au super_admin.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $model = new EmailRateLogModel();

        $this->view('admin/email-rate/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'email_rate',
            'pageTitle'    => 'Monitoring emails',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Monitoring emails']],
            'summary'      => $model->getSummary(),
            'recentLogs'   => $model->getRecentLogs(100),
            'currentRole'  => $adminUser['role'] ?? '',
        ]);
    }
}
