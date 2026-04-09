<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\NewsletterTrackingModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsletterTrackingModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class NewsletterTrackingModelTest extends TestCase
{
    private Database $dbMock;
    private NewsletterTrackingModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new NewsletterTrackingModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // saveLink()
    // ----------------------------------------------------------------

    /**
     * saveLink() exécute un INSERT et retourne l'identifiant généré.
     */
    public function testSaveLinkReturnsId(): void
    {
        $this->dbMock->expects($this->once())
            ->method('insert')
            ->willReturn('7');

        $id = $this->model->saveLink(1, 'https://example.com');

        $this->assertSame(7, $id);
    }

    /**
     * saveLink() passe le bon newsletterId et l'URL.
     */
    public function testSaveLinkPassesCorrectParams(): void
    {
        $this->dbMock->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('newsletter_links'),
                [3, 'https://crabitanbellevue.fr/fr/vins']
            )
            ->willReturn('1');

        $this->model->saveLink(3, 'https://crabitanbellevue.fr/fr/vins');
    }

    // ----------------------------------------------------------------
    // findLink()
    // ----------------------------------------------------------------

    /**
     * findLink() retourne le tableau de la ligne trouvée.
     */
    public function testFindLinkReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'newsletter_id' => 1, 'url' => 'https://example.com'];
        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findLink(1, 1);

        $this->assertSame($row, $result);
    }

    /**
     * findLink() retourne null si aucune ligne trouvée.
     */
    public function testFindLinkReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findLink(99, 1));
    }

    // ----------------------------------------------------------------
    // recordOpen()
    // ----------------------------------------------------------------

    /**
     * recordOpen() exécute un INSERT IGNORE.
     */
    public function testRecordOpenExecutesInsertIgnore(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT IGNORE'),
                [2, 5]
            );

        $this->model->recordOpen(2, 5);
    }

    // ----------------------------------------------------------------
    // recordClick()
    // ----------------------------------------------------------------

    /**
     * recordClick() exécute un INSERT avec les bons paramètres.
     */
    public function testRecordClickExecutesInsert(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('newsletter_clicks'),
                [1, 3, 5]
            );

        $this->model->recordClick(1, 3, 5);
    }

    /**
     * recordClick() accepte un subscriber_id null (abonné anonyme).
     */
    public function testRecordClickAcceptsNullSubscriberId(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                [1, 2, null]
            );

        $this->model->recordClick(1, 2, null);
    }

    // ----------------------------------------------------------------
    // getStats()
    // ----------------------------------------------------------------

    /**
     * getStats() retourne opens_count et clicks_count.
     */
    public function testGetStatsReturnsCountsFromDb(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['opens_count' => '3', 'clicks_count' => '7']);

        $stats = $this->model->getStats(1);

        $this->assertSame('3', $stats['opens_count']);
        $this->assertSame('7', $stats['clicks_count']);
    }

    /**
     * getStats() retourne des zéros si fetchOne renvoie false.
     */
    public function testGetStatsReturnsZeroWhenNoRows(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $stats = $this->model->getStats(99);

        $this->assertSame(0, $stats['opens_count']);
        $this->assertSame(0, $stats['clicks_count']);
    }

    // ----------------------------------------------------------------
    // purgeOlderThan13Months()
    // ----------------------------------------------------------------

    /**
     * purgeOlderThan13Months() lance deux DELETE et additionne les lignes supprimées.
     */
    public function testPurgeDeletesBothTablesAndReturnsTotal(): void
    {
        $this->dbMock->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(4, 2);

        $total = $this->model->purgeOlderThan13Months();

        $this->assertSame(6, $total);
    }

    /**
     * purgeOlderThan13Months() retourne 0 si rien à purger.
     */
    public function testPurgeReturnsZeroWhenNothingToDelete(): void
    {
        $this->dbMock->method('execute')->willReturn(0);

        $this->assertSame(0, $this->model->purgeOlderThan13Months());
    }
}
