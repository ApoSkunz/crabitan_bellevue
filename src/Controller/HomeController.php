<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\NewsModel;
use Model\ReviewModel;
use Model\WineModel;

class HomeController extends Controller
{
    // ----------------------------------------------------------------
    // GET /  |  GET /fr  |  GET /en
    // ----------------------------------------------------------------

    /**
     * Affiche la page d'accueil avec les dernières actualités.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);

        $newsModel    = new NewsModel();
        $news         = $newsModel->getLatest(3);
        $wineModel    = new WineModel();
        $featuredWine = $wineModel->getFeatured();
        $reviewModel  = new ReviewModel();
        $reviews      = $reviewModel->getRandom(3, 4);
        $reviewStats  = $reviewModel->getStats(4);

        $this->view('home', ['lang' => $lang, 'news' => $news, 'featuredWine' => $featuredWine, 'reviews' => $reviews, 'reviewStats' => $reviewStats]);
    }
}
