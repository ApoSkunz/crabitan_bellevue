<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\WineModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour WineModel.
 * Couvre les branches non atteintes par WineModelTest.
 */
#[AllowMockObjectsWithoutExpectations]
class WineModelCoverageTest extends TestCase
{
    private Database $dbMock;
    private WineModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);
        $this->model = new WineModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getAll — sort variants (price_desc, vintage_asc)  line 150
    // ----------------------------------------------------------------

    public function testGetAllSortPriceDescBuildsCorrectOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('price DESC'), $this->anything())
            ->willReturn([]);

        $this->model->getAll(null, 'price_desc');
    }

    public function testGetAllSortVintageAscBuildsCorrectOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('vintage ASC'), $this->anything())
            ->willReturn([]);

        $this->model->getAll(null, 'vintage_asc');
    }

    // ----------------------------------------------------------------
    // getAll — limit + offset non nul (lines 157-161)
    // ----------------------------------------------------------------

    public function testGetAllWithLimitAndOffsetPassesBothParams(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('LIMIT ? OFFSET ?'),
                $this->equalTo([5, 10])
            )
            ->willReturn([]);

        $this->model->getAll(null, 'default', 5, 10);
    }

    // ----------------------------------------------------------------
    // getAll — color + limit (line 176-177 countAll side, via getAll)
    // ----------------------------------------------------------------

    public function testGetAllWithColorAndLimitPassesCorrectParams(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('wine_color = ?'),
                    $this->stringContains('LIMIT ? OFFSET ?')
                ),
                $this->equalTo(['red', 3, 0])
            )
            ->willReturn([]);

        $this->model->getAll('red', 'default', 3);
    }

    // ----------------------------------------------------------------
    // countAll — color + cuveSpeciale (lines 176-177, 224)
    // ----------------------------------------------------------------

    public function testCountAllWithColorAndCuveePassesParams(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('wine_color = ?'),
                    $this->stringContains('is_cuvee_speciale = 1')
                ),
                $this->equalTo(['rosé'])
            )
            ->willReturn(['total' => 1]);

        $result = $this->model->countAll('rosé', true);

        $this->assertSame(1, $result);
    }

    // ----------------------------------------------------------------
    // getAllByColor — sort vintage_asc (line 243)
    // ----------------------------------------------------------------

    public function testGetAllByColorSortVintageAscBuildsOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('vintage ASC'), $this->anything())
            ->willReturn([]);

        $this->model->getAllByColor(null, 'vintage_asc');
    }

    // ----------------------------------------------------------------
    // getAllByColor — sort vintage_desc (line 244)
    // ----------------------------------------------------------------

    public function testGetAllByColorSortVintageDescBuildsOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('vintage DESC'), $this->anything())
            ->willReturn([]);

        $this->model->getAllByColor(null, 'vintage_desc');
    }

    // ----------------------------------------------------------------
    // getAllByColor — color filter (lines 222-226)
    // ----------------------------------------------------------------

    public function testGetAllByColorWithColorFilterPassesParam(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('wine_color = ?'),
                $this->callback(fn(array $p): bool => in_array('white', $p, true))
            )
            ->willReturn([]);

        $this->model->getAllByColor('white');
    }

    // ----------------------------------------------------------------
    // countAllByColor — cuveSpeciale (line 322, 325)
    // ----------------------------------------------------------------

    public function testCountAllByColorCuveeSpecialeFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('is_cuvee_speciale = 1'), $this->anything())
            ->willReturn(['total' => 2]);

        $result = $this->model->countAllByColor(null, null, true);

        $this->assertSame(2, $result);
    }

    // ----------------------------------------------------------------
    // countAllByColor — returns 0 on false (line 308 fallback)
    // ----------------------------------------------------------------

    public function testCountAllByColorReturnsFalseRow(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->countAllByColor();

        $this->assertSame(0, $result);
    }

    // ----------------------------------------------------------------
    // getColorFirstPages — avail=out (lines 330-331)
    // ----------------------------------------------------------------

    public function testGetColorFirstPagesWithOutFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('available = 0'), $this->equalTo([]))
            ->willReturn([['wine_color' => 'red', 'cnt' => 4]]);

        $result = $this->model->getColorFirstPages('out', 10);

        $this->assertSame(['red' => 1], $result);
    }

    // ----------------------------------------------------------------
    // getForAdmin — vintage + labelName filters (lines 611-619)
    // ----------------------------------------------------------------

    public function testGetForAdminWithVintageFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('vintage = ?'),
                $this->callback(fn(array $p): bool => in_array(2020, $p, true))
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, 2020);
    }

    public function testGetForAdminWithLabelNameFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('label_name LIKE ?'),
                $this->callback(fn(array $p): bool => in_array('%Bordeaux%', $p, true))
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, null, 'Bordeaux');
    }

    public function testGetForAdminWithCuveeFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('is_cuvee_speciale = ?'),
                $this->callback(fn(array $p): bool => in_array(1, $p, true))
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, null, null, true);
    }

    public function testGetForAdminWithCuveeFalseFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('is_cuvee_speciale = ?'),
                $this->callback(fn(array $p): bool => in_array(0, $p, true))
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, null, null, false);
    }

    // ----------------------------------------------------------------
    // countForAdmin — vintage + labelName + cuvee filters (lines 612-624)
    // ----------------------------------------------------------------

    public function testCountForAdminWithVintageFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('vintage = ?'),
                $this->callback(fn(array $p): bool => in_array(2021, $p, true))
            )
            ->willReturn(['total' => 2]);

        $this->assertSame(2, $this->model->countForAdmin(null, null, 2021));
    }

    public function testCountForAdminWithLabelNameFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('label_name LIKE ?'),
                $this->callback(fn(array $p): bool => in_array('%Rouge%', $p, true))
            )
            ->willReturn(['total' => 3]);

        $this->assertSame(3, $this->model->countForAdmin(null, null, null, 'Rouge'));
    }

    public function testCountForAdminWithCuveeFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('is_cuvee_speciale = ?'),
                $this->callback(fn(array $p): bool => in_array(1, $p, true))
            )
            ->willReturn(['total' => 1]);

        $this->assertSame(1, $this->model->countForAdmin(null, null, null, null, true));
    }

    // ----------------------------------------------------------------
    // getAdminVintages (lines 583, 585-587)
    // ----------------------------------------------------------------

    public function testGetAdminVintagesReturnsMappedInts(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('DISTINCT vintage'))
            ->willReturn([['vintage' => '2021'], ['vintage' => '2019']]);

        $result = $this->model->getAdminVintages();

        $this->assertSame([2021, 2019], $result);
    }

    public function testGetAdminVintagesReturnsEmptyWhenNoVintages(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->assertSame([], $this->model->getAdminVintages());
    }

    // ----------------------------------------------------------------
    // getAdminLabelNames (lines 612-613, 617-618)
    // ----------------------------------------------------------------

    public function testGetAdminLabelNamesReturnsMappedStrings(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('DISTINCT label_name'))
            ->willReturn([['label_name' => 'Bordeaux'], ['label_name' => 'Saint-Émilion']]);

        $result = $this->model->getAdminLabelNames();

        $this->assertSame(['Bordeaux', 'Saint-Émilion'], $result);
    }

    public function testGetAdminLabelNamesReturnsEmptyWhenNone(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->assertSame([], $this->model->getAdminLabelNames());
    }

    // ----------------------------------------------------------------
    // create — null certification_label + null alcohol_degree (lines 637-640)
    // ----------------------------------------------------------------

    public function testCreateWithNullCertificationAndOptionalFields(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with($this->stringContains('INSERT INTO'))
            ->willReturn('3');

        $data = [
            'label_name' => 'Bordeaux', 'wine_color' => 'red', 'format' => 'bottle',
            'vintage' => 2020, 'price' => 12.0, 'production_quantity' => 300,
            'available' => 1, 'certification_label' => '',
            'area' => 1.0, 'city' => 'Pomerol', 'variety_of_vine' => 'Merlot',
            'age_of_vineyard' => 15,
            'is_cuvee_speciale' => 0, 'image_path' => '', 'slug' => 'bordeaux-2020',
        ];

        $id = $this->model->create($data);
        $this->assertSame(3, $id);
    }

    // ----------------------------------------------------------------
    // getRandomForCart — found (lines 441, 445) / not found (line 441)
    // ----------------------------------------------------------------

    public function testGetRandomForCartReturnsWineWhenFound(): void
    {
        $expected = ['id' => 3, 'label_name' => 'X', 'image_path' => 'img.jpg', 'slug' => 'x', 'price' => 10.0];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('image_path IS NOT NULL'))
            ->willReturn([['id' => 3]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('id = ?'), $this->equalTo([3]))
            ->willReturn($expected);

        $result = $this->model->getRandomForCart();

        $this->assertSame($expected, $result);
    }

    public function testGetRandomForCartReturnsNullWhenNoIds(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->dbMock->expects($this->never())->method('fetchOne');

        $this->assertNull($this->model->getRandomForCart());
    }

    public function testGetRandomForCartReturnsNullWhenFetchOneReturnsFalse(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 5]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getRandomForCart());
    }

    // ----------------------------------------------------------------
    // getActiveNewsletterSubscribers (lines 813, 816-817, 825, 829, 837-841, 843-844, 846-849, 851, 853)
    // ----------------------------------------------------------------

    public function testGetActiveNewsletterSubscribersMergesAndDeduplicates(): void
    {
        $fromAccounts = [
            ['email' => 'alice@example.com', 'lang' => 'fr', 'first_name' => 'Alice', 'unsub_token' => 'tok1'],
            ['email' => 'bob@example.com',   'lang' => 'en', 'first_name' => 'Bob',   'unsub_token' => 'tok2'],
        ];
        $fromSubs = [
            ['email' => 'alice@example.com', 'lang' => 'fr', 'first_name' => 'alice@example.com', 'unsub_token' => 'hash1'],
            ['email' => 'carol@example.com', 'lang' => 'fr', 'first_name' => 'carol@example.com', 'unsub_token' => 'hash2'],
        ];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls($fromAccounts, $fromSubs);

        $result = $this->model->getActiveNewsletterSubscribers();

        // alice deduped, bob + carol remain
        $this->assertCount(3, $result);
        $emails = array_column($result, 'email');
        $this->assertContains('alice@example.com', $emails);
        $this->assertContains('bob@example.com', $emails);
        $this->assertContains('carol@example.com', $emails);
    }

    public function testGetActiveNewsletterSubscribersReturnsEmptyWhenNone(): void
    {
        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->model->getActiveNewsletterSubscribers();

        $this->assertSame([], $result);
    }

    public function testGetActiveNewsletterSubscribersSkipsEmptyEmail(): void
    {
        $fromAccounts = [
            ['email' => '', 'lang' => 'fr', 'first_name' => '', 'unsub_token' => null],
        ];
        $fromSubs = [];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls($fromAccounts, $fromSubs);

        $result = $this->model->getActiveNewsletterSubscribers();

        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // getCuveeSpecialeByColor — found (lines 884-886, 888-890)
    // ----------------------------------------------------------------

    public function testGetCuveeSpecialeByColorReturnsWineWhenFound(): void
    {
        $expected = ['id' => 7, 'label_name' => 'Cuvée Or', 'wine_color' => 'sweet', 'slug' => 'cuvee-or'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('is_cuvee_speciale = 1'))
            ->willReturn([['id' => 7]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('id = ?'), $this->equalTo([7]))
            ->willReturn($expected);

        $result = $this->model->getCuveeSpecialeByColor('sweet');

        $this->assertSame($expected, $result);
    }

    public function testGetCuveeSpecialeByColorReturnsNullWhenNoneFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->dbMock->expects($this->never())->method('fetchOne');

        $this->assertNull($this->model->getCuveeSpecialeByColor('red'));
    }

    public function testGetCuveeSpecialeByColorWithExcludeIds(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('is_cuvee_speciale = 1'),
                    $this->stringContains('NOT IN')
                ),
                $this->callback(fn(array $p): bool => in_array(3, $p, true) && in_array('red', $p, true))
            )
            ->willReturn([]);

        $result = $this->model->getCuveeSpecialeByColor('red', [3, 5]);

        $this->assertNull($result);
    }

    public function testGetCuveeSpecialeByColorFetchOneReturnsFalse(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 9]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getCuveeSpecialeByColor('white'));
    }

    // ----------------------------------------------------------------
    // getAdjacentSlugs — prev null, next present (lines 884-886)
    // ----------------------------------------------------------------

    public function testGetAdjacentSlugsWithNullPrevAndPresentNext(): void
    {
        $next = ['slug' => 'vin-b-2021', 'label_name' => 'Vin B', 'vintage' => 2021, 'is_cuvee_speciale' => 0];

        $this->dbMock
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(false, $next);

        $result = $this->model->getAdjacentSlugs(1);

        $this->assertNull($result['prev']);
        $this->assertSame($next, $result['next']);
    }

    // ----------------------------------------------------------------
    // getAdjacentAvailableSlugs — prev present, next null (lines 905, 907)
    // ----------------------------------------------------------------

    public function testGetAdjacentAvailableSlugsWithPrevAndNullNext(): void
    {
        $prev = ['slug' => 'avail-x-2018', 'label_name' => 'Avail X', 'vintage' => 2018, 'is_cuvee_speciale' => 0];

        $this->dbMock
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls($prev, false);

        $result = $this->model->getAdjacentAvailableSlugs(5);

        $this->assertSame($prev, $result['prev']);
        $this->assertNull($result['next']);
    }

    // ----------------------------------------------------------------
    // getForCartCrossSell — returns empty when no ids after shuffle (line 1119)
    // ----------------------------------------------------------------

    public function testGetForCartCrossSellReturnsEmptyWhenNoAvailable(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->model->getForCartCrossSell([1, 2, 3], 4);

        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // getAll — getBySlug returns null (null path via false return) (line 42)
    // ----------------------------------------------------------------

    public function testGetBySlugReturnsFalseConvertedToNull(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->getBySlug('non-existent-slug');

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // buildAdminWineFilters — vintage out of range (line 611)
    // ----------------------------------------------------------------

    public function testGetForAdminWithVintageTooOldIsIgnored(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->logicalNot($this->stringContains('vintage = ?')),
                $this->equalTo([10, 0])
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, 1800);
    }

    public function testGetForAdminWithEmptyLabelNameIsIgnored(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->logicalNot($this->stringContains('label_name LIKE ?')),
                $this->equalTo([10, 0])
            )
            ->willReturn([]);

        $this->model->getForAdmin(null, null, 10, 0, null, '');
    }

    // ----------------------------------------------------------------
    // getRandomByColor — fetchOne returns false (line 78-79)
    // ----------------------------------------------------------------

    public function testGetRandomByColorReturnsNullWhenFetchOneReturnsFalse(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 10]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->getRandomByColor('red');

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // getRandom — fetchOne returns false (line 83)
    // ----------------------------------------------------------------

    public function testGetRandomReturnsNullWhenFetchOneReturnsFalse(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 11]]);

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getRandom());
    }

    // ----------------------------------------------------------------
    // getRandomForMemo — pickedIds vides après slice (line 485)
    // ----------------------------------------------------------------

    /**
     * getRandomForMemo(0) → array_slice($ids, 0, 0) = [] → pickedIds = [] → return [] (line 485).
     * Couvre WineModel.php:485.
     */
    public function testGetRandomForMemoReturnsEmptyWhenLimitIsZero(): void
    {
        // fetchAll retourne des IDs (non-empty) mais limit=0 → slice vide → pickedIds = []
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1], ['id' => 2], ['id' => 3]]);

        $result = $this->model->getRandomForMemo(0);

        $this->assertSame([], $result);
    }
}
