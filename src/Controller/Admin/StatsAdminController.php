<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\OrderModel;

class StatsAdminController extends AdminController
{
    private OrderModel $orders;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->orders = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/statistiques
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $this->requireAdmin();

        $availableYears = $this->orders->getAvailableYears();
        $period         = $_GET['period'] ?? '30d';

        // Validation : accepte uniquement les valeurs connues ou une année présente en BDD
        $validPeriods = ['30d', '3m', '1y', 'all'];
        if (!in_array($period, $validPeriods, true) && !in_array((int) $period, $availableYears, true)) {
            $period = '30d';
        }

        [$from, $to, $granularity, $periodLabel] = $this->resolvePeriod($period);

        $stats     = $this->orders->getStatsForPeriod($from, $to);
        $chartData = $this->orders->getChartData($from, $to, $granularity);

        $adminUser = $this->requireAdmin();

        $this->view('admin/stats/index', [
            'adminUser'      => $adminUser,
            'adminSection'   => 'stats',
            'pageTitle'      => 'Statistiques CA',
            'breadcrumbs'    => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Statistiques CA'],
            ],
            'period'         => $period,
            'periodLabel'    => $periodLabel,
            'availableYears' => $availableYears,
            'stats'          => $stats,
            'chartData'      => $chartData,
            'granularity'    => $granularity,
        ]);
    }

    /**
     * @return array{?string, ?string, string, string}  [from, to, granularity, label]
     */
    private function resolvePeriod(string $period): array
    {
        return match (true) {
            $period === '30d' => [
                date('Y-m-d', strtotime('-29 days')),
                date('Y-m-d'),
                'daily',
                '30 derniers jours',
            ],
            $period === '3m' => [
                date('Y-m-d', strtotime('-3 months')),
                date('Y-m-d'),
                'monthly',
                '3 derniers mois',
            ],
            $period === '1y' => [
                date('Y-m-d', strtotime('-11 months -' . (int) date('d') . ' days + 1 day')),
                date('Y-m-d'),
                'monthly',
                '12 derniers mois',
            ],
            $period === 'all' => [
                null,
                null,
                'yearly',
                'Depuis toujours',
            ],
            default => [ // année ex. "2024"
                $period . '-01-01',
                $period . '-12-31',
                'monthly',
                'Année ' . $period,
            ],
        };
    }
}
