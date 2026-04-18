<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Database;
use Middleware\AuthMiddleware;
use Service\RateLimiterService;

/**
 * Gère les actions de notation des vins (par email et depuis l'espace client).
 */
class RatingController extends Controller
{
    /** Vue d'erreur rating (évite la duplication — SonarCloud php:S1192). */
    private const ERROR_VIEW = 'rating/error';

    // ----------------------------------------------------------------
    // GET /rating/commande/{token}/{note}
    // ----------------------------------------------------------------

    /**
     * Traite la notation d'un vin via un lien envoyé par email.
     *
     * @param array<string, string> $params Paramètres de route (token, note)
     * @return void
     */
    public function rateViaEmail(array $params): void
    {
        $lang = isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['fr', 'en'], true)
            ? $_COOKIE['lang']
            : 'fr';

        $note = (int) ($params['note'] ?? 0);

        if ($note < 1 || $note > 5) {
            $this->view(self::ERROR_VIEW, ['lang' => $lang, 'reason' => 'invalid_note']);
            return;
        }

        $ratingModel = new \Model\RatingModel();
        $row = $ratingModel->findByToken($params['token'] ?? '');

        if ($row === null) {
            $this->view(self::ERROR_VIEW, ['lang' => $lang, 'reason' => 'invalid_token']);
            return;
        }

        $accountId = (int) $row['account_id'];
        $wineId    = (int) $row['wine_id'];
        $orderId   = (int) $row['order_id'];

        $ratingModel->upsert($accountId, $wineId, $orderId, $note);
        $ratingModel->markTokenUsed($params['token']);

        $this->view('rating/confirm', ['note' => $note, 'lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // GET /rating/order/{orderToken}
    // ----------------------------------------------------------------

    /**
     * Page de notation d'une commande (tous les vins) via token email.
     * GET /rating/order/{orderToken}
     *
     * @param array<string, string> $params Paramètres de route (orderToken)
     * @return void
     */
    public function rateOrder(array $params): void
    {
        $lang = isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['fr', 'en'], true)
            ? $_COOKIE['lang']
            : 'fr';

        $orderToken  = $params['orderToken'] ?? '';
        $ratingModel = new \Model\RatingModel();
        $resolved    = $ratingModel->resolveOrderToken($orderToken);

        if ($resolved === null) {
            $this->view(self::ERROR_VIEW, ['lang' => $lang, 'reason' => 'invalid_token']);
            return;
        }

        $orderId   = (int) $resolved['order_id'];
        $accountId = (int) $resolved['account_id'];

        $orderRow = Database::getInstance()->fetchOne(
            "SELECT id, content FROM orders WHERE id = ? AND user_id = ?",
            [$orderId, $accountId]
        );

        if ($orderRow === false) {
            $this->view(self::ERROR_VIEW, ['lang' => $lang, 'reason' => 'invalid_token']);
            return;
        }

        /** @var array<int, array<string, mixed>> $items */
        $items   = json_decode((string) ($orderRow['content'] ?? '[]'), true) ?? [];
        $wineModel = new \Model\WineModel();

        $wines = [];
        foreach ($items as $item) {
            $wineId = (int) ($item['wine_id'] ?? 0);
            if ($wineId === 0) {
                continue;
            }

            $wine       = $wineModel->getById($wineId);
            $existing   = $ratingModel->getByAccountAndWine($accountId, $wineId);
            $wineToken  = $ratingModel->generateEmailToken($accountId, $wineId, $orderId);
            $currentNote = $existing !== null ? (int) ($existing['rating'] ?? 0) : 0;

            $wines[] = [
                'wine_id'      => $wineId,
                'label_name'   => $wine['label_name'] ?? ($item['label_name'] ?? ''),
                'vintage'      => (int) ($wine['vintage'] ?? $item['vintage'] ?? 0),
                'image_path'   => $wine['image_path'] ?? ($item['image_path'] ?? ''),
                'token'        => $wineToken,
                'current_note' => $currentNote,
            ];
        }

        $noted = isset($_GET['noted']) && $_GET['noted'] === '1';

        $this->view('rating/order', [
            'lang'        => $lang,
            'orderId'     => $orderId,
            'orderToken'  => $orderToken,
            'wines'       => $wines,
            'noted'       => $noted,
            'noindex'     => true,
            'pageTitle'   => $lang === 'fr'
                ? "Notez vos vins — Commande #{$orderId}"
                : "Rate your wines — Order #{$orderId}",
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/rating/commande  (AJAX depuis la page /rating/order)
    // ----------------------------------------------------------------

    /**
     * Enregistre la note d'un vin via token email (appel AJAX).
     * Accepte : {token: string, note: int (1-5)}.
     * Retourne : {success: bool, note: int}.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function rateViaTokenApi(array $params): void // NOSONAR — $params requis par le router
    {
        $ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $limiter = new RateLimiterService(Database::getInstance());
        $rlKey   = 'rating_token:' . $ip;
        if (!$limiter->checkLimit($rlKey, 30, 60)) {
            $this->json(['success' => false, 'message' => 'Trop de requêtes'], 429);
        }
        $limiter->recordAttempt($rlKey, 60);

        $body  = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $token = (string) ($body['token'] ?? '');
        $note  = (int) ($body['note']  ?? 0);

        if ($token === '' || $note < 1 || $note > 5) {
            $this->json(['success' => false, 'message' => 'Paramètres invalides'], 422);
        }

        $ratingModel = new \Model\RatingModel();
        $row = $ratingModel->findByToken($token);

        if ($row === null) {
            $this->json(['success' => false, 'message' => 'Token invalide'], 404);
        }

        $ratingModel->upsert((int) $row['account_id'], (int) $row['wine_id'], (int) $row['order_id'], $note);
        $ratingModel->markTokenUsed($token); // VULN-016 : consommer le token après notation AJAX (était absent)

        $this->json(['success' => true, 'note' => $note]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/rating/vin/{wineId}
    // ----------------------------------------------------------------

    /**
     * Traite la notation d'un vin depuis l'espace client (authentifié).
     *
     * @param array<string, string> $params Paramètres de route (lang, wineId)
     * @return void
     */
    public function rateViaAccount(array $params): void
    {
        $payload = AuthMiddleware::handle(redirectToLogin: false);
        $userId  = (int) $payload['sub'];

        $csrfPosted = $this->request->post('csrf_token', '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfPosted)) {
            $this->json(['success' => false, 'message' => 'CSRF invalide'], 403);
        }

        $note   = (int) $this->request->post('note', '0');
        $wineId = (int) ($params['wineId'] ?? 0);

        if ($note < 1 || $note > 5) {
            $this->json(['success' => false, 'message' => 'Note invalide (1-5)'], 422);
        }

        $ratingModel = new \Model\RatingModel();

        if (!$ratingModel->hasOrdered($userId, $wineId)) {
            $this->json(['success' => false, 'message' => 'Achat requis'], 403);
        }

        $ratingModel->upsert($userId, $wineId, 0, $note);

        $existing = $ratingModel->getByAccountAndWine($userId, $wineId);
        $avg      = $existing !== null ? ($existing['avg'] ?? null) : null;
        $count    = $existing !== null ? (int) ($existing['count'] ?? 0) : 0;

        $this->json(['success' => true, 'avg' => $avg, 'count' => $count]);
    }
}
