<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Exception\HttpException;
use Repository\UserRepository;

/**
 * Sous-classe testable d'AccountAddressController.
 * Surcharge requireCustomer, verifyCsrf, redirect, view pour isoler les tests.
 */
class TestableAccountAddressController extends \Controller\Account\AccountAddressController
{
    public array $fakePayload   = ['sub' => 1, 'role' => 'customer'];
    public bool $csrfValid      = true;
    public ?string $lastRedirect = null;
    public ?string $lastView     = null;
    public ?array $lastViewData  = null;

    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request, $userRepo);
    }

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
        throw new HttpException($status, 'redirect:' . $url);
    }

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }
}
