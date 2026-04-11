<?php

declare(strict_types=1);

namespace Tests\Unit\Fuzzing;

use Core\Database;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * US1 — Fuzzing routes API HTTP.
 *
 * Vérifie qu'aucune route testée ne produit un 500 non intentionnel
 * face à des payloads invalides : valeurs nulles, types incorrects,
 * CSRF forgé, JWT malformé, headers manquants.
 */
class ApiRoutesFuzzingTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock);

        $_ENV['JWT_SECRET'] = 'test-secret-key-minimum-32-chars!!';
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'FuzzingTest/1.0';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Retourne les payloads invalides couvrant les catégories OWASP.
     *
     * @return array<string, mixed>
     */
    private function fuzzPayloads(): array
    {
        return [
            'null_value'             => null,
            'empty_string'           => '',
            'zero'                   => 0,
            'negative'               => -1,
            'very_long_string'       => str_repeat('A', 65536),
            'sql_injection'          => "' OR '1'='1",
            'xss_basic'              => '<script>alert(1)</script>',
            'xss_encoded'            => '&lt;script&gt;alert(1)&lt;/script&gt;',
            'path_traversal'         => '../../../etc/passwd',
            'null_byte'              => "value\x00injected",
            'unicode_overflow'       => "\xF4\x90\x80\x80",
            'array_injection'        => ['key' => 'val'],
            'float_as_int'           => 3.14,
            'boolean_true'           => true,
            'boolean_false'          => false,
            'newline_injection'      => "value\r\nX-Injected: header",
            'format_string'          => '%s%s%s%s%s',
            'php_serialize'          => 'O:8:"stdClass":0:{}',
        ];
    }

    /**
     * Capture un HttpException et vérifie que le code HTTP est 4xx (jamais 500).
     *
     * @param callable $action
     */
    private function assertNot500(callable $action, string $context): void
    {
        try {
            ob_start();
            $action();
            ob_end_clean();
            // Si aucune exception — OK (ex: redirect interne)
            $this->assertTrue(true, $context . ': no exception is acceptable');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertLessThan(
                500,
                $e->getCode(),
                $context . ': HTTP ' . $e->getCode() . ' — pas de 500 autorisé sur payload invalide'
            );
        } catch (\Throwable $e) {
            ob_end_clean();
            // Toute autre exception non catchée = crash non géré = FAIL
            $this->fail($context . ': crash non géré — ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    // ================================================================
    // CartApiController — POST /api/cart/add (sans auth)
    // ================================================================

    /**
     * @dataProvider cartAddFuzzProvider
     */
    #[DataProvider('cartAddFuzzProvider')]
    public function testCartAddWithInvalidPayloads(string $label, mixed $wineId, mixed $quantity): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/add';
        $_SESSION = ['csrf' => 'valid-csrf-token'];
        $_POST    = [
            'wine_id'    => $wineId,
            'quantity'   => $quantity,
            'csrf_token' => 'valid-csrf-token',
        ];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->add([]),
            "CartApiController::add [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, mixed, mixed}>
     */
    public static function cartAddFuzzProvider(): array
    {
        return [
            'null_wine_id'      => ['null_wine_id', null, 1],
            'negative_wine_id'  => ['negative_wine_id', -1, 1],
            'string_wine_id'    => ['string_wine_id', 'abc', 1],
            'sql_wine_id'       => ['sql_wine_id', "1 OR 1=1", 1],
            'array_wine_id'     => ['array_wine_id', ['id' => 1], 1],
            'zero_wine_id'      => ['zero_wine_id', 0, 1],
            'null_qty'          => ['null_qty', 1, null],
            'negative_qty'      => ['negative_qty', 1, -999],
            'string_qty'        => ['string_qty', 1, 'lot'],
            'overflow_qty'      => ['overflow_qty', 1, PHP_INT_MAX],
            'xss_wine_id'       => ['xss_wine_id', '<script>alert(1)</script>', 1],
            'float_wine_id'     => ['float_wine_id', 3.14, 2],
        ];
    }

    // ================================================================
    // CartApiController — POST /api/cart/add sans CSRF
    // ================================================================

    public function testCartAddWithMissingCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/add';
        $_SESSION = ['csrf' => 'expected-token'];
        $_POST    = ['wine_id' => 1, 'quantity' => 1];
        // csrf_token absent

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->add([]),
            'CartApiController::add [missing_csrf]'
        );
    }

    public function testCartAddWithForgedCsrfToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/add';
        $_SESSION = ['csrf' => 'legit-token'];
        $_POST    = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'forged-token'];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->add([]),
            'CartApiController::add [forged_csrf]'
        );
    }

    // ================================================================
    // CartApiController — sans JWT (GET /api/cart/count)
    // ================================================================

    public function testCartCountWithoutJwt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/cart/count';
        $_COOKIE = []; // pas de auth_token

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->count([]),
            'CartApiController::count [no_jwt]'
        );
    }

    public function testCartCountWithMalformedJwt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/cart/count';
        $_COOKIE = ['auth_token' => 'not.a.valid.jwt.token'];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->count([]),
            'CartApiController::count [malformed_jwt]'
        );
    }

    public function testCartCountWithExpiredJwt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/cart/count';

        // JWT avec exp dans le passé
        $payload  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']) ?: '');
        $claims   = base64_encode(json_encode(['sub' => 1, 'role' => 'user', 'exp' => time() - 3600]) ?: '');
        $sig      = base64_encode('invalidsig');
        $_COOKIE  = ['auth_token' => "{$payload}.{$claims}.{$sig}"];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->count([]),
            'CartApiController::count [expired_jwt]'
        );
    }

    public function testCartCountWithAdminJwtBlocked(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/cart/count';

        // Admin ne doit pas accéder au panier client
        $token   = Jwt::generate(999, 'admin');
        $_COOKIE = ['auth_token' => $token];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->count([]),
            'CartApiController::count [admin_jwt_blocked]'
        );
    }

    // ================================================================
    // CartApiController — POST /api/cart/remove fuzz
    // ================================================================

    /**
     * @dataProvider cartRemoveFuzzProvider
     */
    #[DataProvider('cartRemoveFuzzProvider')]
    public function testCartRemoveWithInvalidPayloads(string $label, mixed $wineId): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/remove';
        $_SESSION = ['csrf' => 'csrf-tok'];
        $_POST    = ['wine_id' => $wineId, 'csrf_token' => 'csrf-tok'];
        // Pas de JWT → 401 attendu, pas 500

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->remove([]),
            "CartApiController::remove [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function cartRemoveFuzzProvider(): array
    {
        return [
            'null'           => ['null', null],
            'string'         => ['string', 'wine_abc'],
            'sql'            => ['sql', "1; DROP TABLE wines--"],
            'overflow'       => ['overflow', PHP_INT_MAX],
            'path_traversal' => ['path_traversal', '../config/config.php'],
        ];
    }

    // ================================================================
    // AgeGateController — POST /age-gate fuzz
    // ================================================================

    /**
     * @dataProvider ageGateFuzzProvider
     */
    #[DataProvider('ageGateFuzzProvider')]
    public function testAgeGateWithInvalidPayloads(string $label, mixed $born): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/age-gate';
        $_SESSION = ['csrf' => 'tok'];
        $_POST    = ['born' => $born, 'csrf_token' => 'tok'];

        $this->assertNot500(
            fn() => (new \Controller\AgeGateController(new Request()))->confirm([]),
            "AgeGateController::confirm [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function ageGateFuzzProvider(): array
    {
        return [
            'null'          => ['null', null],
            'empty'         => ['empty', ''],
            'string'        => ['string', 'not-a-date'],
            'sql'           => ['sql', "'; DROP TABLE accounts--"],
            'future_date'   => ['future_date', '2099-01-01'],
            'before_1900'   => ['before_1900', '1800-12-31'],
            'overflow'      => ['overflow', str_repeat('9', 1000)],
            'null_byte'     => ['null_byte', "1990-01-01\x00malicious"],
            'xss'           => ['xss', '<img src=x onerror=alert(1)>'],
        ];
    }

    // ================================================================
    // Routes admin — accès sans auth
    // ================================================================

    public function testAdminDashboardWithoutAuthReturns4xx(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin';
        $_COOKIE  = [];
        $_SESSION = [];

        $this->assertNot500(
            fn() => (new \Controller\Admin\DashboardController(new Request()))->index([]),
            'DashboardController::index [no_auth]'
        );
    }

    public function testAdminWinesWithMalformedJwtReturns4xx(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/vins';
        $_COOKIE  = ['auth_token' => 'eyJhbGciOiJub25lIn0.e30.'];
        $_SESSION = [];

        $this->assertNot500(
            fn() => (new \Controller\Admin\WineAdminController(new Request()))->index([]),
            'WineAdminController::index [malformed_jwt]'
        );
    }

    /**
     * @dataProvider adminPostFuzzProvider
     */
    #[DataProvider('adminPostFuzzProvider')]
    public function testAdminPostEndpointsWithoutCsrf(string $label, string $controller, string $method, array $params): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/admin/test';
        $_SESSION = ['csrf' => 'real-token'];
        $_POST    = ['csrf_token' => 'wrong-token']; // CSRF forgé

        $ctrl = new $controller(new Request());
        $this->assertNot500(
            fn() => $ctrl->{$method}($params),
            "Admin POST CSRF forgé [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, class-string, string, array<string, mixed>}>
     */
    public static function adminPostFuzzProvider(): array
    {
        return [
            'wine_toggle' => [
                'wine_toggle',
                \Controller\Admin\WineAdminController::class,
                'toggleAvailable',
                ['id' => '1'],
            ],
            'wine_featured' => [
                'wine_featured',
                \Controller\Admin\WineAdminController::class,
                'setFeatured',
                ['id' => '1'],
            ],
        ];
    }

    // ================================================================
    // Fuzz headers manquants — Content-Type absent
    // ================================================================

    public function testCartAddWithoutContentTypeHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/add';
        unset($_SERVER['CONTENT_TYPE']);
        $_SESSION = ['csrf' => 'tok'];
        $_POST    = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'tok'];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->add([]),
            'CartApiController::add [no_content_type]'
        );
    }

    public function testCartAddWithWeirdAcceptHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/cart/add';
        $_SERVER['HTTP_ACCEPT']    = 'application/x-www-form-urlencoded; boundary=' . str_repeat('A', 4096);
        $_SESSION = ['csrf' => 'tok'];
        $_POST    = [];

        $this->assertNot500(
            fn() => (new \Controller\Api\CartApiController(new Request()))->add([]),
            'CartApiController::add [weird_accept_header]'
        );
    }
}
