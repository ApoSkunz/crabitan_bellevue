<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\MailChannel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
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

        // Partagé tous canaux
        $_ENV['MAIL_HOST']       = 'smtp.shared.test';
        $_ENV['MAIL_PORT']       = '587';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';
        $_ENV['MAIL_FROM_NAME']  = 'Test';

        // USER/PASS par canal
        $_ENV['MAIL_NOREPLY_USER']      = 'noreply@noreply.test';
        $_ENV['MAIL_NOREPLY_PASS']      = 'pass-noreply';
        $_ENV['MAIL_SECURITY_USER']     = 'security@security.test';
        $_ENV['MAIL_SECURITY_PASS']     = 'pass-security';
        $_ENV['MAIL_NEWSLETTER_USER']   = 'newsletter@newsletter.test';
        $_ENV['MAIL_NEWSLETTER_PASS']   = 'pass-newsletter';
        $_ENV['MAIL_CONTACT_USER']      = 'contact@contact.test';
        $_ENV['MAIL_CONTACT_PASS']      = 'pass-contact';
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
     * Tous les canaux partagent MAIL_HOST — seul USER/PASS diffèrent.
     */
    #[DataProvider('channelProvider')]
    public function testAllChannelsUseSharedHost(MailChannel $channel, string $expectedUser): void
    {
        $service = new MailService();
        $this->applyChannel($service, $channel);
        $mailer = $this->getMailer($service);

        $this->assertSame('smtp.shared.test', $mailer->Host);
        $this->assertSame($expectedUser, $mailer->Username);
    }

    /**
     * @return array<string, array{MailChannel, string}>
     */
    public static function channelProvider(): array
    {
        return [
            'transactionnel' => [MailChannel::TRANSACTIONNEL, 'noreply@noreply.test'],
            'security'       => [MailChannel::SECURITY,       'security@security.test'],
            'newsletter'     => [MailChannel::NEWSLETTER,     'newsletter@newsletter.test'],
            'contact'        => [MailChannel::CONTACT,        'contact@contact.test'],
        ];
    }

    /**
     * Sans MAIL_NOREPLY_USER → SMTPAuth désactivé, pas d'erreur.
     */
    public function testChannelWithoutUserDisablesSMTPAuth(): void
    {
        unset($_ENV['MAIL_NOREPLY_USER'], $_ENV['MAIL_NOREPLY_PASS']);

        $service = new MailService();
        $this->applyChannel($service, MailChannel::TRANSACTIONNEL);
        $mailer = $this->getMailer($service);

        $this->assertFalse($mailer->SMTPAuth);
    }
}
