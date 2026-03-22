<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;

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
}
