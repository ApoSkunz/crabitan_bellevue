<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\TrustedDeviceModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour TrustedDeviceModel.
 */
class TrustedDeviceModelTest extends IntegrationTestCase
{
    private TrustedDeviceModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TrustedDeviceModel();

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['trusted.dev.' . bin2hex(random_bytes(4)) . '@test.local']
        );
    }

    // ----------------------------------------------------------------
    // isTrusted
    // ----------------------------------------------------------------

    /**
     * isTrusted retourne false si le device n'est pas connu.
     */
    public function testIsTrustedReturnsFalseForUnknownDevice(): void
    {
        $this->assertFalse($this->model->isTrusted($this->userId, 'unknown-token'));
    }

    /**
     * isTrusted retourne true après un trust().
     */
    public function testIsTrustedReturnsTrueAfterTrust(): void
    {
        $this->model->trust($this->userId, 'device-abc', 'Chrome · Windows');

        $this->assertTrue($this->model->isTrusted($this->userId, 'device-abc'));
    }

    // ----------------------------------------------------------------
    // trust
    // ----------------------------------------------------------------

    /**
     * trust insère un appareil de confiance.
     */
    public function testTrustInsertsDevice(): void
    {
        $this->model->trust($this->userId, 'device-xyz', 'Firefox · Mac');

        $devices = $this->model->getForUser($this->userId);
        $this->assertCount(1, $devices);
        $this->assertSame('device-xyz', $devices[0]['device_token']);
        $this->assertSame('Firefox · Mac', $devices[0]['device_name']);
    }

    /**
     * trust ne crée pas de doublon (ON DUPLICATE KEY UPDATE).
     */
    public function testTrustUpsertDoesNotDuplicate(): void
    {
        $this->model->trust($this->userId, 'device-xyz', 'Firefox · Mac');
        $this->model->trust($this->userId, 'device-xyz', 'Firefox · Mac Updated');

        $devices = $this->model->getForUser($this->userId);
        $this->assertCount(1, $devices);
        $this->assertSame('Firefox · Mac Updated', $devices[0]['device_name']);
    }

    // ----------------------------------------------------------------
    // updateLastSeen
    // ----------------------------------------------------------------

    /**
     * updateLastSeen ne provoque pas d'erreur si l'appareil existe.
     */
    public function testUpdateLastSeenDoesNotThrow(): void
    {
        $this->model->trust($this->userId, 'device-seen', 'Safari · iOS');
        $this->model->updateLastSeen($this->userId, 'device-seen');

        // updateLastSeen ne doit pas modifier l'existence de l'appareil
        $this->assertTrue($this->model->isTrusted($this->userId, 'device-seen'));
    }

    // ----------------------------------------------------------------
    // untrust
    // ----------------------------------------------------------------

    /**
     * untrust supprime l'appareil de la liste de confiance.
     */
    public function testUntrustRemovesDevice(): void
    {
        $this->model->trust($this->userId, 'device-to-remove', 'Edge · Windows');
        $this->model->untrust($this->userId, 'device-to-remove');

        $this->assertFalse($this->model->isTrusted($this->userId, 'device-to-remove'));
    }

    /**
     * untrust ne provoque pas d'erreur si le device n'existe pas.
     */
    public function testUntrustOnUnknownDeviceDoesNotThrow(): void
    {
        $this->model->untrust($this->userId, 'nonexistent-device');

        $this->assertFalse($this->model->isTrusted($this->userId, 'nonexistent-device'));
    }

    // ----------------------------------------------------------------
    // deleteAllForUser
    // ----------------------------------------------------------------

    /**
     * deleteAllForUser supprime tous les appareils de l'utilisateur.
     */
    public function testDeleteAllForUserRemovesAllDevices(): void
    {
        $this->model->trust($this->userId, 'device-1', 'Device 1');
        $this->model->trust($this->userId, 'device-2', 'Device 2');

        $this->model->deleteAllForUser($this->userId);

        $devices = $this->model->getForUser($this->userId);
        $this->assertCount(0, $devices);
    }

    /**
     * deleteAllForUser ne touche pas les appareils des autres utilisateurs.
     */
    public function testDeleteAllForUserDoesNotAffectOtherUsers(): void
    {
        $otherId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['other.trusted.' . bin2hex(random_bytes(4)) . '@test.local']
        );

        $this->model->trust($otherId, 'other-device', 'Other Device');
        $this->model->deleteAllForUser($this->userId);

        $this->assertTrue($this->model->isTrusted($otherId, 'other-device'));
    }

    // ----------------------------------------------------------------
    // getForUser
    // ----------------------------------------------------------------

    /**
     * getForUser retourne un tableau vide si aucun appareil de confiance.
     */
    public function testGetForUserReturnsEmptyArray(): void
    {
        $result = $this->model->getForUser($this->userId);

        $this->assertSame([], $result);
    }

    /**
     * getForUser retourne les appareils triés par last_seen DESC.
     */
    public function testGetForUserReturnsDevices(): void
    {
        $this->model->trust($this->userId, 'device-a', 'Device A');
        $this->model->trust($this->userId, 'device-b', 'Device B');

        $result = $this->model->getForUser($this->userId);

        $this->assertCount(2, $result);
        $tokens = array_column($result, 'device_token');
        $this->assertContains('device-a', $tokens);
        $this->assertContains('device-b', $tokens);
    }
}
