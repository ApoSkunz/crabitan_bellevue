<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repository\MysqlOrderRepository;

/**
 * Tests unitaires pour MysqlOrderRepository.
 * OrderModel est mocké — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class MysqlOrderRepositoryTest extends TestCase
{
    private OrderModel $modelMock;
    private MysqlOrderRepository $repo;

    protected function setUp(): void
    {
        $this->modelMock = $this->createMock(OrderModel::class);
        $this->repo      = new MysqlOrderRepository($this->modelMock);
    }

    // ----------------------------------------------------------------
    // getForUser()
    // ----------------------------------------------------------------

    /**
     * getForUser() délègue à OrderModel::getForUser() et retourne le résultat.
     */
    public function testGetForUserDelegatesToModel(): void
    {
        $expected = [['id' => 1, 'order_reference' => 'WEB-CB-ABC-2025']];

        $this->modelMock
            ->expects($this->once())
            ->method('getForUser')
            ->with(42, 1, 10, null, null, null)
            ->willReturn($expected);

        $result = $this->repo->getForUser(42, 1, 10);

        $this->assertSame($expected, $result);
    }

    /**
     * getForUser() transmet les filtres période, année et statut au modèle.
     */
    public function testGetForUserPassesFilters(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getForUser')
            ->with(5, 2, 25, 'month', 2025, 'paid')
            ->willReturn([]);

        $result = $this->repo->getForUser(5, 2, 25, 'month', 2025, 'paid');

        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // countForUser()
    // ----------------------------------------------------------------

    /**
     * countForUser() délègue au modèle et retourne le compte exact.
     */
    public function testCountForUserDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('countForUser')
            ->with(7, null, null, null)
            ->willReturn(3);

        $this->assertSame(3, $this->repo->countForUser(7));
    }

    // ----------------------------------------------------------------
    // findDetailForUser()
    // ----------------------------------------------------------------

    /**
     * findDetailForUser() retourne les données de commande du modèle.
     */
    public function testFindDetailForUserReturnOrderData(): void
    {
        $expected = ['id' => 10, 'status' => 'paid'];

        $this->modelMock
            ->expects($this->once())
            ->method('findDetailForUser')
            ->with(10, 42)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findDetailForUser(10, 42));
    }

    /**
     * findDetailForUser() retourne null si la commande n'existe pas ou n'appartient pas à l'utilisateur.
     */
    public function testFindDetailForUserReturnsNullWhenNotFound(): void
    {
        $this->modelMock
            ->method('findDetailForUser')
            ->willReturn(null);

        $this->assertNull($this->repo->findDetailForUser(999, 1));
    }

    // ----------------------------------------------------------------
    // findByReference()
    // ----------------------------------------------------------------

    /**
     * findByReference() délègue au modèle avec référence et userId.
     */
    public function testFindByReferenceDelegatesToModel(): void
    {
        $expected = ['id' => 5, 'order_reference' => 'WEB-CB-XYZ-2025'];

        $this->modelMock
            ->expects($this->once())
            ->method('findByReference')
            ->with('WEB-CB-XYZ-2025', 42)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findByReference('WEB-CB-XYZ-2025', 42));
    }

    /**
     * findByReference() retourne null si la référence n'existe pas pour cet utilisateur.
     */
    public function testFindByReferenceReturnsNullWhenNotFound(): void
    {
        $this->modelMock
            ->method('findByReference')
            ->willReturn(null);

        $this->assertNull($this->repo->findByReference('WEB-CB-UNKNOWN-2025', 1));
    }

    // ----------------------------------------------------------------
    // findByReferenceOnly()
    // ----------------------------------------------------------------

    /**
     * findByReferenceOnly() délègue au modèle sans vérification userId.
     */
    public function testFindByReferenceOnlyDelegatesToModel(): void
    {
        $expected = ['id' => 3, 'order_reference' => 'WEB-CB-TEST-2025'];

        $this->modelMock
            ->expects($this->once())
            ->method('findByReferenceOnly')
            ->with('WEB-CB-TEST-2025')
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findByReferenceOnly('WEB-CB-TEST-2025'));
    }

    // ----------------------------------------------------------------
    // findByIdForAdmin()
    // ----------------------------------------------------------------

    /**
     * findByIdForAdmin() retourne les données complètes de la commande.
     */
    public function testFindByIdForAdminDelegatesToModel(): void
    {
        $expected = ['id' => 8, 'status' => 'shipped', 'email' => 'test@example.com'];

        $this->modelMock
            ->expects($this->once())
            ->method('findByIdForAdmin')
            ->with(8)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findByIdForAdmin(8));
    }

    /**
     * findByIdForAdmin() retourne null si la commande est introuvable.
     */
    public function testFindByIdForAdminReturnsNullWhenNotFound(): void
    {
        $this->modelMock
            ->method('findByIdForAdmin')
            ->willReturn(null);

        $this->assertNull($this->repo->findByIdForAdmin(9999));
    }

    // ----------------------------------------------------------------
    // cancelForUser()
    // ----------------------------------------------------------------

    /**
     * cancelForUser() retourne true si l'annulation est acceptée par le modèle.
     */
    public function testCancelForUserReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('cancelForUser')
            ->with(10, 42)
            ->willReturn(true);

        $this->assertTrue($this->repo->cancelForUser(10, 42));
    }

    /**
     * cancelForUser() retourne false si la commande n'est pas annulable.
     */
    public function testCancelForUserReturnsFalseWhenNotCancellable(): void
    {
        $this->modelMock
            ->method('cancelForUser')
            ->willReturn(false);

        $this->assertFalse($this->repo->cancelForUser(10, 42));
    }

    // ----------------------------------------------------------------
    // requestReturnForUser()
    // ----------------------------------------------------------------

    /**
     * requestReturnForUser() retourne true si la demande de rétractation est acceptée.
     */
    public function testRequestReturnForUserReturnsTrueOnSuccess(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('requestReturnForUser')
            ->with(10, 42)
            ->willReturn(true);

        $this->assertTrue($this->repo->requestReturnForUser(10, 42));
    }

    /**
     * requestReturnForUser() retourne false si hors délai ou statut invalide.
     */
    public function testRequestReturnForUserReturnsFalseWhenNotEligible(): void
    {
        $this->modelMock
            ->method('requestReturnForUser')
            ->willReturn(false);

        $this->assertFalse($this->repo->requestReturnForUser(10, 42));
    }

    // ----------------------------------------------------------------
    // countActiveOrdersForUser()
    // ----------------------------------------------------------------

    /**
     * countActiveOrdersForUser() délègue au modèle et retourne le compteur.
     */
    public function testCountActiveOrdersForUserDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('countActiveOrdersForUser')
            ->with(42)
            ->willReturn(2);

        $this->assertSame(2, $this->repo->countActiveOrdersForUser(42));
    }

    // ----------------------------------------------------------------
    // hasActiveOrdersForUser()
    // ----------------------------------------------------------------

    /**
     * hasActiveOrdersForUser() retourne true si le modèle le confirme.
     */
    public function testHasActiveOrdersForUserReturnsTrueWhenActive(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('hasActiveOrdersForUser')
            ->with(42)
            ->willReturn(true);

        $this->assertTrue($this->repo->hasActiveOrdersForUser(42));
    }

    /**
     * hasActiveOrdersForUser() retourne false si aucune commande active.
     */
    public function testHasActiveOrdersForUserReturnsFalseWhenNone(): void
    {
        $this->modelMock
            ->method('hasActiveOrdersForUser')
            ->willReturn(false);

        $this->assertFalse($this->repo->hasActiveOrdersForUser(42));
    }

    // ----------------------------------------------------------------
    // getInvoiceFiles()
    // ----------------------------------------------------------------

    /**
     * getInvoiceFiles() retourne la liste des fichiers de factures du modèle.
     */
    public function testGetInvoiceFilesDelegatesToModel(): void
    {
        $expected = [['id' => 1, 'path' => 'storage/pdf/inv.pdf', 'source' => 'auto']];

        $this->modelMock
            ->expects($this->once())
            ->method('getInvoiceFiles')
            ->with(5)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->getInvoiceFiles(5));
    }

    // ----------------------------------------------------------------
    // updateStatus()
    // ----------------------------------------------------------------

    /**
     * updateStatus() délègue au modèle sans retour.
     */
    public function testUpdateStatusDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('updateStatus')
            ->with(10, 'shipped');

        $this->repo->updateStatus(10, 'shipped');
    }

    // ----------------------------------------------------------------
    // findByReferenceForInvoice()
    // ----------------------------------------------------------------

    /**
     * findByReferenceForInvoice() délègue au modèle et retourne les données de facturation.
     */
    public function testFindByReferenceForInvoiceDelegatesToModel(): void
    {
        $expected = ['id' => 6, 'order_reference' => 'WEB-CB-INV-2025', 'bill_firstname' => 'Jean'];

        $this->modelMock
            ->expects($this->once())
            ->method('findByReferenceForInvoice')
            ->with('WEB-CB-INV-2025')
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findByReferenceForInvoice('WEB-CB-INV-2025'));
    }

    // ----------------------------------------------------------------
    // addInvoiceFile()
    // ----------------------------------------------------------------

    /**
     * addInvoiceFile() délègue au modèle avec tous les paramètres.
     */
    public function testAddInvoiceFileDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('addInvoiceFile')
            ->with(7, 'storage/pdf/inv_007.pdf', 'WEB-INVOICE-2025-0007', 'auto');

        $this->repo->addInvoiceFile(7, 'storage/pdf/inv_007.pdf', 'WEB-INVOICE-2025-0007', 'auto');
    }

    // ----------------------------------------------------------------
    // updateInvoiceData()
    // ----------------------------------------------------------------

    /**
     * updateInvoiceData() délègue au modèle avec id, numéro et chemin.
     */
    public function testUpdateInvoiceDataDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('updateInvoiceData')
            ->with(7, 'WEB-INVOICE-2025-0007', 'storage/pdf/inv_007.pdf');

        $this->repo->updateInvoiceData(7, 'WEB-INVOICE-2025-0007', 'storage/pdf/inv_007.pdf');
    }

    // ----------------------------------------------------------------
    // findByIdForUser()
    // ----------------------------------------------------------------

    /**
     * findByIdForUser() retourne les données de commande accessibles à l'utilisateur.
     */
    public function testFindByIdForUserDelegatesToModel(): void
    {
        $expected = ['id' => 11, 'order_reference' => 'WEB-CB-USR-2025', 'path_invoice' => null];

        $this->modelMock
            ->expects($this->once())
            ->method('findByIdForUser')
            ->with(11, 42)
            ->willReturn($expected);

        $this->assertSame($expected, $this->repo->findByIdForUser(11, 42));
    }

    // ----------------------------------------------------------------
    // getAddressIdsWithActiveOrders()
    // ----------------------------------------------------------------

    /**
     * getAddressIdsWithActiveOrders() délègue au modèle et retourne un tableau d'IDs.
     */
    public function testGetAddressIdsWithActiveOrdersDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getAddressIdsWithActiveOrders')
            ->with([1, 2, 3])
            ->willReturn([]);

        $this->assertSame([], $this->repo->getAddressIdsWithActiveOrders([1, 2, 3]));
    }

    // ----------------------------------------------------------------
    // hasActiveOrderForAddress()
    // ----------------------------------------------------------------

    /**
     * hasActiveOrderForAddress() délègue au modèle et retourne false (stub snapshotté).
     */
    public function testHasActiveOrderForAddressDelegatesToModel(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('hasActiveOrderForAddress')
            ->with(99)
            ->willReturn(false);

        $this->assertFalse($this->repo->hasActiveOrderForAddress(99));
    }
}
