<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\OrderFormModel;

class OrderFormController extends Controller
{
    private const STORAGE_DIR = ROOT_PATH . '/storage/order_forms';

    // ----------------------------------------------------------------
    // GET /bons-de-commande/{id}/telecharger  (accès public)
    // ----------------------------------------------------------------

    public function download(array $params): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $form = (new OrderFormModel())->findByIdOrNull($id);

        if ($form === null) {
            Response::abort(404);
        }

        $path = self::STORAGE_DIR . '/' . $form['filename'];
        if (!is_file($path)) {
            Response::abort(404);
        }

        $label    = $form['label'] !== null ? ' ' . $form['label'] : '';
        $download = 'Bon_commande_' . $form['year'] . $label . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $download . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
