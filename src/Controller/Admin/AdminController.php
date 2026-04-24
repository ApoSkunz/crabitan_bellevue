<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Controller;
use Middleware\AdminMiddleware;
use Model\AccountModel;

abstract class AdminController extends Controller
{
    /**
     * Vérifie le JWT admin et retourne les infos de l'utilisateur courant.
     *
     * @return array{id: int, role: string, name: string, email: string}
     */
    protected function requireAdmin(): array
    {
        $payload = AdminMiddleware::handle();
        $this->resolveLang(['lang' => 'fr']);

        $account = (new AccountModel())->findById((int) $payload['sub']);
        $name  = 'Admin';
        $email = '';
        if ($account) {
            $full  = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
            $name  = $full !== '' ? $full : ($account['company_name'] ?? 'Admin');
            $email = (string) ($account['email'] ?? '');
        }

        return [
            'id'    => (int) $payload['sub'],
            'role'  => $payload['role'] ?? 'admin',
            'name'  => $name,
            'email' => $email,
        ];
    }

    /**
     * Vérifie le JWT admin ET que le rôle est super_admin.
     * Renvoie une 404 sinon (évite de révéler l'existence de la route).
     *
     * @return array{id: int, role: string, name: string, email: string}
     */
    protected function requireSuperAdmin(): array
    {
        $adminUser = $this->requireAdmin();

        if (($adminUser['role'] ?? '') !== 'super_admin') {
            $this->abort(404);
        }

        return $adminUser;
    }

    /**
     * Vérifie le token CSRF de la requête POST courante.
     *
     * @return bool True si le token est valide
     */
    protected function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Stocke un message flash en session admin.
     *
     * @param string $key     Clé du message (ex. 'success', 'error')
     * @param string $message Contenu du message
     * @return void
     */
    protected function flash(string $key, string $message): void
    {
        $_SESSION['admin_flash'][$key] = $message;
    }

    /**
     * Lit et supprime un message flash de la session admin.
     *
     * @param string $key Clé du message
     * @return string|null Message ou null si absent
     */
    protected function getFlash(string $key): ?string
    {
        $msg = $_SESSION['admin_flash'][$key] ?? null;
        unset($_SESSION['admin_flash'][$key]);
        return $msg;
    }
}
