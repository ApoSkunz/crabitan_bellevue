<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\WineModel;

class WineController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/vins
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $color = isset($_GET['color']) && $_GET['color'] !== '' ? $_GET['color'] : null;
        $sort  = $_GET['sort'] ?? 'default';

        $wines = $model->getAll($color, $sort);

        $this->view('wines/index', [
            'lang'        => $lang,
            'wines'       => $wines,
            'activeColor' => $color,
            'activeSort'  => $sort,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/collection
    // ----------------------------------------------------------------

    public function collection(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();

        $winesByColor = $model->getAllByColor();

        $this->view('wines/collection', [
            'lang'         => $lang,
            'winesByColor' => $winesByColor,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/vins/{slug}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $slug  = $params['slug'] ?? '';
        $model = new WineModel();

        $wine = $model->getBySlug($slug);

        if ($wine === null) {
            Response::abort(404);
        }

        $this->view('wines/show', [
            'lang' => $lang,
            'wine' => $wine,
        ]);
    }
}
