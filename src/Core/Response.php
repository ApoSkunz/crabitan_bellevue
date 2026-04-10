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
        header("Location: $url"); // NOSONAR — phpsecurity:S5146 : url interne (routes MVC uniquement)
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

    /**
     * Affiche la page d'erreur visuelle avec des données supplémentaires accessibles dans la vue.
     *
     * @param int                  $status Code HTTP (ex. 429)
     * @param array<string, mixed> $data   Variables injectées dans la vue error.php (ex. retryMinutes)
     * @param string               $message Message de fallback si la vue est absente
     */
    public static function abortWithData(int $status, array $data = [], string $message = ''): never
    {
        http_response_code($status);
        $errorView = defined('SRC_PATH') ? SRC_PATH . '/View/errors/error.php' : null;
        if ($errorView !== null && file_exists($errorView)) {
            $statusCode = $status; // NOSONAR — exposed to included error view via PHP scope (php:S1481)
            extract($data, EXTR_SKIP);
            include_once $errorView;
        } else {
            echo $message ?: $status;
        }
        throw new \Core\Exception\HttpException($status, null, $message);
    }

    public static function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        // Injecter $navLang automatiquement pour toutes les vues
        if (!isset($data['navLang'])) {
            $default = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
            $fallback = defined('CURRENT_LANG') ? CURRENT_LANG : $default;
            $data['navLang'] = $data['lang'] ?? $fallback;
        }
        extract($data, EXTR_SKIP);
        require SRC_PATH . '/View/' . $template . '.php'; // NOSONAR — require_once bloquerait le re-rendu en tests
    }

    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    /**
     * Force la redirection HTTPS en production.
     *
     * Envoie un header Location 301 vers l'équivalent HTTPS de l'URL courante si :
     * - l'environnement courant est 'production'
     * - La requête n'est pas déjà sur HTTPS ($_SERVER['HTTPS'] !== 'on')
     *
     * Retourne true si une redirection a été émise (l'appelant doit alors exit).
     * Retourne false sans effet en environnement local/dev.
     *
     * @param string|null $env Surcharge de l'environnement (tests uniquement — null = lire APP_ENV)
     * @return bool true si redirection émise, false sinon
     */
    public static function forceHttps(?string $env = null): bool
    {
        $appEnv = $env ?? (defined('APP_ENV') ? APP_ENV : 'development');
        if ($appEnv === 'production' && ($_SERVER['HTTPS'] ?? 'off') !== 'on') {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri  = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: https://' . $host . $uri, true, 301);
            return true;
        }
        return false;
    }
}
