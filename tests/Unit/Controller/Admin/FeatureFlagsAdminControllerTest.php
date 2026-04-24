<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\FeatureFlagsAdminController;
use Core\FeatureFlags;
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
        ?MailService $mail = null
    ): FeatureFlagsAdminController {
        $stubModel = $model ?? $this->buildModelStub($flagRows);
        $stubRl    = $rl    ?? $this->createStub(RateLimiterService::class);
        $stubMail  = $mail  ?? $this->createStub(MailService::class);

        return new class ($stubModel, $stubRl, $stubMail) extends FeatureFlagsAdminController {
            public ?string $viewName = null;
            /** @var array<string, mixed> */
            public array $viewData = [];

            public function __construct(
                private readonly FeatureFlagsModel $injectedModel,
                private readonly RateLimiterService $injectedRl,
                private readonly MailService $injectedMail,
            ) {
            }

            protected function requireAdmin(): array
            {
                return $_SESSION['admin_user'] ?? [];
            }

            protected function requireSuperAdmin(): array
            {
                return $_SESSION['admin_user'] ?? [];
            }

            protected function verifyCsrf(): bool
            {
                return true;
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
}
