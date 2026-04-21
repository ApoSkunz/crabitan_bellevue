<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

/**
 * Sous-classe testable d'AccountRatingsController.
 * Surcharge requireCustomer, verifyCsrf, redirect, view pour éviter les sorties réelles.
 */
class TestableAccountRatingsController extends \Controller\Account\AccountRatingsController
{
    public array $fakePayload   = ['sub' => 1, 'role' => 'customer'];
    public bool $csrfValid      = true;
    public ?string $lastRedirect = null;
    public ?string $lastView     = null;
    public ?array $lastViewData  = null;

    protected function requireCustomer(): array
    {
        return $this->fakePayload;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function redirect(string $url, int $status = 302): never
    {
        $this->lastRedirect = $url;
        throw new \RuntimeException('redirect:' . $url);
    }

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }
}
