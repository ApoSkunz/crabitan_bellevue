<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Exception\HttpException;
use Service\MailService;

/**
 * Sous-classe testable d'AccountSecurityController.
 * Surcharge requireCustomer, verifyCsrf, view, abort, newMailService
 * pour éviter les sorties réelles et les appels SMTP.
 */
class TestableAccountSecurityController extends \Controller\Account\AccountSecurityController
{
    public array $fakePayload    = ['sub' => 1, 'role' => 'customer'];
    public bool $csrfValid       = true;
    public ?string $lastRedirect  = null;
    public ?string $lastView      = null;
    public ?array $lastViewData   = null;

    /** @var MailService|null MailService injecté pour les tests (null → réel). */
    public ?MailService $fakeMailService = null;

    protected function requireCustomer(): array
    {
        return $this->fakePayload;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new HttpException($status, $message);
    }

    protected function newMailService(): MailService
    {
        return $this->fakeMailService ?? parent::newMailService();
    }
}
