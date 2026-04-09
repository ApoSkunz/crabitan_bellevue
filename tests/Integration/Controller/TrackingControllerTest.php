<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\TrackingController;
use Core\Exception\HttpException;
use Core\Request;
use Model\NewsletterTrackingModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour TrackingController.
 * Couvre open() (pixel GIF) et click() (redirect + enregistrement clic).
 */
class TrackingControllerTest extends IntegrationTestCase
{
    private int $newsletterId;
    private int $accountId;
    private string $unsubToken = 'tracking-test-token-abc123';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';

        // Campagne de test
        $this->newsletterId = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, body, sent_count, failed_count, sent_at)
             VALUES ('Tracking test', 'Corps', 0, 0, NOW())"
        );

        // Abonné avec token de désabonnement
        $this->accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, newsletter, newsletter_unsubscribe_token)
             VALUES ('tracking@test.local', 'x', 'customer', 'fr', NOW(), 1, ?)",
            [$this->unsubToken]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Track', 'Test', 'M')",
            [$this->accountId]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(string $uri = '/track/open/1/token'): TrackingController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        return new TrackingController(new Request());
    }

    private function insertLink(string $url = 'https://crabitanbellevue.fr/fr/vins'): int
    {
        return (int) self::$db->insert(
            'INSERT INTO newsletter_links (newsletter_id, url) VALUES (?, ?)',
            [$this->newsletterId, $url]
        );
    }

    // ----------------------------------------------------------------
    // open() — pixel GIF
    // ----------------------------------------------------------------

    /**
     * open() avec token valide → HttpException 200 + ouvre l'enregistrement.
     */
    public function testOpenWithValidTokenRecordsOpenAndThrows200(): void
    {
        $thrown = null;
        ob_start();
        try {
            $this->makeController()->open([
                'id'    => (string) $this->newsletterId,
                'token' => $this->unsubToken,
            ]);
        } catch (HttpException $e) {
            $thrown = $e;
        }
        $output = ob_get_clean();

        $this->assertNotNull($thrown);
        $this->assertSame(200, $thrown->status);

        // Enregistrement dans newsletter_opens
        $row = self::$db->fetchOne(
            'SELECT id FROM newsletter_opens WHERE newsletter_id = ? AND subscriber_id = ?',
            [$this->newsletterId, $this->accountId]
        );
        $this->assertNotEmpty($row);
    }

    /**
     * open() avec token invalide → HttpException 200 mais aucun enregistrement.
     */
    public function testOpenWithInvalidTokenThrows200ButDoesNotRecord(): void
    {
        ob_start();
        try {
            $this->makeController()->open([
                'id'    => (string) $this->newsletterId,
                'token' => 'invalid-token',
            ]);
        } catch (HttpException $e) {
            // attendu
        }
        ob_get_clean();

        $count = self::$db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM newsletter_opens WHERE newsletter_id = ?',
            [$this->newsletterId]
        );
        $this->assertSame(0, (int) ($count['cnt'] ?? 0));
    }

    /**
     * open() avec id=0 → HttpException 200, pas d'enregistrement.
     */
    public function testOpenWithZeroIdThrows200WithoutRecording(): void
    {
        ob_start();
        try {
            $this->makeController()->open(['id' => '0', 'token' => $this->unsubToken]);
        } catch (HttpException $e) {
            // attendu
        }
        ob_get_clean();

        $count = self::$db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM newsletter_opens WHERE newsletter_id = ?',
            [$this->newsletterId]
        );
        $this->assertSame(0, (int) ($count['cnt'] ?? 0));
    }

    /**
     * open() avec token vide → HttpException 200, pas d'enregistrement.
     */
    public function testOpenWithEmptyTokenThrows200WithoutRecording(): void
    {
        ob_start();
        try {
            $this->makeController()->open(['id' => (string) $this->newsletterId, 'token' => '']);
        } catch (HttpException $e) {
            // attendu
        }
        ob_get_clean();

        $count = self::$db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM newsletter_opens WHERE newsletter_id = ?',
            [$this->newsletterId]
        );
        $this->assertSame(0, (int) ($count['cnt'] ?? 0));
    }

    /**
     * open() retourne le contenu binaire du GIF 1×1 dans le buffer.
     */
    public function testOpenOutputsGifPixel(): void
    {
        ob_start();
        try {
            $this->makeController()->open([
                'id'    => (string) $this->newsletterId,
                'token' => $this->unsubToken,
            ]);
        } catch (HttpException) {
            // attendu
        }
        $output = ob_get_clean();

        // Vérifie les magic bytes GIF89a (47 49 46 38 39 61)
        $this->assertStringStartsWith('GIF89a', $output);
    }

    // ----------------------------------------------------------------
    // click() — redirect
    // ----------------------------------------------------------------

    /**
     * click() avec linkId valide + token valide → redirect 302 vers l'URL cible.
     */
    public function testClickWithValidParamsRedirects302(): void
    {
        $linkId = $this->insertLink('https://crabitanbellevue.fr/fr/vins');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController()->click([
            'id'     => (string) $this->newsletterId,
            'linkId' => (string) $linkId,
            'token'  => $this->unsubToken,
        ]);
    }

    /**
     * click() enregistre le clic avec le bon subscriber_id.
     */
    public function testClickRecordsClickWithSubscriberId(): void
    {
        $linkId = $this->insertLink('https://crabitanbellevue.fr/fr/actualites');

        try {
            $this->makeController()->click([
                'id'     => (string) $this->newsletterId,
                'linkId' => (string) $linkId,
                'token'  => $this->unsubToken,
            ]);
        } catch (HttpException) {
            // redirect attendu
        }

        $row = self::$db->fetchOne(
            'SELECT subscriber_id FROM newsletter_clicks WHERE newsletter_id = ? AND link_id = ?',
            [$this->newsletterId, $linkId]
        );
        $this->assertNotEmpty($row);
        $this->assertSame($this->accountId, (int) $row['subscriber_id']);
    }

    /**
     * click() avec token invalide → enregistre le clic avec subscriber_id NULL.
     */
    public function testClickWithInvalidTokenRecordsNullSubscriberId(): void
    {
        $linkId = $this->insertLink('https://example.com');

        try {
            $this->makeController()->click([
                'id'     => (string) $this->newsletterId,
                'linkId' => (string) $linkId,
                'token'  => 'invalid-token',
            ]);
        } catch (HttpException) {
            // redirect attendu
        }

        $row = self::$db->fetchOne(
            'SELECT subscriber_id FROM newsletter_clicks WHERE newsletter_id = ? AND link_id = ?',
            [$this->newsletterId, $linkId]
        );
        $this->assertNotEmpty($row);
        $this->assertNull($row['subscriber_id']);
    }

    /**
     * click() avec token vide → enregistre le clic avec subscriber_id NULL.
     */
    public function testClickWithEmptyTokenRecordsNullSubscriberId(): void
    {
        $linkId = $this->insertLink('https://example.com');

        try {
            $this->makeController()->click([
                'id'     => (string) $this->newsletterId,
                'linkId' => (string) $linkId,
                'token'  => '',
            ]);
        } catch (HttpException) {
            // redirect attendu
        }

        $row = self::$db->fetchOne(
            'SELECT subscriber_id FROM newsletter_clicks WHERE newsletter_id = ? AND link_id = ?',
            [$this->newsletterId, $linkId]
        );
        $this->assertNotEmpty($row);
        $this->assertNull($row['subscriber_id']);
    }

    /**
     * click() avec linkId inexistant → HttpException 404.
     */
    public function testClickWithUnknownLinkIdThrows404(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->click([
            'id'     => (string) $this->newsletterId,
            'linkId' => '999999',
            'token'  => $this->unsubToken,
        ]);
    }

    /**
     * click() avec linkId appartenant à une autre campagne → 404.
     */
    public function testClickWithLinkFromOtherCampaignThrows404(): void
    {
        // Lien créé pour une autre campagne
        $otherNlId = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, body, sent_count, failed_count, sent_at)
             VALUES ('Other', 'Corps', 0, 0, NOW())"
        );
        $linkId = (int) self::$db->insert(
            'INSERT INTO newsletter_links (newsletter_id, url) VALUES (?, ?)',
            [$otherNlId, 'https://example.com']
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->click([
            'id'     => (string) $this->newsletterId, // mauvaise campagne
            'linkId' => (string) $linkId,
            'token'  => $this->unsubToken,
        ]);
    }
}
