<?php

declare(strict_types=1);

namespace Core;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        throw new \Core\Exception\HttpException($status);
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: $url");
        throw new \Core\Exception\HttpException($status, $url);
    }

    public static function abort(int $status = 404, string $message = 'Not Found'): never
    {
        http_response_code($status);
        echo $message;
        throw new \Core\Exception\HttpException($status, null, $message);
    }

    public static function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);
        require SRC_PATH . '/View/' . $template . '.php'; // NOSONAR
    }

    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }
}
