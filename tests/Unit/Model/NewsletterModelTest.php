<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\NewsletterModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsletterModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class NewsletterModelTest extends TestCase
{
    private Database $dbMock;
    private NewsletterModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new NewsletterModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // create()
    // ----------------------------------------------------------------

    /**
     * create() exécute un INSERT et retourne l'identifiant généré.
     */
    public function testCreateInsertsAndReturnsId(): void
    {
        $this->dbMock->expects($this->once())
            ->method('insert')
            ->willReturn('42');

        $id = $this->model->create('Sujet test', 'Corps test', null);

        $this->assertSame(42, $id);
    }

    /**
     * create() transmet correctement imageUrl lorsqu'elle est fournie.
     */
    public function testCreatePassesImageUrl(): void
    {
        $this->dbMock->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->callback(fn ($params) => $params[2] === 'https://example.com/img.jpg')
            )
            ->willReturn('1');

        $this->model->create('Sujet', 'Corps', 'https://example.com/img.jpg');
    }

    // ----------------------------------------------------------------
    // updateStats()
    // ----------------------------------------------------------------

    /**
     * updateStats() exécute un UPDATE avec les bons compteurs.
     */
    public function testUpdateStatsExecutesUpdate(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE'),
                [10, 2, 5]
            );

        $this->model->updateStats(5, 10, 2);
    }

    // ----------------------------------------------------------------
    // count()
    // ----------------------------------------------------------------

    /**
     * count() retourne le total issu de la BDD.
     */
    public function testCountReturnsTotalFromDb(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '7']);

        $this->assertSame(7, $this->model->count());
    }

    /**
     * count() retourne 0 si fetchOne renvoie false.
     */
    public function testCountReturnsZeroWhenNoRows(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $this->model->count());
    }

    // ----------------------------------------------------------------
    // findById()
    // ----------------------------------------------------------------

    /**
     * findById() retourne null si la campagne n'existe pas.
     */
    public function testFindCampaignByIdReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findCampaignById(99));
    }

    /**
     * findCampaignById() enrichit le résultat avec les pièces jointes.
     */
    public function testFindCampaignByIdIncludesAttachments(): void
    {
        $campaign = [
            'id' => 1, 'subject' => 'Test', 'body' => 'Corps',
            'image_url' => null, 'sent_count' => 5, 'failed_count' => 0,
            'sent_at' => '2026-03-29 10:00:00',
        ];
        $attachments = [
            ['id' => 1, 'original_name' => 'doc.pdf', 'stored_path' => 'storage/newsletters/attachments/doc.pdf'],
        ];

        $this->dbMock->method('fetchOne')->willReturn($campaign);
        $this->dbMock->method('fetchAll')->willReturn($attachments);

        $result = $this->model->findCampaignById(1);

        $this->assertNotNull($result);
        $this->assertSame($attachments, $result['attachments']);
    }

    // ----------------------------------------------------------------
    // saveAttachment()
    // ----------------------------------------------------------------

    /**
     * saveAttachment() exécute un INSERT dans newsletter_attachments.
     */
    public function testSaveAttachmentExecutesInsert(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('newsletter_attachments'),
                [3, 'archive.pdf', 'storage/newsletters/attachments/nl_3_abc.pdf']
            );

        $this->model->saveAttachment(3, 'archive.pdf', 'storage/newsletters/attachments/nl_3_abc.pdf');
    }
}
