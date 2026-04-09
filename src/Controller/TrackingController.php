<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Exception\HttpException;
use Core\Response;
use Model\AccountModel;
use Model\NewsletterTrackingModel;

/**
 * Tracking pixel (ouverture) et redirect de clic pour les campagnes newsletter.
 * Routes publiques — aucune authentification requise.
 */
class TrackingController extends Controller
{
    private AccountModel $accounts;
    private NewsletterTrackingModel $tracking;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
        $this->tracking = new NewsletterTrackingModel();
    }

    /**
     * GET /track/open/{id}/{token}
     * Retourne un GIF 1×1 et enregistre l'ouverture de l'e-mail (une seule fois par abonné).
     *
     * @param array<string, string> $params
     * @throws HttpException Code 200 après envoi du pixel
     * @return void
     */
    public function open(array $params): void
    {
        $newsletterId = (int) ($params['id'] ?? 0);
        $token        = $params['token'] ?? '';

        if ($newsletterId > 0 && $token !== '') {
            $subscriber = $this->accounts->findByUnsubscribeToken($token);
            if ($subscriber !== false) {
                $this->tracking->recordOpen($newsletterId, (int) $subscriber['id']);
            }
        }

        $this->sendPixel();
    }

    /**
     * GET /track/click/{id}/{linkId}/{token}
     * Enregistre le clic et redirige vers l'URL cible.
     *
     * @param array<string, string> $params
     * @throws HttpException Code 302 (redirect) ou 404 (lien introuvable)
     * @return void
     */
    public function click(array $params): void
    {
        $newsletterId = (int) ($params['id'] ?? 0);
        $linkId       = (int) ($params['linkId'] ?? 0);
        $token        = $params['token'] ?? '';

        $link = $this->tracking->findLink($linkId, $newsletterId);

        if ($link === null) {
            $this->abort(404);
        }

        $subscriberId = null;
        if ($token !== '') {
            $subscriber = $this->accounts->findByUnsubscribeToken($token);
            if ($subscriber !== false) {
                $subscriberId = (int) $subscriber['id'];
            }
        }

        $this->tracking->recordClick($newsletterId, $linkId, $subscriberId);

        Response::redirect($link['url']);
    }

    /**
     * Envoie le pixel GIF 1×1 et termine la réponse.
     * Méthode protégée pour permettre le mock en test.
     *
     * @throws HttpException Code 200
     * @return void
     */
    protected function sendPixel(): void
    {
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- pixel GIF tracking intentionnel
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        throw new HttpException(200);
    }
}
