<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires — Conformité Loi Évin (Art. L3323-4 CSP).
 *
 * Vérifie que tous les emails contenant une référence à des vins
 * ou envoyés via la newsletter incluent la mention légale obligatoire.
 *
 * FR : "L'abus d'alcool est dangereux pour la santé. À consommer avec modération."
 * EN : "Alcohol abuse is dangerous for your health. To be consumed in moderation."
 */
class MailServiceLoiEvinTest extends TestCase
{
    private const MENTION_FR = "L'abus d'alcool est dangereux pour la santé. À consommer avec modération.";
    private const MENTION_EN = 'Alcohol abuse is dangerous for your health. To be consumed in moderation.';

    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    /**
     * Crée un stub PHPMailer dont send() ne lance pas d'exception.
     */
    private function injectMockMailer(MailService $service): PHPMailer
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré

        return $stub;
    }

    /**
     * Appelle une méthode privée de MailService via Reflection.
     *
     * @param string $method Nom de la méthode
     * @param mixed  ...$args Arguments
     * @return string HTML retourné
     */
    private function callPrivate(string $method, mixed ...$args): string
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        return (string) $m->invoke($this->service, ...$args);
    }

    // ----------------------------------------------------------------
    // emailFooterHtml — footer partagé de tous les emails
    // ----------------------------------------------------------------

    /**
     * Le footer email partagé doit contenir la mention Loi Évin en français.
     */
    public function testEmailFooterHtmlContainsLoiEvinFr(): void
    {
        $html = $this->callPrivate(
            'emailFooterHtml',
            'https://crabitan.local/fr/politique-confidentialite',
            'https://crabitan.local/fr/mentions-legales',
            'https://crabitan.local/fr/support',
            'https://crabitan.local/fr/conditions-generales-de-vente'
        );

        $this->assertStringContainsString(
            self::MENTION_FR,
            $html,
            'Le footer email doit contenir la mention Loi Évin en français (Art. L3323-4 CSP).'
        );
    }

    /**
     * Le footer email partagé doit contenir la mention Loi Évin en anglais.
     */
    public function testEmailFooterHtmlContainsLoiEvinEn(): void
    {
        $html = $this->callPrivate(
            'emailFooterHtml',
            'https://crabitan.local/fr/politique-confidentialite',
            'https://crabitan.local/fr/mentions-legales',
            'https://crabitan.local/fr/support',
            'https://crabitan.local/fr/conditions-generales-de-vente',
            'en'
        );

        $this->assertStringContainsString(
            self::MENTION_EN,
            $html,
            'Le footer email doit contenir la mention Loi Évin en anglais (Art. L3323-4 CSP).'
        );
    }

    /**
     * La mention ne doit pas être dans un commentaire HTML (critère 6).
     */
    public function testLoiEvinNotInHtmlComment(): void
    {
        $html = $this->callPrivate(
            'emailFooterHtml',
            'https://crabitan.local/fr/politique-confidentialite',
            'https://crabitan.local/fr/mentions-legales',
            'https://crabitan.local/fr/support',
            'https://crabitan.local/fr/conditions-generales-de-vente'
        );

        // Vérifie qu'aucun commentaire HTML ne contient la mention
        $this->assertDoesNotMatchRegularExpression(
            '/<!--[^>]*abus d\'alcool[^>]*-->/i',
            $html,
            'La mention Loi Évin ne doit pas être cachée dans un commentaire HTML.'
        );
    }

    // ----------------------------------------------------------------
    // buildNewsletterHtml — newsletter vin disponible
    // ----------------------------------------------------------------

    /**
     * La newsletter HTML doit contenir la mention Loi Évin française.
     */
    public function testBuildNewsletterHtmlContainsLoiEvinFr(): void
    {
        $html = $this->service->buildNewsletterHtml(
            'Nouveau millésime disponible',
            '<p>Découvrez notre nouveau Château Crabitan Bellevue.</p>',
            null,
            'token123'
        );

        $this->assertStringContainsString(
            self::MENTION_FR,
            $html,
            'buildNewsletterHtml() doit inclure la mention Loi Évin (FR) dans le footer email.'
        );
    }

    /**
     * La newsletter HTML doit contenir la mention Loi Évin anglaise.
     */
    public function testBuildNewsletterHtmlContainsLoiEvinEn(): void
    {
        $html = $this->service->buildNewsletterHtml(
            'New vintage available',
            '<p>Discover our new Château Crabitan Bellevue.</p>',
            null,
            'token456',
            'en'
        );

        $this->assertStringContainsString(
            self::MENTION_EN,
            $html,
            'buildNewsletterHtml() doit inclure la mention Loi Évin (EN) dans le footer email.'
        );
    }

    // ----------------------------------------------------------------
    // sendNewWineNewsletter — email transactionnel vin
    // ----------------------------------------------------------------

    /**
     * L'email "nouveau vin disponible" en FR doit contenir la mention Loi Évin.
     */
    public function testSendNewWineNewsletterFrContainsLoiEvin(): void
    {
        $stub = $this->injectMockMailer($this->service);

        $wine = [
            'label_name'          => 'Château Crabitan Bellevue',
            'vintage'             => 2022,
            'certification_label' => 'AOC Sainte-Croix-du-Mont',
            'is_cuvee_speciale'   => false,
            'award'               => null,
            'image_path'          => '',
            'slug'                => 'crabitan-bellevue-2022',
        ];

        $this->service->sendNewWineNewsletter(
            'subscriber@example.com',
            'Jean Dupont',
            'unsub-token',
            $wine,
            'http://crabitan.local',
            'fr'
        );

        $this->assertStringContainsString(
            self::MENTION_FR,
            $stub->Body,
            'sendNewWineNewsletter() FR doit inclure la mention Loi Évin.'
        );
    }

    /**
     * L'email "nouveau vin disponible" en EN doit contenir la mention Loi Évin.
     */
    public function testSendNewWineNewsletterEnContainsLoiEvin(): void
    {
        $stub = $this->injectMockMailer($this->service);

        $wine = [
            'label_name'          => 'Château Crabitan Bellevue',
            'vintage'             => 2022,
            'certification_label' => 'AOC Sainte-Croix-du-Mont',
            'is_cuvee_speciale'   => false,
            'award'               => null,
            'image_path'          => '',
            'slug'                => 'crabitan-bellevue-2022',
        ];

        $this->service->sendNewWineNewsletter(
            'subscriber@example.com',
            'John Smith',
            'unsub-token',
            $wine,
            'http://crabitan.local',
            'en'
        );

        $this->assertStringContainsString(
            self::MENTION_EN,
            $stub->Body,
            'sendNewWineNewsletter() EN doit inclure la mention Loi Évin.'
        );
    }

    // ----------------------------------------------------------------
    // emailSimpleLayout — emails transactionnels génériques
    // ----------------------------------------------------------------

    /**
     * Le layout email simple (emails transactionnels) doit contenir la mention Loi Évin.
     * Ce layout est utilisé pour les commandes, confirmations, etc.
     */
    public function testEmailSimpleLayoutContainsLoiEvinFr(): void
    {
        $html = $this->callPrivate(
            'emailSimpleLayout',
            'Test',
            'Bonjour,',
            'Votre commande de Château Crabitan Bellevue 2022 a été reçue.'
        );

        $this->assertStringContainsString(
            self::MENTION_FR,
            $html,
            'emailSimpleLayout() doit inclure la mention Loi Évin (FR).'
        );
    }

    /**
     * Le layout email avec bouton CTA doit contenir la mention Loi Évin.
     * Ce layout est utilisé pour les emails de vérification, reset mot de passe, etc.
     */
    public function testEmailLayoutContainsLoiEvinFr(): void
    {
        $html = $this->callPrivate(
            'emailLayout',
            'Activation',
            'Bonjour,',
            'Merci de vous être inscrit.',
            'https://example.com/verify',
            'Activer mon compte',
            'Ce lien expire dans 24h.'
        );

        $this->assertStringContainsString(
            self::MENTION_FR,
            $html,
            'emailLayout() doit inclure la mention Loi Évin (FR).'
        );
    }
}
