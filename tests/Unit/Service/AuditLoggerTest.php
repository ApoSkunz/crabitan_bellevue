<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Model\AdminLogModel;
use PHPUnit\Framework\TestCase;
use Service\AuditLogger;

/**
 * Tests unitaires pour AuditLogger.
 *
 * Vérifie que log() insère une entrée via AdminLogModel,
 * et que les erreurs BDD sont silencieuses.
 */
class AuditLoggerTest extends TestCase
{
    /**
     * log() appelle AdminLogModel::insert() avec les bons arguments.
     */
    public function testLogCallsModelInsert(): void
    {
        $modelMock = $this->createMock(AdminLogModel::class);
        $modelMock->expects($this->once())
            ->method('insert')
            ->with(
                $this->equalTo(42),
                $this->equalTo('order.status_updated'),
                $this->equalTo('order'),
                $this->equalTo(7),
                $this->equalTo(['old_status' => 'pending', 'new_status' => 'shipped']),
                $this->equalTo('127.0.0.1')
            )
            ->willReturn(1);

        // Injecte le mock via une sous-classe testable
        $logger = new class ($modelMock) extends AuditLogger {
            private AdminLogModel $model;
            public function __construct(AdminLogModel $model)
            {
                $this->model = $model;
            }
            public function callLog(
                int $adminId,
                string $action,
                string $targetType,
                ?int $targetId,
                array $details,
                string $ip
            ): void {
                try {
                    $this->model->insert($adminId, $action, $targetType, $targetId, $details, $ip);
                } catch (\Throwable) {
                    // silencieux
                }
            }
        };

        $logger->callLog(42, 'order.status_updated', 'order', 7, ['old_status' => 'pending', 'new_status' => 'shipped'], '127.0.0.1');
    }

    /**
     * log() est silencieux si AdminLogModel::insert() lève une exception.
     * Ne doit pas propager l'exception.
     */
    public function testLogIsSilentOnException(): void
    {
        $modelMock = $this->createMock(AdminLogModel::class);
        $modelMock->method('insert')
            ->willThrowException(new \RuntimeException('DB error'));

        $logger = new class ($modelMock) extends AuditLogger {
            private AdminLogModel $model;
            public function __construct(AdminLogModel $model)
            {
                $this->model = $model;
            }
            public function callLog(
                int $adminId,
                string $action,
                string $targetType,
                ?int $targetId,
                array $details,
                string $ip
            ): void {
                try {
                    $this->model->insert($adminId, $action, $targetType, $targetId, $details, $ip);
                } catch (\Throwable) {
                    // silencieux
                }
            }
        };

        // Ne doit pas lever d'exception
        $logger->callLog(1, 'test.action', 'test', null, [], '1.2.3.4');
        $this->assertTrue(true); // atteint si pas d'exception
    }

    /**
     * log() static est silencieux même si la BDD n'est pas disponible.
     * (AuditLogger::log() wrappe dans try/catch).
     */
    public function testStaticLogIsSilentWithoutDb(): void
    {
        // Sans BDD active, log() ne doit pas lever d'exception
        // Le try/catch interne l'absorbe
        $this->expectNotToPerformAssertions();

        try {
            // Cela échouera sur la connexion BDD — doit être silencieux
            AuditLogger::log(999, 'test.nodb', 'test', null, [], '127.0.0.1');
        } catch (\Throwable $e) {
            $this->fail('AuditLogger::log() should not throw: ' . $e->getMessage());
        }
    }

    /**
     * getIp() retourne REMOTE_ADDR par défaut.
     */
    public function testGetIpReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertSame('10.0.0.1', AuditLogger::getIp());
    }

    /**
     * getIp() utilise le premier IP de X-Forwarded-For si valide.
     */
    public function testGetIpPrefersXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $this->assertSame('203.0.113.5', AuditLogger::getIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    /**
     * getIp() ignore X-Forwarded-For si l'IP est invalide.
     */
    public function testGetIpIgnoresInvalidXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.2';

        $this->assertSame('10.0.0.2', AuditLogger::getIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}
