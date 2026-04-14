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

    /**
     * Affiche la liste complète des actualités. Pas de pagination : lazy-load côté client.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);
        $news = (new NewsModel())->getAll();

        $this->view('news/index', [
            'lang' => $lang,
            'news' => $news,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/actualites/{slug}
    // ----------------------------------------------------------------

    /**
     * Affiche le détail d'une actualité identifiée par son slug.
     *
     * @param array<string, string> $params Paramètres de route (lang, slug)
     * @return void
     */
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
