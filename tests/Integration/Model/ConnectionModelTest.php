<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\ConnectionModel;
use Tests\Integration\IntegrationTestCase;

class ConnectionModelTest extends IntegrationTestCase
{
    private ConnectionModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ConnectionModel();

        $accountModel = new AccountModel();
        $this->userId = (int)$accountModel->create(
            'individual',
            'conn@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Conn',
            'User',
            ''
        );
    }

    public function testCreateConnection(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, 'Mozilla/5.0', null, 'password', 3600);

        $row = self::$db->fetchOne(
            "SELECT * FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertIsArray($row);
        $this->assertSame('active', $row['status']);
        $this->assertSame($this->userId, (int)$row['user_id']);
    }

    public function testRevokeConnection(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, 'Mozilla/5.0', null, 'password', 3600);
        $this->model->revoke($token);

        $row = self::$db->fetchOne(
            "SELECT * FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertIsArray($row);
        $this->assertSame('revoked', $row['status']);
    }

    public function testRevokeNonExistentTokenDoesNotFail(): void
    {
        $this->expectNotToPerformAssertions();
        $this->model->revoke('nonexistent-token');
    }

    public function testMultipleConnectionsForSameUser(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->model->create($this->userId, $token1, null, null, 'Chrome', null, 'password', 3600);
        $this->model->create($this->userId, $token2, null, null, 'Firefox', null, 'password', 3600);

        $rows = self::$db->fetchAll(
            "SELECT * FROM connections WHERE user_id = ?",
            [$this->userId]
        );

        $this->assertCount(2, $rows);
    }

    // ------------------------------------------------------------------
    // getActiveForUser
    // ------------------------------------------------------------------

    /**
     * getActiveForUser retourne uniquement les connexions actives non expirées.
     */
    public function testGetActiveForUserReturnsActiveConnections(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, 'device-1', '127.0.0.1', 'Mozilla/5.0', 'Chrome · Win', 'password', 86400);

        $rows = $this->model->getActiveForUser($this->userId);

        $this->assertCount(1, $rows);
        $this->assertSame($token, $rows[0]['token']);
    }

    /**
     * getActiveForUser exclut les connexions révoquées.
     */
    public function testGetActiveForUserExcludesRevokedConnections(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);
        $this->model->revoke($token);

        $rows = $this->model->getActiveForUser($this->userId);

        $this->assertCount(0, $rows);
    }

    /**
     * getActiveForUser exclut les connexions expirées (expired_at dans le passé).
     */
    public function testGetActiveForUserExcludesExpiredConnections(): void
    {
        $token = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_SUB(NOW(), INTERVAL 1 HOUR))",
            [$this->userId, $token]
        );

        $rows = $this->model->getActiveForUser($this->userId);

        $this->assertCount(0, $rows);
    }

    /**
     * getActiveForUser retourne un tableau vide quand l'utilisateur n'a aucune connexion.
     */
    public function testGetActiveForUserReturnsEmptyArrayWhenNoConnections(): void
    {
        $this->assertSame([], $this->model->getActiveForUser($this->userId));
    }

    // ------------------------------------------------------------------
    // getTokenById
    // ------------------------------------------------------------------

    /**
     * getTokenById retourne le token correspondant à l'id + user_id.
     */
    public function testGetTokenByIdReturnsTokenForValidIdAndUser(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $row = self::$db->fetchOne(
            "SELECT id FROM connections WHERE token = ?",
            [$token]
        );
        $id = (int) $row['id'];

        $result = $this->model->getTokenById($id, $this->userId);

        $this->assertSame($token, $result);
    }

    /**
     * getTokenById retourne null quand l'id n'appartient pas à l'utilisateur.
     */
    public function testGetTokenByIdReturnsNullForWrongUser(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $row = self::$db->fetchOne(
            "SELECT id FROM connections WHERE token = ?",
            [$token]
        );
        $id = (int) $row['id'];

        $this->assertNull($this->model->getTokenById($id, $this->userId + 9999));
    }

    /**
     * getTokenById retourne null pour un id inexistant.
     */
    public function testGetTokenByIdReturnsNullForUnknownId(): void
    {
        $this->assertNull($this->model->getTokenById(999999, $this->userId));
    }

    // ------------------------------------------------------------------
    // getLatestByDeviceToken
    // ------------------------------------------------------------------

    /**
     * getLatestByDeviceToken retourne les infos de la dernière connexion pour ce device.
     */
    public function testGetLatestByDeviceTokenReturnsRowForKnownDevice(): void
    {
        $deviceToken = bin2hex(random_bytes(16));
        $this->model->create($this->userId, bin2hex(random_bytes(32)), $deviceToken, null, null, 'Safari · macOS', 'password', 3600);

        $row = $this->model->getLatestByDeviceToken($this->userId, $deviceToken);

        $this->assertIsArray($row);
        $this->assertSame($deviceToken, $row['device_token']);
        $this->assertSame('Safari · macOS', $row['device_name']);
    }

    /**
     * getLatestByDeviceToken retourne null pour un device inconnu.
     */
    public function testGetLatestByDeviceTokenReturnsNullForUnknownDevice(): void
    {
        $this->assertNull($this->model->getLatestByDeviceToken($this->userId, 'unknown-device-uuid'));
    }

    // ------------------------------------------------------------------
    // revokeById
    // ------------------------------------------------------------------

    /**
     * revokeById passe le statut en revoked pour l'id + user_id correspondants.
     */
    public function testRevokeByIdRevokesCorrectConnection(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $row = self::$db->fetchOne("SELECT id FROM connections WHERE token = ?", [$token]);
        $id  = (int) $row['id'];

        $this->model->revokeById($id, $this->userId);

        $updated = self::$db->fetchOne("SELECT status FROM connections WHERE id = ?", [$id]);
        $this->assertSame('revoked', $updated['status']);
    }

    /**
     * revokeById ne modifie pas une connexion appartenant à un autre utilisateur.
     */
    public function testRevokeByIdDoesNotAffectOtherUsers(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $row = self::$db->fetchOne("SELECT id FROM connections WHERE token = ?", [$token]);
        $id  = (int) $row['id'];

        $this->model->revokeById($id, $this->userId + 9999);

        $unchanged = self::$db->fetchOne("SELECT status FROM connections WHERE id = ?", [$id]);
        $this->assertSame('active', $unchanged['status']);
    }

    // ------------------------------------------------------------------
    // isKnownDevice
    // ------------------------------------------------------------------

    /**
     * isKnownDevice retourne true si le device_token a déjà été utilisé par cet utilisateur.
     */
    public function testIsKnownDeviceReturnsTrueForKnownDevice(): void
    {
        $deviceToken = bin2hex(random_bytes(16));
        $this->model->create($this->userId, bin2hex(random_bytes(32)), $deviceToken, null, null, null, 'password', 3600);

        $this->assertTrue($this->model->isKnownDevice($this->userId, $deviceToken));
    }

    /**
     * isKnownDevice retourne false pour un device inconnu.
     */
    public function testIsKnownDeviceReturnsFalseForUnknownDevice(): void
    {
        $this->assertFalse($this->model->isKnownDevice($this->userId, 'never-seen-uuid'));
    }

    /**
     * isKnownDevice retourne true même si la session est révoquée.
     */
    public function testIsKnownDeviceReturnsTrueEvenWhenRevoked(): void
    {
        $deviceToken = bin2hex(random_bytes(16));
        $token       = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, $deviceToken, null, null, null, 'password', 3600);
        $this->model->revoke($token);

        $this->assertTrue($this->model->isKnownDevice($this->userId, $deviceToken));
    }

    // ------------------------------------------------------------------
    // hasAnyConnection
    // ------------------------------------------------------------------

    /**
     * hasAnyConnection retourne false quand l'utilisateur n'a aucune connexion.
     */
    public function testHasAnyConnectionReturnsFalseWhenNoConnections(): void
    {
        $this->assertFalse($this->model->hasAnyConnection($this->userId));
    }

    /**
     * hasAnyConnection retourne true dès qu'une connexion existe (peu importe le statut).
     */
    public function testHasAnyConnectionReturnsTrueAfterCreate(): void
    {
        $this->model->create($this->userId, bin2hex(random_bytes(32)), null, null, null, null, 'password', 3600);

        $this->assertTrue($this->model->hasAnyConnection($this->userId));
    }

    /**
     * hasAnyConnection retourne true même si toutes les connexions sont révoquées.
     */
    public function testHasAnyConnectionReturnsTrueEvenWhenRevoked(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);
        $this->model->revoke($token);

        $this->assertTrue($this->model->hasAnyConnection($this->userId));
    }
}
