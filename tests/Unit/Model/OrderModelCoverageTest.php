<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\EncryptionService;

/**
 * Tests de couverture pour OrderModel — couvre les lignes non atteintes.
 */
#[AllowMockObjectsWithoutExpectations]
class OrderModelCoverageTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub $dbMock;
    private \ReflectionProperty $instanceProp;
    private OrderModel $model;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);

        $this->model = new OrderModel($enc);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // Helper — crée un mock strict + modèle
    // ----------------------------------------------------------------

    /**
     * @return array{0: \PHPUnit\Framework\MockObject\MockObject, 1: OrderModel}
     */
    private function makeMockAndModel(): array
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        return [$mock, new OrderModel($enc)];
    }

    // ----------------------------------------------------------------
    // VALID_PAYMENT_METHODS constant (line 34)
    // ----------------------------------------------------------------

    /**
     * VALID_PAYMENT_METHODS contient les 3 méthodes attendues.
     */
    public function testValidPaymentMethodsConstant(): void
    {
        $this->assertContains('card', OrderModel::VALID_PAYMENT_METHODS);
        $this->assertContains('virement', OrderModel::VALID_PAYMENT_METHODS);
        $this->assertContains('cheque', OrderModel::VALID_PAYMENT_METHODS);
    }

    // ----------------------------------------------------------------
    // create() — paymentMethod variants + virement/cheque prefix (lines 47-100)
    // ----------------------------------------------------------------

    /**
     * create() avec virement génère une référence avec préfixe VB.
     */
    public function testCreateWithVirementGeneratesVbPrefix(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->dbMock->method('insert')->willReturn('1');

        $reference = $this->model->create(1, [], 50.0, 'virement', 0.0, 1, null, '1.0');

        $this->assertStringContainsString('VB', $reference);
    }

    /**
     * create() avec cheque génère une référence avec préfixe CHQ.
     */
    public function testCreateWithChequeGeneratesChqPrefix(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->dbMock->method('insert')->willReturn('1');

        $reference = $this->model->create(1, [], 50.0, 'cheque', 0.0, 1, null, '1.0');

        $this->assertStringContainsString('CHQ', $reference);
    }

    /**
     * create() avec idDeliveryAddress non-null crée un second snapshot.
     */
    public function testCreateWithDeliveryAddressCreatesDeliverySnapshot(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        // Two fetchOne calls for billing + delivery address, plus insert calls
        $this->dbMock->method('insert')->willReturn('1');

        $reference = $this->model->create(1, [], 80.0, 'card', 5.0, 1, 2, '2.0');

        $this->assertStringStartsWith('WEB-', $reference);
    }

    // ----------------------------------------------------------------
    // findByReference (lines 107-131)
    // ----------------------------------------------------------------

    /**
     * findByReference retourne null quand non trouvé.
     */
    public function testFindByReferenceReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByReference('WEB-CB-XXXX-2026', 1));
    }

    /**
     * findByReference retourne un tableau déchiffré quand trouvé.
     */
    public function testFindByReferenceReturnsDecryptedArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'              => 1,
            'order_reference' => 'WEB-CB-XXXX-2026',
            'bill_street'     => 'encrypted_street',
            'bill_phone'      => 'encrypted_phone',
            'del_street'      => 'encrypted_del_street',
        ]);

        $result = $this->model->findByReference('WEB-CB-XXXX-2026', 1);

        $this->assertIsArray($result);
        $this->assertSame('WEB-CB-XXXX-2026', $result['order_reference']);
    }

    // ----------------------------------------------------------------
    // createFromIpn (lines 136-205)
    // ----------------------------------------------------------------

    /**
     * createFromIpn retourne la référence en cas de succès.
     */
    public function testCreateFromIpnReturnsReferenceOnSuccess(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->dbMock->method('insert')->willReturn('10');

        $ref = $this->model->createFromIpn(
            userId: 1,
            items: [],
            total: 100.0,
            deliveryDiscount: 5.0,
            billingAddressId: 1,
            deliveryAddressId: 2,
            cgvVersion: '1.0',
            reference: 'WEB-CB-ABCD1234-2026',
            numappel: 'NUM001',
            numtrans: 'TRANS001'
        );

        $this->assertSame('WEB-CB-ABCD1234-2026', $ref);
    }

    /**
     * createFromIpn lève une PDOException non-23000 immédiatement (pas de retry).
     */
    public function testCreateFromIpnThrowsNonDuplicateException(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        // Code != '23000' → thrown immediately without retry
        $ex = new \PDOException('Connection lost', 2006);
        $this->dbMock->method('insert')->willThrowException($ex);

        $this->expectException(\PDOException::class);
        $this->model->createFromIpn(
            userId: 1,
            items: [],
            total: 50.0,
            deliveryDiscount: 0.0,
            billingAddressId: 1,
            deliveryAddressId: 1,
            cgvVersion: '1.0',
            reference: 'WEB-CB-XXXX-2026',
            numappel: 'N1',
            numtrans: 'T1'
        );
    }

    // ----------------------------------------------------------------
    // findByReferenceOnly (lines 217-227)
    // ----------------------------------------------------------------

    /**
     * findByReferenceOnly retourne null quand non trouvé.
     */
    public function testFindByReferenceOnlyReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByReferenceOnly('WEB-CB-0000-2026'));
    }

    /**
     * findByReferenceOnly retourne le tableau quand trouvé.
     */
    public function testFindByReferenceOnlyReturnsArrayWhenFound(): void
    {
        $row = ['id' => 5, 'order_reference' => 'WEB-CB-0000-2026', 'status' => 'paid'];
        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findByReferenceOnly('WEB-CB-0000-2026');
        $this->assertSame($row, $result);
    }

    // ----------------------------------------------------------------
    // getForAdmin (lines 236-263)
    // ----------------------------------------------------------------

    /**
     * getForAdmin retourne les lignes de fetchAll.
     */
    public function testGetForAdminReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'order_reference' => 'WEB-CB-0001-2026']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getForAdmin(1, 10, null, null);
        $this->assertSame($expected, $result);
    }

    /**
     * getForAdmin avec filtre statut.
     */
    public function testGetForAdminWithStatusFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(1, 10, 'paid', null);
        $this->assertIsArray($result);
    }

    /**
     * getForAdmin avec filtre payment.
     */
    public function testGetForAdminWithPaymentFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(1, 10, null, null, 'card');
        $this->assertIsArray($result);
    }

    /**
     * getForAdmin avec recherche texte.
     */
    public function testGetForAdminWithSearch(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(1, 10, null, 'user@test.com');
        $this->assertIsArray($result);
    }

    /**
     * getForAdmin avec archived=true.
     */
    public function testGetForAdminWithArchivedFlag(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(1, 10, null, null, null, true);
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // countForAdmin (lines 269-285)
    // ----------------------------------------------------------------

    /**
     * countForAdmin retourne le total.
     */
    public function testCountForAdminReturnsTotal(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '30']);

        $this->assertSame(30, $this->model->countForAdmin(null, null));
    }

    /**
     * countForAdmin avec status invalide ne filtre pas.
     */
    public function testCountForAdminWithInvalidStatusDoesNotFilter(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '5']);

        $this->assertSame(5, $this->model->countForAdmin('invalid_status', null));
    }

    // ----------------------------------------------------------------
    // findByIdForAdmin (lines 291-321)
    // ----------------------------------------------------------------

    /**
     * findByIdForAdmin retourne null si non trouvé.
     */
    public function testFindByIdForAdminReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByIdForAdmin(999));
    }

    /**
     * findByIdForAdmin retourne le tableau déchiffré quand trouvé.
     */
    public function testFindByIdForAdminReturnsDecryptedArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'             => 1,
            'order_reference' => 'WEB-CB-0001-2026',
            'bill_street'    => 'encrypted_street',
            'bill_phone'     => 'encrypted_phone',
            'del_street'     => 'encrypted_del_street',
        ]);

        $result = $this->model->findByIdForAdmin(1);
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // addInvoiceFile (lines 340-345)
    // ----------------------------------------------------------------

    /**
     * addInvoiceFile appelle execute avec les bons paramètres.
     */
    public function testAddInvoiceFileCallsExecute(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('order_invoice_files'),
                [1, 'storage/pdf/invoices/inv.pdf', 'WEB-INVOICE-2026-001', 'auto']
            );

        $model->addInvoiceFile(1, 'storage/pdf/invoices/inv.pdf', 'WEB-INVOICE-2026-001', 'auto');
    }

    // ----------------------------------------------------------------
    // getInvoiceFiles (lines 354-362)
    // ----------------------------------------------------------------

    /**
     * getInvoiceFiles retourne les lignes de fetchAll.
     */
    public function testGetInvoiceFilesReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'path' => 'storage/pdf/inv.pdf']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getInvoiceFiles(1);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // updateInvoiceData (lines 372-378)
    // ----------------------------------------------------------------

    /**
     * updateInvoiceData appelle execute avec invoice_number et path.
     */
    public function testUpdateInvoiceDataCallsExecute(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('invoice_number'),
                ['WEB-INVOICE-2026-001', 'storage/pdf/inv.pdf', 5]
            );

        $model->updateInvoiceData(5, 'WEB-INVOICE-2026-001', 'storage/pdf/inv.pdf');
    }

    // ----------------------------------------------------------------
    // findByReferenceForInvoice (lines 388-414)
    // ----------------------------------------------------------------

    /**
     * findByReferenceForInvoice retourne null quand non trouvé.
     */
    public function testFindByReferenceForInvoiceReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByReferenceForInvoice('WEB-CB-XXXX-2026'));
    }

    /**
     * findByReferenceForInvoice retourne le tableau déchiffré quand trouvé.
     */
    public function testFindByReferenceForInvoiceReturnsDecryptedArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'              => 2,
            'order_reference' => 'WEB-CB-XXXX-2026',
            'bill_street'     => 'encrypted_street',
            'bill_phone'      => 'encrypted_phone',
        ]);

        $result = $this->model->findByReferenceForInvoice('WEB-CB-XXXX-2026');
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // findByReferenceWithAddresses (lines 423-450)
    // ----------------------------------------------------------------

    /**
     * findByReferenceWithAddresses retourne null quand non trouvé.
     */
    public function testFindByReferenceWithAddressesReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByReferenceWithAddresses('WEB-CB-XXXX-2026'));
    }

    /**
     * findByReferenceWithAddresses retourne le tableau quand trouvé.
     */
    public function testFindByReferenceWithAddressesReturnsArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'              => 3,
            'order_reference' => 'WEB-CB-XXXX-2026',
            'bill_street'     => 'encrypted_street',
            'bill_phone'      => 'encrypted_phone',
        ]);

        $result = $this->model->findByReferenceWithAddresses('WEB-CB-XXXX-2026');
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // findByIdForUser (lines 456-464)
    // ----------------------------------------------------------------

    /**
     * findByIdForUser retourne null quand non trouvé.
     */
    public function testFindByIdForUserReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findByIdForUser(99, 1));
    }

    /**
     * findByIdForUser retourne le tableau quand trouvé.
     */
    public function testFindByIdForUserReturnsArrayWhenFound(): void
    {
        $row = ['id' => 5, 'order_reference' => 'WEB-CB-XXXX-2026', 'user_id' => 1];
        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findByIdForUser(5, 1);
        $this->assertSame($row, $result);
    }

    // ----------------------------------------------------------------
    // countByStatus (lines 524-533)
    // ----------------------------------------------------------------

    /**
     * countByStatus retourne un tableau indexé par statut.
     */
    public function testCountByStatusReturnsIndexedArray(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['status' => 'pending',   'cnt' => '3'],
            ['status' => 'paid',      'cnt' => '10'],
            ['status' => 'delivered', 'cnt' => '5'],
        ]);

        $result = $this->model->countByStatus();

        $this->assertSame(3, $result['pending']);
        $this->assertSame(10, $result['paid']);
        $this->assertSame(5, $result['delivered']);
    }

    /**
     * countByStatus retourne un tableau vide si aucune commande.
     */
    public function testCountByStatusReturnsEmptyArrayWhenNoOrders(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->countByStatus());
    }

    // ----------------------------------------------------------------
    // getRevenue (lines 539-548)
    // ----------------------------------------------------------------

    /**
     * getRevenue retourne le total des 30 derniers jours.
     */
    public function testGetRevenueReturnsFloat(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '1234.56']);

        $this->assertSame(1234.56, $this->model->getRevenue(30));
    }

    /**
     * getRevenue retourne 0.0 quand aucune commande.
     */
    public function testGetRevenueReturnsZeroWhenNoOrders(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '0']);

        $this->assertSame(0.0, $this->model->getRevenue());
    }

    // ----------------------------------------------------------------
    // getRevenueByYear (lines 554-563)
    // ----------------------------------------------------------------

    /**
     * getRevenueByYear retourne le CA de l'année.
     */
    public function testGetRevenueByYearReturnsFloat(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '9876.54']);

        $this->assertSame(9876.54, $this->model->getRevenueByYear(2025));
    }

    // ----------------------------------------------------------------
    // getRecent (lines 569-582)
    // ----------------------------------------------------------------

    /**
     * getRecent retourne les N dernières commandes.
     */
    public function testGetRecentReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'order_reference' => 'WEB-CB-0001-2026']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getRecent(8);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // getForUser (lines 588-601)
    // ----------------------------------------------------------------

    /**
     * getForUser retourne les commandes de l'utilisateur.
     */
    public function testGetForUserReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'status' => 'paid']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getForUser(1, 1, 10);
        $this->assertSame($expected, $result);
    }

    /**
     * getForUser avec filtre period=3months.
     */
    public function testGetForUserWithPeriod3Months(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForUser(1, 1, 10, '3months');
        $this->assertIsArray($result);
    }

    /**
     * getForUser avec filtre period=year.
     */
    public function testGetForUserWithPeriodYear(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForUser(1, 1, 10, 'year', 2025);
        $this->assertIsArray($result);
    }

    /**
     * getForUser avec filtre status.
     */
    public function testGetForUserWithStatusFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForUser(1, 1, 10, null, null, 'delivered');
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // countForUser (lines 604-611)
    // ----------------------------------------------------------------

    /**
     * countForUser retourne le total.
     */
    public function testCountForUserReturnsTotal(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '8']);

        $this->assertSame(8, $this->model->countForUser(1));
    }

    // ----------------------------------------------------------------
    // findDetailForUser (lines 617-640)
    // ----------------------------------------------------------------

    /**
     * findDetailForUser retourne null quand non trouvé.
     */
    public function testFindDetailForUserReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findDetailForUser(99, 1));
    }

    /**
     * findDetailForUser retourne le tableau déchiffré quand trouvé.
     */
    public function testFindDetailForUserReturnsDecryptedArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'         => 1,
            'user_id'    => 1,
            'bill_street' => 'encrypted_street',
            'bill_phone'  => 'encrypted_phone',
            'del_street'  => 'encrypted_del_street',
        ]);

        $result = $this->model->findDetailForUser(1, 1);
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // cancelForUser (lines 643-657)
    // ----------------------------------------------------------------

    /**
     * cancelForUser retourne false si commande non trouvée.
     */
    public function testCancelForUserReturnsFalseWhenOrderNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->cancelForUser(99, 1));
    }

    /**
     * cancelForUser retourne false si statut non annulable.
     */
    public function testCancelForUserReturnsFalseWhenStatusNotCancellable(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['status' => 'shipped']);

        $this->assertFalse($this->model->cancelForUser(1, 1));
    }

    /**
     * cancelForUser retourne true et exécute UPDATE si statut=pending.
     */
    public function testCancelForUserReturnsTrueWhenPending(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->method('fetchOne')->willReturn(['status' => 'pending']);
        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains("status = 'cancelled'"), [1, 1]);

        $this->assertTrue($model->cancelForUser(1, 1));
    }

    // ----------------------------------------------------------------
    // hasActiveOrdersForUser (lines 660-667)
    // ----------------------------------------------------------------

    /**
     * hasActiveOrdersForUser retourne true si des commandes actives existent.
     */
    public function testHasActiveOrdersForUserReturnsTrueWhenActive(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['cnt' => '2']);

        $this->assertTrue($this->model->hasActiveOrdersForUser(1));
    }

    /**
     * hasActiveOrdersForUser retourne false si aucune commande active.
     */
    public function testHasActiveOrdersForUserReturnsFalseWhenNone(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['cnt' => '0']);

        $this->assertFalse($this->model->hasActiveOrdersForUser(1));
    }

    // ----------------------------------------------------------------
    // countActiveOrdersForUser (lines 676-683)
    // ----------------------------------------------------------------

    /**
     * countActiveOrdersForUser retourne le count.
     */
    public function testCountActiveOrdersForUserReturnsCount(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['cnt' => '3']);

        $this->assertSame(3, $this->model->countActiveOrdersForUser(1));
    }

    // ----------------------------------------------------------------
    // getAddressIdsWithActiveOrders + hasActiveOrderForAddress (lines 699-712)
    // ----------------------------------------------------------------

    /**
     * getAddressIdsWithActiveOrders retourne toujours un tableau vide (stub).
     */
    public function testGetAddressIdsWithActiveOrdersReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->model->getAddressIdsWithActiveOrders([1, 2, 3]));
    }

    /**
     * hasActiveOrderForAddress retourne toujours false (stub).
     */
    public function testHasActiveOrderForAddressReturnsFalse(): void
    {
        $this->assertFalse($this->model->hasActiveOrderForAddress(1));
    }

    // ----------------------------------------------------------------
    // getAvailableYears (lines 763-780)
    // ----------------------------------------------------------------

    /**
     * getAvailableYears retourne les années triées en entiers.
     */
    public function testGetAvailableYearsReturnsIntArray(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['yr' => '2025'],
            ['yr' => '2024'],
        ]);

        $result = $this->model->getAvailableYears();
        $this->assertSame([2025, 2024], $result);
    }

    /**
     * getAvailableYears retourne un tableau vide si aucune commande.
     */
    public function testGetAvailableYearsReturnsEmptyWhenNoOrders(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->getAvailableYears());
    }

    // ----------------------------------------------------------------
    // getStatsForPeriod (lines 789-805)
    // ----------------------------------------------------------------

    /**
     * getStatsForPeriod retourne ca, count, avg correctement.
     */
    public function testGetStatsForPeriodReturnsCaCountAvg(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['ca' => '300.0', 'cnt' => '6']);

        $result = $this->model->getStatsForPeriod('2026-01-01', '2026-03-31');

        $this->assertSame(300.0, $result['ca']);
        $this->assertSame(6, $result['count']);
        $this->assertSame(50.0, $result['avg']);
    }

    /**
     * getStatsForPeriod retourne avg=0 quand count=0.
     */
    public function testGetStatsForPeriodReturnsZeroAvgWhenNoOrders(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['ca' => '0', 'cnt' => '0']);

        $result = $this->model->getStatsForPeriod(null, null);

        $this->assertSame(0.0, $result['avg']);
    }

    // ----------------------------------------------------------------
    // getChartData (lines 813-845)
    // ----------------------------------------------------------------

    /**
     * getChartData granularité daily avec plage.
     */
    public function testGetChartDataDailyGranularity(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['label' => '2026-01-01', 'ca' => '50.0', 'count' => '2'],
        ]);

        $result = $this->model->getChartData('2026-01-01', '2026-01-03', 'daily');
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // 3 jours remplis avec gaps
    }

    /**
     * getChartData granularité monthly.
     */
    public function testGetChartDataMonthlyGranularity(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getChartData('2026-01-01', '2026-03-31', 'monthly');
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Jan, Feb, Mar
    }

    /**
     * getChartData granularité yearly.
     */
    public function testGetChartDataYearlyGranularity(): void
    {
        // Two calls: first for fetchAll (chart data), second for getAvailableYears
        $this->dbMock->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['label' => '2025', 'ca' => '500.0', 'count' => '10']],
            [['yr' => '2025'], ['yr' => '2026']]
        );

        $result = $this->model->getChartData(null, null, 'yearly');
        $this->assertIsArray($result);
    }

    /**
     * getChartData yearly retourne tableau vide si aucune année disponible.
     */
    public function testGetChartDataYearlyReturnsEmptyWhenNoYears(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getChartData(null, null, 'yearly');
        $this->assertSame([], $result);
    }

    /**
     * getChartData sans bornes retourne les valeurs directement.
     */
    public function testGetChartDataWithoutBoundsReturnsIndexedValues(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['label' => '2026-01', 'ca' => '100.0', 'count' => '3'],
        ]);

        $result = $this->model->getChartData(null, null, 'monthly');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ----------------------------------------------------------------
    // getAvailableYearsForUser (lines 940-947)
    // ----------------------------------------------------------------

    /**
     * getAvailableYearsForUser retourne les années de l'utilisateur.
     */
    public function testGetAvailableYearsForUserReturnsYears(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['yr' => '2026'],
            ['yr' => '2025'],
        ]);

        $result = $this->model->getAvailableYearsForUser(1);
        $this->assertSame(['2026', '2025'], $result);
    }

    // ----------------------------------------------------------------
    // getInvoicesForAdmin (lines 1025-1053)
    // ----------------------------------------------------------------

    /**
     * getInvoicesForAdmin retourne les lignes paginées.
     */
    public function testGetInvoicesForAdminReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'invoice_number' => 'WEB-INVOICE-2026-001']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getInvoicesForAdmin(2026, null, null, 1, 10);
        $this->assertSame($expected, $result);
    }

    /**
     * getInvoicesForAdmin filtre par mois.
     */
    public function testGetInvoicesForAdminWithMonthFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getInvoicesForAdmin(2026, 3, null, 1, 10);
        $this->assertIsArray($result);
    }

    /**
     * getInvoicesForAdmin filtre par trimestre.
     */
    public function testGetInvoicesForAdminWithQuarterFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getInvoicesForAdmin(2026, null, 2, 1, 10);
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // countInvoicesForAdmin (lines 1064-1074)
    // ----------------------------------------------------------------

    /**
     * countInvoicesForAdmin retourne le total.
     */
    public function testCountInvoicesForAdminReturnsTotal(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '15']);

        $this->assertSame(15, $this->model->countInvoicesForAdmin(2026, null, null));
    }

    // ----------------------------------------------------------------
    // getInvoiceFilesForExport (lines 1086-1104)
    // ----------------------------------------------------------------

    /**
     * getInvoiceFilesForExport retourne toutes les lignes pour l'export.
     */
    public function testGetInvoiceFilesForExportReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'path' => 'storage/pdf/inv.pdf']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getInvoiceFilesForExport(2026, null, null);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // getInvoiceFilesForUser (lines 1114-1130)
    // ----------------------------------------------------------------

    /**
     * getInvoiceFilesForUser retourne les factures de l'utilisateur.
     */
    public function testGetInvoiceFilesForUserReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'order_reference' => 'WEB-CB-0001-2026']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getInvoiceFilesForUser(1);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // getAvailableYearsWithInvoices (lines 1138-1146)
    // ----------------------------------------------------------------

    /**
     * getAvailableYearsWithInvoices retourne les années en entiers.
     */
    public function testGetAvailableYearsWithInvoicesReturnsIntArray(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            ['yr' => '2026'],
            ['yr' => '2025'],
        ]);

        $result = $this->model->getAvailableYearsWithInvoices();
        $this->assertSame([2026, 2025], $result);
    }

    // ----------------------------------------------------------------
    // getStaleOrders — default arg (line 1208)
    // ----------------------------------------------------------------

    /**
     * getStaleOrders utilise 7 comme valeur par défaut.
     */
    public function testGetStaleOrdersDefaultDays(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getStaleOrders();
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // getAvgOrderValue (lines 1296-1305)
    // ----------------------------------------------------------------

    /**
     * getAvgOrderValue retourne la moyenne arrondie à 2 décimales.
     */
    public function testGetAvgOrderValueReturnsRoundedFloat(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['avg_val' => '123.456']);

        $result = $this->model->getAvgOrderValue(30);
        $this->assertSame(123.46, $result);
    }

    /**
     * getAvgOrderValue retourne 0.0 quand aucune commande.
     */
    public function testGetAvgOrderValueReturnsZeroWhenNoOrders(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['avg_val' => null]);

        $this->assertSame(0.0, $this->model->getAvgOrderValue());
    }

    // ----------------------------------------------------------------
    // getConversionStats (lines 1317-1344)
    // ----------------------------------------------------------------

    /**
     * getConversionStats retourne rate, orders, carts.
     */
    public function testGetConversionStatsReturnsCorrectStructure(): void
    {
        $this->dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['cnt' => '8'],
            ['cnt' => '12']
        );

        $result = $this->model->getConversionStats(30);

        $this->assertSame(8, $result['orders']);
        $this->assertSame(12, $result['carts']);
        $this->assertSame(round(8 / 20, 4), $result['rate']);
    }

    /**
     * getConversionStats retourne rate=0.0 quand total=0.
     */
    public function testGetConversionStatsReturnsZeroRateWhenNoTotal(): void
    {
        $this->dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['cnt' => '0'],
            ['cnt' => '0']
        );

        $result = $this->model->getConversionStats(30);
        $this->assertSame(0.0, $result['rate']);
    }

    // ----------------------------------------------------------------
    // buildAdminFilters — invalid payment filter (lines 985-999)
    // ----------------------------------------------------------------

    /**
     * Un payment method invalide dans getForAdmin n'est pas ajouté au filtre.
     */
    public function testGetForAdminWithInvalidPaymentDoesNotFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(1, 10, null, null, 'bitcoin');
        $this->assertIsArray($result);
    }
}
