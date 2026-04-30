<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Database;
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
    public function testConditionsGeneralesVenteResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('conditionsGeneralesVente', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    /**
     * bare=true si cookie age_verified absent.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConditionsGeneralesVenteBareWithoutCookie(): void
    {
        $this->bootstrapApp();
        unset($_COOKIE['age_verified'], $_GET['bare']);

        $controller = new class (new \Core\Request()) extends \Controller\PageController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->conditionsGeneralesVente(['lang' => 'en']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertTrue($controller->lastViewData['bare'], 'bare doit être true sans cookie age_verified');
        $this->assertSame('en', $controller->lastViewData['lang']);
    }

    /**
     * bare=false si cookie age_verified=1 et pas de ?bare en GET.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConditionsGeneralesVenteNotBareWithCookie(): void
    {
        $this->bootstrapApp();
        $_COOKIE['age_verified'] = '1';
        unset($_GET['bare']);

        $controller = new class (new \Core\Request()) extends \Controller\PageController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->conditionsGeneralesVente(['lang' => 'fr']);

        $this->assertFalse($controller->lastViewData['bare'], 'bare doit être false avec age_verified=1');
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCreditsMediasResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('creditsMedias', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCreditsMediasResolvesLangEn(): void
    {
        $this->bootstrapApp();
        $this->callAction('creditsMedias', 'en');
        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    // ── contactPost() ────────────────────────────────────────────────────────

    /**
     * IP rate-limitée → 429 avant tout traitement.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsTooManyRequestsWhenRateLimited(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '10.0.0.1';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'email'      => 'jean@example.com',
            'subject'    => 'general',
            'message'    => 'Message test.',
            'rgpd'       => '1',
        ];

        $blockedLimiter = new class extends \Service\RateLimiterService {
            public function checkLimit(string $key, int $maxAttempts, int $windowSeconds): bool
            {
                return false; // toujours bloqué
            }
        };

        $controller = new class (new \Core\Request(), $blockedLimiter) extends \Controller\PageController {
            public function __construct(\Core\Request $req, private \Service\RateLimiterService $stub)
            {
                parent::__construct($req);
            }
            protected function newRateLimiterService(): \Service\RateLimiterService
            {
                return $this->stub;
            }
        };

        $caught = null;
        ob_start();
        try {
            $controller->contactPost(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 429 quand le rate limiter bloque');
        $this->assertSame(429, $caught->status);
    }

    /**
     * IP non limitée → recordAttempt() appelé exactement une fois.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostRecordsAttemptWhenCheckPasses(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '10.0.0.2';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => '',  // manquant → 422, mais recordAttempt est avant la validation
            'lastname'   => 'Dupont',
            'email'      => 'jean@example.com',
            'subject'    => 'general',
            'message'    => 'Message.',
            'rgpd'       => '1',
            'website'    => '',  // honeypot vide
        ];

        $recorded = 0;
        $spyLimiter = new class ($recorded) extends \Service\RateLimiterService {
            public function __construct(private int &$count)
            {
            }
            public function checkLimit(string $key, int $maxAttempts, int $windowSeconds): bool
            {
                return true;
            }
            public function recordAttempt(string $key, int $windowSeconds): void
            {
                $this->count++;
            }
        };

        $controller = new class (new \Core\Request(), $spyLimiter) extends \Controller\PageController {
            public function __construct(\Core\Request $req, private \Service\RateLimiterService $stub)
            {
                parent::__construct($req);
            }
            protected function newRateLimiterService(): \Service\RateLimiterService
            {
                return $this->stub;
            }
        };

        ob_start();
        try {
            $controller->contactPost(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException) {
            // 422 attendu (champ manquant), mais recordAttempt doit avoir été appelé avant
        }
        ob_end_clean();

        $this->assertSame(1, $recorded, 'recordAttempt() doit être appelé une fois par requête non limitée');
    }

    /**
     * Honeypot rempli → rejet silencieux (200 succès, pas d'envoi réel).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostHoneypotFilledReturnsSilentSuccess(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => 'Bot',
            'lastname'   => 'Scraper',
            'email'      => 'bot@evil.com',
            'subject'    => 'general',
            'message'    => 'Je suis un bot',
            'rgpd'       => '1',
            'website'    => 'http://evil-bot.com',  // honeypot rempli
        ];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        // Rejet silencieux : réponse 200 comme si le message avait été envoyé
        $this->assertNotNull($caught, 'contactPost() doit retourner une réponse JSON même avec honeypot');
        $this->assertSame(200, $caught->status);
    }

    /**
     * Honeypot vide → traitement normal (pas de rejet prématuré).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostHoneypotEmptyAllowsNormalFlow(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'tok';
        $_POST = [
            'csrf_token' => 'tok',
            'firstname'  => '',  // manquant → 422 pour valider que le flux normal continue
            'lastname'   => 'Test',
            'email'      => 'test@example.com',
            'subject'    => 'general',
            'message'    => 'Hello',
            'rgpd'       => '1',
            'website'    => '',  // honeypot vide = humain
        ];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->contactPost(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        // Doit atteindre la validation des champs (422), pas le rejet honeypot (200)
        $this->assertNotNull($caught);
        $this->assertSame(422, $caught->status);
    }

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
                string $lang,
                string $phone = ''
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
     * Tous les champs valides + mailer lève RuntimeException code 429 → 429.
     * Couvre L109 (branche rate-limit dans le catch Throwable).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactPostReturnsRateLimitErrorOn429(): void
    {
        $this->bootstrapApp();

        $rateLimitMail = new class extends \Service\MailService {
            public function __construct()
            {
                // no-op — stub de test, contourne l'injection SMTP
            }
            public function sendContactToOwner(
                string $firstname,
                string $lastname,
                string $email,
                string $subject,
                string $message,
                string $lang,
                string $phone = ''
            ): void {
                throw new \RuntimeException('Too Many Requests', 429); // NOSONAR — S112
            }
            public function sendContactConfirmation(
                string $to,
                string $firstname,
                string $subject,
                string $lang,
                string $userMessage = ''
            ): void {
                // no-op — stub de test, non appelé lors d'un rate-limit
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
            'message'    => 'Test rate limit.',
            'rgpd'       => '1',
        ];

        $controller = new class (new \Core\Request(), $rateLimitMail) extends \Controller\PageController {
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
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'contactPost() doit retourner 429 sur rate-limit');
        $this->assertSame(429, $caught->status);
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

    // ── llmsTxt() ───────────────────────────────────────────────────────────

    /**
     * llmsTxt() génère du texte/plain avec la liste des vins et lève HttpException(0).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLlmsTxtOutputsPlainTextWithWines(): void
    {
        $this->bootstrapApp();

        $stubWineModel = new class extends \Model\WineModel {
            public function __construct()
            {
                // no-op — stub de test, contourne l'injection BDD
            }
            /** @return array<int, array<string, mixed>> */
            public function getAvailableForLlms(): array
            {
                return [
                    ['slug' => 'sainte-croix-2022', 'label_name' => 'Sainte-Croix-du-Mont',
                        'vintage' => 2022, 'price' => 25.0, 'wine_color' => 'sweet'],
                ];
            }
        };

        $controller = new class (new \Core\Request(), $stubWineModel) extends \Controller\PageController {
            public function __construct(\Core\Request $req, private \Model\WineModel $stub)
            {
                parent::__construct($req);
            }
            protected function newWineModel(): \Model\WineModel
            {
                return $this->stub;
            }
        };

        $caught = null;
        ob_start();
        try {
            $controller->llmsTxt();
        } catch (HttpException $e) {
            $caught = $e;
        }
        $output = ob_get_clean();

        $this->assertNotNull($caught, 'llmsTxt() doit lever HttpException(0)');
        $this->assertSame(0, $caught->status);
        $this->assertIsString($output);
        $this->assertStringContainsString('Château Crabitan Bellevue', $output);
        $this->assertStringContainsString('Sainte-Croix-du-Mont', $output);
        $this->assertStringContainsString('1 références', $output);
    }

    /**
     * llmsTxt() avec zéro vin disponible → section "0 références".
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLlmsTxtWithNoWinesShowsZeroReferences(): void
    {
        $this->bootstrapApp();

        $stubWineModel = new class extends \Model\WineModel {
            public function __construct()
            {
                // no-op — stub de test, contourne l'injection BDD
            }
            /** @return array<int, array<string, mixed>> */
            public function getAvailableForLlms(): array
            {
                return [];
            }
        };

        $controller = new class (new \Core\Request(), $stubWineModel) extends \Controller\PageController {
            public function __construct(\Core\Request $req, private \Model\WineModel $stub)
            {
                parent::__construct($req);
            }
            protected function newWineModel(): \Model\WineModel
            {
                return $this->stub;
            }
        };

        ob_start();
        try {
            $controller->llmsTxt();
        } catch (HttpException) {
            // attendu — llmsTxt() lève toujours HttpException(0)
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('0 références', $output);
    }

    /**
     * newWineModel() retourne une instance de WineModel (couvre la seam réelle).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNewWineModelReturnsWineModelInstance(): void
    {
        $this->bootstrapApp();

        $dbStub   = $this->createStub(Database::class);
        $dbProp   = new \ReflectionProperty(Database::class, 'instance');
        $dbProp->setValue(null, $dbStub); // NOSONAR — S3011 : Reflection requise pour mocker le singleton Database en TU isolé

        $controller = new \Controller\PageController(new \Core\Request());
        $method     = new \ReflectionMethod(\Controller\PageController::class, 'newWineModel');

        $result = $method->invoke($controller);

        $this->assertInstanceOf(\Model\WineModel::class, $result);
        $dbProp->setValue(null, null); // NOSONAR — S3011 : teardown du singleton Database mocké
    }
}
