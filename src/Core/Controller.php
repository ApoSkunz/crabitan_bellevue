<?php

declare(strict_types=1);

namespace Core;

/**
 * Classe de base pour tous les contrôleurs MVC.
 * Fournit les méthodes utilitaires de réponse (view, json, redirect, abort)
 * et la résolution de langue à partir de la route.
 */
abstract class Controller
{
    /**
     * Initialise le contrôleur avec la requête courante.
     *
     * @param Request $request Requête HTTP courante
     */
    public function __construct(protected Request $request)
    {
    }

    /**
     * Affiche un template PHP en injectant les données fournies.
     *
     * @param string               $template   Chemin relatif du template (sans extension)
     * @param array<string, mixed> $data       Variables injectées dans la vue
     * @param int                  $httpStatus Code HTTP de la réponse
     * @return void
     */
    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        Response::view($template, $data, $httpStatus);
    }

    /**
     * Envoie une réponse JSON et termine l'exécution.
     *
     * @param mixed $data   Données à sérialiser en JSON
     * @param int   $status Code HTTP de la réponse
     * @return never
     */
    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /**
     * Redirige vers une URL et termine l'exécution.
     *
     * @param string $url    URL de destination
     * @param int    $status Code HTTP de redirection (301, 302…)
     * @return never
     */
    protected function redirect(string $url, int $status = 302): never
    {
        Response::redirect($url, $status);
    }

    /**
     * Interrompt la requête avec un code d'erreur HTTP.
     *
     * @param int    $status  Code HTTP (404, 403, 500…)
     * @param string $message Message de fallback si la vue d'erreur est absente
     * @return never
     */
    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        Response::abort($status, $message);
    }

    /**
     * Retourne la langue courante définie par la constante CURRENT_LANG.
     *
     * @return string Code langue (ex. 'fr', 'en')
     */
    protected function lang(): string
    {
        return defined('CURRENT_LANG') ? CURRENT_LANG : DEFAULT_LANG;
    }

    /**
     * Résout la langue à partir des params de route ou de l'URI.
     * Définit la constante CURRENT_LANG et charge les traductions si ce n'est pas déjà fait.
     *
     * @param array<string, mixed> $params Paramètres de route (peut contenir 'lang')
     * @return string Code langue résolu
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
