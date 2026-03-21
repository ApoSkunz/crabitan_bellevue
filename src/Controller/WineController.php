<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Lang;

class WineController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/vins
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('wines/index', ['lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/collection
    // ----------------------------------------------------------------

    public function collection(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('wines/collection', ['lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/{slug}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $lang = $this->resolveLang($params);
        $slug = $params['slug'] ?? '';

        $this->view('wines/show', ['lang' => $lang, 'slug' => $slug]);
    }

    // ----------------------------------------------------------------

    private function resolveLang(array $params): string
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
