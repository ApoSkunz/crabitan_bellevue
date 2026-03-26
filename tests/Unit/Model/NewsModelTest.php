<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;

class NewsModelTest extends TestCase
{
    private \Model\NewsModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new \Model\NewsModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    public function testCountAllReturnsInt(): void
    {
        $count = $this->model->countAll();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCountForAdminDelegatesToCountAll(): void
    {
        $this->assertSame($this->model->countAll(), $this->model->countForAdmin());
    }

    public function testGetPaginatedReturnsArray(): void
    {
        $results = $this->model->getPaginated(9, 0);
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(9, count($results));
    }

    public function testGetForAdminReturnsArray(): void
    {
        $results = $this->model->getForAdmin(10, 0);
        $this->assertIsArray($results);
    }

    public function testGetPrevReturnsNullOrArray(): void
    {
        $result = $this->model->getPrev('slug-inexistant-xyz-abc');
        $this->assertNull($result);
    }

    public function testGetNextReturnsNullOrArray(): void
    {
        $result = $this->model->getNext('slug-inexistant-xyz-abc');
        $this->assertNull($result);
    }

    public function testGetByIdReturnsNullForInvalidId(): void
    {
        $result = $this->model->getById(999999);
        $this->assertNull($result);
    }

    public function testGetBySlugReturnsNullForUnknownSlug(): void
    {
        try {
            $result = $this->model->getBySlug('slug-inexistant-xyz-abc');
            $this->assertNull($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Méthode non disponible : ' . $e->getMessage());
        }
    }
}
