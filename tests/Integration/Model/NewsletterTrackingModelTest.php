<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\NewsletterTrackingModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour NewsletterTrackingModel.
 */
class NewsletterTrackingModelTest extends IntegrationTestCase
{
    private NewsletterTrackingModel $model;
    private int $newsletterId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsletterTrackingModel();

        // Crée une campagne newsletter de test pour satisfaire les FK
        $this->newsletterId = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, body, sent_count, failed_count, sent_at)
             VALUES (?, ?, ?, ?, NOW())",
            ['Test tracking', 'Corps test', 0, 0]
        );
    }

    // ----------------------------------------------------------------
    // saveLink() + findLink()
    // ----------------------------------------------------------------

    public function testSaveLinkAndFindLink(): void
    {
        $url    = 'https://crabitanbellevue.fr/fr/vins';
        $linkId = $this->model->saveLink($this->newsletterId, $url);

        $this->assertGreaterThan(0, $linkId);

        $link = $this->model->findLink($linkId, $this->newsletterId);
        $this->assertNotNull($link);
        $this->assertSame($url, $link['url']);
        $this->assertSame($this->newsletterId, (int) $link['newsletter_id']);
    }

    public function testFindLinkReturnsNullForUnknownId(): void
    {
        $this->assertNull($this->model->findLink(999999, $this->newsletterId));
    }

    public function testFindLinkReturnsNullForWrongNewsletter(): void
    {
        $linkId = $this->model->saveLink($this->newsletterId, 'https://example.com');
        $this->assertNull($this->model->findLink($linkId, $this->newsletterId + 1));
    }

    // ----------------------------------------------------------------
    // recordOpen() + getStats()
    // ----------------------------------------------------------------

    public function testRecordOpenInsertsRecord(): void
    {
        $this->model->recordOpen($this->newsletterId, 3);

        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(1, (int) $stats['opens_count']);
    }

    public function testRecordOpenIgnoresDuplicate(): void
    {
        $this->model->recordOpen($this->newsletterId, 3);
        $this->model->recordOpen($this->newsletterId, 3); // doublon → ignoré

        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(1, (int) $stats['opens_count']);
    }

    // ----------------------------------------------------------------
    // recordClick() + getStats()
    // ----------------------------------------------------------------

    public function testRecordClickInsertsRecord(): void
    {
        $linkId = $this->model->saveLink($this->newsletterId, 'https://example.com');
        $this->model->recordClick($this->newsletterId, $linkId, 3);

        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(1, (int) $stats['clicks_count']);
    }

    public function testRecordClickAcceptsNullSubscriberId(): void
    {
        $linkId = $this->model->saveLink($this->newsletterId, 'https://example.com');
        $this->model->recordClick($this->newsletterId, $linkId, null);

        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(1, (int) $stats['clicks_count']);
    }

    // ----------------------------------------------------------------
    // getStats()
    // ----------------------------------------------------------------

    public function testGetStatsReturnsZeroForNewCampaign(): void
    {
        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(0, (int) $stats['opens_count']);
        $this->assertSame(0, (int) $stats['clicks_count']);
    }

    public function testGetStatsAggregatesMultipleOpenersAndClickers(): void
    {
        $linkId = $this->model->saveLink($this->newsletterId, 'https://example.com');

        $this->model->recordOpen($this->newsletterId, 3);
        $this->model->recordOpen($this->newsletterId, 5);
        $this->model->recordClick($this->newsletterId, $linkId, 3);
        $this->model->recordClick($this->newsletterId, $linkId, 3); // doublon → unique_clicks = 1
        $this->model->recordClick($this->newsletterId, $linkId, 5);
        $this->model->recordClick($this->newsletterId, $linkId, null);

        $stats = $this->model->getStats($this->newsletterId);
        $this->assertSame(2, (int) $stats['opens_count']);
        $this->assertSame(4, (int) $stats['clicks_count']);
        $this->assertSame(2, (int) $stats['unique_clicks_count']); // subscriber 3 et 5 (null exclu du DISTINCT)
    }

    // ----------------------------------------------------------------
    // getLinkStats()
    // ----------------------------------------------------------------

    public function testGetLinkStatsReturnsPerLinkBreakdown(): void
    {
        $linkId1 = $this->model->saveLink($this->newsletterId, 'https://example.com/a');
        $linkId2 = $this->model->saveLink($this->newsletterId, 'https://example.com/b');

        $this->model->recordClick($this->newsletterId, $linkId1, 3);
        $this->model->recordClick($this->newsletterId, $linkId1, 3); // même abonné → unique=1, total=2
        $this->model->recordClick($this->newsletterId, $linkId1, 5); // 2e abonné → unique=2, total=3
        $this->model->recordClick($this->newsletterId, $linkId2, 3); // lien 2 → unique=1, total=1

        $rows = $this->model->getLinkStats($this->newsletterId);

        $this->assertCount(2, $rows);
        // Trié par total_clicks DESC → lien 1 en premier
        $this->assertSame('https://example.com/a', $rows[0]['url']);
        $this->assertSame(3, (int) $rows[0]['total_clicks']);
        $this->assertSame(2, (int) $rows[0]['unique_clicks']);

        $this->assertSame('https://example.com/b', $rows[1]['url']);
        $this->assertSame(1, (int) $rows[1]['total_clicks']);
        $this->assertSame(1, (int) $rows[1]['unique_clicks']);
    }

    public function testGetLinkStatsReturnsEmptyArrayWhenNoLinks(): void
    {
        $rows = $this->model->getLinkStats($this->newsletterId);
        $this->assertSame([], $rows);
    }

    public function testGetLinkStatsReturnsLinkWithZeroClicksWhenNoClicks(): void
    {
        $this->model->saveLink($this->newsletterId, 'https://example.com/no-clicks');

        $rows = $this->model->getLinkStats($this->newsletterId);

        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) $rows[0]['total_clicks']);
        $this->assertSame(0, (int) $rows[0]['unique_clicks']);
    }

    // ----------------------------------------------------------------
    // purgeOlderThan13Months()
    // ----------------------------------------------------------------

    public function testPurgeDeletesOldRecords(): void
    {
        // Insère directement avec une date ancienne (> 13 mois)
        $oldDate = date('Y-m-d H:i:s', strtotime('-14 months'));
        self::$db->execute(
            'INSERT INTO newsletter_opens (newsletter_id, subscriber_id, opened_at) VALUES (?, ?, ?)',
            [$this->newsletterId, 3, $oldDate]
        );
        $linkId = $this->model->saveLink($this->newsletterId, 'https://example.com');
        self::$db->execute(
            'INSERT INTO newsletter_clicks (newsletter_id, link_id, subscriber_id, clicked_at) VALUES (?, ?, ?, ?)',
            [$this->newsletterId, $linkId, 3, $oldDate]
        );

        $deleted = $this->model->purgeOlderThan13Months();
        $this->assertSame(2, $deleted);
    }

    public function testPurgeReturnsZeroWhenNothingToDelete(): void
    {
        // Insère des enregistrements récents
        $this->model->recordOpen($this->newsletterId, 3);

        $deleted = $this->model->purgeOlderThan13Months();
        $this->assertSame(0, $deleted);
    }
}
