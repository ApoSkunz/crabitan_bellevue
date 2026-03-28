<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\DeviceConfirmTokenModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour DeviceConfirmTokenModel.
 * Couvre : create, findByToken, confirm, findConfirmedByToken, deleteByToken, purgeExpired.
 */
class DeviceConfirmTokenModelTest extends IntegrationTestCase
{
    private DeviceConfirmTokenModel $model;
    private int $userId;

    /**
     * Crée un utilisateur de test avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new DeviceConfirmTokenModel();

        $accountModel = new AccountModel();
        $this->userId = (int) $accountModel->create(
            'individual',
            'dct_test@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'DCT',
            'User',
            ''
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Insère un token directement en BDD avec une expiration configurable.
     *
     * @param string $token        Valeur du token
     * @param bool   $expired      Si true, expires_at est dans le passé
     * @param bool   $confirmed    Si true, confirmed_at est renseigné
     */
    private function insertToken(
        string $token,
        bool $expired = false,
        bool $confirmed = false
    ): void {
        $expiresExpr   = $expired   ? 'DATE_SUB(NOW(), INTERVAL 60 SECOND)'  : 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)';
        $confirmedExpr = $confirmed ? 'NOW()'                                  : 'NULL';

        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, redirect_url, lang, expires_at, confirmed_at)
             VALUES (?, ?, ?, ?, ?, ?, {$expiresExpr}, {$confirmedExpr})",
            [
                $this->userId,
                'device-uuid-' . bin2hex(random_bytes(4)),
                'Chrome · Windows',
                $token,
                '/dashboard',
                'fr',
            ]
        );
    }

    // ------------------------------------------------------------------
    // create
    // ------------------------------------------------------------------

    /**
     * create insère un nouveau token de confirmation en BDD.
     */
    public function testCreateInsertsToken(): void
    {
        $token       = bin2hex(random_bytes(32));
        $deviceToken = bin2hex(random_bytes(16));

        $this->model->create($this->userId, $deviceToken, 'Chrome · Windows', $token, '/home', 'fr');

        $row = self::$db->fetchOne(
            "SELECT * FROM device_confirm_tokens WHERE token = ?",
            [$token]
        );

        $this->assertIsArray($row);
        $this->assertSame($this->userId, (int) $row['user_id']);
        $this->assertSame($deviceToken, $row['device_token']);
        $this->assertSame('Chrome · Windows', $row['device_name']);
        $this->assertSame('/home', $row['redirect_url']);
        $this->assertSame('fr', $row['lang']);
        $this->assertNull($row['confirmed_at']);
    }

    /**
     * create est idempotent : un second appel avec le même user+device remplace le token.
     */
    public function testCreateReplacesExistingTokenForSameUserDevice(): void
    {
        $deviceToken = bin2hex(random_bytes(16));
        $token1      = bin2hex(random_bytes(32));
        $token2      = bin2hex(random_bytes(32));

        $this->model->create($this->userId, $deviceToken, null, $token1, '/page1', 'fr');
        $this->model->create($this->userId, $deviceToken, null, $token2, '/page2', 'en');

        // token1 ne doit plus exister
        $old = self::$db->fetchOne(
            "SELECT id FROM device_confirm_tokens WHERE token = ?",
            [$token1]
        );
        $this->assertFalse($old);

        // token2 doit être présent
        $new = self::$db->fetchOne(
            "SELECT * FROM device_confirm_tokens WHERE token = ?",
            [$token2]
        );
        $this->assertIsArray($new);
        $this->assertSame('/page2', $new['redirect_url']);
    }

    /**
     * create accepte device_name null.
     */
    public function testCreateAcceptsNullDeviceName(): void
    {
        $token       = bin2hex(random_bytes(32));
        $deviceToken = bin2hex(random_bytes(16));

        $this->model->create($this->userId, $deviceToken, null, $token, '/', 'en');

        $row = self::$db->fetchOne(
            "SELECT device_name FROM device_confirm_tokens WHERE token = ?",
            [$token]
        );
        $this->assertIsArray($row);
        $this->assertNull($row['device_name']);
    }

    // ------------------------------------------------------------------
    // findByToken
    // ------------------------------------------------------------------

    /**
     * findByToken retourne la ligne pour un token valide non expiré.
     */
    public function testFindByTokenReturnsRowForValidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token);

        $row = $this->model->findByToken($token);

        $this->assertIsArray($row);
        $this->assertSame($token, $row['token']);
    }

    /**
     * findByToken retourne false pour un token inexistant.
     */
    public function testFindByTokenReturnsFalseForUnknownToken(): void
    {
        $this->assertFalse($this->model->findByToken('unknown-token'));
    }

    /**
     * findByToken retourne false pour un token expiré.
     */
    public function testFindByTokenReturnsFalseForExpiredToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token, expired: true);

        $this->assertFalse($this->model->findByToken($token));
    }

    // ------------------------------------------------------------------
    // confirm
    // ------------------------------------------------------------------

    /**
     * confirm retourne true et remplit confirmed_at pour un token valide.
     */
    public function testConfirmReturnsTrueAndSetsConfirmedAt(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token);

        $result = $this->model->confirm($token);

        $this->assertTrue($result);

        $row = self::$db->fetchOne(
            "SELECT confirmed_at FROM device_confirm_tokens WHERE token = ?",
            [$token]
        );
        $this->assertIsArray($row);
        $this->assertNotNull($row['confirmed_at']);
    }

    /**
     * confirm retourne false pour un token inexistant.
     */
    public function testConfirmReturnsFalseForUnknownToken(): void
    {
        $this->assertFalse($this->model->confirm('nonexistent-token'));
    }

    /**
     * confirm retourne false pour un token déjà expiré.
     */
    public function testConfirmReturnsFalseForExpiredToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token, expired: true);

        $this->assertFalse($this->model->confirm($token));
    }

    /**
     * confirm retourne false pour un token déjà confirmé (idempotence protégée).
     */
    public function testConfirmReturnsFalseWhenAlreadyConfirmed(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token, confirmed: true);

        $this->assertFalse($this->model->confirm($token));
    }

    // ------------------------------------------------------------------
    // findConfirmedByToken
    // ------------------------------------------------------------------

    /**
     * findConfirmedByToken retourne false si le token n'a pas été confirmé.
     */
    public function testFindConfirmedByTokenReturnsFalseWhenNotConfirmed(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token);

        $this->assertFalse($this->model->findConfirmedByToken($token));
    }

    /**
     * findConfirmedByToken retourne la ligne pour un token confirmé et non expiré.
     */
    public function testFindConfirmedByTokenReturnsRowWhenConfirmed(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token, confirmed: true);

        $row = $this->model->findConfirmedByToken($token);

        $this->assertIsArray($row);
        $this->assertSame($token, $row['token']);
        $this->assertNotNull($row['confirmed_at']);
    }

    /**
     * findConfirmedByToken retourne false pour un token confirmé mais expiré.
     */
    public function testFindConfirmedByTokenReturnsFalseWhenExpired(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token, expired: true, confirmed: true);

        $this->assertFalse($this->model->findConfirmedByToken($token));
    }

    /**
     * findConfirmedByToken retourne false pour un token inconnu.
     */
    public function testFindConfirmedByTokenReturnsFalseForUnknownToken(): void
    {
        $this->assertFalse($this->model->findConfirmedByToken('no-such-token'));
    }

    // ------------------------------------------------------------------
    // deleteByToken
    // ------------------------------------------------------------------

    /**
     * deleteByToken supprime le token en BDD.
     */
    public function testDeleteByTokenRemovesRow(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token);

        $this->model->deleteByToken($token);

        $row = self::$db->fetchOne(
            "SELECT id FROM device_confirm_tokens WHERE token = ?",
            [$token]
        );
        $this->assertFalse($row);
    }

    /**
     * deleteByToken sur un token inexistant ne lève pas d'exception.
     */
    public function testDeleteByTokenOnMissingTokenDoesNotFail(): void
    {
        $this->expectNotToPerformAssertions();
        $this->model->deleteByToken('ghost-token');
    }

    // ------------------------------------------------------------------
    // purgeExpired
    // ------------------------------------------------------------------

    /**
     * purgeExpired supprime uniquement les tokens expirés et retourne le nombre supprimé.
     */
    public function testPurgeExpiredDeletesOnlyExpiredTokens(): void
    {
        $validToken   = bin2hex(random_bytes(32));
        $expiredToken = bin2hex(random_bytes(32));

        $this->insertToken($validToken);
        $this->insertToken($expiredToken, expired: true);

        $deleted = $this->model->purgeExpired();

        $this->assertGreaterThanOrEqual(1, $deleted);

        // Token valide toujours présent
        $this->assertIsArray(
            self::$db->fetchOne("SELECT id FROM device_confirm_tokens WHERE token = ?", [$validToken])
        );

        // Token expiré supprimé
        $this->assertFalse(
            self::$db->fetchOne("SELECT id FROM device_confirm_tokens WHERE token = ?", [$expiredToken])
        );
    }

    /**
     * purgeExpired retourne 0 quand il n'y a aucun token expiré.
     */
    public function testPurgeExpiredReturnsZeroWhenNothingToDelete(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->insertToken($token);

        $deleted = $this->model->purgeExpired();

        $this->assertSame(0, $deleted);
    }
}
