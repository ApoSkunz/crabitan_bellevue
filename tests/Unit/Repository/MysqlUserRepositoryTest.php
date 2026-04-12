<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Core\Database;
use Model\AccountModel;
use Repository\MysqlUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de MysqlUserRepository.
 *
 * Vérifie que le repository délègue correctement à AccountModel en stub,
 * sans toucher à la base de données.
 */
class MysqlUserRepositoryTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * Crée un stub Database + AccountModel + MysqlUserRepository.
     *
     * @return array{0: \PHPUnit\Framework\MockObject\Stub, 1: MysqlUserRepository}
     */
    private function makeRepo(): array
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        $model = new AccountModel();
        $repo  = new MysqlUserRepository($model);
        return [$stub, $repo];
    }

    // ----------------------------------------------------------------
    // findById
    // ----------------------------------------------------------------

    /**
     * findById retourne le compte quand AccountModel en trouve un.
     */
    public function testFindByIdReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 1, 'email' => 'user@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findById(1));
    }

    /**
     * findById retourne false quand le compte est introuvable.
     */
    public function testFindByIdReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findById(999));
    }

    // ----------------------------------------------------------------
    // findByEmail
    // ----------------------------------------------------------------

    /**
     * findByEmail retourne le compte correspondant.
     */
    public function testFindByEmailReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 2, 'email' => 'alice@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByEmail('alice@example.com'));
    }

    /**
     * findByEmail retourne false si l'email n'est pas en base.
     */
    public function testFindByEmailReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByEmail('unknown@example.com'));
    }

    // ----------------------------------------------------------------
    // findByVerificationToken
    // ----------------------------------------------------------------

    /**
     * findByVerificationToken retourne le compte quand le token est valide.
     */
    public function testFindByVerificationTokenReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 3, 'email' => 'bob@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByVerificationToken('abc123'));
    }

    /**
     * findByVerificationToken retourne false si le token est invalide/expiré.
     */
    public function testFindByVerificationTokenReturnsFalseWhenExpired(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByVerificationToken('expiredtoken'));
    }

    // ----------------------------------------------------------------
    // hasDeclaredMajority
    // ----------------------------------------------------------------

    /**
     * hasDeclaredMajority retourne true si la majorité est déclarée.
     */
    public function testHasDeclaredMajorityReturnsTrueWhenDeclared(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(['majority_declared_at' => '2025-01-01 00:00:00']);

        $this->assertTrue($repo->hasDeclaredMajority(1));
    }

    /**
     * hasDeclaredMajority retourne false si la majorité n'est pas déclarée.
     */
    public function testHasDeclaredMajorityReturnsFalseWhenNotDeclared(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(['majority_declared_at' => null]);

        $this->assertFalse($repo->hasDeclaredMajority(1));
    }

    // ----------------------------------------------------------------
    // delete / reactivate
    // ----------------------------------------------------------------

    /**
     * delete retourne 1 quand le soft-delete s'applique.
     */
    public function testDeleteReturnRowCount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $this->assertSame(1, $repo->delete(1));
    }

    /**
     * reactivate retourne 1 quand la réactivation s'applique.
     */
    public function testReactivateReturnRowCount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $this->assertSame(1, $repo->reactivate(1));
    }

    // ----------------------------------------------------------------
    // updatePassword
    // ----------------------------------------------------------------

    /**
     * updatePassword ne lève pas d'exception.
     */
    public function testUpdatePasswordExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updatePassword(1, '$2y$12$hashedpassword');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // logAuditEvent
    // ----------------------------------------------------------------

    /**
     * logAuditEvent ne lève pas d'exception.
     */
    public function testLogAuditEventExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('insert')->willReturn('99');

        $repo->logAuditEvent(1, 'email_changed', '127.0.0.1', ['old' => 'a@a.com']);
        $this->addToAssertionCount(1);
    }
}
