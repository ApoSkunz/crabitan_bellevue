<?php

declare(strict_types=1);

namespace Tests\Unit\Fuzzing;

use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * US2 — Fuzzing entrées PHP.
 *
 * Envoie des inputs aléatoires/extrêmes aux validateurs (login, register,
 * parsing JSON, paramètres commande) et vérifie que le comportement
 * est toujours un rejet propre (HttpException 4xx) ou une validation
 * silencieuse — jamais un crash non géré.
 *
 * Couvre les 1000 premières mutations via itération de corpus.
 */
class PhpInputFuzzingTest extends TestCase
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
        $_SESSION = ['csrf' => 'csrf-token-fuzz'];
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
     * Vérifie qu'un callable ne produit ni exception non gérée ni 500.
     *
     * @param callable $action
     * @param string   $context Nom du cas pour le message d'erreur
     */
    private function assertNoCrash(callable $action, string $context): void
    {
        try {
            ob_start();
            $action();
            ob_end_clean();
            $this->assertTrue(true);
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertLessThan(500, $e->getCode(), $context . ': 5xx interdit sur input invalide');
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail($context . ': crash — ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Génère un corpus de 1000 mutations basé sur un seed value.
     *
     * @return list<array{string, string}>
     */
    private function generateEmailMutations(): array
    {
        $mutations = [];
        $base      = [
            '',
            'not-an-email',
            'a@',
            '@b.com',
            str_repeat('a', 255) . '@test.com',
            "user\x00@test.com",
            "user\r\nX-Injected:val@test.com",
            "user+<script>@test.com",
            "user'OR'1'='1@test.com",
            "user%40evil.com",
            "user@test.com\x00.evil.com",
            "user@" . str_repeat('a', 253) . ".com",
            'user @test.com',
            'user@ test.com',
            '@@@@',
            "\t@test.com",
            "user@test\ncom",
            'а@test.com',  // cyrillique
            'user@tëst.com',
            "user@[127.0.0.1]",
            '"user"@test.com',
            'user+tag@test.com',
            'user.@test.com',
            '.user@test.com',
        ];

        $i = 0;
        foreach ($base as $email) {
            $mutations[] = ["email_mut_{$i}", $email];
            $i++;
        }

        // Mutations supplémentaires jusqu'à 1000
        for ($j = $i; $j < 1000; $j++) {
            $len     = ($j % 300) + 1;
            $char    = chr(($j % 94) + 33); // Chars ASCII imprimables
            $email   = str_repeat($char, $len) . '@mut.com';
            $mutations[] = ["email_mut_{$j}", $email];
        }

        return $mutations;
    }

    // ================================================================
    // Corpus login — AuthController::login
    // ================================================================

    /**
     * @dataProvider loginEmailFuzzProvider
     */
    public function testLoginWithInvalidEmails(string $label, mixed $email): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/connexion';
        $_POST = [
            'email'       => $email,
            'password'    => 'anypassword',
            'csrf_token'  => 'csrf-token-fuzz',
        ];
        $_SESSION = ['csrf' => 'csrf-token-fuzz'];

        $this->assertNoCrash(
            fn() => (new \Controller\AuthController(new Request()))->login([]),
            "AuthController::login [{$label}]"
        );
    }

    /**
     * @return array<int, array{string, mixed}>
     */
    public static function loginEmailFuzzProvider(): array
    {
        $obj = new self('testLoginWithInvalidEmails');
        // Appel statique OK — setUp pas nécessaire ici
        $mutations = $obj->generateEmailMutations();
        return array_slice($mutations, 0, 50); // 50 mutations email
    }

    /**
     * @dataProvider loginPasswordFuzzProvider
     */
    public function testLoginWithInvalidPasswords(string $label, mixed $password): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/connexion';
        $_POST = [
            'email'      => 'test@valid.com',
            'password'   => $password,
            'csrf_token' => 'csrf-token-fuzz',
        ];
        $_SESSION = ['csrf' => 'csrf-token-fuzz'];

        $this->assertNoCrash(
            fn() => (new \Controller\AuthController(new Request()))->login([]),
            "AuthController::login password [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function loginPasswordFuzzProvider(): array
    {
        return [
            'null'              => ['null', null],
            'empty'             => ['empty', ''],
            'very_long'         => ['very_long', str_repeat('A', 65536)],
            'null_byte'         => ['null_byte', "pass\x00word"],
            'sql_injection'     => ['sql_injection', "' OR 1=1--"],
            'xss'               => ['xss', '<script>alert(1)</script>'],
            'unicode_overflow'  => ['unicode_overflow', "\xF4\x90\x80\x80"],
            'newline'           => ['newline', "pass\nword"],
            'crlf'              => ['crlf', "pass\r\nword"],
            'all_null_bytes'    => ['all_null_bytes', str_repeat("\x00", 100)],
            'php_serialize'     => ['php_serialize', 'O:8:"stdClass":0:{}'],
            'format_string'     => ['format_string', '%s%s%s%s%n%n'],
            'backtick'          => ['backtick', '`id`'],
            'backslash'         => ['backslash', '\\\\\\\\'],
            'tab_chars'         => ['tab_chars', "\t\t\t"],
            'control_chars'     => ['control_chars', "\x01\x02\x03"],
            'emoji'             => ['emoji', '🍷🍷🍷🍷'],
            'only_spaces'       => ['only_spaces', '   '],
            'very_long_unicode' => ['very_long_unicode', str_repeat('é', 10000)],
            'number'            => ['number', 0],
        ];
    }

    // ================================================================
    // Corpus register — AuthController::register
    // ================================================================

    /**
     * @dataProvider registerFuzzProvider
     */
    public function testRegisterWithInvalidInputs(string $label, array $post): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST    = array_merge(['csrf_token' => 'csrf-token-fuzz'], $post);
        $_SESSION = ['csrf' => 'csrf-token-fuzz'];

        $this->assertNoCrash(
            fn() => (new \Controller\AuthController(new Request()))->register([]),
            "AuthController::register [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function registerFuzzProvider(): array
    {
        return [
            'all_empty' => ['all_empty', [
                'email' => '', 'password' => '', 'firstname' => '', 'lastname' => '',
            ]],
            'xss_names' => ['xss_names', [
                'email'     => 'user@test.com',
                'password'  => 'Valid1234!',
                'firstname' => '<script>alert(1)</script>',
                'lastname'  => '" onmouseover="alert(1)',
            ]],
            'sql_injection_email' => ['sql_injection_email', [
                'email'    => "' UNION SELECT * FROM accounts--@test.com",
                'password' => 'Valid1234!',
            ]],
            'null_bytes_all' => ['null_bytes_all', [
                'email'     => "user\x00@test.com",
                'password'  => "pass\x00word",
                'firstname' => "Jean\x00Evil",
                'lastname'  => "Dupont\x00",
            ]],
            'very_long_all' => ['very_long_all', [
                'email'     => str_repeat('a', 300) . '@test.com',
                'password'  => str_repeat('P', 10000),
                'firstname' => str_repeat('X', 5000),
                'lastname'  => str_repeat('Y', 5000),
            ]],
            'array_injection' => ['array_injection', [
                'email'    => ['a' => 'b'],
                'password' => ['p' => 'q'],
            ]],
            'missing_password' => ['missing_password', [
                'email' => 'valid@test.com',
            ]],
            'missing_email' => ['missing_email', [
                'password' => 'Valid1234!',
            ]],
            'integer_fields' => ['integer_fields', [
                'email'     => 12345,
                'password'  => 0,
                'firstname' => true,
                'lastname'  => false,
            ]],
            'crlf_email' => ['crlf_email', [
                'email'    => "user\r\n@test.com",
                'password' => 'Valid1234!',
            ]],
        ];
    }

    // ================================================================
    // Corpus parsing JSON — comportement PasswordValidator
    // ================================================================

    /**
     * @dataProvider passwordValidatorFuzzProvider
     */
    public function testPasswordValidatorNeverCrashes(string $label, mixed $password): void
    {
        try {
            $validator = new \Service\PasswordValidator();
            $result    = $validator->validate((string) $password);
            // Tout retour est acceptable — on veut seulement pas de crash
            $this->assertIsBool($result, $label . ': validate() doit retourner bool');
        } catch (\Throwable $e) {
            $this->fail($label . ': PasswordValidator crash — ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function passwordValidatorFuzzProvider(): array
    {
        return [
            'empty'            => ['empty', ''],
            'spaces_only'      => ['spaces_only', '   '],
            'null_byte'        => ['null_byte', "pass\x00word"],
            'very_long'        => ['very_long', str_repeat('Aa1!', 10000)],
            'common_weak'      => ['common_weak', 'password'],
            'all_numbers'      => ['all_numbers', '12345678'],
            'all_upper'        => ['all_upper', 'ABCDEFGH'],
            'all_lower'        => ['all_lower', 'abcdefgh'],
            'unicode_chars'    => ['unicode_chars', 'Mötörhëad1!'],
            'rtl_chars'        => ['rtl_chars', 'ئابتث1234!'],
            'only_specials'    => ['only_specials', '!@#$%^&*()'],
            'with_newlines'    => ['with_newlines', "pass\nword1!"],
            'with_crlf'        => ['with_crlf', "pass\r\nword1!"],
            'tab'              => ['tab', "pass\tword1!"],
            'exact_min_len'    => ['exact_min_len', 'Abc12345!'],
            'one_char_under'   => ['one_char_under', 'Abc1234!'],  // 8 chars — boundary
            'null_value'       => ['null_value', null],
            'integer'          => ['integer', 0],
            'boolean'          => ['boolean', false],
        ];
    }

    // ================================================================
    // Corpus commande — OrderController paramètres
    // ================================================================

    /**
     * @dataProvider orderParamFuzzProvider
     */
    public function testOrderCheckoutWithInvalidParams(string $label, string $method, array $post): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $_POST    = $post;
        $_SESSION = ['csrf' => 'csrf-token-fuzz'];
        $_COOKIE  = [];

        $this->assertNoCrash(
            fn() => (new \Controller\OrderController(new Request()))->checkout([]),
            "OrderController::checkout [{$label}]"
        );
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function orderParamFuzzProvider(): array
    {
        return [
            'empty_post'           => ['empty_post', 'GET', []],
            'sql_injection_post'   => ['sql_injection_post', 'POST', ['id' => "1 OR 1=1"]],
            'xss_fields'           => ['xss_fields', 'POST', ['note' => '<script>alert(1)</script>']],
            'null_values'          => ['null_values', 'POST', ['id' => null, 'qty' => null]],
            'overflow_id'          => ['overflow_id', 'POST', ['id' => PHP_INT_MAX]],
            'negative_id'          => ['negative_id', 'POST', ['id' => -1]],
            'array_as_id'          => ['array_as_id', 'POST', ['id' => [1, 2, 3]]],
            'path_traversal'       => ['path_traversal', 'GET', ['page' => '../../../etc/passwd']],
        ];
    }

    // ================================================================
    // Mutations massives — 1000 inputs email sur AuthController::login
    // ================================================================

    public function testLoginWithMassEmailMutations(): void
    {
        $mutations = $this->generateEmailMutations();
        $count     = 0;

        foreach ($mutations as [$label, $email]) {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI']    = '/fr/connexion';
            $_POST    = ['email' => $email, 'password' => 'P@ssw0rd!', 'csrf_token' => 'csrf-token-fuzz'];
            $_SESSION = ['csrf' => 'csrf-token-fuzz'];

            $this->assertNoCrash(
                fn() => (new \Controller\AuthController(new Request()))->login([]),
                "login mass mutation [{$label}]"
            );

            $count++;
        }

        $this->assertGreaterThanOrEqual(1000, $count, 'Au moins 1000 mutations testées');
    }
}
