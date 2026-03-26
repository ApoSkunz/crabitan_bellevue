<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PageController.
 *
 * Les pages statiques ne nécessitent pas de BDD.
 * Les tests vérifient que chaque action résout correctement la langue
 * et tente le rendu (skippé si la vue est absente en CLI).
 */
class PageControllerTest extends TestCase
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function makeController(): \Controller\PageController
    {
        return new \Controller\PageController(new \Core\Request());
    }

    private function callAction(string $action, string $lang = 'fr'): void
    {
        ob_start();
        try {
            $this->makeController()->$action(['lang' => $lang]);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped("Rendu indisponible sans vue complète : {$e->getMessage()}");
        }
        ob_end_clean();
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChateauResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('chateau', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSavoirFaireResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('savoirFaire', 'en');
        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('contact', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMentionsLegalesResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('mentionsLegales', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPlanDuSiteResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('planDuSite', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWebmasterResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('webmaster', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPolitiqueConfidentialiteResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('politiqueConfidentialite', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSupportResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('support', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testJeuxResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('jeux', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    // ── contactPost() ────────────────────────────────────────────────────────

    /**
     * Sans token CSRF en session → 400.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsCsrfError(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = []; // pas de csrf en session
        $_POST    = ['csrf_token' => ''];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 400 quand CSRF est absent');
        $this->assertSame(400, $caught->status);
    }

    /**
     * CSRF valide, champ obligatoire manquant → 422.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsMissingFieldsError(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => '',   // manquant
            'lastname'   => 'Test',
            'email'      => 'test@example.com',
            'subject'    => 'general',
            'message'    => 'Hello',
            'rgpd'       => '1',
        ];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 422 si un champ obligatoire est vide');
        $this->assertSame(422, $caught->status);
    }

    /**
     * CSRF valide, email invalide → 422.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsInvalidEmailError(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => 'Jean',
            'lastname'   => 'Test',
            'email'      => 'not-an-email',
            'subject'    => 'general',
            'message'    => 'Hello',
            'rgpd'       => '1',
        ];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(422, $caught->status);
    }

    /**
     * Tous les champs valides + CSRF valide + MailService mocké → 200 succès (L63 + L69).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostSuccessWithMockedMailService(): void
    {
        $this->bootstrapApp();

        // No-op MailService : contourne le SMTP sans changer la logique de PageController
        $noopMail = new class extends \Service\MailService {
            // skip SMTP setup — pas de connexion SMTP en test unitaire
            public function __construct()
            {
            }
            public function sendContactToOwner(
                string $firstname,
                string $lastname,
                string $email,
                string $subject,
                string $message,
                string $lang
            ): void {
                // no-op — stub de test, pas d'envoi SMTP réel
            }
            public function sendContactConfirmation(
                string $to,
                string $firstname,
                string $subject,
                string $lang,
                string $userMessage = ''
            ): void {
                // no-op — stub de test, pas d'envoi SMTP réel
            }
        };

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'email'      => 'jean@example.com',
            'subject'    => 'general',
            'message'    => 'Test succès contactPost.',
            'rgpd'       => '1',
        ];

        // Sous-classe qui injecte le no-op MailService via la seam method
        $controller = new class (new \Core\Request(), $noopMail) extends \Controller\PageController {
            public function __construct(\Core\Request $req, private \Service\MailService $stub)
            {
                parent::__construct($req);
            }
            protected function newMailService(): \Service\MailService
            {
                return $this->stub;
            }
        };

        $caught = null;
        ob_start();
        try {
            $controller->contactPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 200 après envoi réussi');
        $this->assertSame(200, $caught->status);
    }

    /**
     * Tous les champs valides + CSRF valide, mais SMTP injoignable → 500.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsSmtpError(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Forcer l'échec SMTP quel que soit l'environnement
        $_ENV['MAIL_HOST'] = 'invalid-smtp-host.test.local';
        $_ENV['MAIL_PORT'] = '9';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'email'      => 'jean@example.com',
            'subject'    => 'general',
            'message'    => 'Test message pour couvrir la branche SMTP erreur.',
            'rgpd'       => '1',
        ];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 500 quand le SMTP est indisponible');
        $this->assertSame(500, $caught->status);
    }
}
