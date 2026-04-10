<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Database;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\TokenPurgeService;

/**
 * Vérifie que TokenPurgeService purge uniquement les tokens/sessions expirés.
 *
 * Les requêtes SQL sont observées via un mock de Database pour confirmer
 * que les clauses WHERE incluent bien une période de grâce et excluent
 * les sessions actives.
 */
#[AllowMockObjectsWithoutExpectations]
class TokenPurgeServiceTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Crée un service avec un mock Database qui capture les SQL exécutés.
     * Les résultats sont retournés via un objet stdClass partagé.
     *
     * @param array<string,int> $returnMap fragment SQL → rowCount
     * @return array{service: TokenPurgeService, ctx: \stdClass}
     */
    private function makeService(array $returnMap = []): array
    {
        $ctx = new \stdClass();
        $ctx->calls = [];

        $db = $this->createMock(Database::class);
        $db->method('execute')
            ->willReturnCallback(static function (string $sql, array $params) use ($ctx, $returnMap): int {
                $ctx->calls[] = $sql;
                foreach ($returnMap as $fragment => $count) {
                    if (str_contains($sql, $fragment)) {
                        return $count;
                    }
                }
                return 0;
            });

        $this->instanceProp->setValue(null, $db);

        return ['service' => new TokenPurgeService($db), 'ctx' => $ctx];
    }

    // -----------------------------------------------------------------------
    // purgeAll
    // -----------------------------------------------------------------------

    public function testPurgeAllReturnsFourKeys(): void
    {
        ['service' => $service] = $this->makeService();
        $result = $service->purgeAll();

        $this->assertArrayHasKey('password_reset', $result);
        $this->assertArrayHasKey('device_confirm_tokens', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('email_verification', $result);
    }

    public function testPurgeAllReturnsCounts(): void
    {
        ['service' => $service] = $this->makeService([
            'password_reset'        => 2,
            'device_confirm_tokens' => 3,
            'connections'           => 1,
        ]);

        $result = $service->purgeAll();
        $this->assertSame(2, $result['password_reset']);
        $this->assertSame(3, $result['device_confirm_tokens']);
        $this->assertSame(1, $result['connections']);
    }

    // -----------------------------------------------------------------------
    // Clauses WHERE — tokens courts
    // -----------------------------------------------------------------------

    public function testPurgePasswordResetUsesGracePeriod(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgePasswordReset();

        $this->assertCount(1, $ctx->calls);
        $this->assertStringContainsString('password_reset', $ctx->calls[0]);
        $this->assertStringContainsString('INTERVAL', $ctx->calls[0]);
    }

    public function testPurgeDeviceConfirmTokensUsesGracePeriod(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgeDeviceConfirmTokens();

        $this->assertCount(1, $ctx->calls);
        $this->assertStringContainsString('device_confirm_tokens', $ctx->calls[0]);
        $this->assertStringContainsString('INTERVAL', $ctx->calls[0]);
    }

    // -----------------------------------------------------------------------
    // Connections — sessions actives exclues
    // -----------------------------------------------------------------------

    public function testPurgeConnectionsExcludesActiveStatus(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgeConnections();

        $this->assertCount(1, $ctx->calls);
        $sql = $ctx->calls[0];

        // WHERE status != 'active' ET période de grâce
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('INTERVAL', $sql);
    }

    public function testPurgeConnectionsTargetsExpiredAtColumn(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgeConnections();

        $this->assertStringContainsString('connections', $ctx->calls[0]);
        $this->assertStringContainsString('expired_at', $ctx->calls[0]);
    }

    // -----------------------------------------------------------------------
    // Email verification — comptes vérifiés préservés
    // -----------------------------------------------------------------------

    public function testPurgeEmailVerificationPreservesVerifiedAccounts(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgeEmailVerificationTokens();

        $this->assertCount(1, $ctx->calls);
        $sql = $ctx->calls[0];

        // Ne touche pas aux comptes vérifiés
        $this->assertStringContainsString('email_verified_at', $sql);
        $this->assertStringContainsString('IS NULL', $sql);
        $this->assertStringContainsString('INTERVAL', $sql);
    }

    public function testPurgeEmailVerificationIsUpdateNotDelete(): void
    {
        ['service' => $service, 'ctx' => $ctx] = $this->makeService();
        $service->purgeEmailVerificationTokens();

        // Anonymisation = UPDATE (mise à NULL), pas DELETE du compte
        $this->assertStringStartsWith('UPDATE', trim($ctx->calls[0]));
    }
}
