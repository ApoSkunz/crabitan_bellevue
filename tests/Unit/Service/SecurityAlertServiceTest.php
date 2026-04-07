<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\MailService;
use Service\RateLimiterService;
use Service\SecurityAlertService;

/**
 * Tests unitaires de SecurityAlertService.
 *
 * Vérifie shouldAlertOnFailedLogins() en simulant le RateLimiterService
 * et l'envoi d'emails via un stub MailService (sans SMTP réel).
 */
class SecurityAlertServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // shouldAlertOnFailedLogins — contrôle du seuil
    // ----------------------------------------------------------------

    /**
     * Avec un stub RateLimiterService dont recall retourne 3 (seuil atteint),
     * shouldAlertOnFailedLogins doit retourner true.
     */
    public function testShouldAlertReturnsTrueWhenThresholdReached(): void
    {
        $rl = $this->createMock(RateLimiterService::class);

        // recordAttempt appelé une fois
        $rl->expects($this->once())->method('recordAttempt');

        // recall retourne 3 (exactement le seuil)
        $rl->method('recall')->willReturn(3);

        $service = new SecurityAlertService($rl);
        $result  = $service->shouldAlertOnFailedLogins(42, 5, 3);

        $this->assertTrue($result);
    }

    /**
     * Avec un recall retournant 2 (sous le seuil), shouldAlertOnFailedLogins doit retourner false.
     */
    public function testShouldAlertReturnsFalseWhenBelowThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(2);

        $service = new SecurityAlertService($rl);
        $result  = $service->shouldAlertOnFailedLogins(42, 5, 3);

        $this->assertFalse($result);
    }

    /**
     * Avec un recall retournant 4 (au-delà du seuil), shouldAlertOnFailedLogins doit retourner false.
     * L'alerte ne se déclenche qu'une seule fois (au passage exact du seuil).
     */
    public function testShouldAlertReturnsFalseWhenAboveThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(4);

        $service = new SecurityAlertService($rl);
        $result  = $service->shouldAlertOnFailedLogins(42, 5, 3);

        $this->assertFalse($result);
    }

    /**
     * Avec un recall retournant 0, shouldAlertOnFailedLogins doit retourner false.
     */
    public function testShouldAlertReturnsFalseWhenZeroFailures(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(0);

        $service = new SecurityAlertService($rl);
        $result  = $service->shouldAlertOnFailedLogins(1);

        $this->assertFalse($result);
    }

    /**
     * shouldAlertOnFailedLogins respecte les paramètres personnalisés (seuil 5).
     */
    public function testShouldAlertUsesCustomThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(5);

        $service = new SecurityAlertService($rl);
        $this->assertTrue($service->shouldAlertOnFailedLogins(99, 10, 5));
    }

    // ----------------------------------------------------------------
    // shouldAutoBlock — blocage définitif à 5 échecs
    // ----------------------------------------------------------------

    /**
     * recordAutoBlockFailure appelle recordAttempt avec une fenêtre de 1h (3600s).
     */
    public function testRecordAutoBlockFailureCallsRecordAttempt(): void
    {
        $rl = $this->createMock(RateLimiterService::class);
        $rl->expects($this->once())
            ->method('recordAttempt')
            ->with(
                $this->stringContains('42'),
                SecurityAlertService::AUTO_BLOCK_WINDOW_MIN * 60
            );

        $service = new SecurityAlertService($rl);
        $service->recordAutoBlockFailure(42);
    }

    /**
     * shouldAutoBlock retourne true quand le compteur atteint AUTO_BLOCK_THRESHOLD (5).
     */
    public function testShouldAutoBlockReturnsTrueAtThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(SecurityAlertService::AUTO_BLOCK_THRESHOLD);

        $service = new SecurityAlertService($rl);
        $this->assertTrue($service->shouldAutoBlock(42));
    }

    /**
     * shouldAutoBlock retourne true quand le compteur dépasse le seuil (ex: 7).
     */
    public function testShouldAutoBlockReturnsTrueAboveThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(7);

        $service = new SecurityAlertService($rl);
        $this->assertTrue($service->shouldAutoBlock(42));
    }

    /**
     * shouldAutoBlock retourne false sous le seuil (4 échecs).
     */
    public function testShouldAutoBlockReturnsFalseBelowThreshold(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(4);

        $service = new SecurityAlertService($rl);
        $this->assertFalse($service->shouldAutoBlock(42));
    }

    /**
     * shouldAutoBlock retourne false à 0 échec.
     */
    public function testShouldAutoBlockReturnsFalseAtZero(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('recall')->willReturn(0);

        $service = new SecurityAlertService($rl);
        $this->assertFalse($service->shouldAutoBlock(1));
    }

    // ----------------------------------------------------------------
    // resetFailedLoginCount
    // ----------------------------------------------------------------

    /**
     * resetFailedLoginCount appelle RateLimiterService::reset deux fois
     * (compteur alerte 5 min + compteur cumul 1h).
     */
    public function testResetFailedLoginCountCallsReset(): void
    {
        $rl = $this->createMock(RateLimiterService::class);
        $rl->expects($this->exactly(2))
            ->method('reset')
            ->with($this->stringContains('42'));

        $service = new SecurityAlertService($rl);
        $service->resetFailedLoginCount(42);
    }

    // ----------------------------------------------------------------
    // sendAdminLoginAlert — ne doit pas lancer d'exception
    // ----------------------------------------------------------------

    /**
     * sendAdminLoginAlert n'exception pas quand MailService fonctionne.
     */
    public function testSendAdminLoginAlertDoesNotThrow(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->atLeastOnce())->method('sendAdminLoginFailureAlert');

        $_ENV['MAINTAINER_MAIL'] = '';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendAdminLoginAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Alice', 'lang' => 'fr'],
            '127.0.0.1'
        );

        $this->assertTrue(true);
    }

    /**
     * sendAdminLoginAlert envoie aussi au maintainer si MAINTAINER_MAIL est différent.
     */
    public function testSendAdminLoginAlertNotifiesMaintainerSeparately(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->exactly(2))->method('sendAdminLoginFailureAlert');

        $_ENV['MAINTAINER_MAIL'] = 'maintainer@example.com';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendAdminLoginAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Alice', 'lang' => 'fr'],
            '127.0.0.1'
        );

        unset($_ENV['MAINTAINER_MAIL']);
        $this->assertTrue(true);
    }

    /**
     * sendAdminLoginAlert n'envoie qu'un seul email si admin = maintainer.
     */
    public function testSendAdminLoginAlertSingleEmailWhenSameAddress(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->once())->method('sendAdminLoginFailureAlert');

        $_ENV['MAINTAINER_MAIL'] = 'admin@test.local';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendAdminLoginAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Alice', 'lang' => 'fr'],
            '127.0.0.1'
        );

        unset($_ENV['MAINTAINER_MAIL']);
        $this->assertTrue(true);
    }

    /**
     * sendAdminLoginAlert ne lève pas d'exception si MailService::send échoue.
     */
    public function testSendAdminLoginAlertIsSilentOnMailFailure(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createStub(MailService::class);
        $mail->method('sendAdminLoginFailureAlert')
            ->willThrowException(new \RuntimeException('SMTP down'));

        $_ENV['MAINTAINER_MAIL'] = '';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendAdminLoginAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Alice', 'lang' => 'fr'],
            '10.0.0.1'
        );

        $this->assertTrue(true); // Pas d'exception propagée
    }

    // ----------------------------------------------------------------
    // sendPasswordChangeAlert — ne doit pas lancer d'exception
    // ----------------------------------------------------------------

    /**
     * sendPasswordChangeAlert n'exception pas quand MailService fonctionne.
     */
    public function testSendPasswordChangeAlertDoesNotThrow(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->atLeastOnce())->method('sendAdminPasswordChangeAlert');

        $_ENV['MAINTAINER_MAIL'] = '';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendPasswordChangeAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Bob', 'lang' => 'en'],
            '192.168.1.1',
            'Mozilla/5.0 (Windows NT 10.0)'
        );

        $this->assertTrue(true);
    }

    /**
     * sendPasswordChangeAlert envoie au maintainer si adresse différente.
     */
    public function testSendPasswordChangeAlertNotifiesMaintainer(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->exactly(2))->method('sendAdminPasswordChangeAlert');

        $_ENV['MAINTAINER_MAIL'] = 'maintainer@example.com';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendPasswordChangeAlert(
            ['email' => 'admin@test.local', 'firstname' => 'Bob', 'lang' => 'fr'],
            '192.168.1.1',
            'Chrome/120'
        );

        unset($_ENV['MAINTAINER_MAIL']);
        $this->assertTrue(true);
    }

    /**
     * sendPasswordChangeAlert est silencieux en cas d'échec SMTP.
     */
    public function testSendPasswordChangeAlertIsSilentOnMailFailure(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createStub(MailService::class);
        $mail->method('sendAdminPasswordChangeAlert')
            ->willThrowException(new \RuntimeException('SMTP down'));

        $_ENV['MAINTAINER_MAIL'] = '';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendPasswordChangeAlert(
            ['email' => 'admin@test.local', 'company_name' => 'Acme', 'lang' => 'fr'],
            '10.0.0.1',
            'Safari'
        );

        $this->assertTrue(true);
    }

    /**
     * sendPasswordChangeAlert utilise company_name si firstname absent.
     */
    public function testSendPasswordChangeAlertUsesCompanyName(): void
    {
        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->once())
            ->method('sendAdminPasswordChangeAlert')
            ->with(
                $this->anything(),
                $this->equalTo('Château Crabitan'), // company_name
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $_ENV['MAINTAINER_MAIL'] = '';

        $service = new SecurityAlertService($rl, $mail);
        $service->sendPasswordChangeAlert(
            ['email' => 'cave@crabitan.fr', 'company_name' => 'Château Crabitan', 'lang' => 'fr'],
            '10.0.0.1',
            'Firefox'
        );
    }
}
