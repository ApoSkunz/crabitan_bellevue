<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Service\MailService;

/**
 * Gestion des comptes administrateurs (super_admin uniquement).
 *
 * Permet de lister, bloquer et débloquer les comptes admin/super_admin.
 * Toutes les actions sont réservées au rôle super_admin.
 */
class AdminGestionAdminsController extends AdminController
{
    private const ADMINS_URL = '/admin/admins';

    private AccountModel $accounts;

    /**
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    /**
     * Affiche la liste de tous les comptes admin et super_admin.
     *
     * Accessible uniquement aux super_admin.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $this->view('admin/admins/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'admins',
            'pageTitle'    => 'Gestion des admins',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Gestion des admins'],
            ],
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
            'csrfToken' => $_SESSION['csrf'] ?? '',
            'admins'    => $this->accounts->findAdmins(),
        ]);
    }

    /**
     * Bloque un compte admin.
     *
     * Révoque toutes les sessions + appareils de confiance du compte ciblé.
     * Interdit de s'auto-bloquer (guard self-action → 403).
     *
     * @param array<string, string> $params Paramètres de route, doit contenir 'id'
     * @return void
     */
    public function block(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect(self::ADMINS_URL);
        }

        $targetId = (int) ($params['id'] ?? 0);

        if ($targetId <= 0) {
            $this->flash('error', 'Identifiant invalide.');
            Response::redirect(self::ADMINS_URL);
        }

        if ($targetId === $adminUser['id']) {
            Response::abort(403, 'Action interdite sur votre propre compte.');
        }

        $target = $this->accounts->findById($targetId);
        if (!$target || !in_array($target['role'], ['admin', 'super_admin'], true)) {
            $this->flash('error', 'Compte introuvable ou non admin.');
            Response::redirect(self::ADMINS_URL);
        }

        $this->accounts->blockAccount($targetId);

        $blockerName = (string) ($adminUser['name'] ?? 'Super Admin');
        try {
            (new MailService())->sendAdminAccountBlocked(
                (string) $target['email'],
                trim(($target['firstname'] ?? '') . ' ' . ($target['lastname'] ?? '')) ?: (string) $target['email'],
                (string) ($target['lang'] ?? 'fr'),
                (string) $blockerName
            );
        } catch (\Throwable) {
            // Mail non bloquant
        }

        $this->flash('success', sprintf('Le compte %s a été bloqué.', $target['email']));
        Response::redirect(self::ADMINS_URL);
    }

    /**
     * Débloque un compte admin.
     *
     * @param array<string, string> $params Paramètres de route, doit contenir 'id'
     * @return void
     */
    public function unblock(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect(self::ADMINS_URL);
        }

        $targetId = (int) ($params['id'] ?? 0);

        if ($targetId <= 0) {
            $this->flash('error', 'Identifiant invalide.');
            Response::redirect(self::ADMINS_URL);
        }

        if ($targetId === $adminUser['id']) {
            Response::abort(403, 'Action interdite sur votre propre compte.');
        }

        $target = $this->accounts->findById($targetId);
        if (!$target || !in_array($target['role'], ['admin', 'super_admin'], true)) {
            $this->flash('error', 'Compte introuvable ou non admin.');
            Response::redirect(self::ADMINS_URL);
        }

        $this->accounts->unblockAccount($targetId);

        $unblockerName = (string) ($adminUser['name'] ?? 'Super Admin');
        try {
            (new MailService())->sendAdminAccountUnblocked(
                (string) $target['email'],
                trim(($target['firstname'] ?? '') . ' ' . ($target['lastname'] ?? '')) ?: (string) $target['email'],
                (string) ($target['lang'] ?? 'fr'),
                (string) $unblockerName
            );
        } catch (\Throwable) {
            // Mail non bloquant
        }

        $this->flash('success', sprintf('Le compte %s a été débloqué.', $target['email']));
        Response::redirect(self::ADMINS_URL);
    }
}
