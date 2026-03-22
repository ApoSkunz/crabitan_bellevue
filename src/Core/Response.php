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
        $errorView = defined('SRC_PATH') ? SRC_PATH . '/View/errors/error.php' : null;
        if ($errorView !== null && file_exists($errorView)) {
            $statusCode = $status; // NOSONAR — exposed to included error view via PHP scope (php:S1481)
            include_once $errorView;
        } else {
            echo $message;
        }
        throw new \Core\Exception\HttpException($status, null, $message);
    }

    public static function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        // Injecter $navLang automatiquement pour toutes les vues
        if (!isset($data['navLang'])) {
            $data['navLang'] = $data['lang']
                ?? (defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr'));
        }
        extract($data, EXTR_SKIP);
        require SRC_PATH . '/View/' . $template . '.php'; // NOSONAR — require_once bloquerait le re-rendu en tests
    }

    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }
}
