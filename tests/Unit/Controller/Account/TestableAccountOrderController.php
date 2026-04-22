<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Core\Exception\HttpException;
use Service\PaymentService;
use Service\RefundService;

/**
 * Sous-classe testable d'AccountOrderController.
 * Surcharge requireCustomer, verifyCsrf, redirect, view, abort, resolveLang
 * ainsi que les seams de services pour éviter toute dépendance externe.
 */
class TestableAccountOrderController extends \Controller\Account\AccountOrderController
{
    public array $fakePayload    = ['sub' => 1, 'role' => 'customer'];
    public bool $csrfValid      = true;
    public ?string $lastRedirect  = null;
    public ?string $lastView      = null;
    public ?array $lastViewData  = null;

    /** @var PaymentService|null */
    public ?PaymentService $fakePaymentService = null;

    /** @var RefundService|null */
    public ?RefundService $fakeRefundService = null;

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
        throw new HttpException($status, $url);
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

    protected function resolveLang(array $params): string
    {
        return $params['lang'] ?? 'fr';
    }

    protected function sendPdfResponse(string $pdfBytes, string $filename): never
    {
        throw new HttpException(200, 'pdf:' . $filename);
    }

    protected function newPaymentService(): PaymentService
    {
        return $this->fakePaymentService ?? parent::newPaymentService();
    }

    protected function newRefundService(): RefundService
    {
        return $this->fakeRefundService ?? parent::newRefundService();
    }
}
