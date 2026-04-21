<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Sous-classe testable de ReviewController.
 * Surcharge redirect/view/abort/resolveLang pour éviter les sorties réelles.
 */
class TestableReviewController extends \Controller\ReviewController
{
    public ?string $lastRedirect  = null;
    public ?string $lastView      = null;
    public ?array $lastViewData   = null;
    public ?int $lastAbortCode    = null;

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

    protected function abort(int $status = 404, string $message = ''): never
    {
        $this->lastAbortCode = $status;
        throw new \RuntimeException('abort:' . $status);
    }

    protected function resolveLang(array $params): string
    {
        return $params['lang'] ?? 'fr';
    }
}
