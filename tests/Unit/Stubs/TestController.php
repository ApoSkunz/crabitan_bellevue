<?php

declare(strict_types=1);

namespace Tests\Unit\Stubs;

use Core\Controller;
use Core\Exception\HttpException;

/**
 * Concrete stub exposing protected Controller methods for unit testing.
 */
class TestController extends Controller
{
    public function callJson(mixed $data, int $status = 200): never
    {
        $this->json($data, $status);
    }

    public function callRedirect(string $url, int $status = 302): never
    {
        $this->redirect($url, $status);
    }

    public function callAbort(int $status = 404, string $message = 'Not Found'): never
    {
        $this->abort($status, $message);
    }

    public function callLang(): string
    {
        return $this->lang();
    }
}
