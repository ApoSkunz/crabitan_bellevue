<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Lang;
use Model\NewsModel;

class HomeController extends Controller
{
    // ----------------------------------------------------------------
    // GET /  |  GET /fr  |  GET /en
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        // Résolution de la langue : priorité au {lang} de la route,
        // sinon extraction depuis l'URI, sinon DEFAULT_LANG.
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

        $newsModel = new NewsModel();
        $news      = $newsModel->getLatest(3);

        $this->view('home', ['lang' => $lang, 'news' => $news]);
    }
}
