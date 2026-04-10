<?php

declare(strict_types=1);

namespace Core;

/**
 * Routeur HTTP du framework MVC.
 * Enregistre les routes GET/POST et les dispatche vers les contrôleurs.
 */
class Router
{
    private array $routes = [];

    /**
     * Initialise le routeur avec la requête courante.
     *
     * @param Request $request Requête HTTP courante
     */
    public function __construct(private Request $request)
    {
    }

    /**
     * Enregistre une route GET.
     *
     * @param string $path   Chemin de la route (ex. '/fr/vins/{slug}')
     * @param string $action Action au format 'Controller@method'
     * @return void
     */
    public function get(string $path, string $action): void
    {
        $this->addRoute('GET', $path, $action);
    }

    /**
     * Enregistre une route POST.
     *
     * @param string $path   Chemin de la route (ex. '/api/login')
     * @param string $action Action au format 'Controller@method'
     * @return void
     */
    public function post(string $path, string $action): void
    {
        $this->addRoute('POST', $path, $action);
    }

    private function addRoute(string $method, string $path, string $action): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'pattern' => $this->buildPattern($path),
            'action'  => $action,
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatche la requête courante vers le contrôleur correspondant.
     * Appelle Response::abort(404) si aucune route ne correspond.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $method = $this->request->method;
        $path   = rtrim($this->request->path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            // Extraction des paramètres de route nommés
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Détection de la langue depuis l'URL
            if (isset($params['lang'])) {
                $this->setLang($params['lang']);
            }

            $this->callAction($route['action'], $params);
            return;
        }

        Response::abort(404);
    }

    private function setLang(string $lang): void
    {
        $lang = in_array($lang, SUPPORTED_LANGS) ? $lang : DEFAULT_LANG;
        defined('CURRENT_LANG') || define('CURRENT_LANG', $lang);
        Lang::load($lang);
    }

    private function callAction(string $action, array $params): void
    {
        [$class, $method] = explode('@', $action);

        // Résolution du namespace complet
        $namespace = 'Controller\\' . $class;

        if (!class_exists($namespace)) {
            Response::abort(500, "Controller $namespace introuvable");
        }

        $controller = new $namespace($this->request);

        if (!method_exists($controller, $method)) {
            Response::abort(500, "Méthode $method introuvable dans $namespace");
        }

        $controller->$method($params);
    }
}
