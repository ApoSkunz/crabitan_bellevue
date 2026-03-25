<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\AccountModel;
use Model\OrderModel;
use Model\WineModel;

class DashboardController extends AdminController
{
    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();

        $wines    = new WineModel();
        $orders   = new OrderModel();
        $accounts = new AccountModel();

        $this->view('admin/dashboard', [
            'adminUser'     => $adminUser,
            'adminSection'  => 'dashboard',
            'pageTitle'     => 'Tableau de bord',
            'breadcrumbs'   => [['label' => 'Admin'], ['label' => 'Tableau de bord']],
            'winesTotal'    => $wines->countForAdmin(null),
            'winesAvail'    => $wines->countAll(),
            'ordersByStatus' => $orders->countByStatus(),
            'revenue30'      => $orders->getRevenue(30),
            'revenueYear'    => $orders->getRevenueByYear((int) date('Y')),
            'revenueLastYear' => $orders->getRevenueByYear((int) date('Y') - 1),
            'accountsTotal' => $accounts->countTotal(),
            'recentOrders'  => $orders->getRecent(8),
            'flash'          => $this->getFlash('success'),
        ]);
    }
}
