<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repository\MysqlOrderRepository;

/**
 * Couvre les méthodes non atteintes dans MysqlOrderRepositoryTest :
 *   - lines 218-220 : getAvailableYearsForUser()
 *   - lines 240-242 : getInvoiceFilesForUser()
 */
#[AllowMockObjectsWithoutExpectations]
class MysqlOrderRepositoryCoverageTest extends TestCase
{
    private OrderModel $modelMock;
    private MysqlOrderRepository $repo;

    protected function setUp(): void
    {
        $this->modelMock = $this->createMock(OrderModel::class);
        $this->repo      = new MysqlOrderRepository($this->modelMock);
    }

    // ----------------------------------------------------------------
    // lines 218-220 — getAvailableYearsForUser()
    // ----------------------------------------------------------------

    /**
     * getAvailableYearsForUser() délègue à OrderModel et retourne le tableau d'années.
     */
    public function testGetAvailableYearsForUserDelegatesToModel(): void
    {
        $expected = [2023, 2024, 2025];

        $this->modelMock
            ->expects($this->once())
            ->method('getAvailableYearsForUser')
            ->with(42)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->getAvailableYearsForUser(42));
    }

    /**
     * getAvailableYearsForUser() retourne un tableau vide si aucune commande.
     */
    public function testGetAvailableYearsForUserReturnsEmptyWhenNoOrders(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getAvailableYearsForUser')
            ->with(99)
            ->willReturn([]);

        $this->assertSame([], $this->repo->getAvailableYearsForUser(99));
    }

    // ----------------------------------------------------------------
    // lines 240-242 — getInvoiceFilesForUser()
    // ----------------------------------------------------------------

    /**
     * getInvoiceFilesForUser() délègue à OrderModel et retourne les fichiers de factures.
     */
    public function testGetInvoiceFilesForUserDelegatesToModel(): void
    {
        $expected = [
            ['id' => 1, 'path' => 'storage/pdf/inv_001.pdf', 'source' => 'auto'],
            ['id' => 2, 'path' => 'storage/pdf/inv_002.pdf', 'source' => 'upload'],
        ];

        $this->modelMock
            ->expects($this->once())
            ->method('getInvoiceFilesForUser')
            ->with(7)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->getInvoiceFilesForUser(7));
    }

    /**
     * getInvoiceFilesForUser() retourne un tableau vide si pas de factures.
     */
    public function testGetInvoiceFilesForUserReturnsEmptyWhenNone(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getInvoiceFilesForUser')
            ->with(1)
            ->willReturn([]);

        $this->assertSame([], $this->repo->getInvoiceFilesForUser(1));
    }
}
