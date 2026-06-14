<?php

declare(strict_types=1);

namespace Core;

/**
 * Classe utilitaire statique pour envoyer les réponses HTTP (JSON, redirect, abort, view).
 */
class Response
{
    /**
     * Envoie une réponse JSON et termine l'exécution via HttpException.
     *
     * @param mixed $data   Données à sérialiser
     * @param int   $status Code HTTP
     * @throws \Core\Exception\HttpException Toujours levée pour terminer le flux
     * @return never
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        throw new \Core\Exception\HttpException($status);
    }

    /**
     * Redirige vers une URL et termine l'exécution via HttpException.
     *
     * @param string $url    URL de destination
     * @param int    $status Code HTTP de redirection
     * @throws \Core\Exception\HttpException Toujours levée pour terminer le flux
     * @return never
     */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: $url"); // NOSONAR — phpsecurity:S5146 : url interne (routes MVC uniquement)
        throw new \Core\Exception\HttpException($status, $url);
    }

    /**
     * Interrompt la requête avec un code d'erreur HTTP et affiche la vue d'erreur.
     *
     * @param int    $status  Code HTTP (404, 403, 500…)
     * @param string $message Message de fallback si la vue d'erreur est absente
     * @throws \Core\Exception\HttpException Toujours levée pour terminer le flux
     * @return never
     */
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
            // VULN-003 : filtrer GLOBALS et clés superglobales avant extract pour éviter la pollution de portée
            $filtered = array_filter($data, static fn($k) => $k !== 'GLOBALS' && !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
            extract($filtered, EXTR_SKIP);
            include_once $errorView;
        } else {
            echo $message ?: $status;
        }
        throw new \Core\Exception\HttpException($status, null, $message);
    }

    /**
     * Affiche un template PHP en injectant les données et en définissant le code HTTP.
     *
     * @param string               $template   Chemin relatif du template (sans extension)
     * @param array<string, mixed> $data       Variables injectées dans la vue via extract()
     * @param int                  $httpStatus Code HTTP de la réponse
     * @return void
     */
    public static function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        // Injecter $navLang automatiquement pour toutes les vues
        if (!isset($data['navLang'])) {
            $default = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
            $fallback = defined('CURRENT_LANG') ? CURRENT_LANG : $default;
            $data['navLang'] = $data['lang'] ?? $fallback;
        }
        // VULN-003 : filtrer GLOBALS et clés superglobales avant extract pour éviter la pollution de portée
        $filtered = array_filter($data, static fn($k) => $k !== 'GLOBALS' && !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
        extract($filtered, EXTR_SKIP);
        require SRC_PATH . '/View/' . $template . '.php'; // NOSONAR — require_once bloquerait le re-rendu en tests
    }

    /**
     * Définit un en-tête HTTP personnalisé.
     *
     * @param string $name  Nom de l'en-tête
     * @param string $value Valeur de l'en-tête
     * @return void
     */
    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }
}
