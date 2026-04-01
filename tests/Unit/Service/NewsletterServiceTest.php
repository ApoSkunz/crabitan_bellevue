<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\NewsletterService;
use Service\MailService;
use Model\NewsletterSubscriptionModel;
use Service\RateLimiterService;

/**
 * Tests unitaires pour NewsletterService.
 *
 * Les dépendances (Model, MailService, RateLimiter) sont mockées
 * afin de tester la logique métier de manière isolée.
 */
class NewsletterServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // confirmSubscription — token valide
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionWithValidToken(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $model->method('findPendingByTokenHash')->willReturn([
            'id'                            => 1,
            'email'                         => 'test@example.com',
            'newsletter_token_hash'         => $hashedToken,
            'newsletter_token_expires_at'   => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'newsletter_confirmed'          => 0,
        ]);

        $model->expects($this->once())->method('confirmByTokenHash');

        $service = new NewsletterService($model, $mailer);
        $service->confirmSubscription($rawToken);

        // Si aucune exception n'est levée, le comportement nominal est validé
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token expiré
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionWithExpiredToken(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $model->method('findPendingByTokenHash')->willReturn([
            'id'                            => 2,
            'email'                         => 'expired@example.com',
            'newsletter_token_hash'         => $hashedToken,
            'newsletter_token_expires_at'   => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'newsletter_confirmed'          => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired');

        $service = new NewsletterService($model, $mailer);
        $service->confirmSubscription($rawToken);
    }

    // ----------------------------------------------------------------
    // confirmSubscription — déjà confirmé (idempotence)
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionAlreadyConfirmed(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        // findPendingByTokenHash retourne null si déjà confirmé (filtre WHERE confirmed = 0)
        $model->method('findPendingByTokenHash')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid');

        $service = new NewsletterService($model, $mailer);
        $service->confirmSubscription(bin2hex(random_bytes(32)));
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token inconnu
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionWithUnknownToken(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        $model->method('findPendingByTokenHash')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid');

        $service = new NewsletterService($model, $mailer);
        $service->confirmSubscription(bin2hex(random_bytes(32)));
    }

    // ----------------------------------------------------------------
    // subscribe — rate limiting bloqué
    // ----------------------------------------------------------------

    public function testSubscribeBlockedByRateLimit(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        // countRecentAttempts retourne 3 (= seuil atteint)
        $model->method('countRecentAttempts')
              ->willReturn(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate_limit');

        $service = new NewsletterService($model, $mailer);
        $service->subscribe('test@example.com', 'fr', '127.0.0.1');
    }

    // ----------------------------------------------------------------
    // subscribe — email déjà confirmé (pas de nouvel envoi)
    // ----------------------------------------------------------------

    public function testSubscribeAlreadyConfirmedEmailThrows(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        $model->method('countRecentAttempts')->willReturn(0);
        $model->method('findByEmail')->willReturn([
            'id'                    => 5,
            'email'                 => 'already@example.com',
            'newsletter_confirmed'  => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already_confirmed');

        $service = new NewsletterService($model, $mailer);
        $service->subscribe('already@example.com', 'fr', '127.0.0.1');
    }

    // ----------------------------------------------------------------
    // resendConfirmation — rate limiting 3/24h atteint
    // ----------------------------------------------------------------

    public function testResendConfirmationBlockedAfterThreeAttempts(): void
    {
        $model   = $this->createMock(NewsletterSubscriptionModel::class);
        $mailer  = $this->createMock(MailService::class);
        $limiter = $this->createMock(RateLimiterService::class);

        $model->method('countRecentAttempts')->willReturn(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate_limit');

        $service = new NewsletterService($model, $mailer);
        $service->resendConfirmation('test@example.com', 'fr');
    }
}
