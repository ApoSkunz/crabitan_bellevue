<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Lang;

class PageController extends Controller
{
    public function chateau(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/chateau', ['lang' => $lang]);
    }

    public function savoirFaire(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/savoir-faire', ['lang' => $lang]);
    }

    public function contact(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/contact', ['lang' => $lang]);
    }

    public function mentionsLegales(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/mentions-legales', ['lang' => $lang, 'noindex' => true]);
    }

    public function planDuSite(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/plan-du-site', ['lang' => $lang, 'noindex' => true]);
    }

    public function webmaster(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/webmaster', ['lang' => $lang, 'noindex' => true]);
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
