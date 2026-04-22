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

    // ----------------------------------------------------------------
    // findDeletedByEmail
    // ----------------------------------------------------------------

    /**
     * findDeletedByEmail retourne le compte soft-deleted correspondant.
     */
    public function testFindDeletedByEmailReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 5, 'email' => 'deleted@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findDeletedByEmail('deleted@example.com'));
    }

    /**
     * findDeletedByEmail retourne false si aucun compte soft-deleted.
     */
    public function testFindDeletedByEmailReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findDeletedByEmail('unknown@example.com'));
    }

    // ----------------------------------------------------------------
    // findByGoogleId
    // ----------------------------------------------------------------

    /**
     * findByGoogleId retourne le compte correspondant.
     */
    public function testFindByGoogleIdReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 6, 'google_id' => 'google-sub-123'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByGoogleId('google-sub-123'));
    }

    /**
     * findByGoogleId retourne false si non trouvé.
     */
    public function testFindByGoogleIdReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByGoogleId('nonexistent-sub'));
    }

    // ----------------------------------------------------------------
    // findDeletedByGoogleId
    // ----------------------------------------------------------------

    /**
     * findDeletedByGoogleId retourne le compte soft-deleted correspondant.
     */
    public function testFindDeletedByGoogleIdReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 7, 'google_id' => 'google-sub-deleted'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findDeletedByGoogleId('google-sub-deleted'));
    }

    /**
     * findDeletedByGoogleId retourne false si non trouvé.
     */
    public function testFindDeletedByGoogleIdReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findDeletedByGoogleId('nonexistent'));
    }

    // ----------------------------------------------------------------
    // findByAppleId
    // ----------------------------------------------------------------

    /**
     * findByAppleId retourne le compte correspondant.
     */
    public function testFindByAppleIdReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 8, 'apple_id' => 'apple-sub-123'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByAppleId('apple-sub-123'));
    }

    /**
     * findByAppleId retourne false si non trouvé.
     */
    public function testFindByAppleIdReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByAppleId('nonexistent-apple'));
    }

    // ----------------------------------------------------------------
    // findBySecurityConfirmToken
    // ----------------------------------------------------------------

    /**
     * findBySecurityConfirmToken retourne le compte correspondant.
     */
    public function testFindBySecurityConfirmTokenReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 9, 'email' => 'secure@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findBySecurityConfirmToken('sha256hash'));
    }

    /**
     * findBySecurityConfirmToken retourne false si non trouvé.
     */
    public function testFindBySecurityConfirmTokenReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findBySecurityConfirmToken('unknown-hash'));
    }

    // ----------------------------------------------------------------
    // findByEmailChangeToken
    // ----------------------------------------------------------------

    /**
     * findByEmailChangeToken retourne le compte correspondant.
     */
    public function testFindByEmailChangeTokenReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 10, 'email' => 'change@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByEmailChangeToken('change-hash'));
    }

    /**
     * findByEmailChangeToken retourne false si non trouvé.
     */
    public function testFindByEmailChangeTokenReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByEmailChangeToken('unknown-change-hash'));
    }

    // ----------------------------------------------------------------
    // findByReactivationToken
    // ----------------------------------------------------------------

    /**
     * findByReactivationToken retourne le compte correspondant.
     */
    public function testFindByReactivationTokenReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 11, 'email' => 'reactivate@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByReactivationToken('reactivation-token'));
    }

    /**
     * findByReactivationToken retourne false si non trouvé.
     */
    public function testFindByReactivationTokenReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByReactivationToken('unknown-reactivation'));
    }

    // ----------------------------------------------------------------
    // findByUnsubscribeToken
    // ----------------------------------------------------------------

    /**
     * findByUnsubscribeToken retourne le compte correspondant.
     */
    public function testFindByUnsubscribeTokenReturnsAccount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $expected = ['id' => 12, 'email' => 'unsub@example.com'];
        $stub->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $repo->findByUnsubscribeToken('unsub-token'));
    }

    /**
     * findByUnsubscribeToken retourne false si non trouvé.
     */
    public function testFindByUnsubscribeTokenReturnsFalseWhenNotFound(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($repo->findByUnsubscribeToken('unknown-unsub'));
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    /**
     * create retourne l'identifiant du compte créé sous forme de string.
     */
    public function testCreateReturnsNewAccountId(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('insert')->willReturn('42');

        $id = $repo->create(
            'individual',
            'new@example.com',
            '$2y$12$hash',
            'fr',
            0,
            'verif-token-hex',
            'M',
            'Dupont',
            'Jean',
            ''
        );

        $this->assertSame('42', $id);
    }

    // ----------------------------------------------------------------
    // createFromGoogle
    // ----------------------------------------------------------------

    /**
     * createFromGoogle retourne l'identifiant du compte créé (int).
     */
    public function testCreateFromGoogleReturnsNewAccountId(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('insert')->willReturn('55');

        $id = $repo->createFromGoogle('google@example.com', 'google-sub-new', 'fr', 'Alice', 'Martin');

        $this->assertSame(55, $id);
    }

    // ----------------------------------------------------------------
    // updateIndividualProfile
    // ----------------------------------------------------------------

    /**
     * updateIndividualProfile s'exécute sans exception.
     */
    public function testUpdateIndividualProfileExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateIndividualProfile(1, 'M', 'Jean', 'Dupont');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // updateCompanyProfile
    // ----------------------------------------------------------------

    /**
     * updateCompanyProfile s'exécute sans exception (siret null).
     */
    public function testUpdateCompanyProfileExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateCompanyProfile(2, 'Acme SA', null);
        $this->addToAssertionCount(1);
    }

    /**
     * updateCompanyProfile s'exécute sans exception (siret fourni).
     */
    public function testUpdateCompanyProfileWithSiretExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateCompanyProfile(2, 'Acme SA', '12345678900001');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // updateLang
    // ----------------------------------------------------------------

    /**
     * updateLang s'exécute sans exception.
     */
    public function testUpdateLangExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateLang(1, 'en');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // updateNewsletter
    // ----------------------------------------------------------------

    /**
     * updateNewsletter avec subscribe=true s'exécute sans exception.
     */
    public function testUpdateNewsletterSubscribeExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateNewsletter(1, true);
        $this->addToAssertionCount(1);
    }

    /**
     * updateNewsletter avec subscribe=false s'exécute sans exception.
     */
    public function testUpdateNewsletterUnsubscribeExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->updateNewsletter(1, false);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // linkGoogleId
    // ----------------------------------------------------------------

    /**
     * linkGoogleId s'exécute sans exception.
     */
    public function testLinkGoogleIdExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->linkGoogleId(1, 'google-sub-link');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // verifyEmail
    // ----------------------------------------------------------------

    /**
     * verifyEmail s'exécute sans exception.
     */
    public function testVerifyEmailExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->verifyEmail(1);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // refreshVerificationToken
    // ----------------------------------------------------------------

    /**
     * refreshVerificationToken s'exécute sans exception.
     */
    public function testRefreshVerificationTokenExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->refreshVerificationToken(1, 'new-verif-token');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // markAsConnected
    // ----------------------------------------------------------------

    /**
     * markAsConnected s'exécute sans exception.
     */
    public function testMarkAsConnectedExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->markAsConnected(1);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // declareMajority
    // ----------------------------------------------------------------

    /**
     * declareMajority s'exécute sans exception.
     */
    public function testDeclareMajorityExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->declareMajority(1, '192.168.1.1');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // revokeAllSessions
    // ----------------------------------------------------------------

    /**
     * revokeAllSessions s'exécute sans exception.
     */
    public function testRevokeAllSessionsExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(3);

        $repo->revokeAllSessions(1);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // saveSecurityConfirmToken
    // ----------------------------------------------------------------

    /**
     * saveSecurityConfirmToken s'exécute sans exception.
     */
    public function testSaveSecurityConfirmTokenExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->saveSecurityConfirmToken(1, 'sha256-token-hash', '2026-01-01 00:00:00');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // consumeSecurityConfirmToken
    // ----------------------------------------------------------------

    /**
     * consumeSecurityConfirmToken s'exécute sans exception.
     */
    public function testConsumeSecurityConfirmTokenExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->consumeSecurityConfirmToken(1);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // savePendingPassword
    // ----------------------------------------------------------------

    /**
     * savePendingPassword s'exécute sans exception.
     */
    public function testSavePendingPasswordExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->savePendingPassword(1, '$2y$12$newhash', '2026-01-01 01:00:00', 'session-jwt-token');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // applyPendingPassword
    // ----------------------------------------------------------------

    /**
     * applyPendingPassword retourne 1 quand le pending est appliqué.
     */
    public function testApplyPendingPasswordReturnsRowCount(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $this->assertSame(1, $repo->applyPendingPassword(1));
    }

    /**
     * applyPendingPassword retourne 0 si aucun pending.
     */
    public function testApplyPendingPasswordReturnsZeroWhenNoPending(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(0);

        $this->assertSame(0, $repo->applyPendingPassword(1));
    }

    // ----------------------------------------------------------------
    // clearPendingPassword
    // ----------------------------------------------------------------

    /**
     * clearPendingPassword s'exécute sans exception.
     */
    public function testClearPendingPasswordExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->clearPendingPassword(1);
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // saveEmailChangeToken
    // ----------------------------------------------------------------

    /**
     * saveEmailChangeToken s'exécute sans exception (hash non null).
     */
    public function testSaveEmailChangeTokenWithHashExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->saveEmailChangeToken(1, 'sha256-email-hash', 'new@example.com', '2026-01-01 00:00:00');
        $this->addToAssertionCount(1);
    }

    /**
     * saveEmailChangeToken s'exécute sans exception (hash null — email déjà pris).
     */
    public function testSaveEmailChangeTokenWithNullHashExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->saveEmailChangeToken(1, null, 'taken@example.com', '2026-01-01 00:00:00');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // applyEmailChange
    // ----------------------------------------------------------------

    /**
     * applyEmailChange s'exécute sans exception.
     */
    public function testApplyEmailChangeExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->applyEmailChange(1, 'confirmed-new@example.com');
        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // clearEmailChangeToken
    // ----------------------------------------------------------------

    /**
     * clearEmailChangeToken s'exécute sans exception.
     */
    public function testClearEmailChangeTokenExecutesWithoutException(): void
    {
        [$stub, $repo] = $this->makeRepo();
        $stub->method('execute')->willReturn(1);

        $repo->clearEmailChangeToken(1);
        $this->addToAssertionCount(1);
    }
}
