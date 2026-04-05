<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Core\CookieHelper;
use Core\Database;
use Core\Jwt;

/**
 * API de vérification de session — utilisée par le polling côté client.
 * Répond 204 si la session est active, 401 + clear cookie si révoquée ou absente.
 */
class SessionApiController extends Controller
{
    // ----------------------------------------------------------------
    // GET /api/session/ping
    // ----------------------------------------------------------------

    /**
     * Vérifie que le JWT en cookie correspond à une session active en base.
     * Utilisé par le polling BroadcastChannel pour détecter une révocation.
     *
     * Réponses :
     *   - 204 No Content : session active
     *   - 401 Unauthorized + clear cookie : absent, invalide ou révoqué
     *
     * @return never
     */
    public function ping(): never
    {
        $token = $_COOKIE['auth_token'] ?? null;

        if ($token === null) {
            $this->json([], 401);
        }

        try {
            Jwt::decode($token);
        } catch (\Throwable) {
            CookieHelper::clear();
            $this->json([], 401);
        }

        $row = Database::getInstance()->fetchOne(
            "SELECT id FROM connections WHERE token = ? AND status = 'active'",
            [$token]
        );

        if (!$row) {
            CookieHelper::clear();
            $this->json([], 401);
        }

        http_response_code(204);
        exit;
    }
}
