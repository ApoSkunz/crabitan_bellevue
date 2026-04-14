<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\GoogleLinkTokenModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour GoogleLinkTokenModel.
 * Couvre : create (rotation DELETE puis INSERT), findActive, consume, TTL/expiration.
 */
class GoogleLinkTokenModelTest extends IntegrationTestCase
{
    private GoogleLinkTokenModel $model;
    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new GoogleLinkTokenModel();

        $accountModel = new AccountModel();
        $this->accountId = (int) $accountModel->create(
            'individual',
            'glt_test_' . bin2hex(random_bytes(4)) . '@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'GLT',
            'User',
            ''
        );
    }

    // ------------------------------------------------------------------
    // create
    // ------------------------------------------------------------------

    public function testCreateReturnsHexToken64Chars(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCreatePersistsSha256HashNotClearToken(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');

        $row = self::$db->fetchOne(
            'SELECT token_hash FROM google_link_tokens WHERE account_id = ? LIMIT 1',
            [$this->accountId]
        );

        $this->assertIsArray($row);
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertNotSame($token, $row['token_hash']);
    }

    public function testCreateRotatesPreviousUnconsumedTokenForSameAccount(): void
    {
        $first  = $this->model->create($this->accountId, 'google-id-1', 'fr');
        $second = $this->model->create($this->accountId, 'google-id-2', 'fr');

        $this->assertNotSame($first, $second);

        // Le premier doit être supprimé (rotation DELETE WHERE consumed_at IS NULL)
        $this->assertFalse($this->model->findActive($first));
        $this->assertIsArray($this->model->findActive($second));
    }

    public function testCreateDoesNotDeleteConsumedTokensForSameAccount(): void
    {
        $first = $this->model->create($this->accountId, 'google-id-1', 'fr');
        $row   = $this->model->findActive($first);
        $this->assertIsArray($row);
        $this->model->consume((int) $row['id']);

        // Un second create() ne doit pas toucher au token consommé (garde la trace historique)
        $this->model->create($this->accountId, 'google-id-2', 'fr');

        $consumed = self::$db->fetchOne(
            'SELECT id, consumed_at FROM google_link_tokens WHERE id = ?',
            [$row['id']]
        );

        $this->assertIsArray($consumed);
        $this->assertNotNull($consumed['consumed_at']);
    }

    public function testCreateSetsExpiryAt15MinutesFromNow(): void
    {
        $this->model->create($this->accountId, 'google-id-1', 'fr');

        $row = self::$db->fetchOne(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS diff
             FROM google_link_tokens WHERE account_id = ? LIMIT 1',
            [$this->accountId]
        );

        $this->assertIsArray($row);
        $diff = (int) $row['diff'];
        // Tolère la latence : on veut ~900 secondes (±30s)
        $this->assertGreaterThan(850, $diff);
        $this->assertLessThanOrEqual(900, $diff);
    }

    // ------------------------------------------------------------------
    // findActive
    // ------------------------------------------------------------------

    public function testFindActiveReturnsRowForValidToken(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-42', 'en');

        $row = $this->model->findActive($token);

        $this->assertIsArray($row);
        $this->assertSame($this->accountId, (int) $row['account_id']);
        $this->assertSame('google-id-42', $row['google_id']);
        $this->assertSame('en', $row['lang']);
        $this->assertNull($row['consumed_at']);
    }

    public function testFindActiveReturnsFalseForUnknownToken(): void
    {
        $this->assertFalse($this->model->findActive('nonexistent'));
    }

    public function testFindActiveReturnsFalseForConsumedToken(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');
        $row   = $this->model->findActive($token);
        $this->model->consume((int) $row['id']);

        $this->assertFalse($this->model->findActive($token));
    }

    public function testFindActiveReturnsFalseForExpiredToken(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');

        // Force l'expiration
        self::$db->execute(
            'UPDATE google_link_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
             WHERE account_id = ?',
            [$this->accountId]
        );

        $this->assertFalse($this->model->findActive($token));
    }

    // ------------------------------------------------------------------
    // consume
    // ------------------------------------------------------------------

    public function testConsumeMarksTokenConsumed(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');
        $row   = $this->model->findActive($token);

        $this->model->consume((int) $row['id']);

        $refreshed = self::$db->fetchOne(
            'SELECT consumed_at FROM google_link_tokens WHERE id = ?',
            [$row['id']]
        );

        $this->assertIsArray($refreshed);
        $this->assertNotNull($refreshed['consumed_at']);
    }

    public function testConsumeIsIdempotent(): void
    {
        $token = $this->model->create($this->accountId, 'google-id-1', 'fr');
        $row   = $this->model->findActive($token);
        $id    = (int) $row['id'];

        $this->model->consume($id);
        $firstConsumedAt = self::$db->fetchOne(
            'SELECT consumed_at FROM google_link_tokens WHERE id = ?',
            [$id]
        )['consumed_at'];

        // Second consume — UPDATE simple, pas d'erreur, consumed_at peut être mis à jour
        $this->model->consume($id);

        $row2 = self::$db->fetchOne(
            'SELECT consumed_at FROM google_link_tokens WHERE id = ?',
            [$id]
        );
        $this->assertIsArray($row2);
        $this->assertNotNull($row2['consumed_at']);
        $this->assertNotNull($firstConsumedAt);
    }

    public function testConsumeWithUnknownIdIsSilent(): void
    {
        // Ne doit pas lever d'exception
        $this->model->consume(999999);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // Constantes
    // ------------------------------------------------------------------

    public function testTtlMinutesConstant(): void
    {
        $this->assertSame(15, GoogleLinkTokenModel::TTL_MINUTES);
    }
}
