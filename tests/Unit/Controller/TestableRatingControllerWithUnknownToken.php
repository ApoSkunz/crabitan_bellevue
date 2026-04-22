<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Variante de TestableRatingController simulant un body JSON avec token inconnu.
 * Utilisé pour tester rateViaTokenApi() sans accès à php://input.
 */
class TestableRatingControllerWithUnknownToken extends TestableRatingController
{
    /**
     * Surcharge rateViaTokenApi pour injecter un body simulé (token=inconnu, note=3).
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function rateViaTokenApi(array $params): void // NOSONAR — $params requis par le router
    {
        $ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $limiter = new \Service\RateLimiterService(\Core\Database::getInstance());
        $rlKey   = 'rating_token:' . $ip;
        if (!$limiter->checkLimit($rlKey, 30, 60)) {
            $this->json(['success' => false, 'message' => 'Trop de requêtes'], 429);
        }
        $limiter->recordAttempt($rlKey, 60);

        $token = 'inexistant-token';
        $note  = 3;

        $ratingModel = new \Model\RatingModel();
        $row         = $ratingModel->findByToken($token);

        if ($row === null) {
            $this->json(['success' => false, 'message' => 'Token invalide'], 404);
        }

        $ratingModel->upsert((int) $row['account_id'], (int) $row['wine_id'], (int) $row['order_id'], $note);
        $ratingModel->markTokenUsed($token);

        $this->json(['success' => true, 'note' => $note]);
    }
}
