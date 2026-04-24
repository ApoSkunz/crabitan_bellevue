<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\GoogleLinkStatusController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\GoogleLinkTokenModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Couvre les branches non atteintes dans GoogleLinkStatusControllerTest :
 *   - line 63  : session_start() appelé quand session inactive
 *   - line 91  : findByHash() retourne false → expired
 *   - line 102 : accountLang fallback sur record['lang'] si lang compte invalide
 *   - lines 137-138 : has_connected = false → markAsConnected() + trust()
 *   - line 170 : redirect adapté si langue URL ≠ langue compte
 */
#[AllowMockObjectsWithoutExpectations]
class GoogleLinkStatusControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');

        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp->setValue(null, $dbMock);

        // S'assurer que la session est active — ne pas la fermer, sinon session_start()
        // dans le contrôleur (line 63) réinitialiserait $_SESSION et perdrait nos fixtures.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/api/google-link-status';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/CoverageTest';
        $_ENV['JWT_SECRET']         = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function inject(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionProperty($obj, $prop);
        $ref->setValue($obj, $value);
    }

    /**
     * @param bool|null                 $isConsumed
     * @param array<string,mixed>|false $findByHash
     * @param array<string,mixed>|false $account
     * @param bool                      $hasMajority
     * @param bool                      $isTrusted
     * @param bool                      $hasConnected  Valeur de has_connected dans $account
     * @return array{status: int, body: string}
     */
    private function callStatus(
        bool|null $isConsumed,
        array|false $findByHash = false,
        array|false $account = false,
        bool $hasMajority = true,
        bool $isTrusted = false,
        bool $hasConnected = true
    ): array {
        $tokenModel = $this->createStub(GoogleLinkTokenModel::class);
        $tokenModel->method('isConsumed')->willReturn($isConsumed);
        $tokenModel->method('findByHash')->willReturn($findByHash);

        $accountModel = $this->createStub(AccountModel::class);
        $accountModel->method('findById')->willReturn($account);
        $accountModel->method('hasDeclaredMajority')->willReturn($hasMajority);

        $connModel    = $this->createStub(ConnectionModel::class);
        $trustedModel = $this->createStub(TrustedDeviceModel::class);
        $trustedModel->method('isTrusted')->willReturn($isTrusted);

        $controller = new GoogleLinkStatusController(new Request());
        $this->inject($controller, 'linkTokens', $tokenModel);
        $this->inject($controller, 'accounts', $accountModel);
        $this->inject($controller, 'connections', $connModel);
        $this->inject($controller, 'trustedDevices', $trustedModel);

        ob_start();
        try {
            $controller->status([]);
        } catch (HttpException $e) {
            $body = (string) ob_get_clean();
            return ['status' => $e->getCode(), 'body' => $body];
        }
        ob_end_clean();
        $this->fail('Expected HttpException was not thrown.');
    }

    // ----------------------------------------------------------------
    // line 63 — session_start() quand session inactive
    // ----------------------------------------------------------------

    /**
     * Couvre line 63 : session_start() est appelé si PHP_SESSION_NONE.
     *
     * On ferme la session, puis on appelle status() — le contrôleur doit
     * détecter PHP_SESSION_NONE et appeler session_start(). La session repart
     * avec un $_SESSION vide → token absent → expired (200).
     */
    public function testSessionStartCalledWhenSessionInactive(): void
    {
        // Fermer la session pour forcer PHP_SESSION_NONE
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // S'assurer que la session est bien fermée
        $this->assertSame(PHP_SESSION_NONE, session_status());

        // Le contrôleur doit appeler session_start() (line 63), puis lire un token vide
        // → json(['status' => 'expired']) → HttpException 200
        $tokenModel   = $this->createStub(GoogleLinkTokenModel::class);
        $accountModel = $this->createStub(AccountModel::class);
        $connModel    = $this->createStub(ConnectionModel::class);
        $trustedModel = $this->createStub(TrustedDeviceModel::class);

        $controller = new GoogleLinkStatusController(new Request());
        $this->inject($controller, 'linkTokens', $tokenModel);
        $this->inject($controller, 'accounts', $accountModel);
        $this->inject($controller, 'connections', $connModel);
        $this->inject($controller, 'trustedDevices', $trustedModel);

        ob_start();
        try {
            $controller->status([]);
        } catch (HttpException $e) {
            ob_end_clean();
            // Session démarrée par le contrôleur — session active ou fermée
            $this->assertSame(200, $e->getCode());
            return;
        }
        ob_end_clean();
        $this->fail('Expected HttpException was not thrown.');
    }

    // ----------------------------------------------------------------
    // line 91 — findByHash() retourne false → expired
    // ----------------------------------------------------------------

    /**
     * Couvre line 91 : token consommé mais findByHash() retourne false → expired.
     */
    public function testFindByHashFalseReturnsExpired(): void
    {
        $_SESSION['pending_google_link_token'] = 'sometoken';

        // isConsumed = true (token consommé), mais findByHash retourne false (ligne 91)
        $result = $this->callStatus(true, false);

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"expired"', $result['body']);
    }

    // ----------------------------------------------------------------
    // line 102 — accountLang fallback sur record['lang']
    // ----------------------------------------------------------------

    /**
     * Couvre line 102 : lang du compte invalide → fallback sur record['lang'].
     *
     * account['lang'] = 'zz' (invalide) → accountLang = record['lang'] = 'en'.
     */
    public function testAccountLangFallbackOnInvalidLang(): void
    {
        $_SESSION['pending_google_link_token']    = 'token_langfallback';
        $_SESSION['google_link_status_last_call'] = 0;

        $record  = [
            'id'          => 1,
            'account_id'  => 42,
            'google_id'   => 'gid',
            'lang'        => 'en',  // fallback attendu
            'consumed_at' => '2026-04-20',
        ];
        // lang invalide dans le compte → branch false de in_array (line 101) → line 102
        $account = [
            'id'            => 42,
            'role'          => 'user',
            'lang'          => 'zz',  // invalide → pas dans SUPPORTED_LANGS
            'has_connected' => '1',
            'email'         => 'test@example.com',
        ];

        $result = $this->callStatus(true, $record, $account);

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"linked"', $result['body']);
        // La redirection doit utiliser 'en' (issu de record['lang'])
        $this->assertStringContainsString('/en', $result['body']);
    }

    // ----------------------------------------------------------------
    // lines 137-138 — has_connected = false → markAsConnected + trust
    // ----------------------------------------------------------------

    /**
     * Couvre lines 137-138 : has_connected = false → markAsConnected() + trust() appelés.
     */
    public function testHasConnectedFalseCallsMarkAsConnectedAndTrust(): void
    {
        $_SESSION['pending_google_link_token']    = 'token_firstconnect';
        $_SESSION['google_link_status_last_call'] = 0;

        $record  = [
            'id'          => 2,
            'account_id'  => 55,
            'google_id'   => 'gid2',
            'lang'        => 'fr',
            'consumed_at' => '2026-04-20',
        ];
        $account = [
            'id'            => 55,
            'role'          => 'user',
            'lang'          => 'fr',
            'has_connected' => '0',  // première connexion → lines 137-138
            'email'         => 'first@example.com',
        ];

        $tokenModel = $this->createStub(GoogleLinkTokenModel::class);
        $tokenModel->method('isConsumed')->willReturn(true);
        $tokenModel->method('findByHash')->willReturn($record);

        $accountModel = $this->createMock(AccountModel::class);
        $accountModel->method('findById')->willReturn($account);
        $accountModel->method('hasDeclaredMajority')->willReturn(true);
        // line 137 : markAsConnected() doit être appelé exactement une fois
        $accountModel->expects($this->once())->method('markAsConnected')->with(55);

        $trustedModel = $this->createMock(TrustedDeviceModel::class);
        $trustedModel->method('isTrusted')->willReturn(false);
        // trust() est appelé deux fois : line 109 (isTrusted=false) + line 138 (has_connected=false)
        $trustedModel->expects($this->exactly(2))->method('trust');

        $connModel = $this->createStub(ConnectionModel::class);

        $controller = new GoogleLinkStatusController(new Request());
        $this->inject($controller, 'linkTokens', $tokenModel);
        $this->inject($controller, 'accounts', $accountModel);
        $this->inject($controller, 'connections', $connModel);
        $this->inject($controller, 'trustedDevices', $trustedModel);

        ob_start();
        try {
            $controller->status([]);
        } catch (HttpException $e) {
            $body = (string) ob_get_clean();
            $this->assertSame(200, $e->getCode());
            $this->assertStringContainsString('"linked"', $body);
            return;
        }
        ob_end_clean();
        $this->fail('Expected HttpException was not thrown.');
    }

    // ----------------------------------------------------------------
    // line 170 — redirect adapté si langue URL ≠ langue compte
    // ----------------------------------------------------------------

    /**
     * Couvre line 170 : redirect /fr/... → /en/... si accountLang = 'en'.
     */
    public function testRedirectAdjustedForAccountLang(): void
    {
        $_SESSION['pending_google_link_token']    = 'token_langredir';
        $_SESSION['google_link_status_last_call'] = 0;
        // _intended commence en /fr/... mais accountLang = 'en' → line 170 adapte
        $_SESSION['_intended']                    = '/fr/vins';

        $record  = [
            'id'          => 3,
            'account_id'  => 77,
            'google_id'   => 'gid3',
            'lang'        => 'en',
            'consumed_at' => '2026-04-20',
        ];
        $account = [
            'id'            => 77,
            'role'          => 'user',
            'lang'          => 'en',  // lang compte = 'en'
            'has_connected' => '1',
            'email'         => 'redir@example.com',
        ];

        $result = $this->callStatus(true, $record, $account);

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"linked"', $result['body']);
        // Le redirect /fr/vins doit être adapté en /en/vins (line 170)
        $this->assertStringContainsString('/en/vins', $result['body']);
        $this->assertStringNotContainsString('/fr/vins', $result['body']);
    }
}
