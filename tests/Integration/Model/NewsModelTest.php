<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\NewsModel;
use Tests\Integration\IntegrationTestCase;

class NewsModelTest extends IntegrationTestCase
{
    private NewsModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsModel();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function insertNews(string $slug = 'test-slug'): void
    {
        $title   = json_encode(['fr' => 'Titre de test', 'en' => 'Test title']);
        $content = json_encode(['fr' => 'Contenu de test.', 'en' => 'Test content.']);

        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at)
             VALUES (?, ?, ?, NOW())",
            [$title, $content, $slug]
        );
    }

    // ── getLatest() ─────────────────────────────────────────────────────────

    public function testGetLatestReturnsAtMostNEntries(): void
    {
        $this->insertNews('news-a');
        $this->insertNews('news-b');
        $this->insertNews('news-c');
        $this->insertNews('news-d');

        $results = $this->model->getLatest(3);

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testGetLatestReturnsEmptyArrayWhenNoNews(): void
    {
        // Table nettoyée par le rollback de setUp — aucune news dans cette transaction
        self::$db->execute("DELETE FROM news WHERE 1=1");

        $results = $this->model->getLatest(3);
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testGetLatestHasExpectedColumns(): void
    {
        $this->insertNews('test-columns');
        $results = $this->model->getLatest(1);

        $this->assertNotEmpty($results);
        $row = $results[0];

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('title', $row);
        $this->assertArrayHasKey('text_content', $row);
        $this->assertArrayHasKey('slug', $row);
        $this->assertArrayHasKey('created_at', $row);
    }

    // ── getBySlug() ─────────────────────────────────────────────────────────

    public function testGetBySlugReturnsNewsForValidSlug(): void
    {
        $this->insertNews('mon-slug-unique');

        $result = $this->model->getBySlug('mon-slug-unique');

        $this->assertNotNull($result);
        $this->assertSame('mon-slug-unique', $result['slug']);
        $this->assertStringContainsString('Titre de test', $result['title']);
    }

    public function testGetBySlugReturnsNullForUnknownSlug(): void
    {
        $result = $this->model->getBySlug('slug-qui-nexiste-pas-xyz');
        $this->assertNull($result);
    }

    // ── getAll() ────────────────────────────────────────────────────────────

    public function testGetAllReturnsAllInsertedNews(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");
        $this->insertNews('all-a');
        $this->insertNews('all-b');
        $this->insertNews('all-c');

        $results = $this->model->getAll();

        $this->assertCount(3, $results);
    }

    public function testGetAllIsOrderedByDateDesc(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");

        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Ancien']), json_encode(['fr' => '']), 'old-news', '2020-01-01 00:00:00']
        );
        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Récent']), json_encode(['fr' => '']), 'new-news', '2025-01-01 00:00:00']
        );

        $results = $this->model->getAll();

        $this->assertSame('new-news', $results[0]['slug']);
        $this->assertSame('old-news', $results[1]['slug']);
    }
}
