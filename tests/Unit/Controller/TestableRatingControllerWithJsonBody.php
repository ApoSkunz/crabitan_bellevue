<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Sous-classe testable de RatingController permettant d'injecter un body JSON
 * arbitraire pour simuler php://input sans accès au flux réel.
 *
 * Surcharge uniquement readRequestBody() (seam) afin que la méthode cible
 * rateViaTokenApi() originale soit bien exécutée (et donc couverte).
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
     * Remplace le flux php://input par le corps JSON injecté via setBody().
     *
     * @return string Corps JSON simulé
     */
    protected function readRequestBody(): string
    {
        return $this->injectedBody;
    }
}
