<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Exception\NewsletterException;
use PHPUnit\Framework\TestCase;
use Service\NewsletterService;
use Service\MailService;
use Model\AccountModel;
use Model\NewsletterSubscriptionModel;

/**
 * Tests unitaires pour NewsletterService.
 *
 * Règle PHPUnit 13 :
 *   - createStub()  → retourne une valeur configurée, sans vérification d'appel
 *   - createMock()  → vérifie que la méthode est (ou n'est pas) appelée via expects()
 */
class NewsletterServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un NewsletterService avec des stubs par défaut.
     * Les dépendances à vérifier (expects) doivent être passées en paramètre.
     *
     * @param NewsletterSubscriptionModel|null $model
     * @param MailService|null                 $mailer
     * @param AccountModel|null                $accounts
     * @return array{NewsletterService, NewsletterSubscriptionModel, MailService, AccountModel}
     */
    private function makeService(
        ?NewsletterSubscriptionModel $model = null,
        ?MailService $mailer = null,
        ?AccountModel $accounts = null
    ): array {
        $model    ??= $this->createStub(NewsletterSubscriptionModel::class);
        $mailer   ??= $this->createStub(MailService::class);
        $accounts ??= $this->createStub(AccountModel::class);
        $service  = new NewsletterService($model, $mailer, $accounts);
        return [$service, $model, $mailer, $accounts];
    }

    // ================================================================
    // confirmSubscription — flux visiteur
    // ================================================================

    public function testConfirmSubscriptionWithValidToken(): void
    {
        $model    = $this->createMock(NewsletterSubscriptionModel::class);
        [$service] = $this->makeService($model);

        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $model->method('findPendingByTokenHash')->willReturn([
            'id'                          => 1,
            'email'                       => 'test@example.com',
            'newsletter_token_hash'       => $hashedToken,
            'newsletter_token_expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'newsletter_confirmed'        => 0,
        ]);
        $model->expects($this->once())->method('confirmByTokenHash');

        $service->confirmSubscription($rawToken);
        $this->assertTrue(true);
    }

    public function testConfirmSubscriptionWithExpiredToken(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        [$service] = $this->makeService($model);

        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $model->method('findPendingByTokenHash')->willReturn([
            'id'                          => 2,
            'email'                       => 'expired@example.com',
            'newsletter_token_hash'       => $hashedToken,
            'newsletter_token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'newsletter_confirmed'        => 0,
        ]);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('expired');

        $service->confirmSubscription($rawToken);
    }

    public function testConfirmSubscriptionWithUnknownTokenThrowsInvalid(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $model->method('findPendingByTokenHash')->willReturn(null);
        $accounts->method('confirmNewsletterByToken')->willReturn(false);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('invalid');

        $service->confirmSubscription(bin2hex(random_bytes(32)));
    }

    // ================================================================
    // confirmSubscription — flux accounts
    // ================================================================

    public function testConfirmSubscriptionFallsBackToAccountToken(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createMock(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $model->method('findPendingByTokenHash')->willReturn(null);
        $accounts->expects($this->once())
                 ->method('confirmNewsletterByToken')
                 ->willReturn(['id' => 42]);

        $service->confirmSubscription(bin2hex(random_bytes(32)));
        $this->assertTrue(true);
    }

    // ================================================================
    // subscribe — flux visiteur
    // ================================================================

    public function testSubscribeBlockedByRateLimit(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(3);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('rate_limit');

        $service->subscribe('test@example.com', 'fr', '127.0.0.1');
    }

    public function testSubscribeAlreadyConfirmedVisitorThrows(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(0);
        $model->method('findByEmail')->willReturn([
            'id'                   => 5,
            'email'                => 'already@example.com',
            'newsletter_confirmed' => 1,
        ]);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('already_confirmed');

        $service->subscribe('already@example.com', 'fr', '127.0.0.1');
    }

    public function testSubscribeVisitorSendsConfirmationEmail(): void
    {
        $model    = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer   = $this->createMock(MailService::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, $mailer, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(0);
        $model->method('findByEmail')->willReturn(null);
        $model->expects($this->once())->method('upsertPending');
        $mailer->expects($this->once())->method('sendNewsletterConfirmation');

        $service->subscribe('visitor@example.com', 'fr', '127.0.0.1');
        $this->assertTrue(true);
    }

    // ================================================================
    // subscribe — flux accounts
    // ================================================================

    public function testSubscribeAlreadyConfirmedAccountThrows(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService(null, null, $accounts);

        $accounts->method('findByEmail')->willReturn([
            'id'         => 10,
            'email'      => 'client@example.com',
            'newsletter' => 1,
        ]);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('already_confirmed');

        $service->subscribe('client@example.com', 'fr', '127.0.0.1');
    }

    public function testSubscribeAccountRoutesViaAccountModel(): void
    {
        $model    = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer   = $this->createMock(MailService::class);
        $accounts = $this->createMock(AccountModel::class);
        [$service] = $this->makeService($model, $mailer, $accounts);

        $accounts->method('findByEmail')->willReturn([
            'id'         => 11,
            'email'      => 'client@example.com',
            'newsletter' => 0,
        ]);
        $accounts->expects($this->once())->method('storeNewsletterConfirmToken');
        $mailer->expects($this->once())->method('sendNewsletterConfirmation');
        $model->expects($this->never())->method('upsertPending');

        $service->subscribe('client@example.com', 'fr', '127.0.0.1');
        $this->assertTrue(true);
    }

    // ================================================================
    // resendConfirmation — flux visiteur
    // ================================================================

    public function testResendConfirmationBlockedAfterThreeAttempts(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(3);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('rate_limit');

        $service->resendConfirmation('test@example.com', 'fr');
    }

    public function testResendConfirmationVisitorNotFoundThrows(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(0);
        $model->method('findByEmail')->willReturn(null);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('not_found');

        $service->resendConfirmation('unknown@example.com', 'fr');
    }

    public function testResendConfirmationVisitorAlreadyConfirmedThrowsNotFound(): void
    {
        $model    = $this->createStub(NewsletterSubscriptionModel::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, null, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(0);
        $model->method('findByEmail')->willReturn([
            'id'                   => 3,
            'email'                => 'done@example.com',
            'newsletter_confirmed' => 1,
        ]);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('not_found');

        $service->resendConfirmation('done@example.com', 'fr');
    }

    public function testResendConfirmationVisitorSendsEmail(): void
    {
        $model    = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer   = $this->createMock(MailService::class);
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService($model, $mailer, $accounts);

        $accounts->method('findByEmail')->willReturn(false);
        $model->method('countRecentAttempts')->willReturn(1);
        $model->method('findByEmail')->willReturn([
            'id'                   => 4,
            'email'                => 'pending@example.com',
            'newsletter_confirmed' => 0,
        ]);
        $model->expects($this->once())->method('upsertPending');
        $mailer->expects($this->once())->method('sendNewsletterConfirmation');

        $service->resendConfirmation('pending@example.com', 'fr');
        $this->assertTrue(true);
    }

    // ================================================================
    // resendConfirmation — flux accounts
    // ================================================================

    public function testResendConfirmationAccountAlreadyConfirmedThrowsNotFound(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        [$service] = $this->makeService(null, null, $accounts);

        $accounts->method('findByEmail')->willReturn([
            'id'         => 10,
            'email'      => 'client@example.com',
            'newsletter' => 1,
        ]);

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('not_found');

        $service->resendConfirmation('client@example.com', 'fr');
    }

    public function testResendConfirmationAccountSendsEmail(): void
    {
        $mailer   = $this->createMock(MailService::class);
        $accounts = $this->createMock(AccountModel::class);
        [$service] = $this->makeService(null, $mailer, $accounts);

        $accounts->method('findByEmail')->willReturn([
            'id'         => 11,
            'email'      => 'pending-account@example.com',
            'newsletter' => 0,
        ]);
        $accounts->expects($this->once())->method('storeNewsletterConfirmToken');
        $mailer->expects($this->once())->method('sendNewsletterConfirmation');

        $service->resendConfirmation('pending-account@example.com', 'fr');
        $this->assertTrue(true);
    }
}
