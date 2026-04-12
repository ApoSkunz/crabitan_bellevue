<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Model\AccountModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Service\LoginService;
use Service\RateLimiterService;
use Service\SecurityAlertService;

/**
 * Tests unitaires pour LoginService.
 *
 * Couvre toutes les branches du flux d'authentification :
 *   - compte déjà verrouillé (BT2 pré-vérification)
 *   - compte soft-deleted avec identifiants valides
 *   - mot de passe incorrect
 *   - compte admin : alerte + blocage automatique
 *   - lockout BT2 après N échecs
 *   - email non vérifié
 *   - connexion réussie (nominal)
 *   - resetCounters
 *
 * Les dépendances sans expectation utilisent createStub() (PHPUnit 13).
 * Les dépendances avec expectation utilisent createMock() localement dans le test.
 */
class LoginServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Retourne un tableau de données de compte actif minimal.
     *
     * @param array<string, mixed> $overrides Champs à écraser
     * @return array<string, mixed>
     */
    private function makeAccount(array $overrides = []): array
    {
        return array_merge([
            'id'                => 42,
            'email'             => 'user@example.com',
            'password'          => password_hash('correct_password', PASSWORD_BCRYPT),
            'email_verified_at' => '2026-01-01 00:00:00',
            'role'              => 'customer',
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
            'lang'              => 'fr',
            'has_connected'     => true,
        ], $overrides);
    }

    /**
     * Construit un LoginService avec des stubs par défaut.
     *
     * @param AccountModel|null         $accounts      Stub ou mock AccountModel
     * @param RateLimiterService|null   $rateLimiter   Stub ou mock RateLimiterService
     * @param SecurityAlertService|null $securityAlert Stub ou mock SecurityAlertService
     * @return LoginService
     */
    private function makeService(
        ?AccountModel $accounts = null,
        ?RateLimiterService $rateLimiter = null,
        ?SecurityAlertService $securityAlert = null
    ): LoginService {
        $accounts      ??= $this->createStub(AccountModel::class);
        $rateLimiter   ??= $this->createStub(RateLimiterService::class);
        $securityAlert ??= $this->createStub(SecurityAlertService::class);

        return new LoginService($accounts, $rateLimiter, $securityAlert);
    }

    // ----------------------------------------------------------------
    // BT2 pré-vérification : compte déjà verrouillé
    // ----------------------------------------------------------------

    /**
     * Si le bucket compte dépasse le seuil AVANT vérification du mot de passe,
     * attempt() doit retourner 'locked' et enregistrer une tentative IP.
     */
    public function testAttemptReturnsLockedWhenAccountAlreadyLockedOut(): void
    {
        $account = $this->makeAccount();

        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($account);

        /** @var RateLimiterService&MockObject $rateLimiter */
        $rateLimiter = $this->createMock(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(false);
        $rateLimiter->method('getRetryAfter')->willReturn(900); // 15 min
        $rateLimiter->expects($this->once())->method('recordAttempt');

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('user@example.com', 'any', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('locked', $result['result']);
        $this->assertSame(15, $result['wait']); // ceil(900/60)
    }

    // ----------------------------------------------------------------
    // Compte soft-deleted avec identifiants valides
    // ----------------------------------------------------------------

    /**
     * Si le compte actif est introuvable mais qu'un compte soft-deleted valide existe
     * avec le bon mot de passe, attempt() doit retourner 'deleted'.
     */
    public function testAttemptReturnsDeletedForPendingDeletionAccount(): void
    {
        $deleted = [
            'id'                     => 99,
            'email'                  => 'deleted@example.com',
            'password'               => password_hash('correct_password', PASSWORD_BCRYPT),
            'scheduled_deletion_at'  => date('Y-m-d H:i:s', time() + 86400),
            'reactivation_token'     => 'token_xyz',
            'firstname'              => 'Alice',
            'company_name'           => null,
            'lang'                   => 'fr',
        ];

        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn(false);
        $accounts->method('findDeletedByEmail')->willReturn($deleted);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('deleted@example.com', 'correct_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('deleted', $result['result']);
        $this->assertNull($result['account']);
    }

    /**
     * Si le compte soft-deleted a un mot de passe incorrect, attempt() ne doit PAS retourner 'deleted'.
     */
    public function testAttemptDoesNotReturnDeletedForWrongPassword(): void
    {
        $deleted = [
            'id'                     => 99,
            'email'                  => 'deleted@example.com',
            'password'               => password_hash('correct_password', PASSWORD_BCRYPT),
            'scheduled_deletion_at'  => date('Y-m-d H:i:s', time() + 86400),
            'reactivation_token'     => 'token_xyz',
            'firstname'              => 'Alice',
            'company_name'           => null,
            'lang'                   => 'fr',
        ];

        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn(false);
        $accounts->method('findDeletedByEmail')->willReturn($deleted);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('deleted@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    // ----------------------------------------------------------------
    // Mauvais mot de passe — compte inconnu
    // ----------------------------------------------------------------

    /**
     * Email inconnu : attempt() doit retourner 'bad_credentials'.
     */
    public function testAttemptReturnsBadCredentialsWhenAccountNotFound(): void
    {
        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn(false);
        $accounts->method('findDeletedByEmail')->willReturn(false);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('unknown@example.com', 'any', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    /**
     * Email valide mais mot de passe incorrect : attempt() doit retourner 'bad_credentials'.
     */
    public function testAttemptReturnsBadCredentialsForWrongPassword(): void
    {
        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount());

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        /** @var SecurityAlertService&Stub $securityAlert */
        $securityAlert = $this->createStub(SecurityAlertService::class);
        $securityAlert->method('shouldAlertOnFailedLogins')->willReturn(false);
        $securityAlert->method('shouldAutoBlock')->willReturn(false);

        $service = $this->makeService($accounts, $rateLimiter, $securityAlert);
        $result  = $service->attempt('user@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
        $this->assertNull($result['account']);
    }

    // ----------------------------------------------------------------
    // Mauvais mot de passe — alerte admin
    // ----------------------------------------------------------------

    /**
     * Mauvais mot de passe sur un compte admin : sendAdminLoginAlert doit être appelé
     * si shouldAlertOnFailedLogins retourne true.
     */
    public function testAttemptSendsAdminAlertOnFailedLoginWhenThresholdReached(): void
    {
        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount(['role' => 'admin']));

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        /** @var SecurityAlertService&MockObject $securityAlert */
        $securityAlert = $this->createMock(SecurityAlertService::class);
        $securityAlert->method('shouldAlertOnFailedLogins')->willReturn(true);
        $securityAlert->method('shouldAutoBlock')->willReturn(false);
        $securityAlert->expects($this->once())->method('sendAdminLoginAlert');

        $service = $this->makeService($accounts, $rateLimiter, $securityAlert);
        $result  = $service->attempt('user@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    /**
     * Mauvais mot de passe sur un compte non-admin : sendAdminLoginAlert ne doit PAS être appelé.
     */
    public function testAttemptDoesNotSendAdminAlertForNonAdminAccount(): void
    {
        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount(['role' => 'customer']));

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        /** @var SecurityAlertService&MockObject $securityAlert */
        $securityAlert = $this->createMock(SecurityAlertService::class);
        $securityAlert->expects($this->never())->method('sendAdminLoginAlert');

        $service = $this->makeService($accounts, $rateLimiter, $securityAlert);
        $result  = $service->attempt('user@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    // ----------------------------------------------------------------
    // Mauvais mot de passe — auto-block admin
    // ----------------------------------------------------------------

    /**
     * Quand shouldAutoBlock retourne true, blockAccount doit être appelé sur le modèle injecté.
     */
    public function testAttemptAutoBlocksAdminWhenShouldAutoBlockIsTrue(): void
    {
        /** @var AccountModel&MockObject $accounts */
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount(['role' => 'admin']));
        $accounts->expects($this->once())->method('blockAccount')->with(42);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        /** @var SecurityAlertService&Stub $securityAlert */
        $securityAlert = $this->createStub(SecurityAlertService::class);
        $securityAlert->method('shouldAlertOnFailedLogins')->willReturn(false);
        $securityAlert->method('shouldAutoBlock')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter, $securityAlert);
        $result  = $service->attempt('user@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    /**
     * Quand firstname et lastname sont vides, displayName pour sendAdminAccountBlocked
     * utilise company_name, puis fallback email.
     * Vérifie que le code n'explose pas — blockAccount est appelé une fois.
     */
    public function testAttemptAutoBlockUsesEmailFallbackWhenNamesEmpty(): void
    {
        $account = $this->makeAccount([
            'role'         => 'admin',
            'account_type' => 'individual',
            'firstname'    => '',
            'lastname'     => '',
            'company_name' => null,
        ]);

        /** @var AccountModel&MockObject $accounts */
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($account);
        $accounts->expects($this->once())->method('blockAccount')->with(42);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        /** @var SecurityAlertService&Stub $securityAlert */
        $securityAlert = $this->createStub(SecurityAlertService::class);
        $securityAlert->method('shouldAlertOnFailedLogins')->willReturn(false);
        $securityAlert->method('shouldAutoBlock')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter, $securityAlert);
        $result  = $service->attempt('user@example.com', 'wrong_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('bad_credentials', $result['result']);
    }

    // ----------------------------------------------------------------
    // Email non vérifié
    // ----------------------------------------------------------------

    /**
     * Compte avec email non vérifié et bon mot de passe : attempt() doit retourner 'unverified'.
     */
    public function testAttemptReturnsUnverifiedWhenEmailNotConfirmed(): void
    {
        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount(['email_verified_at' => null]));

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        // Premier checkLimit (BT2) → true, deuxième (resend_verif) → false (quota épuisé)
        $rateLimiter->method('checkLimit')->willReturnOnConsecutiveCalls(true, false);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('user@example.com', 'correct_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('unverified', $result['result']);
        $this->assertNull($result['account']);
    }

    /**
     * Compte non vérifié dont le quota de renvoi n'est pas épuisé :
     * refreshVerificationToken doit être appelé.
     */
    public function testAttemptRefreshesVerificationTokenWhenResendQuotaAvailable(): void
    {
        /** @var AccountModel&MockObject $accounts */
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($this->makeAccount(['email_verified_at' => null]));
        $accounts->expects($this->once())->method('refreshVerificationToken');

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        // Premier checkLimit (BT2) → true, deuxième (resend_verif) → true (quota OK)
        $rateLimiter->method('checkLimit')->willReturnOnConsecutiveCalls(true, true);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('user@example.com', 'correct_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('unverified', $result['result']);
    }

    // ----------------------------------------------------------------
    // Connexion réussie — nominal
    // ----------------------------------------------------------------

    /**
     * Identifiants corrects et email vérifié : attempt() doit retourner 'ok' avec le compte.
     */
    public function testAttemptReturnsOkOnValidCredentials(): void
    {
        $account = $this->makeAccount();

        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn($account);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('user@example.com', 'correct_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('ok', $result['result']);
        $this->assertIsArray($result['account']);
        $this->assertSame(42, $result['account']['id']);
        $this->assertSame(0, $result['wait']);
    }

    // ----------------------------------------------------------------
    // resetCounters
    // ----------------------------------------------------------------

    /**
     * resetCounters doit appeler rateLimiter->reset() sur le bucket IP et le bucket compte.
     * Pour un compte customer, resetFailedLoginCount ne doit PAS être appelé.
     */
    public function testResetCountersCallsResetOnIpAndAccountKeys(): void
    {
        $account = $this->makeAccount();

        /** @var RateLimiterService&MockObject $rateLimiter */
        $rateLimiter = $this->createMock(RateLimiterService::class);
        $rateLimiter->expects($this->exactly(2))->method('reset');

        /** @var SecurityAlertService&MockObject $securityAlert */
        $securityAlert = $this->createMock(SecurityAlertService::class);
        $securityAlert->expects($this->never())->method('resetFailedLoginCount');

        $service = $this->makeService(null, $rateLimiter, $securityAlert);
        $service->resetCounters('login:1.1.1.1', $account);
    }

    /**
     * resetCounters doit appeler securityAlert->resetFailedLoginCount() pour un compte admin.
     */
    public function testResetCountersResetsAdminFailedLoginCount(): void
    {
        $account = $this->makeAccount(['role' => 'admin']);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);

        /** @var SecurityAlertService&MockObject $securityAlert */
        $securityAlert = $this->createMock(SecurityAlertService::class);
        $securityAlert->expects($this->once())->method('resetFailedLoginCount')->with(42);

        $service = $this->makeService(null, $rateLimiter, $securityAlert);
        $service->resetCounters('login:1.1.1.1', $account);
    }

    // ----------------------------------------------------------------
    // Suppression programmée — alerte session déjà envoyée
    // ----------------------------------------------------------------

    /**
     * Si l'alerte de suppression a déjà été envoyée dans la session courante,
     * le résultat doit rester 'deleted' (pas de double envoi).
     */
    public function testAttemptDoesNotResendDeletionAlertIfAlreadySentInSession(): void
    {
        $deleted = [
            'id'                     => 99,
            'email'                  => 'deleted@example.com',
            'password'               => password_hash('correct_password', PASSWORD_BCRYPT),
            'scheduled_deletion_at'  => date('Y-m-d H:i:s', time() + 86400),
            'reactivation_token'     => 'token_xyz',
            'firstname'              => 'Alice',
            'company_name'           => null,
            'lang'                   => 'fr',
        ];

        /** @var AccountModel&Stub $accounts */
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmail')->willReturn(false);
        $accounts->method('findDeletedByEmail')->willReturn($deleted);

        /** @var RateLimiterService&Stub $rateLimiter */
        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        // Simuler que l'alerte a déjà été envoyée
        $_SESSION['deletion_alert_sent_99'] = true;

        $service = $this->makeService($accounts, $rateLimiter);
        $result  = $service->attempt('deleted@example.com', 'correct_password', 'login:1.1.1.1', 'fr', '1.1.1.1');

        $this->assertSame('deleted', $result['result']);
    }
}
