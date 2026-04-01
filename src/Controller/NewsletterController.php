<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\NewsletterSubscriptionModel;
use Service\MailService;
use Service\NewsletterService;

/**
 * Gère le flux de double opt-in newsletter (RGPD Art. 7).
 *
 * Routes :
 *   GET /{lang}/newsletter/confirmation?token=...  → confirmSubscription()
 *   POST /{lang}/newsletter/inscription            → subscribe()
 */
class NewsletterController extends Controller
{
    private NewsletterService $newsletterService;

    /**
     * Initialise le controller avec ses dépendances.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $model                   = new NewsletterSubscriptionModel();
        $mailer                  = new MailService();
        $this->newsletterService = new NewsletterService($model, $mailer);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/newsletter/confirmation?token=...
    // ----------------------------------------------------------------

    /**
     * Confirme un abonnement newsletter via le token reçu par email.
     *
     * Vérifie le hash SHA-256 du token et son TTL (48h).
     * Affiche une vue de succès ou d'erreur selon le résultat.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function confirmSubscription(array $params): void
    {
        $lang     = $this->resolveLang($params);
        $rawToken = $this->request->get('token', '');

        if ($rawToken === '') {
            $this->view('newsletter/confirmation', [
                'lang'    => $lang,
                'success' => false,
                'reason'  => 'invalid',
            ]);
            return;
        }

        try {
            $this->newsletterService->confirmSubscription($rawToken);

            $this->view('newsletter/confirmation', [
                'lang'    => $lang,
                'success' => true,
                'reason'  => null,
            ]);
        } catch (\RuntimeException $e) {
            $this->view('newsletter/confirmation', [
                'lang'    => $lang,
                'success' => false,
                'reason'  => $e->getMessage(), // 'invalid' ou 'expired'
            ]);
        }
    }

    // ----------------------------------------------------------------
    // POST /{lang}/newsletter/inscription
    // ----------------------------------------------------------------

    /**
     * Inscrit un email à la newsletter et déclenche l'envoi du mail de confirmation.
     *
     * Applique un rate limiting BDD (3 envois / 24h par email).
     * Répond toujours en JSON pour les formulaires AJAX du footer.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function subscribe(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $email = strtolower(trim($this->request->post('email', '')));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json([
                'success' => false,
                'error'   => __('newsletter.invalid_email'),
            ], 422);
        }

        try {
            $this->newsletterService->subscribe($email, $lang, $ip);

            $this->json([
                'success' => true,
                'message' => __('newsletter.confirm_sent'),
            ]);
        } catch (\RuntimeException $e) {
            $errorKey = match ($e->getMessage()) {
                'rate_limit'        => 'newsletter.rate_limit',
                'already_confirmed' => 'newsletter.already_confirmed',
                default             => 'error.generic',
            };

            $this->json([
                'success' => false,
                'error'   => __($errorKey),
            ], 429);
        }
    }
}
