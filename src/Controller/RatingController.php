<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Middleware\AuthMiddleware;

/**
 * Gère les actions de notation des vins (par email et depuis l'espace client).
 */
class RatingController extends Controller
{
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
            $this->view('rating/error', ['lang' => $lang, 'reason' => 'invalid_note']);
            return;
        }

        // @phpstan-ignore-next-line
        $ratingModel = new \Model\RatingModel();
        $row = $ratingModel->findByToken($params['token'] ?? '');

        if ($row === null) {
            $this->view('rating/error', ['lang' => $lang, 'reason' => 'invalid_token']);
            return;
        }

        $accountId = (int) $row['account_id'];
        $wineId    = (int) $row['wine_id'];
        $orderId   = (int) $row['order_id'];

        // @phpstan-ignore-next-line
        $ratingModel->upsert($accountId, $wineId, $orderId, $note);
        // @phpstan-ignore-next-line
        $ratingModel->markTokenUsed($params['token']);

        $this->view('rating/confirm', ['note' => $note, 'lang' => $lang]);
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

        // @phpstan-ignore-next-line
        $ratingModel = new \Model\RatingModel();

        // @phpstan-ignore-next-line
        if (!$ratingModel->hasOrdered($userId, $wineId)) {
            $this->json(['success' => false, 'message' => 'Achat requis'], 403);
        }

        // @phpstan-ignore-next-line
        $ratingModel->upsert($userId, $wineId, 0, $note);

        // @phpstan-ignore-next-line
        $existing = $ratingModel->getByAccountAndWine($userId, $wineId);
        $avg      = $existing !== null ? ($existing['avg'] ?? null) : null;
        $count    = $existing !== null ? (int) ($existing['count'] ?? 0) : 0;

        $this->json(['success' => true, 'avg' => $avg, 'count' => $count]);
    }
}
