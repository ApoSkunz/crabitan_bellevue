<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Model\ConnectionModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ConnectionModel::refreshExpiry().
 *
 * Ces tests vérifient le comportement de la méthode sans interaction BDD réelle.
 * Ils s'appuient sur la BDD si disponible (skip automatique sinon).
 */
class ConnectionModelRefreshExpiryTest extends TestCase
{
    private ConnectionModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new ConnectionModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    /**
     * refreshExpiry() retourne false pour un token inexistant.
     */
    public function testRefreshExpiryReturnsFalseForUnknownToken(): void
    {
        $result = $this->model->refreshExpiry('token-qui-nexiste-pas', 3600);
        $this->assertFalse($result);
    }

    /**
     * refreshExpiry() retourne un booléen.
     */
    public function testRefreshExpiryReturnsBool(): void
    {
        $result = $this->model->refreshExpiry('any-token', 3600);
        $this->assertIsBool($result);
    }
}
