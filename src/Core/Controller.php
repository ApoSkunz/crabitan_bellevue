<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    public function __construct(protected Request $request) {}

    protected function view(string $template, array $data = [], int $status = 200): void
    {
        Response::view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): never
    {
        Response::redirect($url, $status);
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        Response::abort($status, $message);
    }

    protected function lang(): string
    {
        return defined('CURRENT_LANG') ? CURRENT_LANG : DEFAULT_LANG;
    }
}
