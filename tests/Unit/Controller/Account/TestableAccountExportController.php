<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Exception\HttpException;
use Service\MailService;

/**
 * Sous-classe testable d'AccountExportController.
 * Surcharge requireCustomer, verifyCsrf, view, abort, newMailService
 * pour isoler les dépendances externes dans les TU.
 */
class TestableAccountExportController extends \Controller\Account\AccountExportController
{
    public array $fakePayload    = ['sub' => 1, 'role' => 'customer'];
    public bool $csrfValid       = true;
    public ?string $lastView     = null;
    public ?array $lastViewData  = null;

    /** MailService à retourner depuis newMailService(). */
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
