<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\ReviewModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Couvre ReviewModel::update() (lines 84-91) non couverte dans ReviewModelTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ReviewModelCoverageTest extends TestCase
{
    /**
     * Injecte un mock Database dans ReviewModel via réflexion.
     *
     * @param Database $db Instance mock ou stub
     * @return ReviewModel
     */
    private function makeModel(Database $db): ReviewModel
    {
        $model = new ReviewModel();
        $ref   = new \ReflectionProperty(ReviewModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);
        return $model;
    }

    // ----------------------------------------------------------------
    // update() — lines 84-91
    // ----------------------------------------------------------------

    /**
     * update() exécute UPDATE avec les bons paramètres (lines 84-91).
     */
    public function testUpdateCallsExecuteWithCorrectParams(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE customer_reviews'),
                $this->callback(
                    fn($p) => $p[0] === 4
                        && $p[1] === 'Excellent vin !'
                        && $p[2] === 'Lyon'
                        && $p[3] === 7
                )
            );

        $this->makeModel($db)->update(7, 4, 'Excellent vin !', 'Lyon');
    }

    /**
     * update() avec note 5 et commentaire vide (branch minRating extrême).
     */
    public function testUpdateWithRating5AndEmptyComment(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('SET rating = ?'),
                $this->callback(fn($p) => $p[0] === 5 && $p[1] === '' && $p[2] === '' && $p[3] === 1)
            );

        $this->makeModel($db)->update(1, 5, '', '');
    }

    /**
     * update() ne retourne rien (void) — vérifie que l'appel passe sans exception.
     */
    public function testUpdateReturnsVoid(): void
    {
        $db = $this->createStub(Database::class);
        // Ne lève pas d'exception = succès
        $this->makeModel($db)->update(3, 3, 'Moyen', 'Bordeaux');
        $this->addToAssertionCount(1);
    }
}
