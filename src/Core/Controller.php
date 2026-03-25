<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        Response::view($template, $data, $httpStatus);
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

    /**
     * Résout la langue à partir des params de route ou de l'URI.
     * Définit la constante CURRENT_LANG et charge les traductions si ce n'est pas déjà fait.
     */
    protected function resolveLang(array $params): string
    {
        if (isset($params['lang'])) {
            $lang = $params['lang'];
        } else {
            $uri     = rtrim($_SERVER['REQUEST_URI'] ?? '/', '/') ?: '/';
            $segment = explode('/', ltrim($uri, '/'))[0] ?? '';
            $lang    = in_array($segment, SUPPORTED_LANGS, true) ? $segment : DEFAULT_LANG;
        }

        if (!defined('CURRENT_LANG')) {
            define('CURRENT_LANG', $lang);
            Lang::load($lang);
        }

        return $lang;
    }
}
