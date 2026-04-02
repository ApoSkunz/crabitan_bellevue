<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\PageController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour PageController.
 * Couvre les 6 pages statiques : château, savoir-faire, contact,
 * mentions légales, plan du site, webmaster.
 */
class PageControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
    }

    private function makeController(string $uri = '/fr'): PageController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new PageController(new Request());
    }

    // ----------------------------------------------------------------
    // chateau
    // ----------------------------------------------------------------

    public function testChateauRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/le-chateau')->chateau(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('chateau', $output);
    }

    // ----------------------------------------------------------------
    // savoirFaire
    // ----------------------------------------------------------------

    public function testSavoirFaireRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/savoir-faire')->savoirFaire(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('savoir', $output);
    }

    // ----------------------------------------------------------------
    // contact
    // ----------------------------------------------------------------

    public function testContactRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/contact')->contact(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('contact', $output);
    }

    // ----------------------------------------------------------------
    // mentionsLegales
    // ----------------------------------------------------------------

    public function testMentionsLegalesRendersView(): void
    {
        $_COOKIE['age_verified'] = '1';
        ob_start();
        $this->makeController('/fr/mentions-legales')->mentionsLegales(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    // ----------------------------------------------------------------
    // planDuSite
    // ----------------------------------------------------------------

    public function testPlanDuSiteRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/plan-du-site')->planDuSite(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('sitemap', $output);
    }

    // ----------------------------------------------------------------
    // webmaster
    // ----------------------------------------------------------------

    public function testWebmasterRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/webmaster')->webmaster(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('webmaster', $output);
    }

    // ----------------------------------------------------------------
    // politiqueConfidentialite
    // ----------------------------------------------------------------

    public function testPolitiqueConfidentialiteRendersView(): void
    {
        $_COOKIE['age_verified'] = '1';
        ob_start();
        $this->makeController('/fr/politique-confidentialite')->politiqueConfidentialite(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    // ----------------------------------------------------------------
    // mentionsLegales — mode bare
    // ----------------------------------------------------------------

    public function testMentionsLegalesBareMode(): void
    {
        $controller = $this->makeController('/fr/mentions-legales');
        $_GET['bare'] = '';

        ob_start();
        $controller->mentionsLegales(['lang' => 'fr']);
        $output = ob_get_clean();

        $_GET = [];
        $this->assertStringContainsString('bare-legal', $output);
    }

    // ----------------------------------------------------------------
    // support
    // ----------------------------------------------------------------

    public function testSupportRendersView(): void
    {
        $_COOKIE['age_verified'] = '1';
        ob_start();
        $this->makeController('/fr/support')->support(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('support', $output);
    }

    // ----------------------------------------------------------------
    // jeux
    // ----------------------------------------------------------------

    public function testJeuxRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/jeux')->jeux(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('memo', $output);
    }

    // ----------------------------------------------------------------
    // contactPost — chemin succès (SMTP via mailhog sur localhost:1025)
    // ----------------------------------------------------------------

    public function testContactPostSucceedsWithValidData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/contact';
        $_GET = [];

        $token = bin2hex(random_bytes(16));
        $_SESSION['csrf'] = $token;
        $_POST = [
            'csrf_token' => $token,
            'firstname'  => 'Test',
            'lastname'   => 'Integration',
            'email'      => 'test-contact@example.com',
            'subject'    => 'general',
            'message'    => 'Message de test intégration contactPost.',
            'rgpd'       => '1',
        ];

        // Sous-classe anonyme pour injecter un MailService mocké (pas de SMTP réel)
        $stub = $this->createStub(\Service\MailService::class);
        // sendContactToOwner et sendContactConfirmation retournent void → pas de willReturn

        $controller = new class (new Request(), $stub) extends PageController {
            public function __construct(
                Request $request,
                private \Service\MailService $mockMail
            ) {
                parent::__construct($request);
            }
            protected function newMailService(): \Service\MailService
            {
                return $this->mockMail;
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        $controller->contactPost(['lang' => 'fr']);
    }
}
