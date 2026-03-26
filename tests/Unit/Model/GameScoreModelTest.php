<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;

class GameScoreModelTest extends TestCase
{
    private \Model\GameScoreModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new \Model\GameScoreModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    public function testGetBestScoreReturnsIntOrNull(): void
    {
        $score = $this->model->getBestScore('memo');
        $this->assertTrue($score === null || is_int($score));
    }

    public function testUpdateIfBetterReturnsBool(): void
    {
        $result = $this->model->updateIfBetter('memo', 1);
        $this->assertIsBool($result);
    }
}
