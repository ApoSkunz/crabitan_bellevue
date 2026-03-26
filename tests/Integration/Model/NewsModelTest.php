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

    // ── getPrev() ───────────────────────────────────────────────────────────

    public function testGetPrevReturnsNewerArticle(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");

        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Ancien']), json_encode(['fr' => '']), 'older-article', '2020-01-01 00:00:00']
        );
        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Récent']), json_encode(['fr' => '']), 'newer-article', '2025-01-01 00:00:00']
        );

        $prev = $this->model->getPrev('older-article');

        $this->assertNotNull($prev, "getPrev() doit retourner l'article plus récent");
        $this->assertSame('newer-article', $prev['slug']);
    }

    public function testGetPrevReturnsNullWhenNoPreviousExists(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");
        $this->insertNews('only-article');

        $prev = $this->model->getPrev('only-article');

        $this->assertNull($prev, "getPrev() doit retourner null quand il n'y a pas d'article plus récent");
    }

    // ── getNext() ───────────────────────────────────────────────────────────

    public function testGetNextReturnsOlderArticle(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");

        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Récent']), json_encode(['fr' => '']), 'newer-article', '2025-01-01 00:00:00']
        );
        self::$db->execute(
            "INSERT INTO news (title, text_content, slug, created_at) VALUES (?, ?, ?, ?)",
            [json_encode(['fr' => 'Ancien']), json_encode(['fr' => '']), 'older-article', '2020-01-01 00:00:00']
        );

        $next = $this->model->getNext('newer-article');

        $this->assertNotNull($next, "getNext() doit retourner l'article plus ancien");
        $this->assertSame('older-article', $next['slug']);
    }

    public function testGetNextReturnsNullWhenNoNextExists(): void
    {
        self::$db->execute("DELETE FROM news WHERE 1=1");
        $this->insertNews('only-article');

        $next = $this->model->getNext('only-article');

        $this->assertNull($next, "getNext() doit retourner null quand il n'y a pas d'article plus ancien");
    }

    // ── create() ────────────────────────────────────────────────────────────

    public function testCreateInsertsNewsAndReturnsId(): void
    {
        $id = $this->model->create([
            'title'        => json_encode(['fr' => 'Article créé', 'en' => 'Created article']),
            'text_content' => json_encode(['fr' => 'Contenu', 'en' => 'Content']),
            'image_path'   => null,
            'link_path'    => null,
            'slug'         => 'article-cree-test',
        ]);

        $this->assertGreaterThan(0, $id, 'create() doit retourner un ID positif');

        $found = $this->model->getBySlug('article-cree-test');
        $this->assertNotNull($found);
        $this->assertSame('article-cree-test', $found['slug']);
    }

    // ── update() ────────────────────────────────────────────────────────────

    public function testUpdateModifiesExistingNews(): void
    {
        $id = $this->model->create([
            'title'        => json_encode(['fr' => 'Avant mise à jour', 'en' => 'Before update']),
            'text_content' => json_encode(['fr' => 'Texte original', 'en' => 'Original text']),
            'image_path'   => null,
            'link_path'    => null,
            'slug'         => 'avant-maj-test',
        ]);

        $this->model->update($id, [
            'title'        => json_encode(['fr' => 'Après mise à jour', 'en' => 'After update']),
            'text_content' => json_encode(['fr' => 'Nouveau texte', 'en' => 'New text']),
            'image_path'   => 'photo.jpg',
            'link_path'    => '/fr/vins',
            'slug'         => 'apres-maj-test',
        ]);

        $found = $this->model->getBySlug('apres-maj-test');
        $this->assertNotNull($found, 'update() doit modifier le slug');
        /** @var array<string, string> $decodedTitle */
        $decodedTitle = json_decode($found['title'], true);
        $this->assertSame('Après mise à jour', $decodedTitle['fr']);
    }
}
