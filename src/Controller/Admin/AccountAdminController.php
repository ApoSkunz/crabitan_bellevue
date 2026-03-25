<?php

declare(strict_types=1);

namespace Controller\Admin;

use Model\AccountModel;

class AccountAdminController extends AdminController
{
    private const PER_PAGE = 25;

    // ----------------------------------------------------------------
    // GET /admin/comptes
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $accounts  = new AccountModel();

        $page   = max(1, (int) $this->request->get('page', 1));
        $role   = $this->request->get('role') ?: null;
        $search = trim($this->request->get('search', ''));

        $total = $accounts->countForAdmin($role, $search ?: null);
        $list  = $accounts->getForAdmin(self::PER_PAGE, ($page - 1) * self::PER_PAGE, $role, $search ?: null);

        $this->view('admin/accounts/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'accounts',
            'pageTitle'    => 'Comptes clients',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Comptes']],
            'accounts'     => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'role'         => $role,
            'search'       => $search,
        ]);
    }
}
