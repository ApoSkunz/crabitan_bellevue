<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Controller;
use Core\Response;
use Middleware\AuthMiddleware;

/**
 * Classe de base partagée par tous les controllers de l'espace compte.
 *
 * Centralise les helpers communs (CSRF, guard customer) pour éviter la duplication
 * entre AccountProfileController, AccountSecurityController, etc.
 */
abstract class AbstractAccountController extends Controller
{
    /**
     * Vérifie le token CSRF de la requête.
     *
     * @return bool
     */
    protected function verifyCsrf(): bool
    {
        $submitted = $this->request->post('csrf_token', '');
        $session   = $_SESSION['csrf'] ?? '';
        $valid     = $session !== '' && hash_equals($session, $submitted);
        if ($valid) {
            // VULN-008 : régénérer le token après usage pour limiter la fenêtre de rejeu
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $valid;
    }

    /**
     * Vérifie que la requête provient d'un customer authentifié.
     *
     * Redirige vers la page de connexion si non authentifié.
     * Retourne une 404 si le rôle n'est pas 'customer'.
     *
     * @return array<string, mixed> Payload JWT décodé
     */
    protected function requireCustomer(): array
    {
        $payload = AuthMiddleware::handle(redirectToLogin: true);
        if (($payload['role'] ?? '') !== 'customer') {
            Response::abort(404);
        }
        return $payload;
    }
}
