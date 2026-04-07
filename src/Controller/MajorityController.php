<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Jwt;
use Core\Response;
use Model\AccountModel;

/**
 * Gère la déclaration de majorité (Art. L3342-1 CSP).
 *
 * Route : POST /{lang}/declaration-majorite
 */
class MajorityController extends Controller
{
    private AccountModel $accounts;

    /**
     * Initialise les dépendances.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    /**
     * Traite la soumission de la déclaration de majorité.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function declare(array $params): void
    {
        $lang  = $params['lang'] ?? 'fr';
        $token = $this->request->post('csrf_token', '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            Response::redirect("/{$lang}");
        }

        try {
            $payload = Jwt::decode($_COOKIE['auth_token'] ?? '');
        } catch (\Throwable) {
            $payload = null;
        }
        if (!$payload) {
            Response::redirect("/{$lang}");
        }

        $accountId = (int) ($payload['sub'] ?? 0);
        if ($accountId <= 0) {
            Response::redirect("/{$lang}");
        }

        if (($_POST['majority_confirmed'] ?? '') !== '1') {
            $redirect = $_POST['redirect'] ?? "/{$lang}";
            $_SESSION['majority_pending'] = true;
            Response::redirect($redirect);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->accounts->declareMajority($accountId, $ip);

        unset($_SESSION['majority_pending']);

        $redirect = $_POST['redirect'] ?? "/{$lang}/mon-compte";
        Response::redirect($redirect);
    }
}
