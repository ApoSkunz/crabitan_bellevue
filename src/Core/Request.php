<?php

declare(strict_types=1);

namespace Core;

/**
 * Encapsule la requête HTTP courante (méthode, URI, corps, en-têtes).
 * Parsée une seule fois à l'initialisation depuis les superglobales PHP.
 */
class Request
{
    public readonly string $method;
    public readonly string $uri;
    public readonly string $path;
    public readonly array $query;
    public readonly array $body;
    public readonly array $headers;

    /**
     * Initialise la requête depuis les superglobales PHP.
     */
    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path    = strtok($this->uri, '?');
        $this->query   = $_GET;
        $this->body    = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    private function parseBody(): array
    {
        if ($this->method === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $json = file_get_contents('php://input');
                return json_decode($json, true) ?? [];
            }
            return $_POST;
        }
        return [];
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retourne un paramètre de query string.
     *
     * @param string $key     Nom du paramètre
     * @param mixed  $default Valeur par défaut si absent
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Retourne un paramètre du corps de la requête POST.
     *
     * @param string $key     Nom du paramètre
     * @param mixed  $default Valeur par défaut si absent
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Retourne un en-tête HTTP (insensible à la casse).
     *
     * @param string $key     Nom de l'en-tête (ex. 'Authorization')
     * @param mixed  $default Valeur par défaut si absent
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Extrait le token Bearer de l'en-tête Authorization.
     *
     * @return string|null Token JWT ou null si absent
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /**
     * Indique si le Content-Type de la requête est application/json.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return str_contains($this->header('content-type', ''), 'application/json');
    }

    /**
     * Indique si la requête est une requête AJAX (X-Requested-With: XMLHttpRequest).
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }
}
