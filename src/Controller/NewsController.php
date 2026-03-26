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
        $lang      = $this->resolveLang($params);
        $perPage   = 9;
        $page      = max(1, (int) ($_GET['page'] ?? 1));

        $newsModel  = new NewsModel();
        $total      = $newsModel->countAll();
        $totalPages = (int) ceil($total / $perPage);
        $page       = min($page, max(1, $totalPages));
        $news       = $newsModel->getPaginated($perPage, ($page - 1) * $perPage);

        $this->view('news/index', [
            'lang'       => $lang,
            'news'       => $news,
            'page'       => $page,
            'totalPages' => $totalPages,
        ]);
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

        $this->view('news/show', [
            'lang' => $lang,
            'item' => $item,
            'prev' => $newsModel->getPrev($slug),
            'next' => $newsModel->getNext($slug),
        ]);
    }
}
