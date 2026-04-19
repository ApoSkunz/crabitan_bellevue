<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\MailChannel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Service\MailService;

/**
 * Vérifie que applySmtpConfig() sélectionne les bons credentials SMTP
 * selon le MailChannel utilisé par chaque méthode d'envoi.
 */
#[BackupGlobals(true)]
class MailServiceChannelRoutingTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(MailService::class);

        $_ENV['MAIL_HOST']       = 'smtp.default.test';
        $_ENV['MAIL_PORT']       = '587';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';

        $_ENV['MAIL_NOREPLY_USER'] = 'noreply@noreply.test';
        $_ENV['MAIL_NOREPLY_PASS'] = 'pass-noreply';

        $_ENV['MAIL_SECURITY_HOST']       = 'smtp.security.test';
        $_ENV['MAIL_SECURITY_PORT']       = '587';
        $_ENV['MAIL_SECURITY_USER']       = 'security@security.test';
        $_ENV['MAIL_SECURITY_PASS']       = 'pass-security';
        $_ENV['MAIL_SECURITY_ENCRYPTION'] = 'tls';

        $_ENV['MAIL_NEWSLETTER_HOST']       = 'smtp.newsletter.test';
        $_ENV['MAIL_NEWSLETTER_PORT']       = '587';
        $_ENV['MAIL_NEWSLETTER_USER']       = 'newsletter@newsletter.test';
        $_ENV['MAIL_NEWSLETTER_PASS']       = 'pass-newsletter';
        $_ENV['MAIL_NEWSLETTER_ENCRYPTION'] = 'tls';

        $_ENV['MAIL_CONTACT_HOST']       = 'smtp.contact.test';
        $_ENV['MAIL_CONTACT_PORT']       = '587';
        $_ENV['MAIL_CONTACT_USER']       = 'contact@contact.test';
        $_ENV['MAIL_CONTACT_PASS']       = 'pass-contact';
        $_ENV['MAIL_CONTACT_ENCRYPTION'] = 'tls';
    }

    /**
     * Retourne le PHPMailer injecté dans le service via Reflection.
     *
     * @param MailService $service Instance à inspecter
     * @return PHPMailer Instance PHPMailer du service
     */
    private function getMailer(MailService $service): PHPMailer
    {
        $prop = $this->reflection->getProperty('mailer');
        $prop->setAccessible(true);
        return $prop->getValue($service);
    }

    /**
     * Appelle applySmtpConfig() directement via Reflection.
     *
     * @param MailService $service Instance du service
     * @param MailChannel $channel Canal à appliquer
     */
    private function applyChannel(MailService $service, MailChannel $channel): void
    {
        $method = $this->reflection->getMethod('applySmtpConfig');
        $method->setAccessible(true);
        $method->invoke($service, $channel);
    }

    /**
     * Canal TRANSACTIONNEL utilise MAIL_NOREPLY_*.
     */
    public function testTransactionnelUsesNoreplySmtp(): void
    {
        $service = new MailService();
        $this->applyChannel($service, MailChannel::TRANSACTIONNEL);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.default.test', $mailer->Host);
        $this->assertSame('noreply@noreply.test', $mailer->Username);
    }

    /**
     * Canal SECURITY utilise MAIL_SECURITY_*.
     */
    public function testSecurityChannelUsesDedicatedSmtp(): void
    {
        $service = new MailService();
        $this->applyChannel($service, MailChannel::SECURITY);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.security.test', $mailer->Host);
        $this->assertSame('security@security.test', $mailer->Username);
    }

    /**
     * Canal NEWSLETTER utilise MAIL_NEWSLETTER_*.
     */
    public function testNewsletterChannelUsesDedicatedSmtp(): void
    {
        $service = new MailService();
        $this->applyChannel($service, MailChannel::NEWSLETTER);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.newsletter.test', $mailer->Host);
        $this->assertSame('newsletter@newsletter.test', $mailer->Username);
    }

    /**
     * Canal CONTACT utilise MAIL_CONTACT_*.
     */
    public function testContactChannelUsesDedicatedSmtp(): void
    {
        $service = new MailService();
        $this->applyChannel($service, MailChannel::CONTACT);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.contact.test', $mailer->Host);
        $this->assertSame('contact@contact.test', $mailer->Username);
    }

    /**
     * HOST partagé — MAIL_SECURITY_HOST absent utilise MAIL_HOST.
     */
    public function testChannelFallsBackToSharedHostWhenSpecificHostAbsent(): void
    {
        unset($_ENV['MAIL_SECURITY_HOST']);

        $service = new MailService();
        $this->applyChannel($service, MailChannel::SECURITY);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.default.test', $mailer->Host);
        $this->assertSame('security@security.test', $mailer->Username);
    }
}
