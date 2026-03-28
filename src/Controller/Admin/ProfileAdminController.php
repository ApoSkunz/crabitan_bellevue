<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;

class ProfileAdminController extends AdminController
{
    private AccountModel $accounts;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/mon-profil
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/profile/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'profile',
            'pageTitle'    => 'Mon profil',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Mon profil'],
            ],
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
            'csrfToken' => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/mon-profil/mot-de-passe
    // ----------------------------------------------------------------

    public function changePassword(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect('/admin/mon-profil');
        }

        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('new_password_confirm', '');

        $account = $this->accounts->findById($adminUser['id']);

        if (!$account || !password_verify($current, $account['password'] ?? '')) {
            $this->flash('error', 'Mot de passe actuel incorrect.');
            Response::redirect('/admin/mon-profil');
        }

        if (strlen($new) < 12) {
            $this->flash('error', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
            Response::redirect('/admin/mon-profil');
        }

        if ($new !== $confirm) {
            $this->flash('error', 'Les mots de passe ne correspondent pas.');
            Response::redirect('/admin/mon-profil');
        }

        $this->accounts->updatePassword($adminUser['id'], password_hash($new, PASSWORD_BCRYPT));
        $this->flash('success', 'Mot de passe mis à jour avec succès.');
        Response::redirect('/admin/mon-profil');
    }
}
