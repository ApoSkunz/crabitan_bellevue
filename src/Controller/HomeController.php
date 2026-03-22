<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\NewsModel;

class HomeController extends Controller
{
    // ----------------------------------------------------------------
    // GET /  |  GET /fr  |  GET /en
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);

        $newsModel = new NewsModel();
        $news      = $newsModel->getLatest(3);

        $this->view('home', ['lang' => $lang, 'news' => $news]);
    }
}
