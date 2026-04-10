<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\LegalDocumentModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour LegalDocumentModel.
 */
class LegalDocumentModelTest extends IntegrationTestCase
{
    private LegalDocumentModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new LegalDocumentModel();
    }

    // ----------------------------------------------------------------
    // findAll()
    // ----------------------------------------------------------------

    public function testFindAllReturnsIndexedBySlug(): void
    {
        $result = $this->model->findAll();

        $this->assertArrayHasKey('cgv', $result);
        $this->assertArrayHasKey('politique_confidentialite', $result);
        $this->assertArrayHasKey('mentions_legales', $result);
        $this->assertArrayHasKey('version', $result['cgv']);
    }

    public function testFindAllContainsAllEightDocuments(): void
    {
        $result = $this->model->findAll();

        $expected = [
            'cgv', 'politique_confidentialite', 'mentions_legales',
            'matrice_habilitations', 'registre_traitements', 'sous_traitants',
            'procedure_violation', 'dpia',
        ];
        foreach ($expected as $slug) {
            $this->assertArrayHasKey($slug, $result, "Slug manquant : {$slug}");
        }
    }

    // ----------------------------------------------------------------
    // findBySlug()
    // ----------------------------------------------------------------

    public function testFindBySlugReturnsCgv(): void
    {
        $result = $this->model->findBySlug('cgv');

        $this->assertNotNull($result);
        $this->assertSame('cgv', $result['slug']);
        $this->assertSame('1', (string) $result['requires_notification']);
    }

    public function testFindBySlugReturnsNullForUnknownSlug(): void
    {
        $this->assertNull($this->model->findBySlug('slug_inexistant'));
    }

    // ----------------------------------------------------------------
    // update()
    // ----------------------------------------------------------------

    public function testUpdateChangesVersionAndDate(): void
    {
        $this->model->update('mentions_legales', '1.1', '2026-05-01', 1);

        $result = $this->model->findBySlug('mentions_legales');
        $this->assertNotNull($result);
        $this->assertSame('1.1', $result['version']);
        $this->assertSame('2026-05-01', $result['updated_at']);
    }

    public function testUpdateReturnsFalseForUnknownSlug(): void
    {
        $result = $this->model->update('slug_inexistant', '2.0', '2026-05-01', 1);
        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // logHistory() + findHistoryByDocumentId()
    // ----------------------------------------------------------------

    public function testLogHistoryInsertsEntry(): void
    {
        $doc = $this->model->findBySlug('cgv');
        $this->assertNotNull($doc);

        $this->model->logHistory((int) $doc['id'], '1.5', '2026-04-10', 1);

        $history = $this->model->findHistoryByDocumentId((int) $doc['id']);
        $this->assertNotEmpty($history);

        $versions = array_column($history, 'version');
        $this->assertContains('1.5', $versions);
    }

    public function testFindHistoryByDocumentIdReturnsEmptyForNewDocument(): void
    {
        $doc = $this->model->findBySlug('dpia');
        $this->assertNotNull($doc);

        $history = $this->model->findHistoryByDocumentId((int) $doc['id']);
        $this->assertIsArray($history);
    }
}
