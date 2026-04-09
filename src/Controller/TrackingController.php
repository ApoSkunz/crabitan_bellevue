<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
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

        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- pixel GIF tracking intentionnel
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * GET /track/click/{id}/{linkId}/{token}
     * Enregistre le clic et redirige vers l'URL cible.
     *
     * @param array<string, string> $params
     * @return void
     */
    public function click(array $params): void
    {
        $newsletterId = (int) ($params['id'] ?? 0);
        $linkId       = (int) ($params['linkId'] ?? 0);
        $token        = $params['token'] ?? '';

        $link = $this->tracking->findLink($linkId, $newsletterId);

        if ($link === null) {
            http_response_code(404);
            exit;
        }

        $subscriberId = null;
        if ($token !== '') {
            $subscriber = $this->accounts->findByUnsubscribeToken($token);
            if ($subscriber !== false) {
                $subscriberId = (int) $subscriber['id'];
            }
        }

        $this->tracking->recordClick($newsletterId, $linkId, $subscriberId);

        header('Location: ' . $link['url']);
        exit;
    }
}
