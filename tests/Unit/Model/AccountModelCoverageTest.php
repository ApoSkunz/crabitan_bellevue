<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\AccountModel;
use PHPUnit\Framework\TestCase;
use Service\EncryptionService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests de couverture pour AccountModel — couvre les lignes non atteintes.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountModelCoverageTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub $dbMock;
    private \ReflectionProperty $instanceProp;
    private AccountModel $model;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $enc         = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);

        $this->model = new AccountModel($enc);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // createFromGoogle — company branch (lines 209-214)
    // ----------------------------------------------------------------

    /**
     * createFromGoogle avec account_type='company' insère dans account_companies.
     */
    public function testCreateFromGoogleCompanyBranch(): void
    {
        $this->dbMock->method('insert')->willReturn('7');

        $id = $this->model->createFromGoogle(
            'corp@example.com',
            'google-sub-123',
            'fr',
            'Alice',
            'Corp',
            'company',
            'Ma Société'
        );

        $this->assertSame(7, $id);
    }

    /**
     * createFromGoogle rollback si une exception est levée (lines 225-227).
     */
    public function testCreateFromGoogleRollbackOnException(): void
    {
        $this->dbMock->method('insert')->willThrowException(new \RuntimeException('db error'));

        $this->expectException(\RuntimeException::class);
        $this->model->createFromGoogle('x@x.com', 'sub', 'fr', 'A', 'B');
    }

    // ----------------------------------------------------------------
    // create() — rollback branch (line 305)
    // ----------------------------------------------------------------

    /**
     * create() rollback si une exception est levée.
     */
    public function testCreateRollbackOnException(): void
    {
        $this->dbMock->method('insert')->willThrowException(new \RuntimeException('db error'));

        $this->expectException(\RuntimeException::class);
        $this->model->create(
            'individual',
            'test@test.com',
            'hashed',
            'fr',
            0,
            'token123',
            'M',
            'Doe',
            'John',
            ''
        );
    }

    // ----------------------------------------------------------------
    // getReactivationToken (lines 497-501)
    // ----------------------------------------------------------------

    /**
     * getReactivationToken retourne le token quand trouvé.
     */
    public function testGetReactivationTokenReturnsToken(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['reactivation_token' => 'abc123']);

        $this->assertSame('abc123', $this->model->getReactivationToken(1));
    }

    /**
     * getReactivationToken retourne null quand non trouvé.
     */
    public function testGetReactivationTokenReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->getReactivationToken(99));
    }

    /**
     * getReactivationToken retourne null si token absent du tableau.
     */
    public function testGetReactivationTokenReturnsNullWhenKeyMissing(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([]);

        $this->assertNull($this->model->getReactivationToken(1));
    }

    // ----------------------------------------------------------------
    // getPendingSessionToken (lines 536-542)
    // ----------------------------------------------------------------

    /**
     * getPendingSessionToken retourne le token quand présent.
     */
    public function testGetPendingSessionTokenReturnsToken(): void
    {
        $this->dbMock->method('fetchOne')
            ->willReturn(['pending_password_session_token' => 'jwt.token.here']);

        $this->assertSame('jwt.token.here', $this->model->getPendingSessionToken(1));
    }

    /**
     * getPendingSessionToken retourne null si fetchOne retourne false.
     */
    public function testGetPendingSessionTokenReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->getPendingSessionToken(1));
    }

    /**
     * getPendingSessionToken retourne null si clé absente.
     */
    public function testGetPendingSessionTokenReturnsNullWhenKeyMissing(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([]);

        $this->assertNull($this->model->getPendingSessionToken(1));
    }

    // ----------------------------------------------------------------
    // getAccountsPendingDeletionReminder (lines 665-690)
    // ----------------------------------------------------------------

    /**
     * daysAhead=7 utilise la colonne deletion_reminder_7d_sent_at.
     */
    public function testGetAccountsPendingDeletionReminderSevenDays(): void
    {
        $expected = [['id' => 1, 'email' => 'test@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getAccountsPendingDeletionReminder(7);
        $this->assertSame($expected, $result);
    }

    /**
     * daysAhead=1 utilise la colonne deletion_reminder_1d_sent_at.
     */
    public function testGetAccountsPendingDeletionReminderOneDay(): void
    {
        $expected = [['id' => 2, 'email' => 'user@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getAccountsPendingDeletionReminder(1, 6);
        $this->assertSame($expected, $result);
    }

    /**
     * daysAhead invalide lève InvalidArgumentException.
     */
    public function testGetAccountsPendingDeletionReminderInvalidDaysThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->getAccountsPendingDeletionReminder(3);
    }

    // ----------------------------------------------------------------
    // markDeletionReminder7dSent / markDeletionReminder1dSent (lines 698-716)
    // ----------------------------------------------------------------

    /**
     * markDeletionReminder7dSent appelle execute.
     */
    public function testMarkDeletionReminder7dSentCallsExecute(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('deletion_reminder_7d_sent_at'), [5]);

        $model->markDeletionReminder7dSent(5);
    }

    /**
     * markDeletionReminder1dSent appelle execute.
     */
    public function testMarkDeletionReminder1dSentCallsExecute(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('deletion_reminder_1d_sent_at'), [8]);

        $model->markDeletionReminder1dSent(8);
    }

    // ----------------------------------------------------------------
    // purgeScheduledDeletions (lines 798-848)
    // ----------------------------------------------------------------

    /**
     * purgeScheduledDeletions retourne 0 quand aucun compte.
     */
    public function testPurgeScheduledDeletionsReturnsZeroWhenEmpty(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame(0, $this->model->purgeScheduledDeletions());
    }

    /**
     * purgeScheduledDeletions exécute 5 requêtes quand des comptes existent.
     */
    public function testPurgeScheduledDeletionsExecutesQueriesWhenAccountsExist(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->method('fetchAll')->willReturn([['id' => 1], ['id' => 2]]);
        $mock->expects($this->exactly(5))->method('execute');

        $result = $model->purgeScheduledDeletions();
        $this->assertSame(2, $result);
    }

    // ----------------------------------------------------------------
    // getInactiveAccounts (lines 864-881)
    // ----------------------------------------------------------------

    /**
     * getInactiveAccounts retourne les résultats de fetchAll.
     */
    public function testGetInactiveAccountsReturnsFetchAllResult(): void
    {
        $expected = [['id' => 3, 'email' => 'inactive@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getInactiveAccounts(36);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // getAccountsToSoftDelete (lines 892-911)
    // ----------------------------------------------------------------

    /**
     * getAccountsToSoftDelete retourne les résultats de fetchAll.
     */
    public function testGetAccountsToSoftDeleteReturnsFetchAllResult(): void
    {
        $expected = [['id' => 4, 'email' => 'todelete@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getAccountsToSoftDelete();
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // softDeleteForInactivity (lines 951-965)
    // ----------------------------------------------------------------

    /**
     * softDeleteForInactivity retourne un token non vide et appelle execute.
     */
    public function testSoftDeleteForInactivityReturnsToken(): void
    {
        $this->dbMock->method('execute')->willReturn(1);

        $token = $this->model->softDeleteForInactivity(1);

        $this->assertNotEmpty($token);
    }

    // ----------------------------------------------------------------
    // getForAdmin / countForAdmin (lines 1139-1180)
    // ----------------------------------------------------------------

    /**
     * getForAdmin retourne les lignes de fetchAll.
     */
    public function testGetForAdminReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'email' => 'admin@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters());
        $this->assertSame($expected, $result);
    }

    /**
     * getForAdmin avec rôle filtre correctement.
     */
    public function testGetForAdminWithRoleFilter(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(role: 'customer'));
        $this->assertSame([], $result);
    }

    /**
     * getForAdmin avec recherche texte.
     */
    public function testGetForAdminWithSearch(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(search: 'dupont'));
        $this->assertSame([], $result);
    }

    /**
     * getForAdmin avec tri last_connection_desc.
     */
    public function testGetForAdminWithSortLastConnectionDesc(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(
            10,
            0,
            new \ValueObject\AccountAdminFilters(type: 'individual', sort: 'last_connection_desc')
        );
        $this->assertSame([], $result);
    }

    /**
     * getForAdmin avec tri created_at_asc.
     */
    public function testGetForAdminWithSortCreatedAtAsc(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(sort: 'created_at_asc'));
        $this->assertSame([], $result);
    }

    /**
     * getForAdmin avec tri last_connection_asc.
     */
    public function testGetForAdminWithSortLastConnectionAsc(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(sort: 'last_connection_asc'));
        $this->assertSame([], $result);
    }

    /**
     * getForAdmin avec tri order_count_desc — couvre l.1162 du match.
     *
     * @return void
     */
    public function testGetForAdminSortByOrderCountDesc(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(sort: 'order_count_desc'));
        $this->assertStringContainsString('order_count DESC', $capturedSql);
    }

    /**
     * getForAdmin avec tri order_count_asc — couvre l.1163 du match.
     *
     * @return void
     */
    public function testGetForAdminSortByOrderCountAsc(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(sort: 'order_count_asc'));
        $this->assertStringContainsString('order_count ASC', $capturedSql);
    }

    /**
     * getForAdmin avec hasOrdered='1' — couvre l.1197-1198 (if branch).
     *
     * @return void
     */
    public function testApplyAdminFlagFiltersHasOrderedOne(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(hasOrdered: '1'));
        $this->assertStringContainsString('> 0', $capturedSql);
        // Vérifie la clause "a déjà commandé"
        $this->assertMatchesRegularExpression('/COUNT\(\*\)\s+FROM\s+orders.*>\s*0/s', $capturedSql);
    }

    /**
     * getForAdmin avec hasOrdered='0' — couvre l.1199-1200 (elseif branch).
     *
     * @return void
     */
    public function testApplyAdminFlagFiltersHasOrderedZero(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $this->model->getForAdmin(10, 0, new \ValueObject\AccountAdminFilters(hasOrdered: '0'));
        $this->assertStringContainsString('= 0', $capturedSql);
        // Vérifie la clause "jamais commandé"
        $this->assertMatchesRegularExpression('/COUNT\(\*\)\s+FROM\s+orders.*=\s*0/s', $capturedSql);
    }

    /**
     * countForAdmin retourne la valeur entière.
     */
    public function testCountForAdminReturnsInteger(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '42']);

        $this->assertSame(42, $this->model->countForAdmin(new \ValueObject\AccountAdminFilters()));
    }

    /**
     * countForAdmin avec type company.
     */
    public function testCountForAdminWithTypeCompany(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '5']);

        $this->assertSame(5, $this->model->countForAdmin(new \ValueObject\AccountAdminFilters(type: 'company')));
    }

    // ----------------------------------------------------------------
    // getForAdmin / countForAdmin — filtre activity (active/inactive/deleted)
    // via applyAdminFlagFilters()
    // ----------------------------------------------------------------

    /**
     * getForAdmin avec activity=active filtre sur deleted_at IS NULL + last_login_at récent.
     */
    public function testGetForAdminWithActivityActive(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'active');
        $result  = $this->model->getForAdmin(10, 0, $filters);
        $this->assertSame([], $result);
        $this->assertStringContainsString('deleted_at IS NULL', $capturedSql);
        $this->assertStringContainsString('last_login_at >= DATE_SUB', $capturedSql);
    }

    /**
     * getForAdmin avec activity=inactive inclut NULL ou ancien last_login_at.
     */
    public function testGetForAdminWithActivityInactive(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'inactive');
        $result  = $this->model->getForAdmin(10, 0, $filters);
        $this->assertSame([], $result);
        $this->assertStringContainsString('last_login_at IS NULL', $capturedSql);
        $this->assertStringContainsString('< DATE_SUB', $capturedSql);
    }

    /**
     * getForAdmin avec activity=deleted filtre sur deleted_at IS NOT NULL.
     */
    public function testGetForAdminWithActivityDeleted(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'deleted');
        $result  = $this->model->getForAdmin(10, 0, $filters);
        $this->assertSame([], $result);
        $this->assertStringContainsString('deleted_at IS NOT NULL', $capturedSql);
    }

    /**
     * getForAdmin avec activity null n'ajoute pas de clause DATE_SUB supplémentaire.
     * (La clause de base `deleted_at IS NULL` est toujours posée par buildAdminFilters.)
     */
    public function testGetForAdminWithActivityNullNoActivityFilter(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return [];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters();
        $result  = $this->model->getForAdmin(10, 0, $filters);
        $this->assertSame([], $result);
        $this->assertStringNotContainsString('DATE_SUB', $capturedSql);
        $this->assertStringNotContainsString('deleted_at IS NOT NULL', $capturedSql);
    }

    /**
     * countForAdmin avec activity=active inclut la clause déléguée.
     */
    public function testCountForAdminWithActivityActive(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchOne')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return ['total' => '3'];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'active');
        $this->assertSame(3, $this->model->countForAdmin($filters));
        $this->assertStringContainsString('last_login_at >= DATE_SUB', $capturedSql);
    }

    /**
     * countForAdmin avec activity=inactive.
     */
    public function testCountForAdminWithActivityInactive(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchOne')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return ['total' => '2'];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'inactive');
        $this->assertSame(2, $this->model->countForAdmin($filters));
        $this->assertStringContainsString('last_login_at IS NULL', $capturedSql);
    }

    /**
     * countForAdmin avec activity=deleted.
     */
    public function testCountForAdminWithActivityDeleted(): void
    {
        $capturedSql = '';
        $this->dbMock->method('fetchOne')->willReturnCallback(
            function (string $sql) use (&$capturedSql): array {
                $capturedSql = $sql;
                return ['total' => '1'];
            }
        );

        $filters = new \ValueObject\AccountAdminFilters(activity: 'deleted');
        $this->assertSame(1, $this->model->countForAdmin($filters));
        $this->assertStringContainsString('deleted_at IS NOT NULL', $capturedSql);
    }

    // ----------------------------------------------------------------
    // updateRole (lines 1183-1192)
    // ----------------------------------------------------------------

    /**
     * updateRole valide exécute l'UPDATE.
     */
    public function testUpdateRoleValidExecutesUpdate(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('role'), ['admin', 1]);

        $model->updateRole(1, 'admin');
    }

    /**
     * updateRole invalide n'exécute rien.
     */
    public function testUpdateRoleInvalidDoesNotExecute(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->never())->method('execute');

        $model->updateRole(1, 'hacker');
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribers (lines 1228-1249)
    // ----------------------------------------------------------------

    /**
     * getNewsletterSubscribers retourne les lignes de fetchAll.
     */
    public function testGetNewsletterSubscribersReturnsFetchAllResult(): void
    {
        $expected = [['email' => 'sub@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getNewsletterSubscribers(10, 0);
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // countNewsletterSubscribers (lines 1261-1277)
    // ----------------------------------------------------------------

    /**
     * countNewsletterSubscribers retourne le total.
     */
    public function testCountNewsletterSubscribersReturnsTotal(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '15']);

        $this->assertSame(15, $this->model->countNewsletterSubscribers());
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribersBySegment (lines 1305-1399)
    // ----------------------------------------------------------------

    /**
     * Segment 'non-inscrit' utilise la requête newsletter_subscriptions.
     */
    public function testGetNewsletterSubscribersBySegmentNonInscrit(): void
    {
        $expected = [['email' => 'visitor@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getNewsletterSubscribersBySegment('non-inscrit', 10, 0);
        $this->assertSame($expected, $result);
    }

    /**
     * Segment 'tous' utilise la requête UNION.
     */
    public function testGetNewsletterSubscribersBySegmentTous(): void
    {
        $expected = [['email' => 'all@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getNewsletterSubscribersBySegment('tous', 10, 0);
        $this->assertSame($expected, $result);
    }

    /**
     * Segment 'particuliers' filtre par account_type=individual.
     */
    public function testGetNewsletterSubscribersBySegmentParticuliers(): void
    {
        $expected = [['email' => 'indiv@test.com']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->getNewsletterSubscribersBySegment('particuliers', 10, 0);
        $this->assertSame($expected, $result);
    }

    /**
     * Segment 'professionnels' filtre par account_type=company.
     */
    public function testGetNewsletterSubscribersBySegmentProfessionnels(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getNewsletterSubscribersBySegment('professionnels', 5, 0);
        $this->assertSame([], $result);
    }

    /**
     * Segment 'commandes' filtre les abonnés avec commandes.
     */
    public function testGetNewsletterSubscribersBySegmentCommandes(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getNewsletterSubscribersBySegment('commandes', 5, 0);
        $this->assertSame([], $result);
    }

    /**
     * Segment 'sans-commande' filtre les abonnés sans commande.
     */
    public function testGetNewsletterSubscribersBySegmentSansCommande(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getNewsletterSubscribersBySegment('sans-commande', 5, 0);
        $this->assertSame([], $result);
    }

    /**
     * Segment 'inactifs' filtre les abonnés inactifs.
     */
    public function testGetNewsletterSubscribersBySegmentInactifs(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getNewsletterSubscribersBySegment('inactifs', 5, 0);
        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // countNewsletterSubscribersBySegment (lines 1408-1434)
    // ----------------------------------------------------------------

    /**
     * Segment 'non-inscrit' retourne le count.
     */
    public function testCountNewsletterSubscribersBySegmentNonInscrit(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '3']);

        $this->assertSame(3, $this->model->countNewsletterSubscribersBySegment('non-inscrit'));
    }

    /**
     * Segment 'tous' délègue à countNewsletterSubscribers.
     */
    public function testCountNewsletterSubscribersBySegmentTous(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '20']);

        $result = $this->model->countNewsletterSubscribersBySegment('tous');
        $this->assertSame(20, $result);
    }

    /**
     * Segment inconnu délègue à countNewsletterSubscribers.
     */
    public function testCountNewsletterSubscribersBySegmentUnknown(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '10']);

        $result = $this->model->countNewsletterSubscribersBySegment('unknown_segment');
        $this->assertSame(10, $result);
    }

    /**
     * Segment 'particuliers' compte les particuliers.
     */
    public function testCountNewsletterSubscribersBySegmentParticuliers(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['total' => '7']);

        $this->assertSame(7, $this->model->countNewsletterSubscribersBySegment('particuliers'));
    }

    // ----------------------------------------------------------------
    // confirmNewsletterByToken (lines 1499-1502)
    // ----------------------------------------------------------------

    /**
     * confirmNewsletterByToken retourne false si token invalide.
     */
    public function testConfirmNewsletterByTokenReturnsFalseForInvalidToken(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->confirmNewsletterByToken('bad_token'));
    }

    /**
     * confirmNewsletterByToken retourne le compte et exécute UPDATE si token valide.
     */
    public function testConfirmNewsletterByTokenUpdatesAndReturnsAccountOnSuccess(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $account = ['id' => 5];
        $mock->method('fetchOne')->willReturn($account);
        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('newsletter = 1'), [5]);

        $result = $model->confirmNewsletterByToken('valid_token');
        $this->assertSame($account, $result);
    }

    // ----------------------------------------------------------------
    // getConsentData (lines 1515-1542)
    // ----------------------------------------------------------------

    /**
     * getConsentData retourne newsletter false quand compte non trouvé.
     */
    public function testGetConsentDataNewsletterFalseWhenAccountNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getConsentData(1);

        $this->assertFalse($result['newsletter']['abonne']);
        $this->assertSame([], $result['cgv_versions']);
    }

    /**
     * getConsentData retourne newsletter true si newsletter=1.
     */
    public function testGetConsentDataNewsletterTrueWhenSubscribed(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'newsletter'       => 1,
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);
        $this->dbMock->method('fetchAll')->willReturn([
            ['cgv_version' => '1.0'],
            ['cgv_version' => '2.0'],
        ]);

        $result = $this->model->getConsentData(1);

        $this->assertTrue($result['newsletter']['abonne']);
        $this->assertSame(['1.0', '2.0'], $result['cgv_versions']);
    }

    // ----------------------------------------------------------------
    // findAdmins (lines 1626-1627)
    // ----------------------------------------------------------------

    /**
     * findAdmins retourne les lignes de fetchAll.
     */
    public function testFindAdminsReturnsFetchAllResult(): void
    {
        $expected = [['id' => 1, 'email' => 'admin@site.com', 'role' => 'admin']];
        $this->dbMock->method('fetchAll')->willReturn($expected);

        $result = $this->model->findAdmins();
        $this->assertSame($expected, $result);
    }

    // ----------------------------------------------------------------
    // blockAccount (lines 1636-1646)
    // ----------------------------------------------------------------

    /**
     * blockAccount exécute au moins 2 requêtes (is_blocked + DELETE trusted_devices).
     */
    public function testBlockAccountExecutesMultipleQueries(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        // blockAccount calls: execute(is_blocked), execute(revoke connections), execute(DELETE trusted_devices)
        $mock->expects($this->exactly(3))->method('execute');

        $model->blockAccount(1);
    }

    // ----------------------------------------------------------------
    // unblockAccount (lines 1655-1660)
    // ----------------------------------------------------------------

    /**
     * unblockAccount exécute l'UPDATE is_blocked = 0.
     */
    public function testUnblockAccountExecutesUpdate(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('is_blocked = 0'), [3]);

        $model->unblockAccount(3);
    }

    // ----------------------------------------------------------------
    // getSegmentation (lines 1672-1692)
    // ----------------------------------------------------------------

    /**
     * getSegmentation retourne les données agrégées correctement castées.
     */
    public function testGetSegmentationReturnsCastedData(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([[
            'pro'         => '5',
            'particulier' => '20',
            'fr'          => '18',
            'en'          => '7',
            'total'       => '25',
        ]]);

        $result = $this->model->getSegmentation();

        $this->assertSame(5, $result['pro']);
        $this->assertSame(20, $result['particulier']);
        $this->assertSame(18, $result['fr']);
        $this->assertSame(7, $result['en']);
        $this->assertSame(25, $result['total']);
    }

    /**
     * getSegmentation retourne des zéros si fetchAll retourne un tableau vide.
     */
    public function testGetSegmentationReturnsZerosWhenNoData(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->getSegmentation();

        $this->assertSame(0, $result['total']);
    }

    // ----------------------------------------------------------------
    // saveEmailChangeToken (lines 1558-1565)
    // ----------------------------------------------------------------

    /**
     * saveEmailChangeToken appelle execute avec les bons paramètres.
     */
    public function testSaveEmailChangeTokenCallsExecute(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('email_change_token'),
                $this->anything()
            );

        $model->saveEmailChangeToken(1, 'hashed_token', 'new@mail.com', '2026-12-31 23:59:59');
    }

    /**
     * saveEmailChangeToken accepte un token null (anti-énumération).
     */
    public function testSaveEmailChangeTokenAcceptsNullToken(): void
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturnArgument(0);
        $enc->method('encrypt')->willReturnArgument(0);
        $model = new AccountModel($enc);

        $mock->expects($this->once())->method('execute');

        $model->saveEmailChangeToken(1, null, 'existing@mail.com', '2026-12-31 23:59:59');
    }

    // ----------------------------------------------------------------
    // findByEmailChangeToken (lines 1547-1553)
    // ----------------------------------------------------------------

    /**
     * findByEmailChangeToken retourne false quand non trouvé.
     */
    public function testFindByEmailChangeTokenReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $result = $this->model->findByEmailChangeToken('bad_hash');
        $this->assertFalse($result);
    }

    /**
     * findByEmailChangeToken déchiffre email_change_new_email.
     */
    public function testFindByEmailChangeTokenDecryptsNewEmail(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'                    => 1,
            'email'                 => 'old@mail.com',
            'email_change_new_email' => 'encrypted_new@mail.com',
            'email_change_expires_at' => '2027-01-01',
            'email_change_used_at'   => null,
            'lang'                  => 'fr',
        ]);

        $result = $this->model->findByEmailChangeToken('hash123');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email_change_new_email', $result);
    }

    // ----------------------------------------------------------------
    // decryptRow — branche email_change_new_email (lines 93-94)
    // et branche majority_declared_ip (lines 98-99)
    // ----------------------------------------------------------------

    /**
     * findById avec email_change_new_email dans la ligne → decrypt appelé (ligne 93-94).
     */
    public function testFindByIdDecryptsEmailChangeNewEmail(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'                    => 5,
            'email'                 => 'user@test.com',
            'email_change_new_email' => 'encrypted_value',
        ]);

        $result = $this->model->findById(5);
        $this->assertIsArray($result);
        // Le stub EncryptionService retourne l'argument tel quel
        $this->assertSame('encrypted_value', $result['email_change_new_email']);
    }

    /**
     * findById avec majority_declared_ip dans la ligne → decrypt appelé (lignes 98-99).
     */
    public function testFindByIdDecryptsMajorityDeclaredIp(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'                   => 6,
            'email'                => 'user2@test.com',
            'majority_declared_ip' => 'encrypted_ip',
        ]);

        $result = $this->model->findById(6);
        $this->assertIsArray($result);
        $this->assertSame('encrypted_ip', $result['majority_declared_ip']);
    }

    /**
     * findById avec les deux champs sensibles dans la ligne → les deux branches decrypt.
     */
    public function testFindByIdDecryptsBothSensitiveFields(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'                    => 7,
            'email'                 => 'user3@test.com',
            'email_change_new_email' => 'enc_email',
            'majority_declared_ip'  => 'enc_ip',
        ]);

        $result = $this->model->findById(7);
        $this->assertIsArray($result);
        $this->assertSame('enc_email', $result['email_change_new_email']);
        $this->assertSame('enc_ip', $result['majority_declared_ip']);
    }

    /**
     * findById avec decrypt retournant null → fallback `?? $row[...]` couvre L94 & L99.
     */
    public function testFindByIdFallsBackWhenDecryptReturnsNull(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'                     => 8,
            'email'                  => 'null@test.com',
            'email_change_new_email' => 'garbled_enc_email',
            'majority_declared_ip'   => 'garbled_enc_ip',
        ]);
        $enc = $this->createStub(EncryptionService::class);
        $enc->method('decrypt')->willReturn(null);

        $model  = new AccountModel($enc);
        $result = $model->findById(8);

        $this->assertIsArray($result);
        $this->assertSame('garbled_enc_email', $result['email_change_new_email']);
        $this->assertSame('garbled_enc_ip', $result['majority_declared_ip']);
    }

    // ----------------------------------------------------------------
    // findDeletedById — retourne le compte / false
    // ----------------------------------------------------------------

    /**
     * findDeletedById retourne le compte quand trouvé.
     */
    public function testFindDeletedByIdReturnsRowWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['id' => 10, 'email' => 'del@test.local']);

        $result = $this->model->findDeletedById(10);

        $this->assertIsArray($result);
        $this->assertSame(10, $result['id']);
    }

    /**
     * findDeletedById retourne false quand aucun résultat.
     */
    public function testFindDeletedByIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->findDeletedById(99));
    }

    // ----------------------------------------------------------------
    // findRestorableById — retourne le compte / false
    // ----------------------------------------------------------------

    /**
     * findRestorableById retourne le compte dans la fenêtre J+30.
     */
    public function testFindRestorableByIdReturnsRowWhenInWindow(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['id' => 20, 'email' => 'restore@test.local']);

        $result = $this->model->findRestorableById(20);

        $this->assertIsArray($result);
        $this->assertSame(20, $result['id']);
    }

    /**
     * findRestorableById retourne false hors fenêtre ou compte actif.
     */
    public function testFindRestorableByIdReturnsFalseWhenNotRestorable(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->findRestorableById(99));
    }

    // ----------------------------------------------------------------
    // findCancellableById — retourne le compte / false
    // ----------------------------------------------------------------

    /**
     * findCancellableById retourne le compte en alerte inactivité.
     */
    public function testFindCancellableByIdReturnsRowWhenWarning(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['id' => 30, 'email' => 'cancel@test.local']);

        $result = $this->model->findCancellableById(30);

        $this->assertIsArray($result);
        $this->assertSame(30, $result['id']);
    }

    /**
     * findCancellableById retourne false pour un compte actif sans alerte.
     */
    public function testFindCancellableByIdReturnsFalseForActiveAccount(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->findCancellableById(99));
    }
}
