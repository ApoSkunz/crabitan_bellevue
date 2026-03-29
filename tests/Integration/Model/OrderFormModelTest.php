<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\OrderFormModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour OrderFormModel.
 * Chaque test s'exécute dans une transaction rollbackée (IntegrationTestCase).
 */
class OrderFormModelTest extends IntegrationTestCase
{
    private OrderFormModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrderFormModel();
    }

    // ----------------------------------------------------------------
    // create + findById
    // ----------------------------------------------------------------

    public function testCreateReturnsPositiveId(): void
    {
        $id = $this->model->create(2024, null, 'test_2024_abc.pdf');
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateWithLabelAndFindById(): void
    {
        $id  = $this->model->create(2025, 'V2', 'test_2025_v2.pdf');
        $row = $this->model->findById($id);

        $this->assertIsArray($row);
        $this->assertSame(2025, (int) $row['year']);
        $this->assertSame('V2', $row['label']);
        $this->assertSame('test_2025_v2.pdf', $row['filename']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $this->assertFalse($this->model->findById(999999));
    }

    // ----------------------------------------------------------------
    // findByIdOrNull
    // ----------------------------------------------------------------

    public function testFindByIdOrNullReturnsNullForMissing(): void
    {
        $this->assertNull($this->model->findByIdOrNull(999999));
    }

    public function testFindByIdOrNullReturnsRowForExisting(): void
    {
        $id  = $this->model->create(2023, null, 'test_2023.pdf');
        $row = $this->model->findByIdOrNull($id);

        $this->assertNotNull($row);
        $this->assertSame($id, (int) $row['id']);
    }

    // ----------------------------------------------------------------
    // countAll
    // ----------------------------------------------------------------

    public function testCountAllReflectsInserts(): void
    {
        $before = $this->model->countAll();

        $this->model->create(2022, null, 'count_a.pdf');
        $this->model->create(2022, 'V2', 'count_b.pdf');

        $this->assertSame($before + 2, $this->model->countAll());
    }

    // ----------------------------------------------------------------
    // getAll
    // ----------------------------------------------------------------

    public function testGetAllReturnsInsertedRows(): void
    {
        $this->model->create(2020, null, 'hist_2020.pdf');
        $this->model->create(2021, null, 'hist_2021.pdf');

        $all    = $this->model->getAll();
        $years  = array_column($all, 'year');

        $this->assertContains(2020, array_map('intval', $years));
        $this->assertContains(2021, array_map('intval', $years));
    }

    // ----------------------------------------------------------------
    // getPaginated
    // ----------------------------------------------------------------

    public function testGetPaginatedReturnsCorrectSlice(): void
    {
        // Insert 3 rows and track total (table may already contain seeded data)
        $this->model->create(2021, null, 'pg_a.pdf');
        $this->model->create(2022, null, 'pg_b.pdf');
        $this->model->create(2023, null, 'pg_c.pdf');

        $total = $this->model->countAll();

        // Page 1 must return exactly perPage=2 (total >= 3)
        $page1 = $this->model->getPaginated(1, 2);
        $this->assertCount(2, $page1);

        // Beyond last page returns empty
        $beyondLast = $this->model->getPaginated($total + 1, $total);
        $this->assertCount(0, $beyondLast);

        // Total rows across all pages equals countAll()
        $lastPage  = (int) ceil($total / 2);
        $allRows   = [];
        for ($p = 1; $p <= $lastPage; $p++) {
            $allRows = array_merge($allRows, $this->model->getPaginated($p, 2));
        }
        $this->assertCount($total, $allRows);
    }

    // ----------------------------------------------------------------
    // getLatest
    // ----------------------------------------------------------------

    public function testGetLatestReturnsLastInserted(): void
    {
        $this->model->create(2020, null, 'old.pdf');
        $latestId = $this->model->create(2025, 'Latest', 'latest.pdf');

        $row = $this->model->getLatest();
        $this->assertNotNull($row);
        $this->assertSame($latestId, (int) $row['id']);
    }

    public function testGetLatestReturnsNullWhenEmpty(): void
    {
        // Suppression dans la transaction courante — rollbackée en tearDown
        self::$db->execute('DELETE FROM order_forms');

        $this->assertNull($this->model->getLatest());
    }

    // ----------------------------------------------------------------
    // delete
    // ----------------------------------------------------------------

    public function testDeleteRemovesRow(): void
    {
        $id = $this->model->create(2024, null, 'del_test.pdf');

        $this->assertNotNull($this->model->findByIdOrNull($id));

        $returned = $this->model->delete($id);

        $this->assertSame($id, $returned);
        $this->assertNull($this->model->findByIdOrNull($id));
    }

    public function testDeleteNonExistentDoesNotThrow(): void
    {
        $result = $this->model->delete(999999);
        $this->assertSame(999999, $result);
    }
}
