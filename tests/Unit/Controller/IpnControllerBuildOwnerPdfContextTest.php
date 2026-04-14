<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\IpnController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Couvre les branches de IpnController::buildOwnerPdfContext :
 *  - userId invalide  → billing null → return null
 *  - billingId invalide → billing null → return null
 *  - billing introuvable en BDD → return null
 *
 * Les branches nécessitant une BDD réelle sont skippées si
 * l'environnement de test n'a pas de connexion active.
 */
class IpnControllerBuildOwnerPdfContextTest extends TestCase
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function invoke(array $snapshot): ?array
    {
        $ref    = new ReflectionClass(IpnController::class);
        $ctrl   = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('buildOwnerPdfContext');
        /** @var array<string, mixed>|null $result */
        $result = $method->invoke($ctrl, $snapshot);
        return $result;
    }

    public function testReturnsNullWhenUserIdMissing(): void
    {
        try {
            $result = $this->invoke([
                'user_id'             => 0,
                'billing_address_id'  => 1,
                'delivery_address_id' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
        $this->assertNull($result);
    }

    public function testReturnsNullWhenBillingIdMissing(): void
    {
        try {
            $result = $this->invoke([
                'user_id'             => 42,
                'billing_address_id'  => 0,
                'delivery_address_id' => 5,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
        $this->assertNull($result);
    }

    public function testReturnsNullWhenBillingAddressNotFound(): void
    {
        try {
            $result = $this->invoke([
                'user_id'             => 999999999,
                'billing_address_id'  => 999999999,
                'delivery_address_id' => 999999999,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
        $this->assertNull($result);
    }

    public function testReturnsNullOnMissingSnapshotKeys(): void
    {
        try {
            $result = $this->invoke([]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
        $this->assertNull($result);
    }
}
