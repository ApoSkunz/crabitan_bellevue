<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\AccountModel;
use Service\MailService;

/**
 * Gère le double opt-in newsletter côté visiteur.
 *
 * Routes :
 *   GET  /{lang}/newsletter/confirmation  — confirmation via token (email ou profil)
 *   POST /{lang}/newsletter/inscription   — formulaire public d'abonnement
 */
class NewsletterController extends Controller
{
    private AccountModel $accounts;

    /**
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/newsletter/confirmation?token=xxx
    // ----------------------------------------------------------------

    /**
     * Confirme l'abonnement newsletter via le token reçu par email.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function confirmSubscription(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '') {
            $this->flash('error', __('newsletter.confirm_invalid'));
            Response::redirect("/{$lang}");
        }

        $account = $this->accounts->confirmNewsletterByToken($token);

        if (!$account) {
            $this->view('newsletter/confirm', [
                'lang'    => $lang,
                'success' => false,
                'message' => __('newsletter.confirm_invalid'),
            ]);
            return;
        }

        $this->view('newsletter/confirm', [
            'lang'    => $lang,
            'success' => true,
            'message' => __('newsletter.confirm_success'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/newsletter/inscription
    // ----------------------------------------------------------------

    /**
     * Traite le formulaire public d'abonnement newsletter (ex. footer).
     *
     * Double opt-in : envoie un email de confirmation si l'adresse correspond
     * à un compte existant. Retourne toujours le même message pour éviter
     * l'énumération des adresses (RGPD Art. 5 — minimisation).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function subscribe(array $params): void
    {
        $lang  = $params['lang'];
        $email = trim($this->request->post('email', ''));
        $back  = "/{$lang}";

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', __('newsletter.invalid_email'));
            Response::redirect($back);
        }

        if (!$this->verifyCsrf()) {
            $this->flash('error', __('error.csrf'));
            Response::redirect($back);
        }

        $account = $this->accounts->findByEmail($email);

        if ($account && !(bool) ($account['newsletter'] ?? false)) {
            $token = bin2hex(random_bytes(32));
            $this->accounts->storeNewsletterConfirmToken((int) $account['id'], $token);
            $confirmUrl = APP_URL . "/{$lang}/newsletter/confirmation?token=" . urlencode($token);
            (new MailService())->sendNewsletterConfirmation($email, $confirmUrl, $lang);
        }

        // Même message que l'adresse existe ou non — anti-énumération
        $this->flash('success', __('newsletter.confirm_sent'));
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Vérifie le token CSRF du POST courant.
     *
     * @return bool Vrai si le token est valide
     */
    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Ajoute un message flash en session.
     *
     * @param string $key     Clé du message
     * @param string $message Contenu du message
     * @return void
     */
    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }
}
