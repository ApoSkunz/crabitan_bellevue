<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\FeatureFlagsAdminController;
use Core\Exception\HttpException;
use Core\FeatureFlags;
use Model\AccountModel;
use Model\AdminOtpModel;
use Model\FeatureFlagsModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Service\MailService;
use Service\RateLimiterService;

#[CoversClass(FeatureFlagsAdminController::class)]
final class FeatureFlagsAdminControllerTest extends TestCase
{
    protected function setUp(): void
    {
        FeatureFlags::flush();
        $_SESSION = [
            'admin_user' => ['id' => 1, 'role' => 'super_admin', 'email' => 'admin@test.com', 'name' => 'Admin'],
            'csrf'       => 'tok',
        ];
        $_GET  = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        FeatureFlags::flush();
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    /** @param array<string, mixed> $flagRows */
    private function makeController(
        array $flagRows = [],
        ?FeatureFlagsModel $model = null,
        ?RateLimiterService $rl = null,
        ?MailService $mail = null,
        ?AdminOtpModel $otpModel = null,
        ?AccountModel $accountModel = null,
        bool $csrfValid = true,
        ?array $superAdminUser = null,
    ): FeatureFlagsAdminController {
        $stubModel        = $model        ?? $this->buildModelStub($flagRows);
        $stubRl           = $rl           ?? $this->createStub(RateLimiterService::class);
        $stubMail         = $mail         ?? $this->createStub(MailService::class);
        $stubOtpModel     = $otpModel     ?? $this->createStub(AdminOtpModel::class);
        $stubAccountModel = $accountModel ?? $this->createStub(AccountModel::class);
        $superUser        = $superAdminUser ?? $_SESSION['admin_user'] ?? [];

        return new class (
            $stubModel,
            $stubRl,
            $stubMail,
            $stubOtpModel,
            $stubAccountModel,
            $csrfValid,
            $superUser
        ) extends FeatureFlagsAdminController {
            public ?string $viewName = null;
            /** @var array<string, mixed> */
            public array $viewData = [];

            public function __construct(
                private readonly FeatureFlagsModel $injectedModel,
                private readonly RateLimiterService $injectedRl,
                private readonly MailService $injectedMail,
                private readonly AdminOtpModel $injectedOtpModel,
                private readonly AccountModel $injectedAccountModel,
                private readonly bool $injectedCsrfValid,
                private readonly array $injectedSuperUser,
            ) {
            }

            protected function requireAdmin(): array
            {
                return $_SESSION['admin_user'] ?? [];
            }

            protected function requireSuperAdmin(): array
            {
                return $this->injectedSuperUser;
            }

            protected function verifyCsrf(): bool
            {
                return $this->injectedCsrfValid;
            }

            protected function newModel(): FeatureFlagsModel
            {
                return $this->injectedModel;
            }

            protected function newRateLimiter(): RateLimiterService
            {
                return $this->injectedRl;
            }

            protected function newMailService(): MailService
            {
                return $this->injectedMail;
            }

            protected function newAdminOtpModel(): AdminOtpModel
            {
                return $this->injectedOtpModel;
            }

            protected function newAccountModel(): AccountModel
            {
                return $this->injectedAccountModel;
            }

            protected function view(string $name, array $data = [], int $httpStatus = 200): void
            {
                $this->viewName = $name;
                $this->viewData = $data;
            }

            protected function flash(string $type, string $msg): void
            {
                $_SESSION['flash'][$type] = $msg;
            }
        };
    }

    /** @param array<string, mixed> $rows */
    private function buildModelStub(array $rows): FeatureFlagsModel
    {
        $stub = $this->createStub(FeatureFlagsModel::class);
        $stub->method('getAll')->willReturn($rows);
        return $stub;
    }

    private function flagBool(string $key = 'foo', string $value = 'true'): array
    {
        return ['key' => $key, 'value' => $value, 'type' => 'bool', 'label' => ucfirst($key), 'description' => null];
    }

    // ----------------------------------------------------------------
    // index()
    // ----------------------------------------------------------------

    public function testIndexRendersView(): void
    {
        $ctrl = $this->makeController(flagRows: [$this->flagBool()]);
        $ctrl->index([]);

        $this->assertSame('admin/feature-flags/index', $ctrl->viewName);
        $this->assertCount(1, $ctrl->viewData['flags']);
        $this->assertNull($ctrl->viewData['otpKey']);
    }

    public function testIndexPassesOtpKeyFromQueryString(): void
    {
        $_GET['otp_key'] = 'foo';
        $ctrl = $this->makeController();
        $ctrl->index([]);

        $this->assertSame('foo', $ctrl->viewData['otpKey']);
    }

    public function testIndexOtpKeyIsNullWhenQueryStringEmpty(): void
    {
        $ctrl = $this->makeController();
        $ctrl->index([]);

        $this->assertNull($ctrl->viewData['otpKey']);
    }

    public function testIndexNonSuperAdminFetchesSuperAdminEmails(): void
    {
        $_SESSION['admin_user'] = ['id' => 2, 'role' => 'admin', 'email' => 'admin@test.com', 'name' => 'Admin'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findAdmins')->willReturn([
            ['id' => 1, 'role' => 'super_admin', 'email' => 'sa@test.com'],
            ['id' => 2, 'role' => 'admin',       'email' => 'admin@test.com'],
        ]);

        $ctrl = $this->makeController(accountModel: $accounts);
        $ctrl->index([]);

        $this->assertSame(['sa@test.com'], $ctrl->viewData['superAdminEmails']);
    }

    public function testIndexOtpBatchSetRemainingAttemptsWhenOtpFound(): void
    {
        $_GET['otp_batch'] = '1';
        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn(['id' => 1, 'attempts' => 1]);

        $ctrl = $this->makeController(otpModel: $otpModel);
        $ctrl->index([]);

        $this->assertSame(2, $ctrl->viewData['remainingAttempts']); // MAX_OTP_ATTEMPTS(3) - 1
    }

    public function testIndexOtpBatchRemainingAttemptsNullWhenOtpNotFound(): void
    {
        $_GET['otp_batch'] = '1';
        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn(null);

        $ctrl = $this->makeController(otpModel: $otpModel);
        $ctrl->index([]);

        $this->assertNull($ctrl->viewData['remainingAttempts']);
    }

    public function testIndexSuperAdminPassesOtpBlockSecondsLeft(): void
    {
        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('getRetryAfter')->willReturn(300);

        $ctrl = $this->makeController(rl: $rl);
        $ctrl->index([]);

        $this->assertSame(300, $ctrl->viewData['otpBlockSecondsLeft']);
    }

    // ----------------------------------------------------------------
    // requestBatch()
    // ----------------------------------------------------------------

    public function testRequestBatchCsrfFailRedirects(): void
    {
        $this->expectException(HttpException::class);

        $ctrl = $this->makeController(csrfValid: false);
        $ctrl->requestBatch([]);
    }

    public function testRequestBatchNoChangesFlashesInfo(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'true'; // same value → no change

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertSame('Aucune modification détectée.', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchBlockedByOtpBlockRedirects(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'false'; // change

        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturnCallback(function (string $key) {
            return !str_contains($key, 'ff_otp_block');
        });

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('bloquée', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchRateLimitExceededRedirects(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'false';

        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturnCallback(function (string $key) {
            return !str_contains($key, 'ff_batch_req');
        });

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('Trop de demandes', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchMailErrorFlashesError(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'false';

        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);

        $otpModel = $this->createStub(AdminOtpModel::class);

        $mail = $this->createStub(MailService::class);
        $mail->method('sendFeatureFlagBatchOtp')->willThrowException(new \RuntimeException('SMTP error'));

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl, mail: $mail, otpModel: $otpModel);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('Erreur envoi email OTP', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchSuccessRedirectsToOtpBatch(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'false';

        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);

        $otpModel = $this->createStub(AdminOtpModel::class);
        $mail     = $this->createStub(MailService::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl, mail: $mail, otpModel: $otpModel);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('otp_batch', (string) $e->location);
            throw $e;
        }
    }

    public function testRequestBatchSkipsInvalidBoolValue(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = 'not_a_bool'; // invalid bool → skipped → no change

        $rl = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertSame('Aucune modification détectée.', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchAcceptsIntType(): void
    {
        $model = $this->buildModelStub([
            ['key' => 'max', 'value' => '10', 'type' => 'int', 'label' => 'Max', 'description' => null],
        ]);
        $_POST['max'] = '20';

        $rl       = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);
        $otpModel = $this->createStub(AdminOtpModel::class);
        $mail     = $this->createStub(MailService::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl, mail: $mail, otpModel: $otpModel);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            // flash info → OTP envoyé
            $this->assertStringContainsString('OTP envoyé', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchAcceptsFloatType(): void
    {
        $model = $this->buildModelStub([
            ['key' => 'rate', 'value' => '1.5', 'type' => 'float', 'label' => 'Rate', 'description' => null],
        ]);
        $_POST['rate'] = '2.5';

        $rl       = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);
        $otpModel = $this->createStub(AdminOtpModel::class);
        $mail     = $this->createStub(MailService::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl, mail: $mail, otpModel: $otpModel);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('OTP envoyé', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchAcceptsStringType(): void
    {
        $model = $this->buildModelStub([
            ['key' => 'title', 'value' => 'old', 'type' => 'string', 'label' => 'Title', 'description' => null],
        ]);
        $_POST['title'] = 'new';

        $rl       = $this->createStub(RateLimiterService::class);
        $rl->method('checkLimit')->willReturn(true);
        $otpModel = $this->createStub(AdminOtpModel::class);
        $mail     = $this->createStub(MailService::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, rl: $rl, mail: $mail, otpModel: $otpModel);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('OTP envoyé', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchSkipsEmptyValue(): void
    {
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        $_POST['foo'] = ''; // empty → isValidValue returns false

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertSame('Aucune modification détectée.', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    public function testRequestBatchSkipsMissingPostKey(): void
    {
        // Flag 'foo' existe dans le model mais absent de $_POST → ligne 107 continue exécutée
        $model = $this->buildModelStub([$this->flagBool('foo', 'true')]);
        // $_POST vide — 'foo' non soumis

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model);
        try {
            $ctrl->requestBatch([]);
        } catch (HttpException $e) {
            $this->assertSame('Aucune modification détectée.', $_SESSION['flash']['info'] ?? '');
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // confirmBatch()
    // ----------------------------------------------------------------

    public function testConfirmBatchCsrfFailRedirects(): void
    {
        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(csrfValid: false);
        $ctrl->confirmBatch([]);
    }

    public function testConfirmBatchNoOtpFoundRedirects(): void
    {
        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn(null);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(otpModel: $otpModel);
        try {
            $ctrl->confirmBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('expiré', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testConfirmBatchWrongOtpBelowMaxAttemptsFlashesError(): void
    {
        $_POST['otp'] = '000000';
        $realHash     = hash('sha256', '999999');

        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn([
            'id'       => 1,
            'otp_hash' => $realHash,
            'attempts' => 0,
            'payload'  => json_encode([['key' => 'foo', 'old' => 'true', 'new' => 'false', 'label' => 'Foo']]),
        ]);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(otpModel: $otpModel);
        try {
            $ctrl->confirmBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('erroné', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testConfirmBatchWrongOtpAtMaxAttemptsBlocksAndRedirects(): void
    {
        $_POST['otp'] = '000000';
        $realHash     = hash('sha256', '999999');

        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn([
            'id'       => 1,
            'otp_hash' => $realHash,
            'attempts' => 2, // one more = 3 = MAX
            'payload'  => json_encode([['key' => 'foo', 'old' => 'true', 'new' => 'false', 'label' => 'Foo']]),
        ]);

        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createStub(MailService::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(otpModel: $otpModel, rl: $rl, mail: $mail);
        try {
            $ctrl->confirmBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('bloquée', $_SESSION['flash']['error'] ?? '');
            throw $e;
        }
    }

    public function testConfirmBatchWrongOtpAtMaxAttemptsMailErrorSilent(): void
    {
        $_POST['otp'] = '000000';
        $realHash     = hash('sha256', '999999');

        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn([
            'id'       => 1,
            'otp_hash' => $realHash,
            'attempts' => 2,
            'payload'  => json_encode([]),
        ]);

        $rl   = $this->createStub(RateLimiterService::class);
        $mail = $this->createStub(MailService::class);
        $mail->method('sendFeatureFlagOtpBlocked')->willThrowException(new \RuntimeException('SMTP'));

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(otpModel: $otpModel, rl: $rl, mail: $mail);
        $ctrl->confirmBatch([]); // must not propagate SMTP exception
    }

    public function testConfirmBatchCorrectOtpAppliesChangesAndRedirects(): void
    {
        $otp      = '123456';
        $otpHash  = hash('sha256', $otp);
        $payload  = json_encode([
            ['key' => 'foo', 'old' => 'true', 'new' => 'false', 'label' => 'Foo'],
        ]);
        $_POST['otp'] = $otp;

        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn([
            'id'       => 1,
            'otp_hash' => $otpHash,
            'attempts' => 0,
            'payload'  => $payload,
        ]);

        $model = $this->createStub(FeatureFlagsModel::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, otpModel: $otpModel);
        try {
            $ctrl->confirmBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('mis à jour', $_SESSION['flash']['success'] ?? '');
            throw $e;
        }
    }

    public function testConfirmBatchSkipsChangeWithEmptyKey(): void
    {
        $otp     = '654321';
        $payload = json_encode([
            ['key' => '', 'old' => 'true', 'new' => 'false', 'label' => ''],
        ]);
        $_POST['otp'] = $otp;

        $otpModel = $this->createStub(AdminOtpModel::class);
        $otpModel->method('find')->willReturn([
            'id'       => 1,
            'otp_hash' => hash('sha256', $otp),
            'attempts' => 0,
            'payload'  => $payload,
        ]);

        $model = $this->createStub(FeatureFlagsModel::class);

        $this->expectException(HttpException::class);
        $ctrl = $this->makeController(model: $model, otpModel: $otpModel);
        try {
            $ctrl->confirmBatch([]);
        } catch (HttpException $e) {
            $this->assertStringContainsString('0 flag', $_SESSION['flash']['success'] ?? '');
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // Seam factory methods (L277-319) — instanciation réelle via sous-classe concrète
    // ----------------------------------------------------------------

    public function testFactoryMethodsReturnCorrectInstances(): void
    {
        // Mock DB singleton pour que les Model::__construct() n'échouent pas
        $dbMock  = $this->createStub(\Core\Database::class);
        $dbProp  = new \ReflectionProperty(\Core\Database::class, 'instance');
        $dbProp->setValue(null, $dbMock);

        $_ENV['MAIL_USER'] = 'noreply@test.local';

        $ctrl = new class extends FeatureFlagsAdminController {
            // phpcs:ignore
            public function __construct() {}
            public function exposedNewModel(): FeatureFlagsModel
            {
                return $this->newModel();
            }
            public function exposedNewAdminOtpModel(): AdminOtpModel
            {
                return $this->newAdminOtpModel();
            }
            public function exposedNewAccountModel(): AccountModel
            {
                return $this->newAccountModel();
            }
            public function exposedNewRateLimiter(): RateLimiterService
            {
                return $this->newRateLimiter();
            }
            public function exposedNewMailService(): MailService
            {
                return $this->newMailService();
            }
        };

        $this->assertInstanceOf(FeatureFlagsModel::class, $ctrl->exposedNewModel());
        $this->assertInstanceOf(AdminOtpModel::class, $ctrl->exposedNewAdminOtpModel());
        $this->assertInstanceOf(AccountModel::class, $ctrl->exposedNewAccountModel());
        $this->assertInstanceOf(RateLimiterService::class, $ctrl->exposedNewRateLimiter());
        $this->assertInstanceOf(MailService::class, $ctrl->exposedNewMailService());

        $dbProp->setValue(null, null);
        unset($_ENV['MAIL_USER']);
    }
}
