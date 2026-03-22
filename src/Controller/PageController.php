<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;

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

    public function support(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/support', ['lang' => $lang]);
    }

    public function jeux(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/jeux', ['lang' => $lang]);
    }

    public function webmaster(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/webmaster', ['lang' => $lang, 'noindex' => true]);
    }
}
