<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\NewsModel;

class NewsController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/actualites
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);

        $newsModel = new NewsModel();
        $news      = $newsModel->getAll();

        $this->view('news/index', ['lang' => $lang, 'news' => $news]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/actualites/{slug}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $lang = $this->resolveLang($params);
        $slug = $params['slug'] ?? '';

        $newsModel = new NewsModel();
        $item      = $newsModel->getBySlug($slug);

        if ($item === null) {
            $this->abort(404);
        }

        $this->view('news/show', ['lang' => $lang, 'item' => $item]);
    }
}
