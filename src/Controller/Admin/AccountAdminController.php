<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;

class AccountAdminController extends AdminController
{
    private const ALLOWED_PER_PAGE = [10, 25, 50];
    private const DEFAULT_PER_PAGE = 10;

    // ----------------------------------------------------------------
    // GET /admin/comptes
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser  = $this->requireAdmin();
        $accounts   = new AccountModel();
        $page       = max(1, (int) $this->request->get('page', 1));
        $role       = $this->request->get('role') ?: null;
        $type       = $this->request->get('type') ?: null;
        $search     = trim($this->request->get('search', ''));
        $perPageReq = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage    = in_array($perPageReq, self::ALLOWED_PER_PAGE, true) ? $perPageReq : self::DEFAULT_PER_PAGE;

        $total = $accounts->countForAdmin($role, $search ?: null, $type);
        $list  = $accounts->getForAdmin($perPage, ($page - 1) * $perPage, $role, $search ?: null, $type);

        $this->view('admin/accounts/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'accounts',
            'pageTitle'    => 'Comptes clients',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Comptes']],
            'accounts'     => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'role'         => $role,
            'type'         => $type,
            'search'       => $search,
            'currentRole'  => $adminUser['role'] ?? '',
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/comptes/{id}/verifier  (super_admin uniquement)
    // ----------------------------------------------------------------

    public function verify(array $params): void
    {
        $adminUser = $this->requireAdmin();

        if (($adminUser['role'] ?? '') !== 'super_admin') {
            $this->abort(403, 'Réservé au super administrateur.');
        }

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/comptes');
        }

        $id      = (int) $params['id'];
        $account = new AccountModel();
        $account->verifyEmail($id);

        $this->flash('success', "Compte #{$id} vérifié avec succès.");
        Response::redirect('/admin/comptes');
    }
}
