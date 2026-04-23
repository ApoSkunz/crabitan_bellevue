<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Variante de TestableRatingController simulant un body JSON avec token valide.
 * Utilisé pour tester le happy path de rateViaTokenApi() sans accès à php://input.
 *
 * Surcharge uniquement readRequestBody() (seam) — la méthode cible originale
 * est bien exécutée et couverte.
 */
class TestableRatingControllerWithValidToken extends TestableRatingController
{
    /**
     * Retourne un body JSON avec un token valide et une note entre 1 et 5.
     *
     * @return string Corps JSON simulé
     */
    protected function readRequestBody(): string
    {
        return (string) json_encode(['token' => 'valid-token-fixture', 'note' => 4]);
    }
}
