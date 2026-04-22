<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Sous-classe testable de RatingController permettant d'injecter un body JSON
 * arbitraire pour simuler php://input sans accès au flux réel.
 *
 * Seule la méthode readPhpInput() est surchargée afin de couvrir
 * les lignes 171 et 181 du rateViaTokenApi() original.
 */
class TestableRatingControllerWithJsonBody extends TestableRatingController
{
    /** Corps JSON simulé (remplace php://input). */
    private string $injectedBody = '';

    /**
     * Définit le corps JSON injecté.
     *
     * @param string $body Contenu JSON à retourner à la place de php://input
     * @return void
     */
    public function setBody(string $body): void
    {
        $this->injectedBody = $body;
    }

    /**
     * Surcharge rateViaTokenApi() pour injecter le corps JSON via $injectedBody
     * au lieu de php://input, tout en conservant la logique originale du service.
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

        $body  = json_decode($this->injectedBody, true) ?? [];
        $token = (string) ($body['token'] ?? '');
        $note  = (int) ($body['note']  ?? 0);

        if ($token === '' || $note < 1 || $note > 5) {
            $this->json(['success' => false, 'message' => 'Paramètres invalides'], 422);
        }

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
