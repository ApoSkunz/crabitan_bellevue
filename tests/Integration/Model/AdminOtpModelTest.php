<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AdminOtpModel;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\IntegrationTestCase;

#[CoversClass(AdminOtpModel::class)]
class AdminOtpModelTest extends IntegrationTestCase
{
    private AdminOtpModel $model;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AdminOtpModel();

        $this->adminId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'super_admin', 'fr', NOW())",
            ['otp-admin-' . bin2hex(random_bytes(4)) . '@test.local', password_hash('Admin123!', PASSWORD_BCRYPT)]
        );
    }

    // ----------------------------------------------------------------
    // create() + find()
    // ----------------------------------------------------------------

    public function testCreateAndFindReturnRow(): void
    {
        $hash = hash('sha256', '123456');
        $this->model->create($this->adminId, 'ff_batch', $hash, null, 120);

        $row = $this->model->find($this->adminId, 'ff_batch');

        $this->assertIsArray($row);
        $this->assertSame($hash, $row['otp_hash']);
        $this->assertSame(0, (int) $row['attempts']);
    }

    public function testFindReturnsNullWhenNotExists(): void
    {
        $row = $this->model->find($this->adminId, 'ff_batch');
        $this->assertNull($row);
    }

    public function testCreateUpsertOverwritesPreviousOtp(): void
    {
        $hash1 = hash('sha256', '111111');
        $hash2 = hash('sha256', '222222');
        $this->model->create($this->adminId, 'ff_batch', $hash1, null, 120);
        $this->model->create($this->adminId, 'ff_batch', $hash2, 'payload', 120);

        $row = $this->model->find($this->adminId, 'ff_batch');

        $this->assertIsArray($row);
        $this->assertSame($hash2, $row['otp_hash']);
        $this->assertSame(0, (int) $row['attempts']); // reset to 0 on upsert
    }

    public function testCreateStoresPayload(): void
    {
        $payload = json_encode(['key' => 'foo', 'new' => 'bar']);
        $this->model->create($this->adminId, 'ff_batch', hash('sha256', '000000'), $payload, 120);

        $row = $this->model->find($this->adminId, 'ff_batch');

        $this->assertIsArray($row);
        $this->assertSame($payload, $row['payload']);
    }

    // ----------------------------------------------------------------
    // find() — OTP expiré retourne null
    // ----------------------------------------------------------------

    public function testFindReturnsNullWhenExpired(): void
    {
        $hash = hash('sha256', '123456');
        // TTL de 1 seconde → expire immédiatement
        $this->model->create($this->adminId, 'ff_batch', $hash, null, -1);

        $row = $this->model->find($this->adminId, 'ff_batch');

        $this->assertNull($row);
    }

    // ----------------------------------------------------------------
    // incrementAttempts()
    // ----------------------------------------------------------------

    public function testIncrementAttemptsUpdatesCounter(): void
    {
        $this->model->create($this->adminId, 'ff_batch', hash('sha256', '000000'), null, 120);
        $row = $this->model->find($this->adminId, 'ff_batch');
        $this->assertIsArray($row);

        $this->model->incrementAttempts((int) $row['id']);
        $this->model->incrementAttempts((int) $row['id']);

        $updatedRow = self::$db->fetchOne(
            'SELECT attempts FROM admin_otps WHERE id = ?',
            [(int) $row['id']]
        );
        $this->assertSame(2, (int) $updatedRow['attempts']);
    }

    // ----------------------------------------------------------------
    // delete()
    // ----------------------------------------------------------------

    public function testDeleteRemovesRow(): void
    {
        $this->model->create($this->adminId, 'ff_batch', hash('sha256', '000000'), null, 120);
        $this->model->delete($this->adminId, 'ff_batch');

        $this->assertNull($this->model->find($this->adminId, 'ff_batch'));
    }

    public function testDeleteIsIdempotentWhenRowAbsent(): void
    {
        // Ne doit pas lever d'exception
        $this->model->delete($this->adminId, 'ff_batch');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // purgeExpired()
    // ----------------------------------------------------------------

    public function testPurgeExpiredDeletesOnlyExpiredRows(): void
    {
        $adminId2 = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'super_admin', 'fr', NOW())",
            ['otp-admin2-' . bin2hex(random_bytes(4)) . '@test.local', password_hash('Admin123!', PASSWORD_BCRYPT)]
        );

        // OTP valide
        $this->model->create($this->adminId, 'ff_batch', hash('sha256', '111111'), null, 120);
        // OTP expiré
        $this->model->create($adminId2, 'ff_batch', hash('sha256', '222222'), null, -1);

        $purged = $this->model->purgeExpired();

        $this->assertGreaterThanOrEqual(1, $purged);
        $this->assertNotNull($this->model->find($this->adminId, 'ff_batch'));
        $this->assertNull($this->model->find($adminId2, 'ff_batch'));
    }

    public function testPurgeExpiredReturnsZeroWhenNoneExpired(): void
    {
        $this->model->create($this->adminId, 'ff_batch', hash('sha256', '111111'), null, 120);

        $purged = $this->model->purgeExpired();

        $this->assertSame(0, $purged);
    }
}
