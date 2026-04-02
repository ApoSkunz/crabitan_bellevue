<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Exception\NewsletterException;
use Model\AccountModel;
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
    /** Chemin de la vue de confirmation (évite la duplication — SonarCloud php:S1192). */
    private const CONFIRM_VIEW = 'newsletter/confirmation';

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
        $accounts                = new AccountModel();
        $this->newsletterService = new NewsletterService($model, $mailer, $accounts);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/newsletter/confirmation?token=...
    // ----------------------------------------------------------------

    /**
     * Confirme un abonnement newsletter via le token reçu par email.
     *
     * Vérifie le hash SHA-256 du token (flux visiteur) ou le token brut
     * (flux accounts) et son TTL (48h).
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
            $this->view(self::CONFIRM_VIEW, [
                'lang'    => $lang,
                'success' => false,
                'reason'  => 'invalid',
            ]);
            return;
        }

        try {
            $this->newsletterService->confirmSubscription($rawToken);

            $this->view(self::CONFIRM_VIEW, [
                'lang'    => $lang,
                'success' => true,
                'reason'  => null,
            ]);
        } catch (NewsletterException $e) {
            $this->view(self::CONFIRM_VIEW, [
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
     * Route vers le flux accounts si l'email est rattaché à un compte,
     * sinon flux visiteur avec rate limiting (3 envois / 24h).
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
        } catch (\Core\Exception\HttpException $e) {
            // HttpException étend RuntimeException — laisser remonter (déjà traité par Response::json)
            throw $e;
        } catch (NewsletterException $e) {
            // already_confirmed retourne le même message neutre que le succès
            // pour éviter l'énumération d'emails (OWASP — information disclosure)
            if ($e->getMessage() === 'already_confirmed') {
                $this->json([
                    'success' => true,
                    'message' => __('newsletter.confirm_sent'),
                ]);
            }

            $errorKey = match ($e->getMessage()) {
                'rate_limit' => 'newsletter.rate_limit',
                default      => 'error.generic',
            };

            $this->json([
                'success' => false,
                'error'   => __($errorKey),
            ], 429);
        }
    }
}
