<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AdminLogModel;

/**
 * Interface super_admin pour la consultation et l'export du journal d'audit.
 *
 * Accès réservé au super_admin — journal immuable, pas de suppression exposée.
 * Rétention recommandée : 3 ans (Art. 5.2 RGPD accountability).
 */
class AuditLogAdminController extends AdminController
{
    private const DEFAULT_PER_PAGE = 50;
    private const ALLOWED_PER_PAGE = [25, 50, 100];

    private AdminLogModel $logModel;

    /**
     * @param \Core\Request $request
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->logModel = new AdminLogModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/logs
    // ----------------------------------------------------------------

    /**
     * Liste paginée du journal d'audit avec filtres (admin, action, dates).
     * Accès réservé au super_admin.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $page       = max(1, (int) $this->request->get('page', 1));
        $perPageReq = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage    = in_array($perPageReq, self::ALLOWED_PER_PAGE, true) ? $perPageReq : self::DEFAULT_PER_PAGE;

        $filters = $this->extractFilters();

        $total = $this->logModel->count($filters);
        $logs  = $this->logModel->fetchFiltered($filters, $perPage, ($page - 1) * $perPage);

        $this->view('admin/logs/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'logs',
            'pageTitle'    => 'Journal d\'audit',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Journal d\'audit']],
            'logs'         => $logs,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'filters'      => $filters,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/logs/export
    // ----------------------------------------------------------------

    /**
     * Export CSV du journal d'audit (filtres identiques à index).
     * Accès réservé au super_admin.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function export(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $this->requireSuperAdmin();

        $filters  = $this->extractFilters();
        $csv      = $this->logModel->exportCsv($filters);
        $filename = 'audit-log-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        $this->doExit();
    }

    /**
     * Termine le processus courant.
     * Méthode protégée pour permettre l'override dans les tests.
     *
     * @return never
     */
    protected function doExit(): never
    {
        exit; // NOSONAR — php:S1763 : sortie volontaire après envoi CSV (réponse HTTP complète)
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    /**
     * Extrait et assainit les filtres depuis la requête GET.
     *
     * @return array{admin_id?: int, action?: string, date_from?: string, date_to?: string}
     */
    private function extractFilters(): array
    {
        $filters = [];

        $adminId = (int) $this->request->get('admin_id', 0);
        if ($adminId > 0) {
            $filters['admin_id'] = $adminId;
        }

        $action = trim($this->request->get('action', ''));
        if ($action !== '') {
            $filters['action'] = $action;
        }

        $dateFrom = trim($this->request->get('date_from', ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }

        $dateTo = trim($this->request->get('date_to', ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        return $filters;
    }
}
