<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Model\GoogleLinkTokenModel;

/**
 * Endpoint de polling pour le rattachement compte Google (Cas 2).
 * Appelé toutes les 2 s depuis la page "email envoyé" jusqu'à confirmation ou expiration.
 */
class GoogleLinkStatusController extends Controller
{
    private GoogleLinkTokenModel $linkTokens;

    /**
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->linkTokens = new GoogleLinkTokenModel();
    }

    /**
     * GET /api/google-link-status
     *
     * Retourne :
     *   {"status":"pending"}  — token actif, rattachement pas encore effectué
     *   {"status":"linked"}   — token consommé → rattachement réussi
     *   {"status":"expired"}  — token introuvable/expiré ou session absente
     *
     * Rate limit : 1 requête/s par session.
     *
     * @param array<string, mixed> $params Paramètres de route (non utilisés)
     * @return void
     */
    public function status(array $params): void // NOSONAR — $params requis par le router
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now      = time();
        $lastCall = (int) ($_SESSION['google_link_status_last_call'] ?? 0);
        if ($now - $lastCall < 1) {
            $this->json(['status' => 'pending'], 429);
        }
        $_SESSION['google_link_status_last_call'] = $now;

        $plainToken = (string) ($_SESSION['pending_google_link_token'] ?? '');
        if ($plainToken === '') {
            $this->json(['status' => 'expired']);
        }

        $consumed = $this->linkTokens->isConsumed($plainToken);

        if ($consumed === null) {
            $this->json(['status' => 'expired']);
        }

        if ($consumed) {
            unset($_SESSION['pending_google_link_token'], $_SESSION['google_link_status_last_call']);
            $this->json(['status' => 'linked']);
        }

        $this->json(['status' => 'pending']);
    }
}
