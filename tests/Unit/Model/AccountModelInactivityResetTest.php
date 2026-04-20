<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Model\AccountModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Vérifie le comportement de resetInactivityWarning().
 */
class AccountModelInactivityResetTest extends TestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new AccountModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    /**
     * La méthode existe et est publique.
     */
    public function testMethodExists(): void
    {
        $rc = new ReflectionClass(AccountModel::class);
        $this->assertTrue($rc->hasMethod('resetInactivityWarning'));
        $this->assertTrue($rc->getMethod('resetInactivityWarning')->isPublic());
    }

    /**
     * Appel sur un ID inexistant — aucune exception, retour void.
     */
    public function testNoExceptionOnUnknownId(): void
    {
        $this->expectNotToPerformAssertions();
        $this->model->resetInactivityWarning(999999999);
    }

    /**
     * Double appel (idempotent) — aucune exception.
     */
    public function testIdempotentOnUnknownId(): void
    {
        $this->expectNotToPerformAssertions();
        $this->model->resetInactivityWarning(999999999);
        $this->model->resetInactivityWarning(999999999);
    }
}
