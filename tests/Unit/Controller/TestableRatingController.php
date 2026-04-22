<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

/**
 * Sous-classe testable de RatingController.
 * Surcharge view/json/redirect/abort pour capturer les sorties sans rendu réel.
 */
class TestableRatingController extends \Controller\RatingController
{
    public ?string $lastView = null;
    /** @var array<string, mixed>|null */
    public ?array $lastViewData = null;
    public ?int $lastJsonStatus = null;
    /** @var mixed */
    public mixed $lastJsonData = null;
    public ?string $lastRedirect = null;

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }

    /**
     * @param mixed $data
     * @param int   $status
     * @return never
     */
    protected function json(mixed $data, int $status = 200): never
    {
        $this->lastJsonStatus = $status;
        $this->lastJsonData   = $data;
        throw new \Core\Exception\HttpException($status, 'json');
    }

    protected function redirect(string $url, int $status = 302): never
    {
        $this->lastRedirect = $url;
        throw new \RuntimeException('redirect:' . $url);
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new \Core\Exception\HttpException($status, $message);
    }
}
