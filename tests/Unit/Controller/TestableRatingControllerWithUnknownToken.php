<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Variante de TestableRatingController simulant un body JSON avec token inconnu.
 * Utilisé pour tester rateViaTokenApi() sans accès à php://input.
 *
 * Surcharge uniquement readRequestBody() (seam) — la méthode cible originale
 * est bien exécutée et couverte.
 */
class TestableRatingControllerWithUnknownToken extends TestableRatingController
{
    /**
     * Retourne un body JSON avec un token inconnu et une note valide.
     *
     * @return string Corps JSON simulé
     */
    protected function readRequestBody(): string
    {
        return (string) json_encode(['token' => 'inexistant-token', 'note' => 3]);
    }
}
