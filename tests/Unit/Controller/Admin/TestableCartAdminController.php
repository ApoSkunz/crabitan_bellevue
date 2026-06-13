<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

/**
 * Sous-classe testable de CartAdminController.
 * Surcharge requireAdmin/view/redirect/abort pour éviter les sorties réelles.
 */
class TestableCartAdminController extends \Controller\Admin\CartAdminController
{
    public ?string $lastView     = null;
    public ?array $lastViewData = null;
    public ?int $lastAbortCode = null;

    protected function requireAdmin(): array
    {
        return ['id' => 1, 'role' => 'admin', 'name' => 'Admin Test', 'email' => 'admin@test.local'];
    }

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }

    protected function redirect(string $url, int $status = 302): never
    {
        throw new \RuntimeException('redirect:' . $url);
    }

    protected function abort(int $status = 404, string $message = ''): never
    {
        $this->lastAbortCode = $status;
        throw new \RuntimeException('abort:' . $status);
    }
}
