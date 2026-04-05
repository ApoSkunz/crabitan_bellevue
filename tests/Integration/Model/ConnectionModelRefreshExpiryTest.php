<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\ConnectionModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour ConnectionModel::refreshExpiry().
 *
 * Vérifie que expired_at est prolongé sur une session active, que les sessions
 * révoquées et expirées ne sont pas affectées, et que la méthode retourne
 * correctement true/false selon l'état de la ligne.
 */
class ConnectionModelRefreshExpiryTest extends IntegrationTestCase
{
    private ConnectionModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ConnectionModel();

        $accountModel = new AccountModel();
        $this->userId = (int) $accountModel->create(
            'individual',
            'refresh-ttl@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Refresh',
            'TtlTest',
            ''
        );
    }

    // ------------------------------------------------------------------
    // Cas nominal : refresh déclenché sur session active non expirée
    // ------------------------------------------------------------------

    /**
     * refreshExpiry() retourne true et met à jour expired_at pour un token actif.
     */
    public function testRefreshExpiryUpdatesExpiredAtForActiveToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $before = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE token = ?",
            [$token]
        );

        // On attend 1 seconde pour garantir que la nouvelle date est différente
        sleep(1);

        $result = $this->model->refreshExpiry($token, 7200);

        $this->assertTrue($result);

        $after = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertGreaterThan($before['expired_at'], $after['expired_at']);
    }

    /**
     * refreshExpiry() prolonge effectivement expired_at d'environ $expiry secondes.
     */
    public function testRefreshExpiryExtendsExpiredAtByExpiry(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $expiry = 7200;
        $before = time();
        $this->model->refreshExpiry($token, $expiry);

        $row = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE token = ?",
            [$token]
        );

        $newExpiredAt = strtotime($row['expired_at']);
        // La nouvelle date doit être dans une fenêtre raisonnable autour de now + expiry
        $this->assertGreaterThanOrEqual($before + $expiry - 2, $newExpiredAt);
        $this->assertLessThanOrEqual($before + $expiry + 2, $newExpiredAt);
    }

    // ------------------------------------------------------------------
    // Cas : token révoqué — ne doit PAS être mis à jour
    // ------------------------------------------------------------------

    /**
     * refreshExpiry() retourne false et ne modifie pas expired_at d'un token révoqué.
     */
    public function testRefreshExpiryReturnsFalseForRevokedToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);
        $this->model->revoke($token);

        $before = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE token = ?",
            [$token]
        );

        $result = $this->model->refreshExpiry($token, 7200);

        $this->assertFalse($result);

        $after = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertSame($before['expired_at'], $after['expired_at']);
    }

    // ------------------------------------------------------------------
    // Cas : token expiré (expired_at dans le passé) — ne doit PAS être mis à jour
    // ------------------------------------------------------------------

    /**
     * refreshExpiry() retourne false pour un token déjà expiré en BDD.
     */
    public function testRefreshExpiryReturnsFalseForExpiredToken(): void
    {
        $token = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_SUB(NOW(), INTERVAL 1 HOUR))",
            [$this->userId, $token]
        );

        $result = $this->model->refreshExpiry($token, 7200);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // Cas : token inexistant
    // ------------------------------------------------------------------

    /**
     * refreshExpiry() retourne false pour un token inexistant.
     */
    public function testRefreshExpiryReturnsFalseForUnknownToken(): void
    {
        $result = $this->model->refreshExpiry('token-qui-nexiste-pas', 3600);
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // getRecentForUser
    // ------------------------------------------------------------------

    /**
     * Retourne les connexions de l'utilisateur dans la fenêtre temporelle demandée.
     */
    public function testGetRecentForUserReturnsConnectionsWithinWindow(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, null, null, null, null, 'password', 3600);

        $rows = $this->model->getRecentForUser($this->userId, 1);

        $tokens = array_column($rows, 'token');
        $this->assertNotEmpty($rows, 'Au moins une connexion doit être retournée');
        // Le token JWT ne doit PAS figurer dans les colonnes retournées (minimisation RGPD)
        $this->assertFalse(in_array('token', array_keys($rows[0] ?? []), true), 'La colonne token ne doit pas être exposée');
    }

    /**
     * N'inclut pas les connexions plus anciennes que la fenêtre demandée.
     */
    public function testGetRecentForUserExcludesOldConnections(): void
    {
        $oldToken = bin2hex(random_bytes(32));
        self::$db->execute(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at, created_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 100 DAY))",
            [$this->userId, $oldToken]
        );

        $rows = $this->model->getRecentForUser($this->userId, 90);

        $found = array_filter($rows, fn($r) => ($r['auth_method'] ?? '') === 'password'
            && str_contains($r['status'] ?? '', 'active'));
        // La connexion de 100 jours ne doit pas apparaître dans une fenêtre de 90 jours
        $this->assertEmpty(
            array_filter($rows, function ($r) use ($oldToken) {
                // On identifie la vieille connexion via son device_name (null) et la date
                return isset($r['created_at']) && strtotime($r['created_at']) < strtotime('-91 days');
            }),
            'Les connexions hors fenêtre ne doivent pas être retournées'
        );
    }

    /**
     * Retourne un tableau vide pour un utilisateur sans connexion dans la fenêtre.
     */
    public function testGetRecentForUserReturnsEmptyForNewUser(): void
    {
        $accountModel = new AccountModel();
        $newUserId = (int) $accountModel->create(
            'individual',
            'no-connections@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Empty',
            'User',
            ''
        );

        $rows = $this->model->getRecentForUser($newUserId, 90);

        $this->assertSame([], $rows);
    }
}
